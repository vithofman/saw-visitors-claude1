<?php
/**
 * SAW Router - S CUSTOMERS MANAGEMENT
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    /**
     * Dispatch routing
     */
    public function dispatch($route = '', $path = '') {
        // Get route from query var if not passed
        if (empty($route)) {
            $route = get_query_var('saw_route');
        }
        
        if (empty($path)) {
            $path = get_query_var('saw_path');
        }
        
        // Load frontend components FIRST
        $this->load_frontend_components();
        
        // Route to appropriate handler
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
     * Handle admin route
     */
    private function handle_admin_route($path = '') {
        // Require authentication + Super Admin check
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění k přístupu na tuto stránku.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // Parse path
        $path_parts = explode('/', trim($path, '/'));
        
        // Default to dashboard
        if (empty($path)) {
            $this->render_placeholder('Admin Dashboard', 'admin');
            return;
        }
        
        // Handle different sections
        switch ($path_parts[0]) {
            case 'dashboard':
                $this->render_placeholder('Admin Dashboard', 'admin');
                break;
                
            case 'settings':
                $this->handle_admin_settings($path_parts);
                break;
                
            default:
                $this->handle_404();
                break;
        }
    }
    
    /**
     * Handle admin settings routes
     */
    private function handle_admin_settings($path_parts) {
        if (!isset($path_parts[1])) {
            $this->handle_404();
            return;
        }
        
        switch ($path_parts[1]) {
            case 'customers':
                $this->handle_customers_routes($path_parts);
                break;
                
            default:
                $this->handle_404();
                break;
        }
    }
    
    /**
     * Handle customers routes
     */
    private function handle_customers_routes($path_parts) {
        // Load controller
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/admin/class-saw-customers-controller.php';
        $controller = new SAW_Customers_Controller();
        
        // Route to action
        if (!isset($path_parts[2]) || $path_parts[2] === '') {
            // List
            $controller->index();
        } elseif ($path_parts[2] === 'create') {
            // Create form
            $controller->form();
        } elseif ($path_parts[2] === 'edit') {
            // Edit form
            $controller->form();
        } elseif ($path_parts[2] === 'delete') {
            // Delete
            $controller->delete();
        } else {
            $this->handle_404();
        }
    }
    
    /**
     * Handle manager route
     */
    private function handle_manager_route($path = '') {
        $this->render_placeholder('Manager Dashboard', 'manager');
    }
    
    /**
     * Handle terminal route
     */
    private function handle_terminal_route($path = '') {
        $this->render_placeholder('Terminal Check-in', 'terminal');
    }
    
    /**
     * Handle visitor route
     */
    private function handle_visitor_route($path = '') {
        $this->render_placeholder('Visitor Training', 'visitor');
    }
    
    /**
     * Render placeholder page
     */
    private function render_placeholder($title, $role) {
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->set_title($title);
            
            ob_start();
            ?>
            <div class="saw-card">
                <div class="saw-card-body">
                    <h2><?php echo esc_html($title); ?></h2>
                    <p>Tato stránka je ve vývoji.</p>
                    <p><strong>Role:</strong> <?php echo esc_html($role); ?></p>
                </div>
            </div>
            <?php
            $content = ob_get_clean();
            $layout->render($content);
        } else {
            echo '<h1>' . esc_html($title) . '</h1>';
            echo '<p>Tato stránka je ve vývoji.</p>';
        }
    }
    
    /**
     * Handle 404
     */
    private function handle_404() {
        status_header(404);
        nocache_headers();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->set_title('404 - Stránka nenalezena');
            
            ob_start();
            ?>
            <div class="saw-card">
                <div class="saw-card-body" style="text-align: center; padding: 60px 20px;">
                    <h1 style="font-size: 72px; color: #9ca3af; margin: 0;">404</h1>
                    <h2 style="margin-top: 16px;">Stránka nenalezena</h2>
                    <p style="color: #6b7280; margin-top: 12px;">
                        Požadovaná stránka neexistuje nebo k ní nemáte přístup.
                    </p>
                    <a href="<?php echo home_url('/admin/dashboard/'); ?>" class="saw-btn saw-btn-primary" style="margin-top: 24px;">
                        Zpět na dashboard
                    </a>
                </div>
            </div>
            <?php
            $content = ob_get_clean();
            $layout->render($content);
        } else {
            echo '<h1>404 - Stránka nenalezena</h1>';
        }
        exit;
    }
}
