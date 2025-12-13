<?php
/**
 * OOPP List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     2.0.0 - Refactored: Fixed column widths, infinite scroll
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'oopp') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// COMPONENT LOADING
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// DATA FROM CONTROLLER
// ============================================
$items = $items ?? array();
$total = $total ?? 0;
$page = $page ?? 1;
$total_pages = $total_pages ?? 0;
$search = $search ?? '';
$orderby = $orderby ?? 'name';
$order = $order ?? 'ASC';

// ============================================
// LOAD DATA FOR FILTERS
// ============================================
global $wpdb;
$customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 0;

// Načti OOPP skupiny pro filtr
$oopp_groups_options = array('' => $tr('filter_all_groups', 'Všechny skupiny'));
$groups = $wpdb->get_results(
    "SELECT id, code, name FROM {$wpdb->prefix}saw_oopp_groups ORDER BY display_order ASC",
    ARRAY_A
);
if ($groups) {
    foreach ($groups as $group) {
        $oopp_groups_options[$group['id']] = $group['code'] . '. ' . $group['name'];
    }
}

// Načti pobočky pro filtr
$branches_options = array('' => $tr('filter_all_branches', 'Všechny pobočky'));
if ($customer_id) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
    if ($branches) {
        foreach ($branches as $branch) {
            $branches_options[$branch['id']] = $branch['name'];
        }
    }
}

// Načti oddělení pro filtr
$departments_options = array('' => $tr('filter_all_departments', 'Všechna oddělení'));
if ($customer_id) {
    $departments = $wpdb->get_results($wpdb->prepare(
        "SELECT d.id, d.name, b.name as branch_name 
         FROM {$wpdb->prefix}saw_departments d
         LEFT JOIN {$wpdb->prefix}saw_branches b ON d.branch_id = b.id
         WHERE d.customer_id = %d AND d.is_active = 1 
         ORDER BY b.name ASC, d.name ASC",
        $customer_id
    ), ARRAY_A);
    if ($departments) {
        foreach ($departments as $dept) {
            $label = $dept['name'];
            if (!empty($dept['branch_name'])) {
                $label .= ' (' . $dept['branch_name'] . ')';
            }
            $departments_options[$dept['id']] = $label;
        }
    }
}

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $config['plural'] ?? $tr('plural', 'Osobní ochranné pracovní prostředky'),
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
    'empty_message' => $tr('empty_message', 'Žádné OOPP nenalezeny'),
    'add_new' => $tr('btn_add_new', 'Nový OOPP'),
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat OOPP...'),
    'fields' => array('name', 'standards', 'risk_description'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'group_id' => array(
        'label' => $tr('filter_group', 'Skupina'),
        'type' => 'select',
        'options' => $oopp_groups_options,
    ),
    'branch_id' => array(
        'label' => $tr('filter_branch', 'Pobočka'),
        'type' => 'select',
        'options' => $branches_options,
    ),
    'department_id' => array(
        'label' => $tr('filter_department', 'Oddělení'),
        'type' => 'select',
        'options' => $departments_options,
    ),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================

// Store translations for use in callbacks
$list_translations = array(
    'col_group' => $tr('col_group', 'Skupina'),
    'col_name' => $tr('col_name', 'Název'),
    'col_standards' => $tr('col_standards', 'Normy'),
    'col_scope' => $tr('col_scope', 'Platnost'),
    'col_status' => $tr('col_status', 'Stav'),
    'branch_singular' => $tr('list_branch_singular', 'pobočka'),
    'branch_plural' => $tr('list_branch_plural', 'poboček'),
    'all_branches' => $tr('all_branches', 'Všechny pobočky'),
    'departments_count' => $tr('list_departments_count', 'oddělení'),
    'all_departments' => $tr('all_departments', 'Všechna oddělení'),
    'status_active' => $tr('status_active', 'Aktivní'),
    'status_inactive' => $tr('status_inactive', 'Neaktivní'),
);

$table_config['columns'] = array(
    'image' => array(
        'label' => '',
        'type' => 'custom',
        'width' => '10%',  // Obrázek
        'sortable' => false,
        'callback' => function($value, $item) {
            if (!empty($item['image_path'])) {
                $upload_dir = wp_upload_dir();
                $url = $upload_dir['baseurl'] . '/' . ltrim($item['image_path'], '/');
                echo '<div style="display:flex;align-items:center;justify-content:center;padding:10px;min-width:100px;">';
                echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($item['name'] ?? '') . '" style="width:80px;height:80px;min-width:80px;object-fit:cover;border-radius:8px;border:3px solid #e2e8f0;background:#f8fafc;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
                echo '</div>';
            } else {
                echo '<div style="display:flex;align-items:center;justify-content:center;padding:10px;min-width:100px;">';
                echo '<div style="width:80px;height:80px;min-width:80px;background:linear-gradient(135deg,#f1f5f9 0%,#e2e8f0 100%);border-radius:8px;border:3px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;">';
                echo '<span style="font-size:32px;">🦺</span>';
                echo '</div>';
                echo '</div>';
            }
        },
    ),
    
    'group_display' => array(
        'label' => $list_translations['col_group'],
        'type' => 'custom',
        'width' => '25%',  // Skupina střední
        'sortable' => true,
        'sort_column' => 'group_id',
        'callback' => function($value, $item) {
            $code = $item['group_code'] ?? '';
            $name = $item['group_name'] ?? '';
            
            if (empty($code) && empty($name)) {
                echo '<span style="color:#94a3b8; font-style:italic;">—</span>';
                return;
            }
            ?>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 32px;
                    height: 32px;
                    padding: 0 10px;
                    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                    color: #ffffff;
                    font-weight: 800;
                    font-size: 15px;
                    border-radius: 6px;
                    box-shadow: 0 2px 6px rgba(29, 78, 216, 0.4);
                "><?php echo esc_html($code); ?>.</span>
                <span style="
                    font-weight: 600;
                    font-size: 14px;
                    color: #1e293b;
                    line-height: 1.4;
                "><?php echo esc_html($name); ?></span>
            </div>
            <?php
        },
    ),
    
    'name' => array(
        'label' => $list_translations['col_name'],
        'type' => 'custom',
        'sortable' => true,
        'width' => '30%',  // Hlavní identifikátor
        'callback' => function($value, $item) {
            ?>
            <span style="
                font-weight: 700;
                font-size: 15px;
                color: #0f172a;
                line-height: 1.4;
            "><?php echo esc_html($value); ?></span>
            <?php
        },
    ),
    
    'standards' => array(
        'label' => $list_translations['col_standards'],
        'type' => 'custom',
        'width' => '13%',  // Normy střední
        'callback' => function($value, $item) {
            if (!empty($item['standards'])) {
                $short = mb_substr($item['standards'], 0, 20);
                if (mb_strlen($item['standards']) > 20) {
                    $short .= '…';
                }
                ?>
                <span 
                    title="<?php echo esc_attr($item['standards']); ?>"
                    style="
                        display: inline-block;
                        padding: 6px 12px;
                        background: #1e293b;
                        color: #ffffff;
                        font-size: 12px;
                        font-weight: 600;
                        border-radius: 6px;
                        cursor: help;
                        max-width: 100%;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    "
                ><?php echo esc_html($short); ?></span>
                <?php
            } else {
                echo '<span style="color:#94a3b8; font-style:italic;">—</span>';
            }
        },
    ),
    
    'scope' => array(
        'label' => $list_translations['col_scope'],
        'type' => 'custom',
        'width' => '15%',  // Platnost střední
        'callback' => function($value, $item) use ($list_translations) {
            $branch_count = intval($item['branch_count'] ?? 0);
            $dept_count = intval($item['department_count'] ?? 0);
            ?>
            <div style="display:flex; flex-direction:column; gap:6px;">
                <?php if ($branch_count > 0): ?>
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 5px 10px;
                        background: #dbeafe;
                        color: #1e40af;
                        font-size: 12px;
                        font-weight: 700;
                        border-radius: 6px;
                        border: 1px solid #93c5fd;
                    ">
                        🏢 <?php echo $branch_count; ?> <?php echo $branch_count === 1 ? $list_translations['branch_singular'] : $list_translations['branch_plural']; ?>
                    </span>
                <?php else: ?>
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 5px 10px;
                        background: #dcfce7;
                        color: #166534;
                        font-size: 12px;
                        font-weight: 700;
                        border-radius: 6px;
                        border: 1px solid #86efac;
                    ">
                        ✓ <?php echo esc_html($list_translations['all_branches']); ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($dept_count > 0): ?>
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 5px 10px;
                        background: #fef3c7;
                        color: #92400e;
                        font-size: 12px;
                        font-weight: 700;
                        border-radius: 6px;
                        border: 1px solid #fcd34d;
                    ">
                        📁 <?php echo $dept_count; ?> <?php echo esc_html($list_translations['departments_count']); ?>
                    </span>
                <?php else: ?>
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 5px 10px;
                        background: #dcfce7;
                        color: #166534;
                        font-size: 12px;
                        font-weight: 700;
                        border-radius: 6px;
                        border: 1px solid #86efac;
                    ">
                        ✓ <?php echo esc_html($list_translations['all_departments']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php
        },
    ),
    
    'is_active' => array(
        'label' => $list_translations['col_status'],
        'type' => 'badge',
        'sortable' => true,
        'width' => '7%',   // Badge malý
        'align' => 'center',
        'map' => array(
            '1' => 'success',
            '0' => 'secondary',
        ),
        'labels' => array(
            '1' => $list_translations['status_active'],
            '0' => $list_translations['status_inactive'],
        ),
    ),
);
// Součet: 10 + 25 + 30 + 13 + 15 + 7 = 100%

// ============================================
// TABS CONFIGURATION
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// INFINITE SCROLL CONFIGURATION
// ============================================
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// ============================================
// RENDER TABLE
// ============================================
$table = new SAW_Component_Admin_Table('oopp', $table_config);
$table->render();