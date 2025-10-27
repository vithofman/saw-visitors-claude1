<?php
/**
 * SAW Router - DEBUG VERSION
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    public function dispatch($route, $path = '') {
        // Enable error reporting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        
        try {
            // Load frontend classes
            $this->load_frontend_classes();
            
            // Load controllers
            $this->load_controllers();
            
            // Set body class
            add_filter('body_class', function($classes) {
                $classes[] = 'saw-app-body';
                return $classes;
            });
            
            // Enqueue assets BEFORE template
            $this->enqueue_app_assets();
            
            // Force blank template
            add_filter('template_include', array($this, 'use_blank_template'), 999);
            
            // Route based on $route parameter
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
                    $this->show_404();
                    break;
            }
            
        } catch (Exception $e) {
            $this->show_error($e->getMessage());
        }
    }
    
    private function load_frontend_classes() {
        $files = array(
            'includes/frontend/class-saw-app-layout.php',
            'includes/frontend/class-saw-app-header.php',
            'includes/frontend/class-saw-app-sidebar.php',
            'includes/frontend/class-saw-app-footer.php',
        );
        
        foreach ($files as $file) {
            $path = SAW_VISITORS_PLUGIN_DIR . $file;
            if (!file_exists($path)) {
                throw new Exception("Frontend class not found: {$file}");
            }
            require_once $path;
        }
    }
    
    private function load_controllers() {
        $files = array(
            'includes/controllers/class-saw-dashboard-controller.php',
        );
        
        foreach ($files as $file) {
            $path = SAW_VISITORS_PLUGIN_DIR . $file;
            if (!file_exists($path)) {
                throw new Exception("Controller not found: {$file}");
            }
            require_once $path;
        }
    }
    
    public function use_blank_template($template) {
        $blank = SAW_VISITORS_PLUGIN_DIR . 'templates/blank-template.php';
        if (file_exists($blank)) {
            return $blank;
        }
        return $template;
    }
    
    public function enqueue_app_assets() {
        // CSS
        wp_enqueue_style('saw-app', SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app.css', array(), SAW_VISITORS_VERSION);
        wp_enqueue_style('saw-app-header', SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-header.css', array('saw-app'), SAW_VISITORS_VERSION);
        wp_enqueue_style('saw-app-sidebar', SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-sidebar.css', array('saw-app'), SAW_VISITORS_VERSION);
        wp_enqueue_style('saw-app-footer', SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-footer.css', array('saw-app'), SAW_VISITORS_VERSION);
        wp_enqueue_style('saw-app-responsive', SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-responsive.css', array('saw-app'), SAW_VISITORS_VERSION);
        
        // JS
        wp_enqueue_script('saw-app', SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-app.js', array('jquery'), SAW_VISITORS_VERSION, true);
        
        // Localize
        wp_localize_script('saw-app', 'sawApp', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_app_nonce'),
        ));
    }
    
    private function handle_admin_route($path) {
        // Temporarily disable auth check for debugging
        // TODO: Re-enable this after layout works
        /*
        $user = SAW_Auth::get_current_user();
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            wp_redirect('/login');
            exit;
        }
        */
        
        $segments = $this->parse_path($path);
        
        if (empty($segments[0])) {
            // Dashboard
            SAW_Dashboard_Controller::index();
        } else {
            switch ($segments[0]) {
                case 'invitations':
                    $this->placeholder_page('Pozvánky');
                    break;
                    
                case 'visits':
                    $this->placeholder_page('Přehled návštěv');
                    break;
                    
                case 'statistics':
                    $this->placeholder_page('Statistiky');
                    break;
                    
                case 'settings':
                    $this->handle_settings_route($segments);
                    break;
                    
                default:
                    $this->show_404();
                    break;
            }
        }
    }
    
    private function handle_manager_route($path) {
        $this->placeholder_page('Manager Dashboard');
    }
    
    private function handle_terminal_route($path) {
        $this->placeholder_page('Terminal Interface');
    }
    
    private function handle_visitor_route($path) {
        $this->placeholder_page('Visitor Flow');
    }
    
    private function handle_settings_route($segments) {
        if (empty($segments[1])) {
            $this->show_404();
            return;
        }
        
        $pages = array(
            'customers' => 'Správa zákazníků',
            'company' => 'Nastavení firmy',
            'users' => 'Uživatelé',
            'departments' => 'Oddělení',
            'content' => 'Školící obsah',
            'training' => 'Verze školení',
            'audit' => 'Audit Log',
            'email-queue' => 'Email Queue',
            'about' => 'O aplikaci',
        );
        
        $page = $segments[1];
        if (isset($pages[$page])) {
            $this->placeholder_page($pages[$page]);
        } else {
            $this->show_404();
        }
    }
    
    private function parse_path($path) {
        if (empty($path)) {
            return array();
        }
        return array_filter(explode('/', trim($path, '/')));
    }
    
    private function placeholder_page($title) {
        ob_start();
        ?>
        <div class="saw-card">
            <div class="saw-card-body" style="text-align: center; padding: 64px 32px;">
                <h1 style="margin: 0 0 16px 0; font-size: 32px;">🚧 <?php echo esc_html($title); ?></h1>
                <p style="margin: 0; color: #6b7280; font-size: 16px;">Tato stránka je ve vývoji</p>
                <a href="/admin/" style="display: inline-block; margin-top: 24px; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: 500;">← Zpět na Dashboard</a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, $title, '');
    }
    
    private function show_404() {
        status_header(404);
        ob_start();
        ?>
        <div class="saw-card">
            <div class="saw-card-body" style="text-align: center; padding: 64px 32px;">
                <h1 style="margin: 0 0 16px 0; font-size: 48px;">404</h1>
                <p style="margin: 0; color: #6b7280; font-size: 18px;">Stránka nenalezena</p>
                <a href="/admin/" style="display: inline-block; margin-top: 24px; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: 500;">← Zpět na Dashboard</a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, '404', '');
    }
    
    private function show_error($message) {
        ob_start();
        ?>
        <div style="background: white; padding: 48px; border-radius: 8px; max-width: 800px; margin: 48px auto;">
            <h1 style="color: #dc2626; margin: 0 0 16px 0;">⚠️ Chyba v SAW Router</h1>
            <pre style="background: #fee; padding: 16px; border-radius: 4px; overflow: auto;"><?php echo esc_html($message); ?></pre>
            <p><a href="/wp-admin/" style="color: #2563eb;">← Zpět do WP Admin</a></p>
        </div>
        <?php
        echo ob_get_clean();
        exit;
    }
}