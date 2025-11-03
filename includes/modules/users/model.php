<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Users Module Model - FINÁLNÍ OPRAVA
 * 
 * ✅ ŘEŠENÍ:
 * - SuperAdmin: NEvolá parent::get_all() s customer_id filtrem
 * - SuperAdmin: Dělá vlastní SQL dotaz BEZ customer_id filtru
 * - Admin: Volá parent s customer_id filtrem (standardní chování)
 * 
 * @package SAW_Visitors
 * @version 1.0.3
 */
class SAW_Module_Users_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * ✅ FINÁLNÍ OPRAVA: get_all() pro uživatele
     * 
     * PROBLÉM:
     * - Base Model automaticky přidává WHERE customer_id = X
     * - To vyloučí superadminy (mají customer_id = NULL)
     * 
     * ŘEŠENÍ:
     * - Pro SuperAdmina: Vlastní SQL bez customer_id filtru
     * - Pro ostatní: Standardní parent::get_all() s filtrem
     */
    public function get_all($args = []) {
        global $wpdb;
        
        // Session pro customer_id
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $session_customer_id = $_SESSION['saw_current_customer_id'] ?? null;
        
        // Zjisti jestli je SuperAdmin
        $current_user = wp_get_current_user();
        $is_super_admin = in_array('administrator', $current_user->roles, true);
        
        // ============================================================
        // SUPER ADMIN: Vlastní SQL BEZ customer_id filtru
        // ============================================================
        if ($is_super_admin && !empty($this->config['filter_by_customer'])) {
            return $this->get_all_for_super_admin($args);
        }
        
        // ============================================================
        // OSTATNÍ ROLE: Standardní filtrování přes parent
        // ============================================================
        if (!empty($this->config['filter_by_customer'])) {
            if ($session_customer_id) {
                $args['customer_id'] = (int) $session_customer_id;
            } else {
                // Pokud nemá customer_id, nevidí nikoho
                $args['customer_id'] = -1;
            }
        }
        
        return parent::get_all($args);
    }
    
    /**
     * ✅ Speciální metoda pro SuperAdmina
     * 
     * Vrací VŠECHNY uživatele (včetně superadminů)
     * NEPOUŽÍVÁ parent::get_all() aby se vyhnul customer_id filtru
     */
    private function get_all_for_super_admin($filters = []) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Search
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['first_name', 'last_name', 'email'];
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "{$field} LIKE %s";
            }
            
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            
            foreach ($search_fields as $field) {
                $params[] = $search_value;
            }
            
            $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
        }
        
        // Další filtry z configu
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                $sql .= " AND {$filter_key} = %s";
                $params[] = $filters[$filter_key];
            }
        }
        
        // Apply prepare
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        // Ordering
        $orderby = $filters['orderby'] ?? 'created_at';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (in_array($order, ['ASC', 'DESC'])) {
            $sql .= " ORDER BY {$orderby} {$order}";
        }
        
        // Total count
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        // Pagination
        $limit = intval($filters['per_page'] ?? 20);
        $page = intval($filters['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        return [
            'items' => $results,
            'total' => $total
        ];
    }
    
    /**
     * Validace
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email je povinný';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Neplatný formát emailu';
        } elseif ($this->email_exists($data['email'], $id)) {
            $errors['email'] = 'Uživatel s tímto emailem již existuje';
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
        
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                $errors['pin'] = 'PIN musí být 4 čísla';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    private function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($email)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND id != %d",
            $email,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    public function get_by_id($id) {
        $user = parent::get_by_id($id);
        
        if (!$user) {
            return null;
        }
        
        // Pro managery načti oddělení
        if ($user['role'] === 'manager') {
            global $wpdb;
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $id
            ), ARRAY_A);
            
            $user['department_ids'] = array_column($departments, 'department_id');
        }
        
        return $user;
    }
    
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = [
            'saw_visits' => 'created_by',
            'saw_invitations' => 'created_by',
        ];
        
        foreach ($tables_to_check as $table => $column) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
                continue;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$full_table} WHERE {$column} = %d",
                $id
            ));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
}