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
 * @version    5.4.0
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
     * Get all records with filters
     *
     * Returns paginated list with scope filtering applied.
     *
     * @since 1.0.0
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
        
        // Apply scope filtering
        list($scope_where, $scope_params) = $this->apply_data_scope();
        if (!empty($scope_where)) {
            $sql .= $scope_where;
            $params = array_merge($params, $scope_params);
        }
        
        // Search filtering
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
            $search_conditions = [];
            
            foreach ($search_fields as $field) {
                // Validate field name to prevent SQL injection
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
        
        // Additional filters
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                // Validate column name
                if ($this->is_valid_column($filter_key)) {
                    $sql .= " AND `{$filter_key}` = %s";
                    $params[] = $filters[$filter_key];
                }
            }
        }
        
        // Prepare SQL with params
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        // Ordering
        $orderby = $filters['orderby'] ?? 'id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        // Whitelist validation for ORDER BY
        if ($this->is_valid_orderby($orderby) && in_array($order, ['ASC', 'DESC'], true)) {
            $sql .= " ORDER BY `{$orderby}` {$order}";
        }
        
        // Count total
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        // Pagination
        $limit = intval($filters['per_page'] ?? 20);
        $offset = ($filters['page'] ?? 1) - 1;
        $offset = $offset * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        $data = [
            'items' => $results,
            'total' => $total
        ];
        
        $this->set_cache($cache_key, $data);
        
        return $data;
    }
    
    /**
     * Get record by ID
     *
     * @since 1.0.0
     * @param int $id Record ID
     * @return array|null Record data or null
     */
    public function get_by_id($id) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key('item', $id);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $this->table,
            $id
        ), ARRAY_A);
        
        if ($item) {
            $this->set_cache($cache_key, $item);
        }
        
        return $item;
    }
    
    /**
     * Create new record
     *
     * @since 1.0.0
     * @param array $data Record data
     * @return int|WP_Error Insert ID or error
     */
    public function create($data) {
        global $wpdb;
        
        $validation = $this->validate($data);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                __('Database insert failed', 'saw-visitors') . ': ' . $wpdb->last_error
            );
        }
        
        $this->invalidate_cache();
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update existing record
     *
     * @since 1.0.0
     * @param int   $id   Record ID
     * @param array $data Updated data
     * @return bool|WP_Error True on success, error otherwise
     */
    public function update($id, $data) {
        global $wpdb;
        
        $validation = $this->validate($data, $id);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                __('Database update failed', 'saw-visitors') . ': ' . $wpdb->last_error
            );
        }
        
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Delete record
     *
     * @since 1.0.0
     * @param int $id Record ID
     * @return bool|WP_Error True on success, error otherwise
     */
    public function delete($id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE id = %d",
            $this->table,
            $id
        ));
        
        if (!$exists) {
            return new WP_Error(
                'not_found',
                __('Záznam nenalezen', 'saw-visitors')
            );
        }
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                __('Chyba databáze', 'saw-visitors') . ': ' . $wpdb->last_error
            );
        }
        
        if ($result === 0) {
            return new WP_Error(
                'delete_failed',
                __('Záznam se nepodařilo smazat', 'saw-visitors')
            );
        }
        
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Count records with filters
     *
     * @since 1.0.0
     * @param array $filters Query filters
     * @return int Record count
     */
    public function count($filters = []) {
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM %i WHERE 1=1", $this->table);
        $params = [];
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND customer_id = %d";
            $params[] = intval($filters['customer_id']);
        }
        
        if (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $sql .= " AND branch_id = %d";
            $params[] = intval($filters['branch_id']);
        }
        
        // Apply scope filtering
        list($scope_where, $scope_params) = $this->apply_data_scope();
        if (!empty($scope_where)) {
            $sql .= $scope_where;
            $params = array_merge($params, $scope_params);
        }
        
        // Search filtering
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
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
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Search records
     *
     * @since 1.0.0
     * @param string $query  Search query
     * @param int    $limit  Result limit
     * @return array Search results
     */
    public function search($query, $limit = 10) {
        global $wpdb;
        
        if (empty($query)) {
            return [];
        }
        
        $search_fields = $this->config['list_config']['searchable'] ?? ['name'];
        $search_conditions = [];
        $params = [];
        
        foreach ($search_fields as $field) {
            if ($this->is_valid_column($field)) {
                $search_conditions[] = "`{$field}` LIKE %s";
                $params[] = '%' . $wpdb->esc_like($query) . '%';
            }
        }
        
        if (empty($search_conditions)) {
            return [];
        }
        
        $sql = $wpdb->prepare("SELECT * FROM %i WHERE ", $this->table);
        $sql .= "(" . implode(' OR ', $search_conditions) . ")";
        
        // Apply scope filtering
        list($scope_where, $scope_params) = $this->apply_data_scope();
        if (!empty($scope_where)) {
            $sql .= $scope_where;
            $params = array_merge($params, $scope_params);
        }
        
        $sql .= " LIMIT %d";
        $params[] = intval($limit);
        
        $sql = $wpdb->prepare($sql, ...$params);
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Apply data scope filtering
     *
     * Filters query based on user's role and scope permissions.
     *
     * @since 1.0.0
     * @return array [sql_where, params]
     */
    protected function apply_data_scope() {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return ['', []];
        }
        
        if (!$role || !class_exists('SAW_Permissions')) {
            return ['', []];
        }
        
        $permission = SAW_Permissions::get_permission($role, $this->config['entity'], 'list');
        
        if (!$permission || !isset($permission['scope'])) {
            return [' AND 1=0', []];
        }
        
        $scope = $permission['scope'];
        $sql_where = '';
        $params = [];
        
        switch ($scope) {
            case 'all':
                // No filtering needed
                break;
                
            case 'customer':
                if ($this->table_has_column('customer_id')) {
                    $customer_id = $this->get_current_customer_id();
                    if ($customer_id) {
                        $sql_where = " AND customer_id = %d";
                        $params[] = $customer_id;
                    }
                }
                break;
                
            case 'branch':
                if ($this->table_has_column('branch_id')) {
                    $branch_ids = $this->get_accessible_branch_ids();
                    if (!empty($branch_ids)) {
                        $placeholders = implode(',', array_fill(0, count($branch_ids), '%d'));
                        $sql_where = " AND branch_id IN ($placeholders)";
                        $params = array_merge($params, $branch_ids);
                    }
                }
                break;
                
            case 'department':
                if ($this->table_has_column('department_id')) {
                    $department_ids = $this->get_current_department_ids();
                    if (!empty($department_ids)) {
                        $placeholders = implode(',', array_fill(0, count($department_ids), '%d'));
                        $sql_where = " AND department_id IN ($placeholders)";
                        $params = array_merge($params, $department_ids);
                    }
                }
                break;
                
            case 'own':
                if ($this->table_has_column('created_by')) {
                    $user_id = get_current_user_id();
                    if ($user_id) {
                        $sql_where = " AND created_by = %d";
                        $params[] = $user_id;
                    }
                } elseif ($this->table_has_column('user_id')) {
                    $user_id = get_current_user_id();
                    if ($user_id) {
                        $sql_where = " AND user_id = %d";
                        $params[] = $user_id;
                    }
                } elseif ($this->table_has_column('wp_user_id')) {
                    $user_id = get_current_user_id();
                    if ($user_id) {
                        $sql_where = " AND wp_user_id = %d";
                        $params[] = $user_id;
                    }
                }
                break;
        }
        
        return [$sql_where, $params];
    }
    
    /**
     * Check if table has column
     *
     * Uses static cache to avoid repeated queries.
     *
     * @since 1.0.0
     * @param string $column_name Column name to check
     * @return bool True if column exists
     */
    protected function table_has_column($column_name) {
        static $column_cache = [];
        
        $cache_key = $this->table . '_' . $column_name;
        
        if (isset($column_cache[$cache_key])) {
            return $column_cache[$cache_key];
        }
        
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = %s",
            DB_NAME,
            $this->table,
            $column_name
        ));
        
        $column_cache[$cache_key] = (bool) $result;
        
        return $column_cache[$cache_key];
    }
    
    /**
     * Validate column name
     *
     * Security check to prevent SQL injection via column names.
     * Checks if column exists in table.
     *
     * @since 5.4.0
     * @param string $column_name Column name to validate
     * @return bool True if valid
     */
    protected function is_valid_column($column_name) {
        // Basic format validation
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column_name)) {
            return false;
        }
        
        return $this->table_has_column($column_name);
    }
    
    /**
     * Validate ORDER BY column
     *
     * Checks against whitelist to prevent SQL injection.
     *
     * @since 5.4.0
     * @param string $column Column name
     * @return bool True if valid
     */
    protected function is_valid_orderby($column) {
        return in_array($column, $this->allowed_orderby, true);
    }
    
    /**
     * Get accessible branch IDs for current user
     *
     * @since 1.0.0
     * @return array Branch IDs
     */
    protected function get_accessible_branch_ids() {
        $role = $this->get_current_user_role();
        $current_branch_id = $this->get_current_branch_id();
        
        if ($role === 'admin') {
            global $wpdb;
            $customer_id = $this->get_current_customer_id();
            
            if (!$customer_id) {
                return [];
            }
            
            $branch_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM %i WHERE customer_id = %d AND is_active = 1",
                $wpdb->prefix . 'saw_branches',
                $customer_id
            ));
            
            return array_map('intval', $branch_ids);
        }
        
        if ($role === 'super_manager') {
            if (!class_exists('SAW_User_Branches') || !class_exists('SAW_Context')) {
                return $current_branch_id ? [$current_branch_id] : [];
            }
            
            $saw_user_id = SAW_Context::get_saw_user_id();
            if (!$saw_user_id) {
                return $current_branch_id ? [$current_branch_id] : [];
            }
            
            $branch_ids = SAW_User_Branches::get_branch_ids_for_user($saw_user_id);
            
            if (empty($branch_ids)) {
                return $current_branch_id ? [$current_branch_id] : [];
            }
            
            if ($current_branch_id && in_array($current_branch_id, $branch_ids)) {
                return [$current_branch_id];
            }
            
            return $branch_ids;
        }
        
        return $current_branch_id ? [$current_branch_id] : [];
    }
    
    /**
     * Get current user role
     *
     * @since 1.0.0
     * @return string|null Role name or null
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
     * Get cache key with scope context
     *
     * Generates unique cache key including user's scope context.
     *
     * @since 1.0.0
     * @param string $type       Cache type
     * @param mixed  $identifier Additional identifier
     * @return string Cache key
     */
    protected function get_cache_key_with_scope($type, $identifier = '') {
        $key = 'saw_' . $this->config['entity'] . '_' . $type;
        
        $role = $this->get_current_user_role();
        if ($role && $role !== 'super_admin') {
            $key .= '_role_' . $role;
            
            if (class_exists('SAW_Permissions')) {
                $permission = SAW_Permissions::get_permission($role, $this->config['entity'], 'list');
                if ($permission && isset($permission['scope'])) {
                    $key .= '_scope_' . $permission['scope'];
                    
                    switch ($permission['scope']) {
                        case 'customer':
                            $customer_id = $this->get_current_customer_id();
                            if ($customer_id) {
                                $key .= '_c' . $customer_id;
                            }
                            break;
                            
                        case 'branch':
                            $branch_ids = $this->get_accessible_branch_ids();
                            if (!empty($branch_ids)) {
                                sort($branch_ids);
                                $key .= '_b' . implode('_', $branch_ids);
                            }
                            break;
                            
                        case 'department':
                            $dept_ids = $this->get_current_department_ids();
                            if (!empty($dept_ids)) {
                                sort($dept_ids);
                                $key .= '_d' . implode('_', $dept_ids);
                            }
                            break;
                            
                        case 'own':
                            $user_id = get_current_user_id();
                            if ($user_id) {
                                $key .= '_u' . $user_id;
                            }
                            break;
                    }
                }
            }
        }
        
        if (is_array($identifier)) {
            $key .= '_' . md5(serialize($identifier));
        } elseif ($identifier) {
            $key .= '_' . $identifier;
        }
        
        return $key;
    }
    
    /**
     * Validate data
     *
     * Must be implemented by child classes.
     *
     * @since 1.0.0
     * @param array $data Data to validate
     * @param int   $id   Record ID (0 for create)
     * @return true|WP_Error True if valid, error otherwise
     */
    abstract public function validate($data, $id = 0);
    
    /**
     * Get cache key
     *
     * @since 1.0.0
     * @param string $type       Cache type
     * @param mixed  $identifier Identifier
     * @return string Cache key
     */
    protected function get_cache_key($type, $identifier = '') {
        $key = 'saw_' . $this->config['entity'] . '_' . $type;
        
        if (is_array($identifier)) {
            $key .= '_' . md5(serialize($identifier));
        } elseif ($identifier) {
            $key .= '_' . $identifier;
        }
        
        return $key;
    }
    
    /**
     * Get cached data
     *
     * @since 1.0.0
     * @param string $key Cache key
     * @return mixed Cached data or false
     */
    protected function get_cache($key) {
        if (!$this->config['cache']['enabled'] ?? true) {
            return false;
        }
        
        return get_transient($key);
    }
    
    /**
     * Set cache data
     *
     * @since 1.0.0
     * @param string $key  Cache key
     * @param mixed  $data Data to cache
     * @return bool Success
     */
    protected function set_cache($key, $data) {
        if (!$this->config['cache']['enabled'] ?? true) {
            return false;
        }
        
        $ttl = $this->config['cache']['ttl'] ?? $this->cache_ttl;
        
        return set_transient($key, $data, $ttl);
    }
    
    /**
     * Invalidate all cache for entity
     *
     * Removes all transients matching entity pattern.
     *
     * @since 1.0.0
     * @return void
     */
    protected function invalidate_cache() {
        global $wpdb;
        
        $pattern = $wpdb->esc_like('saw_' . $this->config['entity'] . '_') . '%';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE option_name LIKE %s",
            $wpdb->options,
            '_transient_' . $pattern
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE option_name LIKE %s",
            $wpdb->options,
            '_transient_timeout_' . $pattern
        ));
    }
}