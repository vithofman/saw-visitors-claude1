<?php
/**
 * Base Model Class
 *
 * Parent class for all entity models.
 * Provides CRUD operations, scope filtering, caching, and validation.
 * Implements role-based data access control.
 *
 * @package    SAW_Visitors
 * @subpackage Base
 * @version    10.0.0 - SAW Table migration: backwards-compatible config normalization
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_Base_Model Class
 *
 * Abstract base model with scope-aware data access and caching.
 * All entity models must extend this class.
 *
 * @since 1.0.0
 */
abstract class SAW_Base_Model 
{
    /**
     * Database table name
     *
     * @since 1.0.0
     * @var string
     */
    protected $table;
    
    /**
     * Module configuration
     *
     * @since 1.0.0
     * @var array
     */
    protected $config;
    
    /**
     * Cache TTL in seconds
     *
     * @since 1.0.0
     * @var int
     */
    protected $cache_ttl = 300;
    
    /**
     * Allowed ORDER BY columns (whitelist for security)
     *
     * @since 5.4.0
     * @var array
     */
    protected $allowed_orderby = ['id', 'name', 'created_at', 'updated_at'];
    
    /**
     * Normalized list config cache
     *
     * @since 10.0.0
     * @var array|null
     */
    private $normalized_list_config = null;
    
    // =========================================================================
    // CONFIG NORMALIZATION (SAW Table Migration Support)
    // =========================================================================
    
    /**
     * Get normalized list configuration
     *
     * Supports both old format (list_config) and new SAW Table format (list).
     * This enables gradual migration to the new config format without breaking
     * existing modules.
     *
     * Old format (pre-SAW Table):
     *   'list_config' => ['searchable' => [...], 'per_page' => 50, ...]
     *
     * New format (SAW Table):
     *   'list' => ['searchable' => [...], 'per_page' => 50, ...]
     *
     * @since 10.0.0
     * @return array Normalized list configuration
     */
    protected function get_list_config() {
        // Return cached version if available
        if ($this->normalized_list_config !== null) {
            return $this->normalized_list_config;
        }
        
        // Priority: list_config (old) > list (new) > defaults
        if (!empty($this->config['list_config']) && is_array($this->config['list_config'])) {
            // Old format exists - use it directly
            $this->normalized_list_config = $this->config['list_config'];
        } elseif (!empty($this->config['list']) && is_array($this->config['list'])) {
            // New format - normalize to old structure
            $this->normalized_list_config = array(
                'per_page'           => $this->config['list']['per_page'] ?? 50,
                'searchable'         => $this->config['list']['searchable'] ?? array('name'),
                'sortable'           => $this->config['list']['sortable'] ?? array('name', 'created_at'),
                'default_orderby'    => $this->config['list']['default_orderby'] ?? 'id',
                'default_order'      => $this->config['list']['default_order'] ?? 'DESC',
                'filters'            => $this->config['list']['filters'] ?? array(),
                'enable_detail_modal'=> $this->config['list']['enable_detail_modal'] ?? true,
            );
        } else {
            // No list config - use defaults
            $this->normalized_list_config = array(
                'per_page'           => 50,
                'searchable'         => array('name'),
                'sortable'           => array('name', 'created_at'),
                'default_orderby'    => 'id',
                'default_order'      => 'DESC',
                'filters'            => array(),
                'enable_detail_modal'=> true,
            );
        }
        
        return $this->normalized_list_config;
    }
    
    /**
     * Get searchable fields
     *
     * @since 10.0.0
     * @return array List of searchable field names
     */
    protected function get_searchable_fields() {
        $list_config = $this->get_list_config();
        return $list_config['searchable'] ?? array('name');
    }
    
    /**
     * Get filter configuration
     *
     * @since 10.0.0
     * @return array Filter configuration
     */
    protected function get_filter_config() {
        $list_config = $this->get_list_config();
        return $list_config['filters'] ?? array();
    }
    
    /**
     * Get default order by column
     *
     * @since 10.0.0
     * @return string Default order by column
     */
    protected function get_default_orderby() {
        $list_config = $this->get_list_config();
        return $list_config['default_orderby'] ?? 'id';
    }
    
    /**
     * Get default order direction
     *
     * @since 10.0.0
     * @return string Default order direction (ASC or DESC)
     */
    protected function get_default_order() {
        $list_config = $this->get_list_config();
        return $list_config['default_order'] ?? 'DESC';
    }
    
    /**
     * Get per page setting
     *
     * @since 10.0.0
     * @return int Number of items per page
     */
    protected function get_per_page() {
        $list_config = $this->get_list_config();
        return intval($list_config['per_page'] ?? 50);
    }
    
    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================
    
    /**
     * Get all records with filters
     *
     * Returns paginated list with scope filtering applied.
     *
     * @since 1.0.0
     * @since 10.0.0 Uses normalized list config for backwards compatibility
     * @param array $filters Query filters
     * @return array ['items' => array, 'total' => int]
     */
    public function get_all($filters = []) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key_with_scope('list', $filters);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $sql = $wpdb->prepare("SELECT * FROM %i WHERE 1=1", $this->table);
        $params = [];
        
        // Apply data scope (customer/branch filtering)
        list($scope_where, $scope_params) = $this->apply_data_scope();
        if (!empty($scope_where)) {
            $sql .= $scope_where;
            $params = array_merge($params, $scope_params);
        }
        
        // Search filtering
        if (!empty($filters['search'])) {
            $search_fields = $this->get_searchable_fields();
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                if ($this->is_valid_column($field)) {
                    $search_conditions[] = "`{$field}` LIKE %s";
                }
            }
            
            if (!empty($search_conditions)) {
                $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
                
                foreach ($search_conditions as $condition) {
                    $params[] = $search_value;
                }
                
                $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
            }
        }
        
        // Determine tab_param to skip from list_config filters
        // This prevents double filtering when tabs are enabled
        $tab_param_to_skip = null;
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param_to_skip = $this->config['tabs']['tab_param'] ?? 'tab';
        }
        
        // Apply filters from list_config
        $filter_config = $this->get_filter_config();
        foreach ($filter_config as $filter_key => $enabled) {
            // Skip tab_param filter as it's handled by tabs system
            if ($filter_key === $tab_param_to_skip) {
                continue;
            }
            
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                if ($this->is_valid_column($filter_key)) {
                    $sql .= " AND `{$filter_key}` = %s";
                    $params[] = $filters[$filter_key];
                }
            }
        }
        
        // Apply TAB filter (if tabs are enabled)
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            
            // Only apply filter if value is set, not empty, AND not null
            if (isset($filters[$tab_param]) && 
                $filters[$tab_param] !== '' && 
                $filters[$tab_param] !== null && 
                $this->is_valid_column($tab_param)) {
                $sql .= " AND `{$tab_param}` = %s";
                $params[] = $filters[$tab_param];
            }
        }
        
        // Prepare SQL with parameters
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        // Ordering
        $orderby = $filters['orderby'] ?? $this->get_default_orderby();
        $order = strtoupper($filters['order'] ?? $this->get_default_order());
        
        if ($this->is_valid_orderby($orderby) && in_array($order, ['ASC', 'DESC'], true)) {
            $sql .= " ORDER BY `{$orderby}` {$order}";
        }
        
        // Get total count
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        // Pagination
        $limit = intval($filters['per_page'] ?? $this->get_per_page());
        $offset = (max(1, intval($filters['page'] ?? 1)) - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        // Execute query
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        $data = [
            'items' => $results,
            'total' => intval($total)
        ];
        
        $this->set_cache($cache_key, $data);
        
        return $data;
    }
    
    /**
     * Get record by ID
     *
     * @since 1.0.0
     * @since 9.0.0 Added $bypass_cache parameter
     * @param int  $id            Record ID
     * @param bool $bypass_cache  Skip cache and fetch fresh from DB
     * @return array|null Record data or null
     */
    public function get_by_id($id, $bypass_cache = false) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key('item', $id);
        
        // Check cache unless bypassed
        if (!$bypass_cache) {
            $cached = $this->get_cache($cache_key);
            
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Fetch from database
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $this->table,
            $id
        ), ARRAY_A);
        
        // Cache the result
        if ($item) {
            $this->set_cache($cache_key, $item);
        }
        
        return $item;
    }
    
    /**
     * Create new record
     *
     * @since 1.0.0
     * @since 9.0.0 Auto-invalidates cache after successful insert
     * @param array $data Record data
     * @return int|WP_Error Inserted ID or error
     */
    public function create($data) {
        global $wpdb;
        
        // Validate data
        $validation = $this->validate($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Prepare timestamps
        if ($this->table_has_column('created_at')) {
            $data['created_at'] = current_time('mysql');
        }
        if ($this->table_has_column('updated_at')) {
            $data['updated_at'] = current_time('mysql');
        }
        
        // Insert
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error(
                'db_error', 
                'Failed to create record', 
                array('db_error' => $wpdb->last_error)
            );
        }
        
        $inserted_id = $wpdb->insert_id;
        
        // Auto-invalidate cache after successful insert
        $this->invalidate_cache();
        
        return $inserted_id;
    }
    
    /**
     * Update existing record
     *
     * @since 1.0.0
     * @since 9.0.0 Auto-invalidates cache after successful update
     * @param int   $id   Record ID
     * @param array $data Updated data
     * @return bool|WP_Error True on success or error
     */
    public function update($id, $data) {
        global $wpdb;
        
        $id = intval($id);
        
        if (!$id) {
            return new WP_Error('invalid_id', 'Invalid record ID');
        }
        
        // Validate data
        $validation = $this->validate($data, $id);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Update timestamp
        if ($this->table_has_column('updated_at')) {
            $data['updated_at'] = current_time('mysql');
        }
        
        // Update
        $result = $wpdb->update(
            $this->table,
            $data,
            array('id' => $id)
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_error', 
                'Failed to update record', 
                array('db_error' => $wpdb->last_error)
            );
        }
        
        // Auto-invalidate cache after successful update
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Delete record
     *
     * @since 1.0.0
     * @since 9.0.0 Auto-invalidates cache after successful delete
     * @param int $id Record ID
     * @return bool|WP_Error True on success or error
     */
    public function delete($id) {
        global $wpdb;
        
        $id = intval($id);
        
        if (!$id) {
            return new WP_Error('invalid_id', 'Invalid record ID');
        }
        
        // Delete
        $result = $wpdb->delete(
            $this->table,
            array('id' => $id)
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_error', 
                'Failed to delete record', 
                array('db_error' => $wpdb->last_error)
            );
        }
        
        // Auto-invalidate cache after successful delete
        $this->invalidate_cache();
        
        return true;
    }
    
    // =========================================================================
    // DATA SCOPE FILTERING
    // =========================================================================
    
    /**
     * Apply data scope filtering
     *
     * Implements role-based row-level security.
     * Returns SQL WHERE clause and parameters.
     *
     * @since 7.0.0
     * @return array [string $sql_where, array $params]
     */
    protected function apply_data_scope() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return ['', []];
        }
        
        $role = $this->get_current_user_role();
        $is_branches_table = (strpos($this->table, '_branches') !== false);
        $is_users_table = (strpos($this->table, '_users') !== false);
        
        $allow_global = !empty($this->config['allow_global_in_branch_view']) || $is_users_table;

        $user_ctx = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, branch_id, context_customer_id, context_branch_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d",
            $user_id
        ), ARRAY_A);

        $sql_where = "";
        $params = [];

        if ($role === 'super_admin' || $role === 'admin') {
            $active_customer_id = 0;
            $active_branch_id = 0;

            if ($user_ctx) {
                if ($role === 'super_admin') {
                    $active_customer_id = (int)($user_ctx['context_customer_id'] ?? $user_ctx['customer_id'] ?? 0);
                } else {
                    $active_customer_id = (int)($user_ctx['customer_id'] ?? 0);
                }
                $active_branch_id = (int)($user_ctx['context_branch_id'] ?? 0);
            } elseif ($role === 'super_admin') {
                $active_customer_id = (int) get_user_meta($user_id, 'saw_context_customer_id', true);
                $active_branch_id = (int) get_user_meta($user_id, 'saw_context_branch_id', true);
            }

            if ($this->table_has_column('customer_id') && $active_customer_id > 0) {
                if ($role === 'super_admin' && $is_users_table) {
                    $sql_where .= " AND (customer_id = %d OR customer_id IS NULL)";
                } else {
                    $sql_where .= " AND customer_id = %d";
                }
                $params[] = $active_customer_id;
            }

            if ($active_branch_id > 0) {
                if ($is_branches_table) {
                    $sql_where .= " AND id = %d";
                    $params[] = $active_branch_id;
                } elseif ($this->table_has_column('branch_id')) {
                    if ($allow_global) {
                        $sql_where .= " AND (branch_id = %d OR branch_id IS NULL)";
                    } else {
                        $sql_where .= " AND branch_id = %d";
                    }
                    $params[] = $active_branch_id;
                }
            }

            return [$sql_where, $params];
        }

        if (!class_exists('SAW_Permissions')) {
            return ['', []];
        }
        
        $permission = SAW_Permissions::get_permission($role, $this->config['entity'], 'list');
        if (!$permission) {
            return [' AND 1=0', []];
        }

        switch ($permission['scope']) {
            case 'branch':
                $fixed_branch = (int)($user_ctx['branch_id'] ?? 0);
                if ($fixed_branch) {
                    if ($is_branches_table) {
                        $sql_where .= " AND id = %d";
                        $params[] = $fixed_branch;
                    } elseif ($this->table_has_column('branch_id')) {
                        if ($allow_global) {
                            $sql_where .= " AND (branch_id = %d OR branch_id IS NULL)";
                        } else {
                            $sql_where .= " AND branch_id = %d";
                        }
                        $params[] = $fixed_branch;
                    }
                } else {
                    if ($allow_global && $this->table_has_column('branch_id')) {
                        $sql_where .= " AND branch_id IS NULL";
                    } else {
                        $sql_where = " AND 1=0";
                    }
                }
                break;
                
            case 'department':
                if ($this->table_has_column('department_id')) {
                    $dids = $this->get_current_department_ids();
                    if (!empty($dids)) {
                        $placeholders = implode(',', array_fill(0, count($dids), '%d'));
                        $sql_where .= " AND department_id IN ($placeholders)";
                        $params = array_merge($params, $dids);
                    } else {
                        $sql_where = " AND 1=0";
                    }
                }
                break;
                
            case 'own':
                if ($this->table_has_column('created_by')) {
                    $sql_where .= " AND created_by = %d";
                    $params[] = $user_id;
                } elseif ($this->table_has_column('user_id')) {
                    $sql_where .= " AND user_id = %d";
                    $params[] = $user_id;
                } elseif ($this->table_has_column('wp_user_id')) {
                    $sql_where .= " AND wp_user_id = %d";
                    $params[] = $user_id;
                }
                break;
        }

        return [$sql_where, $params];
    }
    
    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================
    
    /**
     * Get cache key with scope (customer/branch context)
     *
     * @since 7.0.0
     * @since 9.0.0 Optimized with static cache
     * @param string $type       Cache type (list, item, etc.)
     * @param mixed  $identifier Additional identifier
     * @return string Cache key
     */
    protected function get_cache_key_with_scope($type, $identifier = '') {
        static $context_loaded = false;
        static $customer_id = 0;
        static $branch_id = 0;
        static $role = 'guest';
        
        if (!$context_loaded) {
            if (is_user_logged_in() && class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id() ?? 0;
                $branch_id = SAW_Context::get_branch_id() ?? 0;
                $role = SAW_Context::get_role() ?? 'guest';
            } else {
                $role = $this->get_current_user_role() ?? 'guest';
            }
            $context_loaded = true;
        }
        
        $key = $this->config['entity'] . '_' . $type;
        $key .= '_role_' . $role;
        $key .= '_cc' . $customer_id;
        $key .= '_cb' . $branch_id;
        
        if (!empty($identifier)) {
            if (is_array($identifier)) {
                $key .= '_' . md5(serialize($identifier));
            } else {
                $key .= '_' . $identifier;
            }
        }
        
        // Cache versioning using transient
        $version_key = 'saw_' . $this->config['entity'] . '_cache_version';
        $v = get_transient($version_key);
        if (!$v) {
            $v = time();
        }
        
        return $key . '_v' . $v;
    }
    
    /**
     * Get value from cache
     *
     * @since 1.0.0
     * @since 9.0.0 Uses SAW_Cache
     * @param string $key Cache key
     * @return mixed Cached value or false
     */
    protected function get_cache($key) {
        if (!($this->config['cache']['enabled'] ?? true)) {
            return false;
        }
        
        return SAW_Cache::get($key, $this->config['entity']);
    }
    
    /**
     * Set value in cache
     *
     * @since 1.0.0
     * @since 9.0.0 Uses SAW_Cache
     * @param string $key  Cache key
     * @param mixed  $data Data to cache
     * @return bool True on success
     */
    protected function set_cache($key, $data) {
        if (!($this->config['cache']['enabled'] ?? true)) {
            return false;
        }
        
        $ttl = $this->config['cache']['ttl'] ?? $this->cache_ttl;
        return SAW_Cache::set($key, $data, $ttl, $this->config['entity']);
    }
    
    /**
     * Invalidate all cache for this entity
     *
     * @since 1.0.0
     * @since 9.0.0 Uses SAW_Cache flush
     * @return void
     */
    protected function invalidate_cache() {
        // Update timestamp transient (immediate version change)
        $version_key = 'saw_' . $this->config['entity'] . '_cache_version';
        set_transient($version_key, time(), 0);
        
        // Flush cache using SAW_Cache
        SAW_Cache::flush($this->config['entity']);
    }
    
    /**
     * Get cache key (alias for get_cache_key_with_scope)
     *
     * @since 1.0.0
     * @param string $type       Cache type
     * @param mixed  $identifier Additional identifier
     * @return string Cache key
     */
    protected function get_cache_key($type, $identifier = '') {
        return $this->get_cache_key_with_scope($type, $identifier);
    }
    
    // =========================================================================
    // HELPER METHODS
    // =========================================================================
    
    /**
     * Check if table has specific column
     *
     * @since 1.0.0
     * @param string $column_name Column name to check
     * @return bool True if column exists
     */
    protected function table_has_column($column_name) {
        static $column_cache = [];
        $key = $this->table . '_' . $column_name;
        
        if (isset($column_cache[$key])) {
            return $column_cache[$key];
        }
        
        global $wpdb;
        $columns = $wpdb->get_col($wpdb->prepare(
            "SHOW COLUMNS FROM %i LIKE %s", 
            $this->table, 
            $column_name
        ));
        
        return $column_cache[$key] = !empty($columns);
    }

    /**
     * Check if column name is valid (SQL injection prevention)
     *
     * @since 1.0.0
     * @param string $column_name Column name to validate
     * @return bool True if valid
     */
    protected function is_valid_column($column_name) {
        return preg_match('/^[a-zA-Z0-9_]+$/', $column_name);
    }
    
    /**
     * Check if orderby column is valid
     *
     * @since 5.4.0
     * @param string $orderby Column name
     * @return bool True if valid
     */
    protected function is_valid_orderby($orderby) {
        return in_array($orderby, $this->allowed_orderby, true) || $this->is_valid_column($orderby);
    }
    
    /**
     * Get current user's SAW role
     *
     * @since 1.0.0
     * @return string|null User role or null
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
     * Get current user's department IDs
     *
     * @since 7.0.0
     * @return array Array of department IDs
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
     * Get current customer ID from context
     *
     * @since 1.0.0
     * @return int|null Customer ID or null
     */
    protected function get_current_customer_id() {
        return class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
    }
    
    /**
     * Get current branch ID from context
     *
     * @since 1.0.0
     * @return int|null Branch ID or null
     */
    protected function get_current_branch_id() {
        return class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
    }
    
    /**
     * Get accessible branch IDs for current user
     *
     * @since 7.0.0
     * @return array Array of branch IDs
     */
    protected function get_accessible_branch_ids() {
        return [];
    }
    
    // =========================================================================
    // VIRTUAL COLUMNS
    // =========================================================================
    
    /**
     * Apply virtual columns to items
     * 
     * Virtual columns are dynamically computed values not stored in database.
     * Configuration is in module's config.php under 'virtual_columns' key.
     * 
     * Supported types:
     * - 'computed': Simple function without DB access
     * - 'complex': Function with DB access (use sparingly - N+1 problem)
     * - 'batch_computed': Optimized batch processing with single query
     * - 'concat': Concatenate multiple fields
     * - 'date_diff': Calculate difference between dates
     * 
     * @since 8.0.0
     * @param array $items Array of items from database
     * @return array Items with applied virtual columns
     */
    protected function apply_virtual_columns($items) {
        if (empty($items) || empty($this->config['virtual_columns'])) {
            return $items;
        }
        
        if (!is_array($items)) {
            return $items;
        }
        
        global $wpdb;
        
        // First pass: Batch computed columns
        $batch_results = array();
        foreach ($this->config['virtual_columns'] as $column_name => $column_config) {
            if (($column_config['type'] ?? '') === 'batch_computed') {
                $item_ids = array_column($items, 'id');
                
                if (empty($item_ids)) {
                    continue;
                }
                
                if (!empty($column_config['batch_query']) && is_callable($column_config['batch_query'])) {
                    try {
                        $batch_data = call_user_func($column_config['batch_query'], $item_ids, $wpdb);
                        
                        $indexed = array();
                        if (is_array($batch_data)) {
                            foreach ($batch_data as $row) {
                                $key = $row['visitor_id'] ?? $row['id'] ?? null;
                                if ($key) {
                                    $indexed[$key] = $row;
                                }
                            }
                        }
                        
                        $batch_results[$column_name] = $indexed;
                    } catch (Exception $e) {
                        $batch_results[$column_name] = array();
                    }
                }
            }
        }
        
        // Second pass: Apply all virtual columns
        foreach ($items as &$item) {
            if (empty($item['id'])) {
                continue;
            }
            
            foreach ($this->config['virtual_columns'] as $column_name => $column_config) {
                $type = $column_config['type'] ?? 'computed';
                
                try {
                    switch ($type) {
                        case 'computed':
                            if (!empty($column_config['compute']) && is_callable($column_config['compute'])) {
                                $item[$column_name] = call_user_func($column_config['compute'], $item);
                            }
                            break;
                        
                        case 'complex':
                            if (!empty($column_config['compute']) && is_callable($column_config['compute'])) {
                                if (!empty($column_config['requires_db'])) {
                                    $item[$column_name] = call_user_func($column_config['compute'], $item, $wpdb);
                                } else {
                                    $item[$column_name] = call_user_func($column_config['compute'], $item);
                                }
                            }
                            break;
                        
                        case 'batch_computed':
                            if (!empty($column_config['apply']) && is_callable($column_config['apply'])) {
                                $batch_data = $batch_results[$column_name] ?? array();
                                $item[$column_name] = call_user_func($column_config['apply'], $item, $batch_data);
                            }
                            break;
                        
                        case 'concat':
                            $values = array();
                            foreach (($column_config['fields'] ?? array()) as $field) {
                                if (!empty($item[$field])) {
                                    $values[] = $item[$field];
                                }
                            }
                            $separator = $column_config['separator'] ?? ' ';
                            $item[$column_name] = implode($separator, $values);
                            break;
                        
                        case 'date_diff':
                            $from = $item[$column_config['from']] ?? null;
                            $to_value = $column_config['to'] ?? 'NOW()';
                            
                            if ($from) {
                                $from_time = strtotime($from);
                                $to_time = ($to_value === 'NOW()') ? time() : strtotime($item[$to_value] ?? $to_value);
                                
                                $diff = $to_time - $from_time;
                                $unit = $column_config['unit'] ?? 'days';
                                
                                switch ($unit) {
                                    case 'years':
                                        $item[$column_name] = floor($diff / (365 * 86400));
                                        break;
                                    case 'months':
                                        $item[$column_name] = floor($diff / (30 * 86400));
                                        break;
                                    case 'days':
                                        $item[$column_name] = floor($diff / 86400);
                                        break;
                                    case 'hours':
                                        $item[$column_name] = floor($diff / 3600);
                                        break;
                                    default:
                                        $item[$column_name] = $diff;
                                }
                            } else {
                                $item[$column_name] = null;
                            }
                            break;
                    }
                } catch (Exception $e) {
                    $item[$column_name] = null;
                }
            }
        }
        unset($item);
        
        return $items;
    }
    
    // =========================================================================
    // ABSTRACT METHODS
    // =========================================================================
    
    /**
     * Validate data before save
     *
     * Must be implemented by child classes.
     *
     * @since 1.0.0
     * @param array $data Data to validate
     * @param int   $id   Record ID (0 for new records)
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    abstract public function validate($data, $id = 0);
}