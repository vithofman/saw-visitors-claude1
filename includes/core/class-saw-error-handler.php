<?php
/**
 * SAW Error Handler - Centralized Error Management
 *
 * Provides user-friendly error messages, database logging,
 * and helper methods for common error scenarios.
 * Replaces scattered wp_die() calls throughout the plugin.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error handler class
 *
 * @since 1.0.0
 */
class SAW_Error_Handler {
    
    /**
     * Error context title mappings
     *
     * @since 1.0.0
     * @var array
     */
    private static $title_map = [
        'database'       => 'Database Error',
        'permission'     => 'Insufficient Permissions',
        'validation'     => 'Validation Error',
        'authentication' => 'Authentication Error',
        'not_found'      => 'Not Found',
        'default'        => 'An Error Occurred'
    ];
    
    /**
     * Handle error with user-friendly message
     *
     * @since 1.0.0
     * @param string|WP_Error $error   Error message or WP_Error object
     * @param string          $context Where the error occurred
     * @param array           $data    Additional data for logging
     */
    public static function handle($error, $context = '', $data = []) {
        self::log_to_db($error, $context, $data);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = is_wp_error($error) ? $error->get_error_message() : $error;
            error_log(sprintf('[SAW_Error_Handler] %s: %s', $context, $message));
        }
        
        self::render_user_friendly_error($error, $context);
    }
    
    /**
     * Render user-friendly error page
     *
     * @since 1.0.0
     * @param string|WP_Error $error   Error message or WP_Error object
     * @param string          $context Error context
     */
    public static function render_user_friendly_error($error, $context = '') {
        $message = is_wp_error($error) ? $error->get_error_message() : $error;
        
        $title = isset(self::$title_map[$context]) 
            ? __(self::$title_map[$context], 'saw-visitors')
            : __(self::$title_map['default'], 'saw-visitors');
        
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => esc_html($message),
                'context' => sanitize_text_field($context)
            ]);
            exit;
        }
        
        wp_die(
            self::get_error_html($message, $title),
            esc_html($title),
            ['response' => 500, 'back_link' => true]
        );
    }
    
    /**
     * Get HTML for error page
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param string $title   Error title
     * @return string HTML content
     */
    private static function get_error_html($message, $title) {
        ob_start();
        ?>
        <div style="max-width: 600px; margin: 50px auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div style="background: #fee; border-left: 4px solid #dc2626; padding: 20px; border-radius: 8px;">
                <h2 style="margin: 0 0 10px 0; color: #dc2626;">⚠️ <?php echo esc_html($title); ?></h2>
                <p style="margin: 0; color: #374151;"><?php echo esc_html($message); ?></p>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="javascript:history.back()" style="display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px;">
                    ← <?php esc_html_e('Back', 'saw-visitors'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/admin/')); ?>" style="display: inline-block; padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
                    🏠 <?php esc_html_e('Dashboard', 'saw-visitors'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Log error to database
     *
     * @since 1.0.0
     * @param string|WP_Error $error   Error message or WP_Error object
     * @param string          $context Error context
     * @param array           $data    Additional data
     * @return bool Success status
     */
    public static function log_to_db($error, $context = '', $data = []) {
        global $wpdb;
        
        $message = is_wp_error($error) ? $error->get_error_message() : $error;
        
        $customer_id = null;
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
        
        $request_method = isset($_SERVER['REQUEST_METHOD']) 
            ? sanitize_text_field($_SERVER['REQUEST_METHOD']) 
            : '';
        
        $context_data = [
            'context' => sanitize_text_field($context),
            'data'    => $data,
            'user_id' => get_current_user_id(),
            'url'     => $request_uri,
            'method'  => $request_method
        ];
        
        $stack_trace = null;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $stack_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_error_log',
            [
                'customer_id' => $customer_id,
                'error_level' => 'error',
                'message'     => substr(sanitize_text_field($message), 0, 500),
                'context'     => wp_json_encode($context_data),
                'stack_trace' => wp_json_encode($stack_trace),
                'file'        => sanitize_text_field(__FILE__),
                'line'        => absint(__LINE__),
                'created_at'  => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Quick helper for permission errors
     *
     * @since 1.0.0
     * @param string $message Optional custom message
     */
    public static function permission_denied($message = '') {
        if (empty($message)) {
            $message = __('You do not have permission to perform this action.', 'saw-visitors');
        }
        self::handle($message, 'permission');
    }
    
    /**
     * Quick helper for not found errors
     *
     * @since 1.0.0
     * @param string $what What was not found
     */
    public static function not_found($what = '') {
        if (empty($what)) {
            $what = __('Record', 'saw-visitors');
        }
        
        $message = sprintf(
            /* translators: %s: what was not found (e.g., "Record", "Customer") */
            __('%s was not found.', 'saw-visitors'),
            $what
        );
        
        self::handle($message, 'not_found');
    }
    
    /**
     * Quick helper for validation errors
     *
     * @since 1.0.0
     * @param string $message Validation error message
     */
    public static function validation_error($message) {
        self::handle($message, 'validation');
    }
    
    /**
     * Quick helper for database errors
     *
     * @since 1.0.0
     * @param string $message Database error message
     */
    public static function database_error($message = '') {
        if (empty($message)) {
            $message = __('A database error occurred. Please try again.', 'saw-visitors');
        }
        self::handle($message, 'database');
    }
}