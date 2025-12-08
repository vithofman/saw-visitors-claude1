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
 * @version     2.0.1 - FIXED: SAW_Module_Style_Manager class check + translations
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// REQUIRED BASE CLASSES
// ============================================
if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
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
     * Translation function
     * @var callable
     */
    private $tr;
    
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
        // ============================================
        // TRANSLATIONS SETUP
        // ============================================
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'permissions') 
            : array();
        
        $this->tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        // ============================================
        // MODULE SETUP
        // ============================================
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Permissions_Model($this->config);
        
        // NOTE: Custom AJAX actions are registered in SAW_Visitors::register_module_ajax_handlers()
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
        
        $tr = $this->tr;
        
        // Only super admin can manage permissions
        if (!current_user_can('manage_options')) {
            wp_die(
                $tr('error_super_admin_only', 'Nedostatečná oprávnění - pouze super admin může spravovat oprávnění'),
                $tr('error_forbidden', 'Zakázáno'),
                array('response' => 403)
            );
        }
        
        // Enqueue module-specific assets
        $this->enqueue_assets();
        
        // Get selected role (default: admin)
        $selected_role = $_GET['role'] ?? 'admin';
        
        // Available roles from config
        $roles = $this->config['role_options'] ?? array(
            'admin' => $tr('role_admin', 'Admin'),
            'super_manager' => $tr('role_super_manager', 'Super Manager'),
            'manager' => $tr('role_manager', 'Manager'),
            'terminal' => $tr('role_terminal', 'Terminál'),
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
        
        // Pass translations to template
        $translations = $this->get_template_translations();
        
        // Start output buffering
        ob_start();
        
        // ✅ FIXED: Check if SAW_Module_Style_Manager exists before using it
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
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
     * Get translations for template
     * 
     * @return array
     */
    private function get_template_translations() {
        $tr = $this->tr;
        
        return array(
            // Page
            'page_title' => $tr('page_title', 'Správa oprávnění'),
            
            // Role selector
            'label_select_role' => $tr('label_select_role', 'Vyberte roli:'),
            
            // Buttons
            'btn_allow_all' => $tr('btn_allow_all', 'Vše povolit'),
            'btn_deny_all' => $tr('btn_deny_all', 'Vše zakázat'),
            'btn_reset' => $tr('btn_reset', 'Reset na výchozí'),
            
            // Info
            'info_auto_save' => $tr('info_auto_save', 'Změny se ukládají automaticky po kliknutí na checkbox.'),
            
            // Column headers
            'col_module' => $tr('col_module', 'Modul'),
            'col_list' => $tr('col_list', 'Zobrazit'),
            'col_view' => $tr('col_view', 'Detail'),
            'col_create' => $tr('col_create', 'Vytvořit'),
            'col_edit' => $tr('col_edit', 'Upravit'),
            'col_delete' => $tr('col_delete', 'Smazat'),
            'col_scope' => $tr('col_scope', 'Rozsah dat'),
            
            // Scope options
            'scope_all' => $tr('scope_all', 'Všechno'),
            'scope_customer' => $tr('scope_customer', 'Zákazník'),
            'scope_branch' => $tr('scope_branch', 'Pobočka'),
            'scope_department' => $tr('scope_department', 'Oddělení'),
            'scope_own' => $tr('scope_own', 'Jen já'),
            
            // Messages
            'msg_saved' => $tr('msg_saved', 'Uloženo'),
        );
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
        
        $tr = $this->tr;
        
        // Only super admin can update permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => $tr('error_no_permission', 'Nedostatečná oprávnění')));
        }
        
        // Get and sanitize parameters
        $role = sanitize_text_field($_POST['role'] ?? '');
        $module = sanitize_text_field($_POST['module'] ?? '');
        $action = sanitize_text_field($_POST['permission_action'] ?? '');
        $allowed = isset($_POST['allowed']) ? (int) $_POST['allowed'] : 0;
        $scope = sanitize_text_field($_POST['scope'] ?? 'all');
        
        // Validate required fields
        if (empty($role) || empty($module) || empty($action)) {
            wp_send_json_error(array('message' => $tr('error_missing_fields', 'Chybí povinná pole')));
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
            wp_send_json_success(array('message' => $tr('msg_permission_saved', 'Oprávnění uloženo')));
        } else {
            wp_send_json_error(array('message' => $tr('error_save_failed', 'Chyba při ukládání')));
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
        
        $tr = $this->tr;
        
        // Only super admin can view permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => $tr('error_no_permission', 'Nedostatečná oprávnění')));
        }
        
        // Get role parameter
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(array('message' => $tr('error_missing_role', 'Chybí role')));
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
        
        $tr = $this->tr;
        
        // Only super admin can reset permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => $tr('error_no_permission', 'Nedostatečná oprávnění')));
        }
        
        // Get role parameter
        $role = sanitize_text_field($_POST['role'] ?? '');
        
        if (empty($role)) {
            wp_send_json_error(array('message' => $tr('error_missing_role', 'Chybí role')));
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
        
        wp_send_json_success(array('message' => $tr('msg_permissions_reset', 'Oprávnění resetována na výchozí')));
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