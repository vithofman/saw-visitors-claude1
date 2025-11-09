<?php
/**
 * Component Manager - Centralized Component Initialization
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 * @version    1.0.0 - Initial release
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages component initialization and AJAX handler registration
 *
 * CRITICAL: This class ensures all components are initialized in the correct order
 * and at the correct time (during 'init' hook), preventing timing issues with AJAX handlers.
 *
 * @since 1.0.0
 */
class SAW_Component_Manager {
    
    /**
     * Singleton instance
     *
     * @since 1.0.0
     * @var SAW_Component_Manager|null
     */
    private static $instance = null;
    
    /**
     * Registered components
     *
     * @since 1.0.0
     * @var array
     */
    private $components = [];
    
    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return SAW_Component_Manager
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     *
     * Initializes component manager and registers WordPress hooks.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Register all components on init hook (priority 5 - before default 10)
        add_action('init', array($this, 'register_all_components'), 5);
    }
    
    /**
     * Register all components
     *
     * CRITICAL: This method runs on 'init' hook with priority 5,
     * ensuring all AJAX handlers are registered before WordPress processes AJAX requests.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_all_components() {
        // Load component classes
        $this->load_component_classes();
        
        // Register AJAX handlers for all components
        $this->register_ajax_handlers();
    }
    
    /**
     * Load all component classes
     *
     * Loads PHP class files for all components that have AJAX functionality.
     *
     * @since 1.0.0
     * @return void
     */
    private function load_component_classes() {
        $components = array(
            'customer-switcher' => 'includes/components/customer-switcher/class-saw-component-customer-switcher.php',
            'branch-switcher'   => 'includes/components/branch-switcher/class-saw-component-branch-switcher.php',
            'language-switcher' => 'includes/components/language-switcher/class-saw-component-language-switcher.php',
            'selectbox'         => 'includes/components/selectbox/class-saw-component-selectbox.php',
            'search'            => 'includes/components/search/class-saw-component-search.php',
            'modal'             => 'includes/components/modal/class-saw-component-modal.php',
            'admin-table'       => 'includes/components/admin-table/class-saw-component-admin-table.php',
        );
        
        foreach ($components as $slug => $path) {
            $full_path = SAW_VISITORS_PLUGIN_DIR . $path;
            
            if (file_exists($full_path)) {
                require_once $full_path;
                $this->components[$slug] = $path;
            }
        }
    }
    
    /**
     * Register AJAX handlers for all components
     *
     * CRITICAL: Calls static register_ajax_handlers() method on each component class
     * that has AJAX functionality. This ensures all AJAX endpoints are registered
     * before WordPress processes any AJAX requests.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_ajax_handlers() {
        $ajax_components = array(
            'SAW_Component_Customer_Switcher',
            'SAW_Component_Branch_Switcher',
            'SAW_Component_Language_Switcher',
        );
        
        foreach ($ajax_components as $class_name) {
            if (class_exists($class_name) && method_exists($class_name, 'register_ajax_handlers')) {
                call_user_func(array($class_name, 'register_ajax_handlers'));
            }
        }
    }
    
    /**
     * Get loaded components
     *
     * Returns list of successfully loaded components.
     *
     * @since 1.0.0
     * @return array Component slugs and paths
     */
    public function get_loaded_components() {
        return $this->components;
    }
    
    /**
     * Check if component is loaded
     *
     * @since 1.0.0
     * @param string $slug Component slug
     * @return bool True if loaded
     */
    public function is_component_loaded($slug) {
        return isset($this->components[$slug]);
    }
}