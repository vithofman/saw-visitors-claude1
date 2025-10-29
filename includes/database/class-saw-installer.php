<?php
/**
 * SAW Database Installer
 * 
 * Automatická instalace všech tabulek pomocí WordPress dbDelta()
 * 2-fázová instalace: nejdříve tabulky bez FK, pak přidání FK
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Installer {
    
    /**
     * Instalace databáze
     * 
     * @return bool
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
     * Pořadí tabulek podle závislostí
     * TOTAL: 33 tables
     * 
     * @return array
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
        
        error_log("[SAW Installer] Phase 2: {$added_count} foreign keys added");
        
        return true;
    }
    
    /**
     * Vložení výchozích dat (volitelné)
     * 
     * @return void
     */
    private static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        $customer_exists = $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}customers`");
        
        if ($customer_exists > 0) {
            return;
        }
        
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
     * @param string $table_name Table name (without prefix)
     * @return bool
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
     * Deinstalace (smaže všechny tabulky)
     * 
     * POZOR: Toto je destruktivní operace!
     * 
     * @return void
     */
    public static function uninstall() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        $tables = array_reverse(self::get_tables_order());
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        
        foreach ($tables as $table_name) {
            $full_table_name = $prefix . $table_name;
            $wpdb->query("DROP TABLE IF EXISTS `{$full_table_name}`");
        }
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        
        delete_option('saw_db_version');
    }
}