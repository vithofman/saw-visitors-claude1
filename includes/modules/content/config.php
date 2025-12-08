<?php
/**
 * Content Module Configuration
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'content',
    'singular' => 'Obsah',
    'plural' => 'Obsah',
    'route' => 'admin/content',
    'icon' => '📝',
    
    'capabilities' => array(
        'view' => 'saw_admin',
        'edit' => 'saw_admin',
    ),
);
