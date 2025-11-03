<?php
/**
 * Permissions Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.0.1
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
    }
    
    public function index() {
        $this->verify_module_access();
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only super admin can manage permissions', 'Forbidden', ['response' => 403]);
        }
        
        $this->enqueue_assets();
        
        $selected_role = $_GET['role'] ?? 'admin';
        
        $roles = [
            'admin' => 'Admin',
            'super_manager' => 'Super Manager',
            'manager' => 'Manager',
            'terminal' => 'Terminál',
        ];
        
        $modules = SAW_Module_Loader::get_all();
        
        $actions = ['list', 'view', 'create', 'edit', 'delete'];
        
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        $permissions = SAW_Permissions::get_all_for_role($selected_role);
        
        $ajax_nonce = wp_create_nonce('saw_ajax_nonce');
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    public function ajax_update_permission() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $role = sanitize_text_field($_POST['role'] ?? '');
        $module = sanitize_text_field($_POST['module'] ?? '');
        $action = sanitize_text_field($_POST['permission_action'] ?? '');
        $allowed = isset($_POST['allowed']) ? (int) $_POST['allowed'] : 0;
        $scope = sanitize_text_field($_POST['scope'] ?? 'all');
        
        if (empty($role) || empty($module) || empty($action)) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }
        
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        $result = SAW_Permissions::set($role, $module, $action, $allowed, $scope);
        
        if ($result) {
            wp_send_json_success(['message' => 'Oprávnění uloženo']);
        } else {
            wp_send_json_error(['message' => 'Chyba při ukládání']);
        }
    }
    
    public function ajax_get_permissions_for_role() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(['message' => 'Missing role']);
        }
        
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        $permissions = SAW_Permissions::get_all_for_role($role);
        
        wp_send_json_success(['permissions' => $permissions]);
    }
    
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
            
            if (!class_exists('SAW_Permissions')) {
                $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
                if (file_exists($permissions_file)) {
                    require_once $permissions_file;
                }
            }
            
            SAW_Permissions::bulk_insert_from_schema($role_schema);
        }
        
        SAW_Permissions::clear_cache();
        
        wp_send_json_success(['message' => 'Oprávnění resetována']);
    }
}