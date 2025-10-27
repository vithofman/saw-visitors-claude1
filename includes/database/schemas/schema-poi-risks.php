<?php
/**
 * Schema: saw_poi_risks
 * 
 * Varování a bezpečnostní rizika pro POI
 * ✅ DYNAMICKÉ JAZYKY - kritické pro bezpečnost!
 * ✅ S customer_id
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_poi_risks( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- ✅ JAZYK varování (DŮLEŽITÉ pro bezpečnost!)
		language VARCHAR(5) NOT NULL COMMENT 'ISO 639-1 (cs, en, de...)',
		
		-- Závažnost rizika
		severity ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium' COMMENT 'Závažnost',
		
		-- Typ rizika
		risk_type ENUM('physical', 'chemical', 'biological', 'fire', 'electrical', 'noise', 'other') 
			DEFAULT 'other' COMMENT 'Typ rizika',
		
		-- Obsah
		title VARCHAR(255) NOT NULL COMMENT 'Název varování',
		description LONGTEXT NOT NULL COMMENT 'Popis rizika',
		prevention_measures LONGTEXT DEFAULT NULL COMMENT 'Preventivní opatření',
		emergency_procedure TEXT DEFAULT NULL COMMENT 'Postup v případě nouze',
		
		-- Vizuální
		icon VARCHAR(100) DEFAULT NULL COMMENT 'CSS třída ikony (např. fa-exclamation-triangle)',
		color VARCHAR(7) DEFAULT NULL COMMENT 'Hex barva (#ff0000 pro kritické)',
		
		-- Požadované OOP
		required_ppe TEXT DEFAULT NULL COMMENT 'Povinné osobní ochranné pomůcky (JSON array)',
		
		-- Pořadí
		display_order INT DEFAULT 0 COMMENT 'Pořadí zobrazení (kritické nahoře)',
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		show_in_app TINYINT(1) DEFAULT 1 COMMENT 'Zobrazit v mobilní aplikaci',
		requires_acknowledgment TINYINT(1) DEFAULT 0 COMMENT 'Vyžaduje potvrzení od návštěvníka',
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_language (language),
		KEY idx_severity (severity),
		KEY idx_type (risk_type),
		KEY idx_order (poi_id, severity, display_order),
		KEY idx_active (customer_id, is_active),
		KEY fk_poirisk_customer (customer_id),
		KEY fk_poirisk_poi (poi_id),
		
		CONSTRAINT fk_poirisk_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_poirisk_poi 
			FOREIGN KEY (poi_id) 
			REFERENCES {$pois_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI rizika a varování (BOZP)';";
}
