<?php
/**
 * Hlavní třída pluginu SAW Visitors v4.6.1
 * UPDATED: Added Customers Controller initialization
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitors {
    
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $router;
    
    public function __construct() {
        $this->plugin_name = 'saw-visitors';
        $this->version = SAW_VISITORS_VERSION;
        
        $this->load_dependencies();
        $this->init_session();
        $this->init_controllers(); // ✅ NOVÉ: Inicializace controllerů
        $this->define_hooks();
    }
    
    private function load_dependencies() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-loader.php';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-auth.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-auth.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-session.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-session.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-password.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-password.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-audit.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-audit.php';
        }
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-router.php';
        
        $this->loader = new SAW_Loader();
    }
    
    private function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * ✅ NOVÁ METODA: Inicializace controllerů
     * Tady se načítají všechny controllery a registrují jejich AJAX handlers
     */
    private function init_controllers() {
        // ✅ CUSTOMERS CONTROLLER - Pro správu zákazníků
        $customers_controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/admin/class-saw-customers-controller.php';
        
        if (file_exists($customers_controller_file)) {
            require_once $customers_controller_file;
            
            // Vytvoř instanci controlleru (tím se zaregistruje AJAX handler)
            new SAW_Customers_Controller();
            
            error_log('SAW Visitors: Customers Controller initialized');
        } else {
            error_log('SAW Visitors ERROR: Customers Controller file not found at: ' . $customers_controller_file);
        }
        
        // ✅ MÍSTO PRO DALŠÍ CONTROLLERY
        // Až budeš přidávat další controllery, dej je sem:
        
        /*
        // Příklad pro budoucí controllery:
        
        $dashboard_controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/admin/class-saw-dashboard-controller.php';
        if (file_exists($dashboard_controller_file)) {
            require_once $dashboard_controller_file;
            new SAW_Dashboard_Controller();
        }
        
        $invitations_controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/admin/class-saw-invitations-controller.php';
        if (file_exists($invitations_controller_file)) {
            require_once $invitations_controller_file;
            new SAW_Invitations_Controller();
        }
        */
    }
    
    private function define_hooks() {
        // Routing
        $this->loader->add_action('init', $this, 'register_rewrite_rules');
        $this->loader->add_filter('query_vars', $this, 'add_query_vars');
        $this->loader->add_action('template_redirect', $this, 'handle_routing', 1);
        
        // Assets
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_scripts');
        
        // WP Admin menu
        $this->loader->add_action('admin_menu', $this, 'add_minimal_admin_menu');
    }
    
    public function add_minimal_admin_menu() {
        add_menu_page(
            'SAW Visitors',
            'SAW Visitors',
            'manage_options',
            'saw-visitors-about',
            array($this, 'display_about_page'),
            'dashicons-groups',
            30
        );
    }
    
    public function display_about_page() {
        ?>
        <div class="wrap">
            <h1>SAW Visitors <?php echo esc_html($this->version); ?></h1>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; max-width: 800px;">
                <h2>O pluginu</h2>
                <p><strong>Verze:</strong> <?php echo esc_html($this->version); ?></p>
                <p><strong>Popis:</strong> Komplexní systém pro správu návštěvníků s multi-tenant architekturou.</p>
                
                <h3>✅ Přístupové URL:</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>Admin:</strong> <a href="<?php echo home_url('/admin/'); ?>" target="_blank"><?php echo home_url('/admin/'); ?></a></li>
                    <li><strong>Správa zákazníků:</strong> <a href="<?php echo home_url('/admin/settings/customers/'); ?>" target="_blank"><?php echo home_url('/admin/settings/customers/'); ?></a></li>
                    <li><strong>Manager:</strong> <a href="<?php echo home_url('/manager/'); ?>" target="_blank"><?php echo home_url('/manager/'); ?></a></li>
                    <li><strong>Terminal:</strong> <a href="<?php echo home_url('/terminal/'); ?>" target="_blank"><?php echo home_url('/terminal/'); ?></a></li>
                </ul>
                
                <h3>⚠️ Důležité:</h3>
                <p style="background: #fef3c7; padding: 12px; border-radius: 4px; color: #92400e;">
                    Pokud výše uvedené odkazy nefungují, klikněte na tlačítko níže pro obnovení rewrite rules:
                </p>
                <form method="post" action="">
                    <input type="hidden" name="saw_flush_rewrite" value="1">
                    <?php wp_nonce_field('saw_flush_rewrite'); ?>
                    <button type="submit" class="button button-primary" style="margin-top: 12px;">🔄 Obnovit Rewrite Rules</button>
                </form>
                
                <?php
                if (isset($_POST['saw_flush_rewrite']) && check_admin_referer('saw_flush_rewrite')) {
                    flush_rewrite_rules();
                    echo '<div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 4px; margin-top: 16px;">✅ Rewrite rules byly obnoveny!</div>';
                }
                ?>
                
                <h3>Technické informace:</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>PHP: <?php echo PHP_VERSION; ?> (požadováno: 8.1+)</li>
                    <li>WordPress: <?php echo get_bloginfo('version'); ?> (požadováno: 6.0+)</li>
                    <li>Multi-tenant: ✓</li>
                    <li>Frontend admin: ✓</li>
                    <li>Customers management: ✓</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function register_rewrite_rules() {
        // Admin routes
        add_rewrite_rule('^admin/?$', 'index.php?saw_route=admin', 'top');
        add_rewrite_rule('^admin/([^/]+)/?$', 'index.php?saw_route=admin&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^admin/([^/]+)/(.+)', 'index.php?saw_route=admin&saw_path=$matches[1]/$matches[2]', 'top');
        
        // Manager routes
        add_rewrite_rule('^manager/?$', 'index.php?saw_route=manager', 'top');
        add_rewrite_rule('^manager/([^/]+)/?$', 'index.php?saw_route=manager&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^manager/([^/]+)/(.+)', 'index.php?saw_route=manager&saw_path=$matches[1]/$matches[2]', 'top');
        
        // Terminal routes
        add_rewrite_rule('^terminal/?$', 'index.php?saw_route=terminal', 'top');
        add_rewrite_rule('^terminal/([^/]+)/?$', 'index.php?saw_route=terminal&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^terminal/([^/]+)/(.+)', 'index.php?saw_route=terminal&saw_path=$matches[1]/$matches[2]', 'top');
        
        // Visitor routes
        add_rewrite_rule('^visitor/?$', 'index.php?saw_route=visitor', 'top');
        add_rewrite_rule('^visitor/([^/]+)/?$', 'index.php?saw_route=visitor&saw_path=$matches[1]', 'top');
        add_rewrite_rule('^visitor/([^/]+)/(.+)', 'index.php?saw_route=visitor&saw_path=$matches[1]/$matches[2]', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'saw_route';
        $vars[] = 'saw_path';
        return $vars;
    }
    
    public function handle_routing() {
        $route = get_query_var('saw_route');
        
        if (!$route) {
            return;
        }
        
        // PREVENT WordPress redirect to /wp-admin/
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
        
        $this->router = new SAW_Router();
        $this->router->dispatch($route, get_query_var('saw_path'));
        exit;
    }
    
    /**
     * ✅ OPRAVENO: WP Admin styles (jen About page)
     */
    public function enqueue_admin_styles() {
        if (isset($_GET['page']) && $_GET['page'] === 'saw-visitors-about') {
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/admin.css')) {
                wp_enqueue_style(
                    $this->plugin_name . '-admin',
                    SAW_VISITORS_PLUGIN_URL . 'assets/css/admin.css',
                    array(),
                    $this->version
                );
            }
        }
    }
    
    public function enqueue_admin_scripts() {
        if (isset($_GET['page']) && $_GET['page'] === 'saw-visitors-about') {
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/admin.js')) {
                wp_enqueue_script(
                    $this->plugin_name . '-admin',
                    SAW_VISITORS_PLUGIN_URL . 'assets/js/admin.js',
                    array('jquery'),
                    $this->version,
                    true
                );
            }
        }
    }
    
    /**
     * ✅ UPDATED: Frontend styles with table CSS
     */
    public function enqueue_public_styles() {
        $route = get_query_var('saw_route');
        
        if (!$route) {
            return;
        }
        
        // Base public styles - VŽDY
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/public.css')) {
            wp_enqueue_style(
                $this->plugin_name . '-public',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/public.css',
                array(),
                $this->version
            );
        }
        
        // ✅ NOVÉ: Tables CSS - pro admin a manager routes
        if (in_array($route, array('admin', 'manager'))) {
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/saw-app-tables.css')) {
                wp_enqueue_style(
                    $this->plugin_name . '-tables',
                    SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-tables.css',
                    array($this->plugin_name . '-public'),
                    $this->version
                );
            }
        }
        
        // ✅ CUSTOMERS CSS - JEN NA CUSTOMERS STRÁNKÁCH
        $path = get_query_var('saw_path');
        
        if ($this->is_customers_page($route, $path)) {
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/saw-customers.css')) {
                wp_enqueue_style(
                    $this->plugin_name . '-customers',
                    SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-customers.css',
                    array($this->plugin_name . '-public', $this->plugin_name . '-tables'),
                    $this->version
                );
            }
        }
    }
    
    /**
     * ✅ NOVÁ METODA: Detekce customers stránky
     */
    private function is_customers_page($route, $path) {
        // Musí být admin route
        if ($route !== 'admin') {
            return false;
        }
        
        // Prázdná cesta = NE
        if (empty($path)) {
            return false;
        }
        
        // Kontrola customers v path
        // Podporuje: settings/customers, settings/customers/new, settings/customers/edit/1
        return (
            strpos($path, 'settings/customers') === 0 ||
            strpos($path, 'customers') === 0
        );
    }
    
    public function enqueue_public_scripts() {
        if (get_query_var('saw_route')) {
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/public.js')) {
                wp_enqueue_script(
                    $this->plugin_name . '-public',
                    SAW_VISITORS_PLUGIN_URL . 'assets/js/public.js',
                    array('jquery'),
                    $this->version,
                    true
                );
                
                // Localize script for AJAX
                wp_localize_script(
                    $this->plugin_name . '-public',
                    'sawAjax',
                    array(
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('saw_ajax_nonce')
                    )
                );
            }
        }
    }
    
    public function run() {
        $this->loader->run();
    }
    
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    public function get_loader() {
        return $this->loader;
    }
    
    public function get_version() {
        return $this->version;
    }
}