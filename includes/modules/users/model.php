<?php
/**
 * Users Module Model
 * 
 * Handles user data retrieval with strict Role Hierarchy:
 * - Super Admin: Sees everyone
 * - Admin: Sees everyone in Customer (including other Admins/Super Managers)
 * - Super Manager: Sees Super Managers, Managers, Terminals. HIDES Admins & Super Admins
 * - Manager: Sees Managers, Terminals in their scope. HIDES Super Managers, Admins, Super Admins
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     6.0.0 - REFACTORED: Tab filtering by role, get_tab_counts()
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_Users_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = isset($config['cache']['ttl']) ? $config['cache']['ttl'] : 1800;
    }
    
    // =========================================================================
    // CRUD
    // =========================================================================
    
    public function create($data) {
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            $data['customer_id'] = null;
        } else {
            if (empty($data['customer_id'])) {
                $data['customer_id'] = SAW_Context::get_customer_id();
            }
            if (!$data['customer_id']) {
                return new WP_Error('missing_customer', 'Customer ID is required');
            }
        }
        
        return parent::create($data);
    }
    
    public function update($id, $data) {
        return parent::update($id, $data);
    }
    
    public function delete($id) {
        return parent::delete($id);
    }

    // =========================================================================
    // MAIN FILTERING LOGIC
    // =========================================================================

    /**
     * Get all users with ROLE HIERARCHY & BRANCH LOGIC
     * 
     * @param array $filters Filters including:
     *   - search: Search string
     *   - tab: Filter by role (for tabs) - 'all', 'admin', 'super_manager', 'manager', 'terminal'
     *   - branch_id: Filter by branch
     *   - department_id: Filter by department (M:N relation)
     *   - is_active: Filter by status
     *   - orderby, order, page, per_page: Pagination
     * @return array
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        // 1. Super Admin (WP) sees everything
        if (current_user_can('manage_options')) {
            return $this->get_all_filtered($filters, true);
        }

        // 2. Get context
        $context_customer_id = SAW_Context::get_customer_id();
        $context_branch_id = SAW_Context::get_branch_id();
        $current_user_id = SAW_Context::get_saw_user_id();
        $current_role = SAW_Context::get_role();

        // Without customer - see nothing
        if (!$context_customer_id) {
            return array('items' => array(), 'total' => 0);
        }

        return $this->get_all_filtered($filters, false, array(
            'customer_id' => $context_customer_id,
            'branch_id' => $context_branch_id,
            'user_id' => $current_user_id,
            'role' => $current_role,
        ));
    }

    /**
     * Get all users with filters (internal method)
     */
    private function get_all_filtered($filters, $is_super_admin = false, $context = array()) {
        global $wpdb;
        
        $where = array('1=1');
        $params = array();
        $join = '';
        
        // =================================================================
        // CONTEXT FILTERING (Customer & Role Hierarchy)
        // =================================================================
        
        if (!$is_super_admin && !empty($context['customer_id'])) {
            $where[] = 'u.customer_id = %d';
            $params[] = $context['customer_id'];
            
            // Role hierarchy - who can see whom
            $current_role = isset($context['role']) ? $context['role'] : '';
            
            if ($current_role === 'super_manager') {
                $where[] = "u.role NOT IN ('super_admin', 'admin')";
            } elseif ($current_role === 'manager') {
                $where[] = "u.role NOT IN ('super_admin', 'admin', 'super_manager')";
            } elseif ($current_role === 'terminal') {
                $where[] = '1=0';
            }
            
            // Branch filtering from context (switcher)
            if (!empty($context['branch_id'])) {
                $branch_where = '(u.branch_id = %d OR u.branch_id IS NULL';
                $params[] = $context['branch_id'];
                
                if (!empty($context['user_id'])) {
                    $branch_where .= ' OR u.id = %d';
                    $params[] = $context['user_id'];
                }
                $branch_where .= ')';
                $where[] = $branch_where;
            }
        }
        
        // =================================================================
        // URL FILTERS (from request)
        // =================================================================
        
        // ✅ Tab filtering - convert tab value to role filter
        if (!empty($filters['tab']) && $filters['tab'] !== 'all') {
            $where[] = 'u.role = %s';
            $params[] = $filters['tab'];
        }
        
        // Filter: Branch (from URL dropdown)
        if (!empty($filters['branch_id'])) {
            $where[] = '(u.branch_id = %d OR u.branch_id IS NULL)';
            $params[] = intval($filters['branch_id']);
        }
        
        // Filter: Department (M:N relation via saw_user_departments)
        if (!empty($filters['department_id'])) {
            $join = " INNER JOIN {$wpdb->prefix}saw_user_departments ud ON u.id = ud.user_id";
            $where[] = 'ud.department_id = %d';
            $params[] = intval($filters['department_id']);
        }
        
        // Filter: Status
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'u.is_active = %d';
            $params[] = intval($filters['is_active']);
        }
        
        // Search
        if (!empty($filters['search'])) {
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(u.first_name LIKE %s OR u.last_name LIKE %s OR u.position LIKE %s)';
            $params[] = $search_value;
            $params[] = $search_value;
            $params[] = $search_value;
        }
        
        // =================================================================
        // BUILD & EXECUTE QUERY
        // =================================================================
        
        $where_sql = implode(' AND ', $where);
        
        // Count query
        $count_sql = "SELECT COUNT(DISTINCT u.id) FROM {$this->table} u {$join} WHERE {$where_sql}";
        $total = !empty($params) 
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) 
            : (int) $wpdb->get_var($count_sql);
        
        // Order
        $orderby = isset($filters['orderby']) ? $filters['orderby'] : 'last_name';
        $order = isset($filters['order']) ? strtoupper($filters['order']) : 'ASC';
        $valid_cols = array('id', 'first_name', 'last_name', 'role', 'created_at', 'last_login', 'is_active');
        if (!in_array($orderby, $valid_cols)) {
            $orderby = 'last_name';
        }
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        // Pagination
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? max(1, intval($filters['per_page'])) : 20;
        $offset = ($page - 1) * $per_page;
        
        // Main query
        $sql = "SELECT DISTINCT u.* FROM {$this->table} u {$join} WHERE {$where_sql} ORDER BY u.{$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        return array(
            'items' => $items ? $items : array(),
            'total' => $total,
        );
    }
    
    /**
     * Get counts for each tab (by role)
     * 
     * ✅ Same pattern as departments module
     * 
     * @return array Tab key => count
     */
    public function get_tab_counts() {
        global $wpdb;
        
        $where = array('1=1');
        $params = array();
        
        // Apply same context filtering as get_all
        if (!current_user_can('manage_options')) {
            $context_customer_id = SAW_Context::get_customer_id();
            $context_branch_id = SAW_Context::get_branch_id();
            $current_user_id = SAW_Context::get_saw_user_id();
            $current_role = SAW_Context::get_role();
            
            if (!$context_customer_id) {
                return array(
                    'all' => 0,
                    'admin' => 0,
                    'super_manager' => 0,
                    'manager' => 0,
                    'terminal' => 0,
                );
            }
            
            $where[] = 'customer_id = %d';
            $params[] = $context_customer_id;
            
            // Role hierarchy
            if ($current_role === 'super_manager') {
                $where[] = "role NOT IN ('super_admin', 'admin')";
            } elseif ($current_role === 'manager') {
                $where[] = "role NOT IN ('super_admin', 'admin', 'super_manager')";
            } elseif ($current_role === 'terminal') {
                $where[] = '1=0';
            }
            
            // Branch context
            if ($context_branch_id) {
                $branch_where = '(branch_id = %d OR branch_id IS NULL';
                $params[] = $context_branch_id;
                
                if ($current_user_id) {
                    $branch_where .= ' OR id = %d';
                    $params[] = $current_user_id;
                }
                $branch_where .= ')';
                $where[] = $branch_where;
            }
        }
        
        $where_sql = implode(' AND ', $where);
        
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin,
                SUM(CASE WHEN role = 'super_manager' THEN 1 ELSE 0 END) as super_manager,
                SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as manager,
                SUM(CASE WHEN role = 'terminal' THEN 1 ELSE 0 END) as terminal
            FROM {$this->table}
            WHERE {$where_sql}
        ";
        
        $counts = !empty($params)
            ? $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_row($sql, ARRAY_A);
        
        return array(
            'all' => (int) (isset($counts['total']) ? $counts['total'] : 0),
            'admin' => (int) (isset($counts['admin']) ? $counts['admin'] : 0),
            'super_manager' => (int) (isset($counts['super_manager']) ? $counts['super_manager'] : 0),
            'manager' => (int) (isset($counts['manager']) ? $counts['manager'] : 0),
            'terminal' => (int) (isset($counts['terminal']) ? $counts['terminal'] : 0),
        );
    }
    
    // =========================================================================
    // VALIDATION
    // =========================================================================
    
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email je povinný';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Neplatný formát emailu';
        } elseif ($this->email_exists($data['email'], $id)) {
            $errors['email'] = 'Email již existuje';
        }
        
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Jméno je povinné';
        }
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Příjmení je povinné';
        }
        if (empty($data['role'])) {
            $errors['role'] = 'Role je povinná';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if email exists
     */
    private function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        if (empty($email)) {
            return false;
        }
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND id != %d",
            $email,
            $exclude_id
        ));
    }
    
    /**
     * Check if user is used in system (cannot be deleted)
     */
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables = array(
            'saw_visits' => 'created_by',
            'saw_invitations' => 'created_by',
        );
        
        foreach ($tables as $table => $column) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) === $full_table) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$full_table} WHERE {$column} = %d",
                    $id
                ));
                if ($count > 0) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get users by customer
     */
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = array(
            'customer_id' => $customer_id,
            'orderby' => 'first_name',
            'order' => 'ASC',
        );
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        return $this->get_all($filters);
    }
}