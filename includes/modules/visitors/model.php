<?php
/**
 * Visitors Module Model
 * * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     2.4.0 - STANDARD: Uses Base Model Hard Flush
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
    
    /**
     * Validate visitor data
     */
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
    
    /**
     * Get visitor by ID (Cached)
     */
    public function get_by_id($id) {
        $cache_key = $this->get_cache_key('item', $id);
        $item = $this->get_cache($cache_key);
        
        if ($item === false) {
            global $wpdb;
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                $this->table,
                $id
            ), ARRAY_A);
            
            if ($item) {
                $this->set_cache($cache_key, $item);
            }
        }
        
        return $item;
    }
    
    /**
     * Get all visitors (Cached)
     * Relies on Base Model to hard-flush this cache on update
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        $cache_key = $this->get_cache_key('list', array_merge(['cid' => $customer_id], $filters));
        $cached = $this->get_cache($cache_key);
        
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
        
        $this->set_cache($cache_key, $result);
        
        return $result;
    }
    
    /**
     * Create visitor
     */
    public function create($data) {
        $result = parent::create($data); // Parent calls invalidate_cache()
        return $result;
    }
    
    /**
     * Update visitor
     */
    public function update($id, $data) {
        $result = parent::update($id, $data); // Parent calls invalidate_cache()
        return $result;
    }
    
    /**
     * Delete visitor
     */
    public function delete($id) {
        $result = parent::delete($id); // Parent calls invalidate_cache()
        return $result;
    }
    
    // ============================================
    // CUSTOM ACTIONS - Must call invalidate_cache() manually
    // ============================================
    
    public function daily_checkin($visitor_id, $log_date = null) {
        global $wpdb;
        if (!$log_date) $log_date = current_time('Y-m-d');
        
        $visitor = $this->get_by_id($visitor_id);
        if (!$visitor) return new WP_Error('visitor_not_found', 'Visitor not found');
        
        $visit_id = $visitor['visit_id'];
        
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE visit_id = %d AND visitor_id = %d AND log_date = %s",
            $wpdb->prefix . 'saw_visit_daily_logs', $visit_id, $visitor_id, $log_date
        ), ARRAY_A);
        
        if ($existing_log) {
            if (!empty($existing_log['checked_out_at'])) {
                $result = $wpdb->insert($wpdb->prefix . 'saw_visit_daily_logs', array(
                    'visit_id' => $visit_id, 'visitor_id' => $visitor_id, 'log_date' => $log_date, 'checked_in_at' => current_time('mysql'),
                ), array('%d', '%d', '%s', '%s'));
            } else {
                $result = $wpdb->update($wpdb->prefix . 'saw_visit_daily_logs', 
                    array('checked_in_at' => current_time('mysql')), 
                    array('id' => $existing_log['id']), array('%s'), array('%d'));
            }
        } else {
            $result = $wpdb->insert($wpdb->prefix . 'saw_visit_daily_logs', array(
                'visit_id' => $visit_id, 'visitor_id' => $visitor_id, 'log_date' => $log_date, 'checked_in_at' => current_time('mysql'),
            ), array('%d', '%d', '%s', '%s'));
        }
        
        if ($result === false) return new WP_Error('checkin_failed', 'Failed to record check-in');
        
        if (empty($visitor['first_checkin_at'])) {
            $wpdb->update($this->table, array('first_checkin_at' => current_time('mysql')), array('id' => $visitor_id), array('%s'), array('%d'));
        }
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
        $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
        $visits_model = new SAW_Module_Visits_Model($visits_config);
        $visits_model->mark_as_started($visit_id);
        
        // 🔥 CRITICAL: Hard flush via Base Model
        $this->invalidate_cache();
        
        return true;
    }
    
    public function daily_checkout($visitor_id, $log_date = null, $manual = false, $admin_id = null, $reason = null) {
        global $wpdb;
        if (!$log_date) $log_date = current_time('Y-m-d');
        
        $visitor = $this->get_by_id($visitor_id);
        if (!$visitor) return new WP_Error('visitor_not_found', 'Visitor not found');
        
        $visit_id = $visitor['visit_id'];
        
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visit_id = %d AND visitor_id = %d AND log_date = %s
             AND checked_in_at IS NOT NULL AND checked_out_at IS NULL
             ORDER BY checked_in_at DESC LIMIT 1",
            $visit_id, $visitor_id, $log_date
        ), ARRAY_A);
        
        if (!$log) return new WP_Error('no_active_checkin', 'Návštěvník není momentálně přítomen');
        
        $update_data = array('checked_out_at' => current_time('mysql'));
        if ($manual) {
            $update_data['manual_checkout'] = 1;
            $update_data['manual_checkout_by'] = $admin_id;
            $update_data['manual_checkout_reason'] = $reason;
        }
        
        $result = $wpdb->update($wpdb->prefix . 'saw_visit_daily_logs', $update_data, array('id' => $log['id']), array_fill(0, count($update_data), '%s'), array('%d'));
        
        if ($result === false) return new WP_Error('checkout_failed', 'Failed to record check-out');
        
        $wpdb->update($this->table, array('last_checkout_at' => current_time('mysql')), array('id' => $visitor_id), array('%s'), array('%d'));
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
        $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
        $visits_model = new SAW_Module_Visits_Model($visits_config);
        $visits_model->check_and_complete_visit($visit_id);
        
        // 🔥 CRITICAL: Hard flush via Base Model
        $this->invalidate_cache();
        
        return true;
    }
    
    public function add_adhoc_visitor($visit_id, $visitor_data) {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $wpdb->prefix . 'saw_visits', $visit_id), ARRAY_A);
        if (!$visit) return new WP_Error('visit_not_found', 'Visit not found');
        
        $data = array(
            'visit_id' => $visit_id,
            'first_name' => sanitize_text_field($visitor_data['first_name']),
            'last_name' => sanitize_text_field($visitor_data['last_name']),
            'position' => !empty($visitor_data['position']) ? sanitize_text_field($visitor_data['position']) : null,
            'email' => !empty($visitor_data['email']) ? sanitize_email($visitor_data['email']) : null,
            'phone' => !empty($visitor_data['phone']) ? sanitize_text_field($visitor_data['phone']) : null,
            'participation_status' => 'confirmed',
            'training_skipped' => !empty($visitor_data['training_skipped']) ? 1 : 0,
        );
        
        $result = $wpdb->insert($this->table, $data, array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'));
        if (!$result) return new WP_Error('insert_failed', 'Failed to create ad-hoc visitor');
        
        // 🔥 CRITICAL: Hard flush via Base Model
        $this->invalidate_cache();
        
        return $wpdb->insert_id;
    }
    
    // ============================================
    // HELPERS
    // ============================================
    
    public function get_certificates($visitor_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE visitor_id = %d ORDER BY created_at DESC",
            $wpdb->prefix . 'saw_visitor_certificates', $visitor_id
        ), ARRAY_A);
    }
    
    public function save_certificates($visitor_id, $certificates_data) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'saw_visitor_certificates', array('visitor_id' => $visitor_id));
        if (empty($certificates_data) || !is_array($certificates_data)) return true;
        
        foreach ($certificates_data as $cert) {
            if (empty($cert['certificate_name'])) continue;
            $wpdb->insert($wpdb->prefix . 'saw_visitor_certificates', array(
                'visitor_id' => $visitor_id,
                'certificate_name' => sanitize_text_field($cert['certificate_name']),
                'certificate_number' => !empty($cert['certificate_number']) ? sanitize_text_field($cert['certificate_number']) : null,
                'valid_until' => !empty($cert['valid_until']) ? sanitize_text_field($cert['valid_until']) : null,
            ), array('%d', '%s', '%s', '%s'));
        }
        return true;
    }
    
    public function get_daily_logs($visitor_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE visitor_id = %d ORDER BY log_date DESC",
            $wpdb->prefix . 'saw_visit_daily_logs', $visitor_id
        ), ARRAY_A);
    }
    
    public function get_visit_data($visit_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, c.name as company_name, b.name as branch_name
             FROM %i v
             LEFT JOIN %i c ON v.company_id = c.id
             LEFT JOIN %i b ON v.branch_id = b.id
             WHERE v.id = %d",
            $wpdb->prefix . 'saw_visits', $wpdb->prefix . 'saw_companies', $wpdb->prefix . 'saw_branches', $visit_id
        ), ARRAY_A);
    }
    
    public function get_visits_for_select() {
        global $wpdb;
        $customer_id = SAW_Context::get_customer_id();
        if (!$customer_id) return array();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.id, c.name as company_name, v.status
             FROM %i v
             LEFT JOIN %i c ON v.company_id = c.id
             WHERE v.customer_id = %d
             ORDER BY v.created_at DESC LIMIT 100",
            $wpdb->prefix . 'saw_visits', $wpdb->prefix . 'saw_companies', $customer_id
        ), ARRAY_A);
    }
}