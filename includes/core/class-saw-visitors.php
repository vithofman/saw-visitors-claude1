<?php
/**
 * SAW Visitors Main Class
 * 
 * Hlavní třída pluginu - inicializace, routing, hooks
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitors {
    
    /**
     * @var SAW_Loader
     */
    protected $loader;
    
    /**
     * @var string
     */
    protected $plugin_name;
    
    /**
     * @var string
     */
    protected $version;
    
    /**
     * @var SAW_Router
     */
    protected $router;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->plugin_name = 'saw-visitors';
        $this->version = SAW_VISITORS_VERSION;
        
        $this->load_dependencies();
        $this->init_session();
        $this->init_controllers();
        $this->define_hooks();
    }
    
    /**
     * Načtení závislostí
     * 
     * @return void
     */
    private function load_dependencies() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-loader.php';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-auth.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-auth.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-password.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-password.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-database.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-database.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-audit.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-audit.php';
        }
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
        
        $this->loader = new SAW_Loader();
    }
    
    /**
     * Inicializace session
     * 
     * @return void
     */
    private function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Inicializace controllerů
     * 
     * Načítá všechny controllery a registruje jejich AJAX handlery
     * 
     * @return void
     */
    private function init_controllers() {
        $customers_controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-controller-customers.php';
        
        if (file_exists($customers_controller_file)) {
            require_once $customers_controller_file;
            new SAW_Controller_Customers();
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Visitors: Customers Controller initialized');
            }
        } else {
            error_log('SAW Visitors ERROR: Customers Controller not found at: ' . $customers_controller_file);
        }
        
        $content_controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-controller-content.php';
        
        if (file_exists($content_controller_file)) {
            require_once $content_controller_file;
            new SAW_Controller_Content();
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Visitors: Content Controller initialized');
            }
        }
    }
    
    /**
     * Definice WordPress hooks
     * 
     * @return void
     */
    private function define_hooks() {
        $this->loader->add_action('init', $this, 'register_rewrite_rules');
        $this->loader->add_filter('query_vars', $this, 'add_query_vars');
        $this->loader->add_action('template_redirect', $this, 'handle_routing', 1);
        
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_scripts');
        
        $this->loader->add_action('admin_menu', $this, 'add_minimal_admin_menu');
    }
    
    /**
     * Přidání WP Admin menu
     * 
     * @return void
     */
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
    
    /**
     * Zobrazení About stránky
     * 
     * @return void
     */
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
                    <li>Multi-tenant: ✔</li>
                    <li>Frontend admin: ✔</li>
                    <li>Customers management: ✔</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Registrace rewrite rules
     * 
     * @return void
     */
    public function register_rewrite_rules() {
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
     * Přidání query vars
     * 
     * @param array $vars Query variables
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'saw_route';
        $vars[] = 'saw_path';
        return $vars;
    }
    
    /**
     * Zpracování routingu
     * 
     * @return void
     */
    public function handle_routing() {
        $route = get_query_var('saw_route');
        
        if (!$route) {
            return;
        }
        
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
        
        $this->router = new SAW_Router();
        $this->router->dispatch($route, get_query_var('saw_path'));
        exit;
    }
    
    /**
     * Enqueue admin styles
     * 
     * @return void
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
    
    /**
     * Enqueue admin scripts
     * 
     * @return void
     */
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
     * Enqueue public styles
     * 
     * @return void
     */
    public function enqueue_public_styles() {
        $route = get_query_var('saw_route');
        
        if (!$route) {
            return;
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/public.css')) {
            wp_enqueue_style(
                $this->plugin_name . '-public',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/public.css',
                array(),
                $this->version
            );
        }
        
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
        
        $path = get_query_var('saw_path');
        
        if ($this->is_customers_page($route, $path)) {
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/pages/saw-customers.css')) {
                wp_enqueue_style(
                    $this->plugin_name . '-customers',
                    SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-customers.css',
                    array($this->plugin_name . '-public', $this->plugin_name . '-tables'),
                    $this->version
                );
            }
        }
    }
    
    /**
     * Detekce customers stránky
     * 
     * @param string $route Route
     * @param string $path  Path
     * @return bool
     */
    private function is_customers_page($route, $path) {
        if ($route !== 'admin') {
            return false;
        }
        
        if (empty($path)) {
            return false;
        }
        
        return (
            strpos($path, 'settings/customers') === 0 ||
            strpos($path, 'customers') === 0
        );
    }
    
    /**
     * Enqueue public scripts
     * 
     * @return void
     */
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
    
    /**
     * Spuštění pluginu
     * 
     * @return void
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    /**
     * @return SAW_Loader
     */
    public function get_loader() {
        return $this->loader;
    }
    
    /**
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}