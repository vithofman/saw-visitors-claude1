<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_department_documents( $table_name, $prefix, $charset_collate ) {
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		department_id bigint(20) UNSIGNED NOT NULL,
		category enum('bozp','po','evakuace','prvni_pomoc','organizacni','pojisteni','covid') NOT NULL,
		title varchar(255) NOT NULL,
		file_url varchar(500) NOT NULL,
		language varchar(5) DEFAULT 'cs',
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_department (department_id),
		KEY idx_category (category),
		KEY idx_language (language),
		KEY fk_deptdoc_dept (department_id)
	) {$charset_collate};";
}
