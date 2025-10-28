<?php
/**
 * SAW Router - S POVINNOU AUTENTIZACÍ
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
     * Check if user is logged in
     */
    private function is_logged_in() {
        // DOČASNÉ: Kontrola WordPress session
        // TODO: Později použít SAW_Session
        
        // Check if user has active WordPress session
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check if has SAW session cookie
        if (isset($_COOKIE['saw_session_token'])) {
            // TODO: Validovat session token v databázi
            return true;
        }
        
        return false;
    }
    
    /**
     * Redirect to login page
     */
    private function redirect_to_login($route = 'admin') {
        // Render simple login page
        $this->render_login_page($route);
    }
    
    /**
     * Render login page
     */
    private function render_login_page($route) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Přihlášení - SAW Visitors</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .login-container {
                    background: white;
                    padding: 48px;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    width: 100%;
                    max-width: 440px;
                }
                .login-logo {
                    text-align: center;
                    margin-bottom: 32px;
                }
                .login-logo svg {
                    display: inline-block;
                }
                .login-title {
                    font-size: 28px;
                    font-weight: 700;
                    color: #111827;
                    margin-bottom: 8px;
                    text-align: center;
                }
                .login-subtitle {
                    font-size: 14px;
                    color: #6b7280;
                    text-align: center;
                    margin-bottom: 32px;
                }
                .login-alert {
                    background: #fee2e2;
                    color: #991b1b;
                    padding: 16px;
                    border-radius: 8px;
                    margin-bottom: 24px;
                    border-left: 4px solid #ef4444;
                }
                .login-form {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }
                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                .form-label {
                    font-size: 14px;
                    font-weight: 500;
                    color: #374151;
                }
                .form-input {
                    padding: 12px 16px;
                    border: 1px solid #d1d5db;
                    border-radius: 8px;
                    font-size: 14px;
                    transition: all 0.2s;
                }
                .form-input:focus {
                    outline: none;
                    border-color: #2563eb;
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
                }
                .form-button {
                    padding: 14px;
                    background: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .form-button:hover {
                    background: #1d4ed8;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
                }
                .form-button:disabled {
                    background: #9ca3af;
                    cursor: not-allowed;
                    transform: none;
                }
                .login-footer {
                    margin-top: 24px;
                    text-align: center;
                    font-size: 12px;
                    color: #6b7280;
                }
                .login-demo {
                    margin-top: 24px;
                    padding: 16px;
                    background: #f3f4f6;
                    border-radius: 8px;
                    font-size: 13px;
                    color: #4b5563;
                }
                .login-demo strong {
                    color: #111827;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-logo">
                    <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                        <rect width="60" height="60" rx="12" fill="#2563eb"/>
                        <text x="30" y="42" font-size="28" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                    </svg>
                </div>
                
                <h1 class="login-title">SAW Visitors</h1>
                <p class="login-subtitle">Systém pro správu návštěv</p>
                
                <div class="login-alert">
                    <strong>🔒 Vyžadováno přihlášení</strong><br>
                    Pro přístup k <?php echo esc_html($route === 'admin' ? 'administraci' : 'této části'); ?> musíte být přihlášeni.
                </div>
                
                <form class="login-form" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="saw_login">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                    <?php wp_nonce_field('saw_login'); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="username">Email / Uživatelské jméno</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            required
                            placeholder="admin@example.com"
                            autocomplete="username"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Heslo</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            required
                            placeholder="••••••••"
                            autocomplete="current-password"
                        >
                    </div>
                    
                    <button type="submit" class="form-button">
                        🔐 Přihlásit se
                    </button>
                </form>
                
                <div class="login-demo">
                    <strong>ℹ️ Demo režim:</strong><br>
                    Autentizace ještě není plně implementována.<br>
                    Tento screen se zobrazuje, protože NENÍ aktivní WordPress session.
                </div>
                
                <div class="login-footer">
                    SAW Visitors v<?php echo SAW_VISITORS_VERSION; ?> © <?php echo date('Y'); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Get current user data
     */
    private function get_current_user_data() {
        // Pokud je WP user přihlášen, použijeme jeho data
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => 'admin', // TODO: Načíst z saw_users tabulky
            );
        }
        
        // TODO: Check SAW session cookie and load from database
        
        // Fallback demo data
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
        // TODO: Load from database based on user's customer_id
        return array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'address' => 'Praha 1, Hlavní 123',
        );
    }
    
    /**
     * Handle admin routes
     */
    private function handle_admin_route($path) {
        // AUTH CHECK - POVINNÉ!
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('admin');
            return;
        }
        
        $this->render_page('Admin Interface', $path, 'admin');
    }
    
    /**
     * Handle manager routes
     */
    private function handle_manager_route($path) {
        // AUTH CHECK - POVINNÉ!
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('manager');
            return;
        }
        
        $this->render_page('Manager Interface', $path, 'manager');
    }
    
    /**
     * Handle terminal routes
     */
    private function handle_terminal_route($path) {
        // AUTH CHECK - POVINNÉ!
        if (!$this->is_logged_in()) {
            $this->redirect_to_login('terminal');
            return;
        }
        
        $this->render_page('Terminal Interface', $path, 'terminal');
    }
    
    /**
     * Handle visitor routes
     */
    private function handle_visitor_route($path) {
        // Visitor routes jsou veřejné - bez auth
        $this->render_page('Visitor Portal', $path, 'visitor');
    }
    
    /**
     * Render page using layout component
     */
    private function render_page($title, $path, $route) {
        // Get user and customer data
        $user = $this->get_current_user_data();
        $customer = $this->get_current_customer_data();
        
        // Generate page content
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
                    </div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        // Use layout component
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, $title, '', $user, $customer);
        }
    }
    
    /**
     * Handle 404
     */
    private function handle_404() {
        $this->render_page('404 - Stránka nenalezena', '404', 'error');
    }
}