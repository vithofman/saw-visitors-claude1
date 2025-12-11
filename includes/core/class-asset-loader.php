<?php
/**
 * Asset Loader - Centralized CSS/JS Asset Loading
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 * @version    5.3.0 - FIXED: Use filemtime() for all assets to prevent cache issues
 * 
 * CHANGELOG v5.3.0:
 * - Fixed: All JS/CSS now use filemtime() instead of SAW_VISITORS_VERSION
 * - Fixed: This prevents Service Worker and browser cache serving stale files
 * - Removed: Unnecessary visits-specific JS file searching (saw-visits.js contains everything)
 * - Kept: Visits-specific CSS loading (visits-detail.css, visits-form.css)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages asset enqueueing for global and module-specific resources
 *
 * @since 1.0.0
 * @since 5.0.0 Renamed from SAW_Asset_Manager to SAW_Asset_Loader
 * @since 5.3.0 Fixed cache issues by using filemtime() for all assets
 */
class SAW_Asset_Loader {
    
    /**
     * WordPress editor asset handles for tracking and cleanup
     *
     * @since 5.1.0
     * @var array
     */
    private static $wordpress_editor_handles = [];
    
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
        'saw-forms'               => 'components/forms.css',
        'saw-tables'              => 'components/tables.css',
        'saw-tabs'                => 'components/tabs.css',
        'saw-feedback'            => 'components/feedback.css',
        'saw-admin-table-detail'  => 'components/admin-table-detail.css',
        
        // Interactive components
        'saw-navigation'          => 'components/navigation.css',
        'saw-file-upload-modern'  => 'components/file-upload-modern.css',
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
        'saw-app' => 'app/app.css',
        'saw-transitions' => 'app/transitions.css',
    ];
    
    /**
     * Dark mode CSS files (loaded conditionally)
     *
     * @since 1.0.0
     * @var array
     */
    const DARK_MODE_STYLES = [
        'saw-app-dark' => 'app/app-dark.css',
        'saw-sidebar-dark' => 'components/sidebar-dark.css',
        'saw-tables-dark' => 'components/tables-dark.css',
        'saw-forms-dark' => 'components/forms-dark.css',
        'saw-navigation-dark' => 'components/navigation-dark.css',
        'saw-feedback-dark' => 'components/feedback-dark.css',
        'saw-file-upload-dark' => 'components/file-upload-dark.css',
        'saw-dashboard-dark' => 'modules/dashboard-dark.css',
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
        
        // Enqueue dashicons for icons
        wp_enqueue_style('dashicons');
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
            if ($handle === 'saw-base-components') {
                $deps = ['saw-variables'];
            } elseif ($handle === 'saw-admin-table-detail') {
                $deps = ['saw-variables', 'saw-base-components', 'saw-tables'];
            } elseif ($handle === 'saw-tabs') {
                $deps = ['saw-variables', 'saw-base-components', 'saw-tables'];
            } else {
                $deps = ['saw-variables', 'saw-base-components'];
            }
            self::enqueue_style($handle, 'css/' . $path, $deps);
        }
    }
    
    /**
     * Enqueue layout CSS files
     *
     * @since 1.0.0
     */
    private static function enqueue_layout_styles() {
        foreach (self::LAYOUT_STYLES as $handle => $path) {
            self::enqueue_style($handle, 'css/' . $path, ['saw-variables', 'saw-base-components']);
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
                self::enqueue_style($handle, 'css/' . $path, ['saw-variables', 'saw-base-components', 'saw-layout']);
            }
        }
        
        // Enqueue dark mode styles if active
        self::enqueue_dark_mode_styles();
    }
    
    /**
     * Enqueue dark mode CSS files if dark mode is active
     *
     * @since 1.0.0
     */
    private static function enqueue_dark_mode_styles() {
        $user_id = get_current_user_id();
        $dark_mode = get_user_meta($user_id, 'saw_dark_mode', true);
        
        if (!$dark_mode && isset($_COOKIE['saw_dark_mode'])) {
            $dark_mode = $_COOKIE['saw_dark_mode'] === '1';
        }
        
        if ($dark_mode) {
            foreach (self::DARK_MODE_STYLES as $handle => $path) {
                $file_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
                
                if (file_exists($file_path)) {
                    $deps = [];
                    if (strpos($path, 'app/') !== false) {
                        $deps = ['saw-app', 'saw-layout'];
                    } elseif (strpos($path, 'components/sidebar-') !== false) {
                        $deps = ['saw-app', 'saw-tables'];
                    } elseif (strpos($path, 'components/tables-') !== false) {
                        $deps = ['saw-app', 'saw-tables'];
                    } elseif (strpos($path, 'components/forms-') !== false) {
                        $deps = ['saw-app', 'saw-forms'];
                    } elseif (strpos($path, 'components/navigation-') !== false) {
                        $deps = ['saw-app', 'saw-navigation'];
                    } elseif (strpos($path, 'components/feedback-') !== false) {
                        $deps = ['saw-app', 'saw-feedback'];
                    } elseif (strpos($path, 'components/file-upload-') !== false) {
                        $deps = ['saw-app', 'saw-file-upload-modern'];
                    } elseif (strpos($path, 'modules/dashboard-') !== false) {
                        $deps = ['saw-app', 'saw-feedback'];
                    } else {
                        $deps = ['saw-app'];
                    }
                    
                    self::enqueue_style($handle, 'css/' . $path, $deps);
                }
            }
            
            // Module-specific dark mode CSS
            $active_module = get_query_var('saw_active_module');
            if ($active_module) {
                $module_dark_css_paths = [
                    "assets/css/modules/{$active_module}/{$active_module}-dark.css",
                    "assets/css/modules/settings/settings-dark.css",
                    "assets/css/modules/content/content-dark.css",
                ];
                
                $module_dark_css = null;
                foreach ($module_dark_css_paths as $path) {
                    $full_path = SAW_VISITORS_PLUGIN_DIR . $path;
                    if (file_exists($full_path)) {
                        $module_dark_css = $path;
                        break;
                    }
                }
                
                if ($module_dark_css) {
                    wp_enqueue_style(
                        "saw-{$active_module}-dark",
                        SAW_VISITORS_PLUGIN_URL . $module_dark_css,
                        ['saw-app-dark', 'saw-sidebar-dark', 'saw-forms-dark'],
                        filemtime(SAW_VISITORS_PLUGIN_DIR . $module_dark_css)
                    );
                }
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
        }
        
        // State Manager
        $state_manager_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/state-manager.js';
        if (file_exists($state_manager_js)) {
            wp_enqueue_script(
                'saw-state-manager',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/state-manager.js',
                ['jquery'],
                filemtime($state_manager_js),
                true
            );
        }

        // View Transition
        $view_transition_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/view-transition.js';
        if (file_exists($view_transition_js)) {
            wp_enqueue_script(
                'saw-view-transition',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/view-transition.js',
                ['jquery', 'saw-app', 'saw-state-manager'],
                filemtime($view_transition_js),
                true
            );
        }

        // Form Autosave
        $form_autosave_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/form-autosave.js';
        if (file_exists($form_autosave_js)) {
            wp_enqueue_script(
                'saw-form-autosave',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/form-autosave.js',
                ['jquery', 'saw-app', 'saw-state-manager'],
                filemtime($form_autosave_js),
                true
            );
        }
        
        // Theme Toggle
        $theme_toggle_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/theme-toggle.js';
        if (file_exists($theme_toggle_js)) {
            wp_enqueue_script(
                'saw-theme-toggle',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/theme-toggle.js',
                ['jquery', 'saw-app'],
                filemtime($theme_toggle_js),
                true
            );
            
            wp_localize_script('saw-theme-toggle', 'sawAjaxUrl', admin_url('admin-ajax.php'));
            wp_localize_script('saw-theme-toggle', 'sawAjaxNonce', wp_create_nonce('saw_ajax_nonce'));
            wp_localize_script('saw-theme-toggle', 'sawPluginUrl', SAW_VISITORS_PLUGIN_URL);
            wp_localize_script('saw-theme-toggle', 'sawVersion', SAW_VISITORS_VERSION);
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
        }

        // Component scripts
        self::enqueue_component_scripts();
    }
    
    /**
     * Enqueue component-specific JavaScript files
     *
     * @since 1.0.1
     * @return void
     */
    private static function enqueue_component_scripts() {
        $component_scripts = [
            'saw-navigation-components' => [
                'path' => 'assets/js/components/navigation-components.js',
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
                'path' => 'assets/js/components/forms.js',
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-select-create' => [
                'path' => 'assets/js/components/select-create.js',
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-file-upload-modern' => [
                'path' => 'assets/js/components/file-upload-modern.js',
                'deps' => ['jquery'],
                'localize' => 'sawFileUpload',
                'localize_data' => [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('saw_upload_file'),
                    'strings' => [
                        'uploading' => 'Nahrávání...',
                        'success' => 'Soubor byl úspěšně nahrán',
                        'error' => 'Chyba při nahrávání',
                        'file_too_large' => 'Soubor je příliš velký',
                        'invalid_type' => 'Nepodporovaný formát souboru',
                        'select_files' => 'Vybrat soubory',
                        'drag_drop' => 'Přetáhněte soubory nebo',
                    ]
                ]
            ],
            'saw-ui' => [
                'path' => 'assets/js/components/ui.js',
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-admin-table-component' => [
                'path' => 'assets/js/components/admin-table.js',
                'deps' => ['jquery', 'saw-app'],
            ],
            'saw-tabs-navigation' => [
                'path' => 'assets/js/components/tabs-navigation.js',
                'deps' => ['jquery', 'saw-app', 'saw-admin-table-component'],
            ],
        ];
        
        foreach ($component_scripts as $handle => $config) {
            $script_path = SAW_VISITORS_PLUGIN_DIR . $config['path'];
            
            if (!file_exists($script_path)) {
                continue;
            }
            
            // ✅ FIX v5.3.0: Use filemtime() instead of SAW_VISITORS_VERSION
            wp_enqueue_script(
                $handle,
                SAW_VISITORS_PLUGIN_URL . $config['path'],
                $config['deps'],
                filemtime($script_path),
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
            
            // Multiple localizations
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
     * Enqueue WordPress editor assets (for content module only)
     *
     * @since 5.1.0
     * @return void
     */
    public static function enqueue_wordpress_editor() {
        wp_enqueue_media();
        wp_enqueue_editor();
        
        global $wp_styles, $wp_scripts;
        
        $editor_css_handles = ['editor', 'media', 'tinymce'];
        foreach ($editor_css_handles as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                self::$wordpress_editor_handles[] = $handle;
            }
        }
        
        $editor_js_handles = ['editor', 'tinymce', 'media-models', 'media-views'];
        foreach ($editor_js_handles as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                self::$wordpress_editor_handles[] = $handle;
            }
        }
    }
    
    /**
     * Get WordPress editor handles for cleanup
     *
     * @since 5.1.0
     * @return array
     */
    public static function get_wordpress_editor_handles() {
        return self::$wordpress_editor_handles;
    }
    
    /**
     * Enqueue module-specific assets
     *
     * @since 1.0.0
     * @since 5.3.0 Fixed: Use filemtime() for all assets, simplified visits handling
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
        
        // WordPress editor for content module
        if ($slug === 'content') {
            self::enqueue_wordpress_editor();
        }
        
        // =====================================================================
        // MODULE CSS
        // =====================================================================
        
        $css_file_new = 'assets/css/modules/' . $slug . '/' . $slug . '.css';
        $css_file_old = 'assets/css/modules/saw-' . $slug . '.css';
        $css_path = null;
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_new)) {
            $css_path = $css_file_new;
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_old)) {
            $css_path = $css_file_old;
        }
        
        if ($css_path) {
            // ✅ FIX v5.3.0: Use filemtime() for cache busting
            wp_enqueue_style(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $css_path,
                ['saw-variables', 'saw-base-components', 'saw-app'],
                filemtime(SAW_VISITORS_PLUGIN_DIR . $css_path)
            );
        }
        
        // Global admin-table detail CSS
        $admin_table_detail_css = 'assets/css/components/admin-table-detail.css';
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $admin_table_detail_css)) {
            $deps = ['saw-tables'];
            $module_handle = 'saw-module-' . $slug;
            $deps[] = $module_handle;
            
            wp_enqueue_style(
                'saw-admin-table-detail',
                SAW_VISITORS_PLUGIN_URL . $admin_table_detail_css,
                $deps,
                filemtime(SAW_VISITORS_PLUGIN_DIR . $admin_table_detail_css)
            );
        }
        
        // =====================================================================
        // MODULE-SPECIFIC CSS
        // =====================================================================
        
        // Companies module CSS
        if ($slug === 'companies') {
            $merge_css = 'assets/css/modules/companies/companies-merge.css';
            if (file_exists(SAW_VISITORS_PLUGIN_DIR . $merge_css)) {
                wp_enqueue_style(
                    'saw-companies-merge',
                    SAW_VISITORS_PLUGIN_URL . $merge_css,
                    ['saw-module-companies', 'saw-admin-table-detail', 'saw-feedback'],
                    filemtime(SAW_VISITORS_PLUGIN_DIR . $merge_css)
                );
            }
        }
        
        // Visits module CSS
        if ($slug === 'visits') {
            $visits_css_files = [
                'saw-visits-detail' => 'assets/css/modules/visits/visits-detail.css',
                'saw-visits-form' => 'assets/css/modules/visits/visits-form.css',
            ];
            
            foreach ($visits_css_files as $handle => $path) {
                if (file_exists(SAW_VISITORS_PLUGIN_DIR . $path)) {
                    wp_enqueue_style(
                        $handle,
                        SAW_VISITORS_PLUGIN_URL . $path,
                        ['saw-module-visits', 'saw-admin-table-detail', 'saw-feedback'],
                        filemtime(SAW_VISITORS_PLUGIN_DIR . $path)
                    );
                }
            }
        }
        
        // =====================================================================
        // MODULE JS
        // =====================================================================
        
        $js_file_new = 'assets/js/modules/' . $slug . '/' . $slug . '.js';
        $js_file_old = 'assets/js/modules/saw-' . $slug . '.js';
        $js_path = null;
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_new)) {
            $js_path = $js_file_new;
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_old)) {
            $js_path = $js_file_old;
        }
        
        if ($js_path) {
            // ✅ FIX v5.3.0: Use filemtime() for cache busting
            wp_enqueue_script(
                'saw-module-' . $slug,
                SAW_VISITORS_PLUGIN_URL . $js_path,
                ['jquery', 'saw-app', 'saw-validation'],
                filemtime(SAW_VISITORS_PLUGIN_DIR . $js_path),
                true
            );
            
            // Localize module script
            $is_nested = '0';
            if (isset($_GET['nested']) && $_GET['nested'] === '1') {
                $is_nested = '1';
            } elseif (isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create']) {
                $is_nested = '1';
            }
            
            wp_localize_script('saw-module-' . $slug, 'saw' . ucfirst($slug), [
                'ajaxurl'  => admin_url('admin-ajax.php'),
                'entity'   => esc_js($slug),
                'isEdit'   => isset($_GET['id']) || (isset($_GET['saw_path']) && strpos($_GET['saw_path'], 'edit') !== false),
                'isNested' => $is_nested
            ]);
        }
        
        // =====================================================================
        // MODULE-SPECIFIC JS
        // =====================================================================
        
        // Companies module JS
        if ($slug === 'companies') {
            $companies_js_files = [
                'saw-companies-detail' => 'assets/js/modules/companies/companies-detail.js',
                'saw-companies-merge' => 'assets/js/modules/companies/companies-merge.js',
            ];
            
            foreach ($companies_js_files as $handle => $path) {
                if (file_exists(SAW_VISITORS_PLUGIN_DIR . $path)) {
                    wp_enqueue_script(
                        $handle,
                        SAW_VISITORS_PLUGIN_URL . $path,
                        ['jquery', 'saw-app', 'saw-module-companies'],
                        filemtime(SAW_VISITORS_PLUGIN_DIR . $path),
                        true
                    );
                }
            }
        }
        
        // NOTE: Visits module JS (saw-visits.js) contains SAWVisitorsManager
        // No additional JS files needed - everything is in the main module file
        
        // Legacy support (scripts.js in module dir)
        if (!$js_path) {
            $legacy_js = $module_path . 'scripts.js';
            
            if (file_exists($legacy_js)) {
                wp_enqueue_script(
                    'saw-module-' . $slug,
                    $module_url . 'scripts.js',
                    ['jquery', 'saw-app'],
                    filemtime($legacy_js),
                    true
                );
                
                wp_localize_script('saw-module-' . $slug, 'sawModule', [
                    'ajaxurl'  => admin_url('admin-ajax.php'),
                    'entity'   => esc_js($slug),
                    'singular' => esc_js($config['singular'] ?? ucfirst($slug)),
                    'plural'   => esc_js($config['plural'] ?? ucfirst($slug) . 's')
                ]);
            }
        }
    }
    
    /**
     * Get assets list for a module (for AJAX navigation)
     *
     * @since 5.1.0
     * @param string $slug Module slug
     * @return array Array with 'css' and 'js' keys containing asset arrays
     */
    public static function get_assets_for_module($slug) {
        $assets = [
            'css' => [],
            'js' => []
        ];
        
        $assets['css'] = array_merge($assets['css'], self::get_global_css_assets());
        $assets['js'] = array_merge($assets['js'], self::get_global_js_assets());
        
        if ($slug && $slug !== 'dashboard' && $slug !== 'settings') {
            $module_assets = self::get_module_assets($slug);
            $assets['css'] = array_merge($assets['css'], $module_assets['css']);
            $assets['js'] = array_merge($assets['js'], $module_assets['js']);
        }
        
        return $assets;
    }
    
    /**
     * Get global CSS assets list
     *
     * @since 5.1.0
     * @return array
     */
    private static function get_global_css_assets() {
        $assets = [];
        
        // Core CSS
        foreach (self::CORE_STYLES as $handle => $path) {
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $deps = ($handle === 'saw-variables') ? [] : ['saw-variables'];
                $assets[] = [
                    'handle' => $handle,
                    'src' => SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path . '?v=' . filemtime($css_path),
                    'deps' => $deps
                ];
            }
        }
        
        // Component CSS
        foreach (self::COMPONENT_STYLES as $handle => $path) {
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $deps = ($handle === 'saw-base-components') 
                    ? ['saw-variables'] 
                    : ['saw-variables', 'saw-base-components'];
                $assets[] = [
                    'handle' => $handle,
                    'src' => SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path . '?v=' . filemtime($css_path),
                    'deps' => $deps
                ];
            }
        }
        
        // Layout CSS
        foreach (self::LAYOUT_STYLES as $handle => $path) {
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $assets[] = [
                    'handle' => $handle,
                    'src' => SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path . '?v=' . filemtime($css_path),
                    'deps' => ['saw-variables', 'saw-base-components']
                ];
            }
        }
        
        // App CSS
        foreach (self::APP_STYLES as $handle => $path) {
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $assets[] = [
                    'handle' => $handle,
                    'src' => SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path . '?v=' . filemtime($css_path),
                    'deps' => ['saw-variables', 'saw-base-components', 'saw-layout']
                ];
            }
        }
        
        // Dashicons
        $assets[] = [
            'handle' => 'dashicons',
            'src' => includes_url('css/dashicons.min.css'),
            'deps' => []
        ];
        
        return $assets;
    }
    
    /**
     * Get global JS assets list
     *
     * @since 5.1.0
     * @return array
     */
    private static function get_global_js_assets() {
        $assets = [];
        
        $validation_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/validation.js';
        if (file_exists($validation_js)) {
            $assets[] = [
                'handle' => 'saw-validation',
                'src' => SAW_VISITORS_PLUGIN_URL . 'assets/js/core/validation.js?v=' . filemtime($validation_js),
                'deps' => ['jquery', 'saw-app']
            ];
        }
        
        $component_js = [
            'saw-forms' => 'components/forms.js',
            'saw-navigation-components' => 'components/navigation-components.js',
            'saw-ui' => 'components/ui.js',
            'saw-admin-table-component' => 'components/admin-table.js',
            'saw-admin-table-sidebar' => 'components/admin-table-sidebar.js',
        ];
        
        foreach ($component_js as $handle => $path) {
            $js_path = SAW_VISITORS_PLUGIN_DIR . 'assets/js/' . $path;
            if (file_exists($js_path)) {
                $deps = ($handle === 'saw-admin-table-sidebar') 
                    ? ['jquery', 'saw-app', 'saw-admin-table-component']
                    : ['jquery', 'saw-app'];
                $assets[] = [
                    'handle' => $handle,
                    'src' => SAW_VISITORS_PLUGIN_URL . 'assets/js/' . $path . '?v=' . filemtime($js_path),
                    'deps' => $deps
                ];
            }
        }
        
        return $assets;
    }
    
    /**
     * Get module-specific assets list
     *
     * @since 5.1.0
     * @since 5.3.0 Fixed: Use filemtime() for versions
     * @param string $slug Module slug
     * @return array
     */
    private static function get_module_assets($slug) {
        $assets = [
            'css' => [],
            'js' => []
        ];
        
        // Module CSS
        $css_file_new = 'assets/css/modules/' . $slug . '/' . $slug . '.css';
        $css_file_old = 'assets/css/modules/saw-' . $slug . '.css';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_new)) {
            $assets['css'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $css_file_new . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $css_file_new),
                'deps' => ['saw-variables', 'saw-base-components']
            ];
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_old)) {
            $assets['css'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $css_file_old . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $css_file_old),
                'deps' => ['saw-variables', 'saw-base-components']
            ];
        }
        
        // Module JS
        $js_file_new = 'assets/js/modules/' . $slug . '/' . $slug . '.js';
        $js_file_old = 'assets/js/modules/saw-' . $slug . '.js';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_new)) {
            $assets['js'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $js_file_new . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $js_file_new),
                'deps' => ['jquery', 'saw-app', 'saw-validation']
            ];
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_old)) {
            $assets['js'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $js_file_old . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $js_file_old),
                'deps' => ['jquery', 'saw-app', 'saw-validation']
            ];
        }
        
        // Companies specific assets
        if ($slug === 'companies') {
            $companies_js_files = [
                'saw-companies-detail' => 'assets/js/modules/companies/companies-detail.js',
                'saw-companies-merge' => 'assets/js/modules/companies/companies-merge.js',
            ];
            
            foreach ($companies_js_files as $handle => $path) {
                if (file_exists(SAW_VISITORS_PLUGIN_DIR . $path)) {
                    $assets['js'][] = [
                        'handle' => $handle,
                        'src' => SAW_VISITORS_PLUGIN_URL . $path . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $path),
                        'deps' => ['jquery', 'saw-app', 'saw-module-companies']
                    ];
                }
            }
            
            $companies_css_files = [
                'saw-companies-merge' => 'assets/css/modules/companies/companies-merge.css',
            ];
            
            foreach ($companies_css_files as $handle => $path) {
                if (file_exists(SAW_VISITORS_PLUGIN_DIR . $path)) {
                    $assets['css'][] = [
                        'handle' => $handle,
                        'src' => SAW_VISITORS_PLUGIN_URL . $path . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $path),
                        'deps' => ['saw-module-companies']
                    ];
                }
            }
        }
        
        // Visits specific CSS (JS is in main saw-visits.js)
        if ($slug === 'visits') {
            $visits_css_files = [
                'saw-visits-detail' => 'assets/css/modules/visits/visits-detail.css',
                'saw-visits-form' => 'assets/css/modules/visits/visits-form.css',
            ];
            
            foreach ($visits_css_files as $handle => $path) {
                if (file_exists(SAW_VISITORS_PLUGIN_DIR . $path)) {
                    $assets['css'][] = [
                        'handle' => $handle,
                        'src' => SAW_VISITORS_PLUGIN_URL . $path . '?v=' . filemtime(SAW_VISITORS_PLUGIN_DIR . $path),
                        'deps' => ['saw-module-visits']
                    ];
                }
            }
        }
        
        // WordPress editor assets for content module
        if ($slug === 'content') {
            global $wp_styles, $wp_scripts;
            
            $editor_css_handles = ['editor', 'media', 'tinymce'];
            foreach ($editor_css_handles as $handle) {
                if (isset($wp_styles->registered[$handle])) {
                    $style = $wp_styles->registered[$handle];
                    $assets['css'][] = [
                        'handle' => $handle,
                        'src' => $style->src . (isset($style->ver) ? '?ver=' . $style->ver : ''),
                        'deps' => $style->deps ?? []
                    ];
                }
            }
            
            $editor_js_handles = ['editor', 'tinymce', 'media-models', 'media-views'];
            foreach ($editor_js_handles as $handle) {
                if (isset($wp_scripts->registered[$handle])) {
                    $script = $wp_scripts->registered[$handle];
                    $assets['js'][] = [
                        'handle' => $handle,
                        'src' => $script->src . (isset($script->ver) ? '?ver=' . $script->ver : ''),
                        'deps' => $script->deps ?? []
                    ];
                }
            }
        }
        
        return $assets;
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
        
        if (!file_exists($file_path)) {
            return;
        }
        
        wp_enqueue_style(
            $handle,
            SAW_VISITORS_PLUGIN_URL . 'assets/' . $path,
            $deps,
            filemtime($file_path)
        );
    }
}