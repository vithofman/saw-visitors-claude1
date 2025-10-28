<?php
/**
 * SAW Database Management
 * Utility functions for database operations
 *
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Database {

    /**
     * All tables (without 'saw_' prefix)
     * Ordered by dependencies (foreign keys)
     */
    private static $tables_order = array(
        // Core (4)
        'customers',
        'customer_api_keys',
        'users',
        'training_config',
        
        // POI System (9)
        'beacons',
        'pois',
        'routes',
        'route_pois',
        'poi_content',
        'poi_media',
        'poi_pdfs',
        'poi_risks',
        'poi_additional_info',
        
        // Multi-tenant Core (6)
        'departments',
        'user_departments',
        'department_materials',
        'department_documents',
        'contact_persons',
        
        // Visitor Management (8)
        'companies',
        'invitations',
        'invitation_departments',
        'materials',
        'documents',
        'uploaded_docs',
        'visitors',
        'visits',
        
        // System (6)
        'audit_log',
        'error_log',
        'rate_limits',
        'sessions',
        'password_resets',
        'email_queue',
    );

    /**
     * Create all tables from schema files
     * 
     * @return bool True on success, false on error
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $prefix = $wpdb->prefix . 'saw_';
        $charset_collate = $wpdb->get_charset_collate();
        
        $schema_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/database/schemas/';
        
        $created_count = 0;
        $error_count = 0;
        
        foreach (self::$tables_order as $table_name) {
            $schema_file = $schema_dir . 'schema-' . str_replace('_', '-', $table_name) . '.php';
            
            if (!file_exists($schema_file)) {
                error_log("[SAW Database] Schema file not found: {$schema_file}");
                $error_count++;
                continue;
            }
            
            require_once $schema_file;
            
            $function_name = 'saw_get_schema_' . $table_name;
            
            if (!function_exists($function_name)) {
                error_log("[SAW Database] Schema function not found: {$function_name}");
                $error_count++;
                continue;
            }
            
            $full_table_name = $prefix . $table_name;
            
            if ($table_name === 'users') {
                $sql = $function_name($full_table_name, $prefix, $wpdb->users, $charset_collate);
            } else {
                $sql = $function_name($full_table_name, $prefix, $charset_collate);
            }
            
            dbDelta($sql);
            
            if (self::table_exists($table_name)) {
                error_log("[SAW Database] ✓ Table created/updated: {$full_table_name}");
                $created_count++;
            } else {
                error_log("[SAW Database] ✗ ERROR creating: {$full_table_name}");
                $error_count++;
            }
        }
        
        error_log("[SAW Database] Result: {$created_count} tables created, {$error_count} errors");
        
        return $error_count === 0;
    }

    /**
     * Check if table exists
     * 
     * @param string $table_name Table name (without prefix)
     * @return bool
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $full_table_name
            )
        );
        
        return $result === $full_table_name;
    }

    /**
     * Get table version
     * 
     * @param string $table_name Table name
     * @return int
     */
    public static function get_table_version($table_name) {
        $option_name = 'saw_table_version_' . $table_name;
        return (int) get_option($option_name, 1);
    }

    /**
     * Update table version
     * 
     * @param string $table_name Table name
     * @param int $version New version
     * @return bool
     */
    public static function update_table_version($table_name, $version) {
        $option_name = 'saw_table_version_' . $table_name;
        return update_option($option_name, $version);
    }

    /**
     * Check all tables - which are missing
     * 
     * @return array Missing tables
     */
    public static function check_all_tables() {
        $missing_tables = array();
        
        foreach (self::$tables_order as $table_name) {
            if (!self::table_exists($table_name)) {
                $missing_tables[] = $table_name;
            }
        }
        
        return $missing_tables;
    }

    /**
     * Drop all tables (for uninstall)
     * WARNING: Destructive operation!
     * 
     * @return bool
     */
    public static function drop_all_tables() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        
        $tables_reverse = array_reverse(self::$tables_order);
        
        foreach ($tables_reverse as $table_name) {
            $full_table_name = $prefix . $table_name;
            $wpdb->query("DROP TABLE IF EXISTS {$full_table_name}");
        }
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        
        foreach (self::$tables_order as $table_name) {
            delete_option('saw_table_version_' . $table_name);
        }
        
        return true;
    }

    /**
     * Get row count in table
     * 
     * @param string $table_name Table name (without prefix)
     * @param int $customer_id Optional filter by customer_id
     * @return int
     */
    public static function get_table_count($table_name, $customer_id = null) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        if ($customer_id && self::has_customer_id_column($table_name)) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$full_table_name} WHERE customer_id = %d",
                    $customer_id
                )
            );
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table_name}");
        }
        
        return (int) $count;
    }

    /**
     * Check if table has customer_id column
     * 
     * @param string $table_name Table name (without prefix)
     * @return bool
     */
    public static function has_customer_id_column($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $columns = $wpdb->get_col(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$full_table_name} LIKE %s",
                'customer_id'
            )
        );
        
        return !empty($columns);
    }

    /**
     * Get list of all tables
     * 
     * @return array Table names (without prefix)
     */
    public static function get_all_tables() {
        return self::$tables_order;
    }

    /**
     * Truncate table (delete all records)
     * WARNING: Destructive operation!
     * 
     * @param string $table_name Table name (without prefix)
     * @return bool
     */
    public static function truncate_table($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        if (!self::table_exists($table_name)) {
            return false;
        }
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        
        $result = $wpdb->query("TRUNCATE TABLE {$full_table_name}");
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        
        return $result !== false;
    }

    /**
     * Get table information
     * 
     * @param string $table_name Table name (without prefix)
     * @return array|null
     */
    public static function get_table_info($table_name) {
        global $wpdb;
        
        if (!self::table_exists($table_name)) {
            return null;
        }
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $count = self::get_table_count($table_name);
        
        $size_result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    data_length + index_length as size_bytes,
                    data_length,
                    index_length
                FROM information_schema.TABLES 
                WHERE table_schema = %s 
                AND table_name = %s",
                DB_NAME,
                $full_table_name
            ),
            ARRAY_A
        );
        
        return array(
            'name' => $table_name,
            'full_name' => $full_table_name,
            'count' => $count,
            'size_bytes' => $size_result ? (int) $size_result['size_bytes'] : 0,
            'data_size' => $size_result ? (int) $size_result['data_length'] : 0,
            'index_size' => $size_result ? (int) $size_result['index_length'] : 0,
            'version' => self::get_table_version($table_name),
        );
    }
    
    /**
     * Get overview of all tables
     * 
     * @return array
     */
    public static function get_database_status() {
        $status = array(
            'total_tables' => count(self::$tables_order),
            'existing_tables' => 0,
            'missing_tables' => array(),
            'tables_info' => array(),
        );
        
        foreach (self::$tables_order as $table_name) {
            if (self::table_exists($table_name)) {
                $status['existing_tables']++;
                $status['tables_info'][$table_name] = self::get_table_info($table_name);
            } else {
                $status['missing_tables'][] = $table_name;
            }
        }
        
        return $status;
    }
}