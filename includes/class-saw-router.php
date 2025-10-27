<?php
/**
 * SAW Router - SAFE VERSION (kontroluje existenci souborů)
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    /**
     * Dispatch routing to appropriate controller
     */
    public function dispatch($route = '', $path = '') {
        // Get route from query var if not passed
        if (empty($route)) {
            $route = get_query_var('saw_route');
        }
        
        if (empty($path)) {
            $path = get_query_var('saw_path');
        }
        
        // Try to load frontend components (if they exist)
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
     * Load frontend component classes (SAFELY - kontroluje existenci)
     */
    private function load_frontend_components() {
        // Pouze načte soubory, které OPRAVDU existují
        $components = [
            'includes/frontend/class-saw-app-layout.php',
            'includes/frontend/class-saw-app-header.php',
            'includes/frontend/class-saw-app-sidebar.php',
            'includes/frontend/class-saw-app-footer.php',
        ];
        
        foreach ($components as $component) {
            $file_path = SAW_VISITORS_PLUGIN_DIR . $component;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Handle admin routes
     */
    private function handle_admin_route($path) {
        // Zkus načíst dashboard controller (pokud existuje)
        $controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-dashboard-controller.php';
        
        if (file_exists($controller_file)) {
            require_once $controller_file;
            
            if (empty($path)) {
                // Dashboard
                if (class_exists('SAW_Dashboard_Controller')) {
                    SAW_Dashboard_Controller::index();
                    return;
                }
            }
        }
        
        // Pokud controller neexistuje, zobraz placeholder
        $this->render_placeholder_page('admin/' . $path, 'Admin Interface');
    }
    
    /**
     * Handle manager routes
     */
    private function handle_manager_route($path) {
        $this->render_placeholder_page('manager/' . $path, 'Manager Interface');
    }
    
    /**
     * Handle terminal routes
     */
    private function handle_terminal_route($path) {
        $this->render_placeholder_page('terminal/' . $path, 'Terminal Interface');
    }
    
    /**
     * Handle visitor routes (public invitation flow)
     */
    private function handle_visitor_route($path) {
        $this->render_placeholder_page('visitor/' . $path, 'Visitor Portal');
    }
    
    /**
     * Render placeholder page for routes without controllers yet
     */
    private function render_placeholder_page($path, $title = '') {
        if (empty($title)) {
            $title = ucfirst(str_replace('/', ' / ', $path));
        }
        
        // Zkus použít layout systém (pokud existuje)
        if (class_exists('SAW_App_Layout')) {
            ob_start();
            $this->render_placeholder_content($path, $title);
            $content = ob_get_clean();
            
            $layout = new SAW_App_Layout();
            $layout->render($content, $title, '');
            return;
        }
        
        // Fallback: jednoduchý HTML výstup
        $this->render_simple_placeholder($path, $title);
    }
    
    /**
     * Render placeholder content (pro layout)
     */
    private function render_placeholder_content($path, $title) {
        ?>
        <div class="saw-page-wrapper">
            <div class="saw-page-header">
                <h1>🚧 <?php echo esc_html($title); ?></h1>
                <p class="saw-page-subtitle">Tato stránka je ve vývoji</p>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-body">
                    <h2>Informace o routě</h2>
                    <table class="saw-info-table">
                        <tr>
                            <th>Cesta:</th>
                            <td><code><?php echo esc_html($path); ?></code></td>
                        </tr>
                        <tr>
                            <th>URL:</th>
                            <td><code><?php echo esc_html($_SERVER['REQUEST_URI']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="saw-badge saw-badge-warning">Ve vývoji</span></td>
                        </tr>
                    </table>
                    
                    <div class="saw-alert saw-alert-info" style="margin-top: 24px;">
                        <strong>ℹ️ Info:</strong> Tento controller a template budou implementovány v dalších fázích vývoje.
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render simple placeholder (fallback bez layoutu)
     */
    private function render_simple_placeholder($path, $title) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>SAW Visitors - <?php echo esc_html($title); ?></title>
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
                }
                .header {
                    background: white;
                    padding: 24px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin-bottom: 24px;
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                .logo {
                    width: 50px;
                    height: 50px;
                    background: #2563eb;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    font-size: 20px;
                }
                .content {
                    background: white;
                    padding: 32px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .success {
                    background: #d1fae5;
                    color: #065f46;
                    padding: 16px;
                    border-radius: 6px;
                    margin-bottom: 24px;
                }
                .info {
                    background: #fef3c7;
                    color: #92400e;
                    padding: 16px;
                    border-radius: 6px;
                    margin-bottom: 24px;
                }
                pre {
                    background: #1f2937;
                    color: #10b981;
                    padding: 16px;
                    border-radius: 6px;
                    overflow-x: auto;
                    margin-top: 16px;
                }
                h1 { margin-bottom: 8px; color: #111827; }
                h2 { margin: 24px 0 16px; color: #374151; }
                p { color: #6b7280; line-height: 1.6; }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 16px 0;
                }
                th {
                    text-align: left;
                    padding: 12px;
                    background: #f9fafb;
                    font-weight: 600;
                    width: 150px;
                }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #e5e7eb;
                }
                code {
                    background: #f3f4f6;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">SAW</div>
                    <div>
                        <h1 style="font-size: 24px; margin: 0;">SAW Visitors v4.6.1</h1>
                        <p style="margin: 0; font-size: 14px;">Frontend Admin System</p>
                    </div>
                </div>
                
                <div class="content">
                    <div class="success">
                        ✅ <strong>ROUTER FUNGUJE!</strong> WordPress routing je správně nastaven.
                    </div>
                    
                    <div class="info">
                        ⚠️ <strong>Ve vývoji:</strong> <?php echo esc_html($title); ?> - Controller a templates budou přidány v dalších krocích.
                    </div>
                    
                    <h2>🚧 Placeholder Page</h2>
                    <p>Tato stránka je dočasná a bude nahrazena kompletním funkčním rozhraním.</p>
                    
                    <h2>Debug Informace:</h2>
                    <table>
                        <tr>
                            <th>Cesta:</th>
                            <td><code><?php echo esc_html($path); ?></code></td>
                        </tr>
                        <tr>
                            <th>URL:</th>
                            <td><code><?php echo esc_html($_SERVER['REQUEST_URI']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><strong style="color: #f59e0b;">🚧 Ve vývoji</strong></td>
                        </tr>
                    </table>
                    
                    <h2>Co funguje:</h2>
                    <ul style="margin-left: 20px; color: #374151;">
                        <li style="margin: 8px 0;">✅ URL rewrite rules</li>
                        <li style="margin: 8px 0;">✅ Query vars (saw_route, saw_path)</li>
                        <li style="margin: 8px 0;">✅ Router dispatch</li>
                        <li style="margin: 8px 0;">✅ Safe file loading (kontroluje existenci)</li>
                    </ul>
                    
                    <h2>Další kroky:</h2>
                    <ol style="margin-left: 20px; color: #374151;">
                        <li style="margin: 8px 0;">Přidat frontend komponenty (header, sidebar, footer)</li>
                        <li style="margin: 8px 0;">Přidat controllery (dashboard, invitations, atd.)</li>
                        <li style="margin: 8px 0;">Přidat templates (dashboard.php, atd.)</li>
                        <li style="margin: 8px 0;">Přidat CSS & JS assets</li>
                    </ol>
                    
                    <p style="margin-top: 32px; padding-top: 32px; border-top: 1px solid #e5e7eb;">
                        <strong>SAW Visitors</strong> • v4.6.1 • © 2025 SAW • 
                        <a href="https://sawuh.cz" style="color: #2563eb;">sawuh.cz</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Handle 404 - unknown route
     */
    private function handle_404() {
        // Zkus použít layout systém (pokud existuje)
        if (class_exists('SAW_App_Layout')) {
            ob_start();
            $this->render_404_content();
            $content = ob_get_clean();
            
            $layout = new SAW_App_Layout();
            $layout->render($content, '404', '');
            return;
        }
        
        // Fallback: jednoduchý 404
        $this->render_simple_404();
    }
    
    /**
     * Render 404 content (pro layout)
     */
    private function render_404_content() {
        ?>
        <div class="saw-page-wrapper">
            <div class="saw-page-header">
                <h1>404</h1>
                <p class="saw-page-subtitle">Stránka nebyla nalezena</p>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-body">
                    <p>Požadovaná stránka neexistuje.</p>
                    <a href="<?php echo home_url('/admin/'); ?>" class="saw-btn saw-btn-primary" style="margin-top: 16px;">
                        ← Zpět na Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render simple 404 (fallback)
     */
    private function render_simple_404() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - SAW Visitors</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f3f4f6;
                    padding: 40px 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .container {
                    max-width: 600px;
                    background: white;
                    padding: 48px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    text-align: center;
                }
                h1 {
                    font-size: 72px;
                    color: #ef4444;
                    margin-bottom: 16px;
                }
                h2 {
                    color: #111827;
                    margin-bottom: 16px;
                }
                p {
                    color: #6b7280;
                    margin-bottom: 32px;
                }
                a {
                    display: inline-block;
                    background: #2563eb;
                    color: white;
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                }
                a:hover {
                    background: #1d4ed8;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>404</h1>
                <h2>Stránka nebyla nalezena</h2>
                <p>Požadovaná stránka neexistuje nebo byla přesunuta.</p>
                <a href="<?php echo home_url('/admin/'); ?>">← Zpět na Dashboard</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}