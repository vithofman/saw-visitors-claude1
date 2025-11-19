<?php
/**
 * SAW Modal Component
 * 
 * Global component for modal windows in the application.
 * Supports static content and dynamic AJAX loading with configurable
 * behavior, actions, and styling options.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Modal
 * @version     4.0.0
 * @since       4.6.1
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Modal Component Class
 * 
 * Handles modal creation, configuration, rendering, and asset management.
 * 
 * @since 4.6.1
 */
class SAW_Component_Modal {
    
    /**
     * Unique modal ID
     * 
     * @since 4.6.1
     * @var string
     */
    private $id;
    
    /**
     * Modal configuration
     * 
     * @since 4.6.1
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     * 
     * Initializes the modal with a unique ID and configuration.
     * 
     * @since 4.6.1
     * @param string $id     Unique modal identifier
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
            // Content settings
            'title' => '',
            'content' => '',
            
            // AJAX settings
            'ajax_enabled' => false,
            'ajax_action' => '',
            'ajax_data' => array(),
            'ajax_nonce' => '',
            
            // Size options: sm, md, lg, xl
            'size' => 'md',
            
            // UI options
            'show_close' => true,
            'show_footer' => false,
            'footer_buttons' => array(),
            
            // Header actions
            'header_actions' => array(),
            
            // Behavior
            'close_on_backdrop' => true,
            'close_on_escape' => true,
            
            // Custom styling
            'custom_class' => '',
            
            // Auto-open on render
            'auto_open' => false,
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Render the modal
     * 
     * Enqueues assets and includes the modal template.
     * 
     * @since 4.6.1
     * @return void
     */
    public function render() {
        $this->enqueue_assets();
        
        $id = $this->id;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/modal-template.php';
    }
    
    /**
     * Enqueue modal assets
     * 
     * Loads JavaScript and localizes script with AJAX configuration.
     * CSS is loaded globally.
     * 
     * @since 4.6.1
     * @return void
     */
    private function enqueue_assets() {
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
    }
    
    /**
     * Get modal ID
     * 
     * Returns the unique modal identifier.
     * 
     * @since 4.6.1
     * @return string Modal ID
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get modal configuration
     * 
     * Returns the complete modal configuration array.
     * 
     * @since 4.6.1
     * @return array Modal configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Static helper: Open modal via JavaScript
     * 
     * Generates JavaScript code to open a modal with optional data.
     * 
     * @since 4.6.1
     * @param string $modal_id Modal ID to open
     * @param array  $data     Optional data to pass
     * @return string JavaScript code
     */
    public static function open_js($modal_id, $data = array()) {
        $data_json = !empty($data) ? wp_json_encode($data) : '{}';
        return "SAWModal.open('" . esc_js($modal_id) . "', " . $data_json . ");";
    }
    
    /**
     * Static helper: Close modal via JavaScript
     * 
     * Generates JavaScript code to close a modal.
     * 
     * @since 4.6.1
     * @param string $modal_id Modal ID to close
     * @return string JavaScript code
     */
    public static function close_js($modal_id) {
        return "SAWModal.close('" . esc_js($modal_id) . "');";
    }
}