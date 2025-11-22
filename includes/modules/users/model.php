<?php
/**
 * Users Module Model - FINAL HIERARCHY FIX
 * * Handles user data retrieval with strict Role Hierarchy:
 * - Super Admin: Sees everyone.
 * - Admin: Sees everyone in Customer (including other Admins/Super Managers).
 * - Super Manager: Sees Super Managers, Managers, Terminals. HIDES Admins & Super Admins.
 * - Manager: Sees Managers, Terminals in their scope. HIDES Super Managers, Admins, Super Admins.
 * * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     5.7.0
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
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    // --- CRUD ---
    
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
        
        $result = parent::create($data);
        // ✅ Base Model už volá invalidate_cache() automaticky
        return $result;
    }
    
    public function update($id, $data) {
        $result = parent::update($id, $data);
        // ✅ Base Model už volá invalidate_cache() automaticky
        return $result;
    }
    
    public function delete($id) {
        $result = parent::delete($id);
        // ✅ Base Model už volá invalidate_cache() automaticky
        return $result;
    }

    // --- HLAVNÍ LOGIKA FILTROVÁNÍ ---

    /**
     * Get all users with ROLE HIERARCHY & BRANCH LOGIC
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        // 1. Super Admin (WP) vidí vše (použije Base Model logiku)
        if (current_user_can('manage_options')) {
            return parent::get_all($filters);
        }

        // 2. Získat kontext
        $context_customer_id = SAW_Context::get_customer_id();
        $context_branch_id = SAW_Context::get_branch_id();
        $current_user_id = SAW_Context::get_saw_user_id();
        $current_role = SAW_Context::get_role();

        // Bez zákazníka nevidí nic
        if (!$context_customer_id) {
            return array('items' => array(), 'total' => 0);
        }

        // 3. Základní SQL
        $sql = "SELECT * FROM {$this->table} WHERE customer_id = %d";
        $params = array($context_customer_id);

        // =========================================================
        // 4. HIERARCHIE ROLÍ (Kdo koho vidí)
        // =========================================================
        
        // Super Admin: Vidí vše (vyřešeno nahoře)
        
        // Admin: Vidí vše v rámci zákazníka (včetně jiných adminů)
        if ($current_role === 'admin') {
             // Admin vidí vše, nic neskrýváme (kromě Super Admina, který nemá customer_id, takže je skrytý už základním dotazem)
        }
        // Super Manager: Nevidí Adminy ani Super Adminy
        elseif ($current_role === 'super_manager') {
             $sql .= " AND role NOT IN ('super_admin', 'admin')";
        }
        // Manager: Nevidí Super Managery, Adminy, ani Super Adminy
        elseif ($current_role === 'manager') {
             $sql .= " AND role NOT IN ('super_admin', 'admin', 'super_manager')";
        }
        // Terminal: Nevidí nikoho (obvykle)
        elseif ($current_role === 'terminal') {
             $sql .= " AND 1=0"; 
        }

        // =========================================================
        // 5. FILTR POBOČKY (Switcher nebo Pevná)
        // =========================================================
        
        if ($context_branch_id) {
            // Pokud filtrujeme podle pobočky:
            // 1. Zobrazit uživatele, kteří patří do této pobočky (branch_id = X)
            // 2. Zobrazit uživatele, kteří jsou "globální" pro zákazníka (branch_id IS NULL)
            //    - To jsou typicky Admini a Super Manageři bez pobočky
            // 3. VŽDY zobrazit sebe sama (pojistka)
            
            $sql .= " AND (branch_id = %d OR branch_id IS NULL";
            $params[] = $context_branch_id;

            if ($current_user_id) {
                $sql .= " OR id = %d";
                $params[] = $current_user_id;
            }
            $sql .= ")";
        } else {
            // Pokud je vybráno "Všechny pobočky" (switcher je 0/NULL), vidíme vše v rámci zákazníka.
            // Logika rolí (bod 4) už vyfiltrovala nadřízené, takže je to bezpečné.
        }

        // =========================================================
        // 6. SEARCH & DALŠÍ FILTRY
        // =========================================================
        
        // Search
        if (!empty($filters['search'])) {
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            $sql .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR position LIKE %s)";
            $params[] = $search_value;
            $params[] = $search_value;
            $params[] = $search_value;
            $params[] = $search_value;
        }

        // Filter: Role
        if (!empty($filters['role'])) {
            $sql .= " AND role = %s";
            $params[] = $filters['role'];
        }

        // Filter: Active
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND is_active = %d";
            $params[] = (int)$filters['is_active'];
        }

        // Count
        $count_sql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));

        // Order & Pagination
        $orderby = $filters['orderby'] ?? 'id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        $valid_cols = ['id', 'first_name', 'last_name', 'email', 'role', 'created_at'];
        if (!in_array($orderby, $valid_cols)) $orderby = 'id';
        
        $sql .= " ORDER BY {$orderby} {$order}";

        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? max(1, intval($filters['per_page'])) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        // Execute
        $items = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return array(
            'items' => $items,
            'total' => $total
        );
    }
    
    // --- HELPERS (Validation, etc.) ---
    
    public function validate($data, $id = 0) {
        $errors = array();
        if (empty($data['email'])) $errors['email'] = 'Email je povinný';
        elseif (!is_email($data['email'])) $errors['email'] = 'Neplatný formát';
        elseif ($this->email_exists($data['email'], $id)) $errors['email'] = 'Email již existuje';
        
        if (empty($data['first_name'])) $errors['first_name'] = 'Jméno je povinné';
        if (empty($data['last_name'])) $errors['last_name'] = 'Příjmení je povinné';
        if (empty($data['role'])) $errors['role'] = 'Role je povinná';
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    private function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        if (empty($email)) return false;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND id != %d", $email, $exclude_id));
    }
    
    public function is_used_in_system($id) {
        global $wpdb;
        $tables = ['saw_visits' => 'created_by', 'saw_invitations' => 'created_by'];
        foreach ($tables as $tbl => $col) {
            $ft = $wpdb->prefix . $tbl;
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ft)) === $ft) {
                if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ft} WHERE {$col} = %d", $id)) > 0) return true;
            }
        }
        return false;
    }

    public function get_by_customer($customer_id, $active_only = false) {
        $filters = ['customer_id' => $customer_id, 'orderby' => 'first_name', 'order' => 'ASC'];
        if ($active_only) $filters['is_active'] = 1;
        return $this->get_all($filters);
    }

}