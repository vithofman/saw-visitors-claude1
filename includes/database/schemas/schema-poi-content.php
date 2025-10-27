<?php
/**
 * Schema: saw_poi_content
 * 
 * Základní textový obsah pro POI (název, popis, instrukce)
 * ✅ DYNAMICKÉ JAZYKY: 1 řádek = 1 jazyk
 * ✅ S customer_id
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_poi_content( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- ✅ DYNAMICKÝ JAZYK (klíčová změna!)
		language VARCHAR(5) NOT NULL COMMENT 'ISO 639-1 kód (cs, en, de, fr, sk...)',
		
		-- Textový obsah
		title VARCHAR(255) NOT NULL COMMENT 'Název POI',
		subtitle VARCHAR(255) DEFAULT NULL COMMENT 'Podtitulek',
		description LONGTEXT DEFAULT NULL COMMENT 'Hlavní popis (WYSIWYG HTML)',
		
		-- Další textové bloky
		safety_instructions LONGTEXT DEFAULT NULL COMMENT 'Bezpečnostní instrukce',
		interesting_facts TEXT DEFAULT NULL COMMENT 'Zajímavosti',
		technical_specs TEXT DEFAULT NULL COMMENT 'Technické údaje',
		
		-- SEO (volitelné)
		meta_description VARCHAR(500) DEFAULT NULL,
		
		-- Status
		is_published TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Publikováno/rozpracováno',
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_poi_language (customer_id, poi_id, language),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_language (language),
		KEY idx_published (customer_id, is_published),
		KEY fk_poicontent_customer (customer_id),
		KEY fk_poicontent_poi (poi_id),
		
		CONSTRAINT fk_poicontent_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_poicontent_poi 
			FOREIGN KEY (poi_id) 
			REFERENCES {$pois_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI obsah - textový (dynamické jazyky!)';";
}
