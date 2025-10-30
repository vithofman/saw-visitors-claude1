<?php
/**
 * Asset Manager
 * 
 * Smart CSS/JS loading - globální vždy, module-specific jen když potřeba
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Asset_Manager 
{
    /**
     * Enqueue global assets
     */
    public static function enqueue_global() {
        wp_enqueue_style(
            'saw-base',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/base.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        if (self::is_list_page()) {
            wp_enqueue_style(
                'saw-tables',
                SAW_VISITORS_PLUGIN_URL . 'assets/global/tables.css',
                ['saw-base'],
                SAW_VISITORS_VERSION
            );
        }
        
        if (self::is_form_page()) {
            wp_enqueue_style(
                'saw-forms',
                SAW_VISITORS_PLUGIN_URL . 'assets/global/forms.css',
                ['saw-base'],
                SAW_VISITORS_VERSION
            );
        }
        
        wp_enqueue_script(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/app.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
    }
    
    /**
     * Enqueue module-specific assets
     */
    public static function enqueue_module($slug) {
        $modules = SAW_Module_Loader::get_all();
        
        if (!isset($modules[$slug])) {
            return;
        }
        
        $config = $modules[$slug];
        $module_path = $config['path'];
        $module_url = str_replace(
            SAW_VISITORS_PLUGIN_DIR,
            SAW_VISITORS_PLUGIN_URL,
            $module_path
        );
        
        $css_file = $module_path . 'styles.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'saw-module-' . $slug,
                $module_url . 'styles.css',
                ['saw-base'],
                SAW_VISITORS_VERSION
            );
        }
        
        $js_file = $module_path . 'scripts.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'saw-module-' . $slug,
                $module_url . 'scripts.js',
                ['jquery', 'saw-app'],
                SAW_VISITORS_VERSION,
                true
            );
            
            wp_localize_script('saw-module-' . $slug, 'sawModule', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_' . $slug . '_ajax'),
                'entity' => $slug,
                'config' => $config
            ]);
        }
    }
    
    /**
     * Is list page?
     */
    private static function is_list_page() {
        $path = get_query_var('saw_path');
        return !empty($path) && 
               strpos($path, '/new') === false && 
               strpos($path, '/edit') === false &&
               strpos($path, '/create') === false;
    }
    
    /**
     * Is form page?
     */
    private static function is_form_page() {
        $path = get_query_var('saw_path');
        return !empty($path) && (
            strpos($path, '/new') !== false || 
            strpos($path, '/edit') !== false ||
            strpos($path, '/create') !== false
        );
    }
}
