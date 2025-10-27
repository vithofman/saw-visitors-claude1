<?php
/**
 * Schema: saw_customer_api_keys
 * 
 * API klíče pro zákazníky (pro Flutter aplikaci)
 * Obsahuje customer_id jako foreign key
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_customer_api_keys( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- API Key
		api_key VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash',
		key_name VARCHAR(100) DEFAULT NULL COMMENT 'Popisek klíče (např. Production App)',
		
		-- Permissions
		is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = deaktivován',
		
		-- Usage tracking
		last_used_at DATETIME DEFAULT NULL,
		request_count BIGINT(20) UNSIGNED DEFAULT 0 COMMENT 'Počet požadavků',
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		expires_at DATETIME DEFAULT NULL COMMENT 'NULL = bez expirace',
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_api_key (api_key),
		KEY idx_customer (customer_id),
		KEY idx_active (customer_id, is_active),
		KEY fk_apikey_customer (customer_id),
		
		CONSTRAINT fk_apikey_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='API klíče pro zákazníky';";
}
