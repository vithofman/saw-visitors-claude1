<?php
/**
 * SAW Visitors Main Class
 * 
 * OPRAVA: enqueue_public_styles() nyní správně detekuje SAW stránky
 * a načítá CSS i při navigaci zpět.
 * 
 * @package SAW_Visitors
 * @version 4.8.0
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
        $this->init_router();
        $this->init_session();
        $this->define_hooks();
    }
    
    private function load_dependencies() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-loader.php';
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-loader.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-asset-manager.php';
        
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
    
    private function init_router() {
        $this->router = new SAW_Router();
    }
    
    private function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    private function define_hooks() {
        $this->loader->add_action('init', $this->router, 'register_routes');
        $this->loader->add_filter('query_vars', $this->router, 'register_query_vars');
        $this->loader->add_action('template_redirect', $this->router, 'dispatch', 1);
        
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_scripts');
        
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
                    <li><strong>Typy účtů:</strong> <a href="<?php echo home_url('/admin/settings/account-types/'); ?>" target="_blank"><?php echo home_url('/admin/settings/account-types/'); ?></a></li>
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
                    <li>Modulární architektura v2: ✔</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
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
     * Enqueue public styles
     * 
     * ← FIX: Používá lepší detekci SAW stránek (místo jen saw_route)
     */
    public function enqueue_public_styles() {
        // Detekce SAW stránky - použij VŠECHNY možné indikátory
        $route = get_query_var('saw_route');
        $path = get_query_var('saw_path');
        $is_saw_page = !empty($route) || !empty($path) || $this->is_saw_url();
        
        if (!$is_saw_page) {
            return;
        }
        
        // Enqueue global assets (base, tables, forms, components)
        // Tyhle se načtou VŽDYCKY na SAW stránkách
        SAW_Asset_Manager::enqueue_global();
        
        // Enqueue module-specific assets (jen když je známý module)
        $active_module = $this->router->get_active_module();
        if ($active_module) {
            SAW_Asset_Manager::enqueue_module($active_module);
        }
    }
    
    /**
     * Enqueue public scripts
     * 
     * ← FIX: Stejná detekce jako u CSS
     */
    public function enqueue_public_scripts() {
        // Detekce SAW stránky
        $route = get_query_var('saw_route');
        $path = get_query_var('saw_path');
        $is_saw_page = !empty($route) || !empty($path) || $this->is_saw_url();
        
        if (!$is_saw_page) {
            return;
        }
        
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
    
    /**
     * Helper: Is SAW URL?
     * 
     * Backup detekce pro případy kdy query vars ještě nejsou ready.
     * Kontroluje URL path přímo.
     */
    private function is_saw_url() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // SAW URL patterns
        $patterns = [
            '/admin/',
            '/manager/',
            '/terminal/',
            '/settings/',
        ];
        
        foreach ($patterns as $pattern) {
            if (strpos($request_uri, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
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