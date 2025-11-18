<?php
/**
 * Branches Module Model
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     20.0.0 - FINAL FIX: Override get_by_id to skip scope filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_Branches_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * ✅ Override get_by_id to SKIP scope filtering
     * 
     * CRITICAL: Branches are the source of branch switcher, not its target.
     * Must be accessible regardless of branch filter.
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return null;
        }
        
        // ⚠️ CRITICAL: NO SCOPE - direct DB query
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $this->table,
            $id
        ), ARRAY_A);
        
        return $item;
    }
    
    /**
     * ✅ Override get_all to SKIP scope filtering
     * 
     * CRITICAL: Branches are the source of branch switcher, not its target.
     * Must show ALL branches for customer, regardless of branch filter.
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id && !saw_is_super_admin()) {
            return array('items' => array(), 'total' => 0);
        }

        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = array();

        if ($customer_id) {
            $sql .= " AND customer_id = %d";
            $params[] = $customer_id;
        }
        
        // ⚠️ CRITICAL: NO SCOPE FILTERING - branches are the switcher source
        
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
        
        // Count
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
        
        return array(
            'items' => $items ?: array(),
            'total' => $total,
        );
    }
    
    /**
     * ✅ Uses parent create() - works with improved Base Model
     */
    public function create($data) {
        return parent::create($data);
    }
    
    /**
     * ✅ Uses parent update()
     */
    public function update($id, $data) {
        return parent::update($id, $data);
    }
    
    /**
     * ✅ Uses parent delete()
     */
    public function delete($id) {
        return parent::delete($id);
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
    
    public function set_new_headquarters($branch_id, $customer_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->table,
            array('is_headquarters' => 0),
            array('customer_id' => $customer_id),
            array('%d'),
            array('%d')
        );
        
        $wpdb->update(
            $this->table,
            array('is_headquarters' => 1),
            array('id' => $branch_id, 'customer_id' => $customer_id),
            array('%d'),
            array('%d', '%d')
        );
        
        $this->invalidate_cache();
    }
    
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