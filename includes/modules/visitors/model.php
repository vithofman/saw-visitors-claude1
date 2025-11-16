<?php
/**
 * Visitors Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Visitors_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['visit_id'])) {
            $errors['visit_id'] = 'Visit ID is required';
        }
        
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    public function get_by_id($id) {
        $cache_key = sprintf('saw_visitors_item_%d', $id);
        $item = get_transient($cache_key);
        
        if ($item === false) {
            $item = parent::get_by_id($id);
            
            if ($item) {
                set_transient($cache_key, $item, $this->cache_ttl);
            }
        }
        
        return $item;
    }
    
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        $cache_key = sprintf(
            'saw_visitors_list_%d_%s',
            $customer_id,
            md5(serialize($filters))
        );
        
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // JOIN s visits pro customer isolation
        $sql = "SELECT vis.*, 
                       v.customer_id,
                       v.company_id,
                       v.branch_id,
                       c.name as company_name,
                       b.name as branch_name
                FROM %i vis
                INNER JOIN %i v ON vis.visit_id = v.id
                LEFT JOIN %i c ON v.company_id = c.id
                LEFT JOIN %i b ON v.branch_id = b.id
                WHERE v.customer_id = %d";
        
        $params = array(
            $this->table,
            $wpdb->prefix . 'saw_visits',
            $wpdb->prefix . 'saw_companies',
            $wpdb->prefix . 'saw_branches',
            $customer_id
        );
        
        // Search filter
        if (!empty($filters['search'])) {
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            $sql .= " AND (vis.first_name LIKE %s OR vis.last_name LIKE %s OR vis.email LIKE %s)";
            $params[] = $search_value;
            $params[] = $search_value;
            $params[] = $search_value;
        }
        
        // Participation status filter
        if (!empty($filters['participation_status'])) {
            $sql .= " AND vis.participation_status = %s";
            $params[] = $filters['participation_status'];
        }
        
        // Count total
        $count_sql = preg_replace('/^SELECT .+ FROM/', 'SELECT COUNT(DISTINCT vis.id) FROM', $sql);
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
        
        // Sorting
        $orderby = $filters['orderby'] ?? 'vis.id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'DESC';
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        // Pagination
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $sql = $wpdb->prepare($sql, ...$params);
        
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $result = array(
            'items' => $items ?: array(),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $per_page > 0 ? ceil($total / $per_page) : 0,
        );
        
        set_transient($cache_key, $result, $this->cache_ttl);
        
        return $result;
    }
    
    public function create($data) {
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    public function delete($id) {
        $result = parent::delete($id);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Get certificates for visitor
     */
    public function get_certificates($visitor_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE visitor_id = %d ORDER BY created_at DESC",
            $wpdb->prefix . 'saw_visitor_certificates',
            $visitor_id
        ), ARRAY_A);
    }
    
    /**
     * Save certificates for visitor
     */
    public function save_certificates($visitor_id, $certificates_data) {
        global $wpdb;
        
        // Delete existing certificates
        $wpdb->delete(
            $wpdb->prefix . 'saw_visitor_certificates',
            array('visitor_id' => $visitor_id)
        );
        
        if (empty($certificates_data) || !is_array($certificates_data)) {
            return true;
        }
        
        // Insert new certificates
        foreach ($certificates_data as $cert) {
            if (empty($cert['certificate_name'])) {
                continue;
            }
            
            $wpdb->insert(
                $wpdb->prefix . 'saw_visitor_certificates',
                array(
                    'visitor_id' => $visitor_id,
                    'certificate_name' => sanitize_text_field($cert['certificate_name']),
                    'certificate_number' => !empty($cert['certificate_number']) ? sanitize_text_field($cert['certificate_number']) : null,
                    'valid_until' => !empty($cert['valid_until']) ? sanitize_text_field($cert['valid_until']) : null,
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * Get daily logs for visitor
     */
    public function get_daily_logs($visitor_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE visitor_id = %d ORDER BY log_date DESC",
            $wpdb->prefix . 'saw_visit_daily_logs',
            $visitor_id
        ), ARRAY_A);
    }
    
    /**
     * Get visit data for visitor
     */
    public function get_visit_data($visit_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, c.name as company_name, b.name as branch_name
             FROM %i v
             LEFT JOIN %i c ON v.company_id = c.id
             LEFT JOIN %i b ON v.branch_id = b.id
             WHERE v.id = %d",
            $wpdb->prefix . 'saw_visits',
            $wpdb->prefix . 'saw_companies',
            $wpdb->prefix . 'saw_branches',
            $visit_id
        ), ARRAY_A);
    }
    
    /**
     * Get all visits for select dropdown (customer isolated)
     */
    public function get_visits_for_select() {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.id, c.name as company_name, v.status
             FROM %i v
             LEFT JOIN %i c ON v.company_id = c.id
             WHERE v.customer_id = %d
             ORDER BY v.created_at DESC
             LIMIT 100",
            $wpdb->prefix . 'saw_visits',
            $wpdb->prefix . 'saw_companies',
            $customer_id
        ), ARRAY_A);
    }
    
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_visitors_item_%d', $id);
        delete_transient($cache_key);
    }
    
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_visitors_list_%' 
             OR option_name LIKE '_transient_timeout_saw_visitors_list_%'"
        );
    }
}
