<?php
/**
 * Schema: saw_poi_media
 * 
 * Multimediální obsah pro POI (obrázky, audio, video)
 * ✅ DYNAMICKÉ JAZYKY pro audio popis
 * ✅ S customer_id
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_poi_media( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ KRITICKÉ: Multi-tenant izolace',
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- Typ média
		media_type ENUM('image', 'audio', 'video', 'youtube', 'vimeo') NOT NULL,
		
		-- ✅ JAZYK (důležité pro audio guide!)
		language VARCHAR(5) DEFAULT NULL COMMENT 'Jazyk média (důležité pro audio!), NULL = universální',
		
		-- Soubor
		file_path VARCHAR(500) NOT NULL COMMENT 'Relativní cesta k souboru (wp-content/uploads/saw-media/)',
		file_url VARCHAR(1000) DEFAULT NULL COMMENT 'Plná URL (pro CDN)',
		file_size BIGINT UNSIGNED DEFAULT NULL COMMENT 'Velikost v bajtech',
		mime_type VARCHAR(100) DEFAULT NULL COMMENT 'image/jpeg, audio/mp3, video/mp4...',
		
		-- External video
		external_url VARCHAR(1000) DEFAULT NULL COMMENT 'YouTube/Vimeo URL',
		external_id VARCHAR(100) DEFAULT NULL COMMENT 'YouTube/Vimeo ID',
		
		-- Metadata
		title VARCHAR(255) DEFAULT NULL COMMENT 'Název média',
		description TEXT DEFAULT NULL COMMENT 'Popis',
		alt_text VARCHAR(500) DEFAULT NULL COMMENT 'Alt text (accessibility)',
		
		-- Audio/Video specifické
		duration_seconds INT UNSIGNED DEFAULT NULL COMMENT 'Délka audio/video',
		transcript LONGTEXT DEFAULT NULL COMMENT 'Transkript audio (accessibility)',
		
		-- Obrázek specifické
		width INT UNSIGNED DEFAULT NULL COMMENT 'Šířka v px',
		height INT UNSIGNED DEFAULT NULL COMMENT 'Výška v px',
		thumbnail_path VARCHAR(500) DEFAULT NULL COMMENT 'Náhled (thumbnail)',
		
		-- Pořadí
		display_order INT DEFAULT 0 COMMENT 'Pořadí zobrazení',
		
		-- Status
		is_featured TINYINT(1) DEFAULT 0 COMMENT '1 = hlavní obrázek/video',
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_type (customer_id, media_type),
		KEY idx_language (language),
		KEY idx_featured (customer_id, poi_id, is_featured),
		KEY idx_order (poi_id, display_order),
		KEY fk_poimedia_customer (customer_id),
		KEY fk_poimedia_poi (poi_id),
		
		CONSTRAINT fk_poimedia_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_poimedia_poi 
			FOREIGN KEY (poi_id) 
			REFERENCES {$pois_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI média - obrázky, audio, video';";
}
