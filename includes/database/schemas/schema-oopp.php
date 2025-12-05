<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_oopp($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		group_id TINYINT UNSIGNED NOT NULL COMMENT 'FK na saw_oopp_groups',
		name VARCHAR(255) NOT NULL,
		image_path VARCHAR(500) NULL COMMENT 'Cesta k obrázku',
		standards TEXT NULL COMMENT 'Související předpisy/normy',
		risk_description TEXT NULL COMMENT 'Popis rizik, proti kterým chrání',
		protective_properties TEXT NULL COMMENT 'Ochranné vlastnosti',
		usage_instructions TEXT NULL COMMENT 'Pokyny pro použití',
		maintenance_instructions TEXT NULL COMMENT 'Pokyny pro údržbu',
		storage_instructions TEXT NULL COMMENT 'Pokyny pro skladování',
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		display_order INT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_group (group_id),
		KEY idx_customer_group (customer_id, group_id),
		KEY idx_active (customer_id, is_active),
		KEY idx_name (customer_id, name(50))
	) {$charset_collate} COMMENT='Osobní ochranné pracovní prostředky';";
}


