<?php
/**
 * Visits Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     2.1.0 - Added search, filters, and schedule-based sorting
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
    
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (empty($data['company_id'])) {
            $errors['company_id'] = 'Company is required';
        }
        
        if (!empty($data['invitation_email']) && !is_email($data['invitation_email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
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
    
    if (isset($filters['is_archived']) && $filters['is_archived'] !== '') {
        $where_conditions[] = 'v.is_archived = %d';
        $params[] = intval($filters['is_archived']);
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
            (SELECT MIN(date) FROM {$wpdb->prefix}saw_visit_schedules WHERE visit_id = v.id) as first_schedule_date
            FROM {$this->table} v 
            LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id 
            WHERE {$where_clause}";
    
    // Sorting
    $orderby = $filters['orderby'] ?? 'first_schedule_date';
    $order = strtoupper($filters['order'] ?? 'DESC');
    
    $allowed_orderby = array('id', 'first_schedule_date', 'status', 'company_name');
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
    
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_visits_item_%d', $id);
        delete_transient($cache_key);
    }
    
    private function invalidate_list_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_visits_list_%' 
             OR option_name LIKE '_transient_timeout_saw_visits_list_%'"
        );
    }
    
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
    
    public function get_schedules($visit_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE visit_id = %d ORDER BY date ASC",
            $wpdb->prefix . 'saw_visit_schedules',
            $visit_id
        ), ARRAY_A);
    }
    
    public function get_first_schedule_date($visit_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT date FROM %i WHERE visit_id = %d ORDER BY date ASC LIMIT 1",
            $wpdb->prefix . 'saw_visit_schedules',
            $visit_id
        ));
    }
    
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
}