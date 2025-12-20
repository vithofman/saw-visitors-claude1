<?php
/**
 * Translations Detail Sidebar Template - BENTO DESIGN
 *
 * Moderní Bento Box design systém pro zobrazení detailu překladu.
 *
 * @package    SAW_Visitors
 * @subpackage Modules/Translations
 * @version    4.0.0 - BENTO DESIGN SYSTEM
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
    ? saw_get_translations($lang, 'admin', 'translations') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="sa-alert sa-alert--danger">' . esc_html($tr('error_not_found', 'Překlad nebyl nalezen')) . '</div>';
    return;
}

// ============================================
// PREPARE DATA
// ============================================
$context_labels = array(
    'terminal' => array('label' => 'Terminal', 'icon' => 'monitor', 'variant' => 'primary'),
    'invitation' => array('label' => 'Pozvánka', 'icon' => 'mail', 'variant' => 'info'),
    'admin' => array('label' => 'Admin', 'icon' => 'settings', 'variant' => 'warning'),
    'common' => array('label' => 'Společné', 'icon' => 'globe', 'variant' => 'secondary'),
    'email' => array('label' => 'Email', 'icon' => 'mail', 'variant' => 'success'),
);

$language_flags = array(
    'cs' => '🇨🇿',
    'en' => '🇬🇧',
    'de' => '🇩🇪',
    'sk' => '🇸🇰',
);

$current_context = $context_labels[$item['context']] ?? array('label' => $item['context'], 'icon' => 'tag', 'variant' => 'secondary');
$language_flag = $language_flags[$item['language_code']] ?? '🌐';
$language_code_upper = strtoupper($item['language_code'] ?? '');

// ============================================
// BUILD HEADER BADGES
// ============================================
$badges = [];

// Language badge
$badges[] = [
    'label' => $language_flag . ' ' . $language_code_upper,
    'variant' => 'info',
];

// Context badge
$badges[] = [
    'label' => $current_context['label'],
    'variant' => $current_context['variant'],
];

// Section badge (if exists)
if (!empty($item['section'])) {
    $badges[] = [
        'label' => $item['section'],
        'variant' => 'secondary',
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
        saw_bento_start('bento-translations');
        
        // ================================
        // HEADER
        // ================================
        saw_bento_header([
            'icon' => 'globe',
            'module' => 'translations',
            'module_label' => $tr('module_name', 'Překlady'),
            'id' => $item['id'],
            'title' => $item['translation_key'] ?? $tr('unnamed', 'Bez názvu'),
            'subtitle' => $language_flag . ' ' . ($current_context['label'] ?? ''),
            'badges' => $badges,
            'nav_enabled' => true,
            'stripe' => true,
            'close_url' => $close_url ?? '',
        ]);
        
        // ================================
        // TRANSLATION KEY CARD
        // ================================
        saw_bento_info([
            'icon' => 'key',
            'title' => $tr('field_translation_key', 'Klíč překladu'),
            'fields' => [
                [
                    'label' => $tr('field_key', 'Klíč'),
                    'value' => $item['translation_key'] ?? '',
                    'type' => 'code',
                    'copyable' => true,
                ],
            ],
            'colspan' => 2,
            'variant' => 'primary',
        ]);
        
        // ================================
        // TRANSLATION TEXT CARD
        // ================================
        saw_bento_text([
            'icon' => 'file-text',
            'title' => $tr('section_translation', 'Text překladu'),
            'content' => $item['translation_text'] ?? '',
            'colspan' => 2,
        ]);
        
        // ================================
        // BASIC INFORMATION
        // ================================
        $info_fields = [
            [
                'label' => $tr('field_language_code', 'Jazyk'),
                'value' => $language_flag . ' ' . $language_code_upper,
                'type' => 'text',
            ],
            [
                'label' => $tr('field_context', 'Kontext'),
                'value' => $current_context['label'],
                'type' => 'badge',
                'variant' => $current_context['variant'],
            ],
        ];
        
        if (!empty($item['section'])) {
            $info_fields[] = [
                'label' => $tr('field_section', 'Sekce'),
                'value' => $item['section'],
                'type' => 'badge',
                'variant' => 'secondary',
            ];
        }
        
        saw_bento_info([
            'icon' => 'info',
            'title' => $tr('section_basic', 'Základní informace'),
            'fields' => $info_fields,
            'colspan' => 1,
        ]);
        
        // ================================
        // ADDITIONAL INFORMATION (if exists)
        // ================================
        if (!empty($item['description']) || !empty($item['placeholders'])) {
            $additional_fields = [];
            
            if (!empty($item['description'])) {
                $additional_fields[] = [
                    'label' => $tr('field_description', 'Popis'),
                    'value' => $item['description'],
                    'type' => 'text',
                ];
            }
            
            if (!empty($item['placeholders'])) {
                $additional_fields[] = [
                    'label' => $tr('field_placeholders', 'Placeholdery'),
                    'value' => $item['placeholders'],
                    'type' => 'code',
                ];
            }
            
            saw_bento_info([
                'icon' => 'tag',
                'title' => $tr('section_additional', 'Další informace'),
                'fields' => $additional_fields,
                'colspan' => 1,
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
