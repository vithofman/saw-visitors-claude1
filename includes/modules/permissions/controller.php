<?php
/**
 * Permissions Module Controller
 * 
 * Handles the permissions matrix UI and AJAX operations for managing
 * role-based access control across all modules.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Permissions
 * @since       4.10.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permissions Controller Class
 * 
 * Provides matrix view for managing permissions and AJAX handlers
 * for real-time permission updates.
 * 
 * @since 4.10.0
 */
class SAW_Module_Permissions_Controller extends SAW_Base_Controller {
    
    use SAW_AJAX_Handlers;
    
    /**
     * Constructor - Initialize controller with config and model
     * 
     * Loads module configuration and initializes model.
     * 
     * Note: Custom AJAX actions are registered in SAW_Visitors::register_module_ajax_handlers()
     * to ensure they're hooked BEFORE WordPress processes AJAX requests.
     * Controllers load on-demand (lazy loading), so registration here would be TOO LATE.
     * 
     * @since 4.10.0
     */
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Permissions_Model($this->config);
        
        // ✅ NOTE: Custom AJAX actions are now registered in SAW_Visitors::register_module_ajax_handlers()
        // This ensures they're hooked BEFORE WordPress processes AJAX requests
        // Controllers load on-demand (lazy loading), so registration here would be TOO LATE
    }
    
    /**
     * Display permissions matrix view
     * 
     * Shows interactive matrix of all modules, actions, and their permissions
     * for the selected role. Includes role selector and bulk action buttons.
     * 
     * @since 4.10.0
     * @return void
     */
    public function index() {
        $this->verify_module_access();
        
        // Only super admin can manage permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions - only super admin can manage permissions', 'Forbidden', array('response' => 403));
        }
        
        // Enqueue module-specific assets
        $this->enqueue_assets();
        
        // Get selected role (default: admin)
        $selected_role = $_GET['role'] ?? 'admin';
        
        // Available roles
        $roles = array(
            'admin' => 'Admin',
            'super_manager' => 'Super Manager',
            'manager' => 'Manager',
            'terminal' => 'Terminál',
        );
        
        // Get all modules
        $modules = SAW_Module_Loader::get_all();
        
        // Available actions
        $actions = array('list', 'view', 'create', 'edit', 'delete');
        
        // Load SAW_Permissions class if not already loaded
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        // Get all permissions for selected role
        $permissions = SAW_Permissions::get_all_for_role($selected_role);
        
        // Create AJAX nonce
        $ajax_nonce = wp_create_nonce('saw_ajax_nonce');
        
        // Start output buffering
        ob_start();
        
        // Inject module-specific CSS
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        // Render flash messages
        $this->render_flash_messages();
        
        // Load list template (matrix view)
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        // Render within admin layout
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * AJAX: Update single permission
     * 
     * Handles real-time updates when user toggles permission checkbox
     * or changes scope selector.
     * 
     * @since 4.10.0
     * @return void Sends JSON response
     */
    public function ajax_update_permission() {
        saw_verify_ajax_unified();
        
        // Only super admin can update permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Get and sanitize parameters
        $role = sanitize_text_field($_POST['role'] ?? '');
        $module = sanitize_text_field($_POST['module'] ?? '');
        $action = sanitize_text_field($_POST['permission_action'] ?? '');
        $allowed = isset($_POST['allowed']) ? (int) $_POST['allowed'] : 0;
        $scope = sanitize_text_field($_POST['scope'] ?? 'all');
        
        // Validate required fields
        if (empty($role) || empty($module) || empty($action)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }
        
        // Load SAW_Permissions class
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        // Update permission
        $result = SAW_Permissions::set($role, $module, $action, $allowed, $scope);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Oprávnění uloženo'));
        } else {
            wp_send_json_error(array('message' => 'Chyba při ukládání'));
        }
    }
    
    /**
     * AJAX: Get all permissions for a role
     * 
     * Used when switching roles to fetch permission data.
     * 
     * @since 4.10.0
     * @return void Sends JSON response
     */
    public function ajax_get_permissions_for_role() {
        saw_verify_ajax_unified();
        
        // Only super admin can view permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Get role parameter
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(array('message' => 'Missing role'));
        }
        
        // Load SAW_Permissions class
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        // Get permissions
        $permissions = SAW_Permissions::get_all_for_role($role);
        
        wp_send_json_success(array('permissions' => $permissions));
    }
    
    /**
     * AJAX: Reset permissions to defaults
     * 
     * Deletes all custom permissions for a role and restores
     * default permissions from schema.
     * 
     * @since 4.10.0
     * @return void Sends JSON response
     */
    public function ajax_reset_permissions() {
        saw_verify_ajax_unified();
        
        // Only super admin can reset permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Get role parameter
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(array('message' => 'Missing role'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_permissions';
        
        // Delete all permissions for this role
        $wpdb->delete($table, array('role' => $role), array('%s'));
        
        // Load default schema
        $schema = require SAW_VISITORS_PLUGIN_DIR . 'includes/auth/permissions-schema.php';
        
        // Restore defaults if schema exists for this role
        if (isset($schema[$role])) {
            $role_schema = array($role => $schema[$role]);
            
            // Load SAW_Permissions class
            if (!class_exists('SAW_Permissions')) {
                $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
                if (file_exists($permissions_file)) {
                    require_once $permissions_file;
                }
            }
            
            // Bulk insert default permissions
            SAW_Permissions::bulk_insert_from_schema($role_schema);
        }
        
        // Clear cache
        SAW_Permissions::clear_cache();
        
        wp_send_json_success(array('message' => 'Oprávnění resetována'));
    }
    
    /**
     * Enqueue module assets
     * 
     * @since 4.10.0
     * @return void
     */
    protected function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('permissions');
        
        // Localize script data
        wp_localize_script(
            'saw-permissions',
            'sawPermissionsData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_ajax_nonce'),
                'homeUrl' => home_url('/admin/permissions/')
            )
        );
    }
}