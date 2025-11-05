<?php
/**
 * Training Languages List Template
 * 
 * @package SAW_Visitors
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

if (!class_exists('SAW_Component_Modal')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
}

// Prepare search component
ob_start();
$search_component = new SAW_Component_Search('training_languages', array(
    'placeholder' => 'Hledat jazyk...',
    'search_value' => $search,
    'ajax_enabled' => false,
    'ajax_action' => 'saw_search_training_languages',
    'show_button' => true,
    'show_info_banner' => true,
    'info_banner_label' => 'Vyhledávání:',
    'clear_url' => home_url('/admin/training-languages/'),
));
$search_component->render();
$search_html = ob_get_clean();

// No filters
$filters_html = '';

// Initialize admin table
$table = new SAW_Component_Admin_Table('training_languages', [
    'title' => 'Jazyky školení',
    'icon' => '🌐',
    'create_url' => home_url('/admin/training-languages/new/'),
    'edit_url' => home_url('/admin/training-languages/edit/{id}/'),
    
    'columns' => [
        'flag_emoji' => [
            'label' => 'Vlajka',
            'type' => 'custom',
            'width' => '80px',
            'align' => 'center',
            'callback' => function($value) {
                return '<span style="font-size: 32px;">' . esc_html($value) . '</span>';
            }
        ],
        'language_name' => [
            'label' => 'Název jazyka',
            'type' => 'custom',
            'sortable' => true,
            'callback' => function($value, $item) {
                $is_protected = ($item['language_code'] === 'cs');
                $html = '<strong>' . esc_html($value) . '</strong>';
                if ($is_protected) {
                    $html .= ' <span class="saw-badge saw-badge-info saw-badge-sm">Povinný</span>';
                }
                return $html;
            }
        ],
        'language_code' => [
            'label' => 'Kód',
            'type' => 'custom',
            'width' => '100px',
            'sortable' => true,
            'callback' => function($value) {
                return '<span class="saw-code-badge">' . esc_html($value) . '</span>';
            }
        ],
        'branches_count' => [
            'label' => 'Aktivní pobočky',
            'type' => 'custom',
            'align' => 'center',
            'width' => '150px',
            'callback' => function($value) {
                $count = intval($value);
                if ($count > 0) {
                    $label = $count === 1 ? 'pobočka' : 'poboček';
                    return '<span class="saw-badge saw-badge-success">' . $count . ' ' . $label . '</span>';
                } else {
                    return '<span class="saw-badge saw-badge-secondary">0 poboček</span>';
                }
            }
        ],
        'created_at' => [
            'label' => 'Vytvořeno',
            'type' => 'date',
            'format' => 'd.m.Y'
        ]
    ],
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    'search' => $search_html,
    'filters' => $filters_html,
    'actions' => ['edit', 'delete'],
    'empty_message' => 'Žádné jazyky nenalezeny',
    'add_new' => 'Nový jazyk',
    
    'enable_modal' => true,
    'modal_id' => 'training-language-detail',
    'modal_ajax_action' => 'saw_get_training_languages_detail',
    
    'row_data_attributes' => function($item) {
        $is_protected = ($item['language_code'] === 'cs');
        return [
            'data-protected' => $is_protected ? '1' : '0'
        ];
    },
    
    'row_actions_filter' => function($actions, $item) {
        $is_protected = ($item['language_code'] === 'cs');
        if ($is_protected) {
            unset($actions['delete']);
        }
        return $actions;
    }
]);

$table->render();

// Modal component
$language_modal = new SAW_Component_Modal('training-language-detail', array(
    'title' => 'Detail jazyka',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_training_languages_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/training-languages/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tento jazyk?',
            'ajax_action' => 'saw_delete_training_languages',
        ),
    ),
));
$language_modal->render();