<?php
/**
 * Training Languages List Template
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    4.1.0 - FIXED: Match departments pattern
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'training_languages') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// ENSURE COMPONENTS ARE LOADED
// ============================================
if (!class_exists('SAW_Component_Admin_Table')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
}

// ============================================
// PREPARE CONFIG FOR ADMIN TABLE
// ============================================
$entity = $config['entity'] ?? 'training_languages';
$table_config = $config;
$base_url = home_url('/admin/' . ($config['route'] ?? 'training-languages'));

$table_config['title'] = $tr('title', $config['plural']);
$table_config['create_url'] = $base_url . '/create';
$table_config['detail_url'] = $base_url . '/{id}/';
$table_config['edit_url'] = $base_url . '/{id}/edit';

// ============================================
// COLUMN DEFINITIONS
// ============================================
$table_config['columns'] = array(
    'flag_emoji' => array(
        'label' => $tr('col_flag', 'Vlajka'),
        'type' => 'custom',
        'width' => '60px',
        'align' => 'center',
        'callback' => function($value) {
            return '<span style="font-size: 24px;">' . esc_html($value) . '</span>';
        }
    ),
    'language_name' => array(
        'label' => $tr('col_name', 'Název jazyka'),
        'type' => 'custom',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
        'callback' => function($value, $item) use ($tr) {
            $html = esc_html($value);
            if (($item['language_code'] ?? '') === 'cs') {
                $html .= ' <span class="saw-badge saw-badge-info">' . esc_html($tr('badge_required', 'Povinný')) . '</span>';
            }
            return $html;
        }
    ),
    'language_code' => array(
        'label' => $tr('col_code', 'Kód'),
        'type' => 'custom',
        'width' => '80px',
        'align' => 'center',
        'sortable' => true,
        'callback' => function($value) {
            return '<code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600; color:#475569;">' . esc_html(strtoupper($value)) . '</code>';
        }
    ),
    'branches_count' => array(
        'label' => $tr('col_branches', 'Aktivní pobočky'),
        'type' => 'custom',
        'align' => 'center',
        'width' => '150px',
        'callback' => function($value) {
            $count = intval($value);
            if ($count > 0) {
                return '<span class="saw-badge saw-badge-success">' . $count . '</span>';
            } else {
                return '<span class="saw-text-muted" style="color:#cbd5e1;">—</span>';
            }
        }
    ),
    'created_at' => array(
        'label' => $tr('col_created', 'Vytvořeno'),
        'type' => 'date',
        'width' => '120px',
        'sortable' => true,
        'format' => 'd.m.Y'
    ),
);

// ============================================
// DATA
// ============================================
$table_config['rows'] = $items ?? array();
$table_config['total_items'] = $total ?? 0;
$table_config['current_page'] = $page ?? 1;
$table_config['total_pages'] = $total_pages ?? 1;
$table_config['search_value'] = $search ?? '';
$table_config['orderby'] = $orderby ?? 'language_name';
$table_config['order'] = $order ?? 'ASC';

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
// SIDEBAR CONTEXT
// ============================================
$table_config['sidebar_mode'] = $sidebar_mode ?? null;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab ?? 'overview';
$table_config['module_config'] = $config;

// ============================================
// ACTIONS
// ============================================
$table_config['actions'] = array('view', 'edit', 'delete');
$table_config['add_new'] = $tr('btn_add_new', 'Nový jazyk');
$table_config['empty_message'] = $tr('empty_message', 'Žádné jazyky nenalezeny');

// ============================================
// TABS - Pass from config
// ============================================
$table_config['tabs'] = $config['tabs'] ?? null;

// ============================================
// CURRENT TAB & TAB COUNTS
// ============================================
if (!empty($table_config['tabs']['enabled'])) {
    $table_config['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
        ? (string)$current_tab 
        : ($table_config['tabs']['default_tab'] ?? 'all');
    $table_config['tab_counts'] = (isset($tab_counts) && is_array($tab_counts)) ? $tab_counts : array();
}

// ============================================
// RENDER
// ============================================
$table = new SAW_Component_Admin_Table($entity, $table_config);
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