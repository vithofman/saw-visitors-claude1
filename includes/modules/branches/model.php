<?php
/**
 * Branches Module Model
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     21.0.0 - Combined tabs support (headquarters/other/inactive)
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
     * Override get_by_id to SKIP scope filtering
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return null;
        }
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $this->table,
            $id
        ), ARRAY_A);
        
        return $item;
    }
    
    /**
     * Override get_all with combined tab filtering
     * 
     * Tab values:
     * - null/empty = all
     * - headquarters = is_headquarters=1 AND is_active=1
     * - other = is_headquarters=0 AND is_active=1
     * - inactive = is_active=0
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
        
        // Combined tab filtering
        if (!empty($filters['tab'])) {
            switch ($filters['tab']) {
                case 'headquarters':
                    $sql .= " AND is_headquarters = 1 AND is_active = 1";
                    break;
                case 'other':
                    $sql .= " AND is_headquarters = 0 AND is_active = 1";
                    break;
                case 'inactive':
                    $sql .= " AND is_active = 0";
                    break;
                // 'all' = no filter
            }
        }
        
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
        
        // Count
        $count_sql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
        $total = !empty($params) 
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : (int) $wpdb->get_var($count_sql);
        
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
        // ⭐ KRITICKÁ OPRAVA: Podpora vlastního offsetu pro infinite scroll
        if (isset($filters['offset']) && $filters['offset'] >= 0) {
            $offset = intval($filters['offset']);
        } else {
            $offset = ($page - 1) * $per_page;
        }
        
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
     * Get counts for each tab
     */
    public function get_tab_counts() {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id && !saw_is_super_admin()) {
            return array('all' => 0, 'headquarters' => 0, 'other' => 0, 'inactive' => 0);
        }
        
        $where = $customer_id ? $wpdb->prepare("WHERE customer_id = %d", $customer_id) : "WHERE 1=1";
        
        $counts = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_headquarters = 1 AND is_active = 1 THEN 1 ELSE 0 END) as headquarters,
                SUM(CASE WHEN is_headquarters = 0 AND is_active = 1 THEN 1 ELSE 0 END) as other,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            FROM {$this->table}
            {$where}
        ", ARRAY_A);
        
        return array(
            'all' => (int) ($counts['total'] ?? 0),
            'headquarters' => (int) ($counts['headquarters'] ?? 0),
            'other' => (int) ($counts['other'] ?? 0),
            'inactive' => (int) ($counts['inactive'] ?? 0),
        );
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