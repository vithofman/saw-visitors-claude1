<?php
/**
 * Příklady použití SAW Visitors v4.6.1 databáze
 * 
 * Tyto příklady ukazují jak pracovat s:
 * - Dynamickými jazyky
 * - Customer izolací
 * - POI systémem
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

// =====================================================
// PŘÍKLAD 1: Vytvoření POI s obsahem ve 3 jazycích
// =====================================================

function example_create_poi_with_multilang_content( $customer_id ) {
	global $wpdb;
	
	// 1. Vytvoř POI
	$wpdb->insert(
		$wpdb->prefix . 'saw_pois',
		array(
			'customer_id' => $customer_id,
			'code'        => 'PROD-LINE-01',
			'poi_type'    => 'production_line',
			'floor_level' => 'Přízemí',
			'zone'        => 'Výrobní hala A',
			'is_active'   => 1,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%d' )
	);
	
	$poi_id = $wpdb->insert_id;
	
	// 2. Přidej český obsah
	SAW_DB_Helper::insert_poi_content( array(
		'customer_id' => $customer_id,
		'poi_id'      => $poi_id,
		'language'    => 'cs',
		'title'       => 'Výrobní linka 01',
		'description' => '<p>Hlavní výrobní linka pro zpracování...</p>',
		'safety_instructions' => '<ul><li>Noste ochranné brýle</li><li>Zákaz vstupu bez doprovodu</li></ul>',
	) );
	
	// 3. Přidej anglický překlad
	SAW_DB_Helper::insert_poi_content( array(
		'customer_id' => $customer_id,
		'poi_id'      => $poi_id,
		'language'    => 'en',
		'title'       => 'Production Line 01',
		'description' => '<p>Main production line for processing...</p>',
		'safety_instructions' => '<ul><li>Wear safety goggles</li><li>No entry without escort</li></ul>',
	) );
	
	// 4. Přidej německý překlad
	SAW_DB_Helper::insert_poi_content( array(
		'customer_id' => $customer_id,
		'poi_id'      => $poi_id,
		'language'    => 'de',
		'title'       => 'Produktionslinie 01',
		'description' => '<p>Hauptproduktionslinie für die Verarbeitung...</p>',
		'safety_instructions' => '<ul><li>Schutzbrille tragen</li><li>Kein Zutritt ohne Begleitung</li></ul>',
	) );
	
	return $poi_id;
}

// =====================================================
// PŘÍKLAD 2: Přidání nového jazyka BEZ změny DB struktury
// =====================================================

function example_add_new_language( $customer_id, $poi_id ) {
	// Zákazník si přeje přidat slovenštinu - jednoduše přidáš řádek!
	
	SAW_DB_Helper::insert_poi_content( array(
		'customer_id' => $customer_id,
		'poi_id'      => $poi_id,
		'language'    => 'sk', // NOVÝ JAZYK!
		'title'       => 'Výrobná linka 01',
		'description' => '<p>Hlavná výrobná linka pre spracovanie...</p>',
	) );
	
	// Hotovo! Žádná změna schématu, žádné migrace!
}

// =====================================================
// PŘÍKLAD 3: Získání obsahu POI pro mobilní aplikaci
// =====================================================

function example_get_poi_for_mobile_app( $customer_id, $poi_id, $user_language ) {
	global $wpdb;
	
	// 1. Načti základní POI info
	$poi = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}saw_pois WHERE customer_id = %d AND id = %d AND is_active = 1",
		$customer_id,
		$poi_id
	) );
	
	if ( ! $poi ) {
		return null;
	}
	
	// 2. Načti content v požadovaném jazyce
	$content = SAW_DB_Helper::get_poi_content( $poi_id, $user_language, $customer_id );
	
	// Fallback na češtinu pokud překlad neexistuje
	if ( ! $content && $user_language !== 'cs' ) {
		$content = SAW_DB_Helper::get_poi_content( $poi_id, 'cs', $customer_id );
	}
	
	// 3. Načti média (obrázky, audio, video)
	$media = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}saw_poi_media 
		WHERE customer_id = %d AND poi_id = %d AND is_active = 1 
		AND (language = %s OR language IS NULL)
		ORDER BY display_order",
		$customer_id,
		$poi_id,
		$user_language
	) );
	
	// 4. Načti rizika v daném jazyce
	$risks = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}saw_poi_risks 
		WHERE customer_id = %d AND poi_id = %d AND language = %s AND is_active = 1
		ORDER BY severity DESC, display_order",
		$customer_id,
		$poi_id,
		$user_language
	) );
	
	// 5. Sestav odpověď pro API
	return array(
		'poi'     => $poi,
		'content' => $content,
		'media'   => $media,
		'risks'   => $risks,
	);
}

// =====================================================
// PŘÍKLAD 4: Bezpečné mazání s customer kontrolou
// =====================================================

function example_safe_delete_poi( $customer_id, $poi_id, $current_user ) {
	// Ověř oprávnění uživatele
	if ( $current_user->customer_id !== $customer_id ) {
		return new WP_Error( 'forbidden', 'Nemáte oprávnění mazat data jiného zákazníka!' );
	}
	
	// Použij bezpečnou funkci s customer_id kontrolou
	$deleted = SAW_DB_Helper::safe_delete( 'pois', $poi_id, $customer_id );
	
	if ( ! $deleted ) {
		return new WP_Error( 'not_found', 'POI nebylo nalezeno nebo nepatří vašemu zákazníkovi.' );
	}
	
	// CASCADE automaticky smaže:
	// - poi_content
	// - poi_media
	// - poi_pdfs
	// - poi_risks
	// - poi_additional_info
	// - route_pois
	
	return true;
}

// =====================================================
// PŘÍKLAD 5: Vytvoření trasy s POI
// =====================================================

function example_create_route_with_pois( $customer_id ) {
	global $wpdb;
	
	// 1. Vytvoř trasu
	$wpdb->insert(
		$wpdb->prefix . 'saw_routes',
		array(
			'customer_id'        => $customer_id,
			'code'               => 'BASIC-TOUR',
			'route_type'         => 'self_guided',
			'estimated_duration' => 45, // minuty
			'difficulty'         => 'easy',
			'is_default'         => 1,
			'is_active'          => 1,
		),
		array( '%d', '%s', '%s', '%d', '%s', '%d', '%d' )
	);
	
	$route_id = $wpdb->insert_id;
	
	// 2. Přidej POI na trasu (v pořadí)
	$pois = array( 5, 8, 12, 15, 20 ); // ID POI
	
	foreach ( $pois as $order => $poi_id ) {
		$wpdb->insert(
			$wpdb->prefix . 'saw_route_pois',
			array(
				'customer_id'             => $customer_id,
				'route_id'                => $route_id,
				'poi_id'                  => $poi_id,
				'stop_order'              => $order + 1,
				'estimated_time_minutes'  => 10,
				'is_mandatory'            => 1,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d' )
		);
	}
	
	return $route_id;
}

// =====================================================
// PŘÍKLAD 6: API endpoint pro Flutter aplikaci
// =====================================================

function example_api_get_route( $customer_id, $route_id, $language ) {
	global $wpdb;
	
	// 1. Načti trasu
	$route = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}saw_routes 
		WHERE customer_id = %d AND id = %d AND is_active = 1",
		$customer_id,
		$route_id
	) );
	
	if ( ! $route ) {
		return null;
	}
	
	// 2. Načti POI na trase (v pořadí)
	$route_pois = $wpdb->get_results( $wpdb->prepare(
		"SELECT rp.*, p.code, p.poi_type 
		FROM {$wpdb->prefix}saw_route_pois rp
		INNER JOIN {$wpdb->prefix}saw_pois p ON rp.poi_id = p.id
		WHERE rp.customer_id = %d AND rp.route_id = %d AND rp.is_active = 1
		ORDER BY rp.stop_order",
		$customer_id,
		$route_id
	) );
	
	// 3. Pro každé POI načti content v daném jazyce
	$stops = array();
	foreach ( $route_pois as $rp ) {
		$content = SAW_DB_Helper::get_poi_content( $rp->poi_id, $language, $customer_id );
		
		$stops[] = array(
			'order'                 => $rp->stop_order,
			'poi_id'                => $rp->poi_id,
			'poi_code'              => $rp->code,
			'poi_type'              => $rp->poi_type,
			'estimated_time'        => $rp->estimated_time_minutes,
			'is_mandatory'          => (bool) $rp->is_mandatory,
			'title'                 => $content->title ?? 'N/A',
			'description'           => $content->description ?? '',
			'safety_instructions'   => $content->safety_instructions ?? '',
		);
	}
	
	// 4. JSON response
	return array(
		'route' => array(
			'id'                => $route->id,
			'code'              => $route->code,
			'type'              => $route->route_type,
			'difficulty'        => $route->difficulty,
			'estimated_duration'=> $route->estimated_duration,
		),
		'stops' => $stops,
		'language' => $language,
	);
}

// =====================================================
// PŘÍKLAD 7: Výpis všech dostupných jazyků
// =====================================================

function example_get_available_languages( $customer_id ) {
	$languages = SAW_DB_Helper::get_customer_languages( $customer_id );
	
	// Mapování na user-friendly názvy
	$language_names = array(
		'cs' => 'Čeština',
		'en' => 'English',
		'de' => 'Deutsch',
		'sk' => 'Slovenčina',
		'pl' => 'Polski',
		'fr' => 'Français',
	);
	
	$result = array();
	foreach ( $languages as $code ) {
		$result[] = array(
			'code' => $code,
			'name' => $language_names[ $code ] ?? $code,
		);
	}
	
	return $result;
}

// =====================================================
// PŘÍKLAD 8: Kontrola integrity dat (CLI script)
// =====================================================

function example_check_data_integrity( $customer_id ) {
	global $wpdb;
	
	$issues = array();
	
	// 1. POI bez žádného contentu
	$pois_without_content = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.id, p.code FROM {$wpdb->prefix}saw_pois p
		LEFT JOIN {$wpdb->prefix}saw_poi_content pc ON p.id = pc.poi_id
		WHERE p.customer_id = %d AND pc.id IS NULL",
		$customer_id
	) );
	
	if ( $pois_without_content ) {
		$issues[] = sprintf( 
			'%d POI bez obsahu: %s', 
			count( $pois_without_content ),
			implode( ', ', wp_list_pluck( $pois_without_content, 'code' ) )
		);
	}
	
	// 2. Content bez POI (orphaned records)
	$orphaned_content = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}saw_poi_content pc
		LEFT JOIN {$wpdb->prefix}saw_pois p ON pc.poi_id = p.id
		WHERE pc.customer_id = %d AND p.id IS NULL",
		$customer_id
	) );
	
	if ( $orphaned_content > 0 ) {
		$issues[] = sprintf( '%d osiřelých content záznamů', $orphaned_content );
	}
	
	// 3. Nekonzistentní customer_id (bezpečnostní kontrola!)
	$inconsistent = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}saw_poi_content pc
		INNER JOIN {$wpdb->prefix}saw_pois p ON pc.poi_id = p.id
		WHERE pc.customer_id = %d AND p.customer_id != pc.customer_id",
		$customer_id
	) );
	
	if ( $inconsistent > 0 ) {
		$issues[] = sprintf( 'KRITICKÉ: %d záznamů s nekonzistentním customer_id!', $inconsistent );
	}
	
	return $issues;
}

// =====================================================
// Použití příkladů:
// =====================================================

/*
// V admin panelu:
$customer_id = saw_get_current_customer_id();

// Vytvoř POI s obsahem ve 3 jazycích
$poi_id = example_create_poi_with_multilang_content( $customer_id );

// Přidej nový jazyk (například slovenštinu)
example_add_new_language( $customer_id, $poi_id );

// V API endpointu pro Flutter:
$route_data = example_api_get_route( $customer_id, $route_id, 'cs' );
wp_send_json_success( $route_data );

// WP-CLI integrace kontroly:
// wp saw check-integrity --customer=1
*/
