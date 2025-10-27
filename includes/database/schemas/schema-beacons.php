<?php
/**
 * Schema: saw_beacons
 * 
 * iBeacon hardware zařízení pro indoor tracking
 * NOVĚ: S customer_id pro multi-tenant izolaci
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_beacons( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		
		-- iBeacon identifikace
		uuid VARCHAR(36) NOT NULL COMMENT 'iBeacon UUID (např. 12345678-1234-1234-1234-123456789012)',
		major INT UNSIGNED NOT NULL COMMENT 'Major hodnota (0-65535)',
		minor INT UNSIGNED NOT NULL COMMENT 'Minor hodnota (0-65535)',
		
		-- Metadata
		name VARCHAR(255) NOT NULL COMMENT 'Název beaconu (např. Vstupní hala - beacon 1)',
		description TEXT DEFAULT NULL,
		
		-- Instalace
		location VARCHAR(255) DEFAULT NULL COMMENT 'Fyzické umístění (např. Vstupní hala, strop)',
		floor_level VARCHAR(50) DEFAULT NULL COMMENT 'Patro (např. 1. patro, přízemí)',
		
		-- Hardware
		battery_level TINYINT UNSIGNED DEFAULT NULL COMMENT 'Stav baterie (0-100%)',
		last_battery_check DATETIME DEFAULT NULL,
		tx_power INT DEFAULT NULL COMMENT 'Vysílací výkon (dBm)',
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Aktivní/deaktivovaný',
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_beacon_identifier (customer_id, uuid, major, minor),
		KEY idx_customer (customer_id),
		KEY idx_uuid (uuid),
		KEY idx_active (customer_id, is_active),
		KEY fk_beacon_customer (customer_id),
		
		CONSTRAINT fk_beacon_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='iBeacon zařízení pro indoor tracking';";
}
