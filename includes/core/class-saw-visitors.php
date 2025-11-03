<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitors {
    
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $router;
    private static $instance_created = false;
    
    public function __construct() {
        if (self::$instance_created) {
            return;
        }
        self::$instance_created = true;
        
        $this->plugin_name = 'saw-visitors';
        $this->version = SAW_VISITORS_VERSION;
        
        $this->load_dependencies();
        $this->init_router();
        $this->init_session();
        $this->init_context();
        $this->init_customer_switcher();  // ← PŘIDÁNO!
        $this->init_branch_switcher();
    }
    
    private function load_dependencies() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-loader.php';
        
        // Base Classes
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        
        // Module Loader
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-loader.php';
        
        // Asset Manager
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-asset-manager.php';
        
        // Router
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
        
        // Session Manager
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        // Context
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
        }
        
        // User Branches
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-user-branches.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-user-branches.php';
        }
        
        $this->loader = new SAW_Loader();
    }
    
    private function init_router() {
        $this->router = new SAW_Router();
    }
    
    private function init_session() {
        if (class_exists('SAW_Session_Manager')) {
            SAW_Session_Manager::instance();
        }
    }
    
    private function init_context() {
        if (class_exists('SAW_Context')) {
            SAW_Context::instance();
        }
    }
    
    private function init_customer_switcher() {
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php';
            
            if (class_exists('SAW_Component_Customer_Switcher')) {
                new SAW_Component_Customer_Switcher();
            }
        }
    }
    
    private function init_branch_switcher() {
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/branch-switcher/class-saw-component-branch-switcher.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/branch-switcher/class-saw-component-branch-switcher.php';
            
            if (class_exists('SAW_Component_Branch_Switcher')) {
                new SAW_Component_Branch_Switcher();
            }
        }
    }
    
    private function define_hooks() {
        $this->loader->add_action('init', $this->router, 'register_routes');
        $this->loader->add_filter('query_vars', $this->router, 'register_query_vars');
        $this->loader->add_action('template_redirect', $this->router, 'dispatch', 1);
        
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
    }
    
    public function enqueue_public_styles() {
        $route = get_query_var('saw_route');
        $is_saw_page = !empty($route) || $this->is_saw_url();
        
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
        $is_saw_page = !empty($route) || $this->is_saw_url();
        
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
        return strpos($request_uri, '/admin/') !== false;
    }
    
    public function run() {
        $this->define_hooks();
        $this->loader->run();
    }
}