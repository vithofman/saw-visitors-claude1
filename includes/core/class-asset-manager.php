<?php
/**
 * Asset Manager
 * 
 * Smart loading CSS/JS - globální vždy, module jen když potřeba.
 * 
 * ✅ OPRAVA v4.9.1: Force CSS refresh při změně stránky
 * 
 * @package SAW_Visitors
 * @version 4.9.1
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Asset_Manager 
{
    /**
     * Enqueue global assets
     * 
     * Tyto assety se načítají na všech SAW stránkách.
     * Neobsahují specifické komponenty (modal, selectbox, search),
     * ty si enqueue samy při použití.
     */
    public static function enqueue_global() {
        // Base styles
        wp_enqueue_style(
            'saw-base',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-base.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        // Tables styles
        wp_enqueue_style(
            'saw-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-tables.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        // Forms styles
        wp_enqueue_style(
            'saw-forms',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-forms.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        // jQuery (WordPress built-in)
        wp_enqueue_script('jquery');

        // Main app JS
        wp_enqueue_script(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-app.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script with global config
        wp_localize_script('saw-app', 'sawGlobal', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'homeUrl' => home_url(),
            'pluginUrl' => SAW_VISITORS_PLUGIN_URL,
            'debug' => defined('SAW_DEBUG') && SAW_DEBUG,
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'customerModalNonce' => wp_create_nonce('saw_customer_modal_nonce'),
            'deleteNonce' => wp_create_nonce('saw_admin_table_nonce'),
        ]);
        
        // ✅ OPRAVA: Force CSS refresh při každém page load
        // Přidá dynamickou verzi do URL → browser nepoužije starou cache
        global $wp_styles;
        if (isset($wp_styles->registered)) {
            // Hash z aktuální URL - každá stránka má vlastní verzi
            $route = get_query_var('saw_route') ?: ($_SERVER['REQUEST_URI'] ?? '');
            $route_hash = substr(md5($route), 0, 8);
            
            foreach ($wp_styles->registered as $handle => $style) {
                // Pouze SAW styly
                if (strpos($handle, 'saw-') === 0) {
                    // Přidej hash do verze
                    $wp_styles->registered[$handle]->ver = SAW_VISITORS_VERSION . '.' . $route_hash;
                }
            }
        }
    }
    
    /**
     * Enqueue module-specific assets
     * 
     * @param string $slug Module slug (customers, account-types, etc.)
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
        
        // ✅ OPRAVA: Dynamická verze i pro module CSS
        $route = get_query_var('saw_route') ?: ($_SERVER['REQUEST_URI'] ?? '');
        $route_hash = substr(md5($route), 0, 8);
        $dynamic_version = SAW_VISITORS_VERSION . '.' . $route_hash;
        
        // Module CSS
        $css_file = $module_path . 'styles.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'saw-module-' . $slug,
                $module_url . 'styles.css',
                ['saw-base', 'saw-tables', 'saw-forms'],
                $dynamic_version  // ✅ Použij dynamickou verzi
            );
        }
        
        // Module JS
        $js_file = $module_path . 'scripts.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'saw-module-' . $slug,
                $module_url . 'scripts.js',
                ['jquery', 'saw-app'],
                $dynamic_version,  // ✅ Použij dynamickou verzi
                true
            );
            
            // Localize module-specific data
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
    
    /**
     * Dequeue old/deprecated assets
     * 
     * Odstraní staré assety z předchozích verzí pluginu.
     */
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
    
    /**
     * Check if current page is SAW page
     * 
     * @return bool
     */
    public static function is_saw_page() {
        $route = get_query_var('saw_route');
        return !empty($route);
    }
}