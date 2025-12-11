<?php
/**
 * Visitors Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     3.3.0 - Added Info Portal token methods
 * 
 * Changelog:
 * - 3.3.0 (2025-12-08): Added Info Portal methods: generate_info_portal_token(), 
 *                        get_visitor_by_info_token(), is_info_portal_token_valid(),
 *                        mark_info_portal_email_sent(), should_send_info_portal_email()
 * - 3.2.0 (2025-12-08): Fixed daily_checkout() to find any active log when log_date is null
 * - 3.1.0 (2025-12-08): Added count_checked_in_visitors(), will_be_last_checkout()
 *                        Modified check_and_complete_visit() to not auto-complete
 *                        Modified daily_checkin() to handle completed visit reopening
 * - 3.0.0: FIXED: Proper Base Model inheritance, cache methods
 */

if (!defined('ABSPATH')) {
    exit;
}

// CRITICAL: Load Base Model first
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
        
        $page = isset($filters['page']) ? intval($filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 100;
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE conditions
        $where = array("v.customer_id = %d");
        $where_values = array($customer_id);
        
        // Branch isolation (if set)
        $branch_id = SAW_Context::get_branch_id();
        if ($branch_id) {
            $where[] = "v.branch_id = %d";
            $where_values[] = $branch_id;
        }
        
        // Training status filter
        if (!empty($filters['training_status'])) {
            $training_status = $filters['training_status'];
            switch ($training_status) {
                case 'completed':
                    $where[] = "vis.training_completed_at IS NOT NULL";
                    break;
                case 'in_progress':
                    $where[] = "vis.training_started_at IS NOT NULL AND vis.training_completed_at IS NULL";
                    break;
                case 'skipped':
                    $where[] = "vis.training_skipped = 1";
                    break;
                case 'not_started':
                    $where[] = "vis.training_started_at IS NULL AND vis.training_completed_at IS NULL AND vis.training_skipped = 0";
                    break;
            }
        }
        
        // Participation status filter
        if (!empty($filters['participation_status'])) {
            $where[] = "vis.participation_status = %s";
            $where_values[] = $filters['participation_status'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $search_conditions = array();
            $search_params = array();
            
            // If search is numeric, try exact ID match
            if (is_numeric($search)) {
                $search_conditions[] = "vis.id = %d";
                $search_params[] = intval($search);
            }
            
            // Always search in text fields
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $search_conditions[] = "(vis.first_name LIKE %s OR vis.last_name LIKE %s OR vis.email LIKE %s)";
            $search_params[] = $search_term;
            $search_params[] = $search_term;
            $search_params[] = $search_term;
            
            $where[] = "(" . implode(" OR ", $search_conditions) . ")";
            $where_values = array_merge($where_values, $search_params);
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Count total
        $count_sql = "SELECT COUNT(DISTINCT vis.id) 
                      FROM {$this->table} vis
                      INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
                      LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                      LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                      WHERE {$where_sql}";
        
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$where_values));
        
        // Main query
        $orderby = isset($filters['orderby']) ? $filters['orderby'] : 'vis.id';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Ensure orderby is safe
        $allowed_orderby = array('vis.id', 'vis.first_name', 'vis.last_name', 'vis.created_at', 'first_name', 'last_name');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'vis.id';
        }
        
        // Map simple column names to full table references
        if ($orderby === 'first_name') {
            $orderby = 'vis.first_name';
        } elseif ($orderby === 'last_name') {
            $orderby = 'vis.last_name';
        }
        
        $sql = "SELECT vis.*, 
               (SELECT MIN(dl.checked_in_at) 
                FROM {$wpdb->prefix}saw_visit_daily_logs dl 
                WHERE dl.visitor_id = vis.id 
                AND dl.checked_in_at IS NOT NULL) as first_checkin_at,
                    v.customer_id,
                    v.company_id,
                    v.branch_id,
                    c.name as company_name,
                    b.name as branch_name
                FROM {$this->table} vis
                INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                WHERE {$where_sql}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$where_values), ARRAY_A);
        
        $result = array(
            'items' => $items ?: array(),
            'total' => intval($total),
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
     * 
     * Supports re-entry after checkout and handles completed visit reopening.
     * 
     * @since 3.0.0
     * @updated 3.1.0 - Added completed visit reopening logic
     * @param int $visitor_id Visitor ID
     * @param string|null $log_date Date in Y-m-d format, defaults to today
     * @return bool|WP_Error True on success, WP_Error on failure
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
        
        // Check visit status before check-in
        $visit_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ));
        
        // Cannot check-in to cancelled visit
        if ($visit_status === 'cancelled') {
            return new WP_Error('visit_cancelled', 'Tato návštěva byla zrušena');
        }
        
        // If visit was completed, try to reopen it
        if ($visit_status === 'completed') {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
            $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
            $visits_model = new SAW_Module_Visits_Model($visits_config);
            
            $reopen_result = $visits_model->reopen_visit($visit_id);
            
            if (is_wp_error($reopen_result)) {
                return $reopen_result;
            }
        }
        
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
                        'customer_id' => $visitor['customer_id'],
                        'branch_id' => $visitor['branch_id'],
                        'log_date' => $log_date,
                        'checked_in_at' => current_time('mysql'),
                    ),
                    array('%d', '%d', '%d', '%d', '%s', '%s')
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
                    'customer_id' => $visitor['customer_id'],
                    'branch_id' => $visitor['branch_id'],
                    'log_date' => $log_date,
                    'checked_in_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s')
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
        
        // Update current_status to present
        $wpdb->update(
            $this->table,
            array('current_status' => 'present'),
            array('id' => $visitor_id),
            array('%s'),
            array('%d')
        );
        
        // Mark visit as started if not already
        $this->mark_visit_as_started($visit_id);
        
        // Invalidate cache
        $this->invalidate_cache();
        
        // ========================================
        // NOTIFICATION TRIGGER: visit_checkin
        // Notifikace hostitelům o příchodu návštěvníka
        // ========================================
        do_action('saw_visitor_checked_in', $visitor_id, $visit_id);
        
        return true;
    }
    
    /**
     * Check-out visitor for specific day
     * Supports manual checkout by admin
     * 
     * @since 3.0.0
     * @updated 3.1.0 - check_and_complete_visit no longer auto-completes
     * @updated 3.2.0 - Support null log_date to find any active log (multi-day visits)
     * 
     * @param int $visitor_id Visitor ID
     * @param string|null $log_date Date in Y-m-d format. If null, finds ANY active log regardless of date.
     * @param bool $manual Whether this is a manual checkout by admin
     * @param int|null $admin_id Admin user ID who performed manual checkout
     * @param string|null $reason Reason for manual checkout
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function daily_checkout($visitor_id, $log_date = null, $manual = false, $admin_id = null, $reason = null) {
        global $wpdb;
        
        $visitor = $this->get_by_id($visitor_id);
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Visitor not found');
        }
        
        $visit_id = $visitor['visit_id'];
        
        // Find latest active check-in
        // If log_date is null, find ANY active log (for dashboard/manual checkout of multi-day visits)
        // If log_date is specified, filter by that date (for terminal same-day checkout)
        if ($log_date === null) {
            // Find ANY active log regardless of date
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
                 WHERE visit_id = %d AND visitor_id = %d
                 AND checked_in_at IS NOT NULL AND checked_out_at IS NULL
                 ORDER BY checked_in_at DESC LIMIT 1",
                $visit_id, $visitor_id
            ), ARRAY_A);
        } else {
            // Find active log for specific date
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
                 WHERE visit_id = %d AND visitor_id = %d AND log_date = %s
                 AND checked_in_at IS NOT NULL AND checked_out_at IS NULL
                 ORDER BY checked_in_at DESC LIMIT 1",
                $visit_id, $visitor_id, $log_date
            ), ARRAY_A);
        }
        
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
        
        // Update last checkout timestamp and current_status on visitor record
        $wpdb->update(
            $this->table,
            array(
                'last_checkout_at' => current_time('mysql'),
                'current_status' => 'checked_out'
            ),
            array('id' => $visitor_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Check if visit can be marked as completed
        // NOTE: As of v3.1.0, this method no longer auto-completes - 
        // the terminal dialog handles the completion decision
        $this->check_and_complete_visit($visit_id);
        
        // Invalidate cache
        $this->invalidate_cache();
        
        // ========================================
        // NOTIFICATION TRIGGER: visit_checkout
        // Notifikace hostitelům o odchodu návštěvníka
        // ========================================
        do_action('saw_visitor_checked_out', $visitor_id, $visit_id);
        
        return true;
    }
    
    /**
     * Add ad-hoc visitor to existing visit
     * 
     * @since 3.0.0
     */
    public function add_adhoc_visitor($visit_id, $visitor_data) {
        global $wpdb;
        
        // Fetch parent visit
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, branch_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Visit not found');
        }
        
        // Build data WITH customer_id and branch_id
        $data = array(
            'visit_id' => $visit_id,
            'customer_id' => $visit['customer_id'],
            'branch_id' => $visit['branch_id'],
            'first_name' => sanitize_text_field($visitor_data['first_name']),
            'last_name' => sanitize_text_field($visitor_data['last_name']),
            'position' => !empty($visitor_data['position']) ? sanitize_text_field($visitor_data['position']) : null,
            'email' => !empty($visitor_data['email']) ? sanitize_email($visitor_data['email']) : null,
            'phone' => !empty($visitor_data['phone']) ? sanitize_text_field($visitor_data['phone']) : null,
            'participation_status' => 'confirmed',
            'current_status' => 'confirmed',
            'training_skipped' => !empty($visitor_data['training_skipped']) ? 1 : 0,
        );
        
        // Insert with correct format
        $result = $wpdb->insert(
            $this->table,
            $data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if (!$result) {
            return new WP_Error('insert_failed', 'Failed to create ad-hoc visitor: ' . $wpdb->last_error);
        }
        
        $this->invalidate_cache();
        return $wpdb->insert_id;
    }
    
    // ============================================
    // CHECKOUT CONFIRMATION SYSTEM v2 METHODS
    // ============================================
    
    /**
     * Count currently checked-in visitors for a visit
     * 
     * @since 3.1.0
     * @param int $visit_id Visit ID
     * @return int Number of currently checked-in visitors
     */
    public function count_checked_in_visitors($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return 0;
        }
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT vis.id)
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visit_daily_logs log ON vis.id = log.visitor_id
             WHERE vis.visit_id = %d
             AND log.checked_in_at IS NOT NULL
             AND log.checked_out_at IS NULL",
            $visit_id
        ));
    }

    /**
     * Check if this checkout will result in zero visitors remaining
     * 
     * @since 3.1.0
     * @param int $visit_id Visit ID
     * @param array $visitor_ids Array of visitor IDs about to be checked out
     * @return bool True if after checkout no one will remain
     */
    public function will_be_last_checkout($visit_id, $visitor_ids) {
        if (empty($visitor_ids) || !is_array($visitor_ids)) {
            return false;
        }
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return false;
        }
        
        $current_count = $this->count_checked_in_visitors($visit_id);
        $checkout_count = count($visitor_ids);
        $remaining = $current_count - $checkout_count;
        
        return $remaining <= 0;
    }
    
    // ============================================
    // INFO PORTAL METHODS (v3.3.0)
    // ============================================
    
    /**
     * Generate unique info portal token for visitor
     * 
     * Creates a 64-character alphanumeric token for accessing the info portal.
     * If token already exists, returns existing one.
     * 
     * @since 3.3.0
     * @param int $visitor_id Visitor ID
     * @return string|WP_Error Token string or WP_Error on failure
     */
    public function generate_info_portal_token($visitor_id) {
        global $wpdb;
        
        $visitor_id = intval($visitor_id);
        if (!$visitor_id) {
            return new WP_Error('invalid_visitor_id', 'Neplatné ID návštěvníka');
        }
        
        $visitor = $this->get_by_id($visitor_id);
        if (!$visitor) {
            return new WP_Error('visitor_not_found', 'Návštěvník nenalezen');
        }
        
        // Return existing token if already generated
        if (!empty($visitor['info_portal_token'])) {
            return $visitor['info_portal_token'];
        }
        
        // Generate unique 64-character token
        $token = '';
        $max_attempts = 10;
        $attempt = 0;
        
        do {
            $token = wp_generate_password(64, false, false);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE info_portal_token = %s",
                $token
            ));
            $attempt++;
        } while ($exists && $attempt < $max_attempts);
        
        if ($exists) {
            return new WP_Error('token_generation_failed', 'Nepodařilo se vygenerovat unikátní token');
        }
        
        // Save token
        $result = $wpdb->update(
            $this->table,
            array(
                'info_portal_token' => $token,
                'info_portal_token_created_at' => current_time('mysql'),
            ),
            array('id' => $visitor_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('token_save_failed', 'Nepodařilo se uložit token');
        }
        
        $this->invalidate_cache();
        
        return $token;
    }
    
    /**
     * Get visitor by info portal token with all related data
     * 
     * @since 3.3.0
     * @param string $token 64-character token
     * @return array|null Visitor data with visit/company info or null
     */
    public function get_visitor_by_info_token($token) {
        global $wpdb;
        
        // Validate token format
        if (empty($token) || strlen($token) !== 64) {
            return null;
        }
        
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        if (strlen($token) !== 64) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                v.*,
                vis.status as visit_status,
                vis.visit_type,
                vis.planned_date_from,
                vis.planned_date_to,
                vis.completed_at as visit_completed_at,
                c.name as company_name,
                b.name as branch_name,
                cust.name as customer_name
             FROM {$this->table} v
             INNER JOIN {$wpdb->prefix}saw_visits vis ON v.visit_id = vis.id
             LEFT JOIN {$wpdb->prefix}saw_companies c ON vis.company_id = c.id
             INNER JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
             INNER JOIN {$wpdb->prefix}saw_customers cust ON v.customer_id = cust.id
             WHERE v.info_portal_token = %s",
            $token
        ), ARRAY_A);
    }
    
    /**
     * Check if info portal token is still valid
     * 
     * Token validity rules:
     * - Active visits (draft/pending/confirmed/in_progress): always valid
     * - Cancelled visits: never valid
     * - Completed visits: valid for grace_hours after completion
     * 
     * @since 3.3.0
     * @param array $visitor Visitor data from get_visitor_by_info_token()
     * @param int $grace_hours Hours after visit completion when token remains valid (default 48)
     * @return bool True if token is valid
     */
    public function is_info_portal_token_valid($visitor, $grace_hours = 48) {
        if (empty($visitor)) {
            return false;
        }
        
        $visit_status = $visitor['visit_status'] ?? '';
        
        // Active visits - always valid
        if (in_array($visit_status, array('draft', 'pending', 'confirmed', 'in_progress'), true)) {
            return true;
        }
        
        // Cancelled - never valid
        if ($visit_status === 'cancelled') {
            return false;
        }
        
        // Completed - check grace period
        if ($visit_status === 'completed') {
            if (empty($visitor['visit_completed_at'])) {
                return false;
            }
            
            $grace_end = strtotime($visitor['visit_completed_at']) + ($grace_hours * 3600);
            return time() <= $grace_end;
        }
        
        return false;
    }
    
    /**
     * Mark that info portal email was sent to visitor
     * 
     * @since 3.3.0
     * @param int $visitor_id Visitor ID
     * @return bool True on success, false on failure
     */
    public function mark_info_portal_email_sent($visitor_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table,
            array('info_portal_email_sent_at' => current_time('mysql')),
            array('id' => intval($visitor_id)),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->invalidate_cache();
        }
        
        return $result !== false;
    }
    
    /**
     * Check if info portal email should be sent to visitor
     * 
     * Email should be sent if:
     * - Visitor has valid email address
     * - Email has not been sent yet (info_portal_email_sent_at is NULL)
     * 
     * @since 3.3.0
     * @param int $visitor_id Visitor ID
     * @return bool True if email should be sent
     */
    public function should_send_info_portal_email($visitor_id) {
        global $wpdb;
        
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT email, info_portal_email_sent_at 
             FROM {$this->table} 
             WHERE id = %d",
            intval($visitor_id)
        ), ARRAY_A);
        
        if (!$visitor) {
            return false;
        }
        
        // Must have valid email
        if (empty($visitor['email']) || !is_email($visitor['email'])) {
            return false;
        }
        
        // Must not be sent yet
        return empty($visitor['info_portal_email_sent_at']);
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
        
        // Get visitor for customer_id and branch_id
        $visitor = $this->get_by_id($visitor_id);
        
        // Insert new certificates
        foreach ($certificates_data as $cert) {
            if (empty($cert['certificate_name'])) {
                continue;
            }
            
            $wpdb->insert(
                $wpdb->prefix . 'saw_visitor_certificates',
                array(
                    'visitor_id' => $visitor_id,
                    'customer_id' => $visitor['customer_id'],
                    'branch_id' => $visitor['branch_id'],
                    'certificate_name' => sanitize_text_field($cert['certificate_name']),
                    'certificate_number' => !empty($cert['certificate_number']) ? sanitize_text_field($cert['certificate_number']) : null,
                    'valid_until' => !empty($cert['valid_until']) ? sanitize_text_field($cert['valid_until']) : null,
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s')
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
     * Mark visit as started - sets started_at to earliest visitor check-in
     * 
     * @since 3.0.0
     * @param int $visit_id Visit ID
     */
    private function mark_visit_as_started($visit_id) {
        global $wpdb;
        
        // Get earliest check-in time from all visitors
        $earliest_checkin = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(first_checkin_at) 
             FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d 
             AND first_checkin_at IS NOT NULL",
            $visit_id
        ));
        
        // Update visit status and started_at
        if ($earliest_checkin) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visits',
                array(
                    'status' => 'in_progress',
                    'started_at' => $earliest_checkin
                ),
                array('id' => $visit_id),
                array('%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Check if all visitors checked out
     * 
     * NOTE: As of v3.1.0, this method NO LONGER auto-completes the visit.
     * 
     * @since 3.0.0
     * @updated 3.1.0 - Removed auto-completion logic
     * @param int $visit_id Visit ID
     * @return bool True if all visitors are checked out
     */
    private function check_and_complete_visit($visit_id) {
        global $wpdb;
        
        // Count visitors still checked in (ANY day - supports overnight visits)
        $checked_in_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT vis.id)
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visit_daily_logs log ON vis.id = log.visitor_id
             WHERE vis.visit_id = %d
             AND log.checked_in_at IS NOT NULL
             AND log.checked_out_at IS NULL",
            $visit_id
        ));
        
        return ((int)$checked_in_count === 0);
    }
}