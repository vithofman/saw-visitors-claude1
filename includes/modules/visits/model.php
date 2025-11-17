<?php
/**
 * Visits Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.0.0 - REFACTORED: Multi-day tracking, walk-in, fire alarm support
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Visits_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate visit data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        // Company is NOT required if physical person (company_id = NULL)
        // This is handled by has_company radio in form
        
        if (!empty($data['invitation_email']) && !is_email($data['invitation_email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Get visit by ID with customer isolation
     */
    public function get_by_id($id) {
        $cache_key = sprintf('saw_visits_item_%d', $id);
        $item = get_transient($cache_key);
        
        if ($item === false) {
            $item = parent::get_by_id($id);
            
            if ($item) {
                set_transient($cache_key, $item, $this->cache_ttl);
            }
        }
        
        if (!$item) {
            return null;
        }
        
        $current_customer_id = SAW_Context::get_customer_id();
        
        if (!current_user_can('manage_options')) {
            if (empty($item['customer_id']) || $item['customer_id'] != $current_customer_id) {
                return null;
            }
        }
        
        return $item;
    }
    
    /**
     * Get all visits with filters and pagination
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        if (!$customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        $cache_key = sprintf(
            'saw_visits_list_%d_%s_%s',
            $customer_id,
            $branch_id ? $branch_id : 'all',
            md5(serialize($filters))
        );
        
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Base WHERE conditions
        $where_conditions = array('v.customer_id = %d');
        $params = array($customer_id);
        
        if ($branch_id) {
            $where_conditions[] = 'v.branch_id = %d';
            $params[] = $branch_id;
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'v.status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_conditions[] = '(c.name LIKE %s OR v.purpose LIKE %s OR v.invitation_email LIKE %s)';
            $params[] = $search_value;
            $params[] = $search_value;
            $params[] = $search_value;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total
        $count_sql = "SELECT COUNT(DISTINCT v.id) 
                      FROM {$this->table} v 
                      LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id 
                      WHERE {$where_clause}";
        $count_sql = $wpdb->prepare($count_sql, ...$params);
        $total = (int) $wpdb->get_var($count_sql);
        
        // Main query with subquery for sorting
        $sql = "SELECT v.*, 
                c.name as company_name,
                (SELECT MIN(date) FROM {$wpdb->prefix}saw_visit_schedules WHERE visit_id = v.id) as first_schedule_date,
                (SELECT CONCAT(first_name, ' ', last_name) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id ORDER BY id ASC LIMIT 1) as first_visitor_name
                FROM {$this->table} v 
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id 
                WHERE {$where_clause}";
        
        // Sorting
        $orderby = $filters['orderby'] ?? 'first_schedule_date';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        $allowed_orderby = array('id', 'first_schedule_date', 'status', 'company_name', 'started_at');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'first_schedule_date';
        }
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'DESC';
        }
        
        if ($orderby === 'first_schedule_date') {
            $sql .= " ORDER BY first_schedule_date IS NULL, first_schedule_date {$order}";
        } elseif ($orderby === 'company_name') {
            $sql .= " ORDER BY c.name {$order}";
        } else {
            $sql .= " ORDER BY v.{$orderby} {$order}";
        }
        
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
     * Create visit and invalidate cache
     */
    public function create($data) {
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Update visit and invalidate cache
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
     * Delete visit and invalidate cache
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
    // NEW METHODS - TRACKING & BUSINESS LOGIC
    // ============================================
    
    /**
     * Mark visit as started (first check-in)
     * 
     * @param int $visit_id Visit ID
     * @return bool True on success, WP_Error on failure
     */
    public function mark_as_started($visit_id) {
        global $wpdb;
        
        $visit = $this->get_by_id($visit_id);
        
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Visit not found');
        }
        
        // Only mark if not already started
        if (!empty($visit['started_at'])) {
            return true;
        }
        
        $result = $wpdb->update(
            $this->table,
            array(
                'started_at' => current_time('mysql'),
                'status' => 'in_progress',
            ),
            array('id' => $visit_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to mark visit as started');
        }
        
        $this->invalidate_item_cache($visit_id);
        $this->invalidate_list_cache();
        
        return true;
    }
    
    /**
     * Check if visit should be completed and complete it
     * Called after every checkout
     * 
     * @param int $visit_id Visit ID
     * @return bool True if completed, false if still ongoing
     */
    public function check_and_complete_visit($visit_id) {
        global $wpdb;
        
        // 1. Is this the last day?
        $last_schedule_date = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(date) FROM %i WHERE visit_id = %d",
            $wpdb->prefix . 'saw_visit_schedules',
            $visit_id
        ));
        
        if (!$last_schedule_date) {
            // Walk-in has no schedule, check today
            $last_schedule_date = current_time('Y-m-d');
        }
        
        if ($last_schedule_date !== current_time('Y-m-d')) {
            return false; // Not the last day yet
        }
        
        // 2. Did everyone check out today?
        $still_inside = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i 
             WHERE visit_id = %d 
             AND log_date = %s
             AND checked_in_at IS NOT NULL 
             AND checked_out_at IS NULL",
            $wpdb->prefix . 'saw_visit_daily_logs',
            $visit_id,
            current_time('Y-m-d')
        ));
        
        if ($still_inside > 0) {
            return false; // Someone still inside
        }
        
        // 3. Everyone checked out on last day → COMPLETE
        return $this->mark_as_completed($visit_id);
    }
    
    /**
     * Mark visit as completed
     * 
     * @param int $visit_id Visit ID
     * @return bool True on success
     */
    public function mark_as_completed($visit_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table,
            array(
                'completed_at' => current_time('mysql'),
                'status' => 'completed',
            ),
            array('id' => $visit_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to mark visit as completed');
        }
        
        // Update all visitors' last_checkout_at
        $wpdb->query($wpdb->prepare(
            "UPDATE %i vis
             INNER JOIN (
                 SELECT visitor_id, MAX(checked_out_at) as last_out
                 FROM %i 
                 WHERE visit_id = %d AND checked_out_at IS NOT NULL
                 GROUP BY visitor_id
             ) logs ON vis.id = logs.visitor_id
             SET vis.last_checkout_at = logs.last_out
             WHERE vis.visit_id = %d",
            $wpdb->prefix . 'saw_visitors',
            $wpdb->prefix . 'saw_visit_daily_logs',
            $visit_id,
            $visit_id
        ));
        
        $this->invalidate_item_cache($visit_id);
        $this->invalidate_list_cache();
        
        return true;
    }
    
    /**
     * Get currently present visitors (FIRE ALARM)
     * 
     * @param int $branch_id Branch ID
     * @param string $date Date (Y-m-d), default today
     * @return array List of visitors currently inside
     */
    public function get_currently_present($branch_id, $date = null) {
        global $wpdb;
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT v.id as visit_id,
		    vis.id as visitor_id,
                    c.name as company_name,
                    CONCAT(vis.first_name, ' ', vis.last_name) as visitor_name,
                    vis.phone,
                    vis.email,
                    dl.checked_in_at as today_checkin,
                    TIMESTAMPDIFF(MINUTE, dl.checked_in_at, NOW()) as minutes_inside
             FROM %i dl
             INNER JOIN %i v ON dl.visit_id = v.id
             INNER JOIN %i vis ON dl.visitor_id = vis.id
             LEFT JOIN %i c ON v.company_id = c.id
             WHERE v.branch_id = %d
               AND dl.log_date = %s
               AND dl.checked_in_at IS NOT NULL
               AND dl.checked_out_at IS NULL
             ORDER BY dl.checked_in_at DESC",
            $wpdb->prefix . 'saw_visit_daily_logs',
            $wpdb->prefix . 'saw_visits',
            $wpdb->prefix . 'saw_visitors',
            $wpdb->prefix . 'saw_companies',
            $branch_id,
            $date
        ), ARRAY_A);
        
        return $results ?: array();
    }
    
    /**
     * Find or create company (walk-in)
     * 
     * @param int $branch_id Branch ID
     * @param string $company_name Company name
     * @return int Company ID
     */
    public function find_or_create_company($branch_id, $company_name) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            return new WP_Error('no_customer', 'Customer context required');
        }
        
        // Normalize name
        $normalized = $this->normalize_company_name($company_name);
        
        // Search for similar company
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i 
             WHERE customer_id = %d 
             AND LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), 's.r.o.', ''), 'a.s.', '')) LIKE %s
             LIMIT 1",
            $wpdb->prefix . 'saw_companies',
            $customer_id,
            '%' . $wpdb->esc_like($normalized) . '%'
        ));
        
        if ($existing) {
            return intval($existing);
        }
        
        // Create new company
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_companies',
            array(
                'customer_id' => $customer_id,
                'name' => sanitize_text_field($company_name),
                'ico' => null, // Walk-in doesn't know ICO
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return new WP_Error('insert_failed', 'Failed to create company');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Normalize company name for comparison
     * 
     * @param string $name Company name
     * @return string Normalized name
     */
    private function normalize_company_name($name) {
        $name = strtolower($name);
        $name = str_replace(array('s.r.o.', 'a.s.', 'spol.', 'v.o.s.', ',', '.'), '', $name);
        $name = preg_replace('/\s+/', '', $name);
        return $name;
    }


/**
     * Generate unique PIN for visit
     * 
     * @return string 6-digit PIN
     */
    public function generate_pin() {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        do {
            $pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                 WHERE pin_code = %s AND customer_id = %d",
                $pin, $customer_id
            ));
        } while ($exists > 0);
        
        return $pin;
    }

    
    /**
     * Create walk-in visit (immediate check-in)
     * 
     * @param array $data Visit data
     * @return int|WP_Error Visit ID or error
     */
    public function create_walkin_visit($data) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            return new WP_Error('no_customer', 'Customer context required');
        }
        
        $visit_data = array(
            'customer_id' => $customer_id,
            'branch_id' => $data['branch_id'],
            'company_id' => $data['company_id'] ?? null, // NULL for physical person
            'visit_type' => 'walk_in',
            'status' => 'in_progress',
            'started_at' => current_time('mysql'),
            'purpose' => $data['purpose'] ?? null,
            'created_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert(
            $this->table,
            $visit_data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return new WP_Error('insert_failed', 'Failed to create walk-in visit');
        }
        
        $this->invalidate_list_cache();
        
        return $wpdb->insert_id;
    }
    
    // ============================================
    // EXISTING METHODS - SCHEDULES & HOSTS
    // ============================================
    
    /**
     * Save visit hosts (M:N relation)
     */
    public function save_hosts($visit_id, $user_ids) {
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->prefix . 'saw_visit_hosts',
            array('visit_id' => $visit_id)
        );
        
        if (!empty($user_ids)) {
            foreach ($user_ids as $user_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_hosts',
                    array(
                        'visit_id' => $visit_id,
                        'user_id' => $user_id,
                    )
                );
            }
        }
    }
    
    /**
     * Get visit hosts
     */
    public function get_hosts($visit_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.position
             FROM %i vh
             INNER JOIN %i u ON vh.user_id = u.id
             WHERE vh.visit_id = %d
             ORDER BY u.last_name, u.first_name",
            $wpdb->prefix . 'saw_visit_hosts',
            $wpdb->prefix . 'saw_users',
            $visit_id
        ), ARRAY_A);
    }

/**
     * Get visit visitors
     * 
     * @param int $visit_id Visit ID
     * @param bool $only_confirmed Filter only confirmed visitors (default false)
     * @return array List of visitors
     */
    public function get_visitors($visit_id, $only_confirmed = false) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d";
        
        if ($only_confirmed) {
            $sql .= " AND participation_status = 'confirmed'";
        }
        
        $sql .= " ORDER BY last_name ASC, first_name ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $visit_id), ARRAY_A);
    }
    
    /**
     * Save visit schedules (multiple days)
     */
    public function save_schedules($visit_id, $post_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'saw_visit_schedules';
        
        $wpdb->delete($table, array('visit_id' => $visit_id), array('%d'));
        
        $dates = $post_data['schedule_dates'] ?? array();
        $times_from = $post_data['schedule_times_from'] ?? array();
        $times_to = $post_data['schedule_times_to'] ?? array();
        $notes_arr = $post_data['schedule_notes'] ?? array();
        
        if (empty($dates) || !is_array($dates)) {
            return new WP_Error('no_schedules', 'Žádné dny návštěvy nebyly zadány');
        }
        
        $unique_dates = array();
        foreach ($dates as $date) {
            if (!empty($date)) {
                if (in_array($date, $unique_dates)) {
                    return new WP_Error('duplicate_dates', 'Některá data se opakují');
                }
                $unique_dates[] = $date;
            }
        }
        
        $sort_order = 0;
        $insert_count = 0;
        
        foreach ($dates as $index => $date) {
            if (empty($date)) {
                continue;
            }
            
            $schedule_data = array(
                'visit_id' => $visit_id,
                'date' => sanitize_text_field($date),
                'time_from' => !empty($times_from[$index]) ? sanitize_text_field($times_from[$index]) : null,
                'time_to' => !empty($times_to[$index]) ? sanitize_text_field($times_to[$index]) : null,
                'notes' => !empty($notes_arr[$index]) ? sanitize_text_field($notes_arr[$index]) : null,
                'sort_order' => $sort_order++,
            );
            
            $result = $wpdb->insert($table, $schedule_data, array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
            ));
            
            if ($result) {
                $insert_count++;
            }
        }
        
        $this->invalidate_item_cache($visit_id);
        $this->invalidate_list_cache();
        
        return $insert_count > 0 ? true : new WP_Error('insert_failed', 'Nepodařilo se uložit dny návštěvy');
    }
    
    /**
     * Get visit schedules
     */
    public function get_schedules($visit_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE visit_id = %d ORDER BY date ASC",
            $wpdb->prefix . 'saw_visit_schedules',
            $visit_id
        ), ARRAY_A);
    }
    
    /**
     * Get first schedule date
     */
    public function get_first_schedule_date($visit_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT date FROM %i WHERE visit_id = %d ORDER BY date ASC LIMIT 1",
            $wpdb->prefix . 'saw_visit_schedules',
            $visit_id
        ));
    }
    
    /**
     * Get schedule date range formatted
     */
    public function get_schedule_date_range($visit_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT MIN(date) as first_date, MAX(date) as last_date, COUNT(*) as day_count 
             FROM %i WHERE visit_id = %d",
            $wpdb->prefix . 'saw_visit_schedules',
            $visit_id
        ), ARRAY_A);
        
        if (empty($result) || empty($result['first_date'])) {
            return '—';
        }
        
        if ($result['day_count'] == 1) {
            return date('d.m.Y', strtotime($result['first_date']));
        } else {
            $first = date('d.m.', strtotime($result['first_date']));
            $last = date('d.m.Y', strtotime($result['last_date']));
            return "{$first} - {$last}";
        }
    }
    
    // ============================================
    // CACHE INVALIDATION
    // ============================================
    
    /**
     * Invalidate single item cache
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_visits_item_%d', $id);
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate list cache
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_visits_list_%' 
             OR option_name LIKE '_transient_timeout_saw_visits_list_%'"
        );
    }
}