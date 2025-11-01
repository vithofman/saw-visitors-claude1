<?php
/**
 * Color Picker Component
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Color_Picker {
    
    public function __construct() {
        // Constructor může být prázdný, zatím nepotřebujeme init
    }
    
    public function enqueue_assets() {
        wp_enqueue_style(
            'saw-color-picker-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/color-picker/color-picker.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-color-picker-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/color-picker/color-picker.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
}
