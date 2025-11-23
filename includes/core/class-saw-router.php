<?php
/**
 * SAW Router
 *
 * Handles custom routing for SAW Visitors plugin with AJAX protection.
 * Manages authentication routes, admin routes, and module dispatching.
 * Includes sidebar context parsing for split-layout detail/form views.
 *
 * @package     SAW_Visitors
 * @subpackage  Core
 * @version     7.2.0 - LOGO FIX
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Router Class
 *
 * Custom routing system with WordPress rewrite rules integration.
 * Protects AJAX requests from being intercepted.
 * Parses URL segments for sidebar context (detail view, create/edit forms).
 *
 * @since 1.0.0
 */
class SAW_Router {
    
    /**
     * Register rewrite rules
     *
     * Adds custom URL patterns for SAW routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        add_rewrite_rule('^login/?$', 'index.php?saw_route=auth&saw_action=login', 'top');
        add_rewrite_rule('^set-password/?$', 'index.php?saw_route=auth&saw_action=set-password', 'top');
        add_rewrite_rule('^reset-password/?$', 'index.php?saw_route=auth&saw_action=reset-password', 'top');
        add_rewrite_rule('^logout/?$', 'index.php?saw_route=auth&saw_action=logout', 'top');
        
	// Dashboard route (MUST be before generic admin routes)
	add_rewrite_rule('^admin/dashboard/?$', 'index.php?saw_route=admin&saw_page=dashboard', 'top');
        
	add_rewrite_rule('^admin/?$', 'index.php?saw_route=admin', 'top');
        add_rewrite_rule('^admin/([^/]+)/?$', 'index.php?saw_route=admin&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^admin/([^/]+)/(.+)', 'index.php?saw_route=admin&saw_path=$matches[1]/$matches[2]', 'top');
        
        add_rewrite_rule('^manager/?$', 'index.php?saw_route=manager', 'top');
        add_rewrite_rule('^manager/([^/]+)/?$', 'index.php?saw_route=manager&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^manager/([^/]+)/(.+)', 'index.php?saw_route=manager&saw_path=$matches[1]/$matches[2]', 'top');
        
        add_rewrite_rule('^terminal/?$', 'index.php?saw_route=terminal', 'top');
        add_rewrite_rule('^terminal/([^/]+)/?$', 'index.php?saw_route=terminal&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^terminal/([^/]+)/(.+)', 'index.php?saw_route=terminal&saw_path=$matches[1]/$matches[2]', 'top');
        
        add_rewrite_rule('^visitor/?$', 'index.php?saw_route=visitor', 'top');
        add_rewrite_rule('^visitor/([^/]+)/?$', 'index.php?saw_route=visitor&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^visitor/([^/]+)/(.+)', 'index.php?saw_route=visitor&saw_path=$matches[1]/$matches[2]', 'top');
    }
    
    /**
     * Register query variables
     *
     * Makes SAW query vars available to WordPress.
     * Uses single query var for sidebar context to avoid conflicts.
     *
     * @since 1.0.0
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function register_query_vars($vars) {
	    $vars[] = 'saw_route';
	    $vars[] = 'saw_path';
	    $vars[] = 'saw_action';
	    $vars[] = 'saw_page';
	    $vars[] = 'saw_sidebar_context';
    return $vars;
}
    
    /**
     * Parse sidebar context from URL segments
     *
     * Analyzes URL segments to determine sidebar state:
     * - /admin/customers/          → null (list only)
     * - /admin/customers/5/        → detail mode
     * - /admin/customers/5/branches → detail mode with tab
     * - /admin/customers/create    → create form mode
     * - /admin/customers/5/edit    → edit form mode
     * - /admin/customers/edit/5    → edit form mode (alternative)
     *
     * @since 7.0.0
     * @param array $segments URL path segments after module route
     * @return array Sidebar context array or empty array
     */
    private function parse_sidebar_context($segments) {
        $context = array(
            'mode' => null,
            'id' => 0,
            'tab' => 'overview',
        );
        
        if (empty($segments) || empty($segments[0])) {
            return $context;
        }
        
        if ($segments[0] === 'create' || $segments[0] === 'new') {
            $context['mode'] = 'create';
            return $context;
        }
        
        if (($segments[0] === 'edit' || $segments[0] === 'upravit') && !empty($segments[1]) && is_numeric($segments[1])) {
            $context['mode'] = 'edit';
            $context['id'] = intval($segments[1]);
            return $context;
        }
        
        if (is_numeric($segments[0])) {
            $id = intval($segments[0]);
            
            if (isset($segments[1]) && ($segments[1] === 'edit' || $segments[1] === 'upravit')) {
                $context['mode'] = 'edit';
                $context['id'] = $id;
                return $context;
            }
            
            $context['mode'] = 'detail';
            $context['id'] = $id;
            
            if (isset($segments[1]) && !empty($segments[1])) {
                $context['tab'] = sanitize_key($segments[1]);
            }
            
            return $context;
        }
        
        return array(
            'mode' => null,
            'id' => 0,
            'tab' => 'overview',
        );
    }
    
    /**
     * Dispatch SAW routes
     *
     * CRITICAL: Never dispatches WordPress AJAX requests.
     *
     * @since 1.0.0
     * @param string $route Route name
     * @param string $path  Route path
     * @return void
     */
    public function dispatch($route = '', $path = '') {
        // Don't dispatch WordPress AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        if (empty($route)) {
            $route = get_query_var('saw_route');
        }
        
        if (empty($path)) {
            $path = get_query_var('saw_path');
        }
        
        if (empty($route)) {
            return;
        }
        
        $this->load_frontend_components();
        
        switch ($route) {
            case 'auth':
                $this->handle_auth_route();
                break;
            
            case 'admin':
                $this->handle_admin_route($path);
                break;
                
            case 'manager':
                $this->handle_manager_route($path);
                break;
                
            case 'terminal':
                $this->handle_terminal_route($path);
                break;
                
            case 'visitor':
                $this->handle_visitor_route($path);
                break;
            
            default:
                $this->handle_404();
                break;
        }
        
        exit;
    }
    
    /**
     * Handle authentication routes
     *
     * Processes login, logout, password set/reset actions.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_auth_route() {
        $action = get_query_var('saw_action');
        
        switch ($action) {
            case 'login':
                $this->render_auth_page('login');
                break;
                
            case 'set-password':
                $this->render_auth_page('set-password');
                break;
                
            case 'reset-password':
                $this->render_auth_page('reset-password');
                break;
                
            case 'logout':
                wp_logout();
                wp_redirect(home_url('/login/'));
                exit;
                
            default:
                $this->handle_404();
                break;
        }
    }
    
    /**
     * Render authentication page
     *
     * @since 1.0.0
     * @param string $page Page template name
     * @return void
     */
    private function render_auth_page($page) {
        $template = SAW_VISITORS_PLUGIN_DIR . 'templates/auth/' . $page . '.php';
        
        if (file_exists($template)) {
            require $template;
            exit;
        } else {
            wp_die('Template not found: ' . $template);
        }
    }
    
    /**
     * Get active module from current path
     *
     * @since 1.0.0
     * @return string|null Module slug or null
     */
    public function get_active_module() {
        $path = get_query_var('saw_path');
        
        if (empty($path)) {
            return 'dashboard';
        }
        
        $clean_path = trim($path, '/');
        $modules = SAW_Module_Loader::get_all();
        
        // CRITICAL FIX: Extract module name from path even if it contains ID
        // e.g., "companies/37" -> "companies", "branches/5/edit" -> "branches"
        $path_segments = explode('/', $clean_path);
        $module_candidate = $path_segments[0] ?? '';
        
        // First, try to match exact module route
        foreach ($modules as $slug => $config) {
            $module_route = ltrim($config['route'] ?? '', '/');
            $module_route_without_admin = str_replace('admin/', '', $module_route);
            
            // Match if path starts with module route (e.g., "companies" or "companies/37")
            if ($module_route_without_admin === $clean_path || 
                strpos($clean_path . '/', $module_route_without_admin . '/') === 0) {
                return $slug;
            }
            
            // Also check if first segment matches module route
            if ($module_route_without_admin === $module_candidate) {
                return $slug;
            }
        }
        
        // Fallback: return first segment if it looks like a module name
        if (!empty($module_candidate) && !is_numeric($module_candidate)) {
            return $module_candidate;
        }
        
        return null;
    }
    
    /**
     * Dispatch module controller
     *
     * Loads and executes appropriate controller method based on URL segments.
     * Parses sidebar context and stores it in query var for controller access.
     *
     * @since 1.0.0
     * @param string $slug     Module slug
     * @param array  $segments URL path segments
     * @return void
     */
    private function dispatch_module($slug, $segments) {
    SAW_Logger::debug("Dispatching module", [
        'slug' => $slug,
        'segments' => $segments
    ]);
    
    $config = SAW_Module_Loader::load($slug);
    
    SAW_Logger::debug("Config loaded", ['config_exists' => ($config ? 'YES' : 'NO')]);
    
    if (!$config) {
        SAW_Logger::error("Config not found for module", ['slug' => $slug]);
        $this->handle_404();
        return;
    }
    
    SAW_Logger::debug("Building class name from slug", ['slug' => $slug]);
    
    $parts = explode('-', $slug);
    $parts = array_map('ucfirst', $parts);
    $class_name = implode('_', $parts);
    $controller_class = 'SAW_Module_' . $class_name . '_Controller';
    
    SAW_Logger::debug("Controller class", ['class' => $controller_class]);
    
    if (!class_exists($controller_class)) {
        SAW_Logger::error("Controller class not found", ['class' => $controller_class, 'slug' => $slug]);
        wp_die('Controller class not found: ' . $controller_class . '<br>Slug: ' . $slug . '<br>Check if controller.php exists and class name matches.');
        return;
    }
    
    SAW_Logger::debug("Creating controller instance...");
    
    try {
        $controller = new $controller_class();
        SAW_Logger::debug("Controller created successfully");
    } catch (Exception $e) {
        SAW_Logger::error("Error creating controller", [
            'message' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString()
        ]);
        wp_die('Error creating controller: ' . $e->getMessage());
        return;
    }
    
    SAW_Logger::debug("Parsing sidebar context...");
    
    $sidebar_context = $this->parse_sidebar_context($segments);
    set_query_var('saw_sidebar_context', $sidebar_context);
    
    SAW_Logger::debug("Sidebar context", ['context' => $sidebar_context]);
    
    if (!method_exists($controller, 'index')) {
        SAW_Logger::error("index() method not found in controller", ['class' => $controller_class]);
        wp_die('Controller does not have index() method: ' . $controller_class);
        return;
    }
    
    if ($sidebar_context['mode'] === null && !empty($segments[0])) {
        if ($segments[0] === 'create' || $segments[0] === 'new') {
            if (method_exists($controller, 'create')) {
                SAW_Logger::debug("Calling create() method");
                $controller->create();
                return;
            }
        }
        
        if (($segments[0] === 'edit' || $segments[0] === 'upravit') && !empty($segments[1])) {
            if (method_exists($controller, 'edit')) {
                SAW_Logger::debug("Calling edit() method");
                $controller->edit(intval($segments[1]));
                return;
            }
        }
    }
    
    SAW_Logger::debug("Calling index() method");
    
    try {
        $controller->index();
        SAW_Logger::debug("index() completed successfully");
    } catch (Throwable $e) {
        SAW_Logger::error("Error in index() method", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}
    
    /**
     * Handle admin routes
     *
     * Processes admin interface routing with authentication check.
     *
     * @since 1.0.0
     * @param string $path URL path
     * @return void
     */
    private function handle_admin_route($path) {
    SAW_Logger::debug("Handling admin route", ['path' => $path]);
    
    if (!$this->is_logged_in()) {
        SAW_Logger::debug("Not logged in - redirecting to login");
        $this->redirect_to_login('admin');
        return;
    }
    
    SAW_Logger::debug("User logged in");
    
    $clean_path = trim($path, '/');
    SAW_Logger::debug("Clean path", ['clean_path' => $clean_path]);

$saw_page = get_query_var('saw_page');
if ($saw_page === 'dashboard') {
    SAW_Logger::debug("Dashboard page detected - loading dashboard");
    // Load dashboard class
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/dashboard/dashboard.php';
    SAW_Frontend_Dashboard::render_dashboard();
    exit;
}
    
    if (empty($clean_path)) {
        SAW_Logger::debug("Empty path - rendering dashboard");
        $this->render_page('Admin Dashboard', $path, 'admin', 'dashboard');
        return;
    }
    
    $modules = SAW_Module_Loader::get_all();
    SAW_Logger::debug("Loaded modules", ['count' => count($modules)]);
    
    // CRITICAL FIX: Check modules FIRST before treating as admin dashboard
    foreach ($modules as $slug => $config) {
        SAW_Logger::debug("Checking module", ['slug' => $slug]);
        
        $module_route = ltrim($config['route'] ?? '', '/');
        $module_route_without_admin = str_replace('admin/', '', $module_route);
        
        SAW_Logger::debug("Module route", ['module_route' => $module_route_without_admin]);
        
        // Match if path starts with module route
        if ($module_route_without_admin === $clean_path || 
            strpos($clean_path . '/', $module_route_without_admin . '/') === 0) {
            
            SAW_Logger::debug("MATCH! Dispatching to module", ['slug' => $slug]);
            
            $route_parts = explode('/', $module_route_without_admin);
            $path_parts = explode('/', $clean_path);
            $remaining_segments = array_slice($path_parts, count($route_parts));
            
            SAW_Logger::debug("Segments", ['segments' => $remaining_segments]);
            
            $this->dispatch_module($slug, $remaining_segments);
            return;
        }
    }
    
    SAW_Logger::debug("No module matched - continuing...");
    
    // CRITICAL FIX: If path is numeric ID, it's likely a detail/edit for last visited module
    // This handles shortcuts like /admin/19/edit -> /admin/customers/19/edit
    $segments = explode('/', $clean_path);
    if (is_numeric($segments[0])) {
        // Default to customers module for numeric IDs (can be made smarter with session)
        $customers_config = SAW_Module_Loader::load('customers');
        if ($customers_config) {
            $this->dispatch_module('customers', $segments);
            return;
        }
    }
    

    
    // Default admin interface
    $active_section = isset($segments[0]) ? $segments[0] : '';
    $this->render_page('Admin Interface', $path, 'admin', $active_section);
}
    
    /**
     * Handle manager routes
     *
     * @since 1.0.0
     * @param string $path URL path
     * @return void
     */
    private function handle_manager_route($path) {
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('manager');
            return;
        }
        
        $this->render_page('Manager Interface', $path, 'manager', '');
    }
    
    /**
     * Handle terminal routes
     *
     * @since 1.0.0
     * @param string $path URL path
     * @return void
     */
    private function handle_terminal_route($path) {
        // Terminal - vyžaduje přihlášení
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('terminal');
            return;
        }
    
        $handler = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal-route-handler.php';
    
        if (file_exists($handler)) {
            require_once $handler;
            exit;
        } else {
            wp_die('Terminal handler not found: ' . $handler);
        }
    }
    
    /**
     * Handle visitor routes
     *
     * @since 1.0.0
     * @param string $path URL path
     * @return void
     */
    private function handle_visitor_route($path) {
        $this->render_page('Visitor Portal', $path, 'visitor', '');
    }
    
    /**
     * Render page with layout
     *
     * @since 1.0.0
     * @param string $title       Page title
     * @param string $path        Current path
     * @param string $route       Route name
     * @param string $active_menu Active menu item
     * @return void
     */
    private function render_page($title, $path, $route, $active_menu = '') {
        $user = $this->get_current_user_data();
        $customer = $this->get_current_customer_data();
        
        ob_start();
        ?>
        <div class="saw-page-wrapper">
            <div class="saw-page-header">
                <h1>🎯 <?php echo esc_html($title); ?></h1>
                <p class="saw-page-subtitle">Stránka ve vývoji</p>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h2>✅ Autentizace funguje!</h2>
                </div>
                <div class="saw-card-body">
                    <div class="saw-alert saw-alert-success">
                        <strong>🔒 Úspěšně přihlášen!</strong><br>
                        Tuto stránku vidíš, protože jsi prošel autentizační kontrolou.
                    </div>
                    
                    <table class="saw-info-table" style="margin-top: 16px;">
                        <tr>
                            <th>Route:</th>
                            <td><code><?php echo esc_html($route); ?></code></td>
                        </tr>
                        <tr>
                            <th>Path:</th>
                            <td><code><?php echo esc_html($path ?: '/'); ?></code></td>
                        </tr>
                        <tr>
                            <th>User:</th>
                            <td><code><?php echo esc_html($user['name']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Role:</th>
                            <td><code><?php echo esc_html($user['role']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Customer:</th>
                            <td><code><?php echo esc_html($customer['name']); ?></code></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, $title, $active_menu, $user, $customer);
        } else {
            echo $content;
        }
    }
    
    /**
     * Handle 404 errors
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_404() {
        $this->render_page('404 - Stránka nenalezena', '404', 'error', '');
    }
    
    /**
     * Load frontend components
     *
     * @since 1.0.0
     * @return void
     */
    private function load_frontend_components() {
        $components = array(
            'class-saw-app-layout.php',
            'class-saw-app-header.php',
            'class-saw-app-sidebar.php',
            'class-saw-app-footer.php',
        );
        
        foreach ($components as $component) {
            $file = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/' . $component;
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
    
    /**
     * Check if user is logged in
     *
     * @since 1.0.0
     * @return bool
     */
    private function is_logged_in() {
        return is_user_logged_in();
    }
    
    /**
     * Redirect to login page
     *
     * @since 1.0.0
     * @param string $route Original route attempting to access
     * @return void
     */
    private function redirect_to_login($route = 'admin') {
        wp_redirect(home_url('/login/'));
        exit;
    }
    
    /**
     * Get current user data
     *
     * @since 1.0.0
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
                'role' => 'admin',
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
     * Get current customer data
     *
     * ✅ FIX v7.3.0: Fixed duplicate base URL in logo_url_full
     *
     * @since 1.0.0
     * @return array Customer data array
     */
    private function get_current_customer_data() {
        $customer_id = null;
        
        // Try SAW_Context first to get customer ID
        if (class_exists('SAW_Context')) {
            $customer_basic = SAW_Context::get_customer_data();
            if ($customer_basic && isset($customer_basic['id'])) {
                $customer_id = $customer_basic['id'];
            }
        }
        
        // If we have customer_id, load FULL data from database
        if ($customer_id) {
            global $wpdb;
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, ico, logo_url FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_customers',
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                $logo_url_full = '';
                
                if (!empty($customer['logo_url'])) {
                    // Check if already full URL (starts with http)
                    if (strpos($customer['logo_url'], 'http') === 0) {
                        $logo_url_full = $customer['logo_url'];
                    } else {
                        // Relative path - build full URL
                        $logo_url_full = wp_get_upload_dir()['baseurl'] . '/' . ltrim($customer['logo_url'], '/');
                    }
                }
                
                return array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'logo_url' => $customer['logo_url'] ?? '',
                    'logo_url_full' => $logo_url_full,
                );
            }
        }
        
        // Fallback: Load first active customer
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, ico, logo_url FROM %i WHERE status = 'active' ORDER BY id ASC LIMIT 1",
            $wpdb->prefix . 'saw_customers'
        ), ARRAY_A);
        
        if ($customer) {
            $logo_url_full = '';
            
            if (!empty($customer['logo_url'])) {
                // Check if already full URL (starts with http)
                if (strpos($customer['logo_url'], 'http') === 0) {
                    $logo_url_full = $customer['logo_url'];
                } else {
                    // Relative path - build full URL
                    $logo_url_full = wp_get_upload_dir()['baseurl'] . '/' . ltrim($customer['logo_url'], '/');
                }
            }
            
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'logo_url' => $customer['logo_url'] ?? '',
                'logo_url_full' => $logo_url_full,
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'logo_url' => '',
            'logo_url_full' => '',
        );
    }
}