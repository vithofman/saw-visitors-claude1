<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_pois($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$beacons_table = $prefix . 'beacons';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		beacon_id BIGINT(20) UNSIGNED DEFAULT NULL,
		poi_type ENUM('machine', 'location', 'hazard', 'information', 'facility') DEFAULT 'location',
		code VARCHAR(100) DEFAULT NULL,
		latitude DECIMAL(10,8) DEFAULT NULL,
		longitude DECIMAL(11,8) DEFAULT NULL,
		floor_level VARCHAR(50) DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_beacon (beacon_id),
		KEY idx_type (customer_id, poi_type),
		KEY idx_code (customer_id, code),
		KEY idx_active (customer_id, is_active),
		CONSTRAINT fk_poi_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_poi_beacon FOREIGN KEY (beacon_id) REFERENCES {$beacons_table}(id) ON DELETE SET NULL
	) {$charset_collate} COMMENT='Points of Interest';";
}
