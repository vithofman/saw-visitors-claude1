<?php
/**
 * Asset Manager - Centralized CSS/JS Asset Loading
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 * @version    1.3.0 - Added select-create component
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
        // Core UI components
        'saw-components'          => 'core/saw-components.css',
        'saw-buttons'             => 'components/buttons.css',
        'saw-forms'               => 'components/forms.css',
        'saw-tables'              => 'components/tables.css',
        'saw-badges'              => 'components/badges.css',
        'saw-modals'              => 'components/modals.css',
        'saw-alerts'              => 'components/alerts.css',
        'saw-cards'               => 'components/cards.css',
        'saw-search'              => 'components/search.css',
        'saw-pagination'          => 'components/pagination.css',
        'saw-table-column-types'  => 'components/table-column-types.css',
        'saw-detail-sections'     => 'components/detail-sections.css',
        
        // Interactive components (CRITICAL: Must load globally to prevent FOUC)
        'saw-customer-switcher'   => '../includes/components/customer-switcher/customer-switcher.css',
        'saw-branch-switcher'     => '../includes/components/branch-switcher/branch-switcher.css',
        'saw-language-switcher'   => '../includes/components/language-switcher/language-switcher.css',
        'saw-selectbox'           => '../includes/components/selectbox/saw-selectbox.css',
	'saw-select-create'       => '../includes/components/select-create/select-create.css',
        
        // Admin Table component
        'saw-admin-table-sidebar' => '../includes/components/admin-table/sidebar.css',
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
            // Handle paths that start with ../ (component-specific paths)
            if (strpos($path, '../') === 0) {
                self::enqueue_style($handle, $path, ['saw-variables']);
            } else {
                self::enqueue_style($handle, 'css/' . $path, ['saw-variables']);
            }
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
        
        // Main app script
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
        
        // Global Validation
        wp_enqueue_script(
            'saw-validation',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/core/saw-validation.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );

        // Enqueue component-specific JavaScript
        self::enqueue_component_scripts();
    }
    
    /**
     * Enqueue component-specific JavaScript files
     *
     * CRITICAL: These must load globally to ensure interactive components
     * work on first page load without FOUC.
     *
     * @since 1.0.1
     * @return void
     */
    private static function enqueue_component_scripts() {
        $component_scripts = [
            'saw-customer-switcher' => [
                'path' => 'includes/components/customer-switcher/customer-switcher.js',
                'deps' => ['jquery', 'saw-app'],
                'localize' => 'sawCustomerSwitcher',
                'localize_data' => [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('saw_customer_switcher'),
                ]
            ],
            'saw-branch-switcher' => [
                'path' => 'includes/components/branch-switcher/branch-switcher.js',
                'deps' => ['jquery', 'saw-app'],
                'localize' => 'sawBranchSwitcher',
                'localize_data' => [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('saw_branch_switcher'),
                ]
            ],
            'saw-language-switcher' => [
                'path' => 'includes/components/language-switcher/language-switcher.js',
                'deps' => ['jquery', 'saw-app'],
                'localize' => 'sawLanguageSwitcher',
                'localize_data' => [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('saw_language_switcher'),
                ]
            ],
            'saw-selectbox' => [
                'path' => 'includes/components/selectbox/saw-selectbox.js',
                'deps' => ['jquery', 'saw-app'],
            ],
	    'saw-select-create' => [
		'path' => 'includes/components/select-create/select-create.js',
		'deps' => ['jquery', 'saw-app'],
	    ],
            'saw-admin-table-sidebar' => [
                'path' => 'includes/components/admin-table/sidebar.js',
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-admin-table-component' => [
                'path' => 'includes/components/admin-table/admin-table.js',
                'deps' => ['jquery', 'saw-app'],
            ],
        ];
        
        foreach ($component_scripts as $handle => $config) {
            $script_path = SAW_VISITORS_PLUGIN_DIR . $config['path'];
            
            if (!file_exists($script_path)) {
                continue;
            }
            
            wp_enqueue_script(
                $handle,
                SAW_VISITORS_PLUGIN_URL . $config['path'],
                $config['deps'],
                SAW_VISITORS_VERSION,
                true
            );
            
            // Localize script if needed
            if (isset($config['localize']) && isset($config['localize_data'])) {
                wp_localize_script(
                    $handle,
                    $config['localize'],
                    $config['localize_data']
                );
            }
        }
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
        
        // 1. Enqueue Module CSS (New Structure)
        $css_file_rel = 'assets/css/modules/saw-' . $slug . '.css';
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_rel)) {
            wp_enqueue_style(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $css_file_rel,
                ['saw-variables', 'saw-components'],
                SAW_VISITORS_VERSION
            );
        }

        // 2. Enqueue Module JS (New Structure)
        $js_file_rel = 'assets/js/modules/saw-' . $slug . '.js';
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_rel)) {
            wp_enqueue_script(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $js_file_rel,
                ['jquery', 'saw-app', 'saw-validation'],
                SAW_VISITORS_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('saw-module-' . $slug, 'saw' . ucfirst($slug), [
                'ajaxurl'  => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('saw_' . $slug . '_ajax'),
                'entity'   => esc_js($slug),
                'isEdit'   => isset($_GET['id']) || (isset($_GET['saw_path']) && strpos($_GET['saw_path'], 'edit') !== false),
                'isNested' => isset($_GET['nested']) ? $_GET['nested'] : '0'
            ]);
            
            return; // Prefer new structure over legacy
        }

        // 3. Legacy Support (scripts.js in module dir)
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