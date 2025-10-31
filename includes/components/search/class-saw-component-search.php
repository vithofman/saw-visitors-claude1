<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Search {
    
    private $entity;
    private $config;
    
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        $this->config = $this->parse_config($config);
    }
    
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
    
    public function render() {
        $this->enqueue_assets();
        
        $entity = $this->entity;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/search-input.php';
    }
    
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