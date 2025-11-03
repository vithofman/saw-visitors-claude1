<?php
/**
 * SAW Router - FIXED VERSION v5.1.0
 * 
 * @package SAW_Visitors
 * @version 5.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    public function register_routes() {
        add_rewrite_rule('^login/?$', 'index.php?saw_route=auth&saw_action=login', 'top');
        add_rewrite_rule('^set-password/?$', 'index.php?saw_route=auth&saw_action=set-password', 'top');
        add_rewrite_rule('^reset-password/?$', 'index.php?saw_route=auth&saw_action=reset-password', 'top');
        add_rewrite_rule('^logout/?$', 'index.php?saw_route=auth&saw_action=logout', 'top');
        
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
    
    public function register_query_vars($vars) {
        $vars[] = 'saw_route';
        $vars[] = 'saw_path';
        $vars[] = 'saw_action';
        return $vars;
    }
    
    public function dispatch($route = '', $path = '') {
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
    
    private function render_auth_page($page) {
        $template = SAW_VISITORS_PLUGIN_DIR . 'templates/auth/' . $page . '.php';
        
        if (file_exists($template)) {
            require $template;
            exit;
        } else {
            wp_die('Template not found: ' . $template);
        }
    }
    
    public function get_active_module() {
        $path = get_query_var('saw_path');
        
        if (empty($path)) {
            return 'dashboard';
        }
        
        $modules = SAW_Module_Loader::get_all();
        
        foreach ($modules as $slug => $config) {
            $route = ltrim($config['route'] ?? '', '/');
            $route_without_admin = str_replace('admin/', '', $route);
            
            if (strpos($path, $route_without_admin) === 0) {
                return $slug;
            }
        }
        
        $segments = explode('/', trim($path, '/'));
        if (isset($segments[0])) {
            return $segments[0];
        }
        
        return null;
    }
    
    private function dispatch_module($slug, $segments) {
        error_log('=== DISPATCH_MODULE START ===');
        error_log('Slug: ' . $slug);
        error_log('Segments: ' . print_r($segments, true));
        
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            error_log('ERROR: Config not found for slug: ' . $slug);
            $this->handle_404();
            return;
        }
        
        error_log('Config loaded successfully');
        
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        error_log('Looking for controller class: ' . $controller_class);
        
        if (!class_exists($controller_class)) {
            error_log('ERROR: Controller class does not exist: ' . $controller_class);
            error_log('Available SAW_Module classes: ' . print_r(array_filter(get_declared_classes(), function($c) {
                return strpos($c, 'SAW_Module_') === 0;
            }), true));
            
            wp_die('Controller class not found: ' . $controller_class . '<br>Slug: ' . $slug . '<br>Check if controller.php exists and class name matches.');
            return;
        }
        
        error_log('Controller class found: ' . $controller_class);
        
        try {
            $controller = new $controller_class();
            error_log('Controller instance created successfully');
        } catch (Exception $e) {
            error_log('ERROR creating controller instance: ' . $e->getMessage());
            wp_die('Error creating controller: ' . $e->getMessage());
            return;
        }
        
        if (empty($segments[0])) {
            error_log('Calling index() method');
            
            if (!method_exists($controller, 'index')) {
                error_log('ERROR: index() method does not exist on controller');
                wp_die('Controller does not have index() method: ' . $controller_class);
                return;
            }
            
            $controller->index();
        } elseif ($segments[0] === 'create' || $segments[0] === 'new') {
            error_log('Calling create() method');
            
            if (!method_exists($controller, 'create')) {
                error_log('ERROR: create() method does not exist on controller');
                wp_die('Controller does not have create() method: ' . $controller_class);
                return;
            }
            
            $controller->create();
        } elseif (($segments[0] === 'edit' || $segments[0] === 'upravit') && !empty($segments[1])) {
            error_log('Calling edit(' . $segments[1] . ') method');
            
            if (!method_exists($controller, 'edit')) {
                error_log('ERROR: edit() method does not exist on controller');
                wp_die('Controller does not have edit() method: ' . $controller_class);
                return;
            }
            
            $controller->edit(intval($segments[1]));
        } else {
            error_log('ERROR: Unknown segment: ' . $segments[0]);
            $this->handle_404();
        }
        
        error_log('=== DISPATCH_MODULE END ===');
    }
    
    private function handle_admin_route($path) {
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('admin');
            return;
        }
        
        $clean_path = trim($path, '/');
        
        if (empty($clean_path)) {
            $this->render_page('Admin Dashboard', $path, 'admin', 'dashboard');
            return;
        }
        
        $modules = SAW_Module_Loader::get_all();
        
        foreach ($modules as $slug => $config) {
            $module_route = ltrim($config['route'] ?? '', '/');
            $module_route_without_admin = str_replace('admin/', '', $module_route);
            
            if (strpos($clean_path, $module_route_without_admin) === 0) {
                $route_parts = explode('/', $module_route_without_admin);
                $path_parts = explode('/', $clean_path);
                $remaining_segments = array_slice($path_parts, count($route_parts));
                
                $this->dispatch_module($slug, $remaining_segments);
                return;
            }
        }
        
        $segments = explode('/', $clean_path);
        
        if ($segments[0] === 'settings' && isset($segments[1])) {
            $this->render_page('Settings: ' . ucfirst($segments[1]), $path, 'admin', 'settings');
            return;
        }
        
        $active_section = isset($segments[0]) ? $segments[0] : '';
        $this->render_page('Admin Interface', $path, 'admin', $active_section);
    }
    
    private function handle_manager_route($path) {
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('manager');
            return;
        }
        
        $this->render_page('Manager Interface', $path, 'manager', '');
    }
    
    private function handle_terminal_route($path) {
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('terminal');
            return;
        }
        
        $this->render_page('Terminal Interface', $path, 'terminal', '');
    }
    
    private function handle_visitor_route($path) {
        $this->render_page('Visitor Portal', $path, 'visitor', '');
    }
    
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
    
    private function handle_404() {
        $this->render_page('404 - Stránka nenalezena', '404', 'error', '');
    }
    
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
    
    private function is_logged_in() {
        return is_user_logged_in();
    }
    
    private function redirect_to_login($route = 'admin') {
        wp_redirect(home_url('/login/'));
        exit;
    }
    
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
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
    
    private function get_current_customer_data() {
        if (class_exists('SAW_Context')) {
            $customer = SAW_Context::get_customer_data();
            if ($customer) {
                return array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'address' => $customer['address'] ?? '',
                    'logo_url' => $customer['logo_url'] ?? '',
                    'logo_url_full' => !empty($customer['logo_url']) ? wp_get_upload_dir()['baseurl'] . '/' . ltrim($customer['logo_url'], '/') : '',
                );
            }
        }
        
        global $wpdb;
        
        $customer = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}saw_customers WHERE status = 'active' ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => $customer['address'] ?? '',
                'logo_url' => $customer['logo_url'] ?? '',
                'logo_url_full' => !empty($customer['logo_url']) ? wp_get_upload_dir()['baseurl'] . '/' . ltrim($customer['logo_url'], '/') : '',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
            'logo_url_full' => '',
        );
    }
}