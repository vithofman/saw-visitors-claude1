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
 * @version    7.1.0 - FINAL: Fixed Branch Scope for Branches Module
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
        
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($filters[$filter_key]) && $filters[$filter_key] !== '') {
                if ($this->is_valid_column($filter_key)) {
                    $sql .= " AND `{$filter_key}` = %s";
                    $params[] = $filters[$filter_key];
                }
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
            return new WP_Error('db_error', __('Database insert failed', 'saw-visitors') . ': ' . $wpdb->last_error);
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
            return new WP_Error('db_error', __('Database update failed', 'saw-visitors') . ': ' . $wpdb->last_error);
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
            return new WP_Error('not_found', __('Záznam nenalezen', 'saw-visitors'));
        }
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Chyba databáze', 'saw-visitors') . ': ' . $wpdb->last_error);
        }
        
        if ($result === 0) {
            return new WP_Error('delete_failed', __('Záznam se nepodařilo smazat', 'saw-visitors'));
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
        return $this->get_all(['search' => $query, 'per_page' => $limit])['items'];
    }

    // =========================================================================
    // 🔥 APPLY DATA SCOPE - FINAL LOGIC WITH BRANCHES FIX
    // =========================================================================
    /**
     * Apply data scope filtering
     *
     * Filters query based on user's role and scope permissions.
     *
     * @since 1.0.0
     * @return array [sql_where, params]
     */
    protected function apply_data_scope() {
        if (!is_user_logged_in()) return ['', []];
        
        global $wpdb;
        $role = $this->get_current_user_role();
        $user_id = get_current_user_id();

        // Auto-detect features
        $is_users_table = (strpos($this->table, 'saw_users') !== false);
        // ✅ FIX: Robustní detekce tabulky poboček (entity name OR table name)
        $is_branches_table = ($this->config['entity'] === 'branches' || strpos($this->table, 'saw_branches') !== false);
        
        $allow_global = !empty($this->config['allow_global_in_branch_view']) || $is_users_table;

        // 1. ZÍSKÁNÍ KONTEXTU PŘÍMO Z DB
        $user_ctx = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, branch_id, context_customer_id, context_branch_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d",
            $user_id
        ), ARRAY_A);

        $sql_where = "";
        $params = [];

        // 2. LOGIKA PRO ADMINA A SUPER ADMINA (SWITCHER)
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
            } 
            elseif ($role === 'super_admin') {
                // Fallback meta
                $active_customer_id = (int) get_user_meta($user_id, 'saw_context_customer_id', true);
                $active_branch_id = (int) get_user_meta($user_id, 'saw_context_branch_id', true);
            }

            // A) Filtr Zákazníka
            if ($this->table_has_column('customer_id') && $active_customer_id > 0) {
                if ($role === 'super_admin' && $is_users_table) {
                    $sql_where .= " AND (customer_id = %d OR customer_id IS NULL)";
                } else {
                    $sql_where .= " AND customer_id = %d";
                }
                $params[] = $active_customer_id;
            }

            // B) Filtr Pobočky
            if ($active_branch_id > 0) {
                if ($is_branches_table) {
                    // ✅ Pokud jsme v modulu poboček, filtrujeme podle ID
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

        // 3. LOGIKA PRO OSTATNÍ ROLE (Manager, SuperManager)
        if (!class_exists('SAW_Permissions')) return ['', []];
        
        $permission = SAW_Permissions::get_permission($role, $this->config['entity'], 'list');
        if (!$permission) return [' AND 1=0', []];

        switch ($permission['scope']) {
            case 'branch':
                // Super Manager -> Pevná pobočka
                $fixed_branch = (int)($user_ctx['branch_id'] ?? 0);
                
                if ($fixed_branch) {
                    if ($is_branches_table) {
                        // ✅ FIX: Super Manager vidí v seznamu poboček jen tu svou
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
                    // Nemá pobočku -> nic, nebo jen globální
                    if ($allow_global && $this->table_has_column('branch_id')) {
                        $sql_where .= " AND branch_id IS NULL";
                    } else {
                        $sql_where = " AND 1=0";
                    }
                }
                break;
                
            case 'department':
                // Manager
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
                // Terminal
                if ($this->table_has_column('created_by')) { $sql_where .= " AND created_by = %d"; $params[] = $user_id; }
                elseif ($this->table_has_column('user_id')) { $sql_where .= " AND user_id = %d"; $params[] = $user_id; }
                elseif ($this->table_has_column('wp_user_id')) { $sql_where .= " AND wp_user_id = %d"; $params[] = $user_id; }
                break;
        }

        return [$sql_where, $params];
    }
    
    // =========================================================================
    // CACHE KEYS
    // =========================================================================
    protected function get_cache_key_with_scope($type, $identifier = '') {
        $key = 'saw_' . $this->config['entity'] . '_' . $type;
        $role = $this->get_current_user_role();
        $key .= '_role_' . ($role ?: 'guest');
        
        if (is_user_logged_in()) {
            global $wpdb;
            $ctx = $wpdb->get_row($wpdb->prepare("SELECT context_customer_id, context_branch_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d", get_current_user_id()));
            if ($ctx) {
                $key .= '_cc' . ($ctx->context_customer_id ?? 0) . '_cb' . ($ctx->context_branch_id ?? 0);
            } elseif ($role === 'super_admin') {
                $mc = get_user_meta(get_current_user_id(), 'saw_context_customer_id', true);
                $mb = get_user_meta(get_current_user_id(), 'saw_context_branch_id', true);
                $key .= '_mcc' . ($mc ?: 0) . '_mcb' . ($mb ?: 0);
            }
        }
        
        if (is_array($identifier)) $key .= '_' . md5(serialize($identifier));
        elseif ($identifier) $key .= '_' . $identifier;
        
        $v = get_option('saw_' . $this->config['entity'] . '_cache_version', 1);
        return $key . '_v' . $v;
    }

    // --- HELPERS (UNCHANGED) ---
    protected function table_has_column($column_name) {
        static $column_cache = [];
        $key = $this->table . '_' . $column_name;
        if (isset($column_cache[$key])) return $column_cache[$key];
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
        if (current_user_can('manage_options')) return 'super_admin';
        if (class_exists('SAW_Context')) return SAW_Context::get_role();
        return null;
    }

    protected function get_current_department_ids() {
        global $wpdb;
        if (!class_exists('SAW_Context')) return [];
        $saw_user_id = SAW_Context::get_saw_user_id();
        if (!$saw_user_id) return [];
        $department_ids = $wpdb->get_col($wpdb->prepare("SELECT department_id FROM %i WHERE user_id = %d", $wpdb->prefix . 'saw_user_departments', $saw_user_id));
        return array_map('intval', $department_ids);
    }

    // Abstract & Protected methods for compatibility
    abstract public function validate($data, $id = 0);
    protected function get_cache_key($type, $identifier = '') { return $this->get_cache_key_with_scope($type, $identifier); }
    protected function get_accessible_branch_ids() { return []; } 
    protected function get_current_customer_id() { return SAW_Context::get_customer_id(); }
    protected function get_current_branch_id() { return SAW_Context::get_branch_id(); }

    protected function get_cache($key) {
        if (!($this->config['cache']['enabled'] ?? true)) return false;
        $group = 'saw_' . $this->config['entity'];
        $cached = wp_cache_get($key, $group);
        if ($cached !== false) return $cached;
        $cached = get_transient($key);
        if ($cached !== false) { wp_cache_set($key, $cached, $group, 300); return $cached; }
        return false;
    }
    
    protected function set_cache($key, $data) {
        if (!($this->config['cache']['enabled'] ?? true)) return false;
        $ttl = $this->config['cache']['ttl'] ?? $this->cache_ttl;
        $group = 'saw_' . $this->config['entity'];
        wp_cache_set($key, $data, $group, min($ttl, 300));
        return set_transient($key, $data, $ttl);
    }
    
    protected function invalidate_cache() {
        $version_key = 'saw_' . $this->config['entity'] . '_cache_version';
        $version = get_option($version_key, 1);
        update_option($version_key, $version + 1);
        wp_cache_flush_group('saw_' . $this->config['entity']);
    }
}