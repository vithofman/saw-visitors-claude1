<?php
/**
 * Module Style Manager
 * 
 * @package SAW_Visitors
 * @version 4.0.0 - NO CLEANUP VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Style_Manager 
{
    private static $instance = null;
    private $active_module = null;
    private $css_cache = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Žádný cleanup - necháme to na PHP full page reload
    }
    
    public function get_module_css($module_slug) {
        if (isset($this->css_cache[$module_slug])) {
            return $this->css_cache[$module_slug];
        }
        
        $css_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/' . $module_slug . '/styles.css';
        
        if (file_exists($css_path)) {
            $css_content = file_get_contents($css_path);
            $this->css_cache[$module_slug] = $css_content;
            return $css_content;
        }
        
        return '';
    }
    
    public function inject_module_css($module_slug) {
        $css_content = $this->get_module_css($module_slug);
        
        if (empty($css_content)) {
            return '';
        }
        
        $this->active_module = $module_slug;
        
        $output = sprintf(
            '<style id="saw-module-css-%s" data-saw-module="%s" type="text/css">%s</style>',
            esc_attr($module_slug),
            esc_attr($module_slug),
            $css_content
        );
        
        return $output;
    }
    
    public function get_active_module() {
        return $this->active_module;
    }
}