<?php
/**
 * SAW Table Context - Multi-tenant Helper
 *
 * Provides context filtering for multi-tenant data isolation.
 * Ensures all queries respect customer_id and branch_id boundaries.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Context Class
 *
 * @since 3.0.0
 */
class SAW_Table_Context {
    
    /**
     * Cached context values
     * @var array|null
     */
    private static $cached_context = null;
    
    /**
     * Get current context for queries
     *
     * @return array Context with customer_id and branch_id
     */
    public static function get() {
        if (self::$cached_context !== null) {
            return self::$cached_context;
        }
        
        // Try to get from SAW_Context class
        if (class_exists('SAW_Context')) {
            self::$cached_context = [
                'customer_id' => SAW_Context::get_customer_id(),
                'branch_id' => SAW_Context::get_branch_id(),
            ];
            return self::$cached_context;
        }
        
        // Fallback: get from SAW_Table_Permissions
        if (class_exists('SAW_Table_Permissions')) {
            self::$cached_context = [
                'customer_id' => SAW_Table_Permissions::get_customer_id(),
                'branch_id' => SAW_Table_Permissions::get_branch_id(),
            ];
            return self::$cached_context;
        }
        
        // Ultimate fallback
        self::$cached_context = [
            'customer_id' => null,
            'branch_id' => null,
        ];
        
        return self::$cached_context;
    }
    
    /**
     * Get customer ID
     *
     * @return int|null
     */
    public static function getCustomerId() {
        $context = self::get();
        return $context['customer_id'];
    }
    
    /**
     * Get branch ID
     *
     * @return int|null
     */
    public static function getBranchId() {
        $context = self::get();
        return $context['branch_id'];
    }
    
    /**
     * Check if customer context is set
     *
     * @return bool
     */
    public static function hasCustomer() {
        return self::getCustomerId() !== null;
    }
    
    /**
     * Check if branch context is set
     *
     * @return bool
     */
    public static function hasBranch() {
        return self::getBranchId() !== null;
    }
    
    /**
     * Apply context filter to query arguments
     *
     * @param array $args   Query arguments
     * @param array $config Module configuration
     * @return array Modified arguments
     */
    public static function applyFilter($args, $config) {
        // Check if module should be filtered by customer
        $filter_by_customer = $config['filter_by_customer'] ?? true;
        if ($filter_by_customer !== false) {
            $customer_id = self::getCustomerId();
            if ($customer_id) {
                $args['customer_id'] = $customer_id;
            }
        }
        
        // Check if module should be filtered by branch
        $filter_by_branch = $config['filter_by_branch'] ?? false;
        if ($filter_by_branch !== false && $filter_by_branch !== null) {
            $branch_id = self::getBranchId();
            if ($branch_id) {
                $args['branch_id'] = $branch_id;
            }
        }
        
        return $args;
    }
    
    /**
     * Apply context filter to WHERE clause
     *
     * @param string $where  Existing WHERE clause
     * @param array  $config Module configuration
     * @param string $alias  Table alias (optional)
     * @return string Modified WHERE clause
     */
    public static function applyWhereFilter($where, $config, $alias = '') {
        $prefix = $alias ? "{$alias}." : '';
        $conditions = [];
        
        // Filter by customer
        $filter_by_customer = $config['filter_by_customer'] ?? true;
        if ($filter_by_customer !== false) {
            $customer_id = self::getCustomerId();
            if ($customer_id) {
                $conditions[] = "{$prefix}customer_id = " . intval($customer_id);
            }
        }
        
        // Filter by branch
        $filter_by_branch = $config['filter_by_branch'] ?? false;
        if ($filter_by_branch !== false && $filter_by_branch !== null) {
            $branch_id = self::getBranchId();
            if ($branch_id) {
                $conditions[] = "{$prefix}branch_id = " . intval($branch_id);
            }
        }
        
        if (empty($conditions)) {
            return $where;
        }
        
        $context_where = implode(' AND ', $conditions);
        
        if (empty($where)) {
            return "WHERE {$context_where}";
        }
        
        // Append to existing WHERE
        if (stripos($where, 'WHERE') === 0) {
            return $where . " AND ({$context_where})";
        }
        
        return "WHERE ({$where}) AND ({$context_where})";
    }
    
    /**
     * Generate cache key with context
     *
     * Ensures cached data is properly isolated per tenant.
     *
     * @param string $base_key Base cache key
     * @param array  $config   Module configuration
     * @return string Context-aware cache key
     */
    public static function getCacheKey($base_key, $config = []) {
        $parts = [$base_key];
        
        // Add customer context to key
        $filter_by_customer = $config['filter_by_customer'] ?? true;
        if ($filter_by_customer !== false) {
            $customer_id = self::getCustomerId();
            $parts[] = 'c' . ($customer_id ?? 0);
        }
        
        // Add branch context to key
        $filter_by_branch = $config['filter_by_branch'] ?? false;
        if ($filter_by_branch !== false && $filter_by_branch !== null) {
            $branch_id = self::getBranchId();
            $parts[] = 'b' . ($branch_id ?? 0);
        }
        
        return implode('_', $parts);
    }
    
    /**
     * Check if item belongs to current context
     *
     * @param array $item   Item data
     * @param array $config Module configuration
     * @return bool
     */
    public static function itemBelongsToContext($item, $config = []) {
        // Check customer
        $filter_by_customer = $config['filter_by_customer'] ?? true;
        if ($filter_by_customer !== false) {
            $customer_id = self::getCustomerId();
            if ($customer_id !== null) {
                $item_customer = $item['customer_id'] ?? null;
                if ($item_customer !== null && intval($item_customer) !== intval($customer_id)) {
                    return false;
                }
            }
        }
        
        // Check branch
        $filter_by_branch = $config['filter_by_branch'] ?? false;
        if ($filter_by_branch !== false && $filter_by_branch !== null) {
            $branch_id = self::getBranchId();
            if ($branch_id !== null) {
                $item_branch = $item['branch_id'] ?? null;
                if ($item_branch !== null && intval($item_branch) !== intval($branch_id)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get context description for debugging
     *
     * @return string
     */
    public static function getDescription() {
        $context = self::get();
        $parts = [];
        
        if ($context['customer_id']) {
            $parts[] = "Customer #{$context['customer_id']}";
        }
        
        if ($context['branch_id']) {
            $parts[] = "Branch #{$context['branch_id']}";
        }
        
        if (empty($parts)) {
            return 'No context (global)';
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Clear cached context
     *
     * Useful when context changes during request.
     */
    public static function clearCache() {
        self::$cached_context = null;
    }
    
    /**
     * Override context temporarily
     *
     * Useful for admin operations that need to access
     * data across tenants.
     *
     * @param int|null $customer_id Customer ID
     * @param int|null $branch_id   Branch ID
     */
    public static function override($customer_id = null, $branch_id = null) {
        self::$cached_context = [
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
        ];
    }
    
    /**
     * Execute callback without context filtering
     *
     * Temporarily disables context filtering for the duration
     * of the callback. Useful for admin operations.
     *
     * @param callable $callback Callback to execute
     * @return mixed Callback result
     */
    public static function withoutContext($callback) {
        // Store current context
        $original = self::$cached_context;
        
        // Disable context
        self::$cached_context = [
            'customer_id' => null,
            'branch_id' => null,
        ];
        
        try {
            $result = call_user_func($callback);
        } finally {
            // Restore original context
            self::$cached_context = $original;
        }
        
        return $result;
    }
    
    /**
     * Execute callback with specific context
     *
     * @param int      $customer_id Customer ID
     * @param int|null $branch_id   Branch ID
     * @param callable $callback    Callback to execute
     * @return mixed Callback result
     */
    public static function withContext($customer_id, $branch_id, $callback) {
        // Store current context
        $original = self::$cached_context;
        
        // Set specific context
        self::override($customer_id, $branch_id);
        
        try {
            $result = call_user_func($callback);
        } finally {
            // Restore original context
            self::$cached_context = $original;
        }
        
        return $result;
    }
}
