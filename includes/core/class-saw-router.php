<?php
/**
 * SAW Router - KOMPLETNÍ OPRAVENÁ VERZE
 * 
 * ✅ ZACHOVÁNO: Vše co fungovalo
 * ✅ PŘIDÁNO: SAW_Context inicializace na začátku
 * ✅ OPRAVENO: get_current_customer_data() používá SAW_Context
 * 
 * @package SAW_Visitors
 * @version 2.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    public function register_routes() {
        // AUTENTIZAČNÍ ROUTY
        add_rewrite_rule('^login/?$', 'index.php?saw_route=auth&saw_action=login', 'top');
        add_rewrite_rule('^set-password/?$', 'index.php?saw_route=auth&saw_action=set-password', 'top');
        add_rewrite_rule('^reset-password/?$', 'index.php?saw_route=auth&saw_action=reset-password', 'top');
        add_rewrite_rule('^logout/?$', 'index.php?saw_route=auth&saw_action=logout', 'top');
        
        // ADMIN ROUTY
        add_rewrite_rule('^admin/?$', 'index.php?saw_route=admin', 'top');
        add_rewrite_rule('^admin/([^/]+)/?$', 'index.php?saw_route=admin&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^admin/([^/]+)/(.+)', 'index.php?saw_route=admin&saw_path=$matches[1]/$matches[2]', 'top');
        
        // MANAGER ROUTY
        add_rewrite_rule('^manager/?$', 'index.php?saw_route=manager', 'top');
        add_rewrite_rule('^manager/([^/]+)/?$', 'index.php?saw_route=manager&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^manager/([^/]+)/(.+)', 'index.php?saw_route=manager&saw_path=$matches[1]/$matches[2]', 'top');
        
        // TERMINAL ROUTY
        add_rewrite_rule('^terminal/?$', 'index.php?saw_route=terminal', 'top');
        add_rewrite_rule('^terminal/([^/]+)/?$', 'index.php?saw_route=terminal&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^terminal/([^/]+)/(.+)', 'index.php?saw_route=terminal&saw_path=$matches[1]/$matches[2]', 'top');
        
        // VISITOR ROUTY
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

 // ✅ KRITICKÁ OPRAVA: Pro AJAX requesty NIC NEDĚLEJ!
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
    
    // ✅ Inicializuj SAW_Context HNED na začátku!
    if (class_exists('SAW_Context') && is_user_logged_in()) {
        SAW_Context::instance();
    }
    
    $this->load_frontend_components();


        
if (empty($route)) {
            $route = get_query_var('saw_route');
        }
        
        if (empty($path)) {
            $path = get_query_var('saw_path');
        }
        
        if (empty($route)) {
            return;
        }
        
        // ✅ KRITICKÁ OPRAVA: Inicializuj SAW_Context HNED na začátku!
        if (class_exists('SAW_Context') && is_user_logged_in()) {
            SAW_Context::instance();
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
            ob_start();
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title><?php echo esc_html(ucfirst(str_replace('-', ' ', $page))); ?></title>
                <style>
                    body {
                        margin: 0;
                        padding: 50px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        background: #f3f4f6;
                        text-align: center;
                    }
                    .container {
                        max-width: 500px;
                        margin: 0 auto;
                        background: white;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    }
                    h1 { color: #111827; }
                    .error {
                        background: #fee2e2;
                        color: #991b1b;
                        padding: 16px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>⚠️ Template nenalezen</h1>
                    <div class="error">
                        <strong>Chyba:</strong> Template soubor nebyl nalezen:<br>
                        <code><?php echo esc_html($template); ?></code>
                    </div>
                    <p><a href="<?php echo home_url('/'); ?>">← Zpět na hlavní stránku</a></p>
                </div>
            </body>
            </html>
            <?php
            echo ob_get_clean();
            exit;
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
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            $this->handle_404();
            return;
        }
        
        $controller_class = 'SAW_Module_' . ucfirst(str_replace('-', '_', $slug)) . '_Controller';
        
        if (!class_exists($controller_class)) {
            $this->handle_404();
            return;
        }
        
        $controller = new $controller_class();
        
        if (empty($segments[0])) {
            $controller->index();
        } elseif ($segments[0] === 'create' || $segments[0] === 'new') {
            $controller->create();
        } elseif (($segments[0] === 'edit' || $segments[0] === 'upravit') && !empty($segments[1])) {
            $controller->edit(intval($segments[1]));
        } else {
            $this->handle_404();
        }
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
                            <th>Customer ID:</th>
                            <td><code><?php echo esc_html($customer['id']); ?></code></td>
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
    
    private function handle_404() {
        $this->render_page('404 - Stránka nenalezena', '404', 'error', '');
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
                'customer_id' => null,
                'branch_id' => null,
            );
        }
        
        return array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
            'customer_id' => null,
            'branch_id' => null,
        );
    }
    
    private function get_current_customer_data() {
        // ✅ OPRAVA: Použij SAW_Context jako prioritu
        if (class_exists('SAW_Context')) {
            $customer = SAW_Context::get_customer_data();
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
        
        global $wpdb;
        
        if (!is_user_logged_in()) {
            return array(
                'id' => 0,
                'name' => 'Žádný zákazník',
                'ico' => '',
                'address' => '',
                'logo_url' => '',
            );
        }
        
        $wp_user = wp_get_current_user();
        
        // Super Admin - ze session
        if (current_user_can('manage_options')) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $customer_id = null;
            
            if (isset($_SESSION['saw_current_customer_id'])) {
                $customer_id = intval($_SESSION['saw_current_customer_id']);
            }
            
            if (!$customer_id) {
                $customer_id = get_user_meta($wp_user->ID, 'saw_current_customer_id', true);
                if ($customer_id) {
                    $customer_id = intval($customer_id);
                }
            }
            
            if ($customer_id) {
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
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
        }
        
        // Admin/Manager - z jejich přiřazeného zákazníka
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
            $wp_user->ID
        ), ARRAY_A);
        
        if ($saw_user && !empty($saw_user['customer_id'])) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                $saw_user['customer_id']
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
        
        // Fallback - první zákazník
        $customer = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['saw_current_customer_id'] = intval($customer['id']);
            update_user_meta($wp_user->ID, 'saw_current_customer_id', intval($customer['id']));
            
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => $customer['address'] ?? '',
                'logo_url' => $customer['logo_url'] ?? '',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        );
    }
}