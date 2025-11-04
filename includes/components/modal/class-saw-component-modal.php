<?php
/**
 * SAW Modal Component
 * 
 * Globální komponenta pro modální okna v aplikaci.
 * Podporuje statický obsah i dynamické načítání přes AJAX.
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Modal {
    
    /**
     * Unique modal ID
     * @var string
     */
    private $id;
    
    /**
     * Modal configuration
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     * 
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
     * Enqueues assets and includes template
     */
    public function render() {
        $this->enqueue_assets();
        
        $id = $this->id;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/modal-template.php';
    }
    
    /**
     * Enqueue modal assets (JS only - CSS is global)
     */
    private function enqueue_assets() {
        // Modal JS
        wp_enqueue_script(
            'saw-modal-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/modal/saw-modal.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script
        $localized_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
        );
        
        if (!empty($this->config['ajax_nonce'])) {
            $localized_data['customNonce'] = $this->config['ajax_nonce'];
        }
        
        wp_localize_script(
            'saw-modal-component',
            'sawModalGlobal',
            $localized_data
        );
    }
    
    /**
     * Get modal ID
     * 
     * @return string
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get modal configuration
     * 
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Static helper: Open modal via JS
     * 
     * @param string $modal_id Modal ID to open
     * @param array  $data     Optional data to pass
     * @return string JavaScript code
     */
    public static function open_js($modal_id, $data = array()) {
        $data_json = !empty($data) ? wp_json_encode($data) : '{}';
        return "SAWModal.open('" . esc_js($modal_id) . "', " . $data_json . ");";
    }
    
    /**
     * Static helper: Close modal via JS
     * 
     * @param string $modal_id Modal ID to close
     * @return string JavaScript code
     */
    public static function close_js($modal_id) {
        return "SAWModal.close('" . esc_js($modal_id) . "');";
    }
}