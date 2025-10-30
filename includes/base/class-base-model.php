<?php
/**
 * Base Model Class
 * 
 * Univerzální DB operace pro všechny moduly.
 * Child modely jen přidávají custom validaci a vztahy.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Base_Model 
{
    protected $table;
    protected $config;
    protected $cache_ttl = 300;
    
    /**
     * Get all items
     */
    public function get_all($filters = []) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key('list', $filters);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "{$field} LIKE %s";
            }
            
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            $search_params = array_fill(0, count($search_fields), $search_value);
            
            $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
            $sql = $wpdb->prepare($sql, ...$search_params);
        }
        
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                $sql .= $wpdb->prepare(" AND {$filter_key} = %s", $filters[$filter_key]);
            }
        }
        
        $orderby = $filters['orderby'] ?? 'id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (in_array($order, ['ASC', 'DESC'])) {
            $sql .= " ORDER BY {$orderby} {$order}";
        }
        
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        $limit = intval($filters['per_page'] ?? 20);
        $offset = ($filters['page'] ?? 1) - 1;
        $offset = $offset * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        $data = [
            'items' => $results,
            'total' => $total
        ];
        
        $this->set_cache($cache_key, $data);
        
        return $data;
    }
    
    /**
     * Get by ID
     */
    public function get_by_id($id) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key('item', $id);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($item) {
            $this->set_cache($cache_key, $item);
        }
        
        return $item;
    }
    
    /**
     * Create new item
     */
    public function create($data) {
        global $wpdb;
        
        $validation = $this->validate($data);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database insert failed: ' . $wpdb->last_error);
        }
        
        $this->invalidate_cache();
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update item
     */
    public function update($id, $data) {
        global $wpdb;
        
        $validation = $this->validate($data, $id);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database update failed: ' . $wpdb->last_error);
        }
        
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Delete item
     */
    public function delete($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database delete failed: ' . $wpdb->last_error);
        }
        
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Count items
     */
    public function count($filters = []) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "{$field} LIKE %s";
            }
            
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            $search_params = array_fill(0, count($search_fields), $search_value);
            
            $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
            $sql = $wpdb->prepare($sql, ...$search_params);
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Validate data (override in child class)
     */
    abstract public function validate($data, $id = 0);
    
    /**
     * Cache helpers
     */
    protected function get_cache_key($type, $identifier = '') {
        $key = 'saw_' . $this->config['entity'] . '_' . $type;
        
        if (is_array($identifier)) {
            $key .= '_' . md5(serialize($identifier));
        } elseif ($identifier) {
            $key .= '_' . $identifier;
        }
        
        return $key;
    }
    
    protected function get_cache($key) {
        if (!$this->config['cache']['enabled'] ?? true) {
            return false;
        }
        
        return get_transient($key);
    }
    
    protected function set_cache($key, $data) {
        if (!$this->config['cache']['enabled'] ?? true) {
            return false;
        }
        
        $ttl = $this->config['cache']['ttl'] ?? $this->cache_ttl;
        
        return set_transient($key, $data, $ttl);
    }
    
    protected function invalidate_cache() {
        global $wpdb;
        
        $pattern = 'saw_' . $this->config['entity'] . '_%';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $pattern
        ));
    }
}
