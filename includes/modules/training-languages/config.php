<?php
/**
 * Training Languages Module Configuration
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    4.0.0 - ADDED: Full translation support
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
// CONFIGURATION
// ============================================
return [
    'slug' => 'training-languages',
    'entity' => 'training_languages',
    'table' => 'saw_training_languages',
    
    'singular' => $tr('singular', 'Jazyk školení'),
    'plural' => $tr('plural', 'Jazyky školení'),
    'icon' => '🌐',
    
    'route' => 'training-languages',
    
    'has_customer_isolation' => true,
    'has_branch_isolation' => false,
    
    'capabilities' => [
        'list' => 'read',
        'view' => 'read',
        'create' => 'read',
        'edit' => 'read',
        'delete' => 'read',
    ],
    
    'list_config' => [
        'per_page' => 20,
        'searchable' => ['language_name', 'language_code'],
    ],
    
    // ============================================
    // TABS CONFIGURATION
    // ============================================
    'tabs' => [
        'enabled' => true,
        'tab_param' => 'has_branches',  // Virtuální parametr - zpracovává se v modelu
        'default_tab' => 'all',
        'tabs' => [
            'all' => [
                'label' => 'Všechny',
                'filter_value' => null,
                'icon' => '📋',
                'count_query' => true,
            ],
            'with_branches' => [
                'label' => 'S pobočkami',
                'filter_value' => 'yes',  // branches_count > 0
                'icon' => '✓',
                'count_query' => true,
            ],
            'without_branches' => [
                'label' => 'Bez poboček',
                'filter_value' => 'no',  // branches_count = 0
                'icon' => '✕',
                'count_query' => true,
            ],
        ],
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
];