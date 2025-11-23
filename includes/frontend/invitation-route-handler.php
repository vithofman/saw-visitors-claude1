<?php
/**
 * Invitation Route Handler
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Load invitation router class
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/invitation-router.php';

// Inicializace - přesunuto do init hooku, aby se spustilo až po načtení WordPress
add_action('init', function() {
    if (class_exists('SAW_Invitation_Router') && !isset($GLOBALS['saw_invitation_router'])) {
        $GLOBALS['saw_invitation_router'] = new SAW_Invitation_Router();
    }
}, 1);

