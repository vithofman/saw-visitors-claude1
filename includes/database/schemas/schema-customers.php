<?php
/**
 * Schema: saw_customers
 * 
 * Hlavní tabulka pro zákazníky (multi-tenant)
 * ŽÁDNÝ customer_id zde - toto JE zákaznická tabulka
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Získání SQL pro vytvoření tabulky saw_customers
 *
 * @param string $table_name Plný název tabulky s prefixem
 * @param string $prefix WordPress prefix + SAW prefix
 * @param string $charset_collate Charset a collate
 * @return string SQL příkaz
 */
function saw_get_schema_customers( $table_name, $prefix, $charset_collate ) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		
		-- Basic info
		name VARCHAR(255) NOT NULL COMMENT 'Název zákazníka (např. ACME s.r.o.)',
		ico VARCHAR(20) DEFAULT NULL COMMENT 'IČO',
		address TEXT DEFAULT NULL COMMENT 'Plná adresa',
		
		-- Branding
		logo_url VARCHAR(500) DEFAULT NULL COMMENT 'URL na logo (wp-content/uploads)',
		primary_color VARCHAR(7) DEFAULT '#1e40af' COMMENT 'Hlavní barva (#hex)',
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		KEY idx_name (name(50)),
		KEY idx_ico (ico)
	) {$charset_collate} COMMENT='Zákazníci (multi-tenant root)';";
}
