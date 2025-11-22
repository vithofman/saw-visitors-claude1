<?php
/**
 * Companies List Template
 * @version 2.0.0 - REFACTORED: New architecture
 */

if (!defined('ABSPATH')) exit;

// Prepare config for AdminTable
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'companies'));

$table_config['title'] = $config['plural'];
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// Column definitions
$table_config['columns'] = array(
    'name' => array(
        'label' => 'Název firmy',
        'type' => 'text',
        'sortable' => true,
        'class' => 'saw-table-cell-bold'
    ),
    'ico' => array(
        'label' => 'IČO',
        'type' => 'text',
        'sortable' => true,
        'width' => '100px'
    ),
    'street' => array(
        'label' => 'Ulice',
        'type' => 'text'
    ),
    'city' => array(
        'label' => 'Město',
        'type' => 'text',
        'sortable' => true
    ),
    'zip' => array(
        'label' => 'PSČ',
        'type' => 'text',
        'width' => '80px'
    ),
    'email' => array(
        'label' => 'Email',
        'type' => 'email'
    ),
    'phone' => array(
        'label' => 'Telefon',
        'type' => 'text',
        'width' => '140px'
    ),
    'is_archived' => array(
        'label' => 'Status',
        'type' => 'custom',
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value) {
            if (!empty($value)) {
                return '<span class="saw-badge saw-badge-secondary">Archivováno</span>';
            }
            return '<span class="saw-badge saw-badge-success">Aktivní</span>';
        }
    ),
    'created_at' => array(
        'label' => 'Vytvořeno',
        'type' => 'date',
        'width' => '120px',
        'format' => 'd.m.Y'
    )
);

// Data
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['search_value'] = $search;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// Search configuration - NEW FORMAT
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat firmy...',
    'fields' => array('name', 'ico', 'email', 'phone', 'city'), // Pole pro vyhledávání
    'show_info_banner' => true,
);

// Filters configuration - NEW FORMAT
$table_config['filters'] = array(
    'is_archived' => array(
        'type' => 'select',
        'label' => 'Status',
        'options' => array(
            '' => 'Všechny',
            '0' => 'Aktivní',
            '1' => 'Archivované',
        ),
    ),
);

// Sidebar context
$table_config['sidebar_mode'] = $sidebar_mode;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab;
$table_config['module_config'] = $config;

// Actions
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nová firma';

// TABS configuration - loaded from config.php
$table_config['tabs'] = $config['tabs'] ?? null;

// Infinite scroll - UPRAVENÉ hodnoty
$table_config['infinite_scroll'] = array(
    'enabled' => true, // Enable infinite scroll
    'initial_load' => 100, // NOVÉ: První načtení 100 řádků
    'per_page' => 50, // Poté po 50 řádcích
    'threshold' => 0.6, // OPRAVENO 2025-01-22: 60% scroll pro dřívější loading
);

// NOVÉ: Pass tab data from get_list_data() result
// CRITICAL: Ensure current_tab is always a valid string, never null
if (!empty($table_config['tabs']['enabled'])) {
    // Use isset() and !== null/'' to handle all cases, including '0' values
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// Ensure Admin Table class is loaded
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// Render
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();