<?php
/**
 * SAW Database Helper
 *
 * Utility class for database operations - helper methods for queries,
 * security, multi-language support, and customer isolation.
 *
 * @package SAW_Visitors
 * @version 4.6.1
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Database Helper Class
 *
 * Provides utility methods for safe database operations including:
 * - Multi-language content management
 * - Customer isolation verification
 * - Safe CRUD operations
 * - Database statistics
 *
 * @since 4.6.1
 */
class SAW_Database_Helper {
    
    /**
     * Get list of all tables in dependency order
     *
     * Total: 20 tables
     *
     * @since 4.6.1
     * @return array Table names (without 'saw_' prefix)
     */
    public static function get_tables_order() {
        return array(
            // Core (4)
            'customers',
            'companies',
            'branches',
            'account_types',
            
            // Users & Auth (4)
            'users',
            'sessions',
            'password_resets',
            'contact_persons',
            
            // Departments & Relations (3)
            'departments',
            'user_branches',
            'user_departments',
            
            // Permissions (1)
            'permissions',
            
            // Training System (6)
            'training_languages',
            'training_language_branches',
            'training_document_types',
            'training_content',
            'training_department_content',
            'training_documents',
            
            // System Logs (2)
            'audit_log',
            'error_log',
        );
    }
    
    /**
     * Get full table name with prefix
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix (e.g. 'customers')
     * @return string Full table name with prefix (e.g. 'wp_saw_customers')
     */
    public static function get_table_name($table_name) {
        global $wpdb;
        return $wpdb->prefix . 'saw_' . $table_name;
    }
    
    /**
     * Check if table exists
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @return bool True if table exists
     */
    public static function table_exists($table_name) {
        global $wpdb;
        $full_name = self::get_table_name($table_name);
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_name
        ));
        
        return $result === $full_name;
    }
    
    /**
     * Get all languages used in system for customer
     *
     * Checks training_languages table for active languages.
     *
     * @since 4.6.1
     * @param int $customer_id Customer ID
     * @return array Language codes ['cs', 'en', 'de', ...]
     */
    public static function get_customer_languages($customer_id) {
        global $wpdb;
        
        $languages = array();
        
        // Training languages
        if (self::table_exists('training_languages')) {
            $langs = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT language_code FROM %i 
                WHERE customer_id = %d AND is_active = 1",
                self::get_table_name('training_languages'),
                $customer_id
            ));
            $languages = array_merge($languages, $langs);
        }
        
        return array_unique(array_filter($languages));
    }
    
    /**
     * Get all training document types
     *
     * Returns all document types ordered by sort_order.
     *
     * @since 4.6.1
     * @return array Array of document type objects
     */
    public static function get_document_types() {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i ORDER BY sort_order ASC",
            self::get_table_name('training_document_types')
        ));
    }
    
    /**
     * Safe insert of training language
     *
     * Creates new training language record or returns existing ID.
     *
     * @since 4.6.1
     * @param array $data Data to insert (customer_id, language_code, language_name required)
     * @return int|false Inserted ID or false on error
     */
    public static function insert_training_language($data) {
        global $wpdb;
        
        // Validate required fields
        $required = array('customer_id', 'language_code', 'language_name');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        // Check if language already exists for this customer
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i 
            WHERE customer_id = %d AND language_code = %s",
            self::get_table_name('training_languages'),
            $data['customer_id'],
            $data['language_code']
        ));
        
        if ($exists) {
            return $exists;
        }
        
        // Insert new language
        $result = $wpdb->insert(
            self::get_table_name('training_languages'),
            array(
                'customer_id'   => $data['customer_id'],
                'language_code' => $data['language_code'],
                'language_name' => $data['language_name'],
                'is_active'     => $data['is_active'] ?? 1,
            ),
            array('%d', '%s', '%s', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update training language
     *
     * Updates existing training language with new data.
     * Only updates fields that are present in $data array.
     *
     * @since 4.6.1
     * @param int   $id   Record ID
     * @param array $data Data to update
     * @return int|false Number of rows updated or false on error
     */
    public static function update_training_language($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        // Whitelist of allowed fields
        $allowed_fields = array(
            'language_code' => '%s',
            'language_name' => '%s',
            'is_active'     => '%d',
        );
        
        // Build update data from allowed fields only
        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $formats[] = $format;
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            self::get_table_name('training_languages'),
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Assign training language to branch
     *
     * Creates relationship between training language and branch.
     *
     * @since 4.6.1
     * @param int $training_language_id Training language ID
     * @param int $branch_id Branch ID
     * @return int|false Inserted ID or false on error/already exists
     */
    public static function assign_language_to_branch($training_language_id, $branch_id) {
        global $wpdb;
        
        // Check if already assigned
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i 
            WHERE training_language_id = %d AND branch_id = %d",
            self::get_table_name('training_language_branches'),
            $training_language_id,
            $branch_id
        ));
        
        if ($exists) {
            return $exists;
        }
        
        $result = $wpdb->insert(
            self::get_table_name('training_language_branches'),
            array(
                'training_language_id' => $training_language_id,
                'branch_id'            => $branch_id,
            ),
            array('%d', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Remove training language from branch
     *
     * Deletes relationship between training language and branch.
     *
     * @since 4.6.1
     * @param int $training_language_id Training language ID
     * @param int $branch_id Branch ID
     * @return bool True on success, false on error
     */
    public static function remove_language_from_branch($training_language_id, $branch_id) {
        global $wpdb;
        
        return $wpdb->delete(
            self::get_table_name('training_language_branches'),
            array(
                'training_language_id' => $training_language_id,
                'branch_id'            => $branch_id,
            ),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Get branches for training language
     *
     * Returns all branches assigned to specific training language.
     *
     * @since 4.6.1
     * @param int $training_language_id Training language ID
     * @return array Array of branch IDs
     */
    public static function get_language_branches($training_language_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT branch_id FROM %i WHERE training_language_id = %d",
            self::get_table_name('training_language_branches'),
            $training_language_id
        ));
    }
    
    /**
     * Verify customer access to record
     *
     * CRITICAL: Always check customer_id before delete/update!
     * Prevents cross-customer data access.
     *
     * @since 4.6.1
     * @param string $table_name  Table name without prefix
     * @param int    $id          Record ID
     * @param int    $customer_id Customer ID
     * @return bool True if customer owns the record
     */
    public static function verify_customer_access($table_name, $id, $customer_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM %i WHERE id = %d",
            self::get_table_name($table_name),
            $id
        ));
        
        return (int) $result === (int) $customer_id;
    }
    
    /**
     * Safe delete with customer_id verification
     *
     * Secure deletion - prevents cross-customer data leak.
     * Verifies customer ownership before deletion.
     *
     * @since 4.6.1
     * @param string $table_name  Table name without prefix
     * @param int    $id          Record ID
     * @param int    $customer_id Customer ID
     * @return bool True on successful deletion, false on error or access denied
     */
    public static function safe_delete($table_name, $id, $customer_id) {
        global $wpdb;
        
        // Verify customer access first
        if (!self::verify_customer_access($table_name, $id, $customer_id)) {
            return false;
        }
        
        return $wpdb->delete(
            self::get_table_name($table_name),
            array(
                'id'          => $id,
                'customer_id' => $customer_id,
            ),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Get record count in table
     *
     * Optionally filtered by customer_id if column exists.
     *
     * @since 4.6.1
     * @param string   $table_name  Table name without prefix
     * @param int|null $customer_id Optional customer filter
     * @return int Record count
     */
    public static function get_table_count($table_name, $customer_id = null) {
        global $wpdb;
        
        $full_table_name = self::get_table_name($table_name);
        
        if ($customer_id && self::has_customer_id_column($table_name)) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE customer_id = %d",
                $full_table_name,
                $customer_id
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i",
                $full_table_name
            ));
        }
        
        return (int) $count;
    }
    
    /**
     * Check if table has customer_id column
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @return bool True if column exists
     */
    public static function has_customer_id_column($table_name) {
        global $wpdb;
        
        $full_table_name = self::get_table_name($table_name);
        
        $columns = $wpdb->get_col($wpdb->prepare(
            "SHOW COLUMNS FROM %i LIKE %s",
            $full_table_name,
            'customer_id'
        ));
        
        return !empty($columns);
    }
    
    /**
     * Safe get record by ID with customer verification
     *
     * Retrieves single record with customer isolation check.
     *
     * @since 4.6.1
     * @param string $table_name  Table name without prefix
     * @param int    $id          Record ID
     * @param int    $customer_id Customer ID
     * @return object|null Record object or null if not found/access denied
     */
    public static function get_by_id($table_name, $id, $customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i 
            WHERE id = %d AND customer_id = %d",
            self::get_table_name($table_name),
            $id,
            $customer_id
        ));
    }
    
    /**
     * Get all records for customer
     *
     * Retrieves all records belonging to specific customer.
     * Supports ordering and limiting results.
     *
     * @since 4.6.1
     * @param string      $table_name  Table name without prefix
     * @param int         $customer_id Customer ID
     * @param string|null $order_by    ORDER BY clause (e.g. 'created_at DESC')
     * @param int|null    $limit       Limit number of records
     * @return array Array of record objects
     */
    public static function get_all_by_customer($table_name, $customer_id, $order_by = null, $limit = null) {
        global $wpdb;
        
        // Build base query
        $sql = $wpdb->prepare(
            "SELECT * FROM %i WHERE customer_id = %d",
            self::get_table_name($table_name),
            $customer_id
        );
        
        // Add ORDER BY if specified (with whitelist for security)
        if ($order_by) {
            $order_by = self::sanitize_order_by($order_by);
            if ($order_by) {
                $sql .= " ORDER BY " . $order_by;
            }
        }
        
        // Add LIMIT if specified
        if ($limit) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Sanitize ORDER BY clause
     *
     * Prevents SQL injection in ORDER BY by validating against whitelist.
     * Only allows safe column names and directions.
     *
     * @since 4.6.1
     * @param string $order_by ORDER BY clause to sanitize
     * @return string|null Sanitized ORDER BY or null if invalid
     */
    private static function sanitize_order_by($order_by) {
        // Common safe columns
        $allowed_columns = array(
            'id', 'name', 'title', 'created_at', 'updated_at',
            'is_active', 'status', 'sort_order', 'language',
            'email', 'first_name', 'last_name', 'phone',
            'language_code', 'language_name', 'address', 'city',
            'postal_code', 'country', 'contact_email', 'contact_phone',
            'description', 'position', 'is_primary', 'uploaded_at',
            'file_name', 'file_size', 'mime_type'
        );
        
        // Allowed directions
        $allowed_directions = array('ASC', 'DESC');
        
        // Parse ORDER BY clause
        $parts = explode(' ', trim($order_by));
        
        if (count($parts) > 2) {
            return null; // Too many parts
        }
        
        $column = $parts[0];
        $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
        
        // Validate column
        if (!in_array($column, $allowed_columns, true)) {
            return null;
        }
        
        // Validate direction
        if (!in_array($direction, $allowed_directions, true)) {
            return null;
        }
        
        return $column . ' ' . $direction;
    }
    
    /**
     * Debug: Describe table structure
     *
     * Returns table column information for debugging.
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @return array Column information
     */
    public static function describe_table($table_name) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "DESCRIBE %i",
            self::get_table_name($table_name)
        ));
    }
    
    /**
     * Get database statistics
     *
     * Returns statistics for all tables, optionally filtered by customer.
     *
     * @since 4.6.1
     * @param int|null $customer_id Optional customer filter
     * @return array Statistics array with table counts
     */
    public static function get_database_stats($customer_id = null) {
        $stats = array(
            'total_tables' => 0,
            'tables' => array(),
        );
        
        foreach (self::get_tables_order() as $table_name) {
            if (self::table_exists($table_name)) {
                $stats['total_tables']++;
                $stats['tables'][$table_name] = array(
                    'count' => self::get_table_count($table_name, $customer_id),
                    'has_customer_id' => self::has_customer_id_column($table_name),
                );
            }
        }
        
        return $stats;
    }
}