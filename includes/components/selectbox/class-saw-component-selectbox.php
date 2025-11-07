<?php
/**
 * SAW Selectbox Component
 * 
 * Custom selectbox component with search, AJAX loading, and icon support.
 * Provides a flexible dropdown interface with configurable behavior and styling.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Selectbox
 * @version     4.6.1
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
     * 
     * @since 4.6.1
     * @param string $id     Unique selectbox identifier
     * @param array  $config Configuration array
     */
    public function __construct($id, $config = array()) {
        $this->id = sanitize_key($id);
        $this->config = $this->parse_config($config);
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
     * Enqueues assets and includes the selectbox input template.
     * 
     * @since 4.6.1
     * @return void
     */
    public function render() {
        $this->enqueue_assets();
        
        $id = $this->id;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/selectbox-input.php';
    }
    
    /**
     * Enqueue selectbox assets
     * 
     * Loads CSS and JavaScript files for the selectbox component.
     * Also ensures saw-app script is loaded with global AJAX configuration.
     * 
     * @since 4.6.1
     * @return void
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-selectbox-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/selectbox/saw-selectbox.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-selectbox-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/selectbox/saw-selectbox.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        if (!wp_script_is('saw-app', 'enqueued')) {
            wp_enqueue_script('saw-app');
        }
        
        $existing_data = wp_scripts()->get_data('saw-app', 'data');
        if (empty($existing_data) || strpos($existing_data, 'sawGlobal') === false) {
            wp_localize_script('saw-app', 'sawGlobal', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_ajax_nonce'),
            ));
        }
    }
}