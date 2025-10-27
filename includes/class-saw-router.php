<?php
/**
 * SAW Router - WORKING VERSION WITH REAL LAYOUT
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
        
        // Load ALL frontend components FIRST
        $this->load_all_components();
        
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
     * Load ALL components
     */
    private function load_all_components() {
        // Frontend components
        $components = array(
            'includes/frontend/class-saw-app-layout.php',
            'includes/frontend/class-saw-app-header.php',
            'includes/frontend/class-saw-app-sidebar.php',
            'includes/frontend/class-saw-app-footer.php',
        );
        
        foreach ($components as $component) {
            $file_path = SAW_VISITORS_PLUGIN_DIR . $component;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Dashboard controller
        $controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-dashboard-controller.php';
        if (file_exists($controller_file)) {
            require_once $controller_file;
        }
    }
    
    /**
     * Handle admin routes
     */
    private function handle_admin_route($path) {
        if (empty($path)) {
            // Dashboard
            if (class_exists('SAW_Dashboard_Controller')) {
                SAW_Dashboard_Controller::index();
                exit;
            }
        }
        
        // Fallback
        $this->render_with_layout('admin/' . $path, 'Admin Interface');
    }
    
    /**
     * Handle manager routes
     */
    private function handle_manager_route($path) {
        $this->render_with_layout('manager/' . $path, 'Manager Interface');
    }
    
    /**
     * Handle terminal routes
     */
    private function handle_terminal_route($path) {
        $this->render_with_layout('terminal/' . $path, 'Terminal Interface');
    }
    
    /**
     * Handle visitor routes
     */
    private function handle_visitor_route($path) {
        $this->render_with_layout('visitor/' . $path, 'Visitor Portal');
    }
    
    /**
     * Render page WITH layout (header, sidebar, footer)
     */
    private function render_with_layout($path, $title = '') {
        if (empty($title)) {
            $title = ucfirst(str_replace('/', ' / ', $path));
        }
        
        // Generate content
        ob_start();
        ?>
        <div class="saw-page-wrapper">
            <div class="saw-page-header">
                <h1>🚧 <?php echo esc_html($title); ?></h1>
                <p class="saw-page-subtitle">Tato stránka je ve vývoji</p>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-body">
                    <h2>Informace o routě</h2>
                    <table class="saw-info-table" style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 12px; background: #f9fafb;">Cesta:</th>
                            <td style="padding: 12px;"><code><?php echo esc_html($path); ?></code></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 12px; background: #f9fafb;">URL:</th>
                            <td style="padding: 12px;"><code><?php echo esc_html($_SERVER['REQUEST_URI']); ?></code></td>
                        </tr>
                        <tr>
                            <th style="text-align: left; padding: 12px; background: #f9fafb;">Status:</th>
                            <td style="padding: 12px;"><span class="saw-badge saw-badge-warning" style="background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;">Ve vývoji</span></td>
                        </tr>
                    </table>
                    
                    <div class="saw-alert saw-alert-info" style="background: #e0f2fe; color: #075985; padding: 16px; border-radius: 6px; margin-top: 24px;">
                        <strong>ℹ️ Info:</strong> Layout funguje! Vidíš header, sidebar a footer. Controller a template budou implementovány v dalších fázích.
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <h3>Quick Links:</h3>
                        <p>
                            <a href="/admin/" style="color: #2563eb; text-decoration: none; margin-right: 16px;">Admin Dashboard</a>
                            <a href="/manager/" style="color: #2563eb; text-decoration: none; margin-right: 16px;">Manager Dashboard</a>
                            <a href="/terminal/" style="color: #2563eb; text-decoration: none;">Terminal</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        // Use layout if available
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, $title, '');
        } else {
            // Fallback: simple HTML
            $this->render_simple_fallback($content, $title);
        }
        
        exit;
    }
    
    /**
     * Simple fallback (pokud layout neexistuje)
     */
    private function render_simple_fallback($content, $title) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?> - SAW Visitors</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f3f4f6;
                    padding: 40px 20px;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: white;
                    padding: 32px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                h1 { color: #111827; margin-bottom: 16px; }
                .error-notice {
                    background: #fef2f2;
                    color: #991b1b;
                    padding: 16px;
                    border-radius: 6px;
                    margin-bottom: 24px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-notice">
                    <strong>⚠️ Layout komponenty nejsou načteny!</strong>
                    <p>Frontend komponenty (header, sidebar, footer) nebyly nalezeny.</p>
                </div>
                <?php echo $content; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Handle 404
     */
    private function handle_404() {
        $this->render_with_layout('404', '404 - Stránka nenalezena');
    }
}