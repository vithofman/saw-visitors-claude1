<?php
/**
 * Branches Detail Sidebar Template - BENTO DESIGN
 *
 * Moderní Bento Box design systém pro zobrazení detailu pobočky.
 * Asymetrický grid s kartami různých velikostí, firemní barvy.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     20.0.0 - BENTO DESIGN SYSTEM
 * @since       4.0.0
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
    ? saw_get_translations($lang, 'admin', 'branches') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="sa-alert sa-alert--danger">' . esc_html($tr('error_not_found', 'Pobočka nebyla nalezena')) . '</div>';
    return;
}

// ============================================
// LOAD RELATED DATA
// ============================================
global $wpdb;

$departments_count = 0;
$visits_count = 0;
$visitors_count = 0;
$departments = array();

if (!empty($item['id'])) {
    $branch_id = intval($item['id']);
    
    $departments_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE branch_id = %d",
        $branch_id
    ));
    
    $visits_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE branch_id = %d",
        $branch_id
    ));
    
    $visitors_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT vis.id) 
         FROM {$wpdb->prefix}saw_visitors vis
         INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
         WHERE v.branch_id = %d",
        $branch_id
    ));
    
    $departments = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, department_number, is_active 
         FROM {$wpdb->prefix}saw_departments 
         WHERE branch_id = %d 
         ORDER BY name ASC
         LIMIT 5",
        $branch_id
    ), ARRAY_A) ?: array();
}

// ============================================
// BUILD HEADER BADGES
// ============================================
$badges = [];

if (!empty($item['is_headquarters'])) {
    $badges[] = [
        'label' => $tr('badge_headquarters', 'Sídlo firmy'),
        'variant' => 'primary',
        'icon' => '🏢',
    ];
}

if (!empty($item['is_active'])) {
    $badges[] = [
        'label' => $tr('status_active', 'Aktivní'),
        'variant' => 'success',
        'dot' => true,
    ];
} else {
    $badges[] = [
        'label' => $tr('status_inactive', 'Neaktivní'),
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
        saw_bento_start('bento-branches');
        
        // ================================
        // HEADER
        // ================================
        saw_bento_header([
            'icon' => 'building-2',
            'module' => 'branches',
            'module_label' => $tr('module_name', 'Pobočky'),
            'id' => $item['id'],
            'title' => $item['name'] ?? $tr('unnamed', 'Bez názvu'),
            'subtitle' => !empty($item['is_headquarters']) ? $tr('badge_headquarters', 'Sídlo firmy') : $tr('badge_branch', 'Pobočka'),
            'badges' => $badges,
            'nav_enabled' => true,
            'stripe' => true,
            'image_url' => $item['image_url'] ?? '',
            'close_url' => $close_url,
        ]);
        
        // ================================
        // STATISTICS (3 stat cards)
        // ================================
        saw_bento_stat([
            'icon' => 'building',
            'value' => $departments_count,
            'label' => $tr('stat_departments', 'Oddělení'),
            'variant' => 'light-blue',
            'link' => home_url('/admin/departments/?branch_id=' . intval($item['id'])),
        ]);
        
        saw_bento_stat([
            'icon' => 'clipboard-list',
            'value' => $visits_count,
            'label' => $tr('stat_visits', 'Návštěv'),
            'variant' => 'default',
            'link' => home_url('/admin/visits/?branch_id=' . intval($item['id'])),
        ]);
        
        saw_bento_stat([
            'icon' => 'users',
            'value' => $visitors_count,
            'label' => $tr('stat_visitors', 'Návštěvníků'),
            'variant' => 'default',
            'link' => home_url('/admin/visitors/?branch_id=' . intval($item['id'])),
        ]);
        
        // ================================
        // ADDRESS (if exists)
        // ================================
        $has_address = !empty($item['street']) || !empty($item['city']) || !empty($item['postal_code']);
        if ($has_address) {
            saw_bento_address([
                'icon' => 'map-pin',
                'title' => $tr('section_address', 'Adresa'),
                'subtitle' => !empty($item['is_headquarters']) ? $tr('main_headquarters', 'Hlavní sídlo') : '',
                'street' => $item['street'] ?? '',
                'city' => $item['city'] ?? '',
                'zip' => $item['postal_code'] ?? '',
                'country' => $item['country'] ?? '',
                'highlight_city' => true,
                'colspan' => 2,
                'show_map_link' => true,
            ]);
        }
        
        // ================================
        // CONTACT (if exists)
        // ================================
        $has_contact = !empty($item['phone']) || !empty($item['email']);
        if ($has_contact) {
            saw_bento_contact([
                'icon' => 'phone',
                'title' => $tr('section_contact', 'Kontakt'),
                'phone' => $item['phone'] ?? '',
                'email' => $item['email'] ?? '',
                'variant' => 'dark',
                'colspan' => 1,
            ]);
        }
        
        // ================================
        // INFO
        // ================================
        $info_fields = [
            [
                'label' => 'ID',
                'value' => $item['id'],
                'type' => 'code',
            ],
        ];
        
        if (!empty($item['code'])) {
            $info_fields[] = [
                'label' => $tr('field_code', 'Kód pobočky'),
                'value' => $item['code'],
                'type' => 'code',
                'copyable' => true,
            ];
        }
        
        $info_fields[] = [
            'label' => $tr('field_type', 'Typ'),
            'value' => !empty($item['is_headquarters']) ? $tr('badge_headquarters', 'Sídlo firmy') : $tr('badge_branch', 'Pobočka'),
            'type' => 'badge',
            'variant' => !empty($item['is_headquarters']) ? 'info' : 'default',
        ];
        
        $info_fields[] = [
            'label' => $tr('field_status', 'Status'),
            'value' => !empty($item['is_active']) ? $tr('status_active', 'Aktivní') : $tr('status_inactive', 'Neaktivní'),
            'type' => 'status',
            'status' => !empty($item['is_active']) ? 'success' : 'default',
            'dot' => true,
        ];
        
        saw_bento_info([
            'icon' => 'info',
            'title' => $tr('section_info', 'Informace'),
            'fields' => $info_fields,
            'colspan' => 1,
        ]);
        
        // ================================
        // DESCRIPTION (if exists)
        // ================================
        if (!empty($item['description'])) {
            saw_bento_text([
                'icon' => 'file-text',
                'title' => $tr('section_description', 'Popis'),
                'content' => $item['description'],
                'colspan' => 2,
            ]);
        }
        
        // ================================
        // NOTES (if exists)
        // ================================
        if (!empty($item['notes'])) {
            saw_bento_text([
                'icon' => 'file-text',
                'title' => $tr('section_notes', 'Interní poznámky'),
                'content' => $item['notes'],
                'variant' => 'muted',
                'colspan' => 2,
            ]);
        }
        
        // ================================
        // DEPARTMENTS LIST (if exists)
        // ================================
        if (!empty($departments)) {
            $dept_items = array_map(function($dept) {
                return [
                    'icon' => 'building',
                    'name' => $dept['name'],
                    'meta' => !empty($dept['department_number']) ? '#' . $dept['department_number'] : '',
                    'url' => home_url('/admin/departments/' . intval($dept['id']) . '/'),
                    'active' => !empty($dept['is_active']),
                ];
            }, $departments);
            
            saw_bento_list([
                'icon' => 'building',
                'title' => $tr('section_departments', 'Oddělení'),
                'badge_count' => $departments_count,
                'items' => $dept_items,
                'show_all_url' => home_url('/admin/departments/?branch_id=' . intval($item['id'])),
                'show_all_label' => $tr('show_all', 'Zobrazit všechna'),
                'max_items' => 5,
                'colspan' => 2,
            ]);
        }
        
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
