<?php
/**
 * Schema: saw_users
 * 
 * Uživatelé systému (admin, manager, terminal)
 * Vazba na WordPress wp_users tabulku
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_users( $table_name, $prefix, $wp_users_table, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		wp_user_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Vazba na wp_users (admin/manager)',
		
		-- Role
		role ENUM('super_admin', 'admin', 'manager', 'terminal') NOT NULL DEFAULT 'manager',
		
		-- Terminal specific
		terminal_name VARCHAR(100) DEFAULT NULL COMMENT 'Název terminálu (např. Vstup A)',
		pin VARCHAR(255) DEFAULT NULL COMMENT 'Bcrypt hash PIN kódu pro terminál',
		
		-- Permissions
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		last_login_at DATETIME DEFAULT NULL,
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_wp_user (wp_user_id),
		KEY idx_customer (customer_id),
		KEY idx_role (customer_id, role),
		KEY idx_active (customer_id, is_active),
		KEY fk_sawuser_customer (customer_id),
		KEY fk_sawuser_wpuser (wp_user_id),
		
		CONSTRAINT fk_sawuser_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_sawuser_wpuser 
			FOREIGN KEY (wp_user_id) 
			REFERENCES {$wp_users_table}(ID) 
			ON DELETE SET NULL
	) {$charset_collate} COMMENT='Uživatelé systému (dual auth)';";
}
