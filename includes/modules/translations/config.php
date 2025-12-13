<?php
/**
 * Translations Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Translations
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'translations') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// MODULE CONFIGURATION
// ============================================
return array(
    'entity' => 'translations',
    'table' => 'saw_ui_translations',
    'singular' => $tr('config_singular', 'Překlad'),
    'plural' => $tr('config_plural', 'Překlady'),
    'route' => 'translations',
    'icon' => '🌐',
    'has_customer_isolation' => false,
    'has_branch_isolation' => false,
    'edit_url' => 'translations/{id}/edit',
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    'fields' => array(
        'translation_key' => array(
            'type' => 'text',
            'label' => $tr('form_translation_key', 'Klíč překladu'),
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'maxlength' => 100,
        ),
        'language_code' => array(
            'type' => 'select',
            'label' => $tr('form_language_code', 'Kód jazyka'),
            'required' => true,
            'options' => array(
                'cs' => 'Čeština',
                'en' => 'English',
                'de' => 'Deutsch',
                'sk' => 'Slovenčina',
            ),
            'sanitize' => 'sanitize_text_field',
        ),
        'context' => array(
            'type' => 'select',
            'label' => $tr('form_context', 'Kontext'),
            'required' => true,
            'options' => array(
                'terminal' => 'Terminal',
                'invitation' => 'Pozvánka',
                'admin' => 'Admin',
                'common' => 'Společné',
            ),
            'sanitize' => 'sanitize_text_field',
        ),
        'section' => array(
            'type' => 'text',
            'label' => $tr('form_section', 'Sekce'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'maxlength' => 50,
            'placeholder' => $tr('form_section_placeholder', 'např. video, risks'),
        ),
        'translation_text' => array(
            'type' => 'textarea',
            'label' => $tr('form_translation_text', 'Text překladu'),
            'required' => true,
            'sanitize' => 'wp_kses_post',
        ),
        'description' => array(
            'type' => 'text',
            'label' => $tr('form_description', 'Popis'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'maxlength' => 255,
        ),
        'placeholders' => array(
            'type' => 'text',
            'label' => $tr('form_placeholders', 'Placeholdery'),
            'required' => false,
            'sanitize' => 'sanitize_text_field',
            'maxlength' => 255,
            'placeholder' => $tr('form_placeholders_placeholder', 'např. {name}, {date}'),
        ),
    ),
    
    // ============================================
    // TABS CONFIGURATION
    // ============================================
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'context',
        'default_tab' => 'all',
            'tabs' => array(
                'all' => array(
                    'label' => $tr('tab_all', 'Všechny'),
                    'icon' => '🌐',
                    'filter_value' => null,
                    'count_query' => true,
                ),
                'terminal' => array(
                    'label' => $tr('tab_terminal', 'Terminal'),
                    'icon' => '🖥️',
                    'filter_value' => 'terminal',
                    'count_query' => true,
                ),
                'invitation' => array(
                    'label' => $tr('tab_invitation', 'Pozvánka'),
                    'icon' => '📧',
                    'filter_value' => 'invitation',
                    'count_query' => true,
                ),
                'admin' => array(
                    'label' => $tr('tab_admin', 'Admin'),
                    'icon' => '⚙️',
                    'filter_value' => 'admin',
                    'count_query' => true,
                ),
                'common' => array(
                    'label' => $tr('tab_common', 'Společné'),
                    'icon' => '🌐',
                    'filter_value' => 'common',
                    'count_query' => true,
                ),
                'email' => array(
                    'label' => $tr('tab_email', 'Email'),
                    'icon' => '📧',
                    'filter_value' => 'email',
                    'count_query' => true,
                ),
            ),
    ),
    
    // ============================================
    // INFINITE SCROLL CONFIGURATION
    // ============================================
    'infinite_scroll' => array(
        'enabled' => true,
        'per_page' => 50,
        'initial_load' => 100,
        'threshold' => 0.6, // 60% scroll pro spuštění načítání (hodnota 0-1, ne pixely)
    ),
    
    // ============================================
    // LIST CONFIGURATION
    // ============================================
    'list_config' => array(
        'per_page' => 50,
        'default_orderby' => 'translation_key',
        'default_order' => 'ASC',
        'searchable_fields' => array('translation_key', 'translation_text', 'description'),
        'filters' => array(
            'language_code' => true,
            'context' => true,
            'section' => true,
        ),
    ),
    
    // ============================================
    // CACHE CONFIGURATION
    // ============================================
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
    ),
    
    // ============================================
    // ACTIONS
    // ============================================
    'actions' => array('view', 'edit', 'delete'),
);

