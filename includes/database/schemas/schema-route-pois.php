<?php
/**
 * Schema: saw_route_pois
 * 
 * M:N vztah mezi trasami a POI
 * Definuje pořadí POI na trase
 * NOVĚ: S customer_id pro extra kontrolu
 *
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_route_pois( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$routes_table = $prefix . 'routes';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT '✅ Redundantní, ale pomáhá s kontrolou',
		route_id BIGINT(20) UNSIGNED NOT NULL,
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- Pořadí na trase
		stop_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Pořadí zastavení (1, 2, 3...)',
		
		-- Čas na místě
		estimated_time_minutes INT UNSIGNED DEFAULT NULL COMMENT 'Odhadovaný čas strávený na POI',
		
		-- Povinnost
		is_mandatory TINYINT(1) DEFAULT 1 COMMENT '1 = povinné zastavení, 0 = volitelné',
		
		-- Status
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		
		-- Timestamps
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		
		PRIMARY KEY (id),
		UNIQUE KEY idx_route_poi (route_id, poi_id),
		KEY idx_customer (customer_id),
		KEY idx_route (route_id),
		KEY idx_poi (poi_id),
		KEY idx_order (route_id, stop_order),
		KEY fk_routepoi_customer (customer_id),
		KEY fk_routepoi_route (route_id),
		KEY fk_routepoi_poi (poi_id),
		
		CONSTRAINT fk_routepoi_customer 
			FOREIGN KEY (customer_id) 
			REFERENCES {$customers_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_routepoi_route 
			FOREIGN KEY (route_id) 
			REFERENCES {$routes_table}(id) 
			ON DELETE CASCADE,
			
		CONSTRAINT fk_routepoi_poi 
			FOREIGN KEY (poi_id) 
			REFERENCES {$pois_table}(id) 
			ON DELETE CASCADE
	) {$charset_collate} COMMENT='M:N vztah: trasa ↔ POI (s pořadím)';";
}
