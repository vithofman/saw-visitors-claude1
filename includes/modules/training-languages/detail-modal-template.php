<?php
/**
 * Training Languages Detail Sidebar Template - BENTO DESIGN
 *
 * Moderní Bento Box design systém pro zobrazení detailu jazyka školení.
 * Asymetrický grid s kartami různých velikostí.
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    6.0.0 - BENTO DESIGN SYSTEM
 * @since      4.0.0
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
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="sa-alert sa-alert--danger">' . esc_html($tr('error_not_found', 'Jazyk nebyl nalezen')) . '</div>';
    return;
}

// ============================================
// PREPARE DATA
// ============================================
$active_branches = $item['active_branches'] ?? array();
$is_protected = ($item['language_code'] === 'cs');
$branches_count = count($active_branches);

// Count default branches
$default_count = 0;
foreach ($active_branches as $b) {
    if (!empty($b['is_default'])) $default_count++;
}

// ============================================
// BUILD HEADER BADGES
// ============================================
$badges = [];

if ($is_protected) {
    $badges[] = [
        'label' => $tr('badge_system_language', 'Systémový jazyk'),
        'variant' => 'info',
        'icon' => 'shield',
    ];
}

if ($branches_count > 0) {
    $badges[] = [
        'label' => $branches_count . ' ' . $tr('badge_branches_count', 'poboček'),
        'variant' => 'success',
    ];
} else {
    $badges[] = [
        'label' => $tr('badge_no_branches', 'Bez poboček'),
        'variant' => 'warning',
    ];
}

// ============================================
// RENDER BENTO GRID
// ============================================
?>

<div class="sa-detail-wrapper bento-wrapper">
    <?php 
    // Initialize Bento
    if (function_exists('saw_bento_start')) {
        
        // Start Bento Grid
        saw_bento_start('bento-training-languages');
        
        // ================================
        // HEADER
        // ================================
        saw_bento_header([
            'icon' => 'globe',
            'module' => 'training-languages',
            'module_label' => $tr('module_name', 'Jazyky školení'),
            'id' => $item['id'],
            'title' => $item['language_name'] ?? $tr('unnamed', 'Bez názvu'),
            'subtitle' => (!empty($item['flag_emoji']) ? $item['flag_emoji'] . ' ' : '') . strtoupper($item['language_code'] ?? ''),
            'badges' => $badges,
            'nav_enabled' => true,
            'stripe' => true,
            'close_url' => $close_url ?? '',
        ]);
        
        // ================================
        // STATISTICS (2 stat cards)
        // ================================
        saw_bento_stat([
            'icon' => 'building-2',
            'value' => $branches_count,
            'label' => $tr('stat_active_branches', 'Aktivních poboček'),
            'variant' => 'light-blue',
            'link' => home_url('/admin/branches/'),
        ]);
        
        saw_bento_stat([
            'icon' => 'star',
            'value' => $default_count,
            'label' => $tr('stat_default_branches', 'Výchozí na pobočkách'),
            'variant' => 'default',
        ]);
        
        // ================================
        // BRANCHES LIST (if exists)
        // ================================
        if (!empty($active_branches)) {
            $branch_items = array_map(function($branch) {
                return [
                    'icon' => !empty($branch['is_default']) ? 'star' : 'building-2',
                    'name' => $branch['name'],
                    'meta' => !empty($branch['code']) ? $branch['code'] : '',
                    'url' => home_url('/admin/branches/' . intval($branch['id']) . '/'),
                    'active' => true,
                    'badge' => !empty($branch['is_default']) ? 'Výchozí' : null,
                    'badge_variant' => 'success',
                ];
            }, $active_branches);
            
            saw_bento_list([
                'icon' => 'building-2',
                'title' => $tr('section_branches', 'Pobočky'),
                'badge_count' => $branches_count,
                'items' => $branch_items,
                'show_all_url' => home_url('/admin/branches/'),
                'show_all_label' => $tr('show_all', 'Zobrazit všechny'),
                'max_items' => 5,
                'colspan' => 2,
            ]);
        } else {
            // Empty state for branches
            saw_bento_text([
                'icon' => 'building-2',
                'title' => $tr('section_branches', 'Pobočky'),
                'content' => $tr('detail_no_branches', 'Tento jazyk není aktivován pro žádnou pobočku.'),
                'variant' => 'muted',
                'colspan' => 2,
            ]);
        }
        
        // ================================
        // INFO
        // ================================
        $info_fields = [
            [
                'label' => $tr('detail_language_code', 'Kód jazyka'),
                'value' => strtoupper($item['language_code'] ?? ''),
                'type' => 'code',
                'copyable' => true,
            ],
            [
                'label' => $tr('detail_language_name', 'Název'),
                'value' => $item['language_name'] ?? '',
                'type' => 'text',
            ],
            [
                'label' => $tr('detail_flag', 'Vlajka'),
                'value' => $item['flag_emoji'] ?? '',
                'type' => 'text',
            ],
        ];
        
        if ($is_protected) {
            $info_fields[] = [
                'label' => $tr('detail_protection', 'Ochrana'),
                'value' => $tr('badge_system_language', 'Systémový jazyk'),
                'type' => 'badge',
                'variant' => 'info',
            ];
        }
        
        saw_bento_info([
            'icon' => 'info',
            'title' => $tr('section_info', 'Informace'),
            'fields' => $info_fields,
            'colspan' => 1,
        ]);
        
        // ================================
        // METADATA (always last, full width)
        // ================================
        saw_bento_meta([
            'icon' => 'clock',
            'title' => $tr('section_metadata', 'Metadata'),
            'created_at' => $item['created_at_formatted'] ?? null,
            'updated_at' => $item['updated_at_formatted'] ?? null,
            'colspan' => 'full',
            'compact' => true,
        ]);
        
        // End Bento Grid
        saw_bento_end();
        
    } else {
        // Fallback - show error if Bento not loaded
        echo '<div class="sa-alert sa-alert--warning">';
        echo 'Bento design system není načten. ';
        echo '</div>';
    }
    ?>
</div>
