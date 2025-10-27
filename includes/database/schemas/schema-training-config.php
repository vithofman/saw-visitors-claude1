<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_training_config( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		training_version int(11) UNSIGNED NOT NULL DEFAULT 1,
		version_reset_reason text DEFAULT NULL,
		training_enabled tinyint(1) NOT NULL DEFAULT 1,
		skip_period_days int(11) UNSIGNED DEFAULT 365,
		validity_period_days int(11) UNSIGNED DEFAULT 365,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_customer_unique (customer_id),
		KEY fk_training_customer (customer_id)
	) {$charset_collate};";
}
