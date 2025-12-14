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
        
        // Phase 4: Add missing columns to existing tables (migrations)
        self::add_missing_columns();
        
        update_option('saw_db_version', '4.6.1');
        
        $duration = round(microtime(true) - $start_time, 2);
        error_log("[SAW Installer] Completed in {$duration}s");
        
        return $error_count === 0;
    }
    
    /**
     * Get tables order based on dependencies
     *
     * Tables are ordered to respect foreign key dependencies.
     * Total: 30 tables
     *
     * @since 4.6.2
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
            
            // OOPP System (5)
            'oopp_groups',
            'oopp',
            'oopp_translations',
            'oopp_branches',
            'oopp_departments',
            
            // Visitor Training System (6)
            'visits',
	        'visit_schedules',
            'visitors',
            'visit_hosts',
            'visit_daily_logs',
            'visitor_certificates',
            'visit_invitation_materials',
            'visit_action_info',
            'visit_action_documents',
            'visit_action_oopp',

	        // Notifications
	        'notifications',
            
            // Email logs
            'email_logs',
            
            // System Logs (2)
            'audit_log',
            'error_log',
            
            // UI Translations
            'ui_languages',
            'ui_translations',
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
            
            // customers
            array('table' => 'customers', 'constraint' => 'fk_customers_account_type', 'column' => 'account_type_id', 'ref_table' => 'account_types', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // users
            array('table' => 'users', 'constraint' => 'fk_user_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'users', 'constraint' => 'fk_user_acctype', 'column' => 'account_type_id', 'ref_table' => 'account_types', 'ref_column' => 'id', 'on_delete' => 'RESTRICT'),
            
            // sessions
            array('table' => 'sessions', 'constraint' => 'fk_session_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'sessions', 'constraint' => 'fk_session_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // password_resets
            array('table' => 'password_resets', 'constraint' => 'fk_pwreset_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'password_resets', 'constraint' => 'fk_pwreset_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // contact_persons
            array('table' => 'contact_persons', 'constraint' => 'fk_contact_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'contact_persons', 'constraint' => 'fk_contact_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // departments
            array('table' => 'departments', 'constraint' => 'fk_dept_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'departments', 'constraint' => 'fk_dept_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // user_branches
            array('table' => 'user_branches', 'constraint' => 'fk_ub_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_branches', 'constraint' => 'fk_ub_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // user_departments
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'user_departments', 'constraint' => 'fk_userdept_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // permissions
            array('table' => 'permissions', 'constraint' => 'fk_perm_acctype', 'column' => 'account_type_id', 'ref_table' => 'account_types', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_languages
            array('table' => 'training_languages', 'constraint' => 'fk_trainlang_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_language_branches
            array('table' => 'training_language_branches', 'constraint' => 'fk_trainlangbranch_lang', 'column' => 'language_id', 'ref_table' => 'training_languages', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_language_branches', 'constraint' => 'fk_trainlangbranch_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_content
            array('table' => 'training_content', 'constraint' => 'fk_training_content_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_content', 'constraint' => 'fk_training_content_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_content', 'constraint' => 'fk_training_content_language', 'column' => 'language_id', 'ref_table' => 'training_languages', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_department_content
            array('table' => 'training_department_content', 'constraint' => 'fk_dept_content_training', 'column' => 'training_content_id', 'ref_table' => 'training_content', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'training_department_content', 'constraint' => 'fk_dept_content_department', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // training_documents
            array('table' => 'training_documents', 'constraint' => 'fk_training_doc_type', 'column' => 'document_type_id', 'ref_table' => 'training_document_types', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // visits
            array('table' => 'visits', 'constraint' => 'fk_visit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visits', 'constraint' => 'fk_visit_company', 'column' => 'company_id', 'ref_table' => 'companies', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
	    // visit_schedules - ✅ UPDATED: přidány customer a branch FKs
	    array('table' => 'visit_schedules', 'constraint' => 'fk_schedule_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
	    array('table' => 'visit_schedules', 'constraint' => 'fk_schedule_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
	    array('table' => 'visit_schedules', 'constraint' => 'fk_schedule_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),

            // visitors
            array('table' => 'visitors', 'constraint' => 'fk_visitor_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visitors', 'constraint' => 'fk_visitor_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'RESTRICT'),
            array('table' => 'visitors', 'constraint' => 'fk_visitor_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'RESTRICT'),
            
            // visit_hosts
            array('table' => 'visit_hosts', 'constraint' => 'fk_host_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_hosts', 'constraint' => 'fk_host_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_hosts', 'constraint' => 'fk_host_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_hosts', 'constraint' => 'fk_host_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_daily_logs - ✅ UPDATED: přidány customer a branch FKs
            array('table' => 'visit_daily_logs', 'constraint' => 'fk_daily_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_daily_logs', 'constraint' => 'fk_daily_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_daily_logs', 'constraint' => 'fk_daily_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_daily_logs', 'constraint' => 'fk_daily_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visitor_certificates - ✅ PŘIDÁNO: customer a branch FKs
            array('table' => 'visitor_certificates', 'constraint' => 'fk_cert_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visitor_certificates', 'constraint' => 'fk_cert_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visitor_certificates', 'constraint' => 'fk_cert_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_invitation_materials
            array('table' => 'visit_invitation_materials', 'constraint' => 'fk_invitation_materials_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_invitation_materials', 'constraint' => 'fk_invitation_materials_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_invitation_materials', 'constraint' => 'fk_invitation_materials_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_action_info
            array('table' => 'visit_action_info', 'constraint' => 'fk_action_info_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_action_info', 'constraint' => 'fk_action_info_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_action_info', 'constraint' => 'fk_action_info_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_action_documents
            array('table' => 'visit_action_documents', 'constraint' => 'fk_action_docs_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_action_documents', 'constraint' => 'fk_action_docs_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // visit_action_oopp
            array('table' => 'visit_action_oopp', 'constraint' => 'fk_action_oopp_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'visit_action_oopp', 'constraint' => 'fk_action_oopp_oopp', 'column' => 'oopp_id', 'ref_table' => 'oopp', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
	        // notifications
	        array('table' => 'notifications', 'constraint' => 'fk_notif_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
	        array('table' => 'notifications', 'constraint' => 'fk_notif_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
	        array('table' => 'notifications', 'constraint' => 'fk_notif_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
	        array('table' => 'notifications', 'constraint' => 'fk_notif_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
	        array('table' => 'notifications', 'constraint' => 'fk_notif_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),

            // email_logs
            array('table' => 'email_logs', 'constraint' => 'fk_email_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'email_logs', 'constraint' => 'fk_email_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'email_logs', 'constraint' => 'fk_email_visit', 'column' => 'visit_id', 'ref_table' => 'visits', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            array('table' => 'email_logs', 'constraint' => 'fk_email_visitor', 'column' => 'visitor_id', 'ref_table' => 'visitors', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),

            // audit_log
            array('table' => 'audit_log', 'constraint' => 'fk_audit_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'audit_log', 'constraint' => 'fk_audit_user', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // error_log
            array('table' => 'error_log', 'constraint' => 'fk_error_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'SET NULL'),
            
            // oopp
            array('table' => 'oopp', 'constraint' => 'fk_oopp_customer', 'column' => 'customer_id', 'ref_table' => 'customers', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'oopp', 'constraint' => 'fk_oopp_group', 'column' => 'group_id', 'ref_table' => 'oopp_groups', 'ref_column' => 'id', 'on_delete' => 'RESTRICT'),
            
            // oopp_branches
            array('table' => 'oopp_branches', 'constraint' => 'fk_oopp_branches_oopp', 'column' => 'oopp_id', 'ref_table' => 'oopp', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'oopp_branches', 'constraint' => 'fk_oopp_branches_branch', 'column' => 'branch_id', 'ref_table' => 'branches', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            
            // oopp_departments
            array('table' => 'oopp_departments', 'constraint' => 'fk_oopp_depts_oopp', 'column' => 'oopp_id', 'ref_table' => 'oopp', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
            array('table' => 'oopp_departments', 'constraint' => 'fk_oopp_depts_dept', 'column' => 'department_id', 'ref_table' => 'departments', 'ref_column' => 'id', 'on_delete' => 'CASCADE'),
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
        
        // Insert OOPP groups (static data)
        self::insert_oopp_groups();
    }
    
    /**
     * Insert OOPP groups (static data)
     * 
     * 8 skupin dle nařízení vlády č. 390/2021 Sb.
     *
     * @since 4.6.1
     * @return void
     */
    private static function insert_oopp_groups() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        $table = $prefix . 'oopp_groups';
        
        // Check if already populated
        $count = $wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($table));
        
        if ($count > 0) {
            return;
        }
        
        $groups = [
            ['code' => 'I',    'name' => 'Prostředky k ochraně hlavy', 'display_order' => 1],
            ['code' => 'II',   'name' => 'Prostředky k ochraně sluchu', 'display_order' => 2],
            ['code' => 'III',  'name' => 'Prostředky k ochraně očí a obličeje', 'display_order' => 3],
            ['code' => 'IV',   'name' => 'Prostředky k ochraně dýchacích orgánů', 'display_order' => 4],
            ['code' => 'V',    'name' => 'Prostředky k ochraně rukou a paží', 'display_order' => 5],
            ['code' => 'VI',   'name' => 'Prostředky k ochraně nohou a ochraně před uklouznutím', 'display_order' => 6],
            ['code' => 'VII',  'name' => 'Prostředky k ochraně pokožky', 'display_order' => 7],
            ['code' => 'VIII', 'name' => 'Prostředky k ochraně těla a/nebo další ochraně pokožky', 'display_order' => 8],
        ];
        
        foreach ($groups as $group) {
            $wpdb->insert($table, $group, ['%s', '%s', '%d']);
        }
        
        error_log('[SAW Installer] Inserted ' . count($groups) . ' OOPP groups');
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
     * Add missing columns to existing tables
     * 
     * Handles database migrations by adding new columns to existing tables.
     * 
     * @since 4.6.2
     * @return void
     */
    private static function add_missing_columns() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        // Add risks_status column to visits table if it doesn't exist
        if (self::table_exists('visits')) {
            $visits_table = $prefix . 'visits';
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'risks_status'",
                DB_NAME,
                $visits_table
            ));
            
            if (!$column_exists) {
                $wpdb->query("ALTER TABLE {$visits_table} 
                    ADD COLUMN risks_status ENUM('pending', 'completed', 'missing') DEFAULT 'pending' 
                    COMMENT 'pending = čeká se na rizika (před dnem návštěvy), completed = rizika nahraná, missing = rizika chybí (v den návštěvy nebo později)'
                    AFTER risks_document_name");
                
                error_log("[SAW Installer] Added risks_status column to visits table");
                
                // Update existing records based on current state
                // ⭐ FIX: Použít WordPress current_time místo CURDATE() pro správné časové pásmo
                $today = current_time('Y-m-d');
                $wpdb->query($wpdb->prepare("UPDATE {$visits_table} v
                    LEFT JOIN {$wpdb->prefix}saw_visit_invitation_materials m 
                        ON v.id = m.visit_id AND m.material_type = 'text'
                    SET v.risks_status = CASE
                        WHEN (v.risks_text IS NOT NULL AND v.risks_text != '') 
                             OR (v.risks_document_path IS NOT NULL AND v.risks_document_path != '')
                             OR m.id IS NOT NULL
                        THEN 'completed'
                        WHEN v.planned_date_from IS NULL OR v.planned_date_from > %s
                        THEN 'pending'
                        ELSE 'missing'
                    END", $today));
                
                error_log("[SAW Installer] Updated risks_status for existing visits");
            }
        }
        
        // Add audit fields (created_by, updated_by) to relevant tables
        self::add_audit_fields_to_tables();
        
        // Add branch_id to audit_log table
        self::add_branch_id_to_audit_log();
        
        // ============================================
        // VISIT ACTION SYSTEM MIGRATION
        // ============================================
        self::add_action_name_to_visits();
        self::add_is_global_to_oopp();
        self::create_action_tables();
    }
    
    /**
     * Add action_name column to visits table
     * 
     * @since 1.0.0
     * @return void
     */
    private static function add_action_name_to_visits() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        if (!self::table_exists('visits')) {
            return;
        }
        
        $visits_table = $prefix . 'visits';
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'action_name'",
            DB_NAME,
            $visits_table
        ));
        
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE {$visits_table} 
                ADD COLUMN action_name VARCHAR(255) NULL 
                COMMENT 'Název akce - krátký identifikátor'
                AFTER company_id");
            
            error_log("[SAW Installer] Added action_name column to visits table");
        }
    }
    
    /**
     * Add is_global column to oopp table
     * 
     * @since 1.0.0
     * @return void
     */
    private static function add_is_global_to_oopp() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        if (!self::table_exists('oopp')) {
            return;
        }
        
        $oopp_table = $prefix . 'oopp';
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'is_global'",
            DB_NAME,
            $oopp_table
        ));
        
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE {$oopp_table} 
                ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 1 
                COMMENT '1 = zobrazuje se všem, 0 = pouze při přiřazení k návštěvě'
                AFTER is_active");
            
            // Add index
            $wpdb->query("CREATE INDEX idx_global ON {$oopp_table} (customer_id, is_global, is_active)");
            
            // Set all existing OOPP to global (default)
            $wpdb->query("UPDATE {$oopp_table} SET is_global = 1 WHERE is_global IS NULL");
            
            error_log("[SAW Installer] Added is_global column to oopp table");
        }
    }
    
    /**
     * Create new action-related tables
     * 
     * @since 1.0.0
     * @return void
     */
    private static function create_action_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $prefix = $wpdb->prefix . 'saw_';
        $charset_collate = $wpdb->get_charset_collate();
        $schemas_dir = dirname(__FILE__) . '/schemas/';
        
        $new_tables = [
            'visit_action_info',
            'visit_action_documents',
            'visit_action_oopp',
        ];
        
        foreach ($new_tables as $table_name) {
            $full_table_name = $prefix . $table_name;
            
            // Skip if exists
            if (self::table_exists($table_name)) {
                continue;
            }
            
            $schema_file = $schemas_dir . 'schema-' . str_replace('_', '-', $table_name) . '.php';
            
            if (!file_exists($schema_file)) {
                error_log("[SAW Installer] Schema file not found: {$schema_file}");
                continue;
            }
            
            require_once $schema_file;
            
            $function_name = 'saw_get_schema_' . $table_name;
            
            if (!function_exists($function_name)) {
                error_log("[SAW Installer] Function not found: {$function_name}");
                continue;
            }
            
            $sql = $function_name($full_table_name, $prefix, $charset_collate);
            
            // Remove FK constraints for dbDelta (they'll be added in Phase 2)
            $sql_clean = preg_replace('/,\s*CONSTRAINT\s+\w+\s+FOREIGN\s+KEY[^,]+/i', '', $sql);
            
            dbDelta($sql_clean);
            
            if (self::table_exists($table_name)) {
                error_log("[SAW Installer] Created table: {$full_table_name}");
            } else {
                error_log("[SAW Installer] ERROR creating table: {$full_table_name}");
            }
        }
    }
    
    /**
     * Add audit fields (created_by, updated_by) to all relevant tables
     * 
     * @since 1.0.0
     * @return void
     */
    private static function add_audit_fields_to_tables() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        // List of tables that should have audit fields
        $tables = array(
            'oopp',
            'branches',
            'departments',
            'visitors',
            'visits',
            'companies',
            'contact_persons',
            'training_documents',
            'training_content',
            'training_document_types',
            'training_languages',
            'visit_hosts',
            'oopp_groups',
        );
        
        foreach ($tables as $table_name) {
            $full_table = $prefix . $table_name;
            
            // Skip if table doesn't exist
            if (!self::table_exists($table_name)) {
                continue;
            }
            
            // Check if created_by exists
            $created_by_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'created_by'",
                DB_NAME,
                $full_table
            ));
            
            // For visits table, check for created_by_email instead (since created_by already exists as BIGINT)
            if ($table_name === 'visits') {
                $created_by_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = %s 
                     AND TABLE_NAME = %s 
                     AND COLUMN_NAME = 'created_by_email'",
                    DB_NAME,
                    $full_table
                ));
                
                if (!$created_by_exists) {
                    $wpdb->query("ALTER TABLE {$full_table} 
                        ADD COLUMN created_by_email VARCHAR(255) NULL 
                        COMMENT 'Email uživatele, který vytvořil záznam'
                        AFTER created_by");
                    error_log("[SAW Installer] Added created_by_email column to {$table_name} table");
                }
            } else {
                if (!$created_by_exists) {
                    // Determine position - after updated_at if it exists, otherwise after created_at
                    $after_column = 'created_at';
                    $updated_at_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s 
                         AND TABLE_NAME = %s 
                         AND COLUMN_NAME = 'updated_at'",
                        DB_NAME,
                        $full_table
                    ));
                    
                    if ($updated_at_exists) {
                        $after_column = 'updated_at';
                    }
                    
                    $wpdb->query("ALTER TABLE {$full_table} 
                        ADD COLUMN created_by VARCHAR(255) NULL 
                        COMMENT 'Email uživatele, který vytvořil záznam'
                        AFTER {$after_column}");
                    error_log("[SAW Installer] Added created_by column to {$table_name} table");
                }
            }
            
            // Check if updated_by exists
            $updated_by_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'updated_by'",
                DB_NAME,
                $full_table
            ));
            
            if (!$updated_by_exists) {
                // Position after created_by or created_by_email
                $after_column = ($table_name === 'visits') ? 'created_by_email' : 'created_by';
                $after_column_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = %s 
                     AND TABLE_NAME = %s 
                     AND COLUMN_NAME = %s",
                    DB_NAME,
                    $full_table,
                    $after_column
                ));
                
                // Fallback to updated_at if created_by doesn't exist
                if (!$after_column_exists) {
                    $updated_at_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s 
                         AND TABLE_NAME = %s 
                         AND COLUMN_NAME = 'updated_at'",
                        DB_NAME,
                        $full_table
                    ));
                    $after_column = $updated_at_exists ? 'updated_at' : 'created_at';
                }
                
                $wpdb->query("ALTER TABLE {$full_table} 
                    ADD COLUMN updated_by VARCHAR(255) NULL 
                    COMMENT 'Email uživatele, který naposledy aktualizoval záznam'
                    AFTER {$after_column}");
                error_log("[SAW Installer] Added updated_by column to {$table_name} table");
            }
        }
    }
    
    /**
     * Add branch_id column to audit_log table
     * 
     * @since 1.0.0
     * @return void
     */
    private static function add_branch_id_to_audit_log() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'saw_audit_log';
        
        // Check if branch_id column exists
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'branch_id'",
            DB_NAME,
            $table_name
        ));
        
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE {$table_name} 
                ADD COLUMN branch_id BIGINT(20) UNSIGNED DEFAULT NULL
                AFTER customer_id");
            error_log("[SAW Installer] Added branch_id column to audit_log table");
        }
        
        // Check if index exists
        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND INDEX_NAME = 'idx_branch'",
            DB_NAME,
            $table_name
        ));
        
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE {$table_name} 
                ADD INDEX idx_branch (branch_id)");
            error_log("[SAW Installer] Added idx_branch index to audit_log table");
        }
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