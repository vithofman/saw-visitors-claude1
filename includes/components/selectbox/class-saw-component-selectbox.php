<?php
/**
 * SAW Selectbox Component
 * 
 * Custom selectbox component with search, AJAX loading, and icon support.
 * Provides a flexible dropdown interface with configurable behavior and styling.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Selectbox
 * @version     4.6.3 - FIXED: Removed duplicit wp_localize_script call
 * @since       4.6.1
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Selectbox Component Class
 * 
 * Handles selectbox rendering with configurable options, AJAX support,
 * and search functionality.
 * 
 * @since 4.6.1
 */
class SAW_Component_Selectbox {
    
    /**
     * Selectbox identifier
     * 
     * @since 4.6.1
     * @var string
     */
    private $id;
    
    /**
     * Selectbox configuration
     * 
     * @since 4.6.1
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     * 
     * Initializes the selectbox component with ID and configuration.
     * CRITICAL FIX: Enqueues assets immediately to prevent FOUC.
     * 
     * @since 4.6.1
     * @param string $id     Unique selectbox identifier
     * @param array  $config Configuration array
     */
    public function __construct($id, $config = array()) {
        $this->id = sanitize_key($id);
        $this->config = $this->parse_config($config);
        $this->enqueue_assets();
    }
    
    /**
     * Parse and merge configuration with defaults
     * 
     * Merges user-provided configuration with default values.
     * 
     * @since 4.6.1
     * @param array $config User configuration
     * @return array Merged configuration
     */
    private function parse_config($config) {
        $defaults = array(
            'options' => array(),
            'selected' => '',
            'placeholder' => 'Vyberte...',
            'ajax_enabled' => false,
            'ajax_action' => '',
            'searchable' => false,
            'allow_empty' => true,
            'empty_label' => '',
            'on_change' => '',
            'custom_class' => '',
            'show_icons' => false,
            'grouped' => false,
            'name' => '',
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Render the selectbox component
     * 
     * Includes the selectbox input template.
     * Assets are already enqueued in constructor.
     * 
     * @since 4.6.1
     * @return void
     */
    public function render() {
        $id = $this->id;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/selectbox-input.php';
    }
    
    /**
     * Enqueue selectbox assets
     * 
     * Loads CSS and JavaScript files for the selectbox component.
     * Relies on SAW_Asset_Loader for sawGlobal initialization.
     * Called from constructor to ensure assets load before wp_head().
     * 
     * @since 4.6.1
     * @return void
     */
    private function enqueue_assets() {
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
    }
}