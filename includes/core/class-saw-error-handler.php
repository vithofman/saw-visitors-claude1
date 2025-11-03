<?php
/**
 * SAW Error Handler
 * 
 * Centralized error handling with user-friendly messages and DB logging.
 * Replaces scattered wp_die() calls throughout the plugin.
 * 
 * @package SAW_Visitors
 * @since 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Error_Handler {
    
    /**
     * Handle error with user-friendly message
     * 
     * @param string|WP_Error $error Error message or WP_Error object
     * @param string $context Where the error occurred
     * @param array $data Additional data for logging
     * @return void
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
     * @param string|WP_Error $error
     * @param string $context
     * @return void (dies after rendering)
     */
    public static function render_user_friendly_error($error, $context = '') {
        $message = is_wp_error($error) ? $error->get_error_message() : $error;
        
        $title_map = [
            'database' => 'Chyba databáze',
            'permission' => 'Nedostatečná oprávnění',
            'validation' => 'Chyba validace',
            'authentication' => 'Chyba přihlášení',
            'not_found' => 'Nenalezeno',
            'default' => 'Došlo k chybě'
        ];
        
        $title = $title_map[$context] ?? $title_map['default'];
        
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $message,
                'context' => $context
            ]);
            exit;
        }
        
        wp_die(
            self::get_error_html($message, $title),
            $title,
            ['response' => 500, 'back_link' => true]
        );
    }
    
    /**
     * Get HTML for error page
     * 
     * @param string $message
     * @param string $title
     * @return string
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
                    ← Zpět
                </a>
                <a href="/admin/" style="display: inline-block; padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
                    🏠 Dashboard
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Log error to database
     * 
     * @param string|WP_Error $error
     * @param string $context
     * @param array $data
     * @return bool
     */
    public static function log_to_db($error, $context = '', $data = []) {
        global $wpdb;
        
        $message = is_wp_error($error) ? $error->get_error_message() : $error;
        
        $customer_id = null;
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        $context_data = [
            'context' => $context,
            'data' => $data,
            'user_id' => get_current_user_id(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ];
        
        $stack_trace = null;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $stack_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'saw_error_log',
            [
                'customer_id' => $customer_id,
                'error_level' => 'error',
                'message' => substr($message, 0, 500),
                'context' => wp_json_encode($context_data),
                'stack_trace' => wp_json_encode($stack_trace),
                'file' => __FILE__,
                'line' => __LINE__,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        return true;
    }
    
    /**
     * Quick helper for permission errors
     * 
     * @param string $message
     * @return void
     */
    public static function permission_denied($message = 'Nemáte oprávnění k této akci') {
        self::handle($message, 'permission');
    }
    
    /**
     * Quick helper for not found errors
     * 
     * @param string $what What was not found
     * @return void
     */
    public static function not_found($what = 'Záznam') {
        self::handle($what . ' nebyl nalezen', 'not_found');
    }
    
    /**
     * Quick helper for validation errors
     * 
     * @param string $message
     * @return void
     */
    public static function validation_error($message) {
        self::handle($message, 'validation');
    }
}