<?php
/**
 * Schema: saw_companies
 * Firemní databáze + FULLTEXT search
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_companies( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		name VARCHAR(255) NOT NULL,
		ico VARCHAR(20) DEFAULT NULL,
		street VARCHAR(255) DEFAULT NULL,
		city VARCHAR(100) DEFAULT NULL,
		zip VARCHAR(20) DEFAULT NULL,
		country VARCHAR(100) DEFAULT 'Česká republika',
		email VARCHAR(255) DEFAULT NULL,
		phone VARCHAR(50) DEFAULT NULL,
		website VARCHAR(255) DEFAULT NULL,
		is_archived TINYINT(1) DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_name (customer_id, name(50)),
		KEY idx_archived (customer_id, is_archived),
		FULLTEXT KEY ft_name (name),
		CONSTRAINT fk_company_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Firmy návštěvníků';";
}
