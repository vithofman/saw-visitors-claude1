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
     * TOTAL: 33 tables
     */
    private static $tables_order = array(
        // Core (4)
        'customers',
        'customer_api_keys',
        'users',
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
     * Phase 1: Tables without foreign keys
     * Phase 2: Add foreign keys via ALTER TABLE
     * 
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
     * @return bool
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
     * @return bool
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
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // department_materials
            array('table' => 'department_materials', 'constraint' => 'fk_deptmat_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'department_materials', 'constraint' => 'fk_deptmat_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // department_documents
            array('table' => 'department_documents', 'constraint' => 'fk_deptdoc_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'department_documents', 'constraint' => 'fk_deptdoc_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // contact_persons
            array('table' => 'contact_persons', 'constraint' => 'fk_contact_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // companies
            array('table' => 'companies', 'constraint' => 'fk_company_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // invitations
            array('table' => 'invitations', 'constraint' => 'fk_inv_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'invitations', 'constraint' => 'fk_inv_company', 'column' => 'company_id', 'ref_table' => 'companies', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'invitations', 'constraint' => 'fk_inv_manager', 'column' => 'responsible_manager_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // invitation_departments
            array('table' => 'invitation_departments', 'constraint' => 'fk_invdept_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'invitation_departments', 'constraint' => 'fk_invdept_inv', 'column' => 'invitation_id', 'ref_table' => 'invitations', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'invitation_departments', 'constraint' => 'fk_invdept_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // materials
            array('table' => 'materials', 'constraint' => 'fk_material_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // documents
            array('table' => 'documents', 'constraint' => 'fk_document_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // uploaded_docs
            array('table' => 'uploaded_docs', 'constraint' => 'fk_upload_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visitors
            array('table' => 'visitors', 'constraint' => 'fk_visitor_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visitors', 'constraint' => 'fk_visitor_company', 'column' => 'company_id', 'ref_table' => 'companies', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'visitors', 'constraint' => 'fk_visitor_riskdoc', 'column' => 'risk_document_id', 'ref_table' => 'uploaded_docs', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // visits
            array('table' => 'visits', 'constraint' => 'fk_visit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_invitation', 'column' => 'invitation_id', 'ref_table' => 'invitations', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'visits', 'constraint' => 'fk_visit_checkinby', 'column' => 'check_in_by', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'visits', 'constraint' => 'fk_visit_checkoutby', 'column' => 'check_out_by', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // audit_log
            array('table' => 'audit_log', 'constraint' => 'fk_audit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'audit_log', 'constraint' => 'fk_audit_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // sessions
            array('table' => 'sessions', 'constraint' => 'fk_session_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'sessions', 'constraint' => 'fk_session_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // password_resets
            array('table' => 'password_resets', 'constraint' => 'fk_pwreset_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'password_resets', 'constraint' => 'fk_pwreset_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
        );
        
        foreach ($foreign_keys as $fk) {
            $table = $prefix . $fk['table'];
            $ref_table = $prefix . $fk['ref_table'];
            
            // Check if FK already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND CONSTRAINT_NAME = %s 
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                DB_NAME,
                $table,
                $fk['constraint']
            ));
            
            if ($exists > 0) {
                continue;
            }
            
            $sql = "ALTER TABLE `{$table}` 
                    ADD CONSTRAINT `{$fk['constraint']}` 
                    FOREIGN KEY (`{$fk['column']}`) 
                    REFERENCES `{$ref_table}`(`{$fk['ref_column']}`) 
                    ON DELETE {$fk['on_delete']}";
            
            $result = $wpdb->query($sql);
            
            if ($result !== false) {
                $added_count++;
            }
        }
        
        error_log("[SAW Database] Phase 2: {$added_count} foreign keys added");
        
        return true;
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
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_table_name
        ));
        
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
            $wpdb->query("DROP TABLE IF EXISTS `{$full_table_name}`");
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
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$full_table_name}` WHERE customer_id = %d",
                $customer_id
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table_name}`");
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
        
        $columns = $wpdb->get_col($wpdb->prepare(
            "SHOW COLUMNS FROM `{$full_table_name}` LIKE %s",
            'customer_id'
        ));
        
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
        
        $result = $wpdb->query("TRUNCATE TABLE `{$full_table_name}`");
        
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