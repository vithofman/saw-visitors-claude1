<?php
/**
 * SAW Router - MINIMAL VERSION
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Router {
    
    /**
     * Dispatch routing - MUST accept parameters!
     */
    public function dispatch($route = '', $path = '') {
        // Get route from query var if not passed
        if (empty($route)) {
            $route = get_query_var('saw_route');
        }
        
        if (empty($path)) {
            $path = get_query_var('saw_path');
        }
        
        // Simple HTML output for testing
        $this->output_test_page($route, $path);
    }
    
    /**
     * Test output - simple HTML
     */
    private function output_test_page($route, $path) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>SAW Visitors - <?php echo esc_html($route); ?></title>
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
                .sidebar {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin-bottom: 24px;
                }
                .nav-item {
                    display: block;
                    padding: 12px 16px;
                    margin-bottom: 4px;
                    border-radius: 6px;
                    color: #374151;
                    text-decoration: none;
                    transition: background 0.2s;
                }
                .nav-item:hover {
                    background: #f3f4f6;
                }
                .nav-item.active {
                    background: #eff6ff;
                    color: #2563eb;
                    font-weight: 500;
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
                    background: #e0e7ff;
                    color: #3730a3;
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
                
                <div class="sidebar">
                    <a href="/admin/" class="nav-item <?php echo ($route === 'admin' && empty($path)) ? 'active' : ''; ?>">📊 Dashboard</a>
                    <a href="/admin/invitations" class="nav-item">📧 Pozvánky</a>
                    <a href="/admin/visits" class="nav-item">👥 Přehled návštěv</a>
                    <a href="/admin/statistics" class="nav-item">📈 Statistiky</a>
                    <hr style="margin: 12px 0; border: none; border-top: 1px solid #e5e7eb;">
                    <a href="/admin/settings/company" class="nav-item">⚙️ Nastavení firmy</a>
                    <a href="/admin/settings/users" class="nav-item">👤 Uživatelé</a>
                    <a href="/admin/settings/departments" class="nav-item">🏛️ Oddělení</a>
                </div>
                
                <div class="content">
                    <div class="success">
                        ✅ <strong>ROUTER FUNGUJE!</strong> WordPress routing je správně nastaven.
                    </div>
                    
                    <div class="info">
                        ℹ️ Toto je <strong>testovací stránka</strong>. Frontend komponenty budou přidány v dalších krocích.
                    </div>
                    
                    <h2>Debug Informace:</h2>
                    <pre>Route: <?php echo esc_html($route ?: 'EMPTY'); ?>

Path:  <?php echo esc_html($path ?: 'EMPTY'); ?>

URL:   <?php echo esc_html($_SERVER['REQUEST_URI']); ?></pre>
                    
                    <h2>Co funguje:</h2>
                    <ul style="margin-left: 20px; color: #374151;">
                        <li style="margin: 8px 0;">✅ URL rewrite rules</li>
                        <li style="margin: 8px 0;">✅ Query vars (saw_route, saw_path)</li>
                        <li style="margin: 8px 0;">✅ Router dispatch</li>
                        <li style="margin: 8px 0;">✅ Template rendering</li>
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
}