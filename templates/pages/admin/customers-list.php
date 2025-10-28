<?php
/**
 * Template: Seznam zákazníků - REFACTORED pro SAW_Admin_Table
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Načti SAW_Admin_Table class
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin-table/class-saw-admin-table.php';

// Vytvoř instanci admin tabulky
$admin_table = new SAW_Admin_Table('customers', array(
    
    // LABELS
    'title'        => 'Správa zákazníků',
    'subtitle'     => 'Zde můžete spravovat všechny zákazníky v systému',
    'singular'     => 'zákazníka',
    'plural'       => 'Zákazníci',
    'add_new'      => 'Přidat zákazníka',
    
    // COLUMNS DEFINITION
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
    
    // DATA
    'rows'         => $customers,
    'total_items'  => $total_customers,
    
    // PAGINATION
    'current_page' => $page,
    'total_pages'  => $total_pages,
    'per_page'     => 20,
    
    // SORTING
    'orderby'      => $orderby,
    'order'        => $order,
    
    // SEARCH
    'search'       => true,
    'search_value' => $search,
    
    // ACTIONS
    'actions'      => array('edit', 'delete'),
    'create_url'   => home_url('/admin/settings/customers/new/'),
    'edit_url'     => home_url('/admin/settings/customers/edit/{id}/'),
    
    // AJAX
    'ajax_search'  => true,
    'ajax_action'  => 'saw_search_customers',
    
    // MESSAGES
    'message'      => $message,
    'message_type' => $message_type,
));

// Renderuj tabulku
$admin_table->render();
?>