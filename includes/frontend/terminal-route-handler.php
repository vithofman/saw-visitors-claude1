<?php
/**
 * Terminal Route Handler
 * 
 * Integrates terminal controller with SAW Router
 * This file is called from SAW_Router::handle_terminal_route()
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load terminal controller
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/terminal.php';

// Initialize and render terminal
$terminal = new SAW_Terminal_Controller();
$terminal->render();
