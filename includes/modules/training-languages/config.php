<?php
/**
 * Training Languages Module Configuration
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    3.2.0 - FIXED: Route path
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'slug' => 'training-languages',
    'entity' => 'training_languages',
    'table' => 'saw_training_languages',
    
    'singular' => 'Jazyk školení',
    'plural' => 'Jazyky školení',
    'icon' => '🌐',
    
    // ✅ OPRAVENO: Odstraněno "settings/", aby odkazy vedly správně
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
    
    // Ponecháme cache zapnutou, model ji umí mazat
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
];