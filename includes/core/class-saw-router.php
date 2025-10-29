<?php
/**
 * SAW Router
 * Handles routing for all application routes
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    /**
     * Register rewrite rules
     */
    public function register_routes() {
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
     * Register query vars
     */
    public function register_query_vars($vars) {
        $vars[] = 'saw_route';
        $vars[] = 'saw_path';
        return $vars;
    }
    
    /**
     * Dispatch routing
     */
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
     * Load frontend components
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
     */
    private function is_logged_in() {
        return is_user_logged_in();
    }
    
    /**
     * Redirect to login
     */
    private function redirect_to_login($route = 'admin') {
        $login_url = '/' . $route . '/login/';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="refresh" content="0;url=<?php echo esc_url($login_url); ?>">
            <title>Přesměrování na přihlášení...</title>
        </head>
        <body>
            <div style="text-align: center; padding: 50px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                <h1>🔒 Přesměrování...</h1>
                <p>Prosím počkejte, přesměrovávám vás na přihlašovací stránku.</p>
                <p><a href="<?php echo esc_url($login_url); ?>">Klikněte zde, pokud se stránka nenačte automaticky</a></p>
            </div>
            <script>window.location.href = '<?php echo esc_js($login_url); ?>';</script>
        </body>
        </html>
        <?php
        echo ob_get_clean();
        exit;
    }
    
    /**
     * Handle admin routes
     */
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
        
        $segments = explode('/', $clean_path);
        
        if ($segments[0] === 'settings' && isset($segments[1]) && $segments[1] === 'customers') {
            $this->handle_customers_routes($segments);
            return;
        }
        
        if ($segments[0] === 'settings' && isset($segments[1]) && $segments[1] === 'account-types') {
            $this->handle_account_types_routes($segments);
            return;
        }
        
        if ($segments[0] === 'settings' && isset($segments[1])) {
            $this->render_page('Settings: ' . ucfirst($segments[1]), $path, 'admin', 'settings');
            return;
        }
        
        $this->render_page('Admin Interface', $path, 'admin', '');
    }
    
    /**
     * Handle customers routes
     */
    private function handle_customers_routes($segments) {
        $controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-controller-customers.php';
        
        if (!file_exists($controller_file)) {
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Router: Customers controller not found at: ' . $controller_file);
            }
            $this->handle_404();
            return;
        }
        
        require_once $controller_file;
        $controller = new SAW_Controller_Customers();
        
        if (count($segments) === 2) {
            $controller->index();
            return;
        }
        
        if (count($segments) === 3 && $segments[2] === 'new') {
            $controller->create();
            return;
        }
        
        if (count($segments) === 4 && $segments[2] === 'edit') {
            $customer_id = intval($segments[3]);
            if ($customer_id > 0) {
                $controller->edit($customer_id);
                return;
            }
        }
        
        $this->handle_404();
    }
    
    /**
     * Handle account types routes
     */
    private function handle_account_types_routes($segments) {
        $controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-controller-account-types.php';
        
        if (!file_exists($controller_file)) {
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Router: Account Types controller not found at: ' . $controller_file);
            }
            $this->handle_404();
            return;
        }
        
        require_once $controller_file;
        $controller = new SAW_Controller_Account_Types();
        
        if (count($segments) === 2) {
            $controller->index();
            return;
        }
        
        if (count($segments) === 3 && $segments[2] === 'new') {
            $controller->create();
            return;
        }
        
        if (count($segments) === 4 && $segments[2] === 'edit') {
            $account_type_id = intval($segments[3]);
            if ($account_type_id > 0) {
                $controller->edit($account_type_id);
                return;
            }
        }
        
        $this->handle_404();
    }
    
    /**
     * Handle manager routes
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
     */
    private function handle_visitor_route($path) {
        $this->render_page('Visitor Portal', $path, 'visitor', '');
    }
    
    /**
     * Render page using layout component
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
                        Tuto stránku vidíš, protože jsi prošel autentizační kontrolou.<br>
                        Zkus otevřít v anonymním okně - měl bys vidět login screen.
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
                            <td><code><?php echo esc_html($user['name'] . ' (' . $user['email'] . ')'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Role:</th>
                            <td><code><?php echo esc_html($user['role']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Customer:</th>
                            <td><code><?php echo esc_html($customer['name']); ?></code></td>
                        </tr>
                        <tr>
                            <th>WordPress User:</th>
                            <td><?php echo is_user_logged_in() ? '✅ Ano' : '❌ Ne'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h3>🚀 Quick Links</h3>
                </div>
                <div class="saw-card-body">
                    <div class="saw-button-group">
                        <a href="/admin/" class="saw-button saw-button-primary">Admin Dashboard</a>
                        <a href="/admin/invitations" class="saw-button saw-button-primary">Pozvánky</a>
                        <a href="/admin/visits" class="saw-button saw-button-primary">Návštěvy</a>
                        <a href="/admin/statistics" class="saw-button saw-button-primary">Statistiky</a>
                        <a href="/admin/settings/customers" class="saw-button saw-button-success">👥 Správa zákazníků</a>
                        <a href="/admin/settings/account-types" class="saw-button saw-button-success">💳 Account Types</a>
                    </div>
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
     * Handle 404
     */
    private function handle_404() {
        $this->render_page('404 - Stránka nenalezena', '404', 'error', '');
    }
    
    /**
     * Get current user data
     */
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => 'admin',
            );
        }
        
        return array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
        );
    }
    
    /**
     * Get current customer data
     */
    private function get_current_customer_data() {
        return array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'address' => 'Praha 1, Hlavní 123',
        );
    }
}