<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Selectbox {
    
    private $id;
    private $config;
    
    public function __construct($id, $config = array()) {
        $this->id = sanitize_key($id);
        $this->config = $this->parse_config($config);
    }
    
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
    
    public function render() {
        $this->enqueue_assets();
        
        $id = $this->id;
        $config = $this->config;
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/selectbox-input.php';
    }
    
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