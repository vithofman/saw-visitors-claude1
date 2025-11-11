<?php
/**
 * Branches Module Model
 *
 * FINAL v13.6.0 - COMPLETE
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     13.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_Branches_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     */
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Get branch by ID with optional cache bypass
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return null;
        }
        
        $cache_key = sprintf('branches_item_%d', $id);
        
        if (!$bypass_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                $this->table,
                $id
            ),
            ARRAY_A
        );
        
        if (!$item) {
            return null;
        }
        
        set_transient($cache_key, $item, $this->cache_ttl);
        
        return $item;
    }
    
    /**
     * Get all branches with filters and caching
     */
    /**
 * Get all branches with filters and caching
 * ALWAYS filtered by customer_id from context
 */
public function get_all($filters = array()) {
    global $wpdb;
    
    // ✅ VŽDY přidej customer_id filtr
    $customer_id = SAW_Context::get_customer_id();
    
    if (!$customer_id) {
        return array('items' => array(), 'total' => 0);
    }
    
    // Cache key
    $filters['customer_id'] = $customer_id; // Force into filters for cache key
    $cache_key = 'branches_list_' . md5(serialize($filters));
    
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    // Build base query
    $sql = "SELECT * FROM {$this->table} WHERE customer_id = %d";
    $params = array($customer_id);
    
    // Search
    if (!empty($filters['search'])) {
        $search_fields = array('name', 'code', 'city', 'email');
        $search_conditions = array();
        
        foreach ($search_fields as $field) {
            $search_conditions[] = "`{$field}` LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }
        
        $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
    }
    
    // Filters
    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $sql .= " AND is_active = %d";
        $params[] = intval($filters['is_active']);
    }
    
    // Count total
    $count_sql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
    $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
    
    // Order
    $orderby = !empty($filters['orderby']) ? sanitize_key($filters['orderby']) : 'is_headquarters';
    $order = !empty($filters['order']) ? strtoupper($filters['order']) : 'DESC';
    
    if (!in_array($order, array('ASC', 'DESC'))) {
        $order = 'DESC';
    }
    
    $sql .= " ORDER BY `{$orderby}` {$order}, name ASC";
    
    // Pagination
    $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
    $per_page = isset($filters['per_page']) ? max(1, intval($filters['per_page'])) : 20;
    $offset = ($page - 1) * $per_page;
    
    $sql .= " LIMIT %d OFFSET %d";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Execute
    $items = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    
    $result = array(
        'items' => $items ?: array(),
        'total' => $total,
    );
    
    // Cache
    set_transient($cache_key, $result, $this->cache_ttl);
    
    return $result;
}
    
    /**
     * Validate data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors['name'] = __('Název pobočky je povinný.', 'saw-visitors');
        }
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = __('Chybí ID zákazníka.', 'saw-visitors');
        }
        
        if (!empty($data['code'])) {
            if ($this->code_exists($data['code'], $data['customer_id'], $id)) {
                $errors['code'] = __('Pobočka s tímto kódem již existuje.', 'saw-visitors');
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', __('Validation failed', 'saw-visitors'), $errors);
    }
    
    /**
     * Check if branch code exists for customer
     */
    private function code_exists($code, $customer_id, $exclude_id = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE code = %s AND customer_id = %d AND id != %d",
            $this->table,
            $code,
            $customer_id,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Set new headquarters (removes old HQ flag)
     */
    public function set_new_headquarters($branch_id, $customer_id) {
        global $wpdb;
        
        // Remove HQ from all branches of this customer
        $wpdb->update(
            $this->table,
            array('is_headquarters' => 0),
            array('customer_id' => $customer_id),
            array('%d'),
            array('%d')
        );
        
        // Set new HQ
        $wpdb->update(
            $this->table,
            array('is_headquarters' => 1),
            array('id' => $branch_id, 'customer_id' => $customer_id),
            array('%d'),
            array('%d', '%d')
        );
        
        $this->invalidate_list_cache();
    }
    
    /**
     * Get count of headquarters for customer
     */
    public function get_headquarters_count($customer_id, $exclude_id = 0) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND is_headquarters = 1 AND id != %d",
            $this->table,
            $customer_id,
            $exclude_id
        ));
    }
}