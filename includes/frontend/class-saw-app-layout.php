<?php
/**
 * SAW App Layout - AJAX FIXED v2.3.0
 *
 * Main layout manager for the application.
 * Handles complete page rendering with header, sidebar, footer, and content.
 * Supports AJAX requests for SPA-like navigation.
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
 * @version 2.3.0 - FIXED CUSTOMER DATA
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
     * Returns JSON response with content, title, active menu, and required assets.
     * Used for SPA-like navigation without full page reload.
     *
     * CRITICAL FIX: Now includes CSS/JS assets that need to be loaded for the page.
     *
     * @since 4.6.1
     * @param string $content Page content HTML
     * @return void
     */
    private function render_content_only($content) {
        header('Content-Type: application/json');
        
        // Get required CSS/JS assets for current module
        $assets = $this->get_required_assets();
        
        // DEBUG: Log response data
        error_log('[SAW_App_Layout] render_content_only() - active_menu: ' . ($this->active_menu ?: 'empty'));
        error_log('[SAW_App_Layout] render_content_only() - assets CSS count: ' . count($assets['css']));
        error_log('[SAW_App_Layout] render_content_only() - assets JS count: ' . count($assets['js']));
        
        wp_send_json_success(array(
            'content' => $content,
            'title' => $this->page_title,
            'active_menu' => $this->active_menu,
            'assets' => $assets
        ));
    }
    
    /**
     * Get required CSS/JS assets for current page
     *
     * Determines which CSS/JS files are needed based on active module.
     * This is needed for AJAX navigation where wp_head() is not called.
     * 
     * CRITICAL: Always includes ALL global component CSS/JS files that are needed
     * by admin-table and other components. This ensures everything works during AJAX navigation.
     *
     * @since 4.6.1
     * @return array Array with 'css' and 'js' keys containing asset URLs
     */
    private function get_required_assets() {
        $assets = array(
            'css' => array(),
            'js' => array()
        );
        
        // DEBUG: Log asset detection
        error_log('[SAW_App_Layout] get_required_assets() called');
        error_log('[SAW_App_Layout] active_menu: ' . ($this->active_menu ?: 'empty'));
        error_log('[SAW_App_Layout] REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        
        // CRITICAL: Always include ALL global component CSS files needed by admin-table
        // These are normally loaded via SAW_Asset_Loader::enqueue_global() but not during AJAX
        
        // Core CSS files (from SAW_Asset_Loader::CORE_STYLES)
        $core_styles = array(
            'saw-variables' => 'foundation/variables.css',
            'saw-reset' => 'foundation/reset.css',
            'saw-typography' => 'foundation/typography.css'
        );
        
        foreach ($core_styles as $handle => $path) {
            $css_url = SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path;
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $deps = ($handle === 'saw-variables') ? array() : array('saw-variables');
                $assets['css'][] = array(
                    'handle' => $handle,
                    'src' => $css_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => $deps
                );
            }
        }
        
        // Component CSS files (from SAW_Asset_Loader::COMPONENT_STYLES)
        // Include all consolidated component styles
        $component_styles = array(
            'saw-base-components' => 'components/base-components.css',
            'saw-forms' => 'components/forms.css', // Consolidated: forms, buttons, selectbox, select-create, color-picker, search-input
            'saw-tables' => 'components/tables.css', // Consolidated: tables, table-column-types, admin-table, admin-table-sidebar
            'saw-navigation' => 'components/navigation.css', // Consolidated: customer-switcher, branch-switcher, language-switcher
            'saw-feedback' => 'components/feedback.css', // Consolidated: alerts, badges, cards, modals, pagination, detail-sections
        );
        
        // Layout CSS files
        $layout_styles = array(
            'saw-layout' => 'layout/layout.css', // Consolidated: grid, containers, spacing
        );
        
        // App CSS files
        $app_styles = array(
            'saw-app' => 'app/app.css', // Consolidated: base, header, sidebar, footer, fixed-layout, legacy-base
        );
        
        // Combine all CSS
        $all_css = array_merge($component_styles, $layout_styles, $app_styles);
        
        foreach ($all_css as $handle => $path) {
            $css_url = SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path;
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                // Determine dependencies based on file type
                if ($handle === 'saw-base-components') {
                    $deps = array('saw-variables');
                } elseif (in_array($handle, array('saw-layout', 'saw-app'))) {
                    $deps = array('saw-variables', 'saw-base-components');
                } else {
                    $deps = array('saw-variables', 'saw-base-components');
                }
                $assets['css'][] = array(
                    'handle' => $handle,
                    'src' => $css_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => $deps
                );
            }
        }
        
        // Core JS files (from assets/js/core/)
        // CRITICAL: Do NOT include saw-app and saw-app-navigation here - they are already loaded globally!
        // Including them again would cause conflicts and duplicate loading
        $core_js = array(
            'saw-validation' => 'core/validation.js',
        );
        
        foreach ($core_js as $handle => $path) {
            $js_url = SAW_VISITORS_PLUGIN_URL . 'assets/js/' . $path;
            $js_path = SAW_VISITORS_PLUGIN_DIR . 'assets/js/' . $path;
            if (file_exists($js_path)) {
                // CRITICAL: saw-app and saw-app-navigation are already loaded globally, don't include them
                // Only include validation.js which is module-specific
                $deps = array('jquery', 'saw-app');
                $assets['js'][] = array(
                    'handle' => $handle,
                    'src' => $js_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => $deps
                );
            }
        }
        
        // Component JS files (from assets/js/components/)
        $component_js = array(
            'saw-forms' => 'components/forms.js', // Consolidated: selectbox, select-create, color-picker, file-upload
            'saw-navigation-components' => 'components/navigation-components.js', // Consolidated: customer-switcher, branch-switcher, language-switcher
            'saw-ui' => 'components/ui.js', // Consolidated: modal, modal-triggers, search
            'saw-admin-table-component' => 'components/admin-table.js',
            'saw-admin-table-sidebar' => 'components/admin-table-sidebar.js',
        );
        
        foreach ($component_js as $handle => $path) {
            $js_url = SAW_VISITORS_PLUGIN_URL . 'assets/js/' . $path;
            $js_path = SAW_VISITORS_PLUGIN_DIR . 'assets/js/' . $path;
            if (file_exists($js_path)) {
                $deps = ($handle === 'saw-admin-table-sidebar') 
                    ? array('jquery', 'saw-app', 'saw-admin-table-component')
                    : array('jquery', 'saw-app');
                $assets['js'][] = array(
                    'handle' => $handle,
                    'src' => $js_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => $deps
                );
            }
        }
        
        // CRITICAL: WordPress dashicons (needed for icons in buttons)
        // Get dashicons URL from WordPress
        $dashicons_url = includes_url('css/dashicons.min.css');
        $assets['css'][] = array(
            'handle' => 'dashicons',
            'src' => $dashicons_url,
            'deps' => array()
        );
        
        // Get active module - try multiple methods
        // CRITICAL: For AJAX requests, we need to detect module from URL since active_menu may not be set
        $active_module = null;
        
        // Method 1: From active_menu (passed to render()) - only if not empty
        if (!empty($this->active_menu)) {
            $active_module = $this->active_menu;
            error_log('[SAW_App_Layout] Method 1: active_module from active_menu: ' . $active_module);
        }
        
        // Method 2: From REQUEST_URI (CRITICAL for AJAX - works even without query vars)
        if (empty($active_module) && isset($_SERVER['REQUEST_URI'])) {
            $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
            if (preg_match('#/admin/([^/]+)#', $uri, $matches)) {
                $active_module = $matches[1];
                error_log('[SAW_App_Layout] Method 2: active_module from REQUEST_URI: ' . $active_module);
            }
        }
        
        // Method 3: From query var (for AJAX requests)
        if (empty($active_module)) {
            $path = get_query_var('saw_path');
            if (!empty($path)) {
                $clean_path = trim($path, '/');
                // Remove 'admin/' prefix if present
                $clean_path = preg_replace('#^admin/#', '', $clean_path);
                $segments = explode('/', $clean_path);
                if (isset($segments[0]) && $segments[0] !== 'admin' && !is_numeric($segments[0])) {
                    $active_module = $segments[0];
                    error_log('[SAW_App_Layout] Method 3: active_module from query var: ' . $active_module);
                }
            }
        }
        
        // Method 4: From router (fallback)
        if (empty($active_module)) {
            if (class_exists('SAW_Router')) {
                try {
                    $router = SAW_Router::get_instance();
                    if ($router && method_exists($router, 'get_active_module')) {
                        $active_module = $router->get_active_module();
                        error_log('[SAW_App_Layout] Method 4: active_module from router: ' . ($active_module ?: 'null'));
                    }
                } catch (Exception $e) {
                    error_log('[SAW_App_Layout] Method 4: Error getting router: ' . $e->getMessage());
                }
            }
        }
        
        // Method 5: Check if active_module matches a known module route and convert to slug
        if (!empty($active_module)) {
            $modules = SAW_Module_Loader::get_all();
            foreach ($modules as $slug => $config) {
                $module_route = ltrim($config['route'] ?? '', '/');
                $module_route_without_admin = str_replace('admin/', '', $module_route);
                // If active_module matches route (e.g., 'content' matches 'admin/content')
                if ($module_route_without_admin === $active_module || 
                    strpos($module_route_without_admin, $active_module . '/') === 0) {
                    $active_module = $slug;
                    error_log('[SAW_App_Layout] Method 5: active_module converted to slug: ' . $active_module);
                    break;
                }
            }
        }
        
        error_log('[SAW_App_Layout] Final active_module: ' . ($active_module ?: 'null'));
        
        // Module-specific CSS
        if ($active_module && $active_module !== 'dashboard' && $active_module !== 'settings') {
            // CRITICAL: Some modules have subdirectories (e.g., content/content.css)
            $module_css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/modules/saw-' . $active_module . '.css';
            $module_css = SAW_VISITORS_PLUGIN_URL . 'assets/css/modules/saw-' . $active_module . '.css';
            
            // Check for subdirectory structure (e.g., content/content.css)
            if (!file_exists($module_css_path)) {
                $module_css_path_alt = SAW_VISITORS_PLUGIN_DIR . 'assets/css/modules/' . $active_module . '/' . $active_module . '.css';
                if (file_exists($module_css_path_alt)) {
                    $module_css_path = $module_css_path_alt;
                    $module_css = SAW_VISITORS_PLUGIN_URL . 'assets/css/modules/' . $active_module . '/' . $active_module . '.css';
                }
            }
            
            error_log('[SAW_App_Layout] Checking module CSS: ' . $module_css_path . ' - exists: ' . (file_exists($module_css_path) ? 'YES' : 'NO'));
            if (file_exists($module_css_path)) {
                $assets['css'][] = array(
                    'handle' => 'saw-module-' . $active_module,
                    'src' => $module_css . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => array('saw-variables', 'saw-base-components')
                );
                error_log('[SAW_App_Layout] Added module CSS: saw-module-' . $active_module);
            } else {
                error_log('[SAW_App_Layout] WARNING: Module CSS file not found: ' . $module_css_path);
            }
            
            // Module-specific JS
            $module_js = SAW_VISITORS_PLUGIN_URL . 'assets/js/modules/saw-' . $active_module . '.js';
            $module_js_path = SAW_VISITORS_PLUGIN_DIR . 'assets/js/modules/saw-' . $active_module . '.js';
            error_log('[SAW_App_Layout] Checking module JS: ' . $module_js_path . ' - exists: ' . (file_exists($module_js_path) ? 'YES' : 'NO'));
            if (file_exists($module_js_path)) {
                $assets['js'][] = array(
                    'handle' => 'saw-module-' . $active_module,
                    'src' => $module_js . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => array('jquery', 'saw-app', 'saw-validation')
                );
                error_log('[SAW_App_Layout] Added module JS: saw-module-' . $active_module);
            } else {
                error_log('[SAW_App_Layout] WARNING: Module JS file not found: ' . $module_js_path);
            }
            
            // CRITICAL: Content module needs WordPress Media Library and TinyMCE
            error_log('[SAW_App_Layout] Checking if content module - active_module: ' . ($active_module ?: 'null'));
            if ($active_module === 'content') {
                error_log('[SAW_App_Layout] Content module detected - adding WordPress Media/TinyMCE assets');
                
                // CRITICAL: Add dependencies FIRST (underscore, backbone) - WordPress core libraries
                // These are normally loaded by WordPress but not during AJAX
                $underscore_js = includes_url('js/underscore.min.js');
                $assets['js'][] = array(
                    'handle' => 'underscore',
                    'src' => $underscore_js,
                    'deps' => array()
                );
                
                $backbone_js = includes_url('js/backbone.min.js');
                $assets['js'][] = array(
                    'handle' => 'backbone',
                    'src' => $backbone_js,
                    'deps' => array('underscore', 'jquery')
                );
                
                // WP Util (dependency for media) - must come after underscore
                $wp_util_js = includes_url('js/wp-util.min.js');
                $assets['js'][] = array(
                    'handle' => 'wp-util',
                    'src' => $wp_util_js,
                    'deps' => array('jquery', 'underscore')
                );
                
                // Media Models (dependency for media-views) - must come after wp-util and backbone
                $media_models_js = includes_url('js/media-models.min.js');
                $assets['js'][] = array(
                    'handle' => 'media-models',
                    'src' => $media_models_js,
                    'deps' => array('jquery', 'wp-util', 'backbone')
                );
                
                // WordPress Media Library JS - must come after media-models
                $media_js = includes_url('js/media-views.min.js');
                $assets['js'][] = array(
                    'handle' => 'media-views',
                    'src' => $media_js,
                    'deps' => array('jquery', 'media-models', 'wp-util')
                );
                
                // WordPress Media Library CSS - try multiple paths
                $media_css_paths = array(
                    ABSPATH . WPINC . '/css/media.css',
                    ABSPATH . WPINC . '/css/media.min.css'
                );
                $media_css = null;
                foreach ($media_css_paths as $path) {
                    if (file_exists($path)) {
                        $media_css = includes_url(str_replace(ABSPATH . WPINC . '/', '', $path));
                        break;
                    }
                }
                if ($media_css) {
                    $assets['css'][] = array(
                        'handle' => 'media',
                        'src' => $media_css,
                        'deps' => array('dashicons')
                    );
                } else {
                    error_log('[SAW_App_Layout] WARNING: WordPress media.css not found');
                }
                
                // TinyMCE CSS (for wp_editor) - try multiple paths
                $tinymce_css_paths = array(
                    ABSPATH . WPINC . '/css/tinymce.css',
                    ABSPATH . WPINC . '/css/editor.css'
                );
                $tinymce_css = null;
                foreach ($tinymce_css_paths as $path) {
                    if (file_exists($path)) {
                        $tinymce_css = includes_url(str_replace(ABSPATH . WPINC . '/', '', $path));
                        break;
                    }
                }
                if ($tinymce_css) {
                    $assets['css'][] = array(
                        'handle' => 'tinymce',
                        'src' => $tinymce_css,
                        'deps' => array()
                    );
                } else {
                    error_log('[SAW_App_Layout] WARNING: WordPress tinymce.css not found');
                }
                
                // TinyMCE JS (core)
                $tinymce_js = includes_url('js/tinymce/tinymce.min.js');
                $assets['js'][] = array(
                    'handle' => 'tinymce',
                    'src' => $tinymce_js,
                    'deps' => array()
                );
                
                // WordPress Editor JS (initializes TinyMCE) - try multiple paths
                $editor_js_paths = array(
                    ABSPATH . WPINC . '/js/editor.min.js',
                    ABSPATH . WPINC . '/js/editor.js'
                );
                $editor_js = null;
                foreach ($editor_js_paths as $path) {
                    if (file_exists($path)) {
                        $editor_js = includes_url(str_replace(ABSPATH . WPINC . '/', '', $path));
                        break;
                    }
                }
                if ($editor_js) {
                    $assets['js'][] = array(
                        'handle' => 'editor',
                        'src' => $editor_js,
                        'deps' => array('jquery', 'tinymce', 'wp-util')
                    );
                } else {
                    error_log('[SAW_App_Layout] WARNING: WordPress editor.js not found');
                }
            }
        }
        
        // DEBUG: Log final asset count
        error_log('[SAW_App_Layout] Final assets count - CSS: ' . count($assets['css']) . ', JS: ' . count($assets['js']));
        
        return $assets;
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
            // via SAW_Asset_Loader::enqueue_global_scripts() with dependencies
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