<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_documents( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		category enum('bozp','po','evakuace','prvni_pomoc','organizacni','pojisteni','covid') NOT NULL,
		title varchar(255) NOT NULL,
		file_url varchar(500) NOT NULL,
		language varchar(5) DEFAULT 'cs',
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_category (category),
		KEY idx_language (language),
		KEY fk_document_customer (customer_id)
	) {$charset_collate};";
}
