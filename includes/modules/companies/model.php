<?php
/**
 * Companies Module Model
 * 
 * Handles all database operations for the Companies module including
 * CRUD operations, validation, customer isolation, and caching.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Companies Model Class
 * 
 * Extends SAW_Base_Model to provide company-specific functionality
 * including validation and data formatting.
 * 
 * @since 1.0.0
 */
class SAW_Module_Companies_Model extends SAW_Base_Model 
{
    /**
     * Constructor - Initialize model with config
     * 
     * Sets up table name, configuration, and cache TTL.
     * 
     * @since 1.0.0
     * @param array $config Module configuration array
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate company data
     * 
     * Validates all required fields and business rules:
     * - customer_id must be present
     * - branch_id must be present
     * - name must be present
     * - email must be valid if provided
     * - website must be valid URL if provided
     * - IČO must be unique within customer (if provided)
     * 
     * @since 1.0.0
     * @param array $data Company data to validate
     * @param int $id Company ID (for update validation, 0 for create)
     * @return bool|WP_Error True if valid, WP_Error if validation fails
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        // Customer ID validation
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        // Branch ID validation
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        // Name validation
        if (empty($data['name'])) {
            $errors['name'] = 'Company name is required';
        }
        
        // Email validation (if provided)
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        // Website validation (if provided)
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Invalid website URL';
        }
        
        // IČO uniqueness check (if provided)
        if (!empty($data['ico']) && $this->ico_exists($data['customer_id'], $data['ico'], $id)) {
            $errors['ico'] = 'Company with this IČO already exists';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Check if IČO already exists
     * 
     * Verifies uniqueness of IČO within the same customer.
     * Empty IČO values are considered valid (not checked).
     * 
     * @since 1.0.0
     * @param int $customer_id Customer ID
     * @param string $ico IČO to check
     * @param int $exclude_id Company ID to exclude from check (for updates)
     * @return bool True if IČO exists, false otherwise
     */
    private function ico_exists($customer_id, $ico, $exclude_id = 0) {
        global $wpdb;
        
        // Empty IČO is allowed (not unique constraint)
        if (empty($ico)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND ico = %s AND id != %d",
            $this->table,
            $customer_id,
            $ico,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Get company by ID with formatting and isolation check
     * 
     * Retrieves a single company record by ID, validates customer isolation,
     * and formats the data for display (status labels, dates).
     * Uses transient cache with 5 minute TTL.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @return array|null Company data or null if not found/no access
     */
    public function get_by_id($id) {
        // Try cache first
        $cache_key = sprintf('saw_companies_item_%d', $id);
        $item = get_transient($cache_key);
        
        if ($item === false) {
            // Cache miss - fetch from database
            $item = parent::get_by_id($id);
            
            if ($item) {
                // Cache for 5 minutes
                set_transient($cache_key, $item, $this->cache_ttl);
            }
        }
        
        if (!$item) {
            return null;
        }
        
        // Customer isolation check
        $current_customer_id = SAW_Context::get_customer_id();
        
        // Super admin can see all companies
        if (!current_user_can('manage_options')) {
            if (empty($item['customer_id']) || $item['customer_id'] != $current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[COMPANIES] Isolation violation - Item customer: %s, Current: %s',
                        $item['customer_id'] ?? 'NULL',
                        $current_customer_id ?? 'NULL'
                    ));
                }
                return null;
            }
        }
        
        // Format archived status
        $item['is_archived_label'] = !empty($item['is_archived']) ? 'Archivováno' : 'Aktivní';
        $item['is_archived_badge_class'] = !empty($item['is_archived']) ? 'saw-badge saw-badge-secondary' : 'saw-badge saw-badge-success';
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Get all companies - COMPLETE OVERRIDE with forced filtering
     * 
     * CRITICAL: This method COMPLETELY BYPASSES parent::get_all() and apply_data_scope()
     * to ensure ALL users (including super_admin) are filtered by customer_id and branch_id.
     * 
     * @since 1.0.0
     * @param array $filters Query filters
     * @return array ['items' => array, 'total' => int]
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        // ✅ ALWAYS require customer_id (even for super_admin)
        if (!$customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        // Build cache key
        $cache_key = sprintf(
            'saw_companies_list_%d_%s_%s',
            $customer_id,
            $branch_id ? $branch_id : 'all',
            md5(serialize($filters))
        );
        
        // Try cache first
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // ✅ Build query with FORCED customer and branch filtering
        $sql = $wpdb->prepare("SELECT * FROM %i WHERE customer_id = %d", $this->table, $customer_id);
        $params = array();
        
        // ✅ FORCE branch filter if branch is selected
        if ($branch_id) {
            $sql .= " AND branch_id = %d";
            $params[] = $branch_id;
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $search_fields = $this->config['list_config']['searchable'] ?? array('name');
            $search_conditions = array();
            
            foreach ($search_fields as $field) {
                $search_conditions[] = "`{$field}` LIKE %s";
            }
            
            if (!empty($search_conditions)) {
                $search_value = '%' . $wpdb->esc_like($filters['search']) . '%';
                foreach ($search_conditions as $condition) {
                    $params[] = $search_value;
                }
                $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
            }
        }
        
        // is_archived filter
        if (isset($filters['is_archived']) && $filters['is_archived'] !== '') {
            $sql .= " AND is_archived = %d";
            $params[] = intval($filters['is_archived']);
        }
        
        // Count total
        $count_sql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, ...$params);
        }
        $total = (int) $wpdb->get_var($count_sql);
        
        // Ordering
        $orderby = $filters['orderby'] ?? 'name';
        $order = strtoupper($filters['order'] ?? 'ASC');
        
        if (!in_array($orderby, array('id', 'name', 'ico', 'city', 'created_at'), true)) {
            $orderby = 'name';
        }
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY `{$orderby}` {$order}";
        
        // Pagination
        $page = isset($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        // Execute query
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $result = array(
            'items' => $items ?: array(),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $per_page > 0 ? ceil($total / $per_page) : 0,
        );
        
        // Cache for 5 minutes
        set_transient($cache_key, $result, $this->cache_ttl);
        
        return $result;
    }
    
    /**
     * Create new company with cache invalidation
     * 
     * Creates a new company record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param array $data Company data
     * @return int|WP_Error New company ID or error
     */
    public function create($data) {
        $result = parent::create($data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Update existing company with cache invalidation
     * 
     * Updates a company record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @param array $data Company data
     * @return bool|WP_Error True on success, error on failure
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Delete company with cache invalidation
     * 
     * Deletes a company record and invalidates relevant caches.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete($id) {
        $result = parent::delete($id);
        
        if (!is_wp_error($result)) {
            $this->invalidate_item_cache($id);
            $this->invalidate_list_cache();
        }
        
        return $result;
    }
    
    /**
     * Invalidate item cache
     * 
     * Removes cached data for a specific company.
     * 
     * @since 1.0.0
     * @param int $id Company ID
     * @return void
     */
    private function invalidate_item_cache($id) {
        $cache_key = sprintf('saw_companies_item_%d', $id);
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate list cache
     * 
     * Removes all cached company lists.
     * Uses wildcard pattern to clear all list variations.
     * 
     * @since 1.0.0
     * @return void
     */
    private function invalidate_list_cache() {
        global $wpdb;
        
        // Delete all company list caches (wildcard)
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_companies_list_%' 
             OR option_name LIKE '_transient_timeout_saw_companies_list_%'"
        );
    }
}