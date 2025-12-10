<?php
/**
 * SAW Table Component - Autoloader
 * 
 * Loads all SAW Table classes and provides helper functions.
 * All classes use sawt- CSS prefix.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Component Directory
 */
define('SAW_TABLE_DIR', dirname(__FILE__) . '/');
define('SAW_TABLE_RENDERERS_DIR', SAW_TABLE_DIR . 'renderers/');
define('SAW_TABLE_TEMPLATES_DIR', SAW_TABLE_DIR . 'templates/');

/**
 * Load all SAW Table classes
 */
function saw_table_load_classes() {
    // Core renderers (order matters for dependencies)
    $classes = [
        'class-badge-renderer.php',
        'class-row-renderer.php',
        'class-section-renderer.php',
        'class-table-renderer.php',
        'class-detail-renderer.php',
        'class-form-renderer.php',
    ];
    
    foreach ($classes as $class) {
        $file = SAW_TABLE_RENDERERS_DIR . $class;
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    // Support classes (if exist)
    $support_classes = [
        'class-saw-table-config.php',
        'class-saw-table-permissions.php',
        'class-saw-table-translations.php',
    ];
    
    foreach ($support_classes as $class) {
        $file = SAW_TABLE_DIR . $class;
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

// Auto-load classes
saw_table_load_classes();

/**
 * ============================================
 * HELPER FUNCTIONS
 * ============================================
 */

/**
 * Render complete list page
 * 
 * @param array $config Module configuration
 * @param array $items  Data items
 * @param array $vars   Additional template variables
 * @return string HTML
 */
function saw_table_render_list($config, $items, $vars = []) {
    // Extract variables
    extract($vars);
    
    // Make config and items available
    $config = $config;
    $items = $items;
    
    // Setup translation helper if not provided
    if (!isset($tr)) {
        $tr = function($key, $fallback = null) use ($config) {
            if (function_exists('saw_get_translations')) {
                static $translations = null;
                if ($translations === null) {
                    $lang = class_exists('SAW_Component_Language_Switcher') 
                        ? SAW_Component_Language_Switcher::get_user_language() 
                        : 'cs';
                    $translations = saw_get_translations($lang, 'admin', $config['entity'] ?? 'common');
                }
                return $translations[$key] ?? $fallback ?? $key;
            }
            return $fallback ?? $key;
        };
    }
    
    // Include template
    ob_start();
    include SAW_TABLE_TEMPLATES_DIR . 'list.php';
    return ob_get_clean();
}

/**
 * Render detail sidebar
 * 
 * @param array  $config       Module configuration
 * @param array  $item         Item data
 * @param array  $related_data Related data
 * @param string $entity       Entity name
 * @return string HTML
 */
function saw_table_render_detail($config, $item, $related_data = [], $entity = '') {
    if (class_exists('SAW_Detail_Renderer')) {
        return SAW_Detail_Renderer::render($config, $item, $related_data, $entity);
    }
    return '<div class="sawt-alert sawt-alert-danger">SAW_Detail_Renderer not found</div>';
}

/**
 * Render form sidebar
 * 
 * @param array       $config Module configuration
 * @param array|null  $item   Item data (null for create)
 * @param string      $entity Entity name
 * @return string HTML
 */
function saw_table_render_form($config, $item = null, $entity = '') {
    if (class_exists('SAW_Form_Renderer')) {
        return SAW_Form_Renderer::render($config, $item, $entity);
    }
    
    // Fallback to template
    $tr = function($key, $fallback = null) {
        return $fallback ?? $key;
    };
    
    ob_start();
    include SAW_TABLE_TEMPLATES_DIR . 'form-sidebar.php';
    return ob_get_clean();
}

/**
 * Render table rows only (for AJAX infinite scroll)
 * 
 * @param array $config Module configuration
 * @param array $items  Data items
 * @return string HTML
 */
function saw_table_render_rows($config, $items) {
    if (class_exists('SAW_Table_Renderer')) {
        $columns = $config['table']['columns'] ?? $config['columns'] ?? [];
        $actions = $config['actions'] ?? ['view', 'edit', 'delete'];
        $entity = $config['entity'] ?? 'items';
        $route = $config['route'] ?? $entity;
        $detail_url = home_url('/admin/' . $route . '/{id}/');
        $edit_url = home_url('/admin/' . $route . '/{id}/edit');
        
        return SAW_Table_Renderer::render_rows($columns, $items, $actions, $detail_url, $edit_url, $entity);
    }
    return '';
}

/**
 * Enqueue SAW Table assets
 * 
 * Should be called via Asset Loader, but can be used directly.
 */
function saw_table_enqueue_assets() {
    // CSS files (in order)
    $css_files = [
        'saw-table-variables' => 'saw-table-variables.css',
        'saw-table-list' => 'saw-table-list.css',
        'saw-table-sidebar' => 'saw-table-sidebar.css',
        'saw-table-detail' => 'saw-table-detail.css',
        'saw-table-form' => 'saw-table-form.css',
    ];
    
    $css_base = SAW_VISITORS_PLUGIN_URL . 'assets/css/components/saw-table/';
    $version = SAW_VISITORS_VERSION ?? '1.0.0';
    
    foreach ($css_files as $handle => $file) {
        wp_enqueue_style(
            'sawt-' . $handle,
            $css_base . $file,
            [],
            $version
        );
    }
    
    // JS file
    wp_enqueue_script(
        'sawt-table',
        SAW_VISITORS_PLUGIN_URL . 'includes/components/saw-table/assets/js/saw-table.js',
        ['jquery'],
        $version,
        true
    );
    
    // Localize script
    wp_localize_script('sawt-table', 'sawtGlobal', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('saw_ajax_nonce'),
        'i18n' => [
            'loading' => __('Načítání...', 'saw-visitors'),
            'error' => __('Došlo k chybě', 'saw-visitors'),
            'confirmDelete' => __('Opravdu chcete smazat tento záznam?', 'saw-visitors'),
            'deleted' => __('Záznam byl smazán', 'saw-visitors'),
            'saved' => __('Záznam byl uložen', 'saw-visitors'),
        ]
    ]);
}

/**
 * Check if SAW Table component is properly loaded
 * 
 * @return bool
 */
function saw_table_is_loaded() {
    return class_exists('SAW_Table_Renderer') 
        && class_exists('SAW_Detail_Renderer')
        && class_exists('SAW_Badge_Renderer')
        && class_exists('SAW_Section_Renderer');
}

/**
 * Get SAW Table component version
 * 
 * @return string
 */
function saw_table_get_version() {
    return '2.0.0';
}

/**
 * Initialize translators for all renderers
 * 
 * @param callable $translator Translation function
 */
function saw_table_set_translator($translator) {
    if (class_exists('SAW_Badge_Renderer')) {
        SAW_Badge_Renderer::set_translator($translator);
    }
    if (class_exists('SAW_Section_Renderer')) {
        SAW_Section_Renderer::set_translator($translator);
    }
    if (class_exists('SAW_Row_Renderer')) {
        SAW_Row_Renderer::set_translator($translator);
    }
    if (class_exists('SAW_Detail_Renderer')) {
        SAW_Detail_Renderer::set_translator($translator);
    }
    if (class_exists('SAW_Form_Renderer')) {
        SAW_Form_Renderer::set_translator($translator);
    }
    if (class_exists('SAW_Table_Renderer')) {
        SAW_Table_Renderer::set_translator($translator);
    }
}

/**
 * Debug: List all loaded SAW Table classes
 * 
 * @return array
 */
function saw_table_debug_classes() {
    return [
        'SAW_Badge_Renderer' => class_exists('SAW_Badge_Renderer'),
        'SAW_Row_Renderer' => class_exists('SAW_Row_Renderer'),
        'SAW_Section_Renderer' => class_exists('SAW_Section_Renderer'),
        'SAW_Table_Renderer' => class_exists('SAW_Table_Renderer'),
        'SAW_Detail_Renderer' => class_exists('SAW_Detail_Renderer'),
        'SAW_Form_Renderer' => class_exists('SAW_Form_Renderer'),
    ];
}
