<?php
/**
 * Visits Module Model
 * @version 3.4.0 - Added find_or_create_company() method for terminal
 */

if (!defined('ABSPATH')) exit;

class SAW_Module_Visits_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = 300;
    }
    
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (!empty($data['invitation_email']) && !is_email($data['invitation_email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if ($item) {
            $item['status_label'] = $this->get_status_label($item['status'] ?? 'pending');
            
            if (!empty($item['created_at'])) {
                $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
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
        
        $page = isset($filters['page']) ? intval($filters['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $where = array("v.customer_id = %d");
        $where_values = array($customer_id);
        
        $branch_id = SAW_Context::get_branch_id();
        if ($branch_id) {
            $where[] = "v.branch_id = %d";
            $where_values[] = $branch_id;
        }
        
        if (!empty($filters['status'])) {
            $where[] = "v.status = %s";
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(c.name LIKE %s OR vis.first_name LIKE %s OR vis.last_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $orderby = isset($filters['orderby']) ? $filters['orderby'] : 'id';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $count_sql = "SELECT COUNT(DISTINCT v.id) FROM {$wpdb->prefix}saw_visits v
                      LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                      LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
                      WHERE {$where_sql}";
        
        $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$where_values));
        
        $sql = "SELECT v.*, 
                       c.name as company_name,
                       b.name as branch_name,
                       (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id) as visitor_count,
                       (SELECT CONCAT(first_name, ' ', last_name) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id ORDER BY id ASC LIMIT 1) as first_visitor_name
                FROM {$wpdb->prefix}saw_visits v
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                WHERE {$where_sql}
                ORDER BY v.{$orderby} {$order}
                LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$where_values), ARRAY_A);
        
        foreach ($items as &$item) {
            if (!empty($item['created_at'])) {
                $item['created_at'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
            }
        }
        
        return array(
            'items' => $items,
            'total' => intval($total),
        );
    }
    
    /**
     * Get currently present visitors for dashboard widget
     * 
     * @since 3.3.0
     * @param int $branch_id Branch ID
     * @return array List of currently present visitors
     */
    public function get_currently_present($branch_id) {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        
        $sql = "SELECT 
                    vis.id as visitor_id,
                    CONCAT(vis.first_name, ' ', vis.last_name) as visitor_name,
                    vis.phone,
                    c.name as company_name,
                    log.checked_in_at as today_checkin,
                    TIMESTAMPDIFF(MINUTE, log.checked_in_at, NOW()) as minutes_inside
                FROM {$wpdb->prefix}saw_visit_daily_logs log
                INNER JOIN {$wpdb->prefix}saw_visitors vis ON log.visitor_id = vis.id
                INNER JOIN {$wpdb->prefix}saw_visits v ON log.visit_id = v.id
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                WHERE v.branch_id = %d
                  AND log.log_date = %s
                  AND log.checked_in_at IS NOT NULL
                  AND log.checked_out_at IS NULL
                ORDER BY log.checked_in_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $branch_id, $today), ARRAY_A);
    }
    
    /**
     * Find or create company by name
     * 
     * Used by terminal for walk-in registration.
     * Searches for existing company by normalized name, creates new if not found.
     * 
     * @since 3.4.0
     * @param int $branch_id Branch ID
     * @param string $company_name Company name
     * @param int|null $customer_id Customer ID (optional, for terminal - if not provided, uses SAW_Context)
     * @return int|WP_Error Company ID or WP_Error on failure
     */
    public function find_or_create_company($branch_id, $company_name, $customer_id = null) {
        global $wpdb;
        
        // Allow passing customer_id directly (for terminal)
        if (!$customer_id) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            return new WP_Error('no_customer', 'Customer context required');
        }
        
        // Normalize name for search (remove spaces, common suffixes)
        $normalized = strtolower(str_replace([' ', 's.r.o.', 'a.s.', '.', ','], '', $company_name));
        
        // Search for similar company
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_companies 
             WHERE customer_id = %d 
             AND branch_id = %d
             AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, ' ', ''), 's.r.o.', ''), 'a.s.', ''), '.', ''), ',', '')) LIKE %s
             LIMIT 1",
            $customer_id,
            $branch_id,
            '%' . $wpdb->esc_like($normalized) . '%'
        ));
        
        if ($existing) {
            SAW_Logger::debug("[SAW Visits Model] Found existing company ID: {$existing}");
            return intval($existing);
        }
        
        // Create new company
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_companies',
            [
                'customer_id' => $customer_id,
                'branch_id' => $branch_id,
                'name' => sanitize_text_field($company_name),
                'ico' => null, // Walk-in doesn't provide ICO
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        
        if (!$result) {
            SAW_Logger::error("[SAW Visits Model] Failed to create company: " . $wpdb->last_error);
            return new WP_Error('insert_failed', 'Failed to create company: ' . $wpdb->last_error);
        } else {
            $new_id = $wpdb->insert_id;
            SAW_Logger::debug("[SAW Visits Model] Created new company ID: {$new_id}");
            return $new_id;
        }  
    }
    
    /**
     * Get status label in Czech
     * 
     * @param string $status Status code
     * @return string Translated status label
     */
    private function get_status_label($status) {
        $labels = array(
            'draft' => 'Koncept',
            'pending' => 'Čekající',
            'confirmed' => 'Potvrzená',
            'in_progress' => 'Probíhající',
            'completed' => 'Dokončená',
            'cancelled' => 'Zrušená',
        );
        return $labels[$status] ?? $status;
    }
}