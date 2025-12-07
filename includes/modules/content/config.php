<?php
/**
 * Content Module Configuration
 *
 * @package SAW_Visitors
 * @version 2.0.0 - ADDED: Translation support
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
    ? saw_get_translations($lang, 'admin', 'content') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// CONFIGURATION
// ============================================
return array(
    'entity' => 'content',
    'singular' => $tr('singular', 'Obsah'),
    'plural' => $tr('plural', 'Obsah'),
    'route' => 'admin/content',
    'icon' => '📝',
    
    'capabilities' => array(
        'view' => 'saw_admin',
        'edit' => 'saw_admin',
    ),
);