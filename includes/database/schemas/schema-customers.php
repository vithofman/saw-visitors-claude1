<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_customers($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		ico VARCHAR(20) DEFAULT NULL,
		dic VARCHAR(20) DEFAULT NULL,
		address_street VARCHAR(255) DEFAULT NULL,
		address_number VARCHAR(20) DEFAULT NULL,
		address_city VARCHAR(100) DEFAULT NULL,
		address_zip VARCHAR(20) DEFAULT NULL,
		address_country VARCHAR(100) DEFAULT 'Česká republika',
		billing_address_street VARCHAR(255) DEFAULT NULL,
		billing_address_number VARCHAR(20) DEFAULT NULL,
		billing_address_city VARCHAR(100) DEFAULT NULL,
		billing_address_zip VARCHAR(20) DEFAULT NULL,
		billing_address_country VARCHAR(100) DEFAULT NULL,
		contact_person VARCHAR(255) DEFAULT NULL,
		contact_position VARCHAR(100) DEFAULT NULL,
		contact_email VARCHAR(255) DEFAULT NULL,
		contact_phone VARCHAR(50) DEFAULT NULL,
		website VARCHAR(500) DEFAULT NULL,
		account_type_id BIGINT(20) UNSIGNED DEFAULT NULL,
		status VARCHAR(50) NOT NULL DEFAULT 'potential',
		acquisition_source VARCHAR(100) DEFAULT NULL,
		subscription_type VARCHAR(50) DEFAULT 'monthly',
		last_payment_date DATE DEFAULT NULL,
		logo_url VARCHAR(500) DEFAULT NULL,
		primary_color VARCHAR(7) DEFAULT '#1e40af',
		admin_language_default VARCHAR(5) NOT NULL DEFAULT 'cs',
		notes TEXT DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_name (name(50)),
		KEY idx_ico (ico),
		KEY idx_status (status),
		KEY idx_account_type (account_type_id),
		KEY idx_contact_email (contact_email),
		KEY idx_admin_language (admin_language_default)
	) {$charset_collate};";
}