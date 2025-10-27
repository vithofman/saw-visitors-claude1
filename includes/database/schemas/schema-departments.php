<?php
/**
 * Schema: saw_departments
 * 
 * Oddělení zákazníka (výroba, sklad, administrativa...)
 * S customer_id a version trackingem
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_departments( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		
		-- Základní info
		name VARCHAR(255) NOT NULL COMMENT 'Název oddělení',
		description TEXT DEFAULT NULL,
		
		-- Version tracking pro školení
		training_version INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Verze školení oddělení',
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_active (customer_id, is_active),
		KEY idx_name (customer_id, name(50)),
		KEY fk_dept_customer (customer_id),
		
		CONSTRAINT fk_dept_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='Oddělení zákazníka (+ version tracking)';";
}
