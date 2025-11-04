<?php
/**
 * Asset Manager
 * 
 * @package SAW_Visitors
 * @version 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Asset_Manager 
{
    public static function enqueue_global() {
        // CORE
        wp_enqueue_style(
            'saw-variables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/core/variables.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-reset',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/core/reset.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-typography',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/core/typography.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        // COMPONENTS
        wp_enqueue_style(
            'saw-buttons',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/buttons.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-forms',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/forms.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/tables.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-badges',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/badges.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
    'saw-modals',
    SAW_VISITORS_PLUGIN_URL . 'assets/css/components/modals.css',
    ['saw-variables'],
    time() // ← DOČASNĚ time() místo SAW_VISITORS_VERSION
);
        
        wp_enqueue_style(
            'saw-alerts',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/alerts.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-cards',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/cards.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-search',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/search.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-pagination',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/pagination.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        // LAYOUT
        wp_enqueue_style(
            'saw-grid',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/layout/grid.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-containers',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/layout/containers.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-spacing',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/layout/spacing.css',
            ['saw-variables'],
            SAW_VISITORS_VERSION
        );
        
        // APP (header, sidebar, footer - pokud existují)
        $app_files = ['saw-app-header.css', 'saw-app-sidebar.css', 'saw-app-footer.css', 'saw-app-responsive.css'];
        foreach ($app_files as $file) {
            $file_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $file;
            if (file_exists($file_path)) {
                $handle = str_replace('.css', '', $file);
                wp_enqueue_style(
                    $handle,
                    SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $file,
                    ['saw-variables'],
                    SAW_VISITORS_VERSION
                );
            }
        }
        
        // JAVASCRIPT
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
        
        // POUZE JavaScript (CSS už není potřeba)
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
    
    public static function is_saw_page() {
        $route = get_query_var('saw_route');
        return !empty($route);
    }
}