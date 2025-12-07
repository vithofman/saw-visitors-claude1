<?php
/**
 * Departments Module Model
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     5.0.0 - REFACTORED: Added tab filtering, get_tab_counts()
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_Departments_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     */
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = 300;
    }
    
    /**
     * Validate department data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        // Check duplicate department number within the same branch
        if (!empty($data['department_number']) && $this->department_number_exists($data['customer_id'], $data['branch_id'], $data['department_number'], $id)) {
            $errors['department_number'] = 'Duplicitní číslo oddělení';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if department number exists
     */
    private function department_number_exists($customer_id, $branch_id, $department_number, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($department_number)) {
            return false;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE customer_id = %d AND branch_id = %d AND department_number = %s AND id != %d",
            $customer_id,
            $branch_id,
            $department_number,
            $exclude_id
        ));
    }
    
    /**
     * Get by ID with formatting
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if ($item) {
            // Format dates
            if (!empty($item['created_at'])) {
                $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
            }
            if (!empty($item['updated_at'])) {
                $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
            }
        }
        
        return $item;
    }
    
    /**
     * Override get_all with custom tab filtering
     * 
     * Converts tab filter values (active/inactive) to is_active column values (1/0)
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        // Get scope
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        if (!$customer_id && !saw_is_super_admin()) {
            return array('items' => array(), 'total' => 0);
        }

        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = array();

        // Customer filter
        if ($customer_id) {
            $sql .= " AND customer_id = %d";
            $params[] = $customer_id;
        }
        
        // Branch filter
        if ($branch_id) {
            $sql .= " AND branch_id = %d";
            $params[] = $branch_id;
        }
        
        // ✅ Tab filtering - convert 'active'/'inactive' to is_active = 1/0
        if (!empty($filters['tab'])) {
            switch ($filters['tab']) {
                case 'active':
                    $sql .= " AND is_active = 1";
                    break;
                case 'inactive':
                    $sql .= " AND is_active = 0";
                    break;
                // 'all' or other values = no filter
            }
        }
        
        // Search
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? array('name', 'department_number');
            $search_conditions = array();
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "`{$field}` LIKE %s";
                $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            }
            
            if (!empty($search_conditions)) {
                $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
            }
        }
        
        // Count before pagination
        $count_sql = preg_replace('/^SELECT \*/', 'SELECT COUNT(*)', $sql);
        $total = !empty($params) 
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : (int) $wpdb->get_var($count_sql);
        
        // Order
        $orderby = !empty($filters['orderby']) ? sanitize_key($filters['orderby']) : 'name';
        $order = !empty($filters['order']) ? strtoupper($filters['order']) : 'ASC';
        
        $allowed_orderby = array('id', 'name', 'department_number', 'created_at', 'updated_at');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY `{$orderby}` {$order}";
        
        // Pagination
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? max(1, intval($filters['per_page'])) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        // Execute
        $items = !empty($params) 
            ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);
        
        return array(
            'items' => $items ?: array(),
            'total' => $total,
        );
    }
    
    /**
     * Get counts for each tab
     * 
     * @return array Tab key => count
     */
    public function get_tab_counts() {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        if (!$customer_id && !saw_is_super_admin()) {
            return array('all' => 0, 'active' => 0, 'inactive' => 0);
        }
        
        $where_parts = array();
        $params = array();
        
        if ($customer_id) {
            $where_parts[] = "customer_id = %d";
            $params[] = $customer_id;
        }
        
        if ($branch_id) {
            $where_parts[] = "branch_id = %d";
            $params[] = $branch_id;
        }
        
        $where = !empty($where_parts) 
            ? "WHERE " . implode(' AND ', $where_parts) 
            : "";
        
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            FROM {$this->table}
            {$where}
        ";
        
        $counts = !empty($params)
            ? $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_row($sql, ARRAY_A);
        
        return array(
            'all' => (int) ($counts['total'] ?? 0),
            'active' => (int) ($counts['active'] ?? 0),
            'inactive' => (int) ($counts['inactive'] ?? 0),
        );
    }
}