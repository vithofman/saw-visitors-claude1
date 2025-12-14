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
 * @version    9.0.0 - IMPROVED: Cache bypass option + better invalidation
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
        
        list($scope_where, $scope_params) = $this->apply_data_scope();
        if (!empty($scope_where)) {
            $sql .= $scope_where;
            $params = array_merge($params, $scope_params);
        }
        
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
        
        // Determine tab_param to skip from list_config filters
        // This prevents double filtering when tabs are enabled
        $tab_param_to_skip = null;
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param_to_skip = $this->config['tabs']['tab_param'] ?? 'tab';
        }
        
        // Apply filters from list_config
        // CRITICAL: Skip tab_param if tabs are enabled (it's handled separately below)
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
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
        // This is separate from list_config filters to ensure proper handling
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            
            // Only apply filter if value is set, not empty, AND not null
            // This ensures "Všechny" tab (filter_value = null) shows all records
            if (isset($filters[$tab_param]) && 
                $filters[$tab_param] !== '' && 
                $filters[$tab_param] !== null && 
                $this->is_valid_column($tab_param)) {
                $sql .= " AND `{$tab_param}` = %s";
                $params[] = $filters[$tab_param];
            }
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $orderby = $filters['orderby'] ?? 'id';
        $order = strtoupper($filters['order'] ?? 'DESC');
        
        if ($this->is_valid_orderby($orderby) && in_array($order, ['ASC', 'DESC'], true)) {
            $sql .= " ORDER BY `{$orderby}` {$order}";
        }
        
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as count_table";
        $total = $wpdb->get_var($total_sql);
        
        $limit = intval($filters['per_page'] ?? 20);
        // ⭐ KRITICKÁ OPRAVA: Pokud je v filters vlastní offset, použijeme ho
        // To umožňuje infinite scroll používat kumulativní offset místo page-based
        if (isset($filters['offset']) && $filters['offset'] >= 0) {
            // Vlastní offset (pro infinite scroll)
            $offset = intval($filters['offset']);
        } else {
            // Standardní page-based offset
            $offset = ($filters['page'] ?? 1) - 1;
            $offset = $offset * $limit;
        }
        
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
     * ✅ IMPROVED: No cache writing - lets get_by_id() handle it
     *
     * @since 1.0.0
     * @since 9.0.0 Removed cache writing
     * @param array $data Record data
     * @return int|WP_Error Insert ID or error
     */
    /**
     * Create new record
     *
     * ✅ ENHANCED: Auto-invalidates cache after successful insert
     *
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
        
        // Auto-set created_by if column exists and not already set
        // Special handling for visits table which uses created_by_email
        $created_by_field = (strpos($this->table, 'visits') !== false) ? 'created_by_email' : 'created_by';
        if ($this->table_has_column($created_by_field) && !isset($data[$created_by_field])) {
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->ID) {
                $data[$created_by_field] = $current_user->user_email;
            }
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
        
        // ✅ Auto-invalidate cache after successful insert
        $this->invalidate_cache();
        
        // Log audit change (create)
        $this->log_audit_change('created', $inserted_id, null, $data);
        
        return $inserted_id;
    }
    
    /**
     * Update existing record
     *
     * ✅ ENHANCED: Auto-invalidates cache after successful update
     *
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
        
        // Load old values before update for audit logging
        $old_values = $this->get_by_id($id, true);
        
        // Update timestamp
        if ($this->table_has_column('updated_at')) {
            $data['updated_at'] = current_time('mysql');
        }
        
        // Auto-set updated_by if column exists
        if ($this->table_has_column('updated_by') && !isset($data['updated_by'])) {
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->ID) {
                $data['updated_by'] = $current_user->user_email;
            }
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
        
        // ✅ Auto-invalidate cache after successful update
        $this->invalidate_cache();
        
        // Log audit change (update)
        $this->log_audit_change('updated', $id, $old_values, $data);
        
        return true;
    }
    
    /**
     * Delete record
     *
     * ✅ ENHANCED: Auto-invalidates cache after successful delete
     *
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
        
        // ✅ Auto-invalidate cache after successful delete
        $this->invalidate_cache();
        
        return true;
    }
    
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
        if (!$user_id) return ['', []];
        
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

        if (!class_exists('SAW_Permissions')) return ['', []];
        
        $permission = SAW_Permissions::get_permission($role, $this->config['entity'], 'list');
        if (!$permission) return [' AND 1=0', []];

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
    // CACHE VERSIONING - FIXED FOR OBJECT CACHE
    // =========================================================================
    /**
     * Get cache key with scope (customer/branch context)
     *
     * ✅ OPTIMIZED: Uses static cache PER REQUEST + SAW_Context
     * ✅ FIXED: No DB query - SAW_Context has its own static cache
     *
     * @param string $type       Cache type (list, item, etc.)
     * @param mixed  $identifier Additional identifier
     * @return string Cache key
     */
    protected function get_cache_key_with_scope($type, $identifier = '') {
        // ✅ STATIC CACHE: Prevents repeated SAW_Context calls within same request
        static $context_loaded = false;
        static $customer_id = 0;
        static $branch_id = 0;
        static $role = 'guest';
        
        if (!$context_loaded) {
            if (is_user_logged_in() && class_exists('SAW_Context')) {
                // SAW_Context has its own static cache internally
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

    // --- HELPERS ---
    protected function table_has_column($column_name) {
        static $column_cache = [];
        $key = $this->table . '_' . $column_name;
        if (isset($column_cache[$key])) {
            return $column_cache[$key];
        }
        global $wpdb;
        $columns = $wpdb->get_col($wpdb->prepare("SHOW COLUMNS FROM %i LIKE %s", $this->table, $column_name));
        return $column_cache[$key] = !empty($columns);
    }

    protected function is_valid_column($column_name) {
        return preg_match('/^[a-zA-Z0-9_]+$/', $column_name);
    }
    
    protected function is_valid_orderby($orderby) {
        return in_array($orderby, $this->allowed_orderby, true) || $this->is_valid_column($orderby);
    }
    
    protected function get_current_user_role() {
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
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
            "SELECT department_id FROM %i WHERE user_id = %d",
            $wpdb->prefix . 'saw_user_departments',
            $saw_user_id
        ));
        return array_map('intval', $department_ids);
    }

    // Abstract & Protected methods for compatibility
    abstract public function validate($data, $id = 0);
    
    protected function get_cache_key($type, $identifier = '') {
        return $this->get_cache_key_with_scope($type, $identifier);
    }
    
    protected function get_accessible_branch_ids() {
        return [];
    }
    
    protected function get_current_customer_id() {
        return SAW_Context::get_customer_id();
    }
    
    protected function get_current_branch_id() {
        return SAW_Context::get_branch_id();
    }

    /**
     * Get entity type from table name or config
     *
     * @since 1.0.0
     * @return string|null Entity type or null if cannot be determined
     */
    protected function get_entity_type() {
        // Try config first
        if (!empty($this->config['entity'])) {
            return $this->config['entity'];
        }
        
        // Extract from table name: wp_saw_oopp -> oopp
        $table = $this->table;
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        if (strpos($table, $prefix) === 0) {
            return substr($table, strlen($prefix));
        }
        
        return null;
    }

    /**
     * Get customer_id from record
     *
     * @since 1.0.0
     * @param int|array $record Record ID or record array
     * @return int|null Customer ID
     */
    protected function get_customer_id_from_record($record) {
        // If record is ID, fetch from DB
        if (is_numeric($record)) {
            $record_data = $this->get_by_id($record, true);
            if (!$record_data) {
                return null;
            }
            $record = $record_data;
        }
        
        // Check if record has customer_id
        if (isset($record['customer_id']) && !empty($record['customer_id'])) {
            return intval($record['customer_id']);
        }
        
        // Fallback to context
        return $this->get_current_customer_id();
    }

    /**
     * Get branch_id from record
     *
     * @since 1.0.0
     * @param int|array $record Record ID or record array
     * @return int|null Branch ID or null
     */
    protected function get_branch_id_from_record($record) {
        // If record is ID, fetch from DB
        if (is_numeric($record)) {
            $record_data = $this->get_by_id($record, true);
            if (!$record_data) {
                return null;
            }
            $record = $record_data;
        }
        
        // Check if record has branch_id
        if (isset($record['branch_id']) && !empty($record['branch_id'])) {
            return intval($record['branch_id']);
        }
        
        // Fallback to context branch_id (user's current branch)
        return $this->get_current_branch_id();
    }

    /**
     * Calculate diff between old and new values
     *
     * @since 1.0.0
     * @param array|null $old_values Old values (null for create)
     * @param array      $new_values New values
     * @return array Array of changed fields with 'old' and 'new' values
     */
    private function calculate_diff($old_values, $new_values) {
        $diff = [];
        $ignore_fields = ['updated_at', 'updated_by', 'id'];
        
        // For create action (old_values is null), include all fields
        $is_create = ($old_values === null);
        
        foreach ($new_values as $key => $new_value) {
            if (in_array($key, $ignore_fields)) {
                continue;
            }
            
            $old_value = $old_values[$key] ?? null;
            
            // For create, include all fields (old is always null)
            // For update, only include changed fields
            if ($is_create) {
                $diff[$key] = [
                    'old' => null,
                    'new' => $new_value
                ];
            } else {
                // Normalize values for comparison
                $old_normalized = $this->normalize_value($old_value);
                $new_normalized = $this->normalize_value($new_value);
                
                if ($old_normalized !== $new_normalized) {
                    $diff[$key] = [
                        'old' => $old_value,
                        'new' => $new_value
                    ];
                }
            }
        }
        
        return $diff;
    }

    /**
     * Normalize value for comparison
     *
     * @since 1.0.0
     * @param mixed $value Value to normalize
     * @return mixed Normalized value
     */
    private function normalize_value($value) {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        return trim((string) $value);
    }

    /**
     * Log audit change
     *
     * @since 1.0.0
     * @param string      $action     Action type: 'created' or 'updated'
     * @param int         $id         Record ID
     * @param array|null  $old_values Old values (null for create)
     * @param array       $new_values New values
     * @return int|false Log entry ID or false on failure
     */
    protected function log_audit_change($action, $id, $old_values, $new_values) {
        if (!class_exists('SAW_Audit')) {
            return false;
        }

        $entity_type = $this->get_entity_type();
        if (!$entity_type) {
            return false;
        }

        // Calculate diff
        $changed_fields = $this->calculate_diff($old_values, $new_values);

        // Get customer_id
        $customer_id = null;
        if (!empty($new_values['customer_id'])) {
            $customer_id = intval($new_values['customer_id']);
        } elseif ($old_values && !empty($old_values['customer_id'])) {
            $customer_id = intval($old_values['customer_id']);
        } else {
            $customer_id = $this->get_current_customer_id();
        }

        // Get branch_id
        $branch_id = null;
        if (!empty($new_values['branch_id'])) {
            $branch_id = intval($new_values['branch_id']);
        } elseif ($old_values && !empty($old_values['branch_id'])) {
            $branch_id = intval($old_values['branch_id']);
        } else {
            // Fallback to context only if entity doesn't have branch_id
            if ($this->table_has_column('branch_id')) {
                // Entity has branch_id column but value is NULL/empty - use context
                $branch_id = $this->get_current_branch_id();
            }
        }

        return SAW_Audit::log_change([
            'entity_type' => $entity_type,
            'entity_id' => $id,
            'action' => $action,
            'old_values' => $old_values,
            'new_values' => $new_values,
            'changed_fields' => $changed_fields,
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
        ]);
    }

    /**
     * Get value from cache
     *
     * ✅ REFACTORED: Uses SAW_Cache instead of duplicate logic
     *
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
     * ✅ REFACTORED: Uses SAW_Cache instead of duplicate logic
     *
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
     * ✅ REFACTORED: Uses SAW_Cache flush
     *
     * @return void
     */
    /**
     * Invalidate all cache for this entity
     *
     * ✅ REFACTORED: Uses SAW_Cache flush
     *
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
     * Apply virtual columns to items
     * 
     * Virtual columns jsou dynamicky počítané hodnoty které nejsou uložené v databázi.
     * Konfigurace je v config.php modulu pod klíčem 'virtual_columns'.
     * 
     * Podporované typy:
     * - 'computed': Jednoduchá funkce bez DB access
     * - 'complex': Funkce s DB access (použij opatrně - N+1 problém)
     * - 'batch_computed': Optimalizovaný batch processing s jediným query
     * - 'concat': Spojí více polí dohromady
     * - 'date_diff': Vypočítá rozdíl mezi daty
     * 
     * @since 8.0.0
     * @param array $items Array položek z databáze
     * @return array Položky s aplikovanými virtual columns
     */
    protected function apply_virtual_columns($items) {
        if (empty($items) || empty($this->config['virtual_columns'])) {
            return $items;
        }
        
        // ✅ Validace vstupních dat
        if (!is_array($items)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Base Model] apply_virtual_columns: Items is not an array');
            }
            return $items;
        }
        
        global $wpdb;
        
        // ✅ Debugging info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Base Model] Applying virtual columns to %d items. Columns: %s',
                count($items),
                implode(', ', array_keys($this->config['virtual_columns']))
            ));
        }
        
        // =====================================
        // PRVNÍ PRŮCHOD: Batch computed columns
        // =====================================
        $batch_results = array();
        foreach ($this->config['virtual_columns'] as $column_name => $column_config) {
            if (($column_config['type'] ?? '') === 'batch_computed') {
                $item_ids = array_column($items, 'id');
                
                // ✅ Ověř že máme nějaké IDs
                if (empty($item_ids)) {
                    continue;
                }
                
                if (!empty($column_config['batch_query']) && is_callable($column_config['batch_query'])) {
                    try {
                        // Zavolej batch query callback
                        $batch_data = call_user_func($column_config['batch_query'], $item_ids, $wpdb);
                        
                        // Indexuj podle visitor_id pro rychlé vyhledávání
                        $indexed = array();
                        if (is_array($batch_data)) {
                            foreach ($batch_data as $row) {
                                // Podporuj různé názvy ID sloupce
                                $key = $row['visitor_id'] ?? $row['id'] ?? null;
                                if ($key) {
                                    $indexed[$key] = $row;
                                }
                            }
                        }
                        
                        $batch_results[$column_name] = $indexed;
                    } catch (Exception $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[Base Model] Batch query error for ' . $column_name . ': ' . $e->getMessage());
                        }
                        $batch_results[$column_name] = array();
                    }
                }
            }
        }
        
        // =====================================
        // DRUHÝ PRŮCHOD: Aplikuj všechny virtual columns
        // =====================================
        foreach ($items as &$item) {
            // ✅ Zkontroluj že item má ID
            if (empty($item['id'])) {
                continue;
            }
            
            foreach ($this->config['virtual_columns'] as $column_name => $column_config) {
                $type = $column_config['type'] ?? 'computed';
                
                try {
                    switch ($type) {
                        case 'computed':
                            // Jednoduchá computed column - bez DB access
                            if (!empty($column_config['compute']) && is_callable($column_config['compute'])) {
                                $item[$column_name] = call_user_func($column_config['compute'], $item);
                            }
                            break;
                        
                        case 'complex':
                            // Komplexní computed column - s DB access
                            // ⚠️ POZOR: N+1 problém! Používej jen když nezbytné!
                            if (!empty($column_config['compute']) && is_callable($column_config['compute'])) {
                                if (!empty($column_config['requires_db'])) {
                                    $item[$column_name] = call_user_func($column_config['compute'], $item, $wpdb);
                                } else {
                                    $item[$column_name] = call_user_func($column_config['compute'], $item);
                                }
                            }
                            break;
                        
                        case 'batch_computed':
                            // Batch computed - použij předem načtené výsledky
                            if (!empty($column_config['apply']) && is_callable($column_config['apply'])) {
                                $batch_data = $batch_results[$column_name] ?? array();
                                $item[$column_name] = call_user_func($column_config['apply'], $item, $batch_data);
                            }
                            break;
                        
                        case 'concat':
                            // Spojí více polí dohromady
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
                            // Vypočítá rozdíl mezi daty
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
                                        $item[$column_name] = $diff; // sekundy
                                }
                            } else {
                                $item[$column_name] = null;
                            }
                            break;
                        
                        default:
                            // Neznámý typ - ignoruj
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('[Base Model] Unknown virtual column type: ' . $type);
                            }
                    }
                } catch (Exception $e) {
                    // Loguj error ale pokračuj dál
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Base Model] Error applying virtual column ' . $column_name . ': ' . $e->getMessage());
                    }
                    $item[$column_name] = null;
                }
            }
        }
        unset($item); // Break reference
        
        return $items;
    }
}