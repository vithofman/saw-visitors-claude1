<?php
/**
 * Template: Seznam zákazníků
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';

$admin_table = new SAW_Component_Admin_Table('customers', array(
    
    'title'        => 'Správa zákazníků',
    'subtitle'     => 'Zde můžete spravovat všechny zákazníky v systému',
    'singular'     => 'zákazníka',
    'plural'       => 'Zákazníci',
    'add_new'      => 'Přidat zákazníka',
    
    'columns' => array(
        'logo_url_full' => array(
            'label' => 'Logo',
            'type'  => 'logo',
            'alt'   => 'Logo zákazníka',
            'align' => 'center',
            'sortable' => false,
        ),
        'name' => array(
            'label'    => 'Název',
            'sortable' => true,
            'type'     => 'custom',
            'render'   => function($value, $row) {
                $html = '<strong>' . esc_html($value) . '</strong>';
                if (!empty($row['notes'])) {
                    $html .= '<br><small class="saw-text-muted">' . esc_html(wp_trim_words($row['notes'], 10)) . '</small>';
                }
                return $html;
            },
        ),
        'ico' => array(
            'label'    => 'IČO',
            'sortable' => true,
        ),
        'address' => array(
            'label'    => 'Adresa',
            'sortable' => false,
            'type'     => 'custom',
            'render'   => function($value, $row) {
                if (empty($value)) {
                    return '<span class="saw-text-muted">—</span>';
                }
                return '<small>' . nl2br(esc_html($value)) . '</small>';
            },
        ),
        'primary_color' => array(
            'label' => 'Barva',
            'type'  => 'color',
            'align' => 'center',
            'sortable' => false,
        ),
    ),
    
    'rows'         => $customers,
    'total_items'  => $total_customers,
    
    'current_page' => $page,
    'total_pages'  => $total_pages,
    'per_page'     => 20,
    
    'orderby'      => $orderby,
    'order'        => $order,
    
    'search'       => true,
    'search_value' => $search,
    
    'actions'      => array('edit', 'delete'),
    'create_url'   => home_url('/admin/settings/customers/new/'),
    'edit_url'     => home_url('/admin/settings/customers/edit/{id}/'),
    
    'ajax_search'  => true,
    'ajax_action'  => 'saw_search_customers',
    
    'message'      => $message,
    'message_type' => $message_type,
));

$admin_table->render();