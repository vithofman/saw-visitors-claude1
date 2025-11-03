<?php
/**
 * Permissions Module Controller
 * 
 * Handles permissions CRUD operations and AJAX requests.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Permissions_Controller extends SAW_Base_Controller {
    
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Permissions_Model($this->config);
        
        add_action('wp_ajax_saw_update_permission', [$this, 'ajax_update_permission']);
        add_action('wp_ajax_saw_get_permissions_for_role', [$this, 'ajax_get_permissions_for_role']);
        add_action('wp_ajax_saw_reset_permissions', [$this, 'ajax_reset_permissions']);
        add_action('wp_ajax_saw_get_permissions_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_permissions', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_permissions', [$this, 'ajax_delete']);
    }
    
    /**
     * Index - matrix view
     */
    public function index() {
        $this->verify_module_access();
        $this->verify_capability('list');
        
        $selected_role = $_GET['role'] ?? 'admin';
        
        $roles = [
            'admin' => 'Admin',
            'super_manager' => 'Super Manager',
            'manager' => 'Manager',
            'terminal' => 'Terminál',
        ];
        
        $modules = SAW_Module_Loader::get_all();
        
        $actions = ['list', 'view', 'create', 'edit', 'delete'];
        
        $permissions = SAW_Permissions::get_all_for_role($selected_role);
        
        require $this->config['path'] . 'list-template.php';
    }
    
    /**
     * AJAX: Update single permission
     */
    public function ajax_update_permission() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $role = sanitize_text_field($_POST['role'] ?? '');
        $module = sanitize_text_field($_POST['module'] ?? '');
        $action = sanitize_text_field($_POST['action'] ?? '');
        $allowed = isset($_POST['allowed']) ? (int) $_POST['allowed'] : 0;
        $scope = sanitize_text_field($_POST['scope'] ?? 'all');
        
        if (empty($role) || empty($module) || empty($action)) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }
        
        $result = SAW_Permissions::set($role, $module, $action, $allowed, $scope);
        
        if ($result) {
            wp_send_json_success(['message' => 'Oprávnění uloženo']);
        } else {
            wp_send_json_error(['message' => 'Chyba při ukládání']);
        }
    }
    
    /**
     * AJAX: Get permissions for role
     */
    public function ajax_get_permissions_for_role() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(['message' => 'Missing role']);
        }
        
        $permissions = SAW_Permissions::get_all_for_role($role);
        
        wp_send_json_success(['permissions' => $permissions]);
    }
    
    /**
     * AJAX: Reset permissions to defaults
     */
    public function ajax_reset_permissions() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(['message' => 'Missing role']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        $wpdb->delete($table, ['role' => $role], ['%s']);
        
        $schema = require SAW_VISITORS_PLUGIN_DIR . 'includes/auth/permissions-schema.php';
        
        if (isset($schema[$role])) {
            $role_schema = [$role => $schema[$role]];
            SAW_Permissions::bulk_insert_from_schema($role_schema);
        }
        
        SAW_Permissions::clear_cache();
        
        wp_send_json_success(['message' => 'Oprávnění resetována']);
    }
}
