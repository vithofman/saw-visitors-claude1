<?php
/**
 * Schema: saw_pois
 * 
 * Points of Interest - místa v prostoru (výrobní linky, místnosti, exponáty)
 * NOVĚ: S customer_id, vztah k beaconu
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_pois( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$beacons_table = $prefix . 'beacons';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		beacon_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Vazba na iBeacon (nullable)',
		
		-- Základní identifikace
		code VARCHAR(50) NOT NULL COMMENT 'Unikátní kód POI (např. POI-001, LINE-A)',
		
		-- Typ POI
		poi_type ENUM('room', 'production_line', 'exhibit', 'safety_zone', 'meeting_point', 'other') 
			DEFAULT 'other' COMMENT 'Typ místa',
		
		-- Lokace
		floor_level VARCHAR(50) DEFAULT NULL COMMENT 'Patro (např. přízemí, 1. patro)',
		building VARCHAR(100) DEFAULT NULL COMMENT 'Budova (pokud více budov)',
		zone VARCHAR(100) DEFAULT NULL COMMENT 'Zóna (např. Výrobní hala A)',
		
		-- GPS coordinates (volitelné pro outdoor)
		latitude DECIMAL(10, 8) DEFAULT NULL,
		longitude DECIMAL(11, 8) DEFAULT NULL,
		
		-- Pořadí
		display_order INT DEFAULT 0 COMMENT 'Pořadí zobrazení',
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_customer_code (customer_id, code),
		KEY idx_customer (customer_id),
		KEY idx_beacon (beacon_id),
		KEY idx_type (customer_id, poi_type),
		KEY idx_active (customer_id, is_active),
		KEY idx_order (customer_id, display_order),
		KEY fk_poi_customer (customer_id),
		KEY fk_poi_beacon (beacon_id),
		
		CONSTRAINT fk_poi_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_poi_beacon 
			FOREIGN KEY (beacon_id) 
			REFERENCES {$beacons_table}(id) 
			ON DELETE SET NULL
	) {$charset_collate} COMMENT='Points of Interest - místa v prostoru';";
}
