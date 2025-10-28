<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_beacons($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		uuid VARCHAR(36) NOT NULL,
		major INT UNSIGNED NOT NULL,
		minor INT UNSIGNED NOT NULL,
		name VARCHAR(255) NOT NULL,
		description TEXT DEFAULT NULL,
		location VARCHAR(255) DEFAULT NULL,
		floor_level VARCHAR(50) DEFAULT NULL,
		battery_level TINYINT UNSIGNED DEFAULT NULL,
		last_battery_check DATETIME DEFAULT NULL,
		tx_power INT DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_beacon_identifier (customer_id, uuid, major, minor),
		KEY idx_customer (customer_id),
		KEY idx_uuid (uuid),
		KEY idx_active (customer_id, is_active),
		CONSTRAINT fk_beacon_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='iBeacon zařízení pro indoor tracking';";
}
