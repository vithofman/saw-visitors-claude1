<?php
/**
 * OOPP Module - List Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Připrav data pro tabulku
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Načti OOPP skupiny pro filtr
global $wpdb;
$customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 0;
$oopp_groups_options = array('' => 'Všechny skupiny');
if ($customer_id) {
    $groups = $wpdb->get_results(
        "SELECT id, code, name FROM {$wpdb->prefix}saw_oopp_groups ORDER BY display_order ASC",
        ARRAY_A
    );
    foreach ($groups as $group) {
        $oopp_groups_options[$group['id']] = $group['code'] . '. ' . $group['name'];
    }
}

// Build table config
$table_config = array(
    'title' => 'Osobní ochranné pracovní prostředky',
    'create_url' => home_url('/admin/oopp/create'),
    'edit_url' => home_url('/admin/oopp/{id}/edit'),
    'detail_url' => home_url('/admin/oopp/{id}/'),
    
    'sidebar_mode' => $sidebar_mode ?? null,
    'detail_item' => $detail_item ?? null,
    'form_item' => $form_item ?? null,
    'detail_tab' => $detail_tab ?? 'overview',
    'related_data' => $related_data ?? null,
    
    'module_config' => isset($config) ? $config : array(),
    
    'rows' => $items,
    'total_items' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'orderby' => $orderby,
    'order' => $order,
    
    'actions' => array('view', 'edit', 'delete'),
    'empty_message' => 'Žádné OOPP nenalezeny',
    'add_new' => 'Nový OOPP',
    
    'ajax_enabled' => true,
    'ajax_nonce' => $ajax_nonce,
);

// Search configuration
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat OOPP...',
    'fields' => array('name', 'standards'),
    'show_info_banner' => true,
);

// Filters
$table_config['filters'] = array(
    'group_id' => array(
        'label' => 'Skupina',
        'type' => 'select',
        'options' => $oopp_groups_options,
    ),
    'is_active' => array(
        'label' => 'Stav',
        'type' => 'select',
        'options' => array(
            '' => 'Všechny',
            '1' => '✅ Aktivní',
            '0' => '❌ Neaktivní',
        ),
    ),
);

// Columns
$table_config['columns'] = array(
    'image' => array(
        'label' => '',
        'type' => 'custom',
        'width' => '60px',
        'sortable' => false,
        'callback' => function($value, $item) {
            if (!empty($item['image_path'])) {
                $upload_dir = wp_upload_dir();
                $url = $upload_dir['baseurl'] . '/' . ltrim($item['image_path'], '/');
                echo '<img src="' . esc_url($url) . '" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">';
            } else {
                echo '<div style="width:50px;height:50px;background:#f1f5f9;border-radius:4px;display:flex;align-items:center;justify-content:center;">🦺</div>';
            }
        },
    ),
    'group_display' => array(
        'label' => 'Skupina',
        'type' => 'custom',
        'width' => '280px',
        'sortable' => true,
        'callback' => function($value, $item) {
            $code = $item['group_code'] ?? '';
            $name = $item['group_name'] ?? '';
            echo '<span style="font-weight:600;">' . esc_html($code) . '.</span> ';
            echo '<span style="color:#64748b;">' . esc_html($name) . '</span>';
        },
    ),
    'name' => array(
        'label' => 'Název',
        'type' => 'text',
        'class' => 'saw-table-cell-bold',
        'sortable' => true,
    ),
    'standards' => array(
        'label' => 'Normy',
        'type' => 'custom',
        'width' => '150px',
        'callback' => function($value, $item) {
            if (!empty($item['standards'])) {
                $short = mb_substr($item['standards'], 0, 30);
                if (mb_strlen($item['standards']) > 30) {
                    $short .= '...';
                }
                echo '<span style="color:#64748b;font-size:12px;" title="' . esc_attr($item['standards']) . '">' . esc_html($short) . '</span>';
            } else {
                echo '<span style="color:#cbd5e1;">—</span>';
            }
        },
    ),
    'scope' => array(
        'label' => 'Platnost',
        'type' => 'custom',
        'width' => '180px',
        'callback' => function($value, $item) {
            $branch_count = intval($item['branch_count'] ?? 0);
            $dept_count = intval($item['department_count'] ?? 0);
            
            // Pobočky
            if ($branch_count > 0) {
                echo '<span class="saw-badge saw-badge-info" style="margin-right:4px;">' . $branch_count . ' poboček</span>';
            } else {
                echo '<span class="saw-badge saw-badge-success" style="margin-right:4px;">Všechny pobočky</span>';
            }
            
            echo '<br style="margin-bottom:4px;">';
            
            // Oddělení
            if ($dept_count > 0) {
                echo '<span class="saw-badge saw-badge-warning">' . $dept_count . ' oddělení</span>';
            } else {
                echo '<span class="saw-badge saw-badge-success">Všechna oddělení</span>';
            }
        },
    ),
    'is_active' => array(
        'label' => 'Stav',
        'type' => 'badge',
        'width' => '100px',
        'map' => array(
            '1' => 'success',
            '0' => 'secondary',
        ),
        'labels' => array(
            '1' => '✅ Aktivní',
            '0' => '❌ Neaktivní',
        ),
    ),
);

// Grouping by OOPP group
$table_config['group_by'] = array(
    'field' => 'group_id',
    'label_field' => 'group_display',
    'format' => function($group_id, $first_item) {
        $code = $first_item['group_code'] ?? $group_id;
        $name = $first_item['group_name'] ?? '';
        return $code . '. ' . $name;
    },
);

// Ensure Admin Table class is loaded
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// Render
$table = new SAW_Component_Admin_Table('oopp', $table_config);
$table->render();

