<?php
/**
 * Asset Manager - Centralized CSS/JS Asset Loading
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages asset enqueueing for global and module-specific resources
 *
 * @since 1.0.0
 */
class SAW_Asset_Manager {
    
    /**
     * Core CSS files to enqueue
     *
     * @since 1.0.0
     * @var array
     */
    const CORE_STYLES = [
        'saw-variables'  => 'core/variables.css',
        'saw-reset'      => 'core/reset.css',
        'saw-typography' => 'core/typography.css'
    ];
    
    /**
     * Component CSS files to enqueue
     *
     * @since 1.0.0
     * @var array
     */
    const COMPONENT_STYLES = [
        'saw-buttons'             => 'components/buttons.css',
        'saw-forms'               => 'components/forms.css',
        'saw-tables'              => 'components/tables.css',
        'saw-badges'              => 'components/badges.css',
        'saw-modals'              => 'components/modals.css',
        'saw-alerts'              => 'components/alerts.css',
        'saw-cards'               => 'components/cards.css',
        'saw-search'              => 'components/search.css',
        'saw-pagination'          => 'components/pagination.css',
        'saw-table-column-types'  => 'components/table-column-types.css'
    ];
    
    /**
     * Layout CSS files to enqueue
     *
     * @since 1.0.0
     * @var array
     */
    const LAYOUT_STYLES = [
        'saw-grid'       => 'layout/grid.css',
        'saw-containers' => 'layout/containers.css',
        'saw-spacing'    => 'layout/spacing.css'
    ];
    
    /**
     * Optional app-level CSS files
     *
     * @since 1.0.0
     * @var array
     */
    const APP_STYLES = [
        'saw-app-header'     => 'saw-app-header.css',
        'saw-app-sidebar'    => 'saw-app-sidebar.css',
        'saw-app-footer'     => 'saw-app-footer.css',
        'saw-app-responsive' => 'saw-app-responsive.css'
    ];
    
    /**
     * Enqueue global assets (CSS and JS)
     *
     * @since 1.0.0
     */
    public static function enqueue_global() {
        self::enqueue_core_styles();
        self::enqueue_component_styles();
        self::enqueue_layout_styles();
        self::enqueue_app_styles();
        self::enqueue_global_scripts();
    }
    
    /**
     * Enqueue core CSS files
     *
     * @since 1.0.0
     */
    private static function enqueue_core_styles() {
        foreach (self::CORE_STYLES as $handle => $path) {
            $deps = ($handle === 'saw-variables') ? [] : ['saw-variables'];
            self::enqueue_style($handle, 'css/' . $path, $deps);
        }
    }
    
    /**
     * Enqueue component CSS files
     *
     * @since 1.0.0
     */
    private static function enqueue_component_styles() {
        foreach (self::COMPONENT_STYLES as $handle => $path) {
            self::enqueue_style($handle, 'css/' . $path, ['saw-variables']);
        }
    }
    
    /**
     * Enqueue layout CSS files
     *
     * @since 1.0.0
     */
    private static function enqueue_layout_styles() {
        foreach (self::LAYOUT_STYLES as $handle => $path) {
            self::enqueue_style($handle, 'css/' . $path, ['saw-variables']);
        }
    }
    
    /**
     * Enqueue optional app-level CSS files
     *
     * @since 1.0.0
     */
    private static function enqueue_app_styles() {
        foreach (self::APP_STYLES as $handle => $file) {
            $file_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $file;
            
            if (file_exists($file_path)) {
                self::enqueue_style($handle, 'css/' . $file, ['saw-variables']);
            }
        }
    }
    
    /**
     * Enqueue global JavaScript
     *
     * @since 1.0.0
     */
    private static function enqueue_global_scripts() {
        wp_enqueue_script('jquery');
        
        wp_enqueue_script(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-app.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-app', 'sawGlobal', [
            'ajaxurl'             => admin_url('admin-ajax.php'),
            'homeUrl'             => home_url(),
            'pluginUrl'           => SAW_VISITORS_PLUGIN_URL,
            'debug'               => defined('SAW_DEBUG') && SAW_DEBUG,
            'nonce'               => wp_create_nonce('saw_ajax_nonce'),
            'customerModalNonce'  => wp_create_nonce('saw_customer_modal_nonce'),
            'deleteNonce'         => wp_create_nonce('saw_admin_table_nonce')
        ]);
    }
    
    /**
     * Enqueue module-specific assets
     *
     * @since 1.0.0
     * @param string $slug Module slug
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
        
        $js_file = $module_path . 'scripts.js';
        
        if (!file_exists($js_file)) {
            return;
        }
        
        wp_enqueue_script(
            'saw-module-' . $slug,
            $module_url . 'scripts.js',
            ['jquery', 'saw-app'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-module-' . $slug, 'sawModule', [
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('saw_' . $slug . '_ajax'),
            'entity'   => esc_js($slug),
            'singular' => esc_js($config['singular'] ?? ucfirst($slug)),
            'plural'   => esc_js($config['plural'] ?? ucfirst($slug) . 's')
        ]);
    }
    
    /**
     * Helper method to enqueue a style
     *
     * @since 1.0.0
     * @param string $handle Style handle
     * @param string $path   Relative path from assets directory
     * @param array  $deps   Dependencies
     */
    private static function enqueue_style($handle, $path, $deps = []) {
        wp_enqueue_style(
            $handle,
            SAW_VISITORS_PLUGIN_URL . 'assets/' . $path,
            $deps,
            SAW_VISITORS_VERSION
        );
    }
}