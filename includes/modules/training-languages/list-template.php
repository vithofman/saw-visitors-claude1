<?php
/**
 * Training Languages List Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/TrainingLanguages
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
    ? saw_get_translations($lang, 'admin', 'training_languages') 
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
$orderby = $orderby ?? 'language_name';
$order = $order ?? 'ASC';

// ============================================
// TABLE CONFIGURATION
// ============================================
$table_config = array(
    'title' => $tr('title', $config['plural'] ?? 'Jazyky školení'),
    'create_url' => home_url('/admin/training-languages/create'),
    'edit_url' => home_url('/admin/training-languages/{id}/edit'),
    'detail_url' => home_url('/admin/training-languages/{id}/'),
    
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
    'empty_message' => $tr('empty_message', 'Žádné jazyky nenalezeny'),
    'add_new' => $tr('btn_add_new', 'Nový jazyk'),
);

// ============================================
// SEARCH CONFIGURATION
// ============================================
$table_config['search'] = array(
    'enabled' => true,
    'placeholder' => $tr('search_placeholder', 'Hledat jazyk...'),
    'fields' => array('language_name', 'language_code'),
    'show_info_banner' => true,
);

// ============================================
// FILTERS CONFIGURATION
// ============================================
$table_config['filters'] = array(
    'has_branches' => array(
        'label' => $tr('filter_branches', 'Aktivní pobočky'),
        'type' => 'select',
        'options' => array(
            '' => $tr('filter_all', 'Všechny'),
            'yes' => $tr('filter_with_branches', 'S aktivními pobočkami'),
            'no' => $tr('filter_without_branches', 'Bez aktivních poboček'),
        ),
    ),
);

// ============================================
// COLUMNS CONFIGURATION - ŠÍŘKY V PROCENTECH
// ============================================
$table_config['columns'] = array(
    'flag_emoji' => array(
        'label' => $tr('col_flag', 'Vlajka'),
        'type' => 'custom',
        'width' => '8%',   // Vlajka je malá
        'align' => 'center',
        'callback' => function($value) {
            return '<span class="sa-table-flag-emoji">' . esc_html($value) . '</span>';
        }
    ),
    'language_name' => array(
        'label' => $tr('col_name', 'Název jazyka'),
        'type' => 'custom',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
        'width' => '42%',  // Hlavní identifikátor
        'callback' => function($value, $item) use ($tr) {
            $html = esc_html($value);
            if (($item['language_code'] ?? '') === 'cs') {
                $html .= ' <span class="sa-badge sa-badge--info">' . esc_html($tr('badge_required', 'Povinný')) . '</span>';
            }
            return $html;
        }
    ),
    'language_code' => array(
        'label' => $tr('col_code', 'Kód'),
        'type' => 'custom',
        'width' => '12%',  // Kód střední
        'align' => 'center',
        'sortable' => true,
        'callback' => function($value) {
            return '<span class="sa-code-badge">' . esc_html(strtoupper($value)) . '</span>';
        }
    ),
    'branches_count' => array(
        'label' => $tr('col_branches', 'Aktivní pobočky'),
        'type' => 'custom',
        'align' => 'center',
        'width' => '20%',  // Počet poboček
        'callback' => function($value) {
            $count = intval($value);
            if ($count > 0) {
                return '<span class="sa-badge sa-badge--success">' . $count . '</span>';
            } else {
                return '<span class="sa-text-muted">—</span>';
            }
        }
    ),
    'created_at' => array(
        'label' => $tr('col_created', 'Vytvořeno'),
        'type' => 'date',
        'width' => '18%',  // Date sloupec
        'sortable' => true,
        'format' => 'd.m.Y'
    ),
);
// Součet: 8 + 42 + 12 + 20 + 18 = 100%

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
$table = new SAW_Component_Admin_Table('training-languages', $table_config);
$table->render();
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    jQuery(document).on('click', '.saw-action-delete', function(e) {
        const row = jQuery(this).closest('tr');
        const codeCell = row.find('td:nth-child(3)').text().trim().toLowerCase();
        
        if (codeCell === 'cs') {
            e.preventDefault();
            e.stopImmediatePropagation();
            alert('<?php echo esc_js($tr('error_cannot_delete_czech', 'Češtinu nelze smazat, je to výchozí jazyk systému.')); ?>');
            return false;
        }
    });
});
</script>