<?php
/**
 * Admin Table Component
 * 
 * Univerzální komponenta pro admin tabulky s podporou:
 * - Řazení sloupců
 * - Vyhledávání (search komponenta)
 * - Akce (edit, delete)
 * - Zprávy (success, error)
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Admin_Table {
    
    private $entity;
    private $config;
    
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        $this->config = $this->parse_config($config);
    }
    
    private function parse_config($config) {
        $defaults = array(
            'columns' => array(),
            'rows' => array(),
            'total_items' => 0,
            'current_page' => 1,
            'total_pages' => 1,
            'per_page' => 20,
            'orderby' => '',
            'order' => 'ASC',
            'search' => true,
            'search_value' => '',
            'actions' => array('edit', 'delete'),
            'create_url' => '',
            'edit_url' => '',
            'ajax_search' => true,
            'ajax_action' => 'saw_admin_table_search',
            'message' => '',
            'message_type' => '',
            'title' => '',
            'subtitle' => '',
            'singular' => '',
            'plural' => '',
            'add_new' => 'Přidat nový',
            'row_class_callback' => null,
            'row_style_callback' => null,
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Render the admin table
     */
    public function render() {
        $config = $this->config;
        $entity = $this->entity;
        
        $this->enqueue_assets();
        
        include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/wrapper.php';
    }
    
    /**
     * Enqueue admin table assets
     */
    private function enqueue_assets() {
        // Admin table CSS
        wp_enqueue_style(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        // Admin table JS
        wp_enqueue_script(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Load search component class if needed
        if (!class_exists('SAW_Component_Search')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
        }
    }
    
    /**
     * Get sort URL for column
     * 
     * @param string $column Column key
     * @param string $current_orderby Current orderby
     * @param string $current_order Current order
     * @return string Sort URL
     */
    public static function get_sort_url($column, $current_orderby, $current_order) {
        $new_order = 'ASC';
        
        if ($column === $current_orderby) {
            $new_order = ($current_order === 'ASC') ? 'DESC' : 'ASC';
        }
        
        $query_args = array(
            'orderby' => $column,
            'order' => $new_order,
        );
        
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $query_args['s'] = sanitize_text_field($_GET['s']);
        }
        
        if (isset($_GET['paged'])) {
            $query_args['paged'] = intval($_GET['paged']);
        }
        
        return add_query_arg($query_args);
    }
    
    /**
     * Get sort icon for column
     * 
     * @param string $column Column key
     * @param string $current_orderby Current orderby
     * @param string $current_order Current order
     * @return string Icon HTML
     */
    public static function get_sort_icon($column, $current_orderby, $current_order) {
        if ($column !== $current_orderby) {
            return '<span class="dashicons dashicons-sort saw-sort-icon"></span>';
        }
        
        if ($current_order === 'ASC') {
            return '<span class="dashicons dashicons-arrow-up saw-sort-icon"></span>';
        } else {
            return '<span class="dashicons dashicons-arrow-down saw-sort-icon"></span>';
        }
    }
}
