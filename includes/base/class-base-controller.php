<?php
/**
 * Base Controller Class - Database-First with Multi-Branch Support
 * 
 * @package SAW_Visitors
 * @version 5.3.1 - Permissions Fix for All Roles
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Base_Controller 
{
    protected $model;
    protected $config;
    protected $entity;
    
    protected function verify_module_access() {
    $role = $this->get_current_user_role();
    
    if ($role === 'super_admin') {
        return true;
    }
    
    if (!class_exists('SAW_Permissions')) {
        $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        if (file_exists($permissions_file)) {
            require_once $permissions_file;
        }
    }
    
    if (!class_exists('SAW_Permissions')) {
        $this->set_flash('Permissions system not available', 'error');
        $this->redirect(home_url('/admin/'));
    }
    
    if (empty($role)) {
        $this->set_flash('User role not found', 'error');
        $this->redirect(home_url('/admin/'));
    }
    
    $has_access = SAW_Permissions::check($role, $this->entity, 'list');
    
    if (!$has_access) {
        $this->set_flash('Nemáte oprávnění k tomuto modulu', 'error');
        $this->redirect(home_url('/admin/'));
    }
    
    return true;
}
    
    protected function can($action) {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        if (!class_exists('SAW_Permissions')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Base Controller] SAW_Permissions not found for action: %s', $action));
            }
            return false;
        }
        
        if (empty($role)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Base Controller] No role found for action: %s', $action));
            }
            return false;
        }
        
        $has_permission = SAW_Permissions::check($role, $this->entity, $action);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Base Controller] Permission check - Role: %s, Entity: %s, Action: %s, Result: %s',
                $role,
                $this->entity,
                $action,
                $has_permission ? 'ALLOWED' : 'DENIED'
            ));
        }
        
        return $has_permission;
    }
    
    protected function validate_scope_access($data, $action = 'create') {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!$role || !class_exists('SAW_Permissions')) {
            return new WP_Error('no_permission', 'Nelze ověřit oprávnění');
        }
        
        $permission = SAW_Permissions::get_permission($role, $this->entity, $action);
        
        if (!$permission || !isset($permission['scope'])) {
            return new WP_Error('no_permission', 'Nemáte oprávnění k této akci');
        }
        
        $scope = $permission['scope'];
        
        switch ($scope) {
            case 'all':
                return true;
                
            case 'customer':
                if (isset($data['customer_id'])) {
                    $current_customer_id = $this->get_current_customer_id();
                    if ($data['customer_id'] != $current_customer_id) {
                        return new WP_Error(
                            'scope_violation',
                            'Nemůžete vytvořit/upravit záznam pro jiného zákazníka'
                        );
                    }
                }
                return true;
                
            case 'branch':
                if (isset($data['branch_id'])) {
                    $accessible_branch_ids = $this->get_accessible_branch_ids();
                    if (!in_array($data['branch_id'], $accessible_branch_ids)) {
                        return new WP_Error(
                            'scope_violation',
                            'Nemůžete vytvořit/upravit záznam pro tuto pobočku'
                        );
                    }
                }
                return true;
                
            case 'department':
                if (isset($data['department_id'])) {
                    $accessible_dept_ids = $this->get_current_department_ids();
                    if (!in_array($data['department_id'], $accessible_dept_ids)) {
                        return new WP_Error(
                            'scope_violation',
                            'Nemůžete vytvořit/upravit záznam pro toto oddělení'
                        );
                    }
                }
                return true;
                
            case 'own':
                if ($action === 'create') {
                    return true;
                }
                return true;
                
            default:
                return new WP_Error('unknown_scope', 'Neznámý scope');
        }
    }
    
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
    
    protected function get_accessible_branch_ids() {
        $branches = $this->get_accessible_branches();
        return array_map(function($branch) {
            return intval($branch['id']);
        }, $branches);
    }
    
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
    
    protected function can_access_item($item) {
        if (!isset($item['customer_id'])) {
            return true;
        }
        
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        $current_customer_id = $this->get_current_customer_id();
        if (!$current_customer_id) {
            return false;
        }
        
        return (int)$item['customer_id'] === (int)$current_customer_id;
    }
    
    protected function get_current_customer() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_data();
        }
        
        return null;
    }
    
    protected function get_current_branch() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_branch_data();
        }
        
        return null;
    }
    
    protected function get_current_user_role() {
        if (class_exists('SAW_Context')) {
            $role = SAW_Context::get_role();
            if ($role) {
                return $role;
            }
        }
        
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        return null;
    }
    
    protected function get_current_customer_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_id();
        }
        
        return null;
    }
    
    protected function get_current_branch_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_branch_id();
        }
        
        return null;
    }
    
    protected function get_current_department_ids() {
        global $wpdb;
        
        if (!class_exists('SAW_Context')) {
            return [];
        }
        
        $saw_user_id = SAW_Context::get_saw_user_id();
        if (!$saw_user_id) {
            return [];
        }
        
        $department_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments 
             WHERE user_id = %d",
            $saw_user_id
        ));
        
        return array_map('intval', $department_ids);
    }
    
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
    
    protected function set_flash($message, $type = 'success') {
        if (!class_exists('SAW_Session_Manager')) {
            return;
        }
        
        $session = SAW_Session_Manager::instance();
        $session->set('flash_' . $type, $message);
    }
    
    protected function redirect($url) {
        wp_redirect($url);
        exit;
    }
    
    protected function before_save($data) {
        return $data;
    }
    
    protected function after_save($id) {
    }
    
    protected function before_delete($id) {
        return true;
    }
    
    protected function after_delete($id) {
    }
    
    protected function enqueue_assets() {
    }
    
    abstract public function index();
}