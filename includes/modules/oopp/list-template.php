<?php
/**
 * OOPP Module - List Template
 * 
 * @package SAW_Visitors
 * @version 1.5.0 - FIXED: Inline styles for guaranteed visibility
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 0;

// Načti OOPP skupiny pro filtr
$oopp_groups_options = array('' => 'Všechny skupiny');
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
$branches_options = array('' => 'Všechny pobočky');
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
$departments_options = array('' => 'Všechna oddělení');
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

// Prepare config for AdminTable
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'oopp'));

$table_config['title'] = $config['plural'] ?? 'Osobní ochranné pracovní prostředky';
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// COLUMN DEFINITIONS - INLINE STYLES
// ============================================
$table_config['columns'] = array(
    
    // ============================================
    // OBRÁZEK - 80x80px, sloupec 120px široký
    // ============================================
    'image' => array(
        'label' => '',
        'type' => 'custom',
        'width' => '120px',
        'min_width' => '120px',
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
    
    // ============================================
    // SKUPINA OOPP - Čitelné, tmavé písmo
    // ============================================
    'group_display' => array(
        'label' => 'Skupina',
        'type' => 'custom',
        'width' => '300px',
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
    
    // ============================================
    // NÁZEV - Tmavé, tučné písmo
    // ============================================
    'name' => array(
        'label' => 'Název',
        'type' => 'custom',
        'sortable' => true,
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
    
    // ============================================
    // NORMY - Chip styl, čitelné
    // ============================================
    'standards' => array(
        'label' => 'Normy',
        'type' => 'custom',
        'width' => '160px',
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
    
    // ============================================
    // PLATNOST - Badges s ikonami
    // ============================================
    'scope' => array(
        'label' => 'Platnost',
        'type' => 'custom',
        'width' => '180px',
        'callback' => function($value, $item) {
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
                        🏢 <?php echo $branch_count; ?> <?php echo $branch_count === 1 ? 'pobočka' : 'poboček'; ?>
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
                        ✓ Všechny pobočky
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
                        📁 <?php echo $dept_count; ?> oddělení
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
                        ✓ Všechna oddělení
                    </span>
                <?php endif; ?>
            </div>
            <?php
        },
    ),
    
    // ============================================
    // STAV - Badge
    // ============================================
    'is_active' => array(
        'label' => 'Stav',
        'type' => 'custom',
        'width' => '110px',
        'align' => 'center',
        'callback' => function($value) {
            if (!empty($value)) {
                ?>
                <span style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 6px 14px;
                    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                    color: #ffffff;
                    font-size: 12px;
                    font-weight: 700;
                    border-radius: 20px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.4);
                ">Aktivní</span>
                <?php
            } else {
                ?>
                <span style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 6px 14px;
                    background: #e2e8f0;
                    color: #64748b;
                    font-size: 12px;
                    font-weight: 700;
                    border-radius: 20px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                ">Neaktivní</span>
                <?php
            }
        },
    ),
);

// Data
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['search_value'] = $search ?? '';
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// Search configuration
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => 'Hledat OOPP...',
    'fields' => array('name', 'standards', 'risk_description'),
    'show_info_banner' => true,
);

// Filters configuration
$table_config['filters'] = array(
    'group_id' => array(
        'type' => 'select',
        'label' => 'Skupina',
        'options' => $oopp_groups_options,
    ),
    'branch_id' => array(
        'type' => 'select',
        'label' => 'Pobočka',
        'options' => $branches_options,
    ),
    'department_id' => array(
        'type' => 'select',
        'label' => 'Oddělení',
        'options' => $departments_options,
    ),
);

// Sidebar context
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;
$table_config['related_data'] = $related_data ?? null;

// Actions
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = 'Nový OOPP';

// TABS configuration - loaded from config.php
$table_config['tabs'] = $config['tabs'] ?? null;

// Infinite scroll
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// Pass tab data from get_list_data() result
if (!empty($table_config['tabs']['enabled'])) {
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
$entity = $config['entity'] ?? 'oopp';
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();
?>

<!-- OOPP Table Styles - Force column widths -->
<style>
/* Vynutit šířku sloupce s obrázkem */
.saw-admin-table th:first-child,
.saw-admin-table td:first-child,
.saw-table th:first-child,
.saw-table td:first-child,
table th:first-child,
table td:first-child {
    min-width: 120px !important;
    width: 120px !important;
}

/* OOPP specifické styly pro modul */
.saw-module-oopp .saw-admin-table th:first-child,
.saw-module-oopp .saw-admin-table td:first-child {
    min-width: 120px !important;
    width: 120px !important;
}
</style>