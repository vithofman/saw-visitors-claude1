<?php
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
            'enable_detail_modal' => false,
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    public function render() {
        $config = $this->config;
        $entity = $this->entity;
        
        $this->enqueue_assets();
        
        include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/wrapper.php';
        
        if ($config['enable_detail_modal']) {
            $this->render_modal_template();
        }
    }
    
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        if (!class_exists('SAW_Component_Search')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
        }
        
        if ($this->config['enable_detail_modal']) {
            wp_enqueue_style(
                'saw-customer-detail-modal',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-customer-detail-modal.css',
                array('saw-admin-table'),
                SAW_VISITORS_VERSION
            );
            
            wp_enqueue_script(
                'saw-customer-detail-modal',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-customer-detail-modal.js',
                array('jquery', 'saw-admin-table'),
                SAW_VISITORS_VERSION,
                true
            );
            
            wp_localize_script('saw-customer-detail-modal', 'sawCustomerModal', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_customer_modal_nonce'),
                'entity' => $this->entity,
                'editUrl' => $this->config['edit_url'] ?? '',
            ));
        }
    }
    
    private function render_modal_template() {
        $entity = $this->entity;
        $edit_url = $this->config['edit_url'] ?? '';
        
        $modal_template = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/' . $entity . '/detail-modal.php';
        
        if (file_exists($modal_template)) {
            include $modal_template;
        } else {
            include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/detail-modal-generic.php';
        }
    }
    
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