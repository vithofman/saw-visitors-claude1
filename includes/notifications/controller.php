<?php
/**
 * SAW Notifications Controller
 *
 * Handles AJAX requests for notifications:
 * - Fetching notifications
 * - Marking as read
 * - Deleting notifications
 * - Getting unread count
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
 * SAW Notifications Controller Class
 *
 * @since 1.0.0
 */
class SAW_Notifications_Controller {
    
    /**
     * Constructor
     *
     * Registers AJAX handlers.
     */
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_saw_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_saw_get_unread_count', [$this, 'ajax_get_unread_count']);
        add_action('wp_ajax_saw_mark_notification_read', [$this, 'ajax_mark_as_read']);
        add_action('wp_ajax_saw_mark_all_notifications_read', [$this, 'ajax_mark_all_as_read']);
        add_action('wp_ajax_saw_delete_notification', [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_delete_all_notifications', [$this, 'ajax_delete_all']);
    }
    
    /**
     * Get current SAW user ID
     *
     * @return int|null SAW user ID or null
     */
    private function get_current_saw_user_id() {
        $wp_user_id = get_current_user_id();
        
        if (!$wp_user_id) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
            $wp_user_id
        ));
    }
    
    /**
     * Get current context (customer_id, branch_id)
     *
     * @return array Context data
     */
    private function get_current_context() {
        $context = [
            'customer_id' => null,
            'branch_id' => null,
        ];
        
        if (class_exists('SAW_Context')) {
            $context['customer_id'] = SAW_Context::get_customer_id();
            $context['branch_id'] = SAW_Context::get_branch_id();
        }
        
        return $context;
    }
    
    /**
     * Load notifications class
     *
     * @return bool Success
     */
    private function load_notifications_class() {
        if (!class_exists('SAW_Notifications')) {
            $file = SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
            return false;
        }
        return true;
    }
    
    /**
     * AJAX: Get notifications
     *
     * Returns paginated notifications for current user.
     */
    public function ajax_get_notifications() {
        // Verify nonce
        if (!check_ajax_referer('saw_notifications_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')]);
        }
        
        // Get user
        $user_id = $this->get_current_saw_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Nepřihlášený uživatel', 'saw-visitors')]);
        }
        
        // Load class
        if (!$this->load_notifications_class()) {
            wp_send_json_error(['message' => __('Chyba systému', 'saw-visitors')]);
        }
        
        // Get context
        $context = $this->get_current_context();
        
        // Parse arguments
        $args = [
            'limit' => isset($_POST['limit']) ? intval($_POST['limit']) : 20,
            'offset' => isset($_POST['offset']) ? intval($_POST['offset']) : 0,
            'unread_only' => isset($_POST['unread_only']) && $_POST['unread_only'] === 'true',
            'customer_id' => $context['customer_id'],
            'branch_id' => $context['branch_id'],
        ];
        
        // Get notifications
        $notifications = SAW_Notifications::get_for_user($user_id, $args);
        
        // Get total unread count
        $unread_count = SAW_Notifications::get_unread_count(
            $user_id, 
            $context['customer_id'], 
            $context['branch_id']
        );
        
        wp_send_json_success([
            'notifications' => $notifications,
            'unread_count' => $unread_count,
            'has_more' => count($notifications) === $args['limit'],
        ]);
    }
    
    /**
     * AJAX: Get unread count
     *
     * Returns only the unread notification count.
     */
    public function ajax_get_unread_count() {
        // Verify nonce
        if (!check_ajax_referer('saw_notifications_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')]);
        }
        
        // Get user
        $user_id = $this->get_current_saw_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Nepřihlášený uživatel', 'saw-visitors')]);
        }
        
        // Load class
        if (!$this->load_notifications_class()) {
            wp_send_json_error(['message' => __('Chyba systému', 'saw-visitors')]);
        }
        
        // Get context
        $context = $this->get_current_context();
        
        // Get count
        $count = SAW_Notifications::get_unread_count(
            $user_id, 
            $context['customer_id'], 
            $context['branch_id']
        );
        
        wp_send_json_success(['count' => $count]);
    }
    
    /**
     * AJAX: Mark notification as read
     */
    public function ajax_mark_as_read() {
        // Verify nonce
        if (!check_ajax_referer('saw_notifications_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')]);
        }
        
        // Get user
        $user_id = $this->get_current_saw_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Nepřihlášený uživatel', 'saw-visitors')]);
        }
        
        // Get notification ID
        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
        
        if (!$notification_id) {
            wp_send_json_error(['message' => __('Chybí ID notifikace', 'saw-visitors')]);
        }
        
        // Load class
        if (!$this->load_notifications_class()) {
            wp_send_json_error(['message' => __('Chyba systému', 'saw-visitors')]);
        }
        
        // Mark as read
        $result = SAW_Notifications::mark_as_read($notification_id, $user_id);
        
        if ($result) {
            // Get new unread count
            $context = $this->get_current_context();
            $count = SAW_Notifications::get_unread_count(
                $user_id, 
                $context['customer_id'], 
                $context['branch_id']
            );
            
            wp_send_json_success(['unread_count' => $count]);
        } else {
            wp_send_json_error(['message' => __('Nepodařilo se označit jako přečtené', 'saw-visitors')]);
        }
    }
    
    /**
     * AJAX: Mark all notifications as read
     */
    public function ajax_mark_all_as_read() {
        // Verify nonce
        if (!check_ajax_referer('saw_notifications_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')]);
        }
        
        // Get user
        $user_id = $this->get_current_saw_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Nepřihlášený uživatel', 'saw-visitors')]);
        }
        
        // Load class
        if (!$this->load_notifications_class()) {
            wp_send_json_error(['message' => __('Chyba systému', 'saw-visitors')]);
        }
        
        // Get context
        $context = $this->get_current_context();
        
        // Mark all as read
        $count = SAW_Notifications::mark_all_as_read($user_id, $context['customer_id']);
        
        wp_send_json_success([
            'marked_count' => $count,
            'unread_count' => 0,
        ]);
    }
    
    /**
     * AJAX: Delete notification
     */
    public function ajax_delete() {
        // Verify nonce
        if (!check_ajax_referer('saw_notifications_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')]);
        }
        
        // Get user
        $user_id = $this->get_current_saw_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Nepřihlášený uživatel', 'saw-visitors')]);
        }
        
        // Get notification ID
        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
        
        if (!$notification_id) {
            wp_send_json_error(['message' => __('Chybí ID notifikace', 'saw-visitors')]);
        }
        
        // Load class
        if (!$this->load_notifications_class()) {
            wp_send_json_error(['message' => __('Chyba systému', 'saw-visitors')]);
        }
        
        // Delete
        $result = SAW_Notifications::delete($notification_id, $user_id);
        
        if ($result) {
            // Get new unread count
            $context = $this->get_current_context();
            $count = SAW_Notifications::get_unread_count(
                $user_id, 
                $context['customer_id'], 
                $context['branch_id']
            );
            
            wp_send_json_success(['unread_count' => $count]);
        } else {
            wp_send_json_error(['message' => __('Nepodařilo se smazat notifikaci', 'saw-visitors')]);
        }
    }
    
    /**
     * AJAX: Delete all notifications
     */
    public function ajax_delete_all() {
        // Verify nonce
        if (!check_ajax_referer('saw_notifications_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')]);
        }
        
        // Get user
        $user_id = $this->get_current_saw_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Nepřihlášený uživatel', 'saw-visitors')]);
        }
        
        // Load class
        if (!$this->load_notifications_class()) {
            wp_send_json_error(['message' => __('Chyba systému', 'saw-visitors')]);
        }
        
        // Get context
        $context = $this->get_current_context();
        
        // Delete all
        $count = SAW_Notifications::delete_all($user_id, $context['customer_id']);
        
        wp_send_json_success([
            'deleted_count' => $count,
            'unread_count' => 0,
        ]);
    }
}

// Initialize controller
new SAW_Notifications_Controller();
