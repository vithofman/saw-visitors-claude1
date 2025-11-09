<?php
/**
 * SAW Search Component
 * 
 * Provides a search input interface with optional AJAX functionality,
 * clear button, and search result information display.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Search
 * @version     1.2.0 - FIXED: Enqueue assets in constructor
 * @since       1.0.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Search Component Class
 * 
 * Handles search input rendering with configurable behavior and styling.
 * 
 * @since 1.0.0
 */
class SAW_Component_Search {
    
    /**
     * Entity identifier
     * 
     * @since 1.0.0
     * @var string
     */
    private $entity;
    
    /**
     * Search configuration
     * 
     * @since 1.0.0
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     * 
     * Initializes the search component with entity and configuration.
     * CRITICAL FIX: Enqueues assets immediately to prevent FOUC.
     * 
     * @since 1.0.0
     * @param string $entity Entity identifier (e.g., 'customers', 'visitors')
     * @param array  $config Configuration array
     */
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        $this->config = $this->parse_config($config);
        $this->enqueue_assets();
    }
    
    /**
     * Parse and merge configuration with defaults
     * 
     * Merges user-provided configuration with default values.
     * 
     * @since 1.0.0
     * @param array $config User configuration
     * @return array Merged configuration
     */
    private function parse_config($config) {
        $defaults = array(
            'placeholder' => 'Hledat...',
            'search_value' => '',
            'ajax_enabled' => true,
            'ajax_action' => 'saw_search',
            'show_clear' => true,
            'show_button' => true,
            'show_info_banner' => true,
            'info_banner_label' => 'Vyhledávání:',
            'clear_url' => '',
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Render the search component
     * 
     * Includes the search input template.
     * Assets are already enqueued in constructor.
     * 
     * @since 1.0.0
     * @return void
     */
    public function render() {
        $entity = $this->entity;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/search-input.php';
    }
    
    /**
     * Enqueue search assets
     * 
     * Loads CSS and JavaScript files for the search component.
     * Called from constructor to ensure assets load before wp_head().
     * 
     * @since 1.0.0
     * @return void
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-search-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/search/saw-search.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-search-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/search/saw-search.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
}