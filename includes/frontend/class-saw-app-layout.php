<?php
/**
 * SAW App Layout - AJAX FIXED v2.0.0
 * 
 * CRITICAL FIX:
 * - ✅ WordPress AJAX requests are NOT intercepted
 * - ✅ Only custom XHR requests go through layout
 * - ✅ Prevents layout from breaking wp_ajax_* handlers
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - AJAX FIX
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Layout {
    
    private $current_user;
    private $current_customer;
    private $page_title;
    private $active_menu;
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Render page with layout
     * 
     * ✅ FIX: Don't intercept WordPress AJAX requests
     * 
     * @param string $content Page content
     * @param string $page_title Page title
     * @param string $active_menu Active menu ID
     * @param array|null $user User data
     * @param array|null $customer Customer data
     * @return void
     */
    public function render($content, $page_title = '', $active_menu = '', $user = null, $customer = null) {
        $this->page_title = $page_title;
        $this->active_menu = $active_menu;
        
        $this->current_user = $user ?: $this->get_current_user_data();
        $this->current_customer = $customer ?: $this->get_current_customer_data();
        
        // ✅ CRITICAL FIX: Don't intercept WordPress AJAX requests!
        // WordPress AJAX handlers (wp_ajax_*) must handle their own responses
        if (wp_doing_ajax()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_App_Layout] WordPress AJAX detected - skipping layout render');
            }
            // Let WordPress AJAX handlers do their thing
            return;
        }
        
        // ✅ Handle custom XHR requests (for SPA-like navigation)
        if ($this->is_ajax_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_App_Layout] Custom AJAX detected - rendering content only');
            }
            $this->render_content_only($content);
            exit;
        }
        
        // ✅ Normal page request - render complete page
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_App_Layout] Normal request - rendering complete page');
        }
        $this->render_complete_page($content);
        exit;
    }
    
    /**
     * Get current customer data
     * 
     * @return array
     */
    private function get_current_customer_data() {
        $customer_id = SAW_Context::get_customer_id();
        
        if ($customer_id) {
            global $wpdb;
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                return [
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'address' => $customer['address'] ?? '',
                    'logo_url' => $customer['logo_url'] ?? '',
                ];
            }
        }
        
        return [
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        ];
    }
    
    /**
     * Get current user data
     * 
     * @return array
     */
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                $wp_user->ID
            ), ARRAY_A);
            
            if ($saw_user) {
                return [
                    'id' => $saw_user['id'],
                    'name' => $saw_user['first_name'] . ' ' . $saw_user['last_name'],
                    'email' => $wp_user->user_email,
                    'role' => $saw_user['role'],
                    'first_name' => $saw_user['first_name'],
                    'last_name' => $saw_user['last_name'],
                    'customer_id' => $saw_user['customer_id'],
                    'branch_id' => $saw_user['branch_id'],
                ];
            }
            
            return [
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
            ];
        }
        
        return [
            'id' => 0,
            'name' => 'Guest',
            'email' => '',
            'role' => 'guest',
        ];
    }
    
    /**
     * Check if request is custom AJAX (not WordPress AJAX)
     * 
     * @return bool
     */
    private function is_ajax_request() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Render content only (for AJAX requests)
     * 
     * @param string $content Page content
     * @return void
     */
    private function render_content_only($content) {
        header('Content-Type: application/json');
        
        wp_send_json_success([
            'content' => $content,
            'title' => $this->page_title,
            'active_menu' => $this->active_menu
        ]);
    }
    
    /**
     * Render complete page
     * 
     * @param string $content Page content
     * @return void
     */
    private function render_complete_page($content) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($this->page_title); ?> - SAW Visitors</title>
            
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app-header.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app-sidebar.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app-responsive.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            
            <?php
            if (function_exists('wp_head')) {
                wp_head();
            }
            ?>
        </head>
        <body class="saw-app-body">
            
            <?php $this->render_header(); ?>
            
            <div class="saw-app-container">
                <?php $this->render_sidebar(); ?>
                
                <main class="saw-app-main">
                    <div id="saw-app-content" class="saw-app-content">
                        <?php echo $content; ?>
                    </div>
                </main>
            </div>
            
            <?php $this->render_footer(); ?>
            
            <?php
            if (function_exists('wp_print_footer_scripts')) {
                wp_print_footer_scripts();
            }
            ?>
            
            <script src="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/js/saw-app-navigation.js?v=<?php echo SAW_VISITORS_VERSION; ?>"></script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render header component
     * 
     * @return void
     */
    private function render_header() {
        if (class_exists('SAW_App_Header')) {
            $header = new SAW_App_Header($this->current_user, $this->current_customer);
            $header->render();
        }
    }
    
    /**
     * Render sidebar component
     * 
     * @return void
     */
    private function render_sidebar() {
        if (class_exists('SAW_App_Sidebar')) {
            $sidebar = new SAW_App_Sidebar($this->current_user, $this->current_customer, $this->active_menu);
            $sidebar->render();
        }
    }
    
    /**
     * Render footer component
     * 
     * @return void
     */
    private function render_footer() {
        if (class_exists('SAW_App_Footer')) {
            $footer = new SAW_App_Footer();
            $footer->render();
        }
    }
}