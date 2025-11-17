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
    // ✅ DEBUG
    $log = WP_CONTENT_DIR . '/saw-dispatch-debug.log';
    file_put_contents($log, "\n" . date('H:i:s') . " ==================\n", FILE_APPEND);
    file_put_contents($log, "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n", FILE_APPEND);
    file_put_contents($log, "is_ajax: " . (wp_doing_ajax() ? 'YES' : 'NO') . "\n", FILE_APPEND);
    
    if (wp_doing_ajax()) {
        file_put_contents($log, "AJAX - skipping dispatch\n", FILE_APPEND);
        return;
    }
    
    if (empty($route)) {
        $route = get_query_var('saw_route');
        file_put_contents($log, "Route from query_var: '$route'\n", FILE_APPEND);
    } else {
        file_put_contents($log, "Route from param: '$route'\n", FILE_APPEND);
    }
    
    if (empty($path)) {
        $path = get_query_var('saw_path');
        file_put_contents($log, "Path from query_var: '$path'\n", FILE_APPEND);
    } else {
        file_put_contents($log, "Path from param: '$path'\n", FILE_APPEND);
    }
    
    if (empty($route)) {
        file_put_contents($log, "Empty route - exiting dispatch\n", FILE_APPEND);
        return;
    }
    
    file_put_contents($log, "Loading frontend components\n", FILE_APPEND);
    $this->load_frontend_components();
    
    file_put_contents($log, "Switching on route: '$route'\n", FILE_APPEND);
    
    switch ($route) {
        case 'auth':
            file_put_contents($log, "-> handle_auth_route()\n", FILE_APPEND);
            $this->handle_auth_route();
            break;
        
        case 'admin':
            file_put_contents($log, "-> handle_admin_route('$path')\n", FILE_APPEND);
            $this->handle_admin_route($path);
            break;
            
        case 'manager':
            file_put_contents($log, "-> handle_manager_route()\n", FILE_APPEND);
            $this->handle_manager_route($path);
            break;
            
        case 'terminal':
            file_put_contents($log, "-> handle_terminal_route()\n", FILE_APPEND);
            $this->handle_terminal_route($path);
            break;
            
        case 'visitor':
            file_put_contents($log, "-> handle_visitor_route()\n", FILE_APPEND);
            $this->handle_visitor_route($path);
            break;
            
        default:
            file_put_contents($log, "-> handle_404() - unknown route\n", FILE_APPEND);
            $this->handle_404();
            break;
    }
    
    file_put_contents($log, "Exiting dispatch\n", FILE_APPEND);
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
        
        foreach ($modules as $slug => $config) {
            $module_route = ltrim($config['route'] ?? '', '/');
            $module_route_without_admin = str_replace('admin/', '', $module_route);
            
            if ($module_route_without_admin === $clean_path || strpos($clean_path . '/', $module_route_without_admin . '/') === 0) {
                return $slug;
            }
        }
        
        $segments = explode('/', $clean_path);
        if (isset($segments[0])) {
            return $segments[0];
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
    // DEBUG
    $log = WP_CONTENT_DIR . '/dispatch-module.log';
    file_put_contents($log, "\n" . date('H:i:s') . " ==================\n", FILE_APPEND);
    file_put_contents($log, "Dispatching module: $slug\n", FILE_APPEND);
    file_put_contents($log, "Segments: " . print_r($segments, true) . "\n", FILE_APPEND);
    
    $config = SAW_Module_Loader::load($slug);
    
    file_put_contents($log, "Config loaded: " . ($config ? 'YES' : 'NO') . "\n", FILE_APPEND);
    
    if (!$config) {
        file_put_contents($log, "ERROR: Config not found\n", FILE_APPEND);
        $this->handle_404();
        return;
    }
    
    file_put_contents($log, "Building class name from slug: $slug\n", FILE_APPEND);
    
    $parts = explode('-', $slug);
    $parts = array_map('ucfirst', $parts);
    $class_name = implode('_', $parts);
    $controller_class = 'SAW_Module_' . $class_name . '_Controller';
    
    file_put_contents($log, "Controller class: $controller_class\n", FILE_APPEND);
    
    if (!class_exists($controller_class)) {
        file_put_contents($log, "ERROR: Controller class not found\n", FILE_APPEND);
        wp_die('Controller class not found: ' . $controller_class . '<br>Slug: ' . $slug . '<br>Check if controller.php exists and class name matches.');
        return;
    }
    
    file_put_contents($log, "Creating controller instance...\n", FILE_APPEND);
    
    try {
        $controller = new $controller_class();
        file_put_contents($log, "Controller created successfully\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents($log, "ERROR creating controller: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($log, "Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
        wp_die('Error creating controller: ' . $e->getMessage());
        return;
    }
    
    file_put_contents($log, "Parsing sidebar context...\n", FILE_APPEND);
    
    $sidebar_context = $this->parse_sidebar_context($segments);
    set_query_var('saw_sidebar_context', $sidebar_context);
    
    file_put_contents($log, "Sidebar context: " . print_r($sidebar_context, true) . "\n", FILE_APPEND);
    
    if (!method_exists($controller, 'index')) {
        file_put_contents($log, "ERROR: index() method not found\n", FILE_APPEND);
        wp_die('Controller does not have index() method: ' . $controller_class);
        return;
    }
    
    if ($sidebar_context['mode'] === null && !empty($segments[0])) {
        if ($segments[0] === 'create' || $segments[0] === 'new') {
            if (method_exists($controller, 'create')) {
                file_put_contents($log, "Calling create() method\n", FILE_APPEND);
                $controller->create();
                return;
            }
        }
        
        if (($segments[0] === 'edit' || $segments[0] === 'upravit') && !empty($segments[1])) {
            if (method_exists($controller, 'edit')) {
                file_put_contents($log, "Calling edit() method\n", FILE_APPEND);
                $controller->edit(intval($segments[1]));
                return;
            }
        }
    }
    
    file_put_contents($log, "Calling index() method\n", FILE_APPEND);
    
    try {
        $controller->index();
        file_put_contents($log, "index() completed successfully\n", FILE_APPEND);
    } catch (Throwable $e) {
        file_put_contents($log, "ERROR in index(): " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($log, "File: " . $e->getFile() . "\n", FILE_APPEND);
        file_put_contents($log, "Line: " . $e->getLine() . "\n", FILE_APPEND);
        file_put_contents($log, "Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
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
    // ✅ PŘIDEJ DEBUG
    $log = WP_CONTENT_DIR . '/saw-router-debug.log';
    file_put_contents($log, "\n" . date('H:i:s') . " ==================\n", FILE_APPEND);
    file_put_contents($log, "Path: $path\n", FILE_APPEND);
    
    if (!$this->is_logged_in()) {
        file_put_contents($log, "Not logged in - redirecting\n", FILE_APPEND);
        $this->redirect_to_login('admin');
        return;
    }
    
    file_put_contents($log, "User logged in\n", FILE_APPEND);
    
    $clean_path = trim($path, '/');
    file_put_contents($log, "Clean path: $clean_path\n", FILE_APPEND);

$saw_page = get_query_var('saw_page');
if ($saw_page === 'dashboard') {
    file_put_contents($log, "Dashboard page detected - loading dashboard\n", FILE_APPEND);
    // Load dashboard class
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/dashboard/dashboard.php';
    SAW_Frontend_Dashboard::render_dashboard();
    exit;
}
    
    if (empty($clean_path)) {
        file_put_contents($log, "Empty path - rendering dashboard\n", FILE_APPEND);
        $this->render_page('Admin Dashboard', $path, 'admin', 'dashboard');
        return;
    }
    
    $modules = SAW_Module_Loader::get_all();
    file_put_contents($log, "Loaded " . count($modules) . " modules\n", FILE_APPEND);
    
    // CRITICAL FIX: Check modules FIRST before treating as admin dashboard
    foreach ($modules as $slug => $config) {
        file_put_contents($log, "Checking module: $slug\n", FILE_APPEND);
        
        $module_route = ltrim($config['route'] ?? '', '/');
        $module_route_without_admin = str_replace('admin/', '', $module_route);
        
        file_put_contents($log, "  Module route: $module_route_without_admin\n", FILE_APPEND);
        
        // Match if path starts with module route
        if ($module_route_without_admin === $clean_path || 
            strpos($clean_path . '/', $module_route_without_admin . '/') === 0) {
            
            file_put_contents($log, "  MATCH! Dispatching to module\n", FILE_APPEND);
            
            $route_parts = explode('/', $module_route_without_admin);
            $path_parts = explode('/', $clean_path);
            $remaining_segments = array_slice($path_parts, count($route_parts));
            
            file_put_contents($log, "  Segments: " . print_r($remaining_segments, true) . "\n", FILE_APPEND);
            
            $this->dispatch_module($slug, $remaining_segments);
            return;
        }
    }
    
    file_put_contents($log, "No module matched - continuing...\n", FILE_APPEND);
    
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
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('terminal');
            return;
        }
        
        $this->render_page('Terminal Interface', $path, 'terminal', '');
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