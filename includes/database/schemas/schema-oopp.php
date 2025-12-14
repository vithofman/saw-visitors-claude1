<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_oopp($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		group_id TINYINT UNSIGNED NOT NULL COMMENT 'FK na saw_oopp_groups',
		image_path VARCHAR(500) NULL COMMENT 'Cesta k obrázku',
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		is_global TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = zobrazuje se všem, 0 = pouze při přiřazení k návštěvě',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		created_by VARCHAR(255) NULL COMMENT 'Email uživatele, který vytvořil záznam',
		updated_by VARCHAR(255) NULL COMMENT 'Email uživatele, který naposledy aktualizoval záznam',
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_group (group_id),
		KEY idx_customer_group (customer_id, group_id),
		KEY idx_active (customer_id, is_active),
		KEY idx_global (customer_id, is_global, is_active)
	) {$charset_collate} COMMENT='Osobní ochranné pracovní prostředky';";
}

