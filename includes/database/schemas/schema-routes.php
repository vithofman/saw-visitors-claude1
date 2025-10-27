<?php
/**
 * Schema: saw_routes
 * 
 * Trasy/okruhy - definují posloupnost POI pro různé typy návštěv
 * NOVĚ: S customer_id
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_routes( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		
		-- Základní identifikace
		code VARCHAR(50) NOT NULL COMMENT 'Unikátní kód (např. ROUTE-A, BASIC-TOUR)',
		
		-- Metadata (bez jazykových sloupců!)
		-- Poznámka: Jazykové varianty budou v samostatné tabulce saw_route_translations (volitelně)
		
		-- Typ trasy
		route_type ENUM('guided', 'self_guided', 'safety', 'production', 'other') 
			DEFAULT 'self_guided' COMMENT 'Typ trasy',
		
		-- Čas
		estimated_duration INT UNSIGNED DEFAULT NULL COMMENT 'Odhadovaná doba v minutách',
		
		-- Difficulty
		difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'easy' COMMENT 'Obtížnost trasy',
		
		-- Pořadí
		display_order INT DEFAULT 0,
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		is_default TINYINT(1) DEFAULT 0 COMMENT '1 = výchozí trasa pro nové návštěvníky',
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_customer_code (customer_id, code),
		KEY idx_customer (customer_id),
		KEY idx_type (customer_id, route_type),
		KEY idx_active (customer_id, is_active),
		KEY idx_default (customer_id, is_default),
		KEY idx_order (customer_id, display_order),
		KEY fk_route_customer (customer_id),
		
		CONSTRAINT fk_route_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='Trasy/okruhy návštěv';";
}
