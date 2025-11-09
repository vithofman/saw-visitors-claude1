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
 * @version    8.0.0 - RELATED DATA SUPPORT ADDED
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
     * Load relations configuration for module
     * 
     * @since 8.0.0
     * @return array|null Relations config or null if file doesn't exist
     */
    protected function load_relations_config() {
        $relations_file = $this->config['path'] . 'relations.php';
        
        if (!file_exists($relations_file)) {
            return null;
        }
        
        $relations = include $relations_file;
        
        if (empty($relations) || !is_array($relations)) {
            return null;
        }
        
        return $relations;
    }
    
    /**
     * Load related data for detail sidebar
     * 
     * Fetches related records based on relations.php configuration.
     * 
     * @since 8.0.0
     * @param int $item_id Current item ID
     * @return array|null Related data grouped by relation key, or null
     */
    protected function load_related_data($item_id) {
        $relations = $this->load_relations_config();
        
        if (empty($relations)) {
            return null;
        }
        
        global $wpdb;
        $related_data = array();
        
        foreach ($relations as $key => $relation) {
            // Validate required fields
            if (empty($relation['entity']) || empty($relation['foreign_key'])) {
                continue;
            }
            
            $table = $wpdb->prefix . 'saw_' . $relation['entity'];
            $foreign_key = $relation['foreign_key'];
            $order_by = $relation['order_by'] ?? 'id DESC';
            
            // Build query
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$foreign_key} = %d ORDER BY {$order_by}",
                $item_id
            );
            
            $items = $wpdb->get_results($query, ARRAY_A);
            
            if (empty($items)) {
                continue;
            }
            
            // Format items for display
            $formatted_items = array();
            foreach ($items as $item) {
                $display_text = $this->format_related_item_display($item, $relation);
                
                $formatted_items[] = array(
                    'id' => $item['id'],
                    'display' => $display_text,
                    'raw' => $item,
                );
            }
            
            $related_data[$key] = array(
                'label' => $relation['label'],
                'icon' => $relation['icon'] ?? '📋',
                'entity' => $relation['entity'],
                'items' => $formatted_items,
                'route' => $relation['route'],
                'count' => count($formatted_items),
            );
        }
        
        return empty($related_data) ? null : $related_data;
    }
    
    /**
     * Format related item display text
     * 
     * @since 8.0.0
     * @param array $item Item data
     * @param array $relation Relation config
     * @return string Formatted display text
     */
    protected function format_related_item_display($item, $relation) {
        // Single field display
        if (!empty($relation['display_field'])) {
            $field = $relation['display_field'];
            return $item[$field] ?? '#' . $item['id'];
        }
        
        // Multiple fields display
        if (!empty($relation['display_fields']) && is_array($relation['display_fields'])) {
            $parts = array();
            foreach ($relation['display_fields'] as $field) {
                if (!empty($item[$field])) {
                    $parts[] = $item[$field];
                }
            }
            return implode(' - ', $parts);
        }
        
        // Fallback
        return $item['name'] ?? $item['title'] ?? '#' . $item['id'];
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
        return $post;
    }
    
    /**
     * Verify module access permissions
     *
     * Checks if current user has permission to access this module.
     * Wp_die() on failure.
     *
     * @since 1.0.0
     * @return void
     */
    protected function verify_module_access() {
        $list_cap = $this->config['capabilities']['list'] ?? 'read';
        
        if (!current_user_can($list_cap)) {
            wp_die(
                __('Nemáte oprávnění k přístupu do této sekce.', 'saw-visitors'),
                __('Přístup odepřen', 'saw-visitors'),
                array('response' => 403)
            );
        }
    }
    
    /**
     * Check action permission
     *
     * @since 1.0.0
     * @param string $action Action name (list, view, create, edit, delete)
     * @return bool True if user can perform action
     */
    protected function can($action) {
        $capability = $this->config['capabilities'][$action] ?? 'manage_options';
        return current_user_can($capability);
    }
    
    /**
     * Validate scope access for data
     *
     * Ensures user can only access data within their scope (customer/branch/department).
     *
     * @since 1.0.0
     * @param array  $data   Data to validate
     * @param string $action Action being performed
     * @return true|WP_Error True on success, WP_Error on failure
     */
    protected function validate_scope_access($data, $action = 'view') {
        // Super admin can access everything
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check customer isolation
        if (!empty($this->config['has_customer_isolation'])) {
            $current_customer_id = $this->get_current_customer_id();
            
            if (!$current_customer_id) {
                return new WP_Error('no_customer', __('Není nastaven zákazník', 'saw-visitors'));
            }
            
            if (!empty($data['customer_id']) && (int)$data['customer_id'] !== (int)$current_customer_id) {
                return new WP_Error('customer_mismatch', __('Nemáte oprávnění k tomuto zákazníkovi', 'saw-visitors'));
            }
        }
        
        // Check branch isolation
        if (!empty($this->config['has_branch_isolation'])) {
            $current_branch_id = $this->get_current_branch_id();
            
            if (!$current_branch_id) {
                return new WP_Error('no_branch', __('Není nastavena pobočka', 'saw-visitors'));
            }
            
            if (!empty($data['branch_id']) && (int)$data['branch_id'] !== (int)$current_branch_id) {
                return new WP_Error('branch_mismatch', __('Nemáte oprávnění k této pobočce', 'saw-visitors'));
            }
        }
        
        return true;
    }
    
    /**
     * Get accessible branch IDs for current user
     *
     * Returns array of branch IDs the user can access.
     *
     * @since 1.0.0
     * @return array|null Array of branch IDs, or null for super admin (all access)
     */
    protected function get_accessible_branch_ids() {
        if (current_user_can('manage_options')) {
            return null; // Super admin - all branches
        }
        
        $role = $this->get_current_user_role();
        
        if ($role === 'super_manager') {
            // Super manager - multiple branches via user_branches table
            if (!class_exists('SAW_User_Branches')) {
                require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-user-branches.php';
            }
            
            $saw_user_id = SAW_Context::get_saw_user_id();
            return SAW_User_Branches::get_branch_ids_for_user($saw_user_id);
        }
        
        // Other roles - single active branch from context
        $branch_id = $this->get_current_branch_id();
        return $branch_id ? [$branch_id] : [];
    }
    
    /**
     * Get current customer
     *
     * Returns customer data for current context.
     *
     * @since 1.0.0
     * @return array|null Customer data or null
     */
    protected function get_current_customer() {
        $customer_id = $this->get_current_customer_id();
        
        if (!$customer_id) {
            return null;
        }
        
        global $wpdb;
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_customers',
            $customer_id
        ), ARRAY_A);
        
        return $customer;
    }
    
    /**
     * Get current branch
     *
     * Returns branch data for current context.
     *
     * @since 1.0.0
     * @return array|null Branch data or null
     */
    protected function get_current_branch() {
        $branch_id = $this->get_current_branch_id();
        
        if (!$branch_id) {
            return null;
        }
        
        global $wpdb;
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_branches',
            $branch_id
        ), ARRAY_A);
        
        return $branch;
    }
    
    /**
     * Add customer isolation to WHERE clause
     *
     * Modifies WHERE array to include customer_id filter if isolation is enabled.
     *
     * @since 1.0.0
     * @param array $where WHERE conditions
     * @return array Modified WHERE conditions
     */
    protected function apply_customer_isolation($where = array()) {
        if (empty($this->config['has_customer_isolation'])) {
            return $where;
        }
        
        if (current_user_can('manage_options')) {
            return $where; // Super admin bypasses isolation
        }
        
        $customer_id = $this->get_current_customer_id();
        
        if ($customer_id) {
            $where['customer_id'] = $customer_id;
        }
        
        return $where;
    }
    
    /**
     * Add branch isolation to WHERE clause
     *
     * Modifies WHERE array to include branch_id filter(s) if isolation is enabled.
     *
     * @since 1.0.0
     * @param array $where WHERE conditions
     * @return array Modified WHERE conditions
     */
    protected function apply_branch_isolation($where = array()) {
        if (empty($this->config['has_branch_isolation'])) {
            return $where;
        }
        
        if (current_user_can('manage_options')) {
            return $where; // Super admin bypasses isolation
        }
        
        $branch_ids = $this->get_accessible_branch_ids();
        
        if (!empty($branch_ids)) {
            if (count($branch_ids) === 1) {
                $where['branch_id'] = $branch_ids[0];
            } else {
                $where['branch_id__in'] = $branch_ids;
            }
        }
        
        return $where;
    }
    
    /**
     * Check if item belongs to accessible scope
     *
     * Validates if a single item is within user's access scope.
     *
     * @since 1.0.0
     * @param array $item Item data
     * @return bool True if accessible
     */
    protected function is_item_accessible($item) {
        if (current_user_can('manage_options')) {
            return true; // Super admin
        }
        
        // Check customer isolation
        if (!empty($this->config['has_customer_isolation'])) {
            $current_customer_id = $this->get_current_customer_id();
            
            if (!$current_customer_id || (int)$item['customer_id'] !== (int)$current_customer_id) {
                return false;
            }
        }
        
        // Check branch isolation
        if (!empty($this->config['has_branch_isolation'])) {
            $branch_ids = $this->get_accessible_branch_ids();
            
            if (empty($branch_ids) || !in_array((int)$item['branch_id'], $branch_ids)) {
                return false;
            }
        }
        
        return true;
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