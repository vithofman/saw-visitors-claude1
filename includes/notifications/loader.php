<?php
/**
 * SAW Notifications Loader
 *
 * Initializes the notification system:
 * - Loads required classes
 * - Registers cron jobs
 * - Enqueues assets
 *
 * Include this file in your main plugin file.
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
 * SAW Notifications Loader Class
 *
 * Handles initialization and asset loading for notifications.
 *
 * @since 1.0.0
 */
class SAW_Notifications_Loader {
    
    /**
     * Singleton instance
     *
     * @var SAW_Notifications_Loader|null
     */
    private static $instance = null;
    
    /**
     * Plugin directory path
     *
     * @var string
     */
    private $plugin_dir;
    
    /**
     * Plugin URL
     *
     * @var string
     */
    private $plugin_url;
    
    /**
     * Get singleton instance
     *
     * @return SAW_Notifications_Loader
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->plugin_dir = SAW_VISITORS_PLUGIN_DIR;
        $this->plugin_url = SAW_VISITORS_PLUGIN_URL;
        
        $this->load_dependencies();
        $this->register_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Load model
        require_once $this->plugin_dir . 'includes/notifications/class-saw-notifications.php';
        
        // Load triggers
        require_once $this->plugin_dir . 'includes/notifications/class-saw-notification-triggers.php';
        
        // Load controller (registers AJAX handlers)
        require_once $this->plugin_dir . 'includes/notifications/controller.php';
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Register cron schedules
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);
        
        // Activation hook
        register_activation_hook(SAW_VISITORS_PLUGIN_FILE, [$this, 'activate']);
        
        // Deactivation hook
        register_deactivation_hook(SAW_VISITORS_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Also enqueue for admin if using SAW admin pages
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Register custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function register_cron_schedules($schedules) {
        // Every morning at 7 AM (approximately)
        $schedules['saw_morning'] = [
            'interval' => DAY_IN_SECONDS,
            'display' => __('Once daily (morning)', 'saw-visitors'),
        ];
        
        return $schedules;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Schedule daily reminders cron (run at 7:00 AM)
        if (!wp_next_scheduled('saw_daily_visit_reminders')) {
            // Calculate next 7 AM
            $next_7am = strtotime('today 7:00');
            if ($next_7am < time()) {
                $next_7am = strtotime('tomorrow 7:00');
            }
            wp_schedule_event($next_7am, 'daily', 'saw_daily_visit_reminders');
        }
        
        // Schedule cleanup cron (run at 3:00 AM)
        if (!wp_next_scheduled('saw_notifications_cleanup')) {
            $next_3am = strtotime('today 3:00');
            if ($next_3am < time()) {
                $next_3am = strtotime('tomorrow 3:00');
            }
            wp_schedule_event($next_3am, 'daily', 'saw_notifications_cleanup');
        }
        
        // Create database table
        $this->create_database_table();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove scheduled cron jobs
        wp_clear_scheduled_hook('saw_daily_visit_reminders');
        wp_clear_scheduled_hook('saw_notifications_cleanup');
    }
    
    /**
     * Create database table
     */
    private function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_notifications';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Include schema file
        $schema_file = $this->plugin_dir . 'includes/database/schemas/schema-notifications.php';
        
        if (file_exists($schema_file)) {
            require_once $schema_file;
            
            if (function_exists('saw_get_schema_notifications')) {
                $sql = saw_get_schema_notifications(
                    $table_name, 
                    $wpdb->prefix . 'saw_', 
                    $charset_collate
                );
                
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql);
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Only load for logged in users on SAW pages
        if (!is_user_logged_in()) {
            return;
        }
        
        // Check if we're on a SAW page
        if (!$this->is_saw_page()) {
            return;
        }
        
        $this->enqueue_notification_assets();
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        // Only load for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $this->enqueue_notification_assets();
    }
    
    /**
     * Enqueue notification CSS and JS
     */
    private function enqueue_notification_assets() {
        $version = defined('SAW_VISITORS_VERSION') ? SAW_VISITORS_VERSION : '1.0.0';
        
        // CSS
        wp_enqueue_style(
            'saw-notifications',
            $this->plugin_url . 'assets/css/components/notifications.css',
            [],
            $version
        );
        
        // JavaScript
        wp_enqueue_script(
            'saw-notifications',
            $this->plugin_url . 'assets/js/saw-notifications.js',
            [],
            $version,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('saw-notifications', 'sawNotificationsConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_notifications_nonce'),
        ]);
    }
    
    /**
     * Check if current page is a SAW page
     *
     * @return bool
     */
    private function is_saw_page() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check for admin, terminal, or other SAW routes
        return (
            strpos($uri, '/admin/') !== false ||
            strpos($uri, '/terminal/') !== false ||
            strpos($uri, '/visitor-invitation/') !== false
        );
    }
}

/**
 * Initialize notifications
 *
 * Call this function from your main plugin file after plugins_loaded.
 */
function saw_init_notifications() {
    SAW_Notifications_Loader::instance();
}

// Auto-initialize on plugins_loaded
add_action('plugins_loaded', 'saw_init_notifications', 20);
