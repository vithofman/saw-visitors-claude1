<?php
/**
 * Branches Module Model
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Branches_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * Validace dat
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Název pobočky je povinný';
        }
        
        if (!empty($data['code']) && $this->code_exists($data['code'], $id, $data['customer_id'] ?? 0)) {
            $errors['code'] = 'Pobočka s tímto kódem již existuje';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Neplatná emailová adresa';
        }
        
        if (!empty($data['latitude'])) {
            $lat = floatval($data['latitude']);
            if ($lat < -90 || $lat > 90) {
                $errors['latitude'] = 'Zeměpisná šířka musí být mezi -90 a 90';
            }
        }
        
        if (!empty($data['longitude'])) {
            $lon = floatval($data['longitude']);
            if ($lon < -180 || $lon > 180) {
                $errors['longitude'] = 'Zeměpisná délka musí být mezi -180 a 180';
            }
        }
        
        if (isset($data['sort_order'])) {
            $sort = intval($data['sort_order']);
            if ($sort < 0) {
                $errors['sort_order'] = 'Pořadí nemůže být záporné';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if code exists
     */
    private function code_exists($code, $exclude_id = 0, $customer_id = 0) {
        global $wpdb;
        
        if (empty($code)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE code = %s AND customer_id = %d AND id != %d",
            $code,
            $customer_id,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Override: Create - automaticky přidá customer_id + spustí WordPress akci
     */
    public function create($data) {
        // ✅ ZÍSKEJ CUSTOMER_ID ZE SESSION
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
        
        if (!$customer_id) {
            global $wpdb;
            $customer_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1");
        }
        
        $data['customer_id'] = $customer_id;
        
        $data = $this->process_opening_hours_for_save($data);
        $data = $this->ensure_single_headquarters($data);
        
        $branch_id = parent::create($data);
        
        if ($branch_id) {
            do_action('saw_branch_created', $branch_id, $customer_id);
        }
        
        return $branch_id;
    }
    
    /**
     * Override: Update - udrží customer_id
     */
    public function update($id, $data) {
        // ✅ ZAJISTI, ŽE SE CUSTOMER_ID NEZTRATÍ
        if (empty($data['customer_id'])) {
            $existing = $this->get_by_id($id);
            $data['customer_id'] = $existing['customer_id'] ?? 1;
        }
        
        $data = $this->process_opening_hours_for_save($data);
        $data = $this->ensure_single_headquarters($data, $id);
        
        return parent::update($id, $data);
    }
    
    /**
     * Ensure only one headquarters per customer
     */
    private function ensure_single_headquarters($data, $exclude_id = 0) {
        if (!empty($data['is_headquarters']) && !empty($data['customer_id'])) {
            global $wpdb;
            
            $wpdb->update(
                $this->table,
                ['is_headquarters' => 0],
                ['customer_id' => $data['customer_id']],
                ['%d'],
                ['%d']
            );
            
            if ($exclude_id > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table} SET is_headquarters = 0 WHERE customer_id = %d AND id != %d",
                    $data['customer_id'],
                    $exclude_id
                ));
            }
        }
        
        return $data;
    }
    
    /**
     * Process opening hours pro uložení
     */
    private function process_opening_hours_for_save($data) {
        if (isset($data['opening_hours']) && is_string($data['opening_hours'])) {
            $lines = explode("\n", $data['opening_hours']);
            $hours = array_filter(array_map('trim', $lines));
            $data['opening_hours'] = !empty($hours) ? json_encode(array_values($hours), JSON_UNESCAPED_UNICODE) : null;
        }
        
        return $data;
    }
    
    /**
     * Get opening hours as array
     */
    public function get_opening_hours_as_array($hours_json) {
        if (empty($hours_json)) {
            return [];
        }
        
        $hours = json_decode($hours_json, true);
        
        return is_array($hours) ? $hours : [];
    }
    
    /**
     * Get full address string
     */
    public function get_full_address($item) {
        $parts = [];
        
        if (!empty($item['street'])) {
            $parts[] = $item['street'];
        }
        
        if (!empty($item['city']) || !empty($item['postal_code'])) {
            $city_parts = array_filter([
                $item['postal_code'] ?? '',
                $item['city'] ?? ''
            ]);
            
            if (!empty($city_parts)) {
                $parts[] = implode(' ', $city_parts);
            }
        }
        
        if (!empty($item['country']) && $item['country'] !== 'CZ') {
            $parts[] = $item['country'];
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Check if branch is used in system
     */
    public function is_used_in_system($id) {
        global $wpdb;
        
        $tables_to_check = [
            'saw_visits',
            'saw_invitations',
            'saw_users',
        ];
        
        foreach ($tables_to_check as $table) {
            $full_table = $wpdb->prefix . $table;
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
                continue;
            }
            
            $column = 'branch_id';
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM `{$full_table}` LIKE %s",
                $column
            ));
            
            if (!$column_exists) {
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
    
    /**
     * Get branches by customer
     */
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'sort_order',
            'order' => 'ASC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
    
    /**
     * Get headquarters for customer
     */
    public function get_headquarters($customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE customer_id = %d AND is_headquarters = 1 LIMIT 1",
            $customer_id
        ), ARRAY_A);
    }
    
    /**
     * Override: Get all
     */
    public function get_all($filters = []) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
}