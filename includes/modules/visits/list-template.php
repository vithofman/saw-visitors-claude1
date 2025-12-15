<?php
/**
 * Visits List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
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
    ? saw_get_translations($lang, 'admin', 'visits') 
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
$orderby = $orderby ?? 'id';
$order = $order ?? 'DESC';

// ============================================
// LOAD BRANCHES FOR FILTER
// ============================================
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

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', 'Návštěvy'),
    'create_url' => home_url('/admin/visits/create'),
    'edit_url' => home_url('/admin/visits/{id}/edit'),
    'detail_url' => home_url('/admin/visits/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádné návštěvy nenalezeny'),
    'add_new' => $tr('add_new', 'Nová návštěva'),
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat návštěvy...'),
    'fields' => array('id', 'company_name', 'first_visitor_name', 'last_name'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'risks_status' => array(
        'type' => 'select',
        'label' => $tr('filter_risks_status', 'Rizika'),
        'options' => array(
            '' => $tr('filter_all_risks', 'Všechna rizika'),
            'pending' => $tr('risks_pending', 'Čeká se'),
            'completed' => $tr('risks_ok', 'OK'),
            'missing' => $tr('risks_missing', 'Chybí'),
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

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$visitor_company_label = $tr('visitor_company', 'Firma');
$visitor_physical_label = $tr('visitor_physical', 'Fyzická osoba');
$visitor_physical_short = $tr('visitor_physical_short', 'Fyzická');
$risks_missing_label = $tr('risks_missing', 'Chybí');
$risks_ok_label = $tr('risks_ok', 'OK');
$risks_missing_title = $tr('risks_missing_title', 'Chybí informace o rizicích');
$risks_ok_title = $tr('risks_ok_title', 'Informace o rizicích jsou dostupné');

$table_config['columns'] = array(
    'company_person' => array(
        'label' => $tr('col_visitor', 'Návštěvník'),
        'type' => 'custom',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
        'width' => '40%',  // Hlavní obsahový sloupec
        'callback' => function($value, $item) use ($visitor_company_label, $visitor_physical_label, $visitor_physical_short) {
            if (!empty($item['company_id'])) {
                $company_name = esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']);
                echo '<div class="saw-visitor-cell">';
                echo '<strong class="saw-visitor-name">' . $company_name . '</strong>';
                echo '<span class="saw-badge saw-badge-info saw-visitor-type-badge">';
                if (class_exists('SAW_Icons')) {
                    echo SAW_Icons::get('building-2', 'saw-icon--xs');
                }
                echo '<span class="saw-badge-text">' . esc_html($visitor_company_label) . '</span>';
                echo '</span>';
                echo '</div>';
            } else {
                $person_name = !empty($item['first_visitor_name']) 
                    ? esc_html($item['first_visitor_name']) 
                    : esc_html($visitor_physical_label);
                    
                echo '<div class="saw-visitor-cell">';
                echo '<strong class="saw-visitor-name saw-visitor-name-person">' . $person_name . '</strong>';
                echo '<span class="saw-badge saw-badge-primary saw-visitor-type-badge">';
                if (class_exists('SAW_Icons')) {
                    echo SAW_Icons::get('user', 'saw-icon--sm');
                }
                echo '<span class="saw-badge-text">' . esc_html($visitor_physical_short) . '</span>';
                echo '</span>';
                echo '</div>';
            }
        },
    ),
    
    'visit_type' => array(
        'label' => $tr('col_type', 'Typ'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '10%',  // Badge je malý
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
        'width' => '8%',   // Malý sloupec
        'align' => 'center',
        'sortable' => false,
        'callback' => function($value, $item) {
            $count = intval($item['visitor_count'] ?? 0);
            if ($count === 0) {
                echo '<span class="saw-text-muted">—</span>';
            } else {
                echo '<div class="saw-visitor-count">';
                if (class_exists('SAW_Icons')) {
                    echo SAW_Icons::get('users', 'saw-icon--sm');
                }
                echo '<strong class="saw-visitor-count-number">' . $count . '</strong>';
                echo '</div>';
            }
        },
    ),
    
    'risks_status' => array(
        'label' => $tr('col_risks', 'Rizika'),
        'type' => 'custom',
        'sortable' => true,
        'width' => '12%',
        'align' => 'center',
        'callback' => function($value, $item) use ($tr) {
            $status = $item['risks_status'] ?? 'missing';
            
            if ($status === 'missing') {
                // Červené/oranžové zvýraznění pro chybějící rizika
                echo '<span class="saw-risks-missing" title="' . esc_attr($tr('risks_missing_title', 'Chybí informace o rizicích')) . '">';
                if (class_exists('SAW_Icons')) {
                    echo SAW_Icons::get('alert-triangle', 'saw-icon--sm');
                }
                echo '<span class="saw-risks-label">' . esc_html($tr('risks_missing', 'Chybí')) . '</span>';
                echo '</span>';
            } elseif ($status === 'pending') {
                echo '<span class="saw-badge saw-badge-secondary">' . esc_html($tr('risks_pending', 'Čeká se')) . '</span>';
            } elseif ($status === 'completed') {
                // Zelené zvýraznění pro OK rizika
                echo '<span class="saw-badge saw-badge-success">';
                if (class_exists('SAW_Icons')) {
                    echo SAW_Icons::get('check-circle', 'saw-icon--sm');
                }
                echo '<span class="saw-badge-text">' . esc_html($tr('risks_ok', 'OK')) . '</span>';
                echo '</span>';
            } else {
                echo '<span class="saw-text-muted">—</span>';
            }
        },
    ),
    
    'status' => array(
        'label' => $tr('col_status', 'Stav'),
        'type' => 'badge',
        'sortable' => true,
        'width' => '15%',  // Badge větší
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
    
    'planned_date_from' => array(
        'label' => $tr('col_planned_date_from', 'Datum návštěvy (od)'),
        'type' => 'date',
        'sortable' => true,
        'width' => '15%',  // Date sloupec
        'format' => 'd.m.Y',
    ),
);
// Součet: 40 + 10 + 8 + 12 + 15 + 15 = 100%

// ============================================
// TABS CONFIGURATION
// ============================================
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
    
    foreach ($table_config['tabs']['tabs'] as $tab_key => &$tab_config_item) {
        if (isset($tab_translations[$tab_key])) {
            $tab_config_item['label'] = $tab_translations[$tab_key];
        }
    }
    unset($tab_config_item);
}

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
    'initial_load' => 50,
    'per_page' => 50,
    'threshold' => 0.6,
);

// ============================================
// RENDER TABLE
// ============================================
$table = new SAW_Component_Admin_Table('visits', $table_config);
$table->render();