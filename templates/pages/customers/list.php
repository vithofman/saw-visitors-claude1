<?php
/**
 * Template: Seznam zákazníků
 * 
 * @package SAW_Visitors
 * @version 4.6.1 FIXED - Sorting now works
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
            'type'  => 'custom',
            'align' => 'center',
            'sortable' => false,
            'render' => function($value, $row) {
                if (empty($value)) {
                    $value = '#6b7280'; // výchozí šedá
                }
                // Vytvoří barevný kroužek s barvou zákazníka
                return '<span class="saw-color-indicator" style="background-color: ' . esc_attr($value) . ';" title="' . esc_attr($value) . '"></span>';
            },
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
    
    // ✅ KRITICKÁ OPRAVA: Vypnuto AJAX pro sorting
    'ajax_search'  => false,  // ✅ ZMĚNĚNO z true na false!
    'ajax_action'  => 'saw_search_customers',
    
    'message'      => $message,
    'message_type' => $message_type,
    
    // ✨ NOVÉ: Callback funkce pro barevné pozadí řádků
    'row_class_callback' => function($row) {
        return 'saw-customer-row';
    },
    'row_style_callback' => function($row) {
        if (!empty($row['primary_color'])) {
            // Vytvoří světlou verzi barvy pro pozadí (gradient 8% → 2% opacity)
            $color = $row['primary_color'];
            // Převede hex na RGB a přidá alpha kanál
            list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
            return 'background: linear-gradient(to right, rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.08) 0%, rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.02) 100%);';
        }
        return '';
    },
));

$admin_table->render();