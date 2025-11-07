<?php
/**
 * Base Controller Class
 *
 * Parent class for all module controllers.
 * Provides common functionality: permissions, scope validation, branch access,
 * rendering, flash messages, data context helpers, and sidebar helpers.
 *
 * @package    SAW_Visitors
 * @subpackage Base
 * @version    7.1.0 - SIDEBAR SUPPORT ADDED
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_Base_Controller Class
 *
 * Abstract base controller with permission checking and scope validation.
 * All module controllers must extend this class.
 *
 * @since 1.0.0
 */
abstract class SAW_Base_Controller 
{
    /**
     * Model instance
     *
     * @since 1.0.0
     * @var SAW_Base_Model
     */
    protected $model;
    
    /**
     * Module configuration
     *
     * @since 1.0.0
     * @var array
     */
    protected $config;
    
    /**
     * Entity name (module slug)
     *
     * @since 1.0.0
     * @var string
     */
    protected $entity;
    
    /**
     * Get sidebar context from router
     * 
     * Returns parsed sidebar context from router's query var.
     * Compatible with router v7.0.0
     * 
     * @since 7.0.0
     * @return array {
     *     Sidebar context data
     *     
     *     @type string|null $mode Sidebar mode: null, 'detail', 'create', 'edit'
     *     @type int         $id   Record ID for detail/edit
     *     @type string      $tab  Active tab for detail view
     * }
     */
    protected function get_sidebar_context() {
        $context = get_query_var('saw_sidebar_context');
        
        // Default context if not set
        if (empty($context) || !is_array($context)) {
            return array(
                'mode' => null,
                'id' => 0,
                'tab' => 'overview',
            );
        }
        
        return $context;
    }
    
    /**
     * Get sidebar mode
     * 
     * Quick helper to get just the mode from sidebar context.
     * 
     * @since 7.0.0
     * @return string|null 'detail', 'create', 'edit', or null
     */
    protected function get_sidebar_mode() {
        $context = $this->get_sidebar_context();
        return $context['mode'] ?? null;
    }
    
    /**
     * Check if sidebar is active
     * 
     * @since 7.0.0
     * @return bool True if any sidebar mode is active
     */
    protected function has_sidebar() {
        return !empty($this->get_sidebar_mode());
    }
    
    /**
     * Process sidebar context and return data for templates
     * 
     * Handles detail/create/edit modes including POST processing.
     * Call this at start of index() method.
     * 
     * @since 7.1.0
     * @return array|null Array with sidebar data, or null if POST redirect occurred
     */
    protected function process_sidebar_context() {
        $ctx = $this->get_sidebar_context();
        
        $detail_item = null;
        $form_item = null;
        $sidebar_mode = $ctx['mode'];
        $detail_tab = $ctx['tab'];
        
        // DETAIL MODE
        if ($ctx['mode'] === 'detail' && $ctx['id']) {
            if (!$this->can('view')) {
                $this->set_flash('Nemáte oprávnění zobrazit detail', 'error');
                wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
                exit;
            }
            
            $detail_item = $this->model->get_by_id($ctx['id']);
            
            if (!$detail_item) {
                $this->set_flash('Záznam nenalezen', 'error');
                wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
                exit;
            }
            
            if (method_exists($this, 'load_detail_related_data')) {
                $detail_item = $this->load_detail_related_data($detail_item, $detail_tab);
            }
        }
        
        // CREATE MODE
        elseif ($ctx['mode'] === 'create') {
            if (!$this->can('create')) {
                $this->set_flash('Nemáte oprávnění vytvářet záznamy', 'error');
                wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handle_create_post();
                return null;
            }
            
            $form_item = array();
        }
        
        // EDIT MODE
        elseif ($ctx['mode'] === 'edit' && $ctx['id']) {
            if (!$this->can('edit')) {
                $this->set_flash('Nemáte oprávnění upravovat záznamy', 'error');
                wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
                exit;
            }
            
            $form_item = $this->model->get_by_id($ctx['id']);
            
            if (!$form_item) {
                $this->set_flash('Záznam nenalezen', 'error');
                wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handle_edit_post($ctx['id']);
                return null;
            }
        }
        
        return array(
            'detail_item' => $detail_item,
            'form_item' => $form_item,
            'sidebar_mode' => $sidebar_mode,
            'detail_tab' => $detail_tab
        );
    }
    
    /**
     * Handle create POST request from sidebar form
     * 
     * @since 7.1.0
     * @return void (redirects)
     */
    protected function handle_create_post() {
        if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_' . $this->entity . '_form')) {
            wp_die('Neplatný bezpečnostní token');
        }
        
        $data = $this->prepare_form_data($_POST);
        
        $scope_validation = $this->validate_scope_access($data, 'create');
        if (is_wp_error($scope_validation)) {
            $this->set_flash($scope_validation->get_error_message(), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/create'));
            exit;
        }
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            $this->set_flash($data->get_error_message(), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/create'));
            exit;
        }
        
        $validation = $this->model->validate($data);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            $this->set_flash(implode('<br>', $errors), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/create'));
            exit;
        }
        
        $result = $this->model->create($data);
        
        if (is_wp_error($result)) {
            $this->set_flash($result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/create'));
            exit;
        }
        
        $this->after_save($result);
        
        $this->set_flash($this->config['singular'] . ' byl úspěšně vytvořen', 'success');
        wp_redirect(home_url('/admin/' . $this->config['route'] . '/' . $result . '/'));
        exit;
    }
    
    /**
     * Handle edit POST request from sidebar form
     * 
     * @since 7.1.0
     * @param int $id Record ID
     * @return void (redirects)
     */
    protected function handle_edit_post($id) {
        if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_' . $this->entity . '_form')) {
            wp_die('Neplatný bezpečnostní token');
        }
        
        $data = $this->prepare_form_data($_POST);
        $data['id'] = $id;
        
        $scope_validation = $this->validate_scope_access($data, 'edit');
        if (is_wp_error($scope_validation)) {
            $this->set_flash($scope_validation->get_error_message(), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/' . $id . '/edit'));
            exit;
        }
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            $this->set_flash($data->get_error_message(), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/' . $id . '/edit'));
            exit;
        }
        
        $validation = $this->model->validate($data, $id);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            $this->set_flash(implode('<br>', $errors), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/' . $id . '/edit'));
            exit;
        }
        
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            $this->set_flash($result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/' . $id . '/edit'));
            exit;
        }
        
        $this->after_save($id);
        
        $this->set_flash($this->config['singular'] . ' byl úspěšně aktualizován', 'success');
        wp_redirect(home_url('/admin/' . $this->config['route'] . '/' . $id . '/'));
        exit;
    }
    
    /**
     * Prepare form data from POST
     * 
     * Override in child controller for custom field processing.
     * 
     * @since 7.1.0
     * @param array $post POST data
     * @return array Prepared data
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        if (!empty($this->config['form_config']['fields'])) {
            foreach ($this->config['form_config']['fields'] as $key => $field) {
                if (isset($post[$key])) {
                    $data[$key] = $this->sanitize_field($post[$key], $field);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Sanitize field value based on type
     * 
     * @since 7.1.0
     * @param mixed $value Field value
     * @param array $field Field configuration
     * @return mixed Sanitized value
     */
    protected function sanitize_field($value, $field) {
        $type = $field['type'] ?? 'text';
        
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'number':
                return intval($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'checkbox':
                return !empty($value) ? 1 : 0;
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Load related data for detail view
     * 
     * Override in child controller to load tabs, relationships, etc.
     * 
     * @since 7.1.0
     * @param array  $item Detail item
     * @param string $tab  Active tab
     * @return array Modified item
     */
    protected function load_detail_related_data($item, $tab) {
        return $item;
    }
    
    /**
     * Verify user has access to module
     *
     * Checks 'list' permission for current module.
     * Redirects with error if access denied.
     *
     * @since 1.0.0
     * @return bool True if access granted
     */
    protected function verify_module_access() {
        $role = $this->get_current_user_role();
        
        // Super admin always has access
        if ($role === 'super_admin') {
            return true;
        }
        
        // Load permissions class if needed
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        if (!class_exists('SAW_Permissions')) {
            $this->set_flash(__('Permissions system not available', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/'));
        }
        
        if (empty($role)) {
            $this->set_flash(__('User role not found', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/'));
        }
        
        $has_access = SAW_Permissions::check($role, $this->entity, 'list');
        
        if (!$has_access) {
            $this->set_flash(__('Nemáte oprávnění k tomuto modulu', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/'));
        }
        
        return true;
    }
    
    /**
     * Check if user can perform action
     *
     * Validates permission for specific action on current module.
     *
     * @since 1.0.0
     * @param string $action Action name (list, view, create, edit, delete)
     * @return bool True if action allowed
     */
    protected function can($action) {
        $role = $this->get_current_user_role();
        
        // Super admin can do everything
        if ($role === 'super_admin') {
            return true;
        }
        
        // Load permissions class if needed
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        if (!class_exists('SAW_Permissions')) {
            return false;
        }
        
        if (empty($role)) {
            return false;
        }
        
        return SAW_Permissions::check($role, $this->entity, $action);
    }
    
    /**
     * Validate scope access for data
     *
     * Checks if user's scope allows access to data based on role permissions.
     * Validates customer_id, branch_id, department_id based on scope.
     *
     * @since 1.0.0
     * @param array  $data   Data to validate
     * @param string $action Action being performed (create, edit)
     * @return true|WP_Error True if allowed, WP_Error otherwise
     */
    protected function validate_scope_access($data, $action = 'create') {
        $role = $this->get_current_user_role();
        
        // Super admin bypasses scope checks
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!$role || !class_exists('SAW_Permissions')) {
            return new WP_Error(
                'no_permission',
                __('Nelze ověřit oprávnění', 'saw-visitors')
            );
        }
        
        $permission = SAW_Permissions::get_permission($role, $this->entity, $action);
        
        if (!$permission || !isset($permission['scope'])) {
            return new WP_Error(
                'no_permission',
                __('Nemáte oprávnění k této akci', 'saw-visitors')
            );
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
                            __('Nemůžete vytvořit/upravit záznam pro jiného zákazníka', 'saw-visitors')
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
                            __('Nemůžete vytvořit/upravit záznam pro tuto pobočku', 'saw-visitors')
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
                            __('Nemůžete vytvořit/upravit záznam pro toto oddělení', 'saw-visitors')
                        );
                    }
                }
                return true;
                
            case 'own':
                // Users with 'own' scope can only manage their own records
                return true;
                
            default:
                return new WP_Error(
                    'unknown_scope',
                    __('Neznámý scope', 'saw-visitors')
                );
        }
    }
    
    /**
     * Get accessible branches for current user
     *
     * Returns branches based on user role:
     * - super_admin/admin: all branches for customer
     * - super_manager: assigned branches
     *
     * @since 1.0.0
     * @return array Branch records
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
     * Get accessible branch IDs
     *
     * @since 1.0.0
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
     * @since 1.0.0
     * @param int $branch_id Branch ID to check
     * @return bool True if accessible
     */
    protected function can_access_branch($branch_id) {
        $accessible_ids = $this->get_accessible_branch_ids();
        return in_array(intval($branch_id), $accessible_ids);
    }
    
    /**
     * Get current customer data
     *
     * @since 1.0.0
     * @return array|null Customer data or null
     */
    protected function get_current_customer() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_data();
        }
        
        return null;
    }
    
    /**
     * Get current branch data
     *
     * @since 1.0.0
     * @return array|null Branch data or null
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
     * @since 1.0.0
     * @return string|null Role name or null
     */
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
    
    /**
     * Get current customer ID
     *
     * @since 1.0.0
     * @return int|null Customer ID or null
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
     * @since 1.0.0
     * @return int|null Branch ID or null
     */
    protected function get_current_branch_id() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_branch_id();
        }
        
        return null;
    }
    
    /**
     * Get current user's department IDs
     *
     * @since 1.0.0
     * @return array Department IDs
     */
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
            "SELECT department_id FROM %i WHERE user_id = %d",
            $wpdb->prefix . 'saw_user_departments',
            $saw_user_id
        ));
        
        return array_map('intval', $department_ids);
    }
    
    /**
     * Render content with layout
     *
     * Wraps content in application layout if available.
     *
     * @since 1.0.0
     * @param string $content HTML content
     * @param string $title   Page title
     * @return void
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
     * Returns merged WordPress and SAW user data.
     *
     * @since 1.0.0
     * @return array User data
     */
    protected function get_current_user_data() {
        $wp_user = wp_get_current_user();
        
        if (!$wp_user->ID) {
            return [
                'id' => 0,
                'name' => __('Guest', 'saw-visitors'),
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
     *
     * Displays and clears flash messages from session.
     *
     * @since 1.0.0
     * @return void
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
     * Stores message in session for next page load.
     *
     * @since 1.0.0
     * @param string $message Message text
     * @param string $type    Message type (success, error)
     * @return void
     */
    protected function set_flash($message, $type = 'success') {
        if (!class_exists('SAW_Session_Manager')) {
            return;
        }
        
        $session = SAW_Session_Manager::instance();
        $session->set('flash_' . $type, $message);
    }
    
    /**
     * Redirect to URL
     *
     * Safe redirect with exit.
     *
     * @since 1.0.0
     * @param string $url Target URL
     * @return void
     */
    protected function redirect($url) {
        wp_safe_redirect($url);
        exit;
    }
    
    /**
     * Hook before save
     *
     * Override in child classes to modify data before save.
     *
     * @since 1.0.0
     * @param array $data Data to save
     * @return array Modified data
     */
    protected function before_save($data) {
        return $data;
    }
    
    /**
     * Hook after save
     *
     * Override in child classes for post-save actions.
     *
     * @since 1.0.0
     * @param int $id Saved record ID
     * @return void
     */
    protected function after_save($id) {
    }
    
    /**
     * Hook before delete
     *
     * Override in child classes to validate before delete.
     *
     * @since 1.0.0
     * @param int $id Record ID to delete
     * @return bool True to allow delete, false to prevent
     */
    protected function before_delete($id) {
        return true;
    }
    
    /**
     * Hook after delete
     *
     * Override in child classes for post-delete actions.
     *
     * @since 1.0.0
     * @param int $id Deleted record ID
     * @return void
     */
    protected function after_delete($id) {
    }
    
    /**
     * Enqueue module assets
     *
     * Override in child classes to load module-specific CSS/JS.
     *
     * @since 1.0.0
     * @return void
     */
    protected function enqueue_assets() {
    }
    
    /**
     * Index action
     *
     * Main list view. Must be implemented by child classes.
     *
     * @since 1.0.0
     * @return void
     */
    abstract public function index();
}