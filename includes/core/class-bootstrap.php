<?php
/**
 * Bootstrap - Master Initialization
 *
 * Central initialization class for the SAW Visitors plugin.
 * Handles dependency loading, service registration, and hook setup.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap Class
 *
 * Master initialization class that replaces the old SAW_Visitors class.
 * Provides clean, organized plugin initialization.
 *
 * @since 5.0.0
 */
class SAW_Bootstrap {
    
    /**
     * Whether bootstrap has been initialized
     *
     * @since 5.0.0
     * @var bool
     */
    private static $initialized = false;
    
    /**
     * Initialize plugin bootstrap
     *
     * Main entry point for plugin initialization.
     * Should be called on 'plugins_loaded' hook with priority 10.
     *
     * @since 5.0.0
     * @return void
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$initialized = true;
        
        // Load core files first (must succeed)
        try {
            self::load_core_files();
        } catch (Throwable $e) {
            error_log('SAW Bootstrap CRITICAL: Failed to load core files: ' . $e->getMessage());
            return; // Can't continue without core files
        }
        
        // Register services (continue even if some fail)
        try {
            self::register_services();
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Error registering services: ' . $e->getMessage());
            // Continue - router might still work
        }
        
        // Initialize services (continue even if some fail)
        try {
            self::init_services();
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Error initializing services: ' . $e->getMessage());
            // Continue - router hooks must be registered
        }
        
        // Register hooks (CRITICAL - must always run)
        try {
            self::register_hooks();
        } catch (Throwable $e) {
            error_log('SAW Bootstrap CRITICAL: Failed to register hooks: ' . $e->getMessage());
            error_log('SAW Bootstrap: Hook registration error trace: ' . $e->getTraceAsString());
            // Plugin won't work without hooks, but at least log the error
        }
    }
    
    /**
     * Load core files
     *
     * Loads all required core files in the correct order.
     *
     * @since 5.0.0
     * @return void
     */
    private static function load_core_files() {
        $files = [
            // Logger (must be first)
            'includes/core/class-saw-logger.php',
            
            // Service Container and Registry
            'includes/core/class-service-container.php',
            'includes/core/class-ajax-registry.php',
            'includes/core/class-hook-registry.php',
            
            // Base classes
            'includes/base/trait-ajax-handlers.php',
            'includes/base/class-base-model.php',
            'includes/base/class-base-controller.php',
            
            // Core systems
            'includes/core/class-module-loader.php',
            'includes/core/class-asset-loader.php',
            'includes/core/class-saw-router.php',
            'includes/core/class-saw-component-manager.php',
        ];
        
        foreach ($files as $file) {
            $path = SAW_VISITORS_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        self::load_optional_files();
    }
    
    /**
     * Load optional files
     *
     * Loads optional core files that may not exist in all installations.
     *
     * @since 5.0.0
     * @return void
     */
    private static function load_optional_files() {
        $optional = [
            'includes/core/class-saw-session-manager.php',
            'includes/core/class-saw-context.php',
            'includes/core/class-saw-session.php',
            'includes/core/class-saw-user-branches.php',
            'includes/core/class-saw-error-handler.php',
            'includes/core/class-saw-audit.php',
            'includes/auth/class-saw-auth.php',
            'includes/auth/class-saw-password.php',
            'includes/database/class-saw-database.php',
        ];
        
        foreach ($optional as $file) {
            $path = SAW_VISITORS_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
    
    /**
     * Register services
     *
     * Registers all core services in the Service Container.
     *
     * @since 5.0.0
     * @return void
     */
    private static function register_services() {
        // Router (CRITICAL - must always register)
        try {
            SAW_Service_Container::register('router', function() {
                return new SAW_Router();
            });
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to register router: ' . $e->getMessage());
        }
        
        // Module Loader
        try {
            SAW_Service_Container::register('module_loader', function() {
                if (class_exists('SAW_Module_Loader')) {
                    SAW_Module_Loader::discover();
                }
                return SAW_Module_Loader::class;
            }, false); // Not singleton, return class name
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to register module loader: ' . $e->getMessage());
        }
        
        // Component Manager
        try {
            SAW_Service_Container::register('component_manager', function() {
                if (class_exists('SAW_Component_Manager')) {
                    return SAW_Component_Manager::instance();
                }
                return null;
            });
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to register component manager: ' . $e->getMessage());
        }
        
        // AJAX Registry
        try {
            SAW_Service_Container::register('ajax_registry', function() {
                return new SAW_AJAX_Registry();
            });
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to register AJAX registry: ' . $e->getMessage());
        }
        
        // Session Manager (optional)
        if (class_exists('SAW_Session_Manager')) {
            try {
                SAW_Service_Container::register('session', function() {
                    if (method_exists('SAW_Session_Manager', 'instance')) {
                        return SAW_Session_Manager::instance();
                    }
                    return null;
                });
            } catch (Throwable $e) {
                error_log('SAW Bootstrap: Failed to register session manager: ' . $e->getMessage());
            }
        }
        
        // Context (optional)
        if (class_exists('SAW_Context')) {
            try {
                SAW_Service_Container::register('context', function() {
                    if (method_exists('SAW_Context', 'instance')) {
                        return SAW_Context::instance();
                    }
                    return null;
                });
            } catch (Throwable $e) {
                error_log('SAW Bootstrap: Failed to register context: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Initialize services
     *
     * Initializes all registered services in the correct order.
     *
     * @since 5.0.0
     * @return void
     */
    private static function init_services() {
        // Initialize Module Loader
        try {
            if (SAW_Service_Container::has('module_loader')) {
                SAW_Service_Container::get('module_loader');
            }
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to init module loader: ' . $e->getMessage());
        }
        
        // Initialize Component Manager
        try {
            if (SAW_Service_Container::has('component_manager')) {
                $component_manager = SAW_Service_Container::get('component_manager');
                if ($component_manager && method_exists($component_manager, 'register_all_components')) {
                    $component_manager->register_all_components();
                }
            }
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to init component manager: ' . $e->getMessage());
        }
        
        // Initialize AJAX Registry
        try {
            if (SAW_Service_Container::has('ajax_registry')) {
                $ajax_registry = SAW_Service_Container::get('ajax_registry');
                $ajax_registry->init();
            }
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to init AJAX registry: ' . $e->getMessage());
        }
        
        // Initialize Session Manager (optional)
        try {
            if (SAW_Service_Container::has('session')) {
                SAW_Service_Container::get('session');
            }
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to init session manager: ' . $e->getMessage());
        }
        
        // Initialize Context (optional)
        try {
            if (SAW_Service_Container::has('context')) {
                SAW_Service_Container::get('context');
            }
        } catch (Throwable $e) {
            error_log('SAW Bootstrap: Failed to init context: ' . $e->getMessage());
        }
    }
    
    /**
     * Register WordPress hooks
     *
     * Registers all WordPress actions and filters.
     *
     * @since 5.0.0
     * @return void
     */
    private static function register_hooks() {
        // CRITICAL: Router must always be registered, even if other services fail
        try {
            // Ensure router class is loaded
            if (!class_exists('SAW_Router')) {
                $router_file = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
                if (file_exists($router_file)) {
                    require_once $router_file;
                } else {
                    error_log('SAW Bootstrap CRITICAL: Router file not found: ' . $router_file);
                    return;
                }
            }
            
            if (!class_exists('SAW_Router')) {
                error_log('SAW Bootstrap CRITICAL: Router class not found after loading file');
                return;
            }
            
            if (!SAW_Service_Container::has('router')) {
                // Try to create router directly if not in container
                $router = new SAW_Router();
            } else {
                $router = SAW_Service_Container::get('router');
            }
            
            if (!$router || !method_exists($router, 'register_routes')) {
                error_log('SAW Bootstrap CRITICAL: Router invalid or missing register_routes method');
                return;
            }
            
            // Router hooks (CRITICAL for plugin to work)
            add_action('init', [$router, 'register_routes'], 10);
            add_action('init', function() use ($router) {
                // Ensure query vars are registered
                add_filter('query_vars', [$router, 'register_query_vars']);
                
                // Flush rewrite rules if needed (only once per version)
                $flushed_version = get_option('saw_rewrite_rules_flushed');
                if ($flushed_version !== SAW_VISITORS_VERSION) {
                    flush_rewrite_rules(false); // false = don't hard flush, just update
                    update_option('saw_rewrite_rules_flushed', SAW_VISITORS_VERSION);
                    error_log('SAW Bootstrap: Rewrite rules flushed for version ' . SAW_VISITORS_VERSION);
                }
            }, 20);
            add_filter('query_vars', [$router, 'register_query_vars']);
            add_action('template_redirect', [$router, 'dispatch'], 1);
            
            // Debug: Log that router is registered
            error_log('SAW Bootstrap: Router hooks registered successfully');
        } catch (Throwable $e) {
            error_log('SAW Bootstrap CRITICAL: Failed to register router hooks: ' . $e->getMessage());
            error_log('SAW Bootstrap: Router error trace: ' . $e->getTraceAsString());
            // Don't return - continue with other hooks
        }
        
        // Asset loading
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        
        // Block wp-admin access for SAW roles
        add_action('admin_init', [__CLASS__, 'block_wp_admin_access']);
        
        // Widgets
        add_action('widgets_init', [__CLASS__, 'register_widgets']);
    }
    
    /**
     * Enqueue assets
     *
     * Loads CSS and JS assets for the current page/module.
     *
     * @since 5.0.0
     * @return void
     */
    public static function enqueue_assets() {
        if (!class_exists('SAW_Asset_Loader')) {
            error_log('SAW Bootstrap: SAW_Asset_Loader class not found');
            return;
        }
        
        try {
            // CRITICAL: Always enqueue global assets first
            SAW_Asset_Loader::enqueue_global();
            
            // Enqueue module-specific assets
            // CRITICAL: Try multiple methods to get active module
            $active_module = null;
            
            // Method 1: From router service
            if (SAW_Service_Container::has('router')) {
                try {
                    $router = SAW_Service_Container::get('router');
                    if ($router && method_exists($router, 'get_active_module')) {
                        $active_module = $router->get_active_module();
                        error_log('SAW Bootstrap: Active module from router: ' . ($active_module ?: 'null'));
                    }
                } catch (Exception $e) {
                    error_log('SAW Bootstrap: Error getting router: ' . $e->getMessage());
                }
            }
            
            // Method 2: From query var directly (fallback)
            if (empty($active_module)) {
                $path = get_query_var('saw_path');
                if (!empty($path)) {
                    $clean_path = trim($path, '/');
                    $segments = explode('/', $clean_path);
                    if (!empty($segments[0]) && !is_numeric($segments[0])) {
                        $active_module = $segments[0];
                        error_log('SAW Bootstrap: Active module from query var: ' . $active_module);
                    }
                }
            }
            
            if ($active_module && $active_module !== 'dashboard') {
                SAW_Asset_Loader::enqueue_module($active_module);
            }
        } catch (Exception $e) {
            // Log error if logger is available
            error_log('SAW Bootstrap: Failed to enqueue assets: ' . $e->getMessage());
            error_log('SAW Bootstrap: Asset enqueue error trace: ' . $e->getTraceAsString());
            if (function_exists('saw_log_error')) {
                saw_log_error('Failed to enqueue assets: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register admin menu
     *
     * Creates a minimal admin menu page showing plugin info.
     *
     * @since 5.0.0
     * @return void
     */
    public static function register_admin_menu() {
        add_menu_page(
            __('SAW Visitors', 'saw-visitors'),
            __('SAW Visitors', 'saw-visitors'),
            'manage_options',
            'saw-visitors-about',
            [__CLASS__, 'render_about_page'],
            'dashicons-groups',
            30
        );
    }
    
    /**
     * Render about page
     *
     * Displays plugin information and active modules.
     *
     * @since 5.0.0
     * @return void
     */
    public static function render_about_page() {
        ?>
        <div class="wrap">
            <h1>
                <?php
                printf(
                    __('SAW Visitors %s', 'saw-visitors'),
                    esc_html(SAW_VISITORS_VERSION)
                );
                ?>
            </h1>
            <p>
                <strong><?php _e('Version:', 'saw-visitors'); ?></strong>
                <?php echo esc_html(SAW_VISITORS_VERSION); ?>
            </p>
            <p><?php _e('Modular architecture with centralized bootstrap.', 'saw-visitors'); ?></p>
            
            <h2><?php _e('Active Modules', 'saw-visitors'); ?></h2>
            <ul>
                <?php
                if (class_exists('SAW_Module_Loader')) {
                    $modules = SAW_Module_Loader::get_all();
                    foreach ($modules as $slug => $config) {
                        printf(
                            '<li>%s (%s)</li>',
                            esc_html($config['plural'] ?? $slug),
                            esc_html($slug)
                        );
                    }
                }
                ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Block WordPress admin access for SAW roles
     *
     * Redirects users with SAW-specific roles away from wp-admin.
     *
     * @since 5.0.0
     * @return void
     */
    public static function block_wp_admin_access() {
        // Don't redirect AJAX or REST API requests
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return;
        }
        
        $saw_roles = ['saw_admin', 'saw_super_manager', 'saw_manager', 'saw_terminal'];
        
        // User doesn't have any SAW role → skip
        if (!array_intersect($saw_roles, (array) $user->roles)) {
            return;
        }
        
        // Redirect away from wp-admin
        wp_safe_redirect(home_url('/login/'));
        exit;
    }
    
    /**
     * Register widgets
     *
     * Registers plugin widgets.
     *
     * @since 5.0.0
     * @return void
     */
    public static function register_widgets() {
        // Current Visitors Widget
        $widget_file = SAW_VISITORS_PLUGIN_DIR . 'includes/widgets/visitors/current-visitors/widget-current-visitors.php';
        if (file_exists($widget_file)) {
            require_once $widget_file;
            if (class_exists('SAW_Widget_Current_Visitors')) {
                SAW_Widget_Current_Visitors::init();
            }
        }
        
        // Dashboard
        $dashboard_file = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/dashboard/dashboard.php';
        if (file_exists($dashboard_file)) {
            require_once $dashboard_file;
        }
    }
}

