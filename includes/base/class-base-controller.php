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
 * @version    12.2.1 - RBAC can() + brace fix
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
     * Lazy load lookup data with caching
     * 
     * PERFORMANCE OPTIMIZATION: Universal method for loading reference data
     * (account types, categories, etc.) with automatic caching to avoid repeated DB queries.
     * 
     * @since 8.1.0
     * @param string   $key       Lookup data key
     * @param callable $callback  Function to load data from DB
     * @param int      $cache_ttl Cache time-to-live in seconds
     * @return mixed Loaded data
     */
    protected function lazy_load_lookup_data($key, $callback, $cache_ttl = 3600) {
        static $memory_cache = array();
        
        if (isset($memory_cache[$key])) {
            return $memory_cache[$key];
        }
        
        $cache_key = 'saw_lookup_' . $this->entity . '_' . $key;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            $memory_cache[$key] = $cached;
            return $cached;
        }
        
        $data = $callback();
        
        set_transient($cache_key, $data, $cache_ttl);
        $memory_cache[$key] = $data;
        
        return $data;
    }
    
    /**
     * Invalidate lookup cache
     * 
     * PERFORMANCE OPTIMIZATION: Clears cached lookup data
     * 
     * @since 8.1.0
     * @param string $key Lookup data key
     * @return void
     */
    protected function invalidate_lookup_cache($key) {
        $cache_key = 'saw_lookup_' . $this->entity . '_' . $key;
        delete_transient($cache_key);
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
        SAW_Logger::debug('load_related_data called with ID: ' . $item_id);
        
        $relations = $this->load_relations_config();
        SAW_Logger::debug('Relations config: ' . print_r($relations, true));
        
        if (empty($relations)) {
            SAW_Logger::debug('No relations config found!');
            return null;
        }
        
        global $wpdb;
        $related_data = array();
        
        foreach ($relations as $key => $relation) {
            SAW_Logger::debug('Processing relation: ' . $key);
            
            if (empty($relation['entity'])) {
                SAW_Logger::debug('Missing entity for relation: ' . $key);
                continue;
            }
            
            $entity_table = $wpdb->prefix . 'saw_' . $relation['entity'];
            $order_by = $relation['order_by'] ?? 'id DESC';
            
            // Handle junction table (many-to-many)
            if (!empty($relation['junction_table'])) {
                SAW_Logger::debug('Using junction table for: ' . $key);
                
                if (empty($relation['foreign_key']) || empty($relation['local_key'])) {
                    SAW_Logger::debug('Missing keys for junction table!');
                    continue;
                }
                
                $junction_table = $wpdb->prefix . $relation['junction_table'];
                $foreign_key = $relation['foreign_key'];
                $local_key = $relation['local_key'];
                
                $query = $wpdb->prepare(
                    "SELECT e.* 
                     FROM {$entity_table} e
                     INNER JOIN {$junction_table} j ON e.id = j.{$local_key}
                     WHERE j.{$foreign_key} = %d
                     ORDER BY e.{$order_by}",
                    $item_id
                );
                
                SAW_Logger::debug('Junction query: ' . $query);
            } 
            // Handle direct foreign key
            else {
                SAW_Logger::debug('Using direct foreign key for: ' . $key);
                
                if (empty($relation['foreign_key'])) {
                    continue;
                }
                
                $foreign_key = $relation['foreign_key'];
                
                $query = $wpdb->prepare(
                    "SELECT * FROM {$entity_table} WHERE {$foreign_key} = %d ORDER BY {$order_by}",
                    $item_id
                );
                
                SAW_Logger::debug('Direct query: ' . $query);
            }
            
            $items = $wpdb->get_results($query, ARRAY_A);
            
            SAW_Logger::debug('Found items: ' . count($items));
            
            if (empty($items)) {
                continue;
            }
            
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
        
        SAW_Logger::debug('Total related data sections: ' . count($related_data));
        
        return empty($related_data) ? null : $related_data;
    }
    
    /**
 * Format related item display text
 * 
 * Supports three display strategies in order of priority:
 * 1. custom_display function - for complex formatting (NEW!)
 * 2. display_field - single field
 * 3. display_fields - array of fields joined with ' - '
 * 4. Fallback to common fields (name, title, id)
 * 
 * @since 8.0.0
 * @version 8.1.0 - Added custom_display support
 * @param array $item Item data
 * @param array $relation Relation config
 * @return string Formatted display text
 */
protected function format_related_item_display($item, $relation) {
    // ✅ NEW: Priority 1 - Custom display function
    if (!empty($relation['custom_display']) && is_callable($relation['custom_display'])) {
        return call_user_func($relation['custom_display'], $item);
    }
    
    // Priority 2 - Single display field
    if (!empty($relation['display_field'])) {
        $field = $relation['display_field'];
        return $item[$field] ?? '#' . $item['id'];
    }
    
    // Priority 3 - Multiple display fields
    if (!empty($relation['display_fields']) && is_array($relation['display_fields'])) {
        $parts = array();
        foreach ($relation['display_fields'] as $field) {
            if (!empty($item[$field])) {
                $parts[] = $item[$field];
            }
        }
        // ✅ IMPROVED: Return ID if no parts found
        return !empty($parts) ? implode(' - ', $parts) : '#' . $item['id'];
    }
    
    // Priority 4 - Fallback to common fields
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
        check_admin_referer('saw_create_' . $this->entity);
        
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

SAW_Logger::debug("AFTER CREATE - ID: $result");
SAW_Logger::debug("DB prefix: " . $GLOBALS['wpdb']->prefix);
$verify = $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare(
    "SELECT * FROM {$GLOBALS['wpdb']->prefix}saw_branches WHERE id = %d",
    $result
), ARRAY_A);
SAW_Logger::debug("Verify query result: " . ($verify ? 'FOUND' : 'NOT FOUND'));
if ($verify) {
    SAW_Logger::debug("Created data: " . print_r($verify, true));
}

$this->after_save($result);
        
        // ✅ NEW: AJAX inline create mode
        if (!empty($_POST['_ajax_inline_create'])) {
            $item = $this->model->get_by_id($result);
            wp_send_json_success(array(
                'id' => $result,
                'name' => $this->get_display_name($item),
            ));
        }
        
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
        check_admin_referer('saw_edit_' . $this->entity);
        
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
 * Kontroluje, zda má aktuální uživatel přístup k modulu (akce "list").
 * Primárně podle SAW rolí / SAW_Permissions, sekundárně podle WP capabilities.
 *
 * @since 1.0.0
 * @return void
 */
    protected function verify_module_access() {
    // Nejprve si zjistíme modul a roli
    $module = $this->entity 
        ?? ($this->config['entity'] ?? ($this->config['route'] ?? ''));

    // Fallback WP capability z configu
    $list_cap = $this->config['capabilities']['list'] ?? 'read';

    // 1) WordPress super admin / capability override
    //    Pokud má uživatel manage_options, necháme ho vždy projít.
    if (current_user_can('manage_options')) {
        return;
    }

    // 2) SAW permissions – primární logika
    $has_permission = false;

    if ($module && class_exists('SAW_Context')) {
        $role = SAW_Context::get_role();
        if (empty($role)) {
            $role = 'guest';
        }

        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }

        if (class_exists('SAW_Permissions')) {
            // pro vstup na seznam používáme logickou akci "list"
            $has_permission = SAW_Permissions::check($role, $module, 'list');
        }
    }

    // 3) Fallback – WordPress capability z configu
    if (!$has_permission && !empty($list_cap) && current_user_can($list_cap)) {
        $has_permission = true;
    }

    if (!$has_permission) {
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
 * Kontroluje oprávnění k akci (list, view, create, edit, delete).
 * Primárně SAW_Permissions, fallback na WP capabilities.
 *
 * @since 1.0.0
 * @param string $action Action name (list, view, create, edit, delete)
 * @return bool True if user can perform action
 */
protected function can($action) {
    // 1) WordPress super admin override
    if (current_user_can('manage_options')) {
        return true;
    }

    // 2) SAW role + SAW_Permissions (primární kontrola)
    $module = $this->entity 
        ?? ($this->config['entity'] ?? ($this->config['route'] ?? ''));

    if ($module && class_exists('SAW_Context')) {
        $role = SAW_Context::get_role();
        if (empty($role)) {
            $role = 'guest';
        }

        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }

        if (class_exists('SAW_Permissions')) {
            if (SAW_Permissions::check($role, $module, $action)) {
                return true;
            }
        }
    }

    // 3) Fallback – WordPress capability podle configu
    $capability = $this->config['capabilities'][$action] ?? null;

    if (!empty($capability) && current_user_can($capability)) {
        return true;
    }

    return false;
}


    /**
     * Determine if RBAC (SAW_Permissions) is available
     *
     * @since 12.4.0
     * @return bool
     */
    protected function use_rbac() {
        return function_exists('saw_can') || class_exists('SAW_Permissions');
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
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (!empty($this->config['has_customer_isolation'])) {
            $current_customer_id = $this->get_current_customer_id();
            
            if (!$current_customer_id) {
                return new WP_Error('no_customer', __('Není nastaven zákazník', 'saw-visitors'));
            }
            
            if (!empty($data['customer_id']) && (int)$data['customer_id'] !== (int)$current_customer_id) {
                return new WP_Error('customer_mismatch', __('Nemáte oprávnění k tomuto zákazníkovi', 'saw-visitors'));
            }
        }
        
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
            return null;
        }
        
        $role = $this->get_current_user_role();
        
        if ($role === 'super_manager') {
            if (!class_exists('SAW_User_Branches')) {
                require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-user-branches.php';
            }
            
            $saw_user_id = class_exists('SAW_Context') ? SAW_Context::get_saw_user_id() : 0;
            return $saw_user_id ? SAW_User_Branches::get_branch_ids_for_user($saw_user_id) : [];
        }
        
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
            return $where;
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
            return $where;
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
            return true;
        }
        
        if (!empty($this->config['has_customer_isolation'])) {
            $current_customer_id = $this->get_current_customer_id();
            
            if (!$current_customer_id || (int)$item['customer_id'] !== (int)$current_customer_id) {
                return false;
            }
        }
        
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
     * Render flash messages as toast notifications
     *
     * Displays and clears flash messages from session using toast notifications.
     *
     * @since 12.3.0
     * @return void
     */
    protected function render_flash_messages() {
        if (!class_exists('SAW_Session_Manager')) {
            return;
        }
        
        $session = SAW_Session_Manager::instance();
        
        if ($session->has('flash_success')) {
            $message = $session->get('flash_success');
            echo '<script>jQuery(document).ready(function() { sawShowToast(' . wp_json_encode($message) . ', "success"); });</script>';
            $session->unset('flash_success');
        }
        
        if ($session->has('flash_error')) {
            $message = $session->get('flash_error');
            echo '<script>jQuery(document).ready(function() { sawShowToast(' . wp_json_encode($message) . ', "danger"); });</script>';
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
     * Load lookup data from config definition
     * 
     * Universal method that loads lookup tables defined in module config.
     * Automatically caches results and formats as associative array indexed by ID.
     * 
     * @since 12.0.0
     * @param string $key Lookup key from config (e.g. 'account_types')
     * @return array Loaded data indexed by ID
     */
    protected function load_lookup_from_config($key) {
        if (empty($this->config['lookup_tables'][$key])) {
            return array();
        }
        
        $lookup_config = $this->config['lookup_tables'][$key];
        
        return $this->lazy_load_lookup_data($key, function() use ($lookup_config) {
            global $wpdb;
            
            $table = $wpdb->prefix . $lookup_config['table'];
            $fields = implode(', ', $lookup_config['fields']);
            $where = !empty($lookup_config['where']) ? 'WHERE ' . $lookup_config['where'] : '';
            $order = !empty($lookup_config['order']) ? 'ORDER BY ' . $lookup_config['order'] : '';
            
            $query = "SELECT {$fields} FROM {$table} {$where} {$order}";
            $results = $wpdb->get_results($query, ARRAY_A);
            
            $data = array();
            foreach ($results as $row) {
                $id = $row['id'];
                $data[$id] = $row;
            }
            
            return $data;
        }, $lookup_config['cache_ttl'] ?? 3600);
    }
    
    /**
     * Load all lookup tables for sidebar forms
     * 
     * Automatically loads all lookup tables defined in config.
     * Called when sidebar is in create/edit mode.
     * 
     * @since 12.0.0
     * @return array All loaded lookup data
     */
    protected function load_sidebar_lookups() {
        $lookups = array();
        
        if (empty($this->config['lookup_tables'])) {
            return $lookups;
        }
        
        foreach ($this->config['lookup_tables'] as $key => $config) {
            $lookups[$key] = $this->load_lookup_from_config($key);
        }
        
        return $lookups;
    }
    
    /**
     * Universal AJAX sidebar loader
     * 
     * Handles AJAX sidebar loading for detail/edit modes.
     * Child controllers can override format_detail_data() for custom formatting.
     * 
     * @since 12.1.0 - FÁZE 2
     * @return void Outputs JSON
     */
    public function ajax_load_sidebar() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'detail');
        
        if (!in_array($mode, array('detail', 'edit'), true)) {
            wp_send_json_error(array('message' => 'Invalid mode'));
        }
        
        $required_permission = ($mode === 'detail') ? 'view' : 'edit';
        if (!$this->can($required_permission)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    'Nemáte oprávnění %s záznamy', 
                    $mode === 'detail' ? 'zobrazit' : 'upravovat'
                )
            ));
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(array(
                'message' => sprintf('%s nenalezen', $this->config['singular'] ?? 'Záznam')
            ));
        }
        
        ob_start();
        
        $related_data = null;
        
        if ($mode === 'edit') {
            $lookups = $this->load_sidebar_lookups();
            
            foreach ($lookups as $key => $data) {
                $this->config[$key] = $data;
            }
        }
        
        if ($mode === 'detail') {
            $related_data = $this->load_related_data($id);
        }
        
        $captured_junk = ob_get_clean();
        
        if (method_exists($this, 'format_detail_data')) {
            $item = $this->format_detail_data($item);
        }
        
        ob_start();
        
        if ($mode === 'detail') {
            $tab = 'overview';
            $config = $this->config;
            $entity = $this->entity;
            
            extract(compact('item', 'tab', 'config', 'entity', 'related_data'));
            
            $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/detail-sidebar.php';
            require $template_path;
        } else {
            $is_edit = true;
            $GLOBALS['saw_sidebar_form'] = true;
            
            $config = $this->config;
            $entity = $this->entity;
            
            $lookups = array();
            if (!empty($this->config['lookup_tables'])) {
                foreach ($this->config['lookup_tables'] as $key => $lookup_config) {
                    if (isset($this->config[$key])) {
                        $lookups[$key] = $this->config[$key];
                    }
                }
            }
            
            extract(array_merge(
                compact('item', 'is_edit', 'config', 'entity'),
                $lookups
            ));
            
            $form_path = $this->config['path'] . 'form-template.php';
            require $form_path;
            
            unset($GLOBALS['saw_sidebar_form']);
        }
        
        $sidebar_content = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $sidebar_content,
            'mode' => $mode,
            'id' => $id
        ));
    }
    
    /**
     * Universal list view renderer
     * 
     * Handles complete list page logic including sidebar modes.
     * Child controllers only need to call: $this->render_list_view();
     * 
     * @since 12.2.0 - FÁZE 3
     * @param array $options Optional overrides
     * @return void Outputs HTML
     */
    protected function render_list_view($options = array()) {
        $this->verify_module_access();
        $this->enqueue_assets();
        
        ob_start();
        
        $sidebar_context = $this->get_sidebar_context();
        $sidebar_mode = $sidebar_context['mode'] ?? null;
        
        $detail_item = null;
        $form_item = null;
        $detail_tab = $sidebar_context['tab'] ?? 'overview';
        $related_data = null;
        
        if ($sidebar_mode === 'detail') {
            $detail_item = $this->handle_detail_mode($sidebar_context);
            if ($detail_item) {
                $related_data = $this->load_related_data($sidebar_context['id']);
            }
        }
        elseif ($sidebar_mode === 'create') {
            $form_item = $this->handle_create_mode();
            if ($form_item === null) return;
        }
        elseif ($sidebar_mode === 'edit') {
            $form_item = $this->handle_edit_mode($sidebar_context);
            if ($form_item === null) return;
        }
        
        if (in_array($sidebar_mode, array('create', 'edit'))) {
            $lookups = $this->load_sidebar_lookups();
            foreach ($lookups as $key => $data) {
                $this->config[$key] = $data;
            }
        }
        
        $list_data = $this->get_list_data();
        
        $captured_junk = ob_get_clean();
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        $this->render_flash_messages();
        
        $template_vars = array_merge(
            $list_data,
            array(
                'config' => $this->config,
                'entity' => $this->entity,
                'detail_item' => $detail_item,
                'form_item' => $form_item,
                'sidebar_mode' => $sidebar_mode,
                'detail_tab' => $detail_tab,
                'related_data' => $related_data,
            ),
            $options
        );
        
        if (!empty($this->config['lookup_tables'])) {
            foreach ($this->config['lookup_tables'] as $key => $lookup_config) {
                if (isset($this->config[$key])) {
                    $template_vars[$key] = $this->config[$key];
                }
            }
        }
        
        extract($template_vars);
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Handle detail sidebar mode
     * 
     * @since 12.2.0
     * @param array $context Sidebar context
     * @return array|null Item data or null on error
     */
    protected function handle_detail_mode($context) {
    // 🔥 MEGA DEBUG
    error_log("=== HANDLE_DETAIL_MODE START ===");
    error_log("Context received: " . print_r($context, true));
    error_log("Context ID: " . ($context['id'] ?? 'MISSING'));
    error_log("Context ID type: " . gettype($context['id'] ?? null));
    
    if (!$this->can('view')) {
        $this->set_flash('Nemáte oprávnění zobrazit detail', 'error');
        wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
        exit;
    }
    
    error_log("Calling get_by_id with ID: " . $context['id']);
    error_log("Model class: " . get_class($this->model));

    
    // Add direct DB check
    global $wpdb;
    $direct_check = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
        $context['id']
    ), ARRAY_A);
    error_log("Direct DB check result: " . ($direct_check ? 'FOUND' : 'NOT FOUND'));
    if ($direct_check) {
        error_log("Direct DB data: " . print_r($direct_check, true));
    }
    
    $item = $this->model->get_by_id($context['id']);
    
    error_log("Model get_by_id result: " . ($item ? 'FOUND' : 'NOT FOUND'));
    if ($item) {
        error_log("Model data: " . print_r($item, true));
    }
    error_log("=== HANDLE_DETAIL_MODE END ===");
    
    if (!$item) {
        $this->set_flash('Záznam nenalezen', 'error');
        wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
        exit;
    }
    
    if (method_exists($this, 'format_detail_data')) {
        $item = $this->format_detail_data($item);
    }
    
    return $item;
}
    
    /**
     * Handle create sidebar mode
     * 
     * @since 12.2.0
     * @return array|null Empty array for new form, null if POST processed
     */
    protected function handle_create_mode() {
        if (!$this->can('create')) {
            $this->set_flash('Nemáte oprávnění vytvářet záznamy', 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_create_post();
            return null;
        }
        
        return array();
    }
    
    /**
     * Handle edit sidebar mode
     * 
     * @since 12.2.0
     * @param array $context Sidebar context
     * @return array|null Item data or null if POST processed
     */
    protected function handle_edit_mode($context) {
        if (!$this->can('edit')) {
            $this->set_flash('Nemáte oprávnění upravovat záznamy', 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
            exit;
        }
        
        $item = $this->model->get_by_id($context['id']);
        
        if (!$item) {
            $this->set_flash('Záznam nenalezen', 'error');
            wp_redirect(home_url('/admin/' . $this->config['route'] . '/'));
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_edit_post($context['id']);
            return null;
        }
        
        return $item;
    }
    
    /**
     * Get list data (items, pagination)
     * 
     * @since 12.2.0
     * @return array List data
     */
    protected function get_list_data() {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = $this->config['list_config']['per_page'] ?? 20;
        
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => $per_page,
        );
        
        if (!empty($this->config['list_config']['filters'])) {
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                if ($enabled && isset($_GET[$filter_key])) {
                    $filters[$filter_key] = sanitize_text_field(wp_unslash($_GET[$filter_key]));
                }
            }
        }
        
        $data = $this->model->get_all($filters);
        
        $result = array(
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $page,
            'total_pages' => ceil($data['total'] / $per_page),
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
        );
        
        foreach ($_GET as $key => $value) {
            if (!in_array($key, array('s', 'orderby', 'order', 'paged'))) {
                $result[$key] = sanitize_text_field(wp_unslash($value));
            }
        }
        
        return $result;
    }
    
    /**
     * Index action
     *
     * Main list view. Must be implemented by child classes.
     *
     * @since 1.0.0
     * @return void
     */

/**
     * AJAX: Load nested sidebar for inline create
     * 
     * Loads form sidebar for target module with prefill data.
     * Returns HTML for nested sidebar display.
     * 
     * @since 13.0.0
     * @return void (JSON response)
     */
    public function ajax_load_nested_sidebar() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('create')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $target_module = sanitize_key($_POST['target_module'] ?? '');
        $prefill = $_POST['prefill'] ?? array();
        
        if (empty($target_module)) {
            wp_send_json_error(array('message' => 'Chybí cílový modul'));
        }
        
        // Load target controller
        $controller_class = 'SAW_Module_' . ucfirst($target_module) . '_Controller';
        $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$target_module}/controller.php";
        
        if (!file_exists($controller_file)) {
            wp_send_json_error(array('message' => 'Modul nenalezen'));
        }
        
        require_once $controller_file;
        
        if (!class_exists($controller_class)) {
            wp_send_json_error(array('message' => 'Controller nenalezen'));
        }
        
        $controller = new $controller_class();
        
        // Prepare prefill item
        $form_item = array();
        foreach ($prefill as $key => $value) {
            $form_item[$key] = sanitize_text_field($value);
        }
        
        // Set nested flag
        $GLOBALS['saw_nested_inline_create'] = true;
        
        // Render sidebar
        ob_start();
        
        $entity = $target_module;
        $config = $controller->config;
        $item = $form_item;
        $is_edit = false;
        
        // Load lookups for form
        if (method_exists($controller, 'load_sidebar_lookups')) {
            $lookups = $controller->load_sidebar_lookups();
            foreach ($lookups as $key => $data) {
                $$key = $data;
            }
        }
        
        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/form-sidebar.php';
        
        $html = ob_get_clean();
        
        unset($GLOBALS['saw_nested_inline_create']);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Get display name for created item
     * 
     * Override in child controllers for custom display names.
     * Used by inline create to populate dropdown option text.
     * 
     * @since 13.0.0
     * @param array $item Created item data
     * @return string Display name
     */
    protected function get_display_name($item) {
        // Try common name fields
        if (!empty($item['name'])) {
            return $item['name'];
        }
        
        if (!empty($item['first_name']) && !empty($item['last_name'])) {
            return trim($item['first_name'] . ' ' . $item['last_name']);
        }
        
        if (!empty($item['title'])) {
            return $item['title'];
        }
        
        // Fallback to ID
        return '#' . $item['id'];
    }

    abstract public function index();
}
