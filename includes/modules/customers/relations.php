<?php
/**
 * Customers Module - Relations Configuration
 *
 * Defines relationships between customers and other entities
 * for the Related Data sidebar system.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers
 * @since       8.0.0
 * @version     3.0.0 - ADDED: Translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATION SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'customers') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// RELATIONS CONFIGURATION
// ============================================
return array(
    'branches' => array(
        'label' => $tr('relation_branches', 'Pobočky'),
        'icon' => '🏢',
        'entity' => 'branches',
        'foreign_key' => 'customer_id',
        'display_field' => 'name',
        'route' => 'admin/branches/{id}/',
        'order_by' => 'is_headquarters DESC, name ASC',
    ),
);