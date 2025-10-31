<?php
/**
 * Asset Manager
 * 
 * Smart loading CSS/JS - globální vždy, module jen když potřeba.
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
    public static function enqueue_global() {
        wp_enqueue_style(
            'saw-base',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-base.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-tables.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-forms',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-forms.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
                    
        wp_enqueue_style(
            'saw-modal',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-modal.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-app.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-app', 'sawGlobal', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'homeUrl' => home_url(),
            'pluginUrl' => SAW_VISITORS_PLUGIN_URL,
            'debug' => defined('SAW_DEBUG') && SAW_DEBUG,
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'customerModalNonce' => wp_create_nonce('saw_customer_modal_nonce'),
            'deleteNonce' => wp_create_nonce('saw_admin_table_nonce'),
        ]);
    }
    
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
                ['saw-base', 'saw-tables', 'saw-forms'],
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
                'singular' => $config['singular'] ?? ucfirst($slug),
                'plural' => $config['plural'] ?? ucfirst($slug) . 's',
                'config' => $config,
            ]);
        }
    }
    
    public static function dequeue_old_assets() {
        $old_css = [
            'saw-customers',
            'saw-account-types',
            'saw-content',
            'saw-companies',
            'saw-departments',
        ];
        
        foreach ($old_css as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
        
        $old_js = [
            'saw-customers',
            'saw-account-types',
            'saw-content',
            'saw-companies',
            'saw-departments',
        ];
        
        foreach ($old_js as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
    }
    
    public static function is_saw_page() {
        $route = get_query_var('saw_route');
        return !empty($route);
    }
}