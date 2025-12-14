<?php
/**
 * SAW Audit Logger - Action Logging and Tracking System
 *
 * Logs all important actions to audit_log table with filtering,
 * statistics, search, and CSV export capabilities.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Audit logging class
 *
 * @since 1.0.0
 */
class SAW_Audit {

    /**
     * Log an action
     *
     * @since 1.0.0
     * @param array $data {
     *     @type string $action       Action name (required)
     *     @type int    $user_id      SAW user ID (optional)
     *     @type int    $customer_id  Customer ID (optional)
     *     @type string $ip_address   IP address (optional, auto-detect)
     *     @type string $user_agent   User agent (optional, auto-detect)
     *     @type string $details      Action details (optional, JSON or text)
     * }
     * @return int|false Log entry ID or false on failure
     */
    public static function log($data) {
        global $wpdb;

        if (empty($data['action'])) {
            return false;
        }

        if (empty($data['ip_address'])) {
            $data['ip_address'] = self::get_client_ip();
        }

        if (empty($data['user_agent'])) {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) 
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) 
                : '';
            $data['user_agent'] = substr($user_agent, 0, 255);
        }

        if (isset($data['details']) && is_array($data['details'])) {
            $data['details'] = wp_json_encode($data['details']);
        }

        $insert_data = [
            'action'      => sanitize_text_field($data['action']),
            'user_id'     => isset($data['user_id']) ? absint($data['user_id']) : null,
            'customer_id' => isset($data['customer_id']) ? absint($data['customer_id']) : null,
            'branch_id'   => isset($data['branch_id']) ? absint($data['branch_id']) : null,
            'ip_address'  => sanitize_text_field($data['ip_address']),
            'user_agent'  => sanitize_text_field($data['user_agent']),
            'details'     => $data['details'] ?? null,
            'created_at'  => current_time('mysql')
        ];

        $format = ['%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s'];

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_audit_log',
            $insert_data,
            $format
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get audit logs with filtering
     *
     * @since 1.0.0
     * @param array $args {
     *     @type int    $user_id      Filter by user ID
     *     @type int    $customer_id  Filter by customer ID
     *     @type string $action       Filter by action
     *     @type string $date_from    Date from (Y-m-d)
     *     @type string $date_to      Date to (Y-m-d)
     *     @type int    $limit        Results limit (default: 100)
     *     @type int    $offset       Offset (default: 0)
     *     @type string $order        Order (default: DESC)
     * }
     * @return array
     */
    public static function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'user_id'     => null,
            'customer_id' => null,
            'branch_id'   => null,
            'action'      => null,
            'date_from'   => null,
            'date_to'     => null,
            'limit'       => 100,
            'offset'      => 0,
            'order'       => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $where_values = [];

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $where_values[] = absint($args['user_id']);
        }

        if ($args['customer_id']) {
            $where[] = 'customer_id = %d';
            $where_values[] = absint($args['customer_id']);
        }

        if ($args['branch_id']) {
            $where[] = 'branch_id = %d';
            $where_values[] = absint($args['branch_id']);
        }

        if ($args['action']) {
            $where[] = 'action = %s';
            $where_values[] = sanitize_text_field($args['action']);
        }

        if ($args['date_from']) {
            $where[] = 'DATE(created_at) >= %s';
            $where_values[] = sanitize_text_field($args['date_from']);
        }

        if ($args['date_to']) {
            $where[] = 'DATE(created_at) <= %s';
            $where_values[] = sanitize_text_field($args['date_to']);
        }

        $where_sql = implode(' AND ', $where);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM %i WHERE {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d",
            $wpdb->prefix . 'saw_audit_log',
            absint($args['limit']),
            absint($args['offset'])
        );

        if (!empty($where_values)) {
            array_unshift($where_values, $wpdb->prefix . 'saw_audit_log');
            $where_values[] = absint($args['limit']);
            $where_values[] = absint($args['offset']);
            
            $sql = $wpdb->prepare(
                "SELECT * FROM %i WHERE {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d",
                ...$where_values
            );
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Count logs (for pagination)
     *
     * @since 1.0.0
     * @param array $args Same as get_logs()
     * @return int
     */
    public static function count_logs($args = []) {
        global $wpdb;

        $where = ['1=1'];
        $where_values = [$wpdb->prefix . 'saw_audit_log'];

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $where_values[] = absint($args['user_id']);
        }

        if (!empty($args['customer_id'])) {
            $where[] = 'customer_id = %d';
            $where_values[] = absint($args['customer_id']);
        }

        if (!empty($args['branch_id'])) {
            $where[] = 'branch_id = %d';
            $where_values[] = absint($args['branch_id']);
        }

        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $where_values[] = sanitize_text_field($args['action']);
        }

        if (!empty($args['date_from'])) {
            $where[] = 'DATE(created_at) >= %s';
            $where_values[] = sanitize_text_field($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $where[] = 'DATE(created_at) <= %s';
            $where_values[] = sanitize_text_field($args['date_to']);
        }

        $where_sql = implode(' AND ', $where);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE {$where_sql}",
            ...$where_values
        ));
    }

    /**
     * Get audit logs for user
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @param int $limit   Results limit
     * @param int $offset  Offset
     * @return array
     */
    public static function get_user_logs($user_id, $limit = 50, $offset = 0) {
        return self::get_logs([
            'user_id' => $user_id,
            'limit'   => $limit,
            'offset'  => $offset
        ]);
    }

    /**
     * Get audit logs for customer
     *
     * @since 1.0.0
     * @param int $customer_id Customer ID
     * @param int $limit       Results limit
     * @param int $offset      Offset
     * @return array
     */
    public static function get_customer_logs($customer_id, $limit = 100, $offset = 0) {
        return self::get_logs([
            'customer_id' => $customer_id,
            'limit'       => $limit,
            'offset'      => $offset
        ]);
    }

    /**
     * Get logs by action
     *
     * @since 1.0.0
     * @param string $action Action name
     * @param int    $limit  Results limit
     * @return array
     */
    public static function get_logs_by_action($action, $limit = 100) {
        return self::get_logs([
            'action' => $action,
            'limit'  => $limit
        ]);
    }

    /**
     * Cleanup old audit logs (called by cron)
     *
     * @since 1.0.0
     * @param int $days Days to keep (default: 90)
     * @return int Number of deleted records
     */
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE created_at < %s",
            $wpdb->prefix . 'saw_audit_log',
            $date_threshold
        ));

        if ($deleted > 0) {
            self::log([
                'action'  => 'audit_log_cleanup',
                'details' => sprintf(
                    /* translators: 1: number of deleted logs, 2: number of days */
                    __('Deleted %1$d old audit logs (older than %2$d days)', 'saw-visitors'),
                    $deleted,
                    $days
                )
            ]);
        }

        return $deleted;
    }

    /**
     * Export audit logs to CSV
     *
     * @since 1.0.0
     * @param array $args Filters (same as get_logs)
     * @return string CSV content
     */
    public static function export_to_csv($args = []) {
        $logs = self::get_logs(array_merge($args, ['limit' => 10000]));

        $csv = "ID,Action,User ID,Customer ID,IP Address,User Agent,Details,Created At\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s\n",
                $log['id'],
                self::escape_csv($log['action']),
                $log['user_id'] ?? '',
                $log['customer_id'] ?? '',
                self::escape_csv($log['ip_address']),
                self::escape_csv($log['user_agent']),
                self::escape_csv($log['details'] ?? ''),
                $log['created_at']
            );
        }

        return $csv;
    }

    /**
     * Get audit log statistics
     *
     * @since 1.0.0
     * @param int|null $customer_id Optional customer filter
     * @return array
     */
    public static function get_stats($customer_id = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'saw_audit_log';
        $where = $customer_id ? $wpdb->prepare('WHERE customer_id = %d', absint($customer_id)) : '';

        return [
            'total_logs' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i {$where}",
                $table
            )),
            
            'logs_by_action' => $wpdb->get_results($wpdb->prepare(
                "SELECT action, COUNT(*) as count FROM %i {$where} GROUP BY action ORDER BY count DESC LIMIT 20",
                $table
            ), ARRAY_A),
            
            'logs_last_24h' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i {$where}" . ($where ? ' AND' : ' WHERE') . " created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $table
            )),
            
            'logs_last_7d' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i {$where}" . ($where ? ' AND' : ' WHERE') . " created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $table
            )),
            
            'logs_last_30d' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i {$where}" . ($where ? ' AND' : ' WHERE') . " created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $table
            ))
        ];
    }

    /**
     * Get all unique actions (for dropdown filters)
     *
     * @since 1.0.0
     * @param int|null $customer_id Optional customer filter
     * @return array
     */
    public static function get_all_actions($customer_id = null) {
        global $wpdb;

        $where = $customer_id ? $wpdb->prepare('WHERE customer_id = %d', absint($customer_id)) : '';

        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT action FROM %i {$where} ORDER BY action ASC",
            $wpdb->prefix . 'saw_audit_log'
        ));
    }

    /**
     * Search in audit log
     *
     * @since 1.0.0
     * @param string $search_term Search term
     * @param int    $limit       Results limit
     * @return array
     */
    public static function search($search_term, $limit = 100) {
        global $wpdb;

        $search_term = '%' . $wpdb->esc_like(sanitize_text_field($search_term)) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE action LIKE %s OR details LIKE %s OR ip_address LIKE %s ORDER BY created_at DESC LIMIT %d",
            $wpdb->prefix . 'saw_audit_log',
            $search_term,
            $search_term,
            $search_term,
            absint($limit)
        ), ARRAY_A);
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Log entity change with field-level diff
     *
     * @since 1.0.0
     * @param array $data {
     *     @type string $entity_type    Entity type (required)
     *     @type int    $entity_id      Entity ID (required)
     *     @type string $action         Action: 'created' or 'updated' (required)
     *     @type array  $old_values     Old values (null for create)
     *     @type array  $new_values     New values
     *     @type array  $changed_fields Only changed fields for compact storage
     *     @type int    $customer_id    Customer ID (optional, auto-detected)
     *     @type int    $branch_id      Branch ID (optional, auto-detected)
     *     @type int    $user_id        SAW user ID (optional, auto-detected)
     * }
     * @return int|false Log entry ID or false on failure
     */
    public static function log_change($data) {
        global $wpdb;

        if (empty($data['entity_type']) || empty($data['entity_id']) || empty($data['action'])) {
            return false;
        }

        // Auto-detect customer_id if not provided
        if (!isset($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }

        // Auto-detect user_id if not provided
        if (!isset($data['user_id']) && class_exists('SAW_Context')) {
            $data['user_id'] = SAW_Context::get_saw_user_id();
        }

        // Auto-detect branch_id if not provided (fallback to context)
        if (!isset($data['branch_id']) && class_exists('SAW_Context')) {
            $data['branch_id'] = SAW_Context::get_branch_id();
        }

        // Prepare old_values and new_values as JSON
        $old_values_json = null;
        if (isset($data['old_values']) && is_array($data['old_values'])) {
            $old_values_json = wp_json_encode($data['old_values']);
        }

        $new_values_json = null;
        if (isset($data['new_values']) && is_array($data['new_values'])) {
            $new_values_json = wp_json_encode($data['new_values']);
        }

        // Prepare changed_fields as JSON (store in details for compatibility)
        $changed_fields_json = null;
        if (isset($data['changed_fields']) && is_array($data['changed_fields']) && !empty($data['changed_fields'])) {
            $changed_fields_json = wp_json_encode($data['changed_fields']);
        }

        // Prepare details JSON with changed_fields
        $details_json = null;
        if ($changed_fields_json) {
            // Store changed_fields directly in details
            $details_json = wp_json_encode(['changed_fields' => $data['changed_fields']]);
        }

        // Auto-detect IP and user agent
        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) 
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) 
            : '';
        $user_agent = substr($user_agent, 0, 500);

        // Insert directly into database with all required fields
        $insert_data = [
            'action'       => sanitize_text_field($data['action']),
            'entity_type'  => sanitize_text_field($data['entity_type']),
            'entity_id'    => absint($data['entity_id']),
            'customer_id'  => isset($data['customer_id']) ? absint($data['customer_id']) : null,
            'branch_id'    => isset($data['branch_id']) ? absint($data['branch_id']) : null,
            'user_id'      => isset($data['user_id']) ? absint($data['user_id']) : null,
            'old_values'   => $old_values_json,
            'new_values'   => $new_values_json,
            'details'      => $details_json,
            'ip_address'   => $ip_address,
            'user_agent'   => $user_agent,
            'created_at'   => current_time('mysql')
        ];

        $format = ['%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_audit_log',
            $insert_data,
            $format
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get change history for a specific entity
     *
     * @since 1.0.0
     * @param string $entity_type Entity type (e.g. 'oopp', 'visitor')
     * @param int    $entity_id   Entity ID
     * @param int    $limit       Maximum number of results (default: 50)
     * @return array Array of change log entries with decoded JSON data
     */
    public static function get_entity_history($entity_type, $entity_id, $limit = 50) {
        global $wpdb;

        $entity_type = sanitize_text_field($entity_type);
        $entity_id = absint($entity_id);
        $limit = absint($limit);

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i 
             WHERE entity_type = %s AND entity_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $wpdb->prefix . 'saw_audit_log',
            $entity_type,
            $entity_id,
            $limit
        ), ARRAY_A);

        // Decode JSON fields
        foreach ($logs as &$log) {
            // Decode old_values directly from column (not from details)
            if (!empty($log['old_values'])) {
                $decoded = json_decode($log['old_values'], true);
                if ($decoded !== null) {
                    $log['old_values'] = $decoded;
                }
            }
            
            // Decode new_values directly from column (not from details)
            if (!empty($log['new_values'])) {
                $decoded = json_decode($log['new_values'], true);
                if ($decoded !== null) {
                    $log['new_values'] = $decoded;
                }
            }
            
            // Decode changed_fields from details
            if (!empty($log['details'])) {
                $details = json_decode($log['details'], true);
                if (is_array($details) && !empty($details['changed_fields']) && is_array($details['changed_fields'])) {
                    $log['changed_fields'] = $details['changed_fields'];
                } else {
                    // Fallback: try to reconstruct from old_values and new_values if changed_fields not available
                    if (!empty($log['old_values']) && is_array($log['old_values']) && 
                        !empty($log['new_values']) && is_array($log['new_values'])) {
                        $reconstructed = array();
                        foreach ($log['new_values'] as $key => $new_val) {
                            $old_val = $log['old_values'][$key] ?? null;
                            if ($old_val !== $new_val) {
                                $reconstructed[$key] = array(
                                    'old' => $old_val,
                                    'new' => $new_val
                                );
                            }
                        }
                        if (!empty($reconstructed)) {
                            $log['changed_fields'] = $reconstructed;
                        }
                    }
                }
            } else {
                // Fallback: try to reconstruct from old_values and new_values if details not available
                if (!empty($log['old_values']) && is_array($log['old_values']) && 
                    !empty($log['new_values']) && is_array($log['new_values'])) {
                    $reconstructed = array();
                    foreach ($log['new_values'] as $key => $new_val) {
                        $old_val = $log['old_values'][$key] ?? null;
                        if ($old_val !== $new_val) {
                            $reconstructed[$key] = array(
                                'old' => $old_val,
                                'new' => $new_val
                            );
                        }
                    }
                    if (!empty($reconstructed)) {
                        $log['changed_fields'] = $reconstructed;
                    }
                }
            }
            
            // Get user email if user_id exists
            if (!empty($log['user_id'])) {
                $wp_user_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT wp_user_id FROM %i WHERE id = %d",
                    $wpdb->prefix . 'saw_users',
                    $log['user_id']
                ));
                if ($wp_user_id) {
                    $wp_user = get_user_by('ID', $wp_user_id);
                    if ($wp_user) {
                        $log['user_email'] = $wp_user->user_email;
                    }
                }
            }
        }

        return $logs ?: [];
    }

    /**
     * Escape value for CSV
     *
     * @since 1.0.0
     * @param string $value Value to escape
     * @return string
     */
    private static function escape_csv($value) {
        $value = str_replace('"', '""', $value);
        
        if (strpos($value, ',') !== false || strpos($value, "\n") !== false || strpos($value, '"') !== false) {
            $value = '"' . $value . '"';
        }
        
        return $value;
    }
}