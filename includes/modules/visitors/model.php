<?php
/**
 * Visitors Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     2.0.0 - REFACTORED: Daily check-in/out, first/last tracking
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
     * Get visitor by ID with cache
     */
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
    
    /**
     * Get all visitors with filters (customer isolated via visits)
     */
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
    
    /**
     * Create visitor and invalidate cache
     */
    public function create($data) {
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Update visitor and invalidate cache
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Delete visitor and invalidate cache
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    // ============================================
    // NEW METHODS - DAILY CHECK-IN/OUT
    // ============================================
    
    /**
     * Daily check-in
     * Records visitor arrival for specific day
     * 
     * @param int $visitor_id Visitor ID
     * @param string $log_date Date (Y-m-d), default today
     * @return bool|WP_Error True on success
     */
    public function daily_checkin($visitor_id, $log_date = null) {
        global $wpdb;
        
        if (!$log_date) {
            $log_date = current_time('Y-m-d');
        }
        
        $visitor = $this->get_by_id($visitor_id);
        
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Visitor not found');
        }
        
        $visit_id = $visitor['visit_id'];
        
        // Check if log exists for today
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE visit_id = %d AND visitor_id = %d AND log_date = %s",
            $wpdb->prefix . 'saw_visit_daily_logs',
            $visit_id,
            $visitor_id,
            $log_date
        ), ARRAY_A);
        
        if ($existing_log) {
    // ✅ OPRAVENO: Pokud už existuje log pro dnes, NEPŘEPISUJ ho
    // Kontroluj jestli už není checked-out
    if (!empty($existing_log['checked_out_at'])) {
        // Už byl checked-out → vytvoř NOVÝ záznam pro další vstup
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_visit_daily_logs',
            array(
                'visit_id' => $visit_id,
                'visitor_id' => $visitor_id,
                'log_date' => $log_date,
                'checked_in_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        error_log("[SAW] Created NEW log entry for visitor #{$visitor_id} - re-entry after checkout");
    } else {
        // Ještě není checked-out → UPDATE check-in času (znovu přišel)
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visit_daily_logs',
            array('checked_in_at' => current_time('mysql')),
            array('id' => $existing_log['id']),
            array('%s'),
            array('%d')
        );
        
        error_log("[SAW] Updated existing log entry for visitor #{$visitor_id} - updated check-in time");
    }
} else {
            // Create new log
            $result = $wpdb->insert(
                $wpdb->prefix . 'saw_visit_daily_logs',
                array(
                    'visit_id' => $visit_id,
                    'visitor_id' => $visitor_id,
                    'log_date' => $log_date,
                    'checked_in_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s')
            );
        }
        
        if ($result === false) {
            return new WP_Error('checkin_failed', 'Failed to record check-in');
        }
        
        // Update visitor's first_checkin_at if this is the first check-in
        if (empty($visitor['first_checkin_at'])) {
            $wpdb->update(
                $this->table,
                array('first_checkin_at' => current_time('mysql')),
                array('id' => $visitor_id),
                array('%s'),
                array('%d')
            );
        }
        
        // Trigger visit started if needed
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
        $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
        $visits_model = new SAW_Module_Visits_Model($visits_config);
        $visits_model->mark_as_started($visit_id);
        
        $this->invalidate_item_cache($visitor_id);
        
        return true;
    }
    
    /**
     * Daily check-out
     * Records visitor departure for specific day
     * 
     * @param int $visitor_id Visitor ID
     * @param string $log_date Date (Y-m-d), default today
     * @param bool $manual Is this manual checkout by admin?
     * @param int|null $admin_id Admin user ID (if manual)
     * @param string|null $reason Reason for manual checkout
     * @return bool|WP_Error True on success
     */
    public function daily_checkout($visitor_id, $log_date = null, $manual = false, $admin_id = null, $reason = null) {
    global $wpdb;
    
    if (!$log_date) {
        $log_date = current_time('Y-m-d');
    }
    
    // ✅ OPRAVENO: Přímý SQL query místo get_by_id (kvůli customer isolation)
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ), ARRAY_A);
    
    if (!$visitor) {
        error_log("[SAW Checkout] ERROR: Visitor ID {$visitor_id} not found in database");
        return new WP_Error('visitor_not_found', 'Visitor not found');
    }
    
    error_log("[SAW Checkout] Visitor found: {$visitor['first_name']} {$visitor['last_name']}");
    
    $visit_id = $visitor['visit_id'];
    
    // Find today's log
    $log = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
     WHERE visit_id = %d 
     AND visitor_id = %d 
     AND log_date = %s
     AND checked_in_at IS NOT NULL
     AND checked_out_at IS NULL
     ORDER BY checked_in_at DESC
     LIMIT 1",
    $visit_id,
    $visitor_id,
    $log_date
), ARRAY_A);
    
    if (!$log) {
    error_log("[SAW Checkout] ERROR: No active check-in found for date {$log_date}");
    return new WP_Error('no_active_checkin', 'Návštěvník není momentálně přítomen');
}
    
    // Update log with checkout
    $update_data = array(
        'checked_out_at' => current_time('mysql'),
    );
    
    if ($manual) {
        $update_data['manual_checkout'] = 1;
        $update_data['manual_checkout_by'] = $admin_id;
        $update_data['manual_checkout_reason'] = $reason;
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'saw_visit_daily_logs',
        $update_data,
        array('id' => $log['id']),
        array_fill(0, count($update_data), '%s'),
        array('%d')
    );
    
    if ($result === false) {
        error_log("[SAW Checkout] ERROR: Failed to update daily log");
        return new WP_Error('checkout_failed', 'Failed to record check-out');
    }
    
    // Update visitor's last_checkout_at
    $wpdb->update(
        $this->table,
        array('last_checkout_at' => current_time('mysql')),
        array('id' => $visitor_id),
        array('%s'),
        array('%d')
    );
    
    error_log("[SAW Checkout] SUCCESS: Visitor {$visitor_id} checked out");
    
    // Check if visit should be completed
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
    $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
    $visits_model = new SAW_Module_Visits_Model($visits_config);
    $visits_model->check_and_complete_visit($visit_id);
    
    $this->invalidate_item_cache($visitor_id);
    
    return true;
}
    
    /**
     * Add ad-hoc visitor
     * Creates visitor on-the-fly (e.g., someone unplanned shows up)
     * 
     * @param int $visit_id Visit ID
     * @param array $visitor_data Visitor data
     * @return int|WP_Error Visitor ID or error
     */
    public function add_adhoc_visitor($visit_id, $visitor_data) {
        global $wpdb;
        
        // Verify visit exists
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_visits',
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Visit not found');
        }
        
        // Create visitor
        $data = array(
            'visit_id' => $visit_id,
            'first_name' => sanitize_text_field($visitor_data['first_name']),
            'last_name' => sanitize_text_field($visitor_data['last_name']),
            'position' => !empty($visitor_data['position']) ? sanitize_text_field($visitor_data['position']) : null,
            'email' => !empty($visitor_data['email']) ? sanitize_email($visitor_data['email']) : null,
            'phone' => !empty($visitor_data['phone']) ? sanitize_text_field($visitor_data['phone']) : null,
            'participation_status' => 'confirmed', // Ad-hoc is confirmed
            'training_skipped' => !empty($visitor_data['training_skipped']) ? 1 : 0,
        );
        
        $result = $wpdb->insert(
            $this->table,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if (!$result) {
            return new WP_Error('insert_failed', 'Failed to create ad-hoc visitor');
        }
        
        $this->invalidate_list_cache();
        
        return $wpdb->insert_id;
    }
    
    // ============================================
    // EXISTING METHODS - CERTIFICATES & DATA
    // ============================================
    
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
    
    // ============================================
    // CACHE INVALIDATION
    // ============================================
    
    /**
     * Invalidate single item cache
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_visitors_item_%d', $id);
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate list cache
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_visitors_list_%' 
             OR option_name LIKE '_transient_timeout_saw_visitors_list_%'"
        );
    }
}