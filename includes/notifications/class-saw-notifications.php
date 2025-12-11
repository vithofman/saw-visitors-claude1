<?php
/**
 * SAW Notifications Model
 *
 * Handles all notification operations including:
 * - Creating notifications
 * - Fetching notifications for users
 * - Marking as read
 * - Batch operations
 * - Cleanup of expired notifications
 *
 * @package    SAW_Visitors
 * @subpackage Notifications
 * @version    1.0.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Notifications Class
 *
 * Model class for notification management.
 *
 * @since 1.0.0
 */
class SAW_Notifications {
    
    /**
     * Table name (without prefix)
     *
     * @var string
     */
    const TABLE = 'saw_notifications';
    
    /**
     * Cache group
     *
     * @var string
     */
    const CACHE_GROUP = 'saw_notifications';
    
    /**
     * Cache TTL in seconds (5 minutes)
     *
     * @var int
     */
    const CACHE_TTL = 300;
    
    /**
     * Notification types with their properties
     *
     * @var array
     */
    const TYPES = [
        'visit_assigned' => [
            'icon' => '👤',
            'color' => 'info',
            'priority' => 'medium',
            'action_type' => 'visit_detail',
        ],
        'visit_today' => [
            'icon' => '📅',
            'color' => 'warning',
            'priority' => 'medium',
            'action_type' => 'visit_detail',
        ],
        'visit_tomorrow' => [
            'icon' => '🗓️',
            'color' => 'info',
            'priority' => 'low',
            'action_type' => 'visit_detail',
        ],
        'visitor_checkin' => [
            'icon' => '✅',
            'color' => 'success',
            'priority' => 'high',
            'action_type' => 'visit_detail',
        ],
        'visitor_checkout' => [
            'icon' => '👋',
            'color' => 'default',
            'priority' => 'low',
            'action_type' => 'visit_detail',
        ],
        'visit_rescheduled' => [
            'icon' => '🔄',
            'color' => 'warning',
            'priority' => 'medium',
            'action_type' => 'visit_detail',
        ],
        'visit_cancelled' => [
            'icon' => '❌',
            'color' => 'danger',
            'priority' => 'medium',
            'action_type' => 'visit_detail',
        ],
        'visit_confirmed' => [
            'icon' => '✨',
            'color' => 'success',
            'priority' => 'low',
            'action_type' => 'visit_detail',
        ],
        'training_completed' => [
            'icon' => '🎓',
            'color' => 'success',
            'priority' => 'low',
            'action_type' => 'visitor_detail',
        ],
        'system' => [
            'icon' => '🔔',
            'color' => 'info',
            'priority' => 'low',
            'action_type' => null,
        ],
    ];
    
    /**
     * Get table name with prefix
     *
     * @return string Full table name
     */
    public static function get_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
    
    /**
     * Create a new notification
     *
     * @param array $data Notification data
     * @return int|false Notification ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        
        // Validate required fields
        $required = ['user_id', 'customer_id', 'type', 'title', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        // Get type config
        $type_config = self::TYPES[$data['type']] ?? self::TYPES['system'];
        
        // Prepare insert data
        $insert_data = [
            'user_id' => intval($data['user_id']),
            'customer_id' => intval($data['customer_id']),
            'branch_id' => isset($data['branch_id']) ? intval($data['branch_id']) : null,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? $type_config['priority'],
            'title' => sanitize_text_field($data['title']),
            'message' => wp_kses_post($data['message']),
            'visit_id' => isset($data['visit_id']) ? intval($data['visit_id']) : null,
            'visitor_id' => isset($data['visitor_id']) ? intval($data['visitor_id']) : null,
            'action_url' => isset($data['action_url']) ? esc_url_raw($data['action_url']) : null,
            'action_type' => $data['action_type'] ?? $type_config['action_type'],
            'meta' => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
            'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
            'created_at' => current_time('mysql'),
        ];
        
        // Insert notification
        $result = $wpdb->insert(
            self::get_table(),
            $insert_data,
            [
                '%d', // user_id
                '%d', // customer_id
                '%d', // branch_id
                '%s', // type
                '%s', // priority
                '%s', // title
                '%s', // message
                '%d', // visit_id
                '%d', // visitor_id
                '%s', // action_url
                '%s', // action_type
                '%s', // meta
                '%s', // expires_at
                '%s', // created_at
            ]
        );
        
        if ($result) {
            $notification_id = $wpdb->insert_id;
            
            // Clear cache for this user
            self::clear_user_cache($data['user_id']);
            
            return $notification_id;
        }
        
        return false;
    }
    
    /**
     * Create notifications for multiple users
     *
     * @param array $user_ids Array of user IDs
     * @param array $data     Notification data (without user_id)
     * @return int Number of notifications created
     */
    public static function create_for_users($user_ids, $data) {
        $created = 0;
        
        foreach ($user_ids as $user_id) {
            $notification_data = array_merge($data, ['user_id' => $user_id]);
            if (self::create($notification_data)) {
                $created++;
            }
        }
        
        return $created;
    }
    
    /**
     * Get notifications for a user
     *
     * @param int   $user_id    User ID
     * @param array $args       Optional arguments
     * @return array Notifications
     */
    public static function get_for_user($user_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'unread_only' => false,
            'type' => null,
            'customer_id' => null,
            'branch_id' => null,
            'include_expired' => false,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $where = ['n.user_id = %d'];
        $params = [$user_id];
        
        // Unread only
        if ($args['unread_only']) {
            $where[] = 'n.is_read = 0';
        }
        
        // Filter by type
        if ($args['type']) {
            $where[] = 'n.type = %s';
            $params[] = $args['type'];
        }
        
        // Filter by customer
        if ($args['customer_id']) {
            $where[] = 'n.customer_id = %d';
            $params[] = $args['customer_id'];
        }
        
        // Filter by branch
        if ($args['branch_id']) {
            $where[] = '(n.branch_id = %d OR n.branch_id IS NULL)';
            $params[] = $args['branch_id'];
        }
        
        // Exclude expired
        if (!$args['include_expired']) {
            $where[] = '(n.expires_at IS NULL OR n.expires_at > NOW())';
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Add limit and offset to params
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $query = $wpdb->prepare(
            "SELECT 
                n.*,
                v.planned_date_from,
                v.planned_date_to,
                v.status as visit_status,
                c.name as company_name,
                CONCAT(vis.first_name, ' ', vis.last_name) as visitor_name
             FROM %i n
             LEFT JOIN {$wpdb->prefix}saw_visits v ON n.visit_id = v.id
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             LEFT JOIN {$wpdb->prefix}saw_visitors vis ON n.visitor_id = vis.id
             WHERE {$where_sql}
             ORDER BY 
                n.is_read ASC,
                FIELD(n.priority, 'high', 'medium', 'low'),
                n.created_at DESC
             LIMIT %d OFFSET %d",
            self::get_table(),
            ...$params
        );
        
        $notifications = $wpdb->get_results($query, ARRAY_A);
        
        // Enrich with type config
        foreach ($notifications as &$notification) {
            $type_config = self::TYPES[$notification['type']] ?? self::TYPES['system'];
            $notification['icon'] = $type_config['icon'];
            $notification['color'] = $type_config['color'];
            $notification['meta'] = $notification['meta'] ? json_decode($notification['meta'], true) : [];
            $notification['time_ago'] = self::time_ago($notification['created_at']);
        }
        
        return $notifications;
    }
    
    /**
     * Get unread count for a user
     *
     * @param int      $user_id     User ID
     * @param int|null $customer_id Optional customer filter
     * @param int|null $branch_id   Optional branch filter
     * @return int Unread count
     */
    public static function get_unread_count($user_id, $customer_id = null, $branch_id = null) {
        global $wpdb;
        
        // Try cache first
        $cache_key = "unread_{$user_id}_{$customer_id}_{$branch_id}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return (int) $cached;
        }
        
        $where = ['user_id = %d', 'is_read = 0', '(expires_at IS NULL OR expires_at > NOW())'];
        $params = [$user_id];
        
        if ($customer_id) {
            $where[] = 'customer_id = %d';
            $params[] = $customer_id;
        }
        
        if ($branch_id) {
            $where[] = '(branch_id = %d OR branch_id IS NULL)';
            $params[] = $branch_id;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE {$where_sql}",
            self::get_table(),
            ...$params
        ));
        
        // Cache for 5 minutes
        wp_cache_set($cache_key, $count, self::CACHE_GROUP, self::CACHE_TTL);
        
        return (int) $count;
    }
    
    /**
     * Mark notification as read
     *
     * @param int $notification_id Notification ID
     * @param int $user_id         User ID (for verification)
     * @return bool Success
     */
    public static function mark_as_read($notification_id, $user_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            self::get_table(),
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            [
                'id' => $notification_id,
                'user_id' => $user_id,
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );
        
        if ($result !== false) {
            self::clear_user_cache($user_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Mark all notifications as read for a user
     *
     * @param int      $user_id     User ID
     * @param int|null $customer_id Optional customer filter
     * @return int Number of notifications marked as read
     */
    public static function mark_all_as_read($user_id, $customer_id = null) {
        global $wpdb;
        
        $where = ['user_id = %d', 'is_read = 0'];
        $params = [$user_id];
        
        if ($customer_id) {
            $where[] = 'customer_id = %d';
            $params[] = $customer_id;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE %i SET is_read = 1, read_at = NOW() WHERE {$where_sql}",
            self::get_table(),
            ...$params
        ));
        
        self::clear_user_cache($user_id);
        
        return $result;
    }
    
    /**
     * Delete a notification
     *
     * @param int $notification_id Notification ID
     * @param int $user_id         User ID (for verification)
     * @return bool Success
     */
    public static function delete($notification_id, $user_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            self::get_table(),
            [
                'id' => $notification_id,
                'user_id' => $user_id,
            ],
            ['%d', '%d']
        );
        
        if ($result) {
            self::clear_user_cache($user_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete all notifications for a user
     *
     * @param int      $user_id     User ID
     * @param int|null $customer_id Optional customer filter
     * @return int Number of notifications deleted
     */
    public static function delete_all($user_id, $customer_id = null) {
        global $wpdb;
        
        $where = ['user_id' => $user_id];
        $format = ['%d'];
        
        if ($customer_id) {
            $where['customer_id'] = $customer_id;
            $format[] = '%d';
        }
        
        $result = $wpdb->delete(self::get_table(), $where, $format);
        
        self::clear_user_cache($user_id);
        
        return $result;
    }
    
    /**
     * Cleanup expired notifications
     *
     * Should be called via WP-Cron daily.
     *
     * @return int Number of notifications deleted
     */
    public static function cleanup_expired() {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE expires_at IS NOT NULL AND expires_at < NOW()",
            self::get_table()
        ));
    }
    
    /**
     * Cleanup old read notifications
     *
     * Deletes read notifications older than specified days.
     *
     * @param int $days Number of days to keep
     * @return int Number of notifications deleted
     */
    public static function cleanup_old_read($days = 30) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            self::get_table(),
            $days
        ));
    }
    
    /**
     * Check if notification exists (prevent duplicates)
     *
     * @param int    $user_id  User ID
     * @param string $type     Notification type
     * @param int    $visit_id Visit ID
     * @param int    $hours    Within last X hours
     * @return bool Exists
     */
    public static function exists($user_id, $type, $visit_id, $hours = 24) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i 
             WHERE user_id = %d 
             AND type = %s 
             AND visit_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            self::get_table(),
            $user_id,
            $type,
            $visit_id,
            $hours
        ));
        
        return $count > 0;
    }
    
    /**
     * Clear cache for a user
     *
     * @param int $user_id User ID
     */
    public static function clear_user_cache($user_id) {
        // Clear all possible cache keys for this user
        wp_cache_delete("unread_{$user_id}_", self::CACHE_GROUP);
        
        // Clear with wildcard pattern isn't supported, so we clear the group
        wp_cache_flush_group(self::CACHE_GROUP);
    }
    
    /**
     * Format time ago string
     *
     * @param string $datetime MySQL datetime
     * @return string Human readable time ago
     */
    public static function time_ago($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return __('Právě teď', 'saw-visitors');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return sprintf(_n('Před %d minutou', 'Před %d minutami', $mins, 'saw-visitors'), $mins);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(_n('Před %d hodinou', 'Před %d hodinami', $hours, 'saw-visitors'), $hours);
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return sprintf(_n('Před %d dnem', 'Před %d dny', $days, 'saw-visitors'), $days);
        } else {
            return date_i18n(get_option('date_format'), $timestamp);
        }
    }
    
    /**
     * Build action URL for notification
     *
     * @param string   $action_type Action type
     * @param int|null $visit_id    Visit ID
     * @param int|null $visitor_id  Visitor ID
     * @return string URL
     */
    public static function build_action_url($action_type, $visit_id = null, $visitor_id = null) {
        $base_url = home_url('/admin');
        
        switch ($action_type) {
            case 'visit_detail':
                return $base_url . '/visits/?detail=' . $visit_id;
            
            case 'visitor_detail':
                // Visitors are shown within visit context
                return $base_url . '/visits/?detail=' . $visit_id . '&visitor=' . $visitor_id;
            
            case 'calendar':
                return $base_url . '/calendar/';
            
            case 'dashboard':
                return $base_url . '/dashboard/';
            
            default:
                return $base_url . '/dashboard/';
        }
    }
}
