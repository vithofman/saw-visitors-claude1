<?php
/**
 * SAW Component Admin Table
 * 
 * Univerzální admin tabulka pro všechny entity (customers, departments, users, atd.)
 * Globální reusable komponenta kopírující design a funkcionalitu z původního customers-list.php
 * 
 * @package SAW_Visitors
 * @version 4.6.1 ENHANCED - Modal detail support + Responsive
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Admin_Table {
    
    /**
     * Název entity (customers, departments, users, atd.)
     * 
     * @var string
     */
    private $entity;
    
    /**
     * Konfigurace tabulky
     * 
     * @var array
     */
    private $config;
    
    /**
     * Konstruktor
     * 
     * @param string $entity Název entity
     * @param array  $config Konfigurace tabulky
     */
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        $this->config = $this->parse_config($config);
    }
    
    /**
     * Parse konfigurace s výchozími hodnotami
     * 
     * @param array $config Uživatelská konfigurace
     * @return array Kompletní konfigurace s defaults
     */
    private function parse_config($config) {
        $defaults = array(
            // Columns definition
            'columns'      => array(),
            
            // Data
            'rows'         => array(),
            'total_items'  => 0,
            
            // Pagination
            'current_page' => 1,
            'total_pages'  => 1,
            'per_page'     => 20,
            
            // Sorting
            'orderby'      => '',
            'order'        => 'ASC',
            
            // Search
            'search'       => true,
            'search_value' => '',
            
            // Actions
            'actions'      => array('edit', 'delete'),
            'create_url'   => '',
            'edit_url'     => '',
            
            // AJAX
            'ajax_search'  => true,
            'ajax_action'  => 'saw_admin_table_search',
            
            // Messages
            'message'      => '',
            'message_type' => '',
            
            // Labels
            'title'        => '',
            'subtitle'     => '',
            'singular'     => '',
            'plural'       => '',
            'add_new'      => 'Přidat nový',
            
            // ✨ NOVÉ: Callback funkce pro custom styling řádků
            'row_class_callback' => null,
            'row_style_callback' => null,
            
            // ✨ NOVÉ: Detail modal
            'enable_detail_modal' => false,
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Render tabulku
     * 
     * Načte wrapper template s kompletní admin tabulkou
     */
    public function render() {
        $config = $this->config;
        $entity = $this->entity;
        
        $this->enqueue_assets();
        
        include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/wrapper.php';
        
        // ✨ NOVÉ: Pokud je zapnutý modal detail, načti modal template
        if ($config['enable_detail_modal']) {
            $this->render_modal_template();
        }
    }
    
    /**
     * Enqueue CSS a JS assets
     * 
     * Načítá globální CSS/JS pro admin table komponentu
     */
    private function enqueue_assets() {
        // Základní admin table CSS
        wp_enqueue_style(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        // Základní admin table JS
        wp_enqueue_script(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // ✨ NOVÉ: Pokud je zapnutý modal, načti modal assets
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
            
            // Localize data pro modal JS
            wp_localize_script('saw-customer-detail-modal', 'sawCustomerModal', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('saw_customer_modal_nonce'),
                'entity'  => $this->entity,
                'editUrl' => $this->config['edit_url'] ?? '',
            ));
        }
    }
    
    /**
     * ✨ NOVÉ: Render modal template
     */
    private function render_modal_template() {
        $entity = $this->entity;
        $edit_url = $this->config['edit_url'] ?? '';
        
        // Načti modal template podle entity
        $modal_template = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/' . $entity . '/detail-modal.php';
        
        if (file_exists($modal_template)) {
            include $modal_template;
        } else {
            // Fallback na generický modal
            include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/detail-modal-generic.php';
        }
    }
    
    /**
     * Získá URL pro řazení podle sloupce
     * 
     * @param string $column Název sloupce
     * @param string $current_orderby Aktuální orderby
     * @param string $current_order Aktuální order
     * @return string URL pro řazení
     */
    public static function get_sort_url($column, $current_orderby, $current_order) {
        $new_order = 'ASC';
        
        if ($column === $current_orderby) {
            $new_order = ($current_order === 'ASC') ? 'DESC' : 'ASC';
        }
        
        $query_args = array(
            'orderby' => $column,
            'order'   => $new_order,
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
     * Vrátí ikonu pro řazení
     * 
     * @param string $column Název sloupce
     * @param string $current_orderby Aktuální orderby
     * @param string $current_order Aktuální order
     * @return string HTML ikony
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