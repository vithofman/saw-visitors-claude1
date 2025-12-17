<?php
/**
 * SAW App Layout - AJAX FIXED v2.4.0
 *
 * Main layout manager for the application.
 * Handles complete page rendering with header, sidebar, footer, and content.
 * Supports AJAX requests for SPA-like navigation.
 *
 * FEATURE v2.4.0:
 * - ✅ Added bottom navigation for mobile/tablet devices
 * - ✅ Bottom nav replaces footer on screens ≤1024px
 * - ✅ 4 main navigation items: Dashboard, Visits, Visitors, Calendar
 *
 * CRITICAL FIX v2.3.0:
 * - ✅ Fixed missing logo_url in customer data on dashboard
 * - ✅ Added primary_color to customer data
 * - ✅ Customer data now consistent across all pages
 *
 * CRITICAL FIX v2.2.0:
 * - ✅ Removed hardcoded saw-app-navigation.js script tag
 * - ✅ Now properly enqueued via SAW_Asset_Loader
 * - ✅ WordPress AJAX requests are NOT intercepted
 * - ✅ Only custom XHR requests go through layout
 * - ✅ Prevents layout from breaking wp_ajax_* handlers
 * - ✅ Fixed layout - eliminuje celostránkový scroll
 *
 * @package SAW_Visitors
 * @version 2.4.0 - ADDED BOTTOM NAVIGATION
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load bottom navigation component
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-bottom-nav.php';

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
     * Current UI language
     *
     * @since 5.4.0
     * @var string
     */
    private $lang;
    
    /**
     * Constructor
     *
     * Initializes layout manager.
     *
     * @since 4.6.1
     */
    public function __construct() {
        // No session needed - using SAW_Context for state management
        $this->lang = $this->get_user_language();
    }
    
    /**
     * Get user's UI language
     *
     * Retrieves language preference from various sources.
     *
     * @since 5.4.0
     * @return string Language code (cs, en, etc.)
     */
    private function get_user_language() {
        // Try SAW language function
        if (function_exists('saw_get_user_language')) {
            return saw_get_user_language();
        }
        
        // Try cookie
        if (isset($_COOKIE['saw_ui_lang'])) {
            return sanitize_text_field($_COOKIE['saw_ui_lang']);
        }
        
        // Default to Czech
        return 'cs';
    }
    
    /**
     * Render page with layout
     *
     * Main rendering method. Always renders complete HTML page.
     * 
     * ✅ REFACTORED v6.0.0: Removed AJAX navigation support.
     * All navigation now uses full page reload with View Transition API.
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
        
        // ✅ CRITICAL: Don't intercept WordPress AJAX requests!
        // WordPress AJAX handlers (wp_ajax_*) must handle their own responses
        if (wp_doing_ajax()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_App_Layout] WordPress AJAX detected - skipping layout render');
            }
            // Let WordPress AJAX handlers do their thing
            return;
        }
        
        // ✅ Always render complete page (full page reload)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_App_Layout] Rendering complete page');
            error_log('[SAW_App_Layout] Page title: ' . $this->page_title);
            error_log('[SAW_App_Layout] Active menu: ' . ($this->active_menu ?: 'empty'));
        }
        
        // CRITICAL: Always render complete page
        // This ensures layout is always present, even if something went wrong earlier
        try {
            $this->render_complete_page($content);
        } catch (Exception $e) {
            error_log('[SAW_App_Layout] ERROR rendering complete page: ' . $e->getMessage());
            error_log('[SAW_App_Layout] Stack trace: ' . $e->getTraceAsString());
            
            // Fallback: render minimal page with error message
            $this->render_error_fallback($content, $e->getMessage());
        }
        exit;
    }
    
    /**
     * Render error fallback page
     *
     * Used when main render fails to ensure something is always displayed.
     *
     * @since 5.1.0
     * @param string $content Original content
     * @param string $error_message Error message
     * @return void
     */
    private function render_error_fallback($content, $error_message) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($this->page_title ?: 'SAW Visitors'); ?> - SAW Visitors</title>
            <?php wp_head(); ?>
        </head>
        <body class="saw-app-body">
            <div style="padding: 20px; max-width: 800px; margin: 50px auto;">
                <h1>SAW Visitors</h1>
                <div class="error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;">
                    <strong>Chyba při načítání stránky:</strong><br>
                    <?php echo esc_html($error_message); ?>
                </div>
                <div style="margin-top: 20px;">
                    <a href="<?php echo esc_url(home_url('/admin/')); ?>" class="button">Zpět na hlavní stránku</a>
                </div>
                <?php echo $content; ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get current customer data
     *
     * Loads customer data from database using SAW_Context.
     * Returns default data if customer not found.
     *
     * ✅ FIX v2.4.0: Removed address and primary_color columns
     *
     * @since 4.6.1
     * @return array Customer data array
     */
    private function get_current_customer_data() {
        $customer_id = SAW_Context::get_customer_id();
        
        if ($customer_id) {
            global $wpdb;
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, ico, logo_url FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_customers',
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                return array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'logo_url' => $customer['logo_url'] ?? '',
                );
            }
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
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
     * Get body attributes (for dark mode, etc.)
     *
     * @since 1.0.0
     * @return string Body attributes HTML
     */
    private function get_body_attributes() {
        $user_id = get_current_user_id();
        $dark_mode = get_user_meta($user_id, 'saw_dark_mode', true);
        
        $attrs = array();
        
        if ($dark_mode === '1') {
            $attrs[] = 'data-theme="dark"';
        }
        
        return !empty($attrs) ? implode(' ', $attrs) : '';
    }
    
    /**
     * Render complete page
     *
     * Outputs full HTML page with header, sidebar, footer, and content.
     * Used for normal page requests (not AJAX).
     *
     * ✅ FIXED v2.2.0: Removed hardcoded saw-app-navigation.js script tag
     * Script is now properly enqueued via SAW_Asset_Loader with correct dependencies
     *
     * ✅ ADDED v2.4.0: Bottom navigation for mobile/tablet devices
     * Renders after footer, visible only on screens ≤1024px via CSS
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
            
            <!-- CSS assets are loaded via SAW_Asset_Loader::enqueue_global() in wp_enqueue_scripts hook -->
            
            <?php include SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php'; ?>
            
            <?php
            if (function_exists('wp_head')) {
                wp_head();
            }
            ?>
        </head>
        <body class="saw-app-body" <?php echo $this->get_body_attributes(); ?>>
            
            <!-- Page loader overlay - visible until JS marks page as ready -->
            <div id="sawPageLoader" class="saw-page-loader">
                <div class="saw-page-loader-spinner"></div>
            </div>
            
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
            // ✅ NEW v2.4.0: Bottom navigation for mobile/tablet
            $this->render_bottom_nav(); 
            ?>
            
            <?php
            // ✅ FIXED v2.2.0: saw-app-navigation.js is now properly enqueued
            // via SAW_Asset_Loader::enqueue_global_scripts() with dependencies
            // No need for hardcoded script tag here!
            if (function_exists('wp_print_footer_scripts')) {
                wp_print_footer_scripts();
            }
            
            // CRITICAL: Print WordPress media templates for content module (TinyMCE media buttons)
            if ($this->active_menu === 'content') {
                if (function_exists('wp_print_media_templates')) {
                    wp_print_media_templates();
                }
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
     * Note: Footer is hidden on mobile/tablet via CSS, replaced by bottom nav.
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
    
    /**
     * Render bottom navigation component
     *
     * Loads and renders the bottom navigation for mobile/tablet devices.
     * Only visible on screens ≤1024px (controlled via CSS).
     * Replaces footer on smaller screens for app-like navigation.
     *
     * @since 5.4.0
     * @return void
     */
    private function render_bottom_nav() {
        if (class_exists('SAW_App_Bottom_Nav')) {
            // Get user role for permission checks
            $saw_role = $this->current_user['role'] ?? 'user';
            
            $bottom_nav = new SAW_App_Bottom_Nav(
                $this->active_menu,
                $this->lang,
                $saw_role
            );
            
            // Check if should render (can be filtered)
            if ($bottom_nav->should_render()) {
                $bottom_nav->render();
            }
        }
    }
}