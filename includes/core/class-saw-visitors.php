<?php
/**
 * Main Plugin Bootstrap Class
 *
 * Core class responsible for initializing the SAW Visitors plugin,
 * managing dependencies, routing, and AJAX handlers.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 * @version    1.0.1 - HOTFIX: Enqueue detection for SAW pages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_Visitors Class
 *
 * Singleton pattern implementation for plugin initialization.
 * Handles dependency loading, router setup, session management,
 * and AJAX handler registration.
 *
 * @since 1.0.0
 */
class SAW_Visitors {
    
    /**
     * Singleton instance
     *
     * @since 1.0.0
     * @var SAW_Visitors|null
     */
    private static $instance = null;
    
    /**
     * Loader instance
     *
     * @since 1.0.0
     * @var SAW_Loader
     */
    protected $loader;
    
    /**
     * Plugin name identifier
     *
     * @since 1.0.0
     * @var string
     */
    protected $plugin_name;
    
    /**
     * Plugin version
     *
     * @since 1.0.0
     * @var string
     */
    protected $version;
    
    /**
     * Router instance
     *
     * @since 1.0.0
     * @var SAW_Router
     */
    protected $router;
    
    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return SAW_Visitors
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - prevents direct instantiation
     *
     * Initializes plugin components in the correct order:
     * 1. Dependencies
     * 2. Router
     * 3. Session management
     * 4. Context
     * 5. UI components (switchers)
     * 6. AJAX handlers
     * 7. Access control
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->plugin_name = 'saw-visitors';
        $this->version = SAW_VISITORS_VERSION;
        
        $this->load_dependencies();
        $this->init_router();
        $this->init_session();
        $this->init_context();
        
        // Initialize Component Manager (handles all components + AJAX registration)
        SAW_Component_Manager::instance();
        
        $this->register_module_ajax_handlers();
        $this->block_wp_admin_for_saw_roles();
    }
    
    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     *
     * @since 1.0.0
     * @throws Exception When attempting to unserialize
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Load required dependencies
     *
     * Loads all core classes, base classes, and optional components.
     * Uses conditional loading for optional dependencies.
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core loader (required)
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-loader.php';
        
        // Component Manager (required) - MUST load before components
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-component-manager.php';
        
        // Base classes (required)
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        
        // Module and asset management (required)
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-loader.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-asset-manager.php';
        
        // Optional core components
        $optional_core = [
            'includes/core/class-saw-user-branches.php',
            'includes/core/class-saw-session-manager.php',
            'includes/core/class-saw-error-handler.php',
            'includes/core/class-saw-context.php',
            'includes/core/class-saw-session.php',
            'includes/core/class-saw-audit.php',
        ];
        
        foreach ($optional_core as $file) {
            $path = SAW_VISITORS_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Optional auth components
        $optional_auth = [
            'includes/auth/class-saw-auth.php',
            'includes/auth/class-saw-password.php',
        ];
        
        foreach ($optional_auth as $file) {
            $path = SAW_VISITORS_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Optional database component
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-database.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-database.php';
        }
        
        // Router (required)
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
        
        // Initialize loader
        $this->loader = new SAW_Loader();
    }
    
    /**
     * Initialize router
     *
     * Creates router instance for handling custom routes.
     *
     * @since 1.0.0
     */
    private function init_router() {
        $this->router = new SAW_Router();
    }
    
    /**
     * Initialize session management
     *
     * Starts session manager if available.
     *
     * @since 1.0.0
     */
    private function init_session() {
        if (class_exists('SAW_Session_Manager')) {
            SAW_Session_Manager::instance();
        }
    }
    
    /**
     * Initialize context system
     *
     * Sets up customer/branch context management.
     *
     * @since 1.0.0
     */
    private function init_context() {
        if (class_exists('SAW_Context')) {
            SAW_Context::instance();
        }
    }
    
    /**
     * Block WordPress admin for SAW roles
     *
     * Redirects users with SAW-specific roles away from wp-admin.
     * Prevents access to WordPress backend for custom roles.
     *
     * @since 1.0.0
     */
    private function block_wp_admin_for_saw_roles() {
        add_action('admin_init', function() {
            $user = wp_get_current_user();
            $saw_roles = ['saw_admin', 'saw_super_manager', 'saw_manager', 'saw_terminal'];
            
            if (array_intersect($saw_roles, $user->roles)) {
                wp_safe_redirect(home_url('/login/'));
                exit;
            }
        });
    }
    
    /**
     * Register module AJAX handlers
     *
     * Dynamically registers AJAX actions for all modules.
     * Creates handlers for: detail, search, delete operations.
     * Also registers custom permission module handlers.
     *
     * @since 1.0.0
     */
    private function register_module_ajax_handlers() {
        $modules = SAW_Module_Loader::get_all();
        $instance = $this;
        
        // Register standard CRUD handlers for all modules
        foreach ($modules as $slug => $config) {
            $entity = $config['entity'];
            
            add_action('wp_ajax_saw_get_' . $entity . '_detail', function() use ($slug, $instance) {
                $instance->handle_module_ajax($slug, 'ajax_get_detail');
            });
            
            add_action('wp_ajax_saw_search_' . $entity, function() use ($slug, $instance) {
                $instance->handle_module_ajax($slug, 'ajax_search');
            });
            
            add_action('wp_ajax_saw_delete_' . $entity, function() use ($slug, $instance) {
                $instance->handle_module_ajax($slug, 'ajax_delete');
            });
        }
        
        // Register custom AJAX actions for permissions module
        // Permissions module has custom business logic (not standard CRUD)
        add_action('wp_ajax_saw_update_permission', [$this, 'handle_permissions_update']);
        add_action('wp_ajax_saw_get_permissions_for_role', [$this, 'handle_permissions_get_for_role']);
        add_action('wp_ajax_saw_reset_permissions', [$this, 'handle_permissions_reset']);
    }
    
    /**
     * Handle permissions update AJAX
     *
     * Updates a single permission for a role.
     *
     * @since 1.0.0
     */
    public function handle_permissions_update() {
        $controller = $this->get_module_controller('permissions');
        
        if (!$controller) {
            wp_send_json_error(['message' => __('Permissions controller not found', 'saw-visitors')]);
            return;
        }
        
        if (method_exists($controller, 'ajax_update_permission')) {
            $controller->ajax_update_permission();
        } else {
            wp_send_json_error(['message' => __('Method ajax_update_permission not found', 'saw-visitors')]);
        }
    }
    
    /**
     * Handle get permissions for role AJAX
     *
     * Retrieves all permissions for a specific role.
     *
     * @since 1.0.0
     */
    public function handle_permissions_get_for_role() {
        $controller = $this->get_module_controller('permissions');
        
        if (!$controller) {
            wp_send_json_error(['message' => __('Permissions controller not found', 'saw-visitors')]);
            return;
        }
        
        if (method_exists($controller, 'ajax_get_permissions_for_role')) {
            $controller->ajax_get_permissions_for_role();
        } else {
            wp_send_json_error(['message' => __('Method ajax_get_permissions_for_role not found', 'saw-visitors')]);
        }
    }
    
    /**
     * Handle reset permissions AJAX
     *
     * Resets permissions to default state.
     *
     * @since 1.0.0
     */
    public function handle_permissions_reset() {
        $controller = $this->get_module_controller('permissions');
        
        if (!$controller) {
            wp_send_json_error(['message' => __('Permissions controller not found', 'saw-visitors')]);
            return;
        }
        
        if (method_exists($controller, 'ajax_reset_permissions')) {
            $controller->ajax_reset_permissions();
        } else {
            wp_send_json_error(['message' => __('Method ajax_reset_permissions not found', 'saw-visitors')]);
        }
    }
    
    /**
     * Get module controller instance
     *
     * Helper method to instantiate a module controller.
     *
     * @since 1.0.0
     * @param string $slug Module slug
     * @return object|null Controller instance or null if not found
     */
    private function get_module_controller($slug) {
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            return null;
        }
        
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        if (!class_exists($controller_class)) {
            return null;
        }
        
        return new $controller_class();
    }
    
    /**
     * Handle module AJAX request
     *
     * Universal AJAX handler for module operations.
     * Validates module, loads controller, and calls requested method.
     *
     * @since 1.0.0
     * @param string $slug Module slug
     * @param string $method Controller method to call
     */
    private function handle_module_ajax($slug, $method) {
        // Validate module exists
        $config = SAW_Module_Loader::load($slug);
        if (!$config) {
            wp_send_json_error(['message' => __('Module not found', 'saw-visitors')]);
            return;
        }
        
        // Get controller instance
        $controller = $this->get_module_controller($slug);
        if (!$controller) {
            wp_send_json_error(['message' => __('Controller not found', 'saw-visitors')]);
            return;
        }
        
        // Validate method exists
        if (!method_exists($controller, $method)) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: method name */
                    __('Method %s not found', 'saw-visitors'),
                    $method
                )
            ]);
            return;
        }
        
        // Call method
        $controller->$method();
    }
    
    /**
     * Define WordPress hooks
     *
     * Registers all plugin hooks with WordPress.
     *
     * @since 1.0.0
     */
    private function define_hooks() {
        $this->loader->add_action('init', $this->router, 'register_routes');
        $this->loader->add_filter('query_vars', $this->router, 'register_query_vars');
        $this->loader->add_action('template_redirect', $this->router, 'dispatch', 1);
        
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        
        $this->loader->add_action('admin_menu', $this, 'add_minimal_admin_menu');
    }
    
    /**
     * Add minimal admin menu
     *
     * Creates a simple admin page showing plugin info and active modules.
     *
     * @since 1.0.0
     */
    public function add_minimal_admin_menu() {
        add_menu_page(
            __('SAW Visitors', 'saw-visitors'),
            __('SAW Visitors', 'saw-visitors'),
            'manage_options',
            'saw-visitors-about',
            [$this, 'display_about_page'],
            'dashicons-groups',
            30
        );
    }
    
    /**
     * Display about page
     *
     * Renders plugin information and list of active modules.
     *
     * @since 1.0.0
     */
    public function display_about_page() {
        ?>
        <div class="wrap">
            <h1>
                <?php
                /* translators: %s: plugin version */
                printf(__('SAW Visitors %s', 'saw-visitors'), esc_html($this->version));
                ?>
            </h1>
            <p>
                <?php
                /* translators: %s: plugin version */
                printf(__('Plugin version: %s', 'saw-visitors'), '<strong>' . esc_html($this->version) . '</strong>');
                ?>
            </p>
            <p><?php _e('Uses modular architecture v2.', 'saw-visitors'); ?></p>
            
            <h2><?php _e('Active Modules', 'saw-visitors'); ?></h2>
            <ul>
                <?php
                $modules = SAW_Module_Loader::get_all();
                foreach ($modules as $slug => $config) {
                    printf(
                        '<li>%s (%s)</li>',
                        esc_html($config['plural']),
                        esc_html($slug)
                    );
                }
                ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Enqueue public styles
     *
     * Loads CSS for SAW pages only.
     * Enqueues global styles and module-specific styles.
     *
     * @since 1.0.0
     * @version 1.0.1 - HOTFIX: Always enqueue assets (SAW uses custom routing)
     */
    public function enqueue_public_styles() {
        // HOTFIX: Always enqueue - SAW uses custom routing, not standard WP pages
        // The is_saw_url() check happens in router, assets must be available
        SAW_Asset_Manager::enqueue_global();
        
        $active_module = $this->router->get_active_module();
        if ($active_module) {
            SAW_Asset_Manager::enqueue_module($active_module);
        }
    }
    
    /**
     * Enqueue public scripts
     *
     * Loads JavaScript for SAW pages only.
     * Includes AJAX configuration.
     *
     * @since 1.0.0
     * @version 1.0.1 - HOTFIX: Always enqueue assets (SAW uses custom routing)
     */
    public function enqueue_public_scripts() {
        // HOTFIX: Always enqueue - SAW uses custom routing, not standard WP pages
        // The is_saw_url() check happens in router, assets must be available
        
        $public_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/public.js';
        
        if (file_exists($public_js)) {
            wp_enqueue_script(
                $this->plugin_name . '-public',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/public.js',
                ['jquery'],
                $this->version,
                true
            );
            
            wp_localize_script(
                $this->plugin_name . '-public',
                'sawAjax',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('saw_ajax_nonce')
                ]
            );
        }
    }
    
    /**
     * Check if current URL is SAW page
     *
     * Determines if the current request is for a SAW admin or app page.
     *
     * @since 1.0.0
     * @return bool True if SAW page, false otherwise
     */
    private function is_saw_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
        
        return strpos($request_uri, '/admin/') !== false || 
               strpos($request_uri, '/app/') !== false;
    }
    
    /**
     * Run the plugin
     *
     * Defines hooks and starts the loader.
     *
     * @since 1.0.0
     */
    public function run() {
        $this->define_hooks();
        $this->loader->run();
    }
}