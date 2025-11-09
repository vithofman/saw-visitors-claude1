<?php
/**
 * SAW App Layout - AJAX FIXED v2.2.0
 *
 * Main layout manager for the application.
 * Handles complete page rendering with header, sidebar, footer, and content.
 * Supports AJAX requests for SPA-like navigation.
 *
 * CRITICAL FIX v2.2.0:
 * - ✅ Removed hardcoded saw-app-navigation.js script tag
 * - ✅ Now properly enqueued via SAW_Asset_Manager
 * - ✅ WordPress AJAX requests are NOT intercepted
 * - ✅ Only custom XHR requests go through layout
 * - ✅ Prevents layout from breaking wp_ajax_* handlers
 * - ✅ Fixed layout - eliminuje celostránkový scroll
 *
 * @package SAW_Visitors
 * @version 2.2.0 - REMOVED HARDCODED SCRIPT
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW App Layout Class
 *
 * Manages complete page layout rendering including header, sidebar, footer.
 * Handles both normal page requests and AJAX requests for dynamic content loading.
 *
 * @since 4.6.1
 */
class SAW_App_Layout {
    
    /**
     * Current user data
     *
     * @since 4.6.1
     * @var array
     */
    private $current_user;
    
    /**
     * Current customer data
     *
     * @since 4.6.1
     * @var array
     */
    private $current_customer;
    
    /**
     * Page title
     *
     * @since 4.6.1
     * @var string
     */
    private $page_title;
    
    /**
     * Active menu item ID
     *
     * @since 4.6.1
     * @var string
     */
    private $active_menu;
    
    /**
     * Constructor
     *
     * Initializes layout manager.
     *
     * @since 4.6.1
     */
    public function __construct() {
        // No session needed - using SAW_Context for state management
    }
    
    /**
     * Render page with layout
     *
     * Main rendering method. Handles three request types:
     * 1. WordPress AJAX (wp_ajax_*) - returns immediately without rendering
     * 2. Custom AJAX (SPA navigation) - renders content only as JSON
     * 3. Normal page request - renders complete HTML page
     *
     * ✅ FIX: Don't intercept WordPress AJAX requests
     *
     * @since 4.6.1
     * @param string     $content      Page content HTML
     * @param string     $page_title   Page title
     * @param string     $active_menu  Active menu ID
     * @param array|null $user         Optional user data override
     * @param array|null $customer     Optional customer data override
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
     * Loads customer data from database using SAW_Context.
     * Returns default data if customer not found.
     *
     * @since 4.6.1
     * @return array Customer data array
     */
    private function get_current_customer_data() {
        $customer_id = SAW_Context::get_customer_id();
        
        if ($customer_id) {
            global $wpdb;
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_customers',
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                return array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'address' => $customer['address'] ?? '',
                    'logo_url' => $customer['logo_url'] ?? '',
                );
            }
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        );
    }
    
    /**
     * Get current user data
     *
     * Loads user data from WordPress and SAW users table.
     * Returns guest data if not logged in.
     *
     * @since 4.6.1
     * @return array User data array
     */
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM %i WHERE wp_user_id = %d AND is_active = 1",
                $wpdb->prefix . 'saw_users',
                $wp_user->ID
            ), ARRAY_A);
            
            if ($saw_user) {
                return array(
                    'id' => $saw_user['id'],
                    'name' => $saw_user['first_name'] . ' ' . $saw_user['last_name'],
                    'email' => $wp_user->user_email,
                    'role' => $saw_user['role'],
                    'first_name' => $saw_user['first_name'],
                    'last_name' => $saw_user['last_name'],
                    'customer_id' => $saw_user['customer_id'],
                    'branch_id' => $saw_user['branch_id'],
                );
            }
            
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Guest',
            'email' => '',
            'role' => 'guest',
        );
    }
    
    /**
     * Check if request is custom AJAX (not WordPress AJAX)
     *
     * Detects custom XMLHttpRequest for SPA-like navigation.
     * Does NOT detect WordPress admin-ajax.php requests.
     *
     * @since 4.6.1
     * @return bool True if custom AJAX request
     */
    private function is_ajax_request() {
        $http_x_requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH'])) 
            : '';
        
        return !empty($http_x_requested_with) && 
               strtolower($http_x_requested_with) === 'xmlhttprequest';
    }
    
    /**
     * Render content only (for AJAX requests)
     *
     * Returns JSON response with content, title, and active menu.
     * Used for SPA-like navigation without full page reload.
     *
     * @since 4.6.1
     * @param string $content Page content HTML
     * @return void
     */
    private function render_content_only($content) {
        header('Content-Type: application/json');
        
        wp_send_json_success(array(
            'content' => $content,
            'title' => $this->page_title,
            'active_menu' => $this->active_menu
        ));
    }
    
    /**
     * Render complete page
     *
     * Outputs full HTML page with header, sidebar, footer, and content.
     * Used for normal page requests (not AJAX).
     *
     * ✅ FIXED v2.2.0: Removed hardcoded saw-app-navigation.js script tag
     * Script is now properly enqueued via SAW_Asset_Manager with correct dependencies
     *
     * @since 4.6.1
     * @param string $content Page content HTML
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
            
            <link rel="stylesheet" href="<?php echo esc_url(SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app.css?v=' . SAW_VISITORS_VERSION); ?>">
            <link rel="stylesheet" href="<?php echo esc_url(SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-header.css?v=' . SAW_VISITORS_VERSION); ?>">
            <link rel="stylesheet" href="<?php echo esc_url(SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-sidebar.css?v=' . SAW_VISITORS_VERSION); ?>">
            <link rel="stylesheet" href="<?php echo esc_url(SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-responsive.css?v=' . SAW_VISITORS_VERSION); ?>">
            <link rel="stylesheet" href="<?php echo esc_url(SAW_VISITORS_PLUGIN_URL . 'assets/css/fixed-layout.css?v=' . SAW_VISITORS_VERSION); ?>">
            
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
            // ✅ FIXED v2.2.0: saw-app-navigation.js is now properly enqueued
            // via SAW_Asset_Manager::enqueue_global_scripts() with dependencies
            // No need for hardcoded script tag here!
            if (function_exists('wp_print_footer_scripts')) {
                wp_print_footer_scripts();
            }
            ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render header component
     *
     * Loads and renders the header component with user and customer data.
     *
     * @since 4.6.1
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
     * Loads and renders the sidebar component with navigation menu.
     *
     * @since 4.6.1
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
     * Loads and renders the footer component.
     *
     * @since 4.6.1
     * @return void
     */
    private function render_footer() {
        if (class_exists('SAW_App_Footer')) {
            $footer = new SAW_App_Footer();
            $footer->render();
        }
    }
}