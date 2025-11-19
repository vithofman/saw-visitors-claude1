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

// Features
$table_config['enable_search'] = true;
$table_config['search_placeholder'] = 'Hledat firmy...';
$table_config['enable_filters'] = true;

// Sidebar context
$table_config['sidebar_mode'] = $sidebar_mode;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab;
$table_config['module_config'] = $config;

// Actions
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nová firma';

// Ensure Admin Table class is loaded
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// Render
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();