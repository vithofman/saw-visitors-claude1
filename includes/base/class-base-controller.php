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
    /**
     * Lazy load lookup data with caching
     *
     * ✅ REFACTORED: Uses SAW_Cache instead of transients
     *
     * @param string   $key       Lookup data key
     * @param callable $callback  Function to load data from DB
     * @param int      $cache_ttl Cache time-to-live in seconds
     * @return mixed Loaded data
     */
    protected function lazy_load_lookup_data($key, $callback, $cache_ttl = 3600) {
        $cache_key = 'lookup_' . $this->entity . '_' . $key;
        
        return SAW_Cache::remember(
            $cache_key,
            $callback,
            $cache_ttl,
            'lookups'
        );
    }
    
    /**
     * Invalidate lookup cache
     *
     * ✅ REFACTORED: Uses SAW_Cache
     *
     * @param string $key Lookup data key
     * @return void
     */
    protected function invalidate_lookup_cache($key) {
        $cache_key = 'lookup_' . $this->entity . '_' . $key;
        SAW_Cache::delete($cache_key, 'lookups');
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
    /**
     * Load related data for detail sidebar
     *
     * ✅ OPTIMIZED: Batch loads all relations to prevent N+1 queries
     * ✅ USES: Single query per table with proper grouping
     *
     * @param int $item_id Current item ID
     * @return array|null Related data grouped by relation key
     */
    protected function load_related_data($item_id) {
        $relations = $this->load_relations_config();
        
        if (empty($relations)) {
            return null;
        }
        
        global $wpdb;
        
        // ✅ STEP 1: Group relations by table to enable batch loading
        $tables_to_load = array();
        
        foreach ($relations as $key => $relation) {
            if (empty($relation['entity'])) {
                continue;
            }
            
            $table = $wpdb->prefix . 'saw_' . $relation['entity'];
            
            if (!isset($tables_to_load[$table])) {
                $tables_to_load[$table] = array();
            }
            
            $tables_to_load[$table][] = array(
                'key' => $key,
                'relation' => $relation,
            );
        }
        
        // ✅ STEP 2: Load all related data with single query per table
        $related_data = array();
        
        foreach ($tables_to_load as $table => $relation_group) {
            $relation = $relation_group[0]['relation'];
            $order_by = $relation['order_by'] ?? 'id DESC';
            
            // Handle junction table (many-to-many)
            if (!empty($relation['junction_table'])) {
                $junction_table = $wpdb->prefix . $relation['junction_table'];
                $foreign_key = $relation['foreign_key'];
                $local_key = $relation['local_key'];
                
                $query = $wpdb->prepare(
                    "SELECT e.*, j.{$foreign_key} as _parent_id
                     FROM {$table} e
                     INNER JOIN {$junction_table} j ON e.id = j.{$local_key}
                     WHERE j.{$foreign_key} = %d
                     ORDER BY e.{$order_by}",
                    $item_id
                );
            } 
            // Handle direct foreign key
            else {
                $foreign_key = $relation['foreign_key'];
                
                $query = $wpdb->prepare(
                    "SELECT *, {$foreign_key} as _parent_id
                     FROM {$table} 
                     WHERE {$foreign_key} = %d 
                     ORDER BY {$order_by}",
                    $item_id
                );
            }
            
            $items = $wpdb->get_results($query, ARRAY_A);
            
            // ✅ STEP 3: Distribute items to their relation keys
            foreach ($relation_group as $group_item) {
                $key = $group_item['key'];
                $rel = $group_item['relation'];
                
                $formatted_items = array();
                
                foreach ($items as $item) {
                    $display_text = $this->format_related_item_display($item, $rel);
                    
                    $formatted_items[] = array(
                        'id' => $item['id'],
                        'display' => $display_text,
                        'raw' => $item,
                    );
                }
                
                if (!empty($formatted_items)) {
                    $related_data[$key] = array(
                        'label' => $rel['label'],
                        'icon' => $rel['icon'] ?? '📋',
                        'entity' => $rel['entity'],
                        'items' => $formatted_items,
                        'route' => $rel['route'],
                        'count' => count($formatted_items),
                    );
                }
            }
        }
        
        return empty($related_data) ? null : $related_data;
    }
    
        /**
     * Format related item display text
     *
     * Supports multiple display strategies in order of priority:
     * 1. custom_display function - for complex formatting
     * 2. display_format - template with placeholders like {name}, {email}
     * 3. display_field - single field
     * 4. display_fields - array of fields joined with ' - '
     * 5. Fallback to common fields (name, title, id)
     *
     * @param array $item     Item data
     * @param array $relation Relation config
     * @return string Display text
     */
    protected function format_related_item_display($item, $relation) {
        // Priority 1 - Custom display function
        if (!empty($relation['custom_display']) && is_callable($relation['custom_display'])) {
            return call_user_func($relation['custom_display'], $item);
        }
        
        // Priority 2 - Display format with placeholders
        if (!empty($relation['display_format'])) {
            $format = $relation['display_format'];
            
            // Replace placeholders like {name}, {email}, etc.
            $display = preg_replace_callback('/\{(\w+)\}/', function($matches) use ($item) {
                $field = $matches[1];
                return $item[$field] ?? '';
            }, $format);
            
            return $display;
        }
        
        // Priority 3 - Single display field
        if (!empty($relation['display_field'])) {
            $field = $relation['display_field'];
            return $item[$field] ?? '#' . $item['id'];
        }
        
        // Priority 4 - Multiple display fields
        if (!empty($relation['display_fields']) && is_array($relation['display_fields'])) {
            $parts = array();
            foreach ($relation['display_fields'] as $field) {
                if (!empty($item[$field])) {
                    $parts[] = $item[$field];
                }
            }
            return !empty($parts) ? implode(' - ', $parts) : '#' . $item['id'];
        }
        
        // Priority 5 - Fallback to common fields
        if (!empty($item['name'])) {
            return $item['name'];
        }
        
        foreach ($item as $key => $value) {
            if ($key !== 'id' && $key !== '_parent_id' && !empty($value)) {
                return $value;
            }
        }
        
        return 'Item #' . $item['id'];
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
                $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
                wp_redirect(home_url('/admin/' . $route . '/'));
                exit;
            }
            
            $detail_item = $this->model->get_by_id($ctx['id']);
            
            if (!$detail_item) {
                $this->set_flash('Záznam nenalezen', 'error');
                $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
                wp_redirect(home_url('/admin/' . $route . '/'));
                exit;
            }
            
            if (method_exists($this, 'load_detail_related_data')) {
                $detail_item = $this->load_detail_related_data($detail_item, $detail_tab);
            }
        }
        elseif ($ctx['mode'] === 'create') {
            if (!$this->can('create')) {
                $this->set_flash('Nemáte oprávnění vytvářet záznamy', 'error');
                $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
                wp_redirect(home_url('/admin/' . $route . '/'));
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
                $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
                wp_redirect(home_url('/admin/' . $route . '/'));
                exit;
            }
            
            $form_item = $this->model->get_by_id($ctx['id']);
            
            if (!$form_item) {
                $this->set_flash('Záznam nenalezen', 'error');
                $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
                wp_redirect(home_url('/admin/' . $route . '/'));
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
        // ✅ CRITICAL: Check for inline create FIRST, before nonce validation
        // This handles cases where form is submitted as normal POST but should be AJAX
        if (!empty($_POST['_ajax_inline_create'])) {
            // Inline create mode - should be AJAX but handle gracefully if not
            if (!wp_doing_ajax() && method_exists($this, 'ajax_inline_create')) {
                // Normal POST with _ajax_inline_create - call ajax_inline_create() directly
                // This provides server-side fallback when JS fails to intercept submission
                // ajax_inline_create() will handle its own nonce check and return JSON
                $this->ajax_inline_create();
                return; // ajax_inline_create() will output JSON and exit
            }
            
            // If wp_doing_ajax() is true, this is a proper AJAX request
            // Use ajax nonce and continue with normal flow
            // The _ajax_inline_create check at the end will handle JSON response
            check_ajax_referer('saw_ajax_nonce', 'nonce');
        }
        
        // Standard nonce validation for normal form submissions
        // For AJAX sidebar submit, use check_ajax_referer
        if (!empty($_POST['_ajax_sidebar_submit'])) {
            check_ajax_referer('saw_ajax_nonce', 'nonce');
        } elseif (empty($_POST['_ajax_inline_create'])) {
            // Only check admin referer if not inline create
            check_admin_referer('saw_create_' . $this->entity);
        }
        
        $data = $this->prepare_form_data($_POST);
        
        $scope_validation = $this->validate_scope_access($data, 'create');
        if (is_wp_error($scope_validation)) {
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => $scope_validation->get_error_message(),
                    'errors' => array($scope_validation->get_error_message())
                ));
            }
            $this->set_flash($scope_validation->get_error_message(), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/create'));
            exit;
        }
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => $data->get_error_message(),
                    'errors' => array($data->get_error_message())
                ));
            }
            $this->set_flash($data->get_error_message(), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/create'));
            exit;
        }
        
        $validation = $this->model->validate($data);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => implode('<br>', $errors),
                    'errors' => $errors
                ));
            }
            $this->set_flash(implode('<br>', $errors), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/create'));
            exit;
        }
        
        $result = $this->model->create($data);

        if (is_wp_error($result)) {
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message(),
                    'errors' => array($result->get_error_message())
                ));
            }
            $this->set_flash($result->get_error_message(), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/create'));
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
        
        // ✅ NEW: AJAX sidebar submit mode (for sidebar forms)
        if (!empty($_POST['_ajax_sidebar_submit'])) {
            wp_send_json_success(array(
                'id' => $result,
                'message' => $this->config['singular'] . ' byl úspěšně vytvořen'
            ));
        }
        
        // ✅ NEW: AJAX inline create mode (for nested inline create)
        // This should only be reached if wp_doing_ajax() is true
        if (!empty($_POST['_ajax_inline_create'])) {
            // Only send JSON if this is actually an AJAX request
            if (wp_doing_ajax()) {
                $item = $this->model->get_by_id($result);
                wp_send_json_success(array(
                    'id' => $result,
                    'name' => $this->get_display_name($item),
                ));
            } else {
                // Fallback: should have been caught earlier, but handle gracefully
                wp_send_json_error(array(
                    'message' => 'Požadavek musí být odeslán přes AJAX'
                ));
            }
            return;
        }
        
        // Normal redirect after create
        $this->set_flash($this->config['singular'] . ' byl úspěšně vytvořen', 'success');
        
        // Fix URL construction to prevent double slashes
        $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
        $redirect_url = home_url('/admin/' . $route . '/' . $result . '/');
        wp_redirect($redirect_url);
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
        // For AJAX sidebar submit, use check_ajax_referer
        if (!empty($_POST['_ajax_sidebar_submit'])) {
            check_ajax_referer('saw_ajax_nonce', 'nonce');
        } else {
            check_admin_referer('saw_edit_' . $this->entity);
        }
        
        $data = $this->prepare_form_data($_POST);
        $data['id'] = $id;
        
        $scope_validation = $this->validate_scope_access($data, 'edit');
        if (is_wp_error($scope_validation)) {
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => $scope_validation->get_error_message(),
                    'errors' => array($scope_validation->get_error_message())
                ));
            }
            $this->set_flash($scope_validation->get_error_message(), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/' . $id . '/edit'));
            exit;
        }
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => $data->get_error_message(),
                    'errors' => array($data->get_error_message())
                ));
            }
            $this->set_flash($data->get_error_message(), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/' . $id . '/edit'));
            exit;
        }
        
        $validation = $this->model->validate($data, $id);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => implode('<br>', $errors),
                    'errors' => $errors
                ));
            }
            $this->set_flash(implode('<br>', $errors), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/' . $id . '/edit'));
            exit;
        }
        
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            // Check for AJAX sidebar submit
            if (!empty($_POST['_ajax_sidebar_submit'])) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message(),
                    'errors' => array($result->get_error_message())
                ));
            }
            $this->set_flash($result->get_error_message(), 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/' . $id . '/edit'));
            exit;
        }
        
        $this->after_save($id);
        
        // ✅ NEW: AJAX sidebar submit mode (for sidebar forms)
        if (!empty($_POST['_ajax_sidebar_submit'])) {
            wp_send_json_success(array(
                'id' => $id,
                'message' => $this->config['singular'] . ' byl úspěšně aktualizován'
            ));
        }
        
        $this->set_flash($this->config['singular'] . ' byl úspěšně aktualizován', 'success');
        $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
        wp_redirect(home_url('/admin/' . $route . '/' . $id . '/'));
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
     * REMOVED v6.0.0: Sidebar now renders as full page via router, not AJAX.
     * 
     * @deprecated 6.0.0 - No longer used, sidebar renders as full page
     * @return void
     */
    public function ajax_load_sidebar() {
        // Method removed - sidebar now renders as full page via router
        wp_send_json_error(array('message' => 'AJAX sidebar loading is no longer supported. Use full page navigation.'));
        saw_verify_ajax_unified();
        
        $id = intval($_POST['id'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'detail');
        
        if (!in_array($mode, array('detail', 'edit', 'create'), true)) {
            wp_send_json_error(array('message' => 'Invalid mode'));
        }
        
        // For create mode, id is not required
        if ($mode === 'create') {
            $id = 0;
        }
        
        // Permission check
        $required_permission = ($mode === 'detail') ? 'view' : (($mode === 'edit') ? 'edit' : 'create');
        if (!$this->can($required_permission)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    'Nemáte oprávnění %s záznamy', 
                    $mode === 'detail' ? 'zobrazit' : ($mode === 'edit' ? 'upravovat' : 'vytvářet')
                )
            ));
        }
        
        // For detail and edit modes, we need an item
        $item = null;
        if ($mode !== 'create') {
            $item = $this->model->get_by_id($id);
            
            if (!$item) {
                wp_send_json_error(array(
                    'message' => sprintf('%s nenalezen', $this->config['singular'] ?? 'Záznam')
                ));
            }
        }
        
        ob_start();
        
        $related_data = null;
        
        if ($mode === 'edit' || $mode === 'create') {
            $lookups = $this->load_sidebar_lookups();
            
            foreach ($lookups as $key => $data) {
                $this->config[$key] = $data;
            }
        }
        
        if ($mode === 'detail') {
            $related_data = $this->load_related_data($id);
        }
        
        $captured_junk = ob_get_clean();
        
        if ($mode === 'detail' && method_exists($this, 'format_detail_data')) {
            $item = $this->format_detail_data($item);
        }
        
        ob_start();
        
        if ($mode === 'detail') {
            $tab = 'overview';
            $config = $this->config;
            $entity = $this->entity;
            
            // Set controller instance in global for detail-sidebar.php to use
            global $saw_current_controller;
            $saw_current_controller = $this;
            
            // Allow modules to set header_meta via format_detail_data or get_detail_header_meta method
            // This way modules can customize header badges without rendering header themselves
            // Always call get_detail_header_meta if method exists, it can override existing header_meta
            if (method_exists($this, 'get_detail_header_meta')) {
                $header_meta = $this->get_detail_header_meta($item);
                if (!empty($header_meta)) {
                    $item['header_meta'] = $header_meta;
                }
            }
            
            extract(compact('item', 'tab', 'config', 'entity', 'related_data'));
            
            $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/detail-sidebar.php';
            require $template_path;
            
            // Clean up global
            unset($GLOBALS['saw_current_controller']);
        } else {
            // Edit or Create mode - render form in sidebar
            $is_edit = ($mode === 'edit');
            $GLOBALS['saw_sidebar_form'] = true;
            
            $config = $this->config;
            $entity = $this->entity;
            
            // For create mode, use empty item
            if (!$is_edit) {
                $item = array();
            }
            
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
            
            // Render form first
            $form_path = $this->config['path'] . 'form-template.php';
            require $form_path;
            
            $form_html = ob_get_clean();
            
            unset($GLOBALS['saw_sidebar_form']);
            
            // For both edit and create modes, use form-sidebar template (unified structure)
            ob_start();
            require SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/form-sidebar.php';
        }
        
        $sidebar_content = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $sidebar_content,
            'mode' => $mode,
            'id' => $id
        ));
    }
    
    /**
     * AJAX handler for create form submit
     * 
     * Called via AJAX from sidebar form.
     * 
     * @since 7.2.0
     * @return void Outputs JSON
     */
    public function ajax_create() {
        if (!$this->can('create')) {
            wp_send_json_error(array(
                'message' => 'Nemáte oprávnění vytvářet záznamy'
            ));
        }
        
        // Set flag for AJAX sidebar submit (before calling handle_create_post)
        $_POST['_ajax_sidebar_submit'] = 1;
        
        // Call handle_create_post which will detect AJAX mode and return JSON
        $this->handle_create_post();
    }
    
    /**
     * AJAX handler for edit form submit
     * 
     * Called via AJAX from sidebar form.
     * 
     * @since 7.2.0
     * @return void Outputs JSON
     */
    public function ajax_edit() {
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            wp_send_json_error(array(
                'message' => 'Chybí ID záznamu'
            ));
        }
        
        if (!$this->can('edit')) {
            wp_send_json_error(array(
                'message' => 'Nemáte oprávnění upravovat záznamy'
            ));
        }
        
        // Set flag for AJAX sidebar submit (before calling handle_edit_post)
        $_POST['_ajax_sidebar_submit'] = 1;
        
        // Call handle_edit_post which will detect AJAX mode and return JSON
        $this->handle_edit_post($id);
    }
    
    /**
     * AJAX handler for getting previous/next record ID
     * 
     * Returns the ID of the previous or next record based on current filters
     * (customer_id, branch_id) with circular navigation.
     * 
     * @since 7.0.0
     * @return void Outputs JSON
     */
    public function ajax_get_adjacent_id() {
        saw_verify_ajax_unified();
        
        if (!$this->can('view')) {
            wp_send_json_error(array(
                'message' => 'Nemáte oprávnění zobrazit záznamy'
            ));
        }
        
        $current_id = intval($_POST['id'] ?? 0);
        $direction = sanitize_text_field($_POST['direction'] ?? 'next'); // 'next' or 'prev'
        
        if (!$current_id) {
            wp_send_json_error(array(
                'message' => 'Chybí ID záznamu'
            ));
        }
        
        if (!in_array($direction, array('next', 'prev'))) {
            wp_send_json_error(array(
                'message' => 'Neplatný směr navigace'
            ));
        }
        
        // Get context filters
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        // Get current record to determine its position
        $current_item = $this->model->get_by_id($current_id);
        if (!$current_item) {
            wp_send_json_error(array(
                'message' => 'Záznam nenalezen'
            ));
        }
        
        // Build query to get all IDs with same filters
        global $wpdb;
        $table = $this->model->table ?? $wpdb->prefix . $this->config['table'];
        
        $where = array('1=1');
        $where_values = array();
        
        // Filter by customer_id if set
        if ($customer_id) {
            $where[] = 'customer_id = %d';
            $where_values[] = $customer_id;
        }
        
        // Filter by branch_id if set
        if ($branch_id) {
            $where[] = 'branch_id = %d';
            $where_values[] = $branch_id;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get all IDs ordered by ID (or name if available)
        $order_by = 'id ASC';
        if ($this->model_has_column('name')) {
            $order_by = 'name ASC, id ASC';
        } elseif ($this->model_has_column('title')) {
            $order_by = 'title ASC, id ASC';
        }
        
        // Build query safely - use %i for table name
        $query = "SELECT id FROM %i WHERE {$where_clause} ORDER BY {$order_by}";
        $query_params = array_merge(array($table), $where_values);
        $query = $wpdb->prepare($query, $query_params);
        
        $ids = $wpdb->get_col($query);
        
        if (empty($ids)) {
            wp_send_json_error(array(
                'message' => 'Žádné záznamy nenalezeny'
            ));
        }
        
        // Find current position
        $current_index = array_search($current_id, $ids);
        
        if ($current_index === false) {
            wp_send_json_error(array(
                'message' => 'Aktuální záznam není v seznamu'
            ));
        }
        
        // Get adjacent ID with circular navigation
        $adjacent_id = null;
        
        if ($direction === 'next') {
            // Next: if last, go to first
            $adjacent_index = ($current_index + 1) % count($ids);
            $adjacent_id = $ids[$adjacent_index];
        } else {
            // Prev: if first, go to last
            $adjacent_index = ($current_index - 1 + count($ids)) % count($ids);
            $adjacent_id = $ids[$adjacent_index];
        }
        
        if (!$adjacent_id) {
            wp_send_json_error(array(
                'message' => 'Nepodařilo se najít sousední záznam'
            ));
        }
        
        // Build detail URL
        $route = $this->config['route'] ?? $this->entity;
        $detail_url = home_url('/admin/' . $route . '/' . $adjacent_id . '/');
        
        wp_send_json_success(array(
            'id' => $adjacent_id,
            'url' => $detail_url
        ));
    }
    
    /**
     * Check if model has a specific column
     * Helper for ajax_get_adjacent_id
     * 
     * @since 7.0.0
     * @param string $column Column name
     * @return bool
     */
    private function model_has_column($column) {
        if (!isset($this->model->table)) {
            return false;
        }
        
        global $wpdb;
        $table = $this->model->table;
        
        // Cache column check
        static $column_cache = array();
        $cache_key = $table . ':' . $column;
        
        if (isset($column_cache[$cache_key])) {
            return $column_cache[$cache_key];
        }
        
        $columns = $wpdb->get_col($wpdb->prepare("DESCRIBE %i", $table));
        $has_column = in_array($column, $columns);
        $column_cache[$cache_key] = $has_column;
        
        return $has_column;
    }
    
    /**
     * AJAX handler to get items for infinite scroll
     * 
     * @since 7.0.0
     * @return void Outputs JSON
     */
    public function ajax_get_items_infinite() {
        saw_verify_ajax_unified();
        
        if (!$this->can('list')) {
            wp_send_json_error(array(
                'message' => 'Nemáte oprávnění zobrazit záznamy'
            ));
        }
        
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        
        // OPRAVENO 2025-01-22: Respektuj initial_load config pro první stránku
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        if ($infinite_scroll_enabled && $page === 1) {
            $per_page = $this->config['infinite_scroll']['initial_load'] ?? 100;
        } else {
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        }
        
        // Ensure per_page is reasonable
        $per_page = max(1, min(100, $per_page));
        
        // ⭐ KRITICKÁ OPRAVA: Pro infinite scroll použij offset místo page-based offsetu
        // Pokud je infinite scroll enabled a máme loaded_count, použijeme ho jako offset
        // OPRAVENO: Použij loaded_count i když je page=1, pokud už máme načtené záznamy
        if ($infinite_scroll_enabled && isset($_POST['loaded_count'])) {
            $loaded_count = intval($_POST['loaded_count']);
            if ($loaded_count > 0) {
                // Použijeme vlastní offset místo page-based offsetu
                $filters = array(
                    'offset' => $loaded_count,
                    'page' => 1, // Reset page na 1, protože použijeme vlastní offset
                    'per_page' => $per_page,
                    'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
                    'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'id',
                    'order' => isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC',
                );
            } else {
                // Fallback na standardní page-based pagination (první načtení)
                $filters = array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
                    'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'id',
                    'order' => isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC',
                );
            }
        } else {
            // Standardní page-based pagination
            $filters = array(
                'page' => $page,
                'per_page' => $per_page,
                'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
                'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'id',
                'order' => isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC',
            );
        }
        
        // NOVÉ: Add TAB filter - POST contains filter_value directly from JS
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            
            // POST may contain filter_value directly from JS
            if (isset($_POST[$tab_param]) && $_POST[$tab_param] !== '') {
                $post_value = sanitize_text_field(wp_unslash($_POST[$tab_param]));
                
                // Use filter_value directly in SQL query
                $filters[$tab_param] = $post_value;
            }
        }
        
        // Add filter params, but skip tab_param if tabs are enabled
        if (!empty($this->config['list_config']['filters'])) {
            $tab_param = !empty($this->config['tabs']['enabled']) ? ($this->config['tabs']['tab_param'] ?? 'tab') : null;
            
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                // Skip tab_param filter as it's handled by tabs system
                if ($filter_key === $tab_param) {
                    continue;
                }
                
                if ($enabled && isset($_POST[$filter_key]) && $_POST[$filter_key] !== '') {
                    $filters[$filter_key] = sanitize_text_field($_POST[$filter_key]);
                }
            }
        }
        
        // Get data from model
        $data = $this->model->get_all($filters);
        
        // Get columns from POST (JSON) or use get_table_columns method
        // ⭐ FIX: Always prioritize get_table_columns() if it exists, because it contains callbacks
        // JSON columns don't have callbacks (can't be serialized), so we need the full config
        $columns = array();
        
        if (method_exists($this, 'get_table_columns')) {
            // Priority: use get_table_columns() - it contains callbacks and full config
            $columns = $this->get_table_columns();
        } elseif (isset($_POST['columns']) && !empty($_POST['columns'])) {
            // Fallback: use columns from JSON (without callbacks)
            $columns_json = wp_unslash($_POST['columns']);
            if (is_string($columns_json)) {
                $columns = json_decode($columns_json, true);
                if (!is_array($columns)) {
                    $columns = array();
                }
            } elseif (is_array($columns_json)) {
                $columns = $columns_json;
            }
        }
        
        // Last resort: generate from fields
        if (empty($columns) && !empty($this->config['fields'])) {
            foreach ($this->config['fields'] as $key => $field) {
                if (!empty($field['hidden'])) continue;
                $columns[$key] = array(
                    'label' => $field['label'] ?? ucfirst($key),
                    'type' => $this->map_field_type_to_column_type($field['type'] ?? 'text'),
                );
            }
        }
        
        // Sanitize columns
        if (!is_array($columns)) {
            $columns = array();
        }
        
        // Debug: Check columns decoding and badge configuration
        if (empty($columns)) {
            error_log('[Infinite Scroll] Columns empty after decode');
        } else {
            error_log('[Infinite Scroll] Columns decoded: ' . count($columns) . ' columns');
            // Check if badge columns have map and labels
            foreach ($columns as $key => $column) {
                if (isset($column['type']) && $column['type'] === 'badge') {
                    error_log('[Infinite Scroll] Badge column found: ' . $key . ' - map: ' . (isset($column['map']) && is_array($column['map']) ? count($column['map']) . ' items' : 'missing') . ', labels: ' . (isset($column['labels']) && is_array($column['labels']) ? count($column['labels']) . ' items' : 'missing'));
                    if (empty($column['map']) || empty($column['labels'])) {
                        error_log('[Infinite Scroll] Badge column missing map/labels: ' . $key . ' - map: ' . (isset($column['map']) ? 'exists' : 'missing') . ', labels: ' . (isset($column['labels']) ? 'exists' : 'missing'));
                    }
                }
            }
        }
        
        // Use admin-table component to render rows
        if (!class_exists('SAW_Component_Admin_Table')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
        }
        
        // Build table config for rendering
        // OPRAVENO: Přidat edit_url pro infinite scroll - akční menu se nevykreslovalo bez edit_url
        $base_url = home_url('/admin/' . ($this->config['route'] ?? $this->entity));
        $edit_url = $base_url . '/{id}/edit';
        $detail_url = $this->get_detail_url();
        $actions = $this->config['actions'] ?? array();
        
        $table_config = array(
            'columns' => $columns,
            'actions' => $actions,
            'detail_url' => $detail_url,
            'edit_url' => $edit_url, // OPRAVENO: Přidat edit_url pro akční menu
            'rows' => $data['items'],
            'module_config' => $this->config,
            'entity' => $this->entity, // OPRAVENO: Přidat entity pro render_action_buttons
        );
        
        $table = new SAW_Component_Admin_Table($this->entity, $table_config);
        
        // Render rows HTML - just the rows, not group headers
        // For infinite scroll, we append rows to existing groups
        $rows_html = '';
        if (!empty($data['items'])) {
            ob_start();
            foreach ($data['items'] as $row) {
                $this->render_infinite_scroll_row($row, $table, $columns);
            }
            $rows_html = ob_get_clean();
        }
        
        // ⭐ FIX: Správná logika pro has_more - počítá skutečně načtené záznamy
        // has_more = true pokud načteno méně než total
        // KRITICKÁ OPRAVA: Pro infinite scroll musíme správně počítat skutečně načtené záznamy
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        $items_loaded = count($data['items']);
        
        // OPRAVENO: Použij offset z filters, pokud je nastaven (infinite scroll s loaded_count)
        if ($infinite_scroll_enabled && isset($filters['offset'])) {
            // Offset je skutečný počet již načtených záznamů před tímto načtením
            $loaded_count = $filters['offset'] + $items_loaded;
        } elseif ($page === 1) {
            // Pro první stránku: skutečně načtené = items_loaded
            $loaded_count = $items_loaded;
        } else {
            // Standardní page-based výpočet
            $loaded_count = ($page - 1) * $per_page + $items_loaded;
        }
        
        // KRITICKÁ OPRAVA: has_more závisí pouze na tom, zda loaded_count < total
        $has_more = $loaded_count < $data['total'];
        
        wp_send_json_success(array(
            'html' => $rows_html,
            'has_more' => $has_more,
            'page' => $page,
            'total' => $data['total'],
            'loaded' => count($data['items'])
        ));
    }
    
    /**
     * Render a single row for infinite scroll
     * 
     * @since 7.0.0
     * @param array $row Row data
     * @param SAW_Component_Admin_Table $table Table component instance
     * @param array $columns Columns configuration
     * @return void Outputs HTML
     */
    private function render_infinite_scroll_row($row, $table, $columns) {
        $detail_url = $this->get_detail_url();
        if (!empty($detail_url) && !empty($row['id'])) {
            $detail_url = str_replace('{id}', intval($row['id']), $detail_url);
        } else {
            $detail_url = '';
        }
        
        // NOVÁ jednoduchá verze - no grouping
        $row_class = 'saw-table-row';
        
        if (!empty($detail_url)) {
            $row_class .= ' saw-clickable-row';
        }
        
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>" 
            data-id="<?php echo esc_attr($row['id'] ?? ''); ?>"
            <?php if (!empty($detail_url)): ?>
                data-detail-url="<?php echo esc_url($detail_url); ?>"
            <?php endif; ?>>
            
            <?php foreach ($columns as $key => $column): ?>
                <?php $table->render_table_cell_for_template($row, $key, $column); ?>
            <?php endforeach; ?>
        </tr>
        <?php
    }
    
    /**
     * Get detail URL template
     * 
     * @since 7.0.0
     * @return string Detail URL template
     */
    protected function get_detail_url() {
        $route = $this->config['route'] ?? $this->entity;
        return home_url('/admin/' . $route . '/{id}/');
    }
    
    /**
     * Get table columns configuration
     * Can be overridden by child controllers
     * 
     * @since 7.0.0
     * @return array Columns configuration
     */
    protected function get_table_columns() {
        // Try to get from a static cache or method
        // Child controllers can override this
        return array();
    }
    
    /**
     * Map field type to column type
     * 
     * @since 7.0.0
     * @param string $field_type Field type
     * @return string Column type
     */
    private function map_field_type_to_column_type($field_type) {
        $map = array(
            'text' => 'text',
            'email' => 'email',
            'textarea' => 'text',
            'select' => 'badge',
            'file' => 'image',
            'date' => 'date',
            'checkbox' => 'boolean',
            'number' => 'text',
        );
        return $map[$field_type] ?? 'text';
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
                
                // Set header_meta for detail sidebar
                if (method_exists($this, 'get_detail_header_meta')) {
                    $header_meta = $this->get_detail_header_meta($detail_item);
                    if (!empty($header_meta)) {
                        $detail_item['header_meta'] = $header_meta;
                    }
                }
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
                'controller' => $this,
                'model' => $this->model,
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
        if (!$this->can('view')) {
            $this->set_flash('Nemáte oprávnění zobrazit detail', 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/'));
            exit;
        }
        
        $item = $this->model->get_by_id($context['id']);
        
        if (!$item) {
            $this->set_flash('Záznam nenalezen', 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/'));
            exit;
        }
        
        if (method_exists($this, 'format_detail_data')) {
            $item = $this->format_detail_data($item);
        }
        
        // Set header_meta if method exists
        if (method_exists($this, 'get_detail_header_meta')) {
            $header_meta = $this->get_detail_header_meta($item);
            if (!empty($header_meta)) {
                $item['header_meta'] = $header_meta;
            }
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
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/'));
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
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/'));
            exit;
        }
        
        $item = $this->model->get_by_id($context['id']);
        
        if (!$item) {
            $this->set_flash('Záznam nenalezen', 'error');
            $route = !empty($this->config['route']) ? trim($this->config['route'], '/') : $this->entity;
            wp_redirect(home_url('/admin/' . $route . '/'));
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
        
        // OPRAVENO 2025-01-22: Respektuj initial_load config
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        
        if ($infinite_scroll_enabled) {
            // Pokud je první stránka, použij initial_load
            if ($page === 1) {
                $per_page = $this->config['infinite_scroll']['initial_load'] ?? 100;
            } else {
                // Další stránky používají per_page
                $per_page = $this->config['infinite_scroll']['per_page'] ?? 50;
            }
        } else {
            $per_page = $this->config['list_config']['per_page'] ?? 20;
        }
        
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => $per_page,
        );
        
        // Handle TAB filtering
        // URL NOW contains filter_value directly (e.g., ?is_archived=0)
        // We need to find which tab matches this filter_value
        $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            $url_value = isset($_GET[$tab_param]) ? sanitize_text_field(wp_unslash($_GET[$tab_param])) : null;
            
            // If URL has no parameter, it's "all" tab
            if ($url_value === null || $url_value === '') {
                $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
            } else {
                // URL contains filter_value, find matching tab_key
                $tab_found = false;
                foreach ($this->config['tabs']['tabs'] as $tab_key => $tab_config) {
                    // Compare filter_value with URL value (both as strings for consistency)
                    // Handle both INT (0, 1) and string ('0', '1') values
                    if ($tab_config['filter_value'] !== null && 
                        (string)$tab_config['filter_value'] === (string)$url_value) {
                        $current_tab = (string)$tab_key;
                        $tab_found = true;
                        break;
                    }
                }
                
                // If no tab found matching the URL value, default to "all"
                if (!$tab_found) {
                    $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
                }
            }
            
            // Apply filter to SQL query
            // URL value IS the filter_value, use it directly
            // Only apply if URL has a value (not "all" tab)
            if ($url_value !== null && $url_value !== '') {
                $filters[$tab_param] = $url_value;
            }
        }
        
        // Apply list_config filters, but skip tab_param if tabs are enabled
        // CRITICAL: Skip tab_param to prevent conflicts with tabs system
        if (!empty($this->config['list_config']['filters'])) {
            $tab_param_to_skip = !empty($this->config['tabs']['enabled']) ? 
                ($this->config['tabs']['tab_param'] ?? 'tab') : null;
            
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                // Skip tab_param filter as it's handled by tabs system above
                // This prevents "Všechny" tab from showing filtered results
                if ($filter_key === $tab_param_to_skip) {
                    continue;
                }
                
                // Only apply filter if it's enabled AND present in URL
                // Don't apply filters that aren't in the URL
                if ($enabled && isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
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
        
        // Add tab data if tabs are enabled
        if (!empty($this->config['tabs']['enabled'])) {
            // Ensure current_tab is always a valid string, never null or empty
            // CRITICAL: Always set a valid tab key, even if matching failed
            if (isset($current_tab) && $current_tab !== null && $current_tab !== '') {
                $result['current_tab'] = (string)$current_tab;
            } else {
                $result['current_tab'] = (string)($this->config['tabs']['default_tab'] ?? 'all');
            }
            
            // Get tab counts - this should return array of tab_key => count
            $result['tab_counts'] = $this->get_tab_counts();
        }
        
        foreach ($_GET as $key => $value) {
            if (!in_array($key, array('s', 'orderby', 'order', 'paged'))) {
                $result[$key] = sanitize_text_field(wp_unslash($value));
            }
        }
        
        return $result;
    }
    
    /**
     * Get tab counts for tabs navigation
     * 
     * Counts records for each tab based on tab configuration.
     * 
     * @since 7.1.0
     * @return array Tab key => count
     */
    protected function get_tab_counts() {
        if (empty($this->config['tabs']['enabled'])) {
            return array();
        }
        
        $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
        $tabs = $this->config['tabs']['tabs'] ?? array();
        $counts = array();
        
        foreach ($tabs as $tab_key => $tab_config) {
            if (empty($tab_config['count_query'])) {
                $counts[$tab_key] = 0;
                continue;
            }
            
            // Build filters for this tab
            $filters = array(
                'page' => 1,
                'per_page' => 1, // We only need count, not items
            );
            
            // Apply tab filter - use filter_value from tab config
            // This is the actual database value, not the tab key
            // Only apply if filter_value is not null (null = "all" tab shows everything)
            // Handle both INT (0, 1) and string values
            if ($tab_config['filter_value'] !== null && $tab_config['filter_value'] !== '') {
                // Preserve the original type (INT or string) for proper SQL comparison
                $filters[$tab_param] = $tab_config['filter_value'];
            }
            
            // Apply other existing filters from GET (search, etc.)
            $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            if (!empty($search)) {
                $filters['search'] = $search;
            }
            
            // Apply list_config filters (but exclude the tab_param to avoid conflicts)
            // CRITICAL: Skip tab_param to prevent double filtering
            if (!empty($this->config['list_config']['filters'])) {
                foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                    // Skip tab_param filter as we're applying it via filter_value above
                    if ($filter_key === $tab_param) {
                        continue;
                    }
                    // Only apply if filter is enabled AND present in URL
                    if ($enabled && isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                        $filters[$filter_key] = sanitize_text_field(wp_unslash($_GET[$filter_key]));
                    }
                }
            }
            
            // Model already has tabs config from constructor (loaded from config.php)
            // No need to access protected $config property
            
            // Get count from model
            // CRITICAL: Add unique parameter to ensure cache key is unique for each tab
            // This prevents cache collisions between different tab counts
            $count_filters = $filters;
            $count_filters['_tab_count'] = $tab_key; // Unique key for each tab to ensure separate cache
            
            // Call model to get count
            $data = $this->model->get_all($count_filters);
            $counts[$tab_key] = isset($data['total']) ? intval($data['total']) : 0;
        }
        
        return $counts;
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
        saw_verify_ajax_unified();
        
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
        
        // Set nested flag BEFORE enqueueing assets
        $GLOBALS['saw_nested_inline_create'] = true;
        
        // Enqueue assets for the target module (so isNested is set correctly)
        if (method_exists($controller, 'enqueue_assets')) {
            $controller->enqueue_assets();
        } elseif (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module($target_module);
        }
        
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
    public function get_display_name($item) {
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
