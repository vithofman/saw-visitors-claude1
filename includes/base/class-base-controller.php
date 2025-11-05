<?php
/**
 * Base Controller Class - Database-First with Multi-Branch Support
 * 
 * @package SAW_Visitors
 * @version 5.1.1
 * 
 * CHANGELOG:
 * - FIXED: Permission check používá $this->entity místo $this->config['entity']
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Base_Controller 
{
    protected $model;
    protected $config;
    protected $entity;
    
    /**
     * Verify module access based on permissions
     * 
     * ✅ FIXED v5.1.1: Uses $this->entity instead of $this->config['entity']
     * ✅ FIXED v5.1.1: Explicit SAW_Permissions class loading
     * ✅ UPDATED: Uses SAW_Error_Handler instead of wp_die
     */
    protected function verify_module_access() {
        // Super admin má vždy přístup
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // ✅ FIX: Explicit load SAW_Permissions if not loaded
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        // Kontrola že třída existuje
        if (!class_exists('SAW_Permissions')) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::permission_denied('Permissions system not available');
            } else {
                wp_die('Permissions system not available');
            }
            return false;
        }
        
        // Získání role uživatele
        $role = $this->get_current_user_role();
        
        if (empty($role)) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::permission_denied('User role not found');
            } else {
                wp_die('User role not found');
            }
            return false;
        }
        
        // ✅ CRITICAL FIX: Používáme $this->entity místo $this->config['entity']
        $has_access = SAW_Permissions::check($role, $this->entity, 'list');
        
        if (!$has_access) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::permission_denied('Nemáte oprávnění k tomuto modulu');
            } else {
                wp_die('Nemáte oprávnění k tomuto modulu');
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user can perform action
     * 
     * ✅ FIXED v5.1.1: Uses $this->entity instead of $this->config['entity']
     * ✅ FIXED v5.1.1: Explicit SAW_Permissions class loading
     * 
     * @param string $action
     * @return bool
     */
    protected function can($action) {
        // Super admin má vždy přístup
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // ✅ FIX: Explicit load SAW_Permissions if not loaded
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        // Kontrola existence permission systému
        if (!class_exists('SAW_Permissions')) {
            return false;
        }
        
        // Získání role uživatele
        $role = $this->get_current_user_role();
        
        if (empty($role)) {
            return false;
        }
        
        // ✅ CRITICAL FIX: Používáme $this->entity místo $this->config['entity']
        return SAW_Permissions::check($role, $this->entity, $action);
    }
    
    /**
     * Get accessible branches for current user
     * 
     * ✅ NEW: Multi-branch support for super_manager
     * 
     * @return array Branch objects
     */
    protected function get_accessible_branches() {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin' || $role === 'admin') {
            global $wpdb;
            $customer_id = $this->get_current_customer_id();
            
            if (!$customer_id) {
                return [];
            }
            
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM %i 
                 WHERE customer_id = %d AND is_active = 1 
                 ORDER BY is_headquarters DESC, name ASC",
                $wpdb->prefix . 'saw_branches',
                $customer_id
            ), ARRAY_A);
        }
        
        if ($role === 'super_manager') {
            if (!class_exists('SAW_User_Branches') || !class_exists('SAW_Context')) {
                return [];
            }
            
            $saw_user_id = SAW_Context::get_saw_user_id();
            if (!$saw_user_id) {
                return [];
            }
            
            return SAW_User_Branches::get_branches_for_user($saw_user_id);
        }
        
        return [];
    }
    
    /**
     * Get accessible branch IDs for current user
     * 
     * ✅ NEW: Multi-branch support
     * 
     * @return array Branch IDs
     */
    protected function get_accessible_branch_ids() {
        $branches = $this->get_accessible_branches();
        return array_map(function($branch) {
            return intval($branch['id']);
        }, $branches);
    }
    
    /**
     * Check if user can access specific branch
     * 
     * ✅ NEW: Branch access validation
     * 
     * @param int $branch_id
     * @return bool
     */
    protected function can_access_branch($branch_id) {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if ($role === 'admin') {
            global $wpdb;
            $customer_id = $this->get_current_customer_id();
            
            if (!$customer_id) {
                return false;
            }
            
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM %i 
                 WHERE id = %d AND customer_id = %d AND is_active = 1",
                $wpdb->prefix . 'saw_branches',
                $branch_id,
                $customer_id
            ), ARRAY_A);
            
            return !empty($branch);
        }
        
        if ($role === 'super_manager') {
            if (!class_exists('SAW_User_Branches') || !class_exists('SAW_Context')) {
                return false;
            }
            
            $saw_user_id = SAW_Context::get_saw_user_id();
            if (!$saw_user_id) {
                return false;
            }
            
            return SAW_User_Branches::is_user_allowed_branch($saw_user_id, $branch_id);
        }
        
        return false;
    }
    
    /**
     * Get current customer
     * 
     * ✅ UPDATED: Uses SAW_Context instead of sessions
     * 
     * @return array|null
     */
    protected function get_current_customer() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_data();
        }
        
        return null;
    }
    
    /**
     * Get current branch
     * 
     * ✅ UPDATED: Uses SAW_Context instead of sessions
     * 
     * @return array|null
     */
    protected function get_current_branch() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_branch_data();
        }
        
        return null;
    }
    
    /**
     * Get current user role
     * 
     * ✅ UPDATED: Uses SAW_Context
     * 
     * @return string|null
     */
    protected function get_current_user_role() {
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        return null;
    }
    
    /**
     * Get current customer ID
     * 
     * ✅ NEW: Direct access helper
     * 
     * @return int|null
     */
    protected function get_current_customer_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_id();
        }
        
        return null;
    }
    
    /**
     * Get current branch ID
     * 
     * ✅ NEW: Direct access helper
     * 
     * @return int|null
     */
    protected function get_current_branch_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_branch_id();
        }
        
        return null;
    }
    
    /**
     * Render with layout
     * 
     * @param string $content
     * @param string $title
     */
    protected function render_with_layout($content, $title = '') {
        $user = $this->get_current_user_data();
        $customer = $this->get_current_customer();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, $title, $this->entity, $user, $customer);
        } else {
            echo $content;
        }
    }
    
    /**
     * Get current user data
     * 
     * @return array
     */
    protected function get_current_user_data() {
        $wp_user = wp_get_current_user();
        
        if (!$wp_user->ID) {
            return [
                'id' => 0,
                'name' => 'Guest',
                'email' => '',
                'role' => 'guest'
            ];
        }
        
        global $wpdb;
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE wp_user_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            $wp_user->ID
        ), ARRAY_A);
        
        if ($saw_user) {
            return [
                'id' => $saw_user['id'],
                'wp_user_id' => $wp_user->ID,
                'name' => $saw_user['first_name'] . ' ' . $saw_user['last_name'],
                'email' => $wp_user->user_email,
                'role' => $saw_user['role'],
                'first_name' => $saw_user['first_name'],
                'last_name' => $saw_user['last_name'],
                'customer_id' => $saw_user['customer_id']
            ];
        }
        
        return [
            'id' => $wp_user->ID,
            'name' => $wp_user->display_name,
            'email' => $wp_user->user_email,
            'role' => current_user_can('manage_options') ? 'super_admin' : 'admin'
        ];
    }
    
    /**
     * Render flash messages
     */
    protected function render_flash_messages() {
        if (!class_exists('SAW_Session_Manager')) {
            return;
        }
        
        $session = SAW_Session_Manager::instance();
        
        if ($session->has('flash_success')) {
            echo '<div class="saw-alert saw-alert-success">' . esc_html($session->get('flash_success')) . '</div>';
            $session->unset('flash_success');
        }
        
        if ($session->has('flash_error')) {
            echo '<div class="saw-alert saw-alert-error">' . esc_html($session->get('flash_error')) . '</div>';
            $session->unset('flash_error');
        }
    }
    
    /**
     * Set flash message
     * 
     * @param string $message
     * @param string $type success|error
     */
    protected function set_flash($message, $type = 'success') {
        if (!class_exists('SAW_Session_Manager')) {
            return;
        }
        
        $session = SAW_Session_Manager::instance();
        $session->set('flash_' . $type, $message);
    }
    
    /**
     * Redirect helper
     * 
     * @param string $url
     */
    protected function redirect($url) {
        wp_redirect($url);
        exit;
    }
    
    /**
     * Before save hook
     * Override in child controller if needed
     * 
     * @param array $data
     * @return array|WP_Error
     */
    protected function before_save($data) {
        return $data;
    }
    
    /**
     * After save hook
     * Override in child controller if needed
     * 
     * @param int $id
     */
    protected function after_save($id) {
        // Override in child controller
    }
    
    /**
     * Before delete hook
     * Override in child controller if needed
     * 
     * @param int $id
     * @return bool|WP_Error
     */
    protected function before_delete($id) {
        return true;
    }
    
    /**
     * After delete hook
     * Override in child controller if needed
     * 
     * @param int $id
     */
    protected function after_delete($id) {
        // Override in child controller
    }
    
    /**
     * Enqueue assets
     * Override in child controller if needed
     */
    protected function enqueue_assets() {
        // Override in child controller
    }
    
    abstract public function index();
}