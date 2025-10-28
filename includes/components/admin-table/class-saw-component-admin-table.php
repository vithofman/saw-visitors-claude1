<?php
/**
 * SAW Component Admin Table
 * 
 * Univerzální admin tabulka pro všechny entity (customers, departments, users, atd.)
 * Globální reusable komponenta kopírující design a funkcionalitu z původního customers-list.php
 * 
 * @package SAW_Visitors
 * @version 4.6.1
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
    }
    
    /**
     * Enqueue CSS a JS assets
     * 
     * Načítá globální CSS/JS pro admin table komponentu
     */
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
        
        wp_enqueue_script(
            'saw-admin-table-ajax',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table-ajax.js',
            array('jquery', 'saw-admin-table'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script(
            'saw-admin-table-ajax',
            'sawAdminTableAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('saw_admin_table_nonce'),
                'entity'  => $this->entity,
            )
        );
    }
    
    /**
     * Helper: Get sort URL
     * 
     * Vytvoří URL s parametry pro řazení sloupce
     * 
     * @param string $column          Název sloupce
     * @param string $current_orderby Aktuální řazený sloupec
     * @param string $current_order   Aktuální směr řazení (ASC/DESC)
     * @return string URL s sort parametry
     */
    public static function get_sort_url($column, $current_orderby, $current_order) {
        $new_order = 'ASC';
        if ($current_orderby === $column && $current_order === 'ASC') {
            $new_order = 'DESC';
        }
        
        $base_url = remove_query_arg(array('orderby', 'order'));
        return add_query_arg(array('orderby' => $column, 'order' => $new_order), $base_url);
    }
    
    /**
     * Helper: Get sort icon
     * 
     * Vrátí HTML se správnou ikonou pro řazení sloupce
     * 
     * @param string $column          Název sloupce
     * @param string $current_orderby Aktuální řazený sloupec
     * @param string $current_order   Aktuální směr řazení (ASC/DESC)
     * @return string HTML s ikonou řazení
     */
    public static function get_sort_icon($column, $current_orderby, $current_order) {
        if ($current_orderby !== $column) {
            return '<span class="saw-sort-icon">⇅</span>';
        }
        
        return $current_order === 'ASC' 
            ? '<span class="saw-sort-icon saw-sort-asc">▲</span>' 
            : '<span class="saw-sort-icon saw-sort-desc">▼</span>';
    }
    
    /**
     * Getter pro entity
     * 
     * @return string Název entity
     */
    public function get_entity() {
        return $this->entity;
    }
    
    /**
     * Getter pro config
     * 
     * @return array Kompletní konfigurace tabulky
     */
    public function get_config() {
        return $this->config;
    }
}