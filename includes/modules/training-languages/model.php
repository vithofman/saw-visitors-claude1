<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Training_Languages_Model extends SAW_Base_Model 
{
    private $branches_table;
    
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->branches_table = $wpdb->prefix . 'saw_training_language_branches';
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['language_code'])) {
            $errors['language_code'] = 'Kód jazyka je povinný';
        }
        
        if (empty($data['language_name'])) {
            $errors['language_name'] = 'Název jazyka je povinný';
        }
        
        if (empty($data['flag_emoji'])) {
            $errors['flag_emoji'] = 'Vlajka je povinná';
        }
        
        if (!empty($data['language_code']) && $this->code_exists($data['language_code'], $id, $data['customer_id'] ?? 0)) {
            $errors['language_code'] = 'Jazyk s tímto kódem již existuje';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    private function code_exists($code, $exclude_id = 0, $customer_id = 0) {
        global $wpdb;
        
        if (empty($code)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE language_code = %s AND customer_id = %d AND id != %d",
            $code,
            $customer_id,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    public function create($data) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
        
        if (!$customer_id) {
            global $wpdb;
            $customer_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1");
        }
        
        $data['customer_id'] = $customer_id;
        
        $branches_data = $data['branches'] ?? [];
        unset($data['branches']);
        
        $language_id = parent::create($data);
        
        if ($language_id && !empty($branches_data)) {
            $this->sync_branches($language_id, $branches_data);
        }
        
        return $language_id;
    }
    
    public function update($id, $data) {
        if (empty($data['customer_id'])) {
            $existing = $this->get_by_id($id);
            $data['customer_id'] = $existing['customer_id'] ?? 1;
        }
        
        $branches_data = $data['branches'] ?? [];
        unset($data['branches']);
        
        $result = parent::update($id, $data);
        
        if ($result) {
            $this->sync_branches($id, $branches_data);
        }
        
        return $result;
    }
    
    private function sync_branches($language_id, $branches_data) {
        global $wpdb;
        
        $wpdb->delete($this->branches_table, ['language_id' => $language_id], ['%d']);
        
        if (empty($branches_data)) {
            return;
        }
        
        foreach ($branches_data as $branch_id => $branch_data) {
            if (empty($branch_data['active'])) {
                continue;
            }
            
            $wpdb->insert(
                $this->branches_table,
                [
                    'language_id' => $language_id,
                    'branch_id' => $branch_id,
                    'is_default' => !empty($branch_data['is_default']) ? 1 : 0,
                    'is_active' => 1,
                    'display_order' => intval($branch_data['display_order'] ?? 0),
                ],
                ['%d', '%d', '%d', '%d', '%d']
            );
        }
    }
    
    public function delete($id) {
        $language = $this->get_by_id($id);
        
        if (!$language) {
            return new WP_Error('not_found', 'Jazyk nebyl nalezen');
        }
        
        if ($language['language_code'] === 'cs') {
            return new WP_Error('protected', 'Čeština nemůže být smazána');
        }
        
        if ($this->is_used_in_content($id)) {
            return new WP_Error('in_use', 'Jazyk je použit v obsahu a nemůže být smazán');
        }
        
        return parent::delete($id);
    }
    
    private function is_used_in_content($language_id) {
        global $wpdb;
        
        $language = $this->get_by_id($language_id);
        if (!$language) {
            return false;
        }
        
        $code = $language['language_code'];
        $customer_id = $language['customer_id'];
        
        $tables_to_check = [
            'saw_materials' => 'language',
            'saw_department_materials' => 'language',
            'saw_poi_content' => 'language',
        ];
        
        foreach ($tables_to_check as $table => $column) {
            $full_table = $wpdb->prefix . str_replace('saw_', '', $table);
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
                continue;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$full_table} WHERE customer_id = %d AND {$column} = %s",
                $customer_id,
                $code
            ));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public function get_branches_for_language($language_id) {
        global $wpdb;
        
        $branches_table = $wpdb->prefix . 'saw_branches';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name, b.code, b.city,
                    lb.is_default, lb.is_active, lb.display_order
             FROM {$branches_table} b
             LEFT JOIN {$this->branches_table} lb ON b.id = lb.branch_id AND lb.language_id = %d
             WHERE b.customer_id = (SELECT customer_id FROM {$this->table} WHERE id = %d)
             AND b.is_active = 1
             ORDER BY b.name ASC",
            $language_id,
            $language_id
        ), ARRAY_A);
    }
    
    public function get_active_branches_for_language($language_id) {
        global $wpdb;
        
        $branches_table = $wpdb->prefix . 'saw_branches';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name, b.code, b.city,
                    lb.is_default, lb.display_order
             FROM {$branches_table} b
             INNER JOIN {$this->branches_table} lb ON b.id = lb.branch_id
             WHERE lb.language_id = %d AND lb.is_active = 1
             ORDER BY lb.display_order ASC, b.name ASC",
            $language_id
        ), ARRAY_A);
    }
    
    public function get_by_customer($customer_id) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'language_name',
            'order' => 'ASC',
        ];
        
        return $this->get_all($filters);
    }
    
    public function get_branches_count($language_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->branches_table} WHERE language_id = %d AND is_active = 1",
            $language_id
        ));
    }
}