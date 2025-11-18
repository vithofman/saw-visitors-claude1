<?php
/**
 * Training Languages Model
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    3.7.0 - FINAL ROBUST FIX
 */

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
        $this->cache_ttl = $config['cache']['ttl'] ?? 3600;
    }
    
    public function validate($data, $id = 0) {
        $errors = [];
        if (empty($data['language_code'])) $errors['language_code'] = 'Kód jazyka je povinný';
        if (empty($data['language_name'])) $errors['language_name'] = 'Název jazyka je povinný';
        if (empty($data['flag_emoji'])) $errors['flag_emoji'] = 'Vlajka je povinná';
        
        $customer_id = !empty($data['customer_id']) ? $data['customer_id'] : SAW_Context::get_customer_id();
        if (!empty($data['language_code']) && $this->code_exists($data['language_code'], $id, $customer_id)) {
            $errors['language_code'] = 'Jazyk s tímto kódem již existuje';
        }
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    private function code_exists($code, $exclude_id = 0, $customer_id = 0) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE language_code = %s AND customer_id = %d AND id != %d",
            $code, $customer_id, $exclude_id
        ));
    }
    
    // Helper: Check physical existence to prevent FK errors
    public function exists($id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %d", $id));
    }
    
    public function get_all($filters = []) {
        global $wpdb;
        $customer_id = SAW_Context::get_customer_id();
        if (!$customer_id) return ['items' => [], 'total' => 0];
        
        // Direct DB query for consistency (No Cache for List)
        $sql = "SELECT l.*, 
                COUNT(CASE WHEN lb.is_active = 1 THEN 1 END) as branches_count
                FROM {$this->table} l
                LEFT JOIN {$this->branches_table} lb ON l.id = lb.language_id
                WHERE l.customer_id = %d";
        $params = [$customer_id];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (l.language_name LIKE %s OR l.language_code LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $sql .= " GROUP BY l.id";
        $orderby = $filters['orderby'] ?? 'language_name';
        $order = strtoupper($filters['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $allowed = ['language_name', 'language_code', 'created_at', 'branches_count'];
        if (!in_array($orderby, $allowed)) $orderby = 'language_name';
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        // Total count query
        $total = count($wpdb->get_results($wpdb->prepare($sql, ...$params)));
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        
        return ['items' => $items ?: [], 'total' => $total];
    }
    
    public function get_by_id($id) {
        $cache_key = $this->get_cache_key('item', $id);
        $item = $this->get_cache($cache_key);
        
        if ($item === false) {
            global $wpdb;
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $this->table, $id), ARRAY_A);
            if ($item) $this->set_cache($cache_key, $item);
        }
        
        if (!$item) return null;
        
        // Always load branches live to ensure consistency
        $item['branches'] = $this->get_branches_for_form($id);
        
        // Load active branches for detail view
        global $wpdb;
        $item['active_branches'] = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name, b.code, b.city, lb.is_default, lb.display_order
             FROM {$wpdb->prefix}saw_branches b
             INNER JOIN {$this->branches_table} lb ON b.id = lb.branch_id
             WHERE lb.language_id = %d AND lb.is_active = 1
             ORDER BY lb.display_order ASC, b.name ASC",
            $id
        ), ARRAY_A);

        return $item;
    }
    
    public function create($data) {
        $customer_id = SAW_Context::get_customer_id();
        $data['customer_id'] = $customer_id;
        $branches_data = $data['branches'] ?? [];
        unset($data['branches']);
        
        // 1. Insert Main Record
        $id = parent::create($data);
        
        // 2. Check if insert was successful AND record exists
        if ($id && !is_wp_error($id)) {
            // Force check existence to prevent FK error
            if ($this->exists($id) && !empty($branches_data)) {
                $this->sync_branches($id, $branches_data);
            }
            
            // 🔥 Explicitly clear cache for this new ID to prevent "Not Found" error
            $cache_key = $this->get_cache_key('item', $id);
            delete_transient($cache_key);
        }
        
        return $id;
    }
    
    public function update($id, $data) {
        $branches_data = $data['branches'] ?? null;
        unset($data['branches']);
        
        if (!$this->exists($id)) {
            return new WP_Error('not_found', 'Záznam neexistuje.');
        }

        $result = parent::update($id, $data);
        
        if ($branches_data !== null && !is_wp_error($result)) {
            $this->sync_branches($id, $branches_data);
            
            // Flush caches
            $this->invalidate_cache();
            $cache_key = $this->get_cache_key('item', $id);
            delete_transient($cache_key);
        }
        
        return $result;
    }
    
    public function delete($id) {
        $item = $this->get_by_id($id);
        if ($item && $item['language_code'] === 'cs') return new WP_Error('protected', 'Čeština nemůže být smazána');
        return parent::delete($id);
    }
    
    private function sync_branches($language_id, $branches_data) {
        global $wpdb;
        
        // No Transaction - Direct DELETE/INSERT to avoid isolation issues
        $wpdb->delete($this->branches_table, ['language_id' => $language_id], ['%d']);
        
        if (empty($branches_data)) return;
        
        foreach ($branches_data as $branch_id => $data) {
            if (empty($data['active'])) continue;
            
            $wpdb->insert($this->branches_table, [
                'language_id' => $language_id,
                'branch_id' => $branch_id,
                'is_default' => !empty($data['is_default']) ? 1 : 0,
                'is_active' => 1,
                'display_order' => intval($data['display_order'] ?? 0),
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%d', '%d', '%d', '%s']);
        }
    }
    
    public function get_branches_for_form($language_id) {
        global $wpdb;
        $customer_id = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM {$this->table} WHERE id = %d", $language_id));
        
        if (!$customer_id) return [];
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name, b.code, b.city, lb.is_default, lb.is_active, lb.display_order
             FROM {$wpdb->prefix}saw_branches b
             LEFT JOIN {$this->branches_table} lb ON b.id = lb.branch_id AND lb.language_id = %d
             WHERE b.customer_id = %d AND b.is_active = 1
             ORDER BY b.name ASC",
            $language_id, $customer_id
        ), ARRAY_A);
    }
    
    public function get_available_branches() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, code, city FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
            SAW_Context::get_customer_id()
        ), ARRAY_A);
    }
}