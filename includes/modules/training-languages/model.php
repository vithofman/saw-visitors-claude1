<?php
/**
 * Training Languages Model - REFACTORED
 * 
 * @package SAW_Visitors
 * @version 2.0.0
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
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * Validate data
     */
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
        
        $customer_id = !empty($data['customer_id']) ? $data['customer_id'] : SAW_Context::get_customer_id();
        
        if (!empty($data['language_code']) && $this->code_exists($data['language_code'], $id, $customer_id)) {
            $errors['language_code'] = 'Jazyk s tímto kódem již existuje';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if language code exists for customer
     */
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
    
    /**
     * Get all - NO CACHE, with branches count
     */
    public function get_all($filters = []) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!isset($filters['customer_id'])) {
            $filters['customer_id'] = $customer_id;
        }
        
        // Base WHERE
        $where = ['1=1'];
        $params = [];
        
        // Customer filter
        if (!empty($filters['customer_id'])) {
            $where[] = 'l.customer_id = %d';
            $params[] = intval($filters['customer_id']);
        }
        
        // Search
        if (!empty($filters['search'])) {
            $where[] = '(l.language_name LIKE %s OR l.language_code LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // ORDER BY
        $orderby = !empty($filters['orderby']) ? sanitize_text_field($filters['orderby']) : 'language_name';
        $order = !empty($filters['order']) && strtoupper($filters['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        // LIMIT
        $per_page = !empty($filters['per_page']) ? intval($filters['per_page']) : 20;
        $page = !empty($filters['page']) ? intval($filters['page']) : 1;
        $offset = ($page - 1) * $per_page;
        
        // SQL with LEFT JOIN for branches count
        $sql = "SELECT l.*, 
                COUNT(CASE WHEN lb.is_active = 1 THEN 1 END) as branches_count
                FROM {$this->table} l
                LEFT JOIN {$this->branches_table} lb ON l.id = lb.language_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY l.id
                ORDER BY l.{$orderby} {$order}
                LIMIT {$per_page} OFFSET {$offset}";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        // Count total
        $count_sql = "SELECT COUNT(DISTINCT l.id)
                      FROM {$this->table} l
                      WHERE " . implode(' AND ', $where);
        
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        
        $total = $wpdb->get_var($count_sql);
        
        return [
            'items' => $items,
            'total' => $total
        ];
    }
    
    /**
     * Get by ID with formatting and active branches
     */
    public function get_by_id($id) {
        $item = parent::get_by_id($id);
        
        if (!$item) {
            return null;
        }
        
        // Customer isolation check
        $current_customer_id = SAW_Context::get_customer_id();
        
        if (!current_user_can('manage_options')) {
            if (empty($item['customer_id']) || $item['customer_id'] != $current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[TRAINING-LANGUAGES] Isolation violation - Item customer: %s, Current: %s',
                        $item['customer_id'] ?? 'NULL',
                        $current_customer_id ?? 'NULL'
                    ));
                }
                return null;
            }
        }
        
        // Get active branches for this language
        global $wpdb;
        $item['active_branches'] = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.name, b.code, b.city,
                    lb.is_default, lb.display_order
             FROM {$wpdb->prefix}saw_branches b
             INNER JOIN {$this->branches_table} lb ON b.id = lb.branch_id
             WHERE lb.language_id = %d AND lb.is_active = 1
             ORDER BY lb.display_order ASC, b.name ASC",
            $id
        ), ARRAY_A);
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Create with branches
     */
    public function create($data) {
        $customer_id = SAW_Context::get_customer_id();
        $data['customer_id'] = $customer_id;
        
        $branches_data = $data['branches'] ?? [];
        unset($data['branches']);
        
        $language_id = parent::create($data);
        
        if ($language_id && !is_wp_error($language_id) && !empty($branches_data)) {
            $this->sync_branches($language_id, $branches_data);
        }
        
        return $language_id;
    }
    
    /**
     * Update with branches
     */
    public function update($id, $data) {
        if (empty($data['customer_id'])) {
            $existing = $this->get_by_id($id);
            $data['customer_id'] = $existing['customer_id'] ?? SAW_Context::get_customer_id();
        }
        
        $branches_data = $data['branches'] ?? [];
        unset($data['branches']);
        
        $result = parent::update($id, $data);
        
        if ($result && !is_wp_error($result)) {
            $this->sync_branches($id, $branches_data);
        }
        
        return $result;
    }
    
    /**
     * Sync branches for language
     */
    private function sync_branches($language_id, $branches_data) {
        global $wpdb;
        
        // Delete existing
        $wpdb->delete($this->branches_table, ['language_id' => $language_id], ['%d']);
        
        if (empty($branches_data)) {
            return;
        }
        
        // Insert new
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
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%d', '%s']
            );
        }
    }
    
    /**
     * Delete - protect Czech
     */
    public function delete($id) {
        $language = $this->get_by_id($id);
        
        if (!$language) {
            return new WP_Error('not_found', 'Jazyk nebyl nalezen');
        }
        
        if ($language['language_code'] === 'cs') {
            return new WP_Error('protected', 'Čeština nemůže být smazána');
        }
        
        return parent::delete($id);
    }
    
    /**
     * Get branches for language (for form)
     */
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
}
