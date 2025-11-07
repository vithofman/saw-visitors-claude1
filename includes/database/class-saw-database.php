<?php
/**
 * SAW Database Management
 *
 * Utility functions for database operations.
 * Handles table creation in two phases: tables first, then foreign keys.
 *
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Database Manager
 *
 * Manages database schema creation, foreign key relationships, and utility functions
 * for database operations. Uses two-phase approach to avoid circular dependency issues.
 *
 * @since 4.6.1
 */
class SAW_Database {

    /**
     * All tables (without 'saw_' prefix)
     *
     * Ordered by dependencies (foreign keys).
     * Total: 34 tables
     *
     * @since 4.6.1
     * @var array
     */
    private static $tables_order = array(
        // Core (4)
        'customers',
        'customer_api_keys',
        'users',
        'permissions',
        'training_config',
        'account_types',
        
        // POI System (8)
        'beacons',
        'pois',
        'routes',
        'route_pois',
        'poi_content',
        'poi_media',
        'poi_pdfs',
        'poi_risks',
        'poi_additional_info',
        
        // Multi-tenant Core (5)
        'departments',
        'user_departments',
        'user_branches',
        'department_materials',
        'department_documents',
        'contact_persons',
        'branches',

        // Training language
        'training_languages',
        'training_language_branches',
        
        // Visitor Management (8)
        'companies',
        'invitations',
        'invitation_departments',
        'materials',
        'documents',
        'uploaded_docs',
        'visitors',
        'visits',
        
        // System (5)
        'audit_log',
        'error_log',
        'rate_limits',
        'sessions',
        'password_resets',
        'email_queue',
    );

    /**
     * Create all tables in 2 phases
     *
     * Phase 1: Tables without foreign keys
     * Phase 2: Add foreign keys via ALTER TABLE
     *
     * @since 4.6.1
     * @return bool True on success, false on error
     */
    public static function create_tables() {
        global $wpdb;
        
        $start_time = microtime(true);
        error_log('[SAW Database] Starting table creation...');
        
        // Phase 1: Create tables without FK constraints
        $created = self::create_tables_without_fk();
        
        if (!$created) {
            error_log('[SAW Database] ERROR: Failed to create tables');
            return false;
        }
        
        // Phase 2: Add foreign keys
        $fk_added = self::add_foreign_keys();
        
        $duration = round(microtime(true) - $start_time, 2);
        error_log("[SAW Database] Completed in {$duration}s");
        
        return $fk_added;
    }

    /**
     * Phase 1: Create tables without foreign keys (fast)
     *
     * Iterates through all tables, loads schema, removes FK constraints,
     * and creates tables using WordPress dbDelta().
     *
     * @since 4.6.1
     * @return bool True if no errors occurred
     */
    private static function create_tables_without_fk() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $prefix = $wpdb->prefix . 'saw_';
        $charset_collate = $wpdb->get_charset_collate();
        $schema_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/database/schemas/';
        
        $created_count = 0;
        $error_count = 0;
        
        foreach (self::$tables_order as $table_name) {
            if (self::table_exists($table_name)) {
                $created_count++;
                continue;
            }
            
            $schema_file = $schema_dir . 'schema-' . str_replace('_', '-', $table_name) . '.php';
            
            if (!file_exists($schema_file)) {
                error_log("[SAW Database] Schema missing: {$schema_file}");
                $error_count++;
                continue;
            }
            
            require_once $schema_file;
            
            $function_name = 'saw_get_schema_' . $table_name;
            
            if (!function_exists($function_name)) {
                error_log("[SAW Database] Function missing: {$function_name}");
                $error_count++;
                continue;
            }
            
            $full_table_name = $prefix . $table_name;
            
            if ($table_name === 'users') {
                $sql = $function_name($full_table_name, $prefix, $wpdb->users, $charset_collate);
            } else {
                $sql = $function_name($full_table_name, $prefix, $charset_collate);
            }
            
            // Remove CONSTRAINT lines (FK will be added later)
            // Pattern removes: comma + whitespace + CONSTRAINT ... ON DELETE/UPDATE ... (newline or comma)
            $sql = preg_replace('/,\s*CONSTRAINT\s+\w+\s+FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+[^\s]+\s*\([^)]+\)\s*(ON\s+(DELETE|UPDATE)\s+\w+(\s+\w+)?)+/i', '', $sql);
            
            dbDelta($sql);
            
            if (self::table_exists($table_name)) {
                $created_count++;
            } else {
                error_log("[SAW Database] ERROR creating: {$full_table_name}");
                $error_count++;
            }
        }
        
        error_log("[SAW Database] Phase 1: {$created_count} tables created, {$error_count} errors");
        
        return $error_count === 0;
    }

    /**
     * Phase 2: Add foreign keys via ALTER TABLE
     *
     * Adds all foreign key constraints after tables are created.
     * Prevents circular dependency issues during table creation.
     *
     * @since 4.6.1
     * @return bool True if no errors occurred
     */
    private static function add_foreign_keys() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        $added_count = 0;
        
        $foreign_keys = array(
            // customer_api_keys
            array('table' => 'customer_api_keys', 'constraint' => 'fk_apikey_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // users
            array('table' => 'users', 'constraint' => 'fk_user_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // beacons
            array('table' => 'beacons', 'constraint' => 'fk_beacon_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // pois
            array('table' => 'pois', 'constraint' => 'fk_poi_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'pois', 'constraint' => 'fk_poi_beacon', 'column' => 'beacon_id', 'ref_table' => 'beacons', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // routes
            array('table' => 'routes', 'constraint' => 'fk_route_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // route_pois
            array('table' => 'route_pois', 'constraint' => 'fk_routepoi_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'route_pois', 'constraint' => 'fk_routepoi_route', 'column' => 'route_id', 'ref_table' => 'routes', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'route_pois', 'constraint' => 'fk_routepoi_poi', 'column' => 'poi_id', 'ref_table' => 'pois', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // poi_content
            array('table' => 'poi_content', 'constraint' => 'fk_poicontent_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'poi_content', 'constraint' => 'fk_poicontent_poi', 'column' => 'poi_id', 'ref_table' => 'pois', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // poi_media
            array('table' => 'poi_media', 'constraint' => 'fk_poimedia_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'poi_media', 'constraint' => 'fk_poimedia_poi', 'column' => 'poi_id', 'ref_table' => 'pois', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // poi_pdfs
            array('table' => 'poi_pdfs', 'constraint' => 'fk_poipdf_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'poi_pdfs', 'constraint' => 'fk_poipdf_poi', 'column' => 'poi_id', 'ref_table' => 'pois', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // poi_risks
            array('table' => 'poi_risks', 'constraint' => 'fk_poirisk_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'poi_risks', 'constraint' => 'fk_poirisk_poi', 'column' => 'poi_id', 'ref_table' => 'pois', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // poi_additional_info
            array('table' => 'poi_additional_info', 'constraint' => 'fk_poiinfo_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'poi_additional_info', 'constraint' => 'fk_poiinfo_poi', 'column' => 'poi_id', 'ref_table' => 'pois', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // departments
            array('table' => 'departments', 'constraint' => 'fk_dept_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // user_departments
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // user_branches
            array('table' => 'user_branches', 'constraint' => 'fk_userbranch_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_branches', 'constraint' => 'fk_userbranch_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // department_materials
            array('table' => 'department_materials', 'constraint' => 'fk_deptmat_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'department_materials', 'constraint' => 'fk_deptmat_material', 'column' => 'material_id', 'ref_table' => 'materials', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // department_documents
            array('table' => 'department_documents', 'constraint' => 'fk_deptdoc_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'department_documents', 'constraint' => 'fk_deptdoc_document', 'column' => 'document_id', 'ref_table' => 'documents', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // contact_persons
            array('table' => 'contact_persons', 'constraint' => 'fk_contact_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // branches
            array('table' => 'branches', 'constraint' => 'fk_branch_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_languages
            array('table' => 'training_languages', 'constraint' => 'fk_trainlang_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_language_branches
            array('table' => 'training_language_branches', 'constraint' => 'fk_trainlangbranch_language', 'column' => 'language_id', 'ref_table' => 'training_languages', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_language_branches', 'constraint' => 'fk_trainlangbranch_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // companies
            array('table' => 'companies', 'constraint' => 'fk_company_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // invitations
            array('table' => 'invitations', 'constraint' => 'fk_invitation_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'invitations', 'constraint' => 'fk_invitation_company', 'column' => 'company_id', 'ref_table' => 'companies', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'invitations', 'constraint' => 'fk_invitation_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // invitation_departments
            array('table' => 'invitation_departments', 'constraint' => 'fk_invdept_invitation', 'column' => 'invitation_id', 'ref_table' => 'invitations', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'invitation_departments', 'constraint' => 'fk_invdept_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // materials
            array('table' => 'materials', 'constraint' => 'fk_material_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // documents
            array('table' => 'documents', 'constraint' => 'fk_document_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // uploaded_docs
            array('table' => 'uploaded_docs', 'constraint' => 'fk_uploaded_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'uploaded_docs', 'constraint' => 'fk_uploaded_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visitors
            array('table' => 'visitors', 'constraint' => 'fk_visitor_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visitors', 'constraint' => 'fk_visitor_company', 'column' => 'company_id', 'ref_table' => 'companies', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // visits
            array('table' => 'visits', 'constraint' => 'fk_visit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_invitation', 'column' => 'invitation_id', 'ref_table' => 'invitations', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // audit_log
            array('table' => 'audit_log', 'constraint' => 'fk_audit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // error_log
            array('table' => 'error_log', 'constraint' => 'fk_error_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // sessions
            array('table' => 'sessions', 'constraint' => 'fk_session_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // password_resets
            array('table' => 'password_resets', 'constraint' => 'fk_pwreset_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
        );
        
        foreach ($foreign_keys as $fk) {
            $table = $prefix . $fk['table'];
            $ref_table = $prefix . $fk['ref_table'];
            
            // Check if FK already exists
            if (self::foreign_key_exists($fk['table'], $fk['constraint'])) {
                $added_count++;
                continue;
            }
            
            $sql = $wpdb->prepare(
                "ALTER TABLE %i ADD CONSTRAINT %i 
                 FOREIGN KEY (%i) REFERENCES %i(%i) 
                 ON DELETE %s",
                $table,
                $fk['constraint'],
                $fk['column'],
                $ref_table,
                $fk['ref_column'],
                $fk['on_delete']
            );
            
            $wpdb->query($sql);
            
            if (self::foreign_key_exists($fk['table'], $fk['constraint'])) {
                $added_count++;
            }
        }
        
        error_log("[SAW Database] Phase 2: {$added_count} foreign keys added");
        
        return true;
    }

    /**
     * Check if table exists
     *
     * @since 4.6.1
     * @param string $table_name Table name without 'saw_' prefix
     * @return bool True if table exists
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_table_name
        ));
        
        return $result === $full_table_name;
    }

    /**
     * Check if foreign key exists
     *
     * @since 4.6.1
     * @param string $table_name   Table name without 'saw_' prefix
     * @param string $constraint   Constraint name
     * @return bool True if FK exists
     */
    public static function foreign_key_exists($table_name, $constraint) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        $database = DB_NAME;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM information_schema.TABLE_CONSTRAINTS 
             WHERE CONSTRAINT_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND CONSTRAINT_NAME = %s 
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            $database,
            $full_table_name,
            $constraint
        ));
        
        return $result > 0;
    }

    /**
     * Get table version
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @return int Table version number
     */
    public static function get_table_version($table_name) {
        $option_name = 'saw_table_version_' . $table_name;
        return (int) get_option($option_name, 1);
    }

    /**
     * Update table version
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @param int    $version    New version number
     * @return bool True on success
     */
    public static function update_table_version($table_name, $version) {
        $option_name = 'saw_table_version_' . $table_name;
        return update_option($option_name, $version);
    }

    /**
     * Check all tables - which are missing
     *
     * @since 4.6.1
     * @return array Missing table names
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
     * Drop all plugin tables
     *
     * WARNING: Permanently deletes all plugin data!
     * Used during plugin uninstallation.
     *
     * @since 4.6.1
     * @return bool True on success
     */
    public static function drop_all_tables() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        
        // Disable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        
        // Drop in reverse order
        $tables_reverse = array_reverse(self::$tables_order);
        
        foreach ($tables_reverse as $table_name) {
            $full_table_name = $prefix . $table_name;
            
            // Use prepared statement for table name
            $wpdb->query($wpdb->prepare(
                "DROP TABLE IF EXISTS %i",
                $full_table_name
            ));
        }
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        
        // Delete version options
        foreach (self::$tables_order as $table_name) {
            delete_option('saw_table_version_' . $table_name);
        }
        
        return true;
    }

    /**
     * Get row count in table
     *
     * Optionally filtered by customer_id if column exists.
     *
     * @since 4.6.1
     * @param string   $table_name  Table name without prefix
     * @param int|null $customer_id Optional customer ID filter
     * @return int Row count
     */
    public static function get_table_count($table_name, $customer_id = null) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
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
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $columns = $wpdb->get_col($wpdb->prepare(
            "SHOW COLUMNS FROM %i LIKE %s",
            $full_table_name,
            'customer_id'
        ));
        
        return !empty($columns);
    }

    /**
     * Get list of all tables
     *
     * @since 4.6.1
     * @return array Table names (without prefix)
     */
    public static function get_all_tables() {
        return self::$tables_order;
    }

    /**
     * Truncate table (delete all records)
     *
     * WARNING: Destructive operation! Deletes all data.
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @return bool True on success, false if table doesn't exist
     */
    public static function truncate_table($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        if (!self::table_exists($table_name)) {
            return false;
        }
        
        // Disable foreign key checks temporarily
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        
        $result = $wpdb->query($wpdb->prepare(
            "TRUNCATE TABLE %i",
            $full_table_name
        ));
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        
        return $result !== false;
    }

    /**
     * Get table information
     *
     * Returns comprehensive info about a table including row count, size, etc.
     *
     * @since 4.6.1
     * @param string $table_name Table name without prefix
     * @return array|null Table info or null if table doesn't exist
     */
    public static function get_table_info($table_name) {
        global $wpdb;
        
        if (!self::table_exists($table_name)) {
            return null;
        }
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $count = self::get_table_count($table_name);
        
        $size_result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                data_length + index_length as size_bytes,
                data_length,
                index_length
            FROM information_schema.TABLES 
            WHERE table_schema = %s 
            AND table_name = %s",
            DB_NAME,
            $full_table_name
        ), ARRAY_A);
        
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
     * Returns comprehensive database status including all tables info.
     *
     * @since 4.6.1
     * @return array Database status with all tables info
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