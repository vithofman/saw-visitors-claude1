<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitors {
    
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $router;
    private static $ajax_initialized = false;
    
    public function __construct() {
        $this->plugin_name = 'saw-visitors';
        $this->version = SAW_VISITORS_VERSION;
        
        $this->load_dependencies();
        $this->init_router();
        $this->init_session();
        $this->block_wp_admin_for_saw_roles();
        $this->init_ajax_controllers();
        $this->define_hooks();
    }
    
    private function load_dependencies() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-loader.php';
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-loader.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-asset-manager.php';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-auth.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-auth.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-password.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-password.php';
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
    
    private function block_wp_admin_for_saw_roles() {
        add_action('admin_init', function() {
            $user = wp_get_current_user();
            $saw_roles = ['saw_admin', 'saw_super_manager', 'saw_manager', 'saw_terminal'];
            
            if (array_intersect($saw_roles, $user->roles)) {
                wp_redirect(home_url('/login/'));
                exit;
            }
        });
    }
    
    private function init_ajax_controllers() {
        if (self::$ajax_initialized) {
            return;
        }
        
        self::$ajax_initialized = true;
        
        $modules = SAW_Module_Loader::get_all();
        
        foreach ($modules as $slug => $config) {
            $controller_file = $config['path'] . 'controller.php';
            
            if (file_exists($controller_file)) {
                if (file_exists($config['path'] . 'model.php')) {
                    require_once $config['path'] . 'model.php';
                }
                
                require_once $controller_file;
                
                $parts = explode('-', $slug);
                $parts = array_map('ucfirst', $parts);
                $class_name = implode('_', $parts);
                $controller_class = 'SAW_Module_' . $class_name . '_Controller';
                
                if (class_exists($controller_class)) {
                    new $controller_class();
                }
            }
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
            <p>Plugin verze: <strong><?php echo esc_html($this->version); ?></strong></p>
            <p>Používá modulární architekturu v2.</p>
            
            <h2>Aktivní moduly</h2>
            <ul>
                <?php
                $modules = SAW_Module_Loader::get_all();
                foreach ($modules as $slug => $config) {
                    echo '<li>' . esc_html($config['plural']) . ' (' . esc_html($slug) . ')</li>';
                }
                ?>
            </ul>
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
    
    public function enqueue_public_styles() {
        $route = get_query_var('saw_route');
        $path = get_query_var('saw_path');
        $is_saw_page = !empty($route) || !empty($path) || $this->is_saw_url();
        
        if (!$is_saw_page) {
            return;
        }
        
        SAW_Asset_Manager::enqueue_global();
        
        $active_module = $this->router->get_active_module();
        if ($active_module) {
            SAW_Asset_Manager::enqueue_module($active_module);
        }
    }
    
    public function enqueue_public_scripts() {
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
    
    private function is_saw_url() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($request_uri, '/admin/') !== false || 
               strpos($request_uri, '/app/') !== false;
    }
    
    public function run() {
        $this->loader->run();
    }
}