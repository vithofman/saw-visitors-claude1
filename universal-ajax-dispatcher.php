<?php
/**
 * Universal AJAX Dispatcher
 * 
 * Dynamically routes AJAX requests like saw_load_sidebar_{module} and saw_delete_{module}
 * to the appropriate module controller without needing explicit registration in each module.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Universal dispatcher for saw_load_sidebar_{module} AJAX actions
 * 
 * Extracts module name from action, loads controller, calls ajax_load_sidebar()
 */
function saw_universal_load_sidebar_dispatcher() {
    $action = $_POST['action'] ?? '';
    
    // Extract module name from action (e.g., "saw_load_sidebar_visitors" -> "visitors")
    if (!preg_match('/^saw_load_sidebar_(.+)$/', $action, $matches)) {
        wp_send_json_error(['message' => 'Invalid action format']);
        return;
    }
    
    $module = sanitize_key($matches[1]);
    
    // Load controller
    $controller_class = 'SAW_Module_' . ucfirst($module) . '_Controller';
    $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module}/controller.php";
    
    if (!file_exists($controller_file)) {
        wp_send_json_error(['message' => "Module controller not found: {$module}"]);
        return;
    }
    
    if (!class_exists('SAW_Base_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
    }
    
    require_once $controller_file;
    
    if (!class_exists($controller_class)) {
        wp_send_json_error(['message' => "Controller class not found: {$controller_class}"]);
        return;
    }
    
    $controller = new $controller_class();
    
    if (!method_exists($controller, 'ajax_load_sidebar')) {
        wp_send_json_error(['message' => 'Method ajax_load_sidebar not found']);
        return;
    }
    
    $controller->ajax_load_sidebar();
}

/**
 * Universal dispatcher for saw_delete_{module} AJAX actions
 * 
 * Extracts module name from action, loads controller, calls ajax_delete()
 */
function saw_universal_delete_dispatcher() {
    $action = $_POST['action'] ?? '';
    
    // Extract module name from action (e.g., "saw_delete_visitors" -> "visitors")
    if (!preg_match('/^saw_delete_(.+)$/', $action, $matches)) {
        wp_send_json_error(['message' => 'Invalid action format']);
        return;
    }
    
    $module = sanitize_key($matches[1]);
    
    // Load controller
    $controller_class = 'SAW_Module_' . ucfirst($module) . '_Controller';
    $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module}/controller.php";
    
    if (!file_exists($controller_file)) {
        wp_send_json_error(['message' => "Module controller not found: {$module}"]);
        return;
    }
    
    if (!class_exists('SAW_Base_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
    }
    
    require_once $controller_file;
    
    if (!class_exists($controller_class)) {
        wp_send_json_error(['message' => "Controller class not found: {$controller_class}"]);
        return;
    }
    
    $controller = new $controller_class();
    
    if (!method_exists($controller, 'ajax_delete')) {
        wp_send_json_error(['message' => 'Method ajax_delete not found']);
        return;
    }
    
    $controller->ajax_delete();
}

/**
 * Universal dispatcher for saw_create_{module} AJAX actions
 * 
 * Extracts module name from action, loads controller, calls ajax_create()
 */
function saw_universal_create_dispatcher() {
    $action = $_POST['action'] ?? '';
    
    // Extract module name from action (e.g., "saw_create_visitors" -> "visitors")
    if (!preg_match('/^saw_create_(.+)$/', $action, $matches)) {
        wp_send_json_error(['message' => 'Invalid action format']);
        return;
    }
    
    $module = sanitize_key($matches[1]);
    
    // Load controller
    $controller_class = 'SAW_Module_' . ucfirst($module) . '_Controller';
    $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module}/controller.php";
    
    if (!file_exists($controller_file)) {
        wp_send_json_error(['message' => "Module controller not found: {$module}"]);
        return;
    }
    
    if (!class_exists('SAW_Base_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
    }
    
    require_once $controller_file;
    
    if (!class_exists($controller_class)) {
        wp_send_json_error(['message' => "Controller class not found: {$controller_class}"]);
        return;
    }
    
    $controller = new $controller_class();
    
    if (!method_exists($controller, 'ajax_create')) {
        wp_send_json_error(['message' => 'Method ajax_create not found']);
        return;
    }
    
    $controller->ajax_create();
}

/**
 * Universal dispatcher for saw_edit_{module} AJAX actions
 * 
 * Extracts module name from action, loads controller, calls ajax_edit()
 */
function saw_universal_edit_dispatcher() {
    $action = $_POST['action'] ?? '';
    
    // Extract module name from action (e.g., "saw_edit_visitors" -> "visitors")
    if (!preg_match('/^saw_edit_(.+)$/', $action, $matches)) {
        wp_send_json_error(['message' => 'Invalid action format']);
        return;
    }
    
    $module = sanitize_key($matches[1]);
    
    // Load controller
    $controller_class = 'SAW_Module_' . ucfirst($module) . '_Controller';
    $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module}/controller.php";
    
    if (!file_exists($controller_file)) {
        wp_send_json_error(['message' => "Module controller not found: {$module}"]);
        return;
    }
    
    if (!class_exists('SAW_Base_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
    }
    
    require_once $controller_file;
    
    if (!class_exists($controller_class)) {
        wp_send_json_error(['message' => "Controller class not found: {$controller_class}"]);
        return;
    }
    
    $controller = new $controller_class();
    
    if (!method_exists($controller, 'ajax_edit')) {
        wp_send_json_error(['message' => 'Method ajax_edit not found']);
        return;
    }
    
    $controller->ajax_edit();
}

/**
 * Register universal AJAX dispatchers for all modules
 * 
 * This function should be called during plugin initialization.
 * It registers wildcard-style handlers that work for ALL modules.
 */
function saw_register_universal_ajax_handlers() {
    $modules = [
        'visitors', 'visits', 'companies', 'branches', 'customers',
        'account-types', 'departments', 'users', 'terminals'
    ];
    
    foreach ($modules as $module) {
        // Register load_sidebar handler
        add_action("wp_ajax_saw_load_sidebar_{$module}", 'saw_universal_load_sidebar_dispatcher');
        
        // Register delete handler
        add_action("wp_ajax_saw_delete_{$module}", 'saw_universal_delete_dispatcher');
        
        // Register create handler (for sidebar forms)
        add_action("wp_ajax_saw_create_{$module}", 'saw_universal_create_dispatcher');
        
        // Register edit handler (for sidebar forms)
        add_action("wp_ajax_saw_edit_{$module}", 'saw_universal_edit_dispatcher');
    }
}
