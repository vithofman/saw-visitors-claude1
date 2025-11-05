<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitors {
    
    private static $instance = null;
    
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $router;
    
    /**
     * Singleton pattern - ONLY way to create instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - prevents direct instantiation
     */
    private function __construct() {
        $this->plugin_name = 'saw-visitors';
        $this->version = SAW_VISITORS_VERSION;
        
        $this->load_dependencies();
        $this->init_router();
        $this->init_session();
        $this->init_context();
        $this->init_customer_switcher();
        $this->init_branch_switcher();
        $this->init_language_switcher();
        $this->register_module_ajax_handlers();
        $this->block_wp_admin_for_saw_roles();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    private function load_dependencies() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-loader.php';
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-loader.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-asset-manager.php';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-user-branches.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-user-branches.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-error-handler.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-error-handler.php';
        }
        
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
        if (class_exists('SAW_Session_Manager')) {
            SAW_Session_Manager::instance();
        }
    }
    
    private function init_context() {
        if (class_exists('SAW_Context')) {
            SAW_Context::instance();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[SAW_Visitors] Context initialized - Customer: %s, Branch: %s',
                    SAW_Context::get_customer_id() ?? 'NULL',
                    SAW_Context::get_branch_id() ?? 'NULL'
                ));
            }
        }
    }
    
    private function init_customer_switcher() {
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php';
            
            if (class_exists('SAW_Component_Customer_Switcher')) {
                new SAW_Component_Customer_Switcher();
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW_Visitors] Customer Switcher registered');
                }
            }
        }
    }
    
    private function init_branch_switcher() {
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/branch-switcher/class-saw-component-branch-switcher.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/branch-switcher/class-saw-component-branch-switcher.php';
            
            if (class_exists('SAW_Component_Branch_Switcher')) {
                new SAW_Component_Branch_Switcher();
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW_Visitors] Branch Switcher registered');
                }
            }
        }
    }
    
    private function init_language_switcher() {
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/language-switcher/ajax-handler.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/language-switcher/ajax-handler.php';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_Visitors] Language Switcher AJAX handler loaded');
            }
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
    
    private function register_module_ajax_handlers() {
        $modules = SAW_Module_Loader::get_all();
        $instance = $this;
        
        foreach ($modules as $slug => $config) {
            $entity = $config['entity'];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Registration] Module: %s, Entity: %s, Actions: saw_get_%s_detail, saw_search_%s, saw_delete_%s',
                    $slug,
                    $entity,
                    $entity,
                    $entity,
                    $entity
                ));
            }
            
            add_action('wp_ajax_saw_get_' . $entity . '_detail', function() use ($slug, $instance) {
                $instance->handle_module_ajax_detail($slug);
            });
            
            add_action('wp_ajax_saw_search_' . $entity, function() use ($slug, $instance) {
                $instance->handle_module_ajax_search($slug);
            });
            
            add_action('wp_ajax_saw_delete_' . $entity, function() use ($slug, $instance) {
                $instance->handle_module_ajax_delete($slug);
            });
        }
    }
    
    private function handle_module_ajax_detail($slug) {
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Detail] ERROR: Module not found - Slug: ' . $slug);
            }
            wp_send_json_error(['message' => 'Module not found']);
            return;
        }
        
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        if (!class_exists($controller_class)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Detail] ERROR: Controller not found - Class: ' . $controller_class);
            }
            wp_send_json_error(['message' => 'Controller not found']);
            return;
        }
        
        $controller = new $controller_class();
        
        if (method_exists($controller, 'ajax_get_detail')) {
            $controller->ajax_get_detail();
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Detail] ERROR: Method ajax_get_detail not found in ' . $controller_class);
            }
            wp_send_json_error(['message' => 'Method not found']);
        }
    }
    
    private function handle_module_ajax_search($slug) {
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Search] ERROR: Module not found - Slug: ' . $slug);
            }
            wp_send_json_error(['message' => 'Module not found']);
            return;
        }
        
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        if (!class_exists($controller_class)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Search] ERROR: Controller not found - Class: ' . $controller_class);
            }
            wp_send_json_error(['message' => 'Controller not found']);
            return;
        }
        
        $controller = new $controller_class();
        
        if (method_exists($controller, 'ajax_search')) {
            $controller->ajax_search();
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Search] ERROR: Method ajax_search not found in ' . $controller_class);
            }
            wp_send_json_error(['message' => 'Method not found']);
        }
    }
    
    private function handle_module_ajax_delete($slug) {
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Delete] ERROR: Module not found - Slug: ' . $slug);
            }
            wp_send_json_error(['message' => 'Module not found']);
            return;
        }
        
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        if (!class_exists($controller_class)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Delete] ERROR: Controller not found - Class: ' . $controller_class . ', Slug: ' . $slug);
            }
            wp_send_json_error(['message' => 'Controller not found']);
            return;
        }
        
        $controller = new $controller_class();
        
        if (method_exists($controller, 'ajax_delete')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Delete] Calling ajax_delete on ' . $controller_class);
            }
            $controller->ajax_delete();
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Delete] ERROR: Method ajax_delete not found in ' . $controller_class);
            }
            wp_send_json_error(['message' => 'Method not found']);
        }
    }
    
    private function define_hooks() {
        $this->loader->add_action('init', $this->router, 'register_routes');
        $this->loader->add_filter('query_vars', $this->router, 'register_query_vars');
        $this->loader->add_action('template_redirect', $this->router, 'dispatch', 1);
        
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        
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
        return strpos($request_uri, '/admin/') !== false || 
               strpos($request_uri, '/app/') !== false;
    }
    
    public function run() {
        $this->define_hooks();
        $this->loader->run();
    }
}