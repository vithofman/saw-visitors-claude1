<?php
/**
 * SAW Database Installer
 *
 * Automatic installation of all database tables using WordPress dbDelta().
 * Two-phase installation: tables without FK first, then add foreign keys.
 *
 * @package SAW_Visitors
 * @version 4.6.1
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Installer Class
 *
 * Handles plugin installation, database creation, seed data, and uninstallation.
 * Uses two-phase approach to avoid circular foreign key dependencies.
 *
 * @since 4.6.1
 */
class SAW_Installer {
    
    /**
     * Install plugin database
     *
     * Creates all tables in two phases:
     * - Phase 1: Tables without foreign key constraints
     * - Phase 2: Add foreign key constraints via ALTER TABLE
     *
     * @since 4.6.1
     * @return bool True if installation successful, false on error
     */
    public static function install() {
        global $wpdb;
        
        $start_time = microtime(true);
        error_log('[SAW Installer] Starting installation...');
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $prefix = $wpdb->prefix . 'saw_';
        $charset_collate = $wpdb->get_charset_collate();
        
        $schemas_dir = dirname(__FILE__) . '/schemas/';
        $tables = self::get_tables_order();
        
        // Phase 1: Create tables without FK
        $created_count = 0;
        $error_count = 0;
        
        foreach ($tables as $table_name) {
            if (self::table_exists($table_name)) {
                $created_count++;
                continue;
            }
            
            $schema_file = $schemas_dir . 'schema-' . str_replace('_', '-', $table_name) . '.php';
            
            if (!file_exists($schema_file)) {
                error_log("[SAW Installer] Schema file missing: {$schema_file}");
                $error_count++;
                continue;
            }
            
            require_once $schema_file;
            
            $function_name = 'saw_get_schema_' . $table_name;
            
            if (!function_exists($function_name)) {
                error_log("[SAW Installer] Function missing: {$function_name}");
                $error_count++;
                continue;
            }
            
            $full_table_name = $prefix . $table_name;
            
            if ($table_name === 'users') {
                $sql = $function_name($full_table_name, $prefix, $wpdb->users, $charset_collate);
            } else {
                $sql = $function_name($full_table_name, $prefix, $charset_collate);
            }
            
            // Remove FK constraints for Phase 1
            $sql = preg_replace('/,\s*CONSTRAINT\s+fk_\w+\s+FOREIGN\s+KEY[^,)]+/i', '', $sql);
            
            dbDelta($sql);
            
            if (self::table_exists($table_name)) {
                $created_count++;
            } else {
                error_log("[SAW Installer] ERROR creating: {$full_table_name}");
                $error_count++;
            }
        }
        
        error_log("[SAW Installer] Phase 1: {$created_count} tables created, {$error_count} errors");
        
        // Phase 2: Add foreign keys
        self::add_foreign_keys();
        
        // Insert default data
        self::insert_default_data();
        
        update_option('saw_db_version', '4.6.1');
        
        $duration = round(microtime(true) - $start_time, 2);
        error_log("[SAW Installer] Completed in {$duration}s");
        
        return $error_count === 0;
    }
    
    /**
     * Get tables order based on dependencies
     *
     * Tables are ordered to respect foreign key dependencies.
     * Total: 33 tables
     *
     * @since 4.6.1
     * @return array Table names (without 'saw_' prefix)
     */
    private static function get_tables_order() {
        return array(
            // Core (5)
            'customers',
            'customer_api_keys',
            'users',
            'training_config',
            'account_types',
            
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
            'uploaded_docs',
            'visitors',
            'visits',
            'materials',
            'documents',
            
            // System (6)
            'audit_log',
            'error_log',
            'sessions',
            'password_resets',
            'rate_limits',
            'email_queue',
        );
    }
    
    /**
     * Add foreign keys via ALTER TABLE
     *
     * Second phase of installation: adds all foreign key constraints
     * after tables are created. Prevents circular dependency issues.
     *
     * @since 4.6.1
     * @return bool True on success
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
                $added_count++;
                continue;
            }
            
            // Use prepared statement for ALTER TABLE
            $sql = $wpdb->prepare(
                "ALTER TABLE %i 
                ADD CONSTRAINT %i 
                FOREIGN KEY (%i) 
                REFERENCES %i(%i) 
                ON DELETE %s",
                $table,
                $fk['constraint'],
                $fk['column'],
                $ref_table,
                $fk['ref_column'],
                $fk['on_delete']
            );
            
            $result = $wpdb->query($sql);
            
            if ($result !== false) {
                $added_count++;
            }
        }
        
        error_log("[SAW Installer] Phase 2: {$added_count} foreign keys added");
        
        return true;
    }
    
    /**
     * Insert default data (optional)
     *
     * Creates demo customer and training config if database is empty.
     * Only runs if no customers exist.
     *
     * @since 4.6.1
     * @return void
     */
    private static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        $customer_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i",
            $prefix . 'customers'
        ));
        
        if ($customer_exists > 0) {
            return;
        }
        
        // Insert demo customer
        $wpdb->insert(
            $prefix . 'customers',
            array(
                'name'          => 'Demo Zákazník',
                'ico'           => '12345678',
                'address'       => 'Demo ulice 123, 100 00 Praha',
                'primary_color' => '#1e40af',
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        $customer_id = $wpdb->insert_id;
        
        // Insert training config if table exists
        if ($customer_id && self::table_exists('training_config')) {
            $wpdb->insert(
                $prefix . 'training_config',
                array(
                    'customer_id'         => $customer_id,
                    'training_version'    => 1,
                    'skip_threshold_days' => 365,
                ),
                array('%d', '%d', '%d')
            );
        }
    }
    
    /**
     * Check if table exists
     *
     * @since 4.6.1
     * @param string $table_name Table name (without 'saw_' prefix)
     * @return bool True if table exists
     */
    private static function table_exists($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'saw_' . $table_name;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_table_name
        ));
        
        return $result === $full_table_name;
    }
    
    /**
     * Uninstall plugin
     *
     * Drops all plugin tables from database.
     * WARNING: This is a destructive operation that deletes all data!
     *
     * @since 4.6.1
     * @return void
     */
    public static function uninstall() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        $tables = array_reverse(self::get_tables_order());
        
        // Disable foreign key checks temporarily
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        
        foreach ($tables as $table_name) {
            $full_table_name = $prefix . $table_name;
            
            // Use prepared statement for DROP TABLE
            $wpdb->query($wpdb->prepare(
                "DROP TABLE IF EXISTS %i",
                $full_table_name
            ));
        }
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        
        // Delete version option
        delete_option('saw_db_version');
    }
}