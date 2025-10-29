<?php
/**
 * Template: Seznam zákazníků
 * 
 * @package SAW_Visitors
 * @version 4.6.1 ENHANCED
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';

// Filtry pro tabulku
$filters_html = '<div class="saw-filters" style="margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-end;">
    <div style="flex: 1; max-width: 200px;">
        <label for="filter-status" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Status:</label>
        <select name="status" id="filter-status" class="saw-input" style="width: 100%;">
            <option value="">Všechny statusy</option>
            <option value="potential" ' . selected($status_filter ?? '', 'potential', false) . '>⏳ Potenciální</option>
            <option value="active" ' . selected($status_filter ?? '', 'active', false) . '>✅ Aktivní</option>
            <option value="inactive" ' . selected($status_filter ?? '', 'inactive', false) . '>❌ Neaktivní</option>
        </select>
    </div>
    
    <div style="flex: 1; max-width: 200px;">
        <label for="filter-account-type" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Typ účtu:</label>
        <select name="account_type" id="filter-account-type" class="saw-input" style="width: 100%;">
            <option value="">Všechny typy</option>';
            
if (!empty($account_types_for_filter)) {
    foreach ($account_types_for_filter as $id => $display_name) {
        $selected = selected($account_type_filter ?? '', $id, false);
        $filters_html .= '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($display_name) . '</option>';
    }
}

$filters_html .= '</select>
    </div>
    
    <button type="button" id="apply-filters" class="saw-button saw-button-primary" style="padding: 10px 20px;">
        <span class="dashicons dashicons-filter" style="font-size: 16px; margin-right: 4px;"></span>
        Filtrovat
    </button>
    
    <button type="button" id="reset-filters" class="saw-button saw-button-secondary" style="padding: 10px 20px;">
        <span class="dashicons dashicons-image-rotate" style="font-size: 16px; margin-right: 4px;"></span>
        Reset
    </button>
</div>';

$admin_table = new SAW_Component_Admin_Table('customers', array(
    
    'title'        => 'Správa zákazníků',
    'subtitle'     => 'Zde můžete spravovat všechny zákazníky v systému',
    'singular'     => 'zákazníka',
    'plural'       => 'Zákazníci',
    'add_new'      => 'Přidat zákazníka',
    
    'before_table' => $filters_html,
    
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
                $html = '<strong style="font-size: 14px;">' . esc_html($value) . '</strong>';
                if (!empty($row['ico'])) {
                    $html .= '<br><small style="color: #646970;">IČO: ' . esc_html($row['ico']) . '</small>';
                }
                return $html;
            },
        ),
        'status' => array(
            'label' => 'Status',
            'type'  => 'custom',
            'sortable' => true,
            'render' => function($value, $row) {
                $colors = array(
                    'potential' => '#f59e0b',
                    'active'    => '#10b981',
                    'inactive'  => '#ef4444',
                );
                $labels = array(
                    'potential' => 'Potenciální',
                    'active'    => 'Aktivní',
                    'inactive'  => 'Neaktivní',
                );
                $color = $colors[$value] ?? '#6b7280';
                $label = $labels[$value] ?? $value;
                
                return '<span class="saw-badge" style="background-color: ' . esc_attr($color) . '; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html($label) . '</span>';
            },
        ),
        'account_type_display_name' => array(
            'label' => 'Typ účtu',
            'type'  => 'custom',
            'sortable' => false,
            'render' => function($value, $row) {
                if (empty($value)) {
                    return '<span style="color: #9ca3af; font-style: italic;">Bez typu</span>';
                }
                $color = $row['account_type_color'] ?? '#6b7280';
                return '<span class="saw-badge" style="background-color: ' . esc_attr($color) . '; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">' . esc_html($value) . '</span>';
            },
        ),
        'contact_email' => array(
            'label' => 'Email',
            'type'  => 'custom',
            'sortable' => false,
            'render' => function($value, $row) {
                if (empty($value)) {
                    return '<span style="color: #9ca3af;">—</span>';
                }
                return '<a href="mailto:' . esc_attr($value) . '" style="color: #0073aa; text-decoration: none;">' . esc_html($value) . '</a> <button type="button" class="copy-email-btn" data-email="' . esc_attr($value) . '" style="background: transparent; border: none; cursor: pointer; color: #646970; padding: 0; margin-left: 4px;" title="Zkopírovat email"><span class="dashicons dashicons-admin-page" style="font-size: 14px;"></span></button>';
            },
        ),
        'address_city' => array(
            'label' => 'Město',
            'sortable' => false,
            'type' => 'custom',
            'render' => function($value, $row) {
                if (empty($value)) {
                    return '<span style="color: #9ca3af;">—</span>';
                }
                return '<span style="font-size: 13px;">' . esc_html($value) . '</span>';
            },
        ),
        'primary_color' => array(
            'label' => 'Barva',
            'type'  => 'custom',
            'align' => 'center',
            'sortable' => false,
            'render' => function($value, $row) {
                if (empty($value)) {
                    $value = '#6b7280';
                }
                return '<span class="saw-color-indicator" style="display: inline-block; width: 28px; height: 28px; border-radius: 50%; background-color: ' . esc_attr($value) . '; border: 2px solid #fff; box-shadow: 0 0 0 1px #dcdcde;" title="' . esc_attr($value) . '"></span>';
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
    
    'ajax_search'  => false,
    'ajax_action'  => 'saw_search_customers',
    
    'message'      => $message,
    'message_type' => $message_type,
    
    'enable_detail_modal' => true,
    
    'row_class_callback' => function($row) {
        return 'saw-customer-row';
    },
    'row_style_callback' => function($row) {
        if (!empty($row['primary_color'])) {
            $color = $row['primary_color'];
            list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
            return 'background: linear-gradient(to right, rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.08) 0%, rgba(' . $r . ', ' . $g . ', ' . $b . ', 0.02) 100%);';
        }
        return '';
    },
));

$admin_table->render();
?>

<script>
(function($) {
    'use strict';
    
    // Apply filters
    $('#apply-filters').on('click', function() {
        var status = $('#filter-status').val();
        var accountType = $('#filter-account-type').val();
        var search = $('input[name="s"]').val();
        
        var url = new URL(window.location.href);
        url.searchParams.set('paged', '1');
        
        if (status) {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        
        if (accountType) {
            url.searchParams.set('account_type', accountType);
        } else {
            url.searchParams.delete('account_type');
        }
        
        if (search) {
            url.searchParams.set('s', search);
        }
        
        window.location.href = url.toString();
    });
    
    // Reset filters
    $('#reset-filters').on('click', function() {
        var url = new URL(window.location.href);
        url.searchParams.delete('status');
        url.searchParams.delete('account_type');
        url.searchParams.delete('s');
        url.searchParams.set('paged', '1');
        window.location.href = url.toString();
    });
    
    // Copy email to clipboard
    $(document).on('click', '.copy-email-btn', function() {
        var email = $(this).data('email');
        navigator.clipboard.writeText(email).then(function() {
            alert('Email zkopírován: ' + email);
        });
    });
    
})(jQuery);
</script>