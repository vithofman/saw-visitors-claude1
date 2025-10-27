<?php
/**
 * Schema: saw_poi_pdfs
 * 
 * PDF dokumenty pro POI (návody, specifikace, technická dokumentace)
 * ✅ DYNAMICKÉ JAZYKY
 * ✅ S customer_id
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_poi_pdfs( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- ✅ JAZYK dokumentu
		language VARCHAR(5) NOT NULL COMMENT 'ISO 639-1 (cs, en, de...)',
		
		-- Typ dokumentu
		document_type ENUM('manual', 'specification', 'safety', 'certificate', 'diagram', 'other') 
			DEFAULT 'other' COMMENT 'Typ PDF dokumentu',
		
		-- Metadata
		title VARCHAR(255) NOT NULL COMMENT 'Název dokumentu',
		description TEXT DEFAULT NULL,
		
		-- Soubor
		file_path VARCHAR(500) NOT NULL COMMENT 'Relativní cesta (wp-content/uploads/saw-pdfs/)',
		file_url VARCHAR(1000) DEFAULT NULL COMMENT 'Plná URL (pro CDN)',
		file_size BIGINT UNSIGNED DEFAULT NULL COMMENT 'Velikost v bajtech',
		file_hash VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash (deduplikace)',
		
		-- PDF specifické
		page_count INT UNSIGNED DEFAULT NULL COMMENT 'Počet stran',
		
		-- Přístupnost
		is_downloadable TINYINT(1) DEFAULT 1 COMMENT 'Povoleno stahování',
		requires_auth TINYINT(1) DEFAULT 0 COMMENT 'Vyžaduje přihlášení',
		
		-- Verze
		version VARCHAR(20) DEFAULT NULL COMMENT 'Verze dokumentu (např. v1.2)',
		
		-- Pořadí
		display_order INT DEFAULT 0,
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_language (language),
		KEY idx_type (customer_id, document_type),
		KEY idx_hash (file_hash),
		KEY idx_order (poi_id, display_order),
		KEY fk_poipdf_customer (customer_id),
		KEY fk_poipdf_poi (poi_id),
		
		CONSTRAINT fk_poipdf_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_poipdf_poi 
			FOREIGN KEY (poi_id) 
			REFERENCES {$pois_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI PDF dokumenty (návody, spec.)';";
}
