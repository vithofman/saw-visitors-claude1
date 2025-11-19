<?php
/**
 * Asset Loader - Centralized CSS/JS Asset Loading
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 * @version    5.0.0 - Renamed from SAW_Asset_Manager to SAW_Asset_Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages asset enqueueing for global and module-specific resources
 *
 * @since 1.0.0
 * @since 5.0.0 Renamed from SAW_Asset_Manager to SAW_Asset_Loader
 */
class SAW_Asset_Loader {
    
    /**
     * Core CSS files to enqueue
     *
     * @since 1.0.0
     * @var array
     */
    const CORE_STYLES = [
        'saw-variables'  => 'foundation/variables.css',
        'saw-reset'      => 'foundation/reset.css',
        'saw-typography' => 'foundation/typography.css'
    ];
    
    /**
     * Component CSS files to enqueue
     *
     * @since 1.0.0
     * @var array
     */
    const COMPONENT_STYLES = [
        // Core UI components
        'saw-base-components'     => 'components/base-components.css',
        'saw-forms'               => 'components/forms.css', // Consolidated: forms, buttons, selectbox, select-create, color-picker, search-input
        'saw-tables'              => 'components/tables.css', // Consolidated: tables, table-column-types, admin-table, admin-table-sidebar
        'saw-feedback'            => 'components/feedback.css', // Consolidated: alerts, badges, cards, modals, pagination, detail-sections
        
        // Interactive components (CRITICAL: Must load globally to prevent FOUC)
        'saw-navigation'           => 'components/navigation.css', // Consolidated: customer-switcher, branch-switcher, language-switcher
        'saw-file-upload'         => 'components/file-upload.css',
    ];
    
    /**
     * Layout CSS files to enqueue
     *
     * @since 1.0.0
     * @var array
     */
    const LAYOUT_STYLES = [
        'saw-layout' => 'layout/layout.css'
    ];
    
    /**
     * Optional app-level CSS files
     *
     * @since 1.0.0
     * @var array
     */
    const APP_STYLES = [
        'saw-app' => 'app/app.css', // Consolidated: base, header, sidebar, footer, fixed-layout, legacy-base
    ];
    
    /**
     * Enqueue global assets (CSS and JS)
     *
     * @since 1.0.0
     */
    public static function enqueue_global() {
        error_log('SAW Asset Loader: Starting global asset enqueueing');
        self::enqueue_core_styles();
        self::enqueue_component_styles();
        self::enqueue_layout_styles();
        self::enqueue_app_styles();
        self::enqueue_global_scripts();
        error_log('SAW Asset Loader: Global asset enqueueing completed');
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
        foreach (self::APP_STYLES as $handle => $path) {
            $file_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            
            if (file_exists($file_path)) {
                self::enqueue_style($handle, 'css/' . $path, ['saw-variables']);
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
        $app_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/app.js';
        if (file_exists($app_js)) {
            wp_enqueue_script(
                'saw-app',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/app.js',
                ['jquery'],
                filemtime($app_js),
                true
            );
            
            wp_localize_script('saw-app', 'sawGlobal', [
                'ajaxurl'             => admin_url('admin-ajax.php'),
                'homeUrl'             => home_url(),
                'pluginUrl'           => SAW_VISITORS_PLUGIN_URL,
                'version'             => SAW_VISITORS_VERSION,
                'debug'               => defined('SAW_DEBUG') && SAW_DEBUG,
                'nonce'               => wp_create_nonce('saw_ajax_nonce'),
                'customerModalNonce'  => wp_create_nonce('saw_customer_modal_nonce'),
                'deleteNonce'         => wp_create_nonce('saw_admin_table_nonce')
            ]);
        } else {
            error_log("SAW Asset Loader: app.js not found: {$app_js}");
        }
        
        // Navigation (SPA functionality with enhanced features)
        $nav_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/navigation.js';
        if (file_exists($nav_js)) {
            wp_enqueue_script(
                'saw-app-navigation',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/navigation.js',
                ['jquery', 'saw-app'],
                filemtime($nav_js),
                true
            );
        } else {
            error_log("SAW Asset Loader: navigation.js not found: {$nav_js}");
        }
        

        // Global Validation
        $validation_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/validation.js';
        if (file_exists($validation_js)) {
            wp_enqueue_script(
                'saw-validation',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/validation.js',
                ['jquery'],
                filemtime($validation_js),
                true
            );
        } else {
            error_log("SAW Asset Loader: validation.js not found: {$validation_js}");
        }

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
            'saw-navigation-components' => [
                'path' => 'assets/js/components/navigation-components.js', // Consolidated: customer-switcher, branch-switcher, language-switcher
                'deps' => ['jquery', 'saw-app'],
                'localize_multiple' => [
                    'sawCustomerSwitcher' => [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('saw_customer_switcher'),
                    ],
                    'sawBranchSwitcher' => [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('saw_branch_switcher'),
                    ],
                    'sawLanguageSwitcher' => [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('saw_language_switcher'),
                    ]
                ]
            ],
            'saw-forms' => [
                'path' => 'assets/js/components/forms.js', // Consolidated: selectbox, select-create, color-picker, file-upload
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-ui' => [
                'path' => 'assets/js/components/ui.js', // Consolidated: modal, modal-triggers, search
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-admin-table-sidebar' => [
                'path' => 'assets/js/components/admin-table-sidebar.js',
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-admin-table-component' => [
                'path' => 'assets/js/components/admin-table.js',
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
            
            // Multiple localizations (for consolidated components)
            if (isset($config['localize_multiple']) && is_array($config['localize_multiple'])) {
                foreach ($config['localize_multiple'] as $localize_name => $localize_data) {
                    wp_localize_script(
                        $handle,
                        $localize_name,
                        $localize_data
                    );
                }
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
        
        // 1. Enqueue Module CSS (New Structure - check both old and new paths)
        // Try new structure first (modules/{slug}/{slug}.css)
        $css_file_new = 'assets/css/modules/' . $slug . '/' . $slug . '.css';
        $css_file_old = 'assets/css/modules/saw-' . $slug . '.css';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_new)) {
            wp_enqueue_style(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $css_file_new,
                ['saw-variables', 'saw-base-components'],
                SAW_VISITORS_VERSION
            );
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_old)) {
            wp_enqueue_style(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $css_file_old,
                ['saw-variables', 'saw-base-components'],
                SAW_VISITORS_VERSION
            );
        }

        // 2. Enqueue Module JS (New Structure - check both old and new paths)
        $js_file_new = 'assets/js/modules/' . $slug . '/' . $slug . '.js';
        $js_file_old = 'assets/js/modules/saw-' . $slug . '.js';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_new)) {
            wp_enqueue_script(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $js_file_new,
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
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_old)) {
            wp_enqueue_script(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $js_file_old,
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
        $file_path = SAW_VISITORS_PLUGIN_DIR . 'assets/' . $path;
        
        // Check if file exists before enqueueing
        if (!file_exists($file_path)) {
            error_log("SAW Asset Loader: CSS file not found: {$file_path}");
            return;
        }
        
        wp_enqueue_style(
            $handle,
            SAW_VISITORS_PLUGIN_URL . 'assets/' . $path,
            $deps,
            filemtime($file_path) // Use filemtime for cache busting
        );
    }
}

