<?php
/**
 * Asset Manager
 * 
 * Smart loading CSS/JS - globální vždy, module jen když potřeba.
 * Řeší problém "ztracení stylů" při navigaci.
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
     * Enqueue global assets (na VŠECH SAW stránkách)
     * 
     * Tyhle styly a scripty se načtou vždycky, protože jsou potřeba všude.
     */
    public static function enqueue_global() {
        // Base CSS - základní styly pro celou aplikaci
        wp_enqueue_style(
            'saw-base',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/base.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        // Tables CSS - admin tabulky (list views)
        // Načte se VžDY, i na form stránkách (pak je hidden, ale připravený)
        wp_enqueue_style(
            'saw-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/tables.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        // Forms CSS - formuláře
        wp_enqueue_style(
            'saw-forms',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/forms.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        // Components CSS - buttony, badges, etc.
        wp_enqueue_style(
            'saw-components',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/components.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        // Modal CSS - modal okno pro detail
        wp_enqueue_style(
            'saw-modal',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/modal.css',
            ['saw-base'],
            SAW_VISITORS_VERSION
        );
        
        // Global JS
        wp_enqueue_script('jquery'); // WordPress jQuery
        
        wp_enqueue_script(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/global/app.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize global data
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
    
    /**
     * Enqueue module-specific assets
     * 
     * Načte CSS/JS jen pro konkrétní modul (customers, account-types, atd.)
     * 
     * @param string $slug Module slug (např. 'customers')
     */
    public static function enqueue_module($slug) {
        // Load module manifest
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
        
        // CSS
        $css_file = $module_path . 'styles.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'saw-module-' . $slug,
                $module_url . 'styles.css',
                ['saw-base', 'saw-tables', 'saw-forms'], // Závislosti
                SAW_VISITORS_VERSION
            );
        }
        
        // JS
        $js_file = $module_path . 'scripts.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'saw-module-' . $slug,
                $module_url . 'scripts.js',
                ['jquery', 'saw-app'], // Závislosti
                SAW_VISITORS_VERSION,
                true // Load in footer
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
     * Dequeue old assets (cleanup)
     * 
     * Odstraní staré CSS/JS z původního systému, aby nedělaly konflikty.
     * Volej to v class-saw-visitors.php při inicializaci.
     */
    public static function dequeue_old_assets() {
        // List starých CSS souborů
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
        
        // List starých JS souborů
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
     * Is SAW admin page?
     * 
     * Helper pro detekci, jestli jsme na SAW stránce.
     */
    public static function is_saw_page() {
        $path = get_query_var('saw_path');
        return !empty($path);
    }
    
    /**
     * Get current module
     * 
     * Zjistí, který modul je teď aktivní (z URL).
     */
    public static function get_current_module() {
        if (!class_exists('SAW_Router')) {
            return null;
        }
        
        $router = new SAW_Router();
        return $router->get_active_module();
    }
}