<?php
/**
 * Database management class
 * 
 * Handles database schema creation and updates
 */

namespace SAW_Visitors;

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Database {
    
    private $wpdb;
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Get array of all plugin table names
     * 
     * @return array Table names without prefix
     */
    public static function get_table_names() {
        return [
            'customers',
            'companies',
            'branches',
            'departments',
            'users',
            'user_branches',
            'user_departments',
            'account_types',
            'permissions',
            'sessions',
            'password_resets',
            'contact_persons',
            'audit_log',
            'error_log',
            'training_languages',
            'training_language_branches'
        ];
    }
    
    /**
     * Create all plugin tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Customers table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_customers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            ico varchar(20) DEFAULT NULL,
            address text,
            contact_email varchar(100),
            contact_phone varchar(50),
            logo_url varchar(500),
            primary_color varchar(7) DEFAULT '#1e40af',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ico (ico),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Companies table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_companies (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            name varchar(200) NOT NULL,
            ico varchar(20) DEFAULT NULL,
            address text,
            contact_email varchar(100),
            contact_phone varchar(50),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY ico (ico),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Branches table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_branches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            name varchar(200) NOT NULL,
            address text,
            city varchar(100),
            postal_code varchar(20),
            country varchar(100) DEFAULT 'Česká republika',
            contact_email varchar(100),
            contact_phone varchar(50),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Departments table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_departments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            branch_id bigint(20) unsigned NOT NULL,
            name varchar(200) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY branch_id (branch_id),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Users table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_users (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            account_type_id bigint(20) unsigned NOT NULL,
            email varchar(100) NOT NULL,
            password_hash varchar(255) NOT NULL,
            first_name varchar(100),
            last_name varchar(100),
            phone varchar(50),
            is_active tinyint(1) DEFAULT 1,
            last_login datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY customer_id (customer_id),
            KEY account_type_id (account_type_id),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // User branches (many-to-many relationship)
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_user_branches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            branch_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_branch (user_id, branch_id),
            KEY user_id (user_id),
            KEY branch_id (branch_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // User departments (many-to-many relationship)
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_user_departments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            department_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_department (user_id, department_id),
            KEY user_id (user_id),
            KEY department_id (department_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Account types table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_account_types (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            name varchar(100) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Permissions table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_permissions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            account_type_id bigint(20) unsigned NOT NULL,
            permission_key varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY account_permission (account_type_id, permission_key),
            KEY account_type_id (account_type_id),
            KEY permission_key (permission_key)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Sessions table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_token varchar(255) NOT NULL,
            ip_address varchar(45),
            user_agent varchar(500),
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Password resets table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_password_resets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            token varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Contact persons table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_contact_persons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            branch_id bigint(20) unsigned DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100),
            phone varchar(50),
            position varchar(100),
            is_primary tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY branch_id (branch_id),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Audit log table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_audit_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL,
            entity_type varchar(50),
            entity_id bigint(20) unsigned,
            old_values text,
            new_values text,
            ip_address varchar(45),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY entity_type (entity_type),
            KEY entity_id (entity_id),
            KEY created_at (created_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Error log table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_error_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            error_type varchar(50) NOT NULL,
            error_message text NOT NULL,
            stack_trace text,
            request_url varchar(500),
            request_method varchar(10),
            ip_address varchar(45),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY user_id (user_id),
            KEY error_type (error_type),
            KEY created_at (created_at)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Training languages table
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_training_languages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            language_code varchar(10) NOT NULL,
            language_name varchar(100) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY language_code (language_code),
            KEY is_active (is_active)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Training language branches (many-to-many relationship)
        $sql = "CREATE TABLE {$this->wpdb->prefix}saw_training_language_branches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            training_language_id bigint(20) unsigned NOT NULL,
            branch_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY language_branch (training_language_id, branch_id),
            KEY training_language_id (training_language_id),
            KEY branch_id (branch_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Drop all plugin tables
     * WARNING: This will permanently delete all data!
     */
    public function drop_tables() {
        $tables = self::get_table_names();
        
        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . 'saw_' . $table;
            $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
    }
    
    /**
     * Check if all required tables exist
     * 
     * @return bool True if all tables exist, false otherwise
     */
    public function tables_exist() {
        $tables = self::get_table_names();
        
        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . 'saw_' . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            
            if ($exists !== $table_name) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get database version
     * 
     * @return string Current database version
     */
    public function get_version() {
        return get_option('saw_visitors_db_version', '0.0.0');
    }
    
    /**
     * Update database version
     * 
     * @param string $version New version number
     */
    public function update_version($version) {
        update_option('saw_visitors_db_version', $version);
    }
}