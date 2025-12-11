<?php
/**
 * SAW Notification Triggers
 *
 * Automatically creates notifications when specific events occur.
 * Hooks into various plugin actions to generate appropriate notifications.
 *
 * Trigger Events:
 * - Visit assigned to host
 * - Visitor check-in
 * - Visitor check-out
 * - Visit rescheduled
 * - Visit cancelled
 * - Visit confirmed (invitation completed)
 * - Training completed
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
 * SAW Notification Triggers Class
 *
 * Handles automatic notification creation based on events.
 *
 * @since 1.0.0
 */
class SAW_Notification_Triggers {
    
    /**
     * Singleton instance
     *
     * @var SAW_Notification_Triggers|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return SAW_Notification_Triggers
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     *
     * Registers all notification hooks.
     */
    private function __construct() {
        // Visit host assignment
        add_action('saw_visit_host_assigned', [$this, 'on_host_assigned'], 10, 3);
        
        // Visitor check-in
        add_action('saw_visitor_checked_in', [$this, 'on_visitor_checkin'], 10, 3);
        
        // Visitor check-out
        add_action('saw_visitor_checked_out', [$this, 'on_visitor_checkout'], 10, 3);
        
        // Visit rescheduled
        add_action('saw_visit_rescheduled', [$this, 'on_visit_rescheduled'], 10, 3);
        
        // Visit cancelled
        add_action('saw_visit_cancelled', [$this, 'on_visit_cancelled'], 10, 2);
        
        // Visit confirmed (invitation completed)
        add_action('saw_visit_confirmed', [$this, 'on_visit_confirmed'], 10, 2);
        
        // Training completed
        add_action('saw_training_completed', [$this, 'on_training_completed'], 10, 2);
        
        // Schedule daily reminder cron
        add_action('saw_daily_visit_reminders', [$this, 'send_daily_reminders']);
        
        // Schedule cleanup cron
        add_action('saw_notifications_cleanup', [$this, 'cleanup_notifications']);
    }
    
    /**
     * Get visit hosts (users assigned to a visit)
     *
     * @param int $visit_id Visit ID
     * @return array Array of user data
     */
    private function get_visit_hosts($visit_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.customer_id, u.first_name, u.last_name, u.email
             FROM {$wpdb->prefix}saw_visit_hosts vh
             INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
             WHERE vh.visit_id = %d AND u.is_active = 1",
            $visit_id
        ), ARRAY_A);
    }
    
    /**
     * Get visit data with company and visitor info
     *
     * @param int $visit_id Visit ID
     * @return array|null Visit data
     */
    private function get_visit_data($visit_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                v.*,
                c.name as company_name,
                (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id) as visitor_count,
                (SELECT CONCAT(first_name, ' ', last_name) 
                 FROM {$wpdb->prefix}saw_visitors 
                 WHERE visit_id = v.id 
                 ORDER BY id ASC LIMIT 1) as first_visitor_name
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             WHERE v.id = %d",
            $visit_id
        ), ARRAY_A);
    }
    
    /**
     * Get visitor data
     *
     * @param int $visitor_id Visitor ID
     * @return array|null Visitor data
     */
    private function get_visitor_data($visitor_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT vis.*, v.customer_id, v.branch_id, c.name as company_name
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             WHERE vis.id = %d",
            $visitor_id
        ), ARRAY_A);
    }
    
    /**
     * Get visit creator
     *
     * @param int $visit_id Visit ID
     * @return array|null User data
     */
    private function get_visit_creator($visit_id) {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT created_by, customer_id, branch_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit || !$visit['created_by']) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, first_name, last_name, email
             FROM {$wpdb->prefix}saw_users 
             WHERE id = %d AND is_active = 1",
            $visit['created_by']
        ), ARRAY_A);
    }
    
    /**
     * Format visitor name for display
     *
     * @param string|null $company_name Company name
     * @param string      $visitor_name Visitor name
     * @param int         $visitor_count Total visitor count
     * @return string Formatted name
     */
    private function format_visitor_display($company_name, $visitor_name, $visitor_count = 1) {
        if ($company_name) {
            if ($visitor_count > 1) {
                return sprintf('%s (%d návštěvníků)', $company_name, $visitor_count);
            }
            return sprintf('%s - %s', $company_name, $visitor_name);
        }
        
        if ($visitor_count > 1) {
            return sprintf('%s a %d dalších', $visitor_name, $visitor_count - 1);
        }
        
        return $visitor_name;
    }
    
    /**
     * Format date for display
     *
     * @param string $date MySQL date
     * @return string Formatted date
     */
    private function format_date($date) {
        $timestamp = strtotime($date);
        $today = strtotime('today');
        $tomorrow = strtotime('tomorrow');
        
        if ($timestamp === $today) {
            return __('dnes', 'saw-visitors');
        } elseif ($timestamp === $tomorrow) {
            return __('zítra', 'saw-visitors');
        }
        
        return date_i18n('j. n. Y', $timestamp);
    }
    
    // ================================================================
    // EVENT HANDLERS
    // ================================================================
    
    /**
     * Handle host assignment
     *
     * Called when a user is assigned as host to a visit.
     *
     * @param int   $visit_id Visit ID
     * @param int   $user_id  Assigned user ID
     * @param array $visit    Visit data
     */
    public function on_host_assigned($visit_id, $user_id, $visit = []) {
        // Load notifications class
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        // Get visit data if not provided
        if (empty($visit)) {
            $visit = $this->get_visit_data($visit_id);
        }
        
        if (!$visit) {
            return;
        }
        
        // Check for duplicate
        if (SAW_Notifications::exists($user_id, 'visit_assigned', $visit_id, 24)) {
            return;
        }
        
        $visitor_display = $this->format_visitor_display(
            $visit['company_name'] ?? null,
            $visit['first_visitor_name'] ?? '',
            $visit['visitor_count'] ?? 1
        );
        
        $date_display = $this->format_date($visit['planned_date_from']);
        
        SAW_Notifications::create([
            'user_id' => $user_id,
            'customer_id' => $visit['customer_id'],
            'branch_id' => $visit['branch_id'],
            'type' => 'visit_assigned',
            'title' => __('Nová návštěva přiřazena', 'saw-visitors'),
            'message' => sprintf(
                __('Byli jste přiřazeni jako hostitel návštěvy: %s (%s)', 'saw-visitors'),
                $visitor_display,
                $date_display
            ),
            'visit_id' => $visit_id,
            'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit_id),
            'meta' => [
                'company_name' => $visit['company_name'] ?? null,
                'visitor_name' => $visit['first_visitor_name'] ?? null,
                'planned_date' => $visit['planned_date_from'],
            ],
        ]);
    }
    
    /**
     * Handle visitor check-in
     *
     * Called when a visitor checks in.
     *
     * @param int   $visitor_id Visitor ID
     * @param int   $visit_id   Visit ID
     * @param array $visitor    Visitor data
     */
    public function on_visitor_checkin($visitor_id, $visit_id, $visitor = []) {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        // Get visitor data if not provided
        if (empty($visitor)) {
            $visitor = $this->get_visitor_data($visitor_id);
        }
        
        if (!$visitor) {
            return;
        }
        
        // Get visit hosts
        $hosts = $this->get_visit_hosts($visit_id);
        
        if (empty($hosts)) {
            return;
        }
        
        $visitor_name = trim($visitor['first_name'] . ' ' . $visitor['last_name']);
        $company_display = $visitor['company_name'] 
            ? sprintf('%s (%s)', $visitor_name, $visitor['company_name'])
            : $visitor_name;
        
        foreach ($hosts as $host) {
            // Don't notify duplicates within 1 hour
            if (SAW_Notifications::exists($host['id'], 'visitor_checkin', $visit_id, 1)) {
                continue;
            }
            
            SAW_Notifications::create([
                'user_id' => $host['id'],
                'customer_id' => $visitor['customer_id'],
                'branch_id' => $visitor['branch_id'],
                'type' => 'visitor_checkin',
                'priority' => 'high',
                'title' => __('Návštěvník přišel', 'saw-visitors'),
                'message' => sprintf(
                    __('%s právě přišel/a a čeká na vás', 'saw-visitors'),
                    $company_display
                ),
                'visit_id' => $visit_id,
                'visitor_id' => $visitor_id,
                'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit_id),
                'meta' => [
                    'visitor_name' => $visitor_name,
                    'company_name' => $visitor['company_name'] ?? null,
                ],
                // High priority notifications expire after 2 hours
                'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            ]);
        }
    }
    
    /**
     * Handle visitor check-out
     *
     * Called when a visitor checks out.
     *
     * @param int   $visitor_id Visitor ID
     * @param int   $visit_id   Visit ID
     * @param array $visitor    Visitor data
     */
    public function on_visitor_checkout($visitor_id, $visit_id, $visitor = []) {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        if (empty($visitor)) {
            $visitor = $this->get_visitor_data($visitor_id);
        }
        
        if (!$visitor) {
            return;
        }
        
        $hosts = $this->get_visit_hosts($visit_id);
        
        if (empty($hosts)) {
            return;
        }
        
        $visitor_name = trim($visitor['first_name'] . ' ' . $visitor['last_name']);
        
        foreach ($hosts as $host) {
            SAW_Notifications::create([
                'user_id' => $host['id'],
                'customer_id' => $visitor['customer_id'],
                'branch_id' => $visitor['branch_id'],
                'type' => 'visitor_checkout',
                'priority' => 'low',
                'title' => __('Návštěvník odešel', 'saw-visitors'),
                'message' => sprintf(
                    __('%s se odhlásil/a', 'saw-visitors'),
                    $visitor_name
                ),
                'visit_id' => $visit_id,
                'visitor_id' => $visitor_id,
                'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit_id),
                'meta' => [
                    'visitor_name' => $visitor_name,
                    'company_name' => $visitor['company_name'] ?? null,
                ],
            ]);
        }
    }
    
    /**
     * Handle visit rescheduled
     *
     * Called when visit dates are changed.
     *
     * @param int    $visit_id Visit ID
     * @param string $old_date Old planned date
     * @param string $new_date New planned date
     */
    public function on_visit_rescheduled($visit_id, $old_date, $new_date) {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        $visit = $this->get_visit_data($visit_id);
        
        if (!$visit) {
            return;
        }
        
        $hosts = $this->get_visit_hosts($visit_id);
        
        $visitor_display = $this->format_visitor_display(
            $visit['company_name'] ?? null,
            $visit['first_visitor_name'] ?? '',
            $visit['visitor_count'] ?? 1
        );
        
        $old_display = $this->format_date($old_date);
        $new_display = $this->format_date($new_date);
        
        foreach ($hosts as $host) {
            SAW_Notifications::create([
                'user_id' => $host['id'],
                'customer_id' => $visit['customer_id'],
                'branch_id' => $visit['branch_id'],
                'type' => 'visit_rescheduled',
                'title' => __('Návštěva přesunuta', 'saw-visitors'),
                'message' => sprintf(
                    __('Návštěva %s byla přesunuta z %s na %s', 'saw-visitors'),
                    $visitor_display,
                    $old_display,
                    $new_display
                ),
                'visit_id' => $visit_id,
                'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit_id),
                'meta' => [
                    'old_date' => $old_date,
                    'new_date' => $new_date,
                    'company_name' => $visit['company_name'] ?? null,
                ],
            ]);
        }
    }
    
    /**
     * Handle visit cancelled
     *
     * Called when a visit is cancelled.
     *
     * @param int   $visit_id Visit ID
     * @param array $visit    Visit data
     */
    public function on_visit_cancelled($visit_id, $visit = []) {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        if (empty($visit)) {
            $visit = $this->get_visit_data($visit_id);
        }
        
        if (!$visit) {
            return;
        }
        
        $hosts = $this->get_visit_hosts($visit_id);
        
        $visitor_display = $this->format_visitor_display(
            $visit['company_name'] ?? null,
            $visit['first_visitor_name'] ?? '',
            $visit['visitor_count'] ?? 1
        );
        
        $date_display = $this->format_date($visit['planned_date_from']);
        
        foreach ($hosts as $host) {
            SAW_Notifications::create([
                'user_id' => $host['id'],
                'customer_id' => $visit['customer_id'],
                'branch_id' => $visit['branch_id'],
                'type' => 'visit_cancelled',
                'title' => __('Návštěva zrušena', 'saw-visitors'),
                'message' => sprintf(
                    __('Návštěva %s (%s) byla zrušena', 'saw-visitors'),
                    $visitor_display,
                    $date_display
                ),
                'visit_id' => $visit_id,
                'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit_id),
                'meta' => [
                    'company_name' => $visit['company_name'] ?? null,
                    'planned_date' => $visit['planned_date_from'],
                ],
            ]);
        }
    }
    
    /**
     * Handle visit confirmed
     *
     * Called when invitation flow is completed.
     *
     * @param int   $visit_id Visit ID
     * @param array $visit    Visit data
     */
    public function on_visit_confirmed($visit_id, $visit = []) {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        if (empty($visit)) {
            $visit = $this->get_visit_data($visit_id);
        }
        
        if (!$visit) {
            return;
        }
        
        // Notify the creator
        $creator = $this->get_visit_creator($visit_id);
        
        if (!$creator) {
            return;
        }
        
        $visitor_display = $this->format_visitor_display(
            $visit['company_name'] ?? null,
            $visit['first_visitor_name'] ?? '',
            $visit['visitor_count'] ?? 1
        );
        
        SAW_Notifications::create([
            'user_id' => $creator['id'],
            'customer_id' => $visit['customer_id'],
            'branch_id' => $visit['branch_id'],
            'type' => 'visit_confirmed',
            'priority' => 'low',
            'title' => __('Návštěva potvrzena', 'saw-visitors'),
            'message' => sprintf(
                __('%s dokončil/a registraci a je připraven/a k návštěvě', 'saw-visitors'),
                $visitor_display
            ),
            'visit_id' => $visit_id,
            'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit_id),
            'meta' => [
                'company_name' => $visit['company_name'] ?? null,
                'visitor_count' => $visit['visitor_count'] ?? 1,
            ],
        ]);
    }
    
    /**
     * Handle training completed
     *
     * Called when a visitor completes training.
     *
     * @param int   $visitor_id Visitor ID
     * @param array $visitor    Visitor data
     */
    public function on_training_completed($visitor_id, $visitor = []) {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        if (empty($visitor)) {
            $visitor = $this->get_visitor_data($visitor_id);
        }
        
        if (!$visitor) {
            return;
        }
        
        $hosts = $this->get_visit_hosts($visitor['visit_id']);
        
        $visitor_name = trim($visitor['first_name'] . ' ' . $visitor['last_name']);
        
        foreach ($hosts as $host) {
            SAW_Notifications::create([
                'user_id' => $host['id'],
                'customer_id' => $visitor['customer_id'],
                'branch_id' => $visitor['branch_id'],
                'type' => 'training_completed',
                'priority' => 'low',
                'title' => __('Školení dokončeno', 'saw-visitors'),
                'message' => sprintf(
                    __('%s dokončil/a vstupní školení', 'saw-visitors'),
                    $visitor_name
                ),
                'visit_id' => $visitor['visit_id'],
                'visitor_id' => $visitor_id,
                'action_url' => SAW_Notifications::build_action_url('visit_detail', $visitor['visit_id']),
                'meta' => [
                    'visitor_name' => $visitor_name,
                ],
            ]);
        }
    }
    
    // ================================================================
    // SCHEDULED TASKS
    // ================================================================
    
    /**
     * Send daily visit reminders
     *
     * Called via WP-Cron every morning.
     * Sends notifications about today's and tomorrow's visits.
     */
    public function send_daily_reminders() {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        global $wpdb;
        
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // Get visits for today and tomorrow
        $visits = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                v.id, v.customer_id, v.branch_id, v.planned_date_from,
                c.name as company_name,
                (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id) as visitor_count,
                (SELECT CONCAT(first_name, ' ', last_name) 
                 FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id LIMIT 1) as first_visitor_name
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             WHERE v.status IN ('pending', 'confirmed')
             AND v.planned_date_from IN (%s, %s)
             ORDER BY v.planned_date_from ASC",
            $today,
            $tomorrow
        ), ARRAY_A);
        
        foreach ($visits as $visit) {
            $hosts = $this->get_visit_hosts($visit['id']);
            
            if (empty($hosts)) {
                continue;
            }
            
            $is_today = $visit['planned_date_from'] === $today;
            $type = $is_today ? 'visit_today' : 'visit_tomorrow';
            
            $visitor_display = $this->format_visitor_display(
                $visit['company_name'] ?? null,
                $visit['first_visitor_name'] ?? '',
                $visit['visitor_count'] ?? 1
            );
            
            foreach ($hosts as $host) {
                // Prevent duplicates
                if (SAW_Notifications::exists($host['id'], $type, $visit['id'], 20)) {
                    continue;
                }
                
                $title = $is_today 
                    ? __('Návštěva dnes', 'saw-visitors')
                    : __('Návštěva zítra', 'saw-visitors');
                
                $message = $is_today
                    ? sprintf(__('Dnes máte naplánovanou návštěvu: %s', 'saw-visitors'), $visitor_display)
                    : sprintf(__('Zítra máte naplánovanou návštěvu: %s', 'saw-visitors'), $visitor_display);
                
                SAW_Notifications::create([
                    'user_id' => $host['id'],
                    'customer_id' => $visit['customer_id'],
                    'branch_id' => $visit['branch_id'],
                    'type' => $type,
                    'priority' => $is_today ? 'medium' : 'low',
                    'title' => $title,
                    'message' => $message,
                    'visit_id' => $visit['id'],
                    'action_url' => SAW_Notifications::build_action_url('visit_detail', $visit['id']),
                    'meta' => [
                        'company_name' => $visit['company_name'] ?? null,
                        'visitor_count' => $visit['visitor_count'] ?? 1,
                    ],
                    // Expire at end of day
                    'expires_at' => $is_today 
                        ? date('Y-m-d 23:59:59') 
                        : date('Y-m-d 23:59:59', strtotime('+1 day')),
                ]);
            }
        }
    }
    
    /**
     * Cleanup old notifications
     *
     * Called via WP-Cron daily.
     */
    public function cleanup_notifications() {
        if (!class_exists('SAW_Notifications')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        }
        
        // Delete expired notifications
        SAW_Notifications::cleanup_expired();
        
        // Delete read notifications older than 30 days
        SAW_Notifications::cleanup_old_read(30);
    }
    
    // ================================================================
    // HELPER METHODS FOR MANUAL TRIGGERING
    // ================================================================
    
    /**
     * Manually trigger host assignment notification
     *
     * Can be called from visit save handler.
     *
     * @param int   $visit_id   Visit ID
     * @param array $new_hosts  New host user IDs
     * @param array $old_hosts  Previous host user IDs
     */
    public static function notify_new_hosts($visit_id, $new_hosts, $old_hosts = []) {
        $added_hosts = array_diff($new_hosts, $old_hosts);
        
        if (empty($added_hosts)) {
            return;
        }
        
        $instance = self::instance();
        
        foreach ($added_hosts as $user_id) {
            $instance->on_host_assigned($visit_id, $user_id);
        }
    }
}

// Initialize triggers
add_action('plugins_loaded', function() {
    SAW_Notification_Triggers::instance();
});
