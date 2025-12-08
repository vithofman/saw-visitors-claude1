<?php
/**
 * Permissions Module Model
 * 
 * Database operations for permissions management.
 * Handles CRUD operations for the permissions table without customer filtering.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Permissions
 * @since       4.10.0
 * @version     2.0.0 - FIXED: Added require_once for base class, translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// REQUIRED BASE CLASS
// ============================================
if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

/**
 * Permissions Model Class
 * 
 * Extends SAW_Base_Model to provide permissions-specific functionality.
 * 
 * @since 4.10.0
 */
class SAW_Module_Permissions_Model extends SAW_Base_Model {
    
    /**
     * Translation function
     * @var callable
     */
    private $tr;
    
    /**
     * Constructor - Initialize model with config
     * 
     * Sets up table name, configuration, and cache TTL.
     * 
     * @since 4.10.0
     * @param array $config Module configuration array
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 3600;
        
        // ============================================
        // TRANSLATIONS SETUP
        // ============================================
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'permissions') 
            : array();
        
        $this->tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
    }
    
    /**
     * Get all permissions with filtering and pagination
     * 
     * Overrides parent method to disable customer filtering since permissions
     * are global. Supports search, filters, sorting, and pagination.
     * 
     * SECURITY: All dynamic SQL values are properly escaped using $wpdb->prepare()
     * 
     * @since 4.10.0
     * @param array $args Query arguments (search, filters, orderby, page, etc.)
     * @return array Array with 'items' and 'total' keys
     */
    public function get_all($args = array()) {
        global $wpdb;
        
        // Start building WHERE conditions
        $where_conditions = array('1=1');
        $prepare_params = array();
        
        // ================================================
        // SEARCH
        // ================================================
        if (!empty($args['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? array('role', 'module', 'action');
            $search_conditions = array();
            
            foreach ($search_fields as $field) {
                // Validate field name (whitelist)
                if (!in_array($field, array('role', 'module', 'action'), true)) {
                    continue;
                }
                $search_conditions[] = $field . ' LIKE %s';
                $prepare_params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            }
            
            if (!empty($search_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
            }
        }
        
        // ================================================
        // FILTERS
        // ================================================
        foreach ($this->config['list_config']['filters'] ?? array() as $filter_key => $enabled) {
            if ($enabled && isset($args[$filter_key]) && $args[$filter_key] !== '') {
                // Validate filter key (whitelist)
                if (!in_array($filter_key, array('role', 'allowed'), true)) {
                    continue;
                }
                $where_conditions[] = $filter_key . ' = %s';
                $prepare_params[] = $args[$filter_key];
            }
        }
        
        // Build WHERE clause
        $where_clause = implode(' AND ', $where_conditions);
        
        // ================================================
        // ORDERING
        // ================================================
        $orderby = $args['orderby'] ?? 'role';
        $order = strtoupper($args['order'] ?? 'ASC');
        
        // Validate orderby (whitelist)
        $allowed_orderby = array('role', 'module', 'action', 'allowed', 'scope');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'role';
        }
        
        // Validate order direction
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'ASC';
        }
        
        // ================================================
        // BUILD MAIN QUERY
        // ================================================
        $base_query = "SELECT * FROM %i WHERE {$where_clause}";
        
        // Prepare base query with table name and parameters
        if (!empty($prepare_params)) {
            $query = $wpdb->prepare(
                $base_query,
                $this->table,
                ...$prepare_params
            );
        } else {
            $query = $wpdb->prepare($base_query, $this->table);
        }
        
        // Add ORDER BY (safe, already validated)
        $query .= " ORDER BY {$orderby} {$order}";
        
        // ================================================
        // COUNT TOTAL RECORDS
        // ================================================
        $count_query = "SELECT COUNT(*) FROM %i WHERE {$where_clause}";
        
        if (!empty($prepare_params)) {
            $count_query_prepared = $wpdb->prepare(
                $count_query,
                $this->table,
                ...$prepare_params
            );
        } else {
            $count_query_prepared = $wpdb->prepare($count_query, $this->table);
        }
        
        $total = (int) $wpdb->get_var($count_query_prepared);
        
        // ================================================
        // PAGINATION
        // ================================================
        $limit = intval($args['per_page'] ?? 50);
        $page = intval($args['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        
        // Add LIMIT and OFFSET (safe, already validated as integers)
        $query .= " LIMIT {$limit} OFFSET {$offset}";
        
        // ================================================
        // EXECUTE QUERY
        // ================================================
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array(
            'items' => $results ?: array(),
            'total' => $total,
        );
    }
    
    /**
     * Validate permission data
     * 
     * Validates all required fields for permission records.
     * 
     * @since 4.10.0
     * @param array $data Permission data to validate
     * @param int $id Permission ID (for update validation, 0 for create)
     * @return bool|WP_Error True if valid, WP_Error if validation fails
     */
    public function validate($data, $id = 0) {
        $errors = array();
        $tr = $this->tr;
        
        // Role validation
        if (empty($data['role'])) {
            $errors['role'] = $tr('validation_role_required', 'Role je povinná');
        }
        
        // Module validation
        if (empty($data['module'])) {
            $errors['module'] = $tr('validation_module_required', 'Modul je povinný');
        }
        
        // Action validation
        if (empty($data['action'])) {
            $errors['action'] = $tr('validation_action_required', 'Akce je povinná');
        }
        
        // Scope validation
        if (empty($data['scope'])) {
            $errors['scope'] = $tr('validation_scope_required', 'Rozsah dat je povinný');
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', $tr('validation_failed', 'Validace selhala'), $errors);
    }
}