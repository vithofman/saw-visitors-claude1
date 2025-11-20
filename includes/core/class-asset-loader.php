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
        'saw-forms'               => 'components/forms.css', // Consolidated: forms, buttons, selectbox, select-create, color-picker, search-input
        'saw-tables'              => 'components/tables.css', // Consolidated: tables, table-column-types, admin-table, admin-table-sidebar
        'saw-feedback'            => 'components/feedback.css', // Consolidated: alerts, badges, cards, modals, pagination, detail-sections
        
        // Interactive components (CRITICAL: Must load globally to prevent FOUC)
        'saw-navigation'           => 'components/navigation.css', // Consolidated: customer-switcher, branch-switcher, language-switcher
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
        'saw-app' => 'app/app.css', // Consolidated: base, header, sidebar, footer, fixed-layout, legacy-base
        'saw-transitions' => 'app/transitions.css', // View Transition API and fallback overlay styles
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
        error_log('SAW Asset Loader: Starting global asset enqueueing');
        self::enqueue_core_styles();
        self::enqueue_component_styles();
        self::enqueue_layout_styles();
        self::enqueue_app_styles();
        self::enqueue_global_scripts();
        
        // Enqueue dashicons for icons (float button, etc.)
        wp_enqueue_style('dashicons');
        
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
            // CRITICAL: Component styles depend on base-components (except base-components itself)
            $deps = ($handle === 'saw-base-components') 
                ? ['saw-variables'] 
                : ['saw-variables', 'saw-base-components'];
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
            // CRITICAL: Layout styles depend on base-components
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
                // CRITICAL: App styles depend on layout and base components
                self::enqueue_style($handle, 'css/' . $path, ['saw-variables', 'saw-base-components', 'saw-layout']);
            } else {
                error_log("SAW Asset Loader: App CSS file not found: {$file_path}");
            }
        }
        
        // Enqueue dark mode styles if dark mode is active
        self::enqueue_dark_mode_styles();
    }
    
    /**
     * Enqueue dark mode CSS files if dark mode is active
     *
     * @since 1.0.0
     */
    private static function enqueue_dark_mode_styles() {
        // Check if dark mode is active (from user meta or localStorage)
        $user_id = get_current_user_id();
        $dark_mode = get_user_meta($user_id, 'saw_dark_mode', true);
        
        // Also check for data-theme attribute on body (set by JavaScript)
        if (!$dark_mode && isset($_COOKIE['saw_dark_mode'])) {
            $dark_mode = $_COOKIE['saw_dark_mode'] === '1';
        }
        
        if ($dark_mode) {
            foreach (self::DARK_MODE_STYLES as $handle => $path) {
                $file_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
                
                if (file_exists($file_path)) {
                    // Set dependencies based on the CSS file type
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
                } else {
                    error_log("SAW Asset Loader: Dark mode CSS file not found: {$file_path}");
                }
            }
            
            // Enqueue module-specific dark mode CSS
            $active_module = get_query_var('saw_active_module');
            if ($active_module) {
                // Try different paths for module dark CSS
                $module_dark_css_paths = [
                    "assets/css/modules/{$active_module}/{$active_module}-dark.css", // e.g., modules/companies/companies-dark.css
                    "assets/css/modules/settings/settings-dark.css", // settings module
                    "assets/css/modules/content/content-dark.css", // content module
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
        } else {
            error_log("SAW Asset Loader: app.js not found: {$app_js}");
        }
        
        // State Manager (for scroll position, table state, form data)
        $state_manager_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/state-manager.js';
        if (file_exists($state_manager_js)) {
            wp_enqueue_script(
                'saw-state-manager',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/state-manager.js',
                ['jquery'],
                filemtime($state_manager_js),
                true
            );
        } else {
            error_log("SAW Asset Loader: state-manager.js not found: {$state_manager_js}");
        }

        // View Transition (for smooth page transitions)
        $view_transition_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/view-transition.js';
        if (file_exists($view_transition_js)) {
            wp_enqueue_script(
                'saw-view-transition',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/view-transition.js',
                ['jquery', 'saw-app', 'saw-state-manager'],
                filemtime($view_transition_js),
                true
            );
        } else {
            error_log("SAW Asset Loader: view-transition.js not found: {$view_transition_js}");
        }

        // Form Autosave (automatic form data saving)
        $form_autosave_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/form-autosave.js';
        if (file_exists($form_autosave_js)) {
            wp_enqueue_script(
                'saw-form-autosave',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/core/form-autosave.js',
                ['jquery', 'saw-app', 'saw-state-manager'],
                filemtime($form_autosave_js),
                true
            );
        } else {
            error_log("SAW Asset Loader: form-autosave.js not found: {$form_autosave_js}");
        }
        
        // Theme Toggle (dark mode switching)
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
        } else {
            error_log("SAW Asset Loader: theme-toggle.js not found: {$theme_toggle_js}");
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
                'path' => 'assets/js/components/forms.js', // Consolidated: selectbox, select-create, color-picker
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
                'path' => 'assets/js/components/ui.js', // Consolidated: modal, modal-triggers, search
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
     * Enqueue WordPress editor assets (for content module only)
     *
     * @since 5.1.0
     * @return void
     */
    public static function enqueue_wordpress_editor() {
        // Enqueue WordPress media library
        wp_enqueue_media();
        
        // Enqueue WordPress editor
        wp_enqueue_editor();
        
        // Track WordPress editor assets for cleanup
        global $wp_styles, $wp_scripts;
        
        // Track WordPress editor CSS handles
        $editor_css_handles = ['editor', 'media', 'tinymce'];
        foreach ($editor_css_handles as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                self::$wordpress_editor_handles[] = $handle;
            }
        }
        
        // Track WordPress editor JS handles
        $editor_js_handles = ['editor', 'tinymce', 'media-models', 'media-views'];
        foreach ($editor_js_handles as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                self::$wordpress_editor_handles[] = $handle;
            }
        }
        
        error_log('SAW Asset Loader: WordPress editor assets enqueued. Handles: ' . implode(', ', self::$wordpress_editor_handles));
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
        
        // CRITICAL: Enqueue WordPress editor for content module
        if ($slug === 'content') {
            self::enqueue_wordpress_editor();
        }
        
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
        
        // Get global assets
        $assets['css'] = array_merge($assets['css'], self::get_global_css_assets());
        $assets['js'] = array_merge($assets['js'], self::get_global_js_assets());
        
        // Get module-specific assets
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
            $css_url = SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path;
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $deps = ($handle === 'saw-variables') ? [] : ['saw-variables'];
                $assets[] = [
                    'handle' => $handle,
                    'src' => $css_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => $deps
                ];
            }
        }
        
        // Component CSS
        foreach (self::COMPONENT_STYLES as $handle => $path) {
            $css_url = SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path;
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $deps = ($handle === 'saw-base-components') 
                    ? ['saw-variables'] 
                    : ['saw-variables', 'saw-base-components'];
                $assets[] = [
                    'handle' => $handle,
                    'src' => $css_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => $deps
                ];
            }
        }
        
        // Layout CSS
        foreach (self::LAYOUT_STYLES as $handle => $path) {
            $css_url = SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path;
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $assets[] = [
                    'handle' => $handle,
                    'src' => $css_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => ['saw-variables', 'saw-base-components']
                ];
            }
        }
        
        // App CSS
        foreach (self::APP_STYLES as $handle => $path) {
            $css_url = SAW_VISITORS_PLUGIN_URL . 'assets/css/' . $path;
            $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/' . $path;
            if (file_exists($css_path)) {
                $assets[] = [
                    'handle' => $handle,
                    'src' => $css_url . '?v=' . SAW_VISITORS_VERSION,
                    'deps' => ['saw-variables', 'saw-base-components', 'saw-layout']
                ];
            }
        }
        
        // Dashicons
        $dashicons_url = includes_url('css/dashicons.min.css');
        $assets[] = [
            'handle' => 'dashicons',
            'src' => $dashicons_url,
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
        
        // Note: saw-app and saw-app-navigation are already loaded globally, don't include them again
        // Only include validation.js which might be needed
        
        $validation_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/core/validation.js';
        if (file_exists($validation_js)) {
            $assets[] = [
                'handle' => 'saw-validation',
                'src' => SAW_VISITORS_PLUGIN_URL . 'assets/js/core/validation.js' . '?v=' . SAW_VISITORS_VERSION,
                'deps' => ['jquery', 'saw-app']
            ];
        }
        
        // Component JS files (needed for admin-table and other components)
        // These are normally loaded globally, but we include them here for SPA navigation consistency
        $component_js = [
            'saw-forms' => 'components/forms.js',
            'saw-navigation-components' => 'components/navigation-components.js',
            'saw-ui' => 'components/ui.js',
            'saw-admin-table-component' => 'components/admin-table.js',
            'saw-admin-table-sidebar' => 'components/admin-table-sidebar.js',
        ];
        
        foreach ($component_js as $handle => $path) {
            $js_url = SAW_VISITORS_PLUGIN_URL . 'assets/js/' . $path;
            $js_path = SAW_VISITORS_PLUGIN_DIR . 'assets/js/' . $path;
            if (file_exists($js_path)) {
                $deps = ($handle === 'saw-admin-table-sidebar') 
                    ? ['jquery', 'saw-app', 'saw-admin-table-component']
                    : ['jquery', 'saw-app'];
                $assets[] = [
                    'handle' => $handle,
                    'src' => $js_url . '?v=' . SAW_VISITORS_VERSION,
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
                'src' => SAW_VISITORS_PLUGIN_URL . $css_file_new . '?v=' . SAW_VISITORS_VERSION,
                'deps' => ['saw-variables', 'saw-base-components']
            ];
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $css_file_old)) {
            $assets['css'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $css_file_old . '?v=' . SAW_VISITORS_VERSION,
                'deps' => ['saw-variables', 'saw-base-components']
            ];
        }
        
        // Module JS
        $js_file_new = 'assets/js/modules/' . $slug . '/' . $slug . '.js';
        $js_file_old = 'assets/js/modules/saw-' . $slug . '.js';
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_new)) {
            $assets['js'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $js_file_new . '?v=' . SAW_VISITORS_VERSION,
                'deps' => ['jquery', 'saw-app', 'saw-validation']
            ];
        } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . $js_file_old)) {
            $assets['js'][] = [
                'handle' => 'saw-module-' . $slug,
                'src' => SAW_VISITORS_PLUGIN_URL . $js_file_old . '?v=' . SAW_VISITORS_VERSION,
                'deps' => ['jquery', 'saw-app', 'saw-validation']
            ];
        }
        
        // WordPress editor assets for content module
        if ($slug === 'content') {
            global $wp_styles, $wp_scripts;
            
            // Add WordPress editor CSS
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
            
            // Add WordPress editor JS
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

