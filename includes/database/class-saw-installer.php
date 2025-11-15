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
     * - Phase 3: Insert default data
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
            $sql = preg_replace('/,\s*CONSTRAINT\s+\w+\s+FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+[^\s]+\s*\([^)]+\)(\s+ON\s+DELETE\s+\w+)?(\s+ON\s+UPDATE\s+\w+)?/i', '', $sql);
            
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
        
        // Phase 3: Insert default data
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
     * Total: 25 tables
     *
     * @since 4.6.1
     * @return array Table names (without 'saw_' prefix)
     */
    private static function get_tables_order() {
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
            
            // Visitor Training System (5)
            'visits',
            'visitors',
            'visit_hosts',
            'visit_daily_logs',
            'visitor_certificates',
            
            // System Logs (2)
            'audit_log',
            'error_log',
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
            // companies
            array('table' => 'companies', 'constraint' => 'fk_company_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // branches
            array('table' => 'branches', 'constraint' => 'fk_branch_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // account_types
            array('table' => 'account_types', 'constraint' => 'fk_acctype_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // users
            array('table' => 'users', 'constraint' => 'fk_user_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'users', 'constraint' => 'fk_user_acctype', 'column' => 'account_type_id', 'ref_table' => 'account_types', 'ref_column' => 'id', 'on_delete' => 'RESTRICT'),
            
            // sessions
            array('table' => 'sessions', 'constraint' => 'fk_session_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // password_resets
            array('table' => 'password_resets', 'constraint' => 'fk_pwreset_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // contact_persons
            array('table' => 'contact_persons', 'constraint' => 'fk_contact_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'contact_persons', 'constraint' => 'fk_contact_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // departments
            array('table' => 'departments', 'constraint' => 'fk_dept_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'departments', 'constraint' => 'fk_dept_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // user_branches
            array('table' => 'user_branches', 'constraint' => 'fk_userbranch_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_branches', 'constraint' => 'fk_userbranch_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // user_departments
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // permissions
            array('table' => 'permissions', 'constraint' => 'fk_perm_acctype', 'column' => 'account_type_id', 'ref_table' => 'account_types', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_languages
            array('table' => 'training_languages', 'constraint' => 'fk_trainlang_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_language_branches
            array('table' => 'training_language_branches', 'constraint' => 'fk_trainlangbranch_lang', 'column' => 'training_language_id', 'ref_table' => 'training_languages', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_language_branches', 'constraint' => 'fk_trainlangbranch_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_content
            array('table' => 'training_content', 'constraint' => 'fk_training_content_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_content', 'constraint' => 'fk_training_content_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_content', 'constraint' => 'fk_training_content_language', 'column' => 'language_id', 'ref_table' => 'training_languages', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_department_content
            array('table' => 'training_department_content', 'constraint' => 'fk_dept_content_training', 'column' => 'training_content_id', 'ref_table' => 'training_content', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_department_content', 'constraint' => 'fk_dept_content_department', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_documents
            array('table' => 'training_documents', 'constraint' => 'fk_training_doc_type', 'column' => 'document_type_id', 'ref_table' => 'training_document_types', 'ref_column' => 'id', 'on_delete' => 'RESTRICT'),
            
            // visits
            array('table' => 'visits', 'constraint' => 'fk_visit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_company', 'column' => 'company_id', 'ref_table' => 'companies', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // visitors
            array('table' => 'visitors', 'constraint' => 'fk_visitor_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_hosts
            array('table' => 'visit_hosts', 'constraint' => 'fk_host_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_hosts', 'constraint' => 'fk_host_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_daily_logs
            array('table' => 'visit_daily_logs', 'constraint' => 'fk_daily_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_daily_logs', 'constraint' => 'fk_daily_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visitor_certificates
            array('table' => 'visitor_certificates', 'constraint' => 'fk_cert_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // audit_log
            array('table' => 'audit_log', 'constraint' => 'fk_audit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'audit_log', 'constraint' => 'fk_audit_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
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
     * Insert default data
     *
     * Creates demo customer, default account type, and training document types.
     *
     * @since 4.6.1
     * @return void
     */
    private static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        // Insert demo customer if needed
        $customer_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i",
            $prefix . 'customers'
        ));
        
        if ($customer_exists == 0) {
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
            
            // Insert default account type
            if ($customer_id && self::table_exists('account_types')) {
                $wpdb->insert(
                    $prefix . 'account_types',
                    array(
                        'customer_id' => $customer_id,
                        'name'        => 'Administrátor',
                        'description' => 'Výchozí administrátorský účet s plným přístupem',
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }
        
        // Insert training document types if table is empty
        if (self::table_exists('training_document_types')) {
            $types_exist = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i",
                $prefix . 'training_document_types'
            ));
            
            if ($types_exist == 0) {
                $document_types = array(
                    array('name' => 'Dokumentace BOZP', 'description' => 'Dokumenty týkající se bezpečnosti a ochrany zdraví při práci', 'sort_order' => 1),
                    array('name' => 'Dokumentace PO', 'description' => 'Dokumenty požární ochrany a prevence', 'sort_order' => 2),
                    array('name' => 'Dokumentace rizik', 'description' => 'Identifikace a hodnocení rizik na pracovišti', 'sort_order' => 3),
                    array('name' => 'Pokyny BOZP a PO', 'description' => 'Pracovní a bezpečnostní pokyny pro zaměstnance', 'sort_order' => 4),
                    array('name' => 'Místní provozní bezpečnostní předpis', 'description' => 'Interní bezpečnostní předpisy specifické pro pracoviště', 'sort_order' => 5),
                    array('name' => 'Požární řád', 'description' => 'Základní dokument upravující požární ochranu v objektu', 'sort_order' => 6),
                    array('name' => 'Požární poplachové směrnice', 'description' => 'Postupy při vzniku požáru a evakuaci', 'sort_order' => 7),
                    array('name' => 'Požární evakuační plán', 'description' => 'Grafické znázornění únikových cest a shromažďovacích míst', 'sort_order' => 8),
                    array('name' => 'Dokumentace prevence havárií', 'description' => 'Dokumenty pro předcházení závažným haváriím', 'sort_order' => 9),
                    array('name' => 'Dokumentace k ochraně životního prostředí', 'description' => 'Dokumenty týkající se ochrany životního prostředí a nakládání s odpady', 'sort_order' => 10),
                    array('name' => 'Ostatní dokumenty', 'description' => 'Další relevantní dokumenty nespadající do předchozích kategorií', 'sort_order' => 11),
                );
                
                foreach ($document_types as $type) {
                    $wpdb->insert(
                        $prefix . 'training_document_types',
                        $type,
                        array('%s', '%s', '%d')
                    );
                }
                
                error_log("[SAW Installer] Phase 3: Inserted 11 training document types");
            }
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