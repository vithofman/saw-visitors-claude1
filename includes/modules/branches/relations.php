<?php
/**
 * Branches Module - Relations Configuration
 *
 * Defines relationships between branches and other entities
 * (Currently empty, as branches are leaf nodes)
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @since       9.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Branches have no child relations, return empty array
// This ensures compatibility with Base_Controller's load_related_data()
return array();