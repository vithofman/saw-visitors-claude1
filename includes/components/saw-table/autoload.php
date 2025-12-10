<?php
/**
 * SAW Table Component - Autoloader
 * 
 * @version     2.1.0 - FIXED: saw_table_render_list signature
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SAW_TABLE_DIR', dirname(__FILE__) . '/');
define('SAW_TABLE_RENDERERS_DIR', SAW_TABLE_DIR . 'renderers/');
define('SAW_TABLE_TEMPLATES_DIR', SAW_TABLE_DIR . 'templates/');

/**
 * Load all SAW Table classes
 */
function saw_table_load_classes() {
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

saw_table_load_classes();

/**
 * Render complete list page
 * 
 * FIXED: Accepts single array OR three separate arguments
 * 
 * Usage 1 (new - single array):
 *   saw_table_render_list([
 *       'config' => $config,
 *       'items' => $items,
 *       'total' => $total,
 *       ...
 *   ]);
 * 
 * Usage 2 (old - three arguments):
 *   saw_table_render_list($config, $items, $vars);
 * 
 * @param array $config_or_options Config array OR options array with all params
 * @param array $items             Items (only if using 3-arg format)
 * @param array $vars              Extra variables (only if using 3-arg format)
 * @return string HTML
 */
function saw_table_render_list($config_or_options, $items = [], $vars = []) {
    // Detect which format is being used
    if (is_array($config_or_options) && isset($config_or_options['config'])) {
        // NEW FORMAT: Single array with all options
        $options = $config_or_options;
        $config = $options['config'] ?? [];
        $items = $options['items'] ?? [];
        
        // Everything else goes to vars
        unset($options['config'], $options['items']);
        $vars = $options;
    } else {
        // OLD FORMAT: Three separate arguments
        $config = $config_or_options;
        // $items and $vars already set from function params
    }
    
    // Extract variables for template
    extract($vars);
    
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
 */
function saw_table_render_detail($config, $item, $related_data = [], $entity = '') {
    if (class_exists('SAW_Detail_Renderer')) {
        return SAW_Detail_Renderer::render($config, $item, $related_data, $entity);
    }
    return '<div class="sawt-alert sawt-alert-danger">SAW_Detail_Renderer not found</div>';
}

/**
 * Render form sidebar
 */
function saw_table_render_form($config, $item = null, $entity = '') {
    if (class_exists('SAW_Form_Renderer')) {
        return SAW_Form_Renderer::render($config, $item, $entity);
    }
    
    $tr = function($key, $fallback = null) {
        return $fallback ?? $key;
    };
    
    ob_start();
    include SAW_TABLE_TEMPLATES_DIR . 'form-sidebar.php';
    return ob_get_clean();
}

/**
 * Render table rows only (for AJAX infinite scroll)
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
 */
function saw_table_enqueue_assets() {
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
    
    wp_enqueue_script(
        'sawt-table',
        SAW_VISITORS_PLUGIN_URL . 'includes/components/saw-table/assets/js/saw-table.js',
        ['jquery'],
        $version,
        true
    );
    
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
 */
function saw_table_is_loaded() {
    return class_exists('SAW_Table_Renderer') 
        && class_exists('SAW_Detail_Renderer')
        && class_exists('SAW_Badge_Renderer')
        && class_exists('SAW_Section_Renderer');
}

/**
 * Get SAW Table component version
 */
function saw_table_get_version() {
    return '2.1.0';
}

/**
 * Initialize translators for all renderers
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
