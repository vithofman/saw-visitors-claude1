<?php
/**
 * Visits List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     4.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load translations
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visits') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// Load branches for filter
global $wpdb;
$customer_id = SAW_Context::get_customer_id();
$branches = array();

if ($customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
    
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// Base configuration
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'visits'));

$table_config['title'] = $tr('title', 'Návštěvy');
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// Search configuration
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat návštěvy...'),
    'fields' => array('id', 'company_name', 'first_visitor_name', 'last_name'),
    'show_info_banner' => true,
);

// Filters configuration
$table_config['filters'] = array(
    'status' => array(
        'type' => 'select',
        'label' => $tr('filter_status', 'Stav'),
        'options' => array(
            '' => $tr('filter_all_statuses', 'Všechny stavy'),
            'draft' => $tr('status_draft', 'Koncept'),
            'pending' => $tr('status_pending', 'Čekající'),
            'confirmed' => $tr('status_confirmed', 'Potvrzená'),
            'in_progress' => $tr('status_in_progress', 'Probíhající'),
            'completed' => $tr('status_completed', 'Dokončená'),
            'cancelled' => $tr('status_cancelled', 'Zrušená'),
        ),
    ),
    'visit_type' => array(
        'type' => 'select',
        'label' => $tr('filter_visit_type', 'Typ návštěvy'),
        'options' => array(
            '' => $tr('filter_all_types', 'Všechny typy'),
            'planned' => $tr('visit_type_planned', 'Plánovaná'),
            'walk_in' => $tr('visit_type_walk_in', 'Walk-in'),
        ),
    ),
);

// Sidebar context
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;
$table_config['related_data'] = $related_data ?? null;
$table_config['branches'] = $branches;

// Translated labels for callbacks
$visitor_company_label = $tr('visitor_company', 'Firma');
$visitor_physical_label = $tr('visitor_physical', 'Fyzická osoba');
$visitor_physical_short = $tr('visitor_physical_short', 'Fyzická');
$risks_missing_label = $tr('risks_missing', 'Chybí');
$risks_ok_label = $tr('risks_ok', 'OK');
$risks_missing_title = $tr('risks_missing_title', 'Chybí informace o rizicích');
$risks_ok_title = $tr('risks_ok_title', 'Informace o rizicích jsou dostupné');

// Column definitions
$table_config['columns'] = array(
    'company_person' => array(
        'label' => $tr('col_visitor', 'Návštěvník'),
        'type' => 'custom',
        'sortable' => false,
        'class' => 'saw-table-cell-bold',
        'callback' => function($value, $item) use ($visitor_company_label, $visitor_physical_label, $visitor_physical_short) {
            if (!empty($item['company_id'])) {
                $company_name = esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']);
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                echo '<strong>' . $company_name . '</strong>';
                echo '<span class="saw-badge saw-badge-info" style="font-size: 11px;">🏢 ' . esc_html($visitor_company_label) . '</span>';
                echo '</div>';
            } else {
                $person_name = !empty($item['first_visitor_name']) 
                    ? esc_html($item['first_visitor_name']) 
                    : esc_html($visitor_physical_label);
                    
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                echo '<strong style="color: #6366f1;">' . $person_name . '</strong>';
                echo '<span class="saw-badge" style="background: #6366f1; color: white; font-size: 11px;">👤 ' . esc_html($visitor_physical_short) . '</span>';
                echo '</div>';
            }
        },
    ),
    
    'branch_name' => array(
        'label' => $tr('col_branch', 'Pobočka'),
        'type' => 'text',
        'sortable' => false,
        'width' => '150px',
    ),
    
    'visit_type' => array(
        'label' => $tr('col_type', 'Typ'),
        'type' => 'badge',
        'sortable' => false,
        'width' => '110px',
        'map' => array(
            'planned' => 'info',
            'walk_in' => 'warning',
        ),
        'labels' => array(
            'planned' => $tr('visit_type_planned', 'Plánovaná'),
            'walk_in' => $tr('visit_type_walk_in', 'Walk-in'),
        ),
    ),
    
    'visitor_count' => array(
        'label' => $tr('col_count', 'Počet'),
        'type' => 'custom',
        'width' => '90px',
        'align' => 'center',
        'sortable' => false,
        'callback' => function($value, $item) {
            $count = intval($item['visitor_count'] ?? 0);
            if ($count === 0) {
                echo '<span style="color: #999;">—</span>';
            } else {
                echo '<strong style="color: #0066cc;">👥 ' . $count . '</strong>';
            }
        },
    ),
    
    'has_risks' => array(
        'label' => $tr('col_risks', 'Rizika'),
        'type' => 'custom',
        'sortable' => false,
        'width' => '100px',
        'align' => 'center',
        'callback' => function($value, $item) use ($risks_missing_label, $risks_ok_label, $risks_missing_title, $risks_ok_title) {
            $has_risks = $item['has_risks'] ?? 'no';
            $status = $item['status'] ?? '';
            
            if ($status === 'confirmed' && $has_risks === 'no') {
                echo '<span class="saw-badge saw-badge-danger" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px;" title="' . esc_attr($risks_missing_title) . '">⚠️ ' . esc_html($risks_missing_label) . '</span>';
            } elseif ($has_risks === 'yes') {
                echo '<span class="saw-badge saw-badge-success" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px;" title="' . esc_attr($risks_ok_title) . '">✅ ' . esc_html($risks_ok_label) . '</span>';
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        },
    ),
    
    'status' => array(
        'label' => $tr('col_status', 'Stav'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '130px',
        'map' => array(
            'draft' => 'secondary',
            'pending' => 'warning',
            'confirmed' => 'info',
            'in_progress' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
        ),
        'labels' => array(
            'draft' => $tr('status_draft', 'Koncept'),
            'pending' => $tr('status_pending', 'Čekající'),
            'confirmed' => $tr('status_confirmed', 'Potvrzená'),
            'in_progress' => $tr('status_in_progress', 'Probíhající'),
            'completed' => $tr('status_completed', 'Dokončená'),
            'cancelled' => $tr('status_cancelled', 'Zrušená'),
        ),
    ),
    
    'created_at' => array(
        'label' => $tr('col_created_at', 'Vytvořeno'),
        'type' => 'date',
        'sortable' => true,
        'width' => '110px',
        'format' => 'd.m.Y',
    ),
);

// Data & pagination
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// Actions & messages
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = $tr('add_new', 'Nová návštěva');
$table_config['empty_message'] = $tr('empty_message', 'Žádné návštěvy nenalezeny');

// Tabs configuration
$table_config['tabs'] = $config['tabs'] ?? null;

if (!empty($table_config['tabs']['enabled']) && !empty($table_config['tabs']['tabs'])) {
    $tab_translations = array(
        'all' => $tr('tab_all', 'Všechny'),
        'draft' => $tr('tab_draft', 'Koncept'),
        'pending' => $tr('tab_pending', 'Čekající'),
        'confirmed' => $tr('tab_confirmed', 'Potvrzená'),
        'in_progress' => $tr('tab_in_progress', 'Probíhající'),
        'completed' => $tr('tab_completed', 'Dokončená'),
        'cancelled' => $tr('tab_cancelled', 'Zrušená'),
    );
    
    foreach ($table_config['tabs']['tabs'] as $tab_key => &$tab_config) {
        if (isset($tab_translations[$tab_key])) {
            $tab_config['label'] = $tab_translations[$tab_key];
        }
    }
    unset($tab_config);
}

if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// Infinite scroll configuration
$table_config['infinite_scroll'] = array(
    'enabled' => true,
    'initial_load' => 100,
    'per_page' => 50,
    'threshold' => 0.6,
);

// Render
$table = new SAW_Component_Admin_Table('visits', $table_config);
$table->render();