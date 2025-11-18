<?php
/**
 * Visitors Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     3.0.0 - FIXED: Proper Base Model inheritance, cache methods
 */

if (!defined('ABSPATH')) {
    exit;
}

// ✅ CRITICAL: Load Base Model first
if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
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
     * Get all visitors with proper scope filtering
     * ✅ Uses Base Model's apply_data_scope()
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id && !saw_is_super_admin()) {
            return array('items' => array(), 'total' => 0);
        }
        
        $cache_key = $this->get_cache_key_with_scope('list', $filters);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // JOIN with visits for customer isolation
        $sql = "SELECT vis.*, 
                       v.customer_id,
                       v.company_id,
                       v.branch_id,
                       c.name as company_name,
                       b.name as branch_name
                FROM {$this->table} vis
                INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                WHERE 1=1";
        
        $params = array();
        
        // Customer isolation
        if ($customer_id) {
            $sql .= " AND v.customer_id = %d";
            $params[] = $customer_id;
        }
        
        // ✅ Apply scope filtering (role-based access)
        list($scope_where, $scope_params) = $this->apply_data_scope('v');
        if (!empty($scope_where)) {
            $sql .= $scope_where;
            $params = array_merge($params, $scope_params);
        }
        
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
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        
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
        
        $items = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
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
     * Create visitor - uses parent's method with cache invalidation
     */
    public function create($data) {
        $result = parent::create($data);
        return $result;
    }
    
    /**
     * Update visitor - uses parent's method with cache invalidation
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        return $result;
    }
    
    /**
     * Delete visitor - uses parent's method with cache invalidation
     */
    public function delete($id) {
        $result = parent::delete($id);
        return $result;
    }
    
    // ============================================
    // CUSTOM ACTIONS - Daily Check-in/out
    // ============================================
    
    /**
     * Check-in visitor for specific day
     * Supports re-entry after checkout
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
        
        // Check for existing log today
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visit_id = %d AND visitor_id = %d AND log_date = %s
             ORDER BY checked_in_at DESC LIMIT 1",
            $visit_id, $visitor_id, $log_date
        ), ARRAY_A);
        
        if ($existing_log) {
            // If already checked out, create new entry (re-entry)
            if (!empty($existing_log['checked_out_at'])) {
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
            } else {
                // Update existing check-in time
                $result = $wpdb->update(
                    $wpdb->prefix . 'saw_visit_daily_logs',
                    array('checked_in_at' => current_time('mysql')),
                    array('id' => $existing_log['id']),
                    array('%s'),
                    array('%d')
                );
            }
        } else {
            // Create new log entry
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
            return new WP_Error('checkin_failed', 'Failed to record check-in: ' . $wpdb->last_error);
        }
        
        // Update first check-in timestamp on visitor record
        if (empty($visitor['first_checkin_at'])) {
            $wpdb->update(
                $this->table,
                array('first_checkin_at' => current_time('mysql')),
                array('id' => $visitor_id),
                array('%s'),
                array('%d')
            );
        }
        
        // Mark visit as started if not already
        $this->mark_visit_as_started($visit_id);
        
        // 🔥 CRITICAL: Invalidate cache
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Check-out visitor for specific day
     * Supports manual checkout by admin
     */
    public function daily_checkout($visitor_id, $log_date = null, $manual = false, $admin_id = null, $reason = null) {
        global $wpdb;
        
        if (!$log_date) {
            $log_date = current_time('Y-m-d');
        }
        
        $visitor = $this->get_by_id($visitor_id);
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Visitor not found');
        }
        
        $visit_id = $visitor['visit_id'];
        
        // Find latest active check-in for today
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visit_id = %d AND visitor_id = %d AND log_date = %s
             AND checked_in_at IS NOT NULL AND checked_out_at IS NULL
             ORDER BY checked_in_at DESC LIMIT 1",
            $visit_id, $visitor_id, $log_date
        ), ARRAY_A);
        
        if (!$log) {
            return new WP_Error('no_active_checkin', 'Návštěvník není momentálně přítomen');
        }
        
        // Prepare update data
        $update_data = array('checked_out_at' => current_time('mysql'));
        $formats = array('%s');
        
        if ($manual) {
            $update_data['manual_checkout'] = 1;
            $update_data['manual_checkout_by'] = $admin_id;
            $update_data['manual_checkout_reason'] = $reason;
            $formats[] = '%d';
            $formats[] = '%d';
            $formats[] = '%s';
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visit_daily_logs',
            $update_data,
            array('id' => $log['id']),
            $formats,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('checkout_failed', 'Failed to record check-out: ' . $wpdb->last_error);
        }
        
        // Update last checkout timestamp on visitor record
        $wpdb->update(
            $this->table,
            array('last_checkout_at' => current_time('mysql')),
            array('id' => $visitor_id),
            array('%s'),
            array('%d')
        );
        
        // Check if visit can be marked as completed
        $this->check_and_complete_visit($visit_id);
        
        // 🔥 CRITICAL: Invalidate cache
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Add ad-hoc visitor to existing visit
     */
    public function add_adhoc_visitor($visit_id, $visitor_data) {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Visit not found');
        }
        
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
        
        $result = $wpdb->insert(
            $this->table,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if (!$result) {
            return new WP_Error('insert_failed', 'Failed to create ad-hoc visitor: ' . $wpdb->last_error);
        }
        
        // 🔥 CRITICAL: Invalidate cache
        $this->invalidate_cache();
        
        return $wpdb->insert_id;
    }
    
    // ============================================
    // HELPER METHODS
    // ============================================
    
    /**
     * Get certificates for visitor
     */
    public function get_certificates($visitor_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visitor_certificates 
             WHERE visitor_id = %d 
             ORDER BY created_at DESC",
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
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visitor_id = %d 
             ORDER BY log_date DESC, checked_in_at DESC",
            $visitor_id
        ), ARRAY_A);
    }
    
    /**
     * Get visit data with relations
     */
    public function get_visit_data($visit_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, 
                    c.name as company_name, 
                    b.name as branch_name
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
             WHERE v.id = %d",
            $visit_id
        ), ARRAY_A);
    }
    
    /**
     * Get visits for select dropdown
     */
    public function get_visits_for_select() {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        if (!$customer_id) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.id, c.name as company_name, v.status
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             WHERE v.customer_id = %d
             ORDER BY v.created_at DESC 
             LIMIT 100",
            $customer_id
        ), ARRAY_A);
    }
    
    /**
     * Mark visit as started if not already
     */
    private function mark_visit_as_started($visit_id) {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if ($visit && $visit['status'] === 'confirmed') {
            $wpdb->update(
                $wpdb->prefix . 'saw_visits',
                array('status' => 'in_progress'),
                array('id' => $visit_id),
                array('%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Check if all visitors checked out and mark visit as completed
     */
    private function check_and_complete_visit($visit_id) {
        global $wpdb;
        
        // Count visitors still checked in
        $checked_in_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT vis.id)
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visit_daily_logs log ON vis.id = log.visitor_id
             WHERE vis.visit_id = %d
             AND log.log_date = %s
             AND log.checked_in_at IS NOT NULL
             AND log.checked_out_at IS NULL",
            $visit_id,
            current_time('Y-m-d')
        ));
        
        // If no one is checked in, mark visit as completed
        if ($checked_in_count == 0) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visits',
                array('status' => 'completed'),
                array('id' => $visit_id),
                array('%s'),
                array('%d')
            );
        }
    }
}