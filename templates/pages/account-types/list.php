<?php
if (!defined('ABSPATH')) exit;

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';

$filters_html = '<div class="saw-filters" style="margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-end;">
    <div style="flex: 1; max-width: 200px;">
        <label for="filter-is-active" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px;">Status:</label>
        <select name="is_active" id="filter-is-active" class="saw-input" style="width: 100%;">
            <option value="">Všechny statusy</option>
            <option value="1" ' . selected($is_active_filter ?? '', 1, false) . '>✅ Aktivní</option>
            <option value="0" ' . selected($is_active_filter ?? '', 0, false) . '>❌ Neaktivní</option>
        </select>
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

$admin_table = new SAW_Component_Admin_Table('account-types', [
    'title' => 'Account Types',
    'subtitle' => 'Správa typů účtů v systému',
    'singular' => 'account type',
    'plural' => 'Account Types',
    'add_new' => 'Přidat Account Type',
    
    'before_table' => $filters_html,
    
    'columns' => [
        'id' => [
            'label' => 'ID',
            'sortable' => true,
            'width' => '80px'
        ],
        'display_name' => [
            'label' => 'Název',
            'sortable' => true,
            'type' => 'custom',
            'render' => function($value, $row) {
                $html = '<div style="cursor: pointer;">';
                $html .= '<strong style="font-size: 14px; color: #2563eb;">' . esc_html($value) . '</strong>';
                if (!empty($row['name'])) {
                    $html .= '<br><small style="color: #646970;">Internal: ' . esc_html($row['name']) . '</small>';
                }
                $html .= '</div>';
                return $html;
            }
        ],
        'color' => [
            'label' => 'Barva',
            'type' => 'custom',
            'sortable' => false,
            'align' => 'center',
            'render' => function($value) {
                return '<span style="display: inline-block; width: 28px; height: 28px; border-radius: 50%; background-color: ' . esc_attr($value) . '; border: 2px solid #fff; box-shadow: 0 0 0 1px #dcdcde;" title="' . esc_attr($value) . '"></span>';
            }
        ],
        'price' => [
            'label' => 'Cena',
            'sortable' => true,
            'type' => 'custom',
            'render' => function($value) {
                return '<strong style="color: #2563eb;">$' . number_format($value, 2) . '</strong>';
            }
        ],
        'sort_order' => [
            'label' => 'Pořadí',
            'sortable' => true,
            'width' => '100px',
            'align' => 'center'
        ],
        'is_active' => [
            'label' => 'Status',
            'sortable' => true,
            'type' => 'custom',
            'render' => function($value) {
                $colors = [1 => '#10b981', 0 => '#ef4444'];
                $labels = [1 => 'Aktivní', 0 => 'Neaktivní'];
                $color = $colors[$value] ?? '#6b7280';
                $label = $labels[$value] ?? 'Neznámý';
                return '<span class="saw-badge" style="background-color: ' . esc_attr($color) . '; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html($label) . '</span>';
            }
        ],
        'created_at' => [
            'label' => 'Vytvořeno',
            'sortable' => true,
            'type' => 'custom',
            'render' => function($value) {
                return date('d.m.Y H:i', strtotime($value));
            }
        ]
    ],
    
    'rows' => $account_types,
    'total_items' => $total_account_types,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'orderby' => $orderby,
    'order' => $order,
    'search' => true,
    'search_value' => $search,
    'actions' => ['edit', 'delete'],
    'create_url' => home_url('/admin/settings/account-types/new/'),
    'edit_url' => home_url('/admin/settings/account-types/edit/{id}/'),
    'ajax_search' => false,
]);

$admin_table->render();
?>

<script>
(function($) {
    'use strict';
    
    $('#apply-filters').on('click', function() {
        var isActive = $('#filter-is-active').val();
        var search = $('input[name="s"]').val();
        
        var url = new URL(window.location.href);
        url.searchParams.set('paged', '1');
        
        if (isActive !== '') {
            url.searchParams.set('is_active', isActive);
        } else {
            url.searchParams.delete('is_active');
        }
        
        if (search) {
            url.searchParams.set('s', search);
        }
        
        window.location.href = url.toString();
    });
    
    $('#reset-filters').on('click', function() {
        var url = new URL(window.location.href);
        url.searchParams.delete('is_active');
        url.searchParams.delete('s');
        url.searchParams.set('paged', '1');
        window.location.href = url.toString();
    });
    
})(jQuery);
</script>