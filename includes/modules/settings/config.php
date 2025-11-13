<?php
/**
 * Settings Module Configuration
 *
 * Module manifest for auto-discovery by SAW_Module_Loader.
 * Defines routes, capabilities, menu items, and cache settings.
 *
 * @package SAW_Visitors
 * @subpackage Modules\Settings
 * @since 4.9.0
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'name' => 'Nastaveni',
    'description' => 'Sprava nastaveni aplikace a osobnich preferenci',
    'version' => '1.0.0',
    'icon' => 'cog',
    'entity' => 'settings',
    'singular' => 'Nastaveni',
    'plural' => 'Nastaveni',
    'route' => '/admin/settings',
    'controller' => 'SAW_Module_Settings_Controller',
    'model' => 'SAW_Settings_Model',
    'enabled' => true,
    
    'capabilities' => [
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
    ],
];
