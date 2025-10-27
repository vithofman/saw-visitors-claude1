<?php
/**
 * Rozšíření hlavní třídy pluginu pro Phase 5
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add to class-saw-visitors.php
 * Přidejte tyto metody do existující třídy SAW_Visitors
 */

// ========================================
// ČÁST 1: Rozšířené admin menu
// ========================================

/**
 * Add admin menu (ROZŠÍŘENÁ VERZE - přepsat stávající metodu)
 */
public function add_admin_menu() {
	// Hlavní menu položka
	add_menu_page(
		'SAW Visitors',
		'SAW Visitors',
		'manage_options',
		'saw-visitors',
		array( $this, 'display_dashboard' ),
		'dashicons-groups',
		30
	);
	
	// Submenu - Dashboard
	add_submenu_page(
		'saw-visitors',
		'Dashboard',
		'Dashboard',
		'manage_options',
		'saw-visitors',
		array( $this, 'display_dashboard' )
	);
	
	// Submenu - Zákazníci
	add_submenu_page(
		'saw-visitors',
		'Zákazníci',
		'Zákazníci',
		'manage_options',
		'saw-customers',
		array( 'SAW_Admin_Customers', 'list_page' )
	);
	
	add_submenu_page(
		null, // Skrytá stránka
		'Přidat zákazníka',
		'',
		'manage_options',
		'saw-customers-new',
		array( 'SAW_Admin_Customers', 'edit_page' )
	);
	
	add_submenu_page(
		null, // Skrytá stránka
		'Upravit zákazníka',
		'',
		'manage_options',
		'saw-customers-edit',
		array( 'SAW_Admin_Customers', 'edit_page' )
	);
	
	// Submenu - Správa obsahu
	add_submenu_page(
		'saw-visitors',
		'Správa obsahu',
		'Správa obsahu',
		'manage_options',
		'saw-content',
		array( 'SAW_Admin_Content', 'main_page' )
	);
	
	// Submenu - Verze školení
	add_submenu_page(
		'saw-visitors',
		'Verze školení',
		'Verze školení',
		'manage_options',
		'saw-training-version',
		array( 'SAW_Admin_Training_Version', 'main_page' )
	);
	
	// Submenu - Audit Log
	add_submenu_page(
		'saw-visitors',
		'Audit Log',
		'Audit Log',
		'manage_options',
		'saw-audit-log',
		array( 'SAW_Admin_Audit_Log', 'main_page' )
	);
	
	// Submenu - Email Queue
	add_submenu_page(
		'saw-visitors',
		'Email Queue',
		'Email Queue',
		'manage_options',
		'saw-email-queue',
		array( 'SAW_Admin_Email_Queue', 'main_page' )
	);
	
	// Submenu - O pluginu
	add_submenu_page(
		'saw-visitors',
		'O pluginu',
		'O pluginu',
		'manage_options',
		'saw-visitors-about',
		array( $this, 'display_about' )
	);
}

// ========================================
// ČÁST 2: Dropdown přepínač zákazníků
// ========================================

/**
 * Initialize session for customer selection
 * Přidat do __construct() nebo init metody
 */
public function init_customer_session() {
	if ( ! session_id() ) {
		session_start();
	}
	
	// Nastavit výchozího zákazníka pokud není zvolen
	if ( ! isset( $_SESSION['saw_selected_customer_id'] ) ) {
		global $wpdb;
		$first_customer = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY name ASC LIMIT 1" );
		if ( $first_customer ) {
			$_SESSION['saw_selected_customer_id'] = intval( $first_customer );
		}
	}
}

/**
 * Add customer dropdown to admin bar
 */
public function add_customer_dropdown_to_admin_bar( $wp_admin_bar ) {
	// Zobrazit pouze pro Super Admin
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Zobrazit pouze na SAW Visitors stránkách
	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'saw-' ) === false ) {
		return;
	}
	
	global $wpdb;
	
	$customers = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}saw_customers ORDER BY name ASC" );
	
	if ( empty( $customers ) ) {
		return;
	}
	
	$selected_customer_id = isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
	
	// Najít vybraného zákazníka
	$selected_customer = null;
	foreach ( $customers as $c ) {
		if ( $c->id === $selected_customer_id ) {
			$selected_customer = $c;
			break;
		}
	}
	
	// Parent menu item
	$wp_admin_bar->add_node( array(
		'id'    => 'saw-customer-selector',
		'title' => '🏢 Zákazník: ' . ( $selected_customer ? esc_html( $selected_customer->name ) : 'Vyberte' ),
		'href'  => '#',
		'meta'  => array(
			'class' => 'saw-customer-dropdown',
		),
	) );
	
	// Add customer items
	foreach ( $customers as $customer ) {
		$is_selected = ( $customer->id === $selected_customer_id );
		
		$wp_admin_bar->add_node( array(
			'id'     => 'saw-customer-' . $customer->id,
			'parent' => 'saw-customer-selector',
			'title'  => ( $is_selected ? '✓ ' : '' ) . esc_html( $customer->name ),
			'href'   => wp_nonce_url(
				add_query_arg( array(
					'saw_action'      => 'switch_customer',
					'customer_id'     => $customer->id,
				) ),
				'saw_switch_customer_' . $customer->id
			),
			'meta'   => array(
				'class' => $is_selected ? 'saw-customer-selected' : '',
			),
		) );
	}
}

/**
 * Handle customer switch
 */
public function handle_customer_switch() {
	if ( ! isset( $_GET['saw_action'] ) || $_GET['saw_action'] !== 'switch_customer' ) {
		return;
	}
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nemáte oprávnění k této akci.' );
	}
	
	$customer_id = isset( $_GET['customer_id'] ) ? intval( $_GET['customer_id'] ) : 0;
	
	if ( ! $customer_id ) {
		wp_die( 'Neplatné ID zákazníka.' );
	}
	
	check_admin_referer( 'saw_switch_customer_' . $customer_id );
	
	// Ověřit že zákazník existuje
	global $wpdb;
	$customer = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, name FROM {$wpdb->prefix}saw_customers WHERE id = %d",
		$customer_id
	) );
	
	if ( ! $customer ) {
		wp_die( 'Zákazník nenalezen.' );
	}
	
	// Nastavit session
	if ( ! session_id() ) {
		session_start();
	}
	$_SESSION['saw_selected_customer_id'] = $customer_id;
	
	// Log audit
	SAW_Audit::log( array(
		'action'      => 'customer_switched',
		'customer_id' => $customer_id,
		'details'     => 'Super Admin switched to customer: ' . $customer->name,
	) );
	
	// Redirect zpět na aktuální stránku
	$redirect_url = remove_query_arg( array( 'saw_action', 'customer_id', '_wpnonce' ) );
	wp_redirect( $redirect_url );
	exit;
}

/**
 * Add hooks for customer dropdown (přidat do define_admin_hooks())
 */
private function define_admin_hooks_phase5() {
	// Customer session
	$this->loader->add_action( 'admin_init', $this, 'init_customer_session' );
	
	// Customer dropdown in admin bar
	$this->loader->add_action( 'admin_bar_menu', $this, 'add_customer_dropdown_to_admin_bar', 100 );
	
	// Handle customer switch
	$this->loader->add_action( 'admin_init', $this, 'handle_customer_switch' );
	
	// Enqueue admin bar styles
	$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_bar_styles' );
}

/**
 * Enqueue admin bar styles
 */
public function enqueue_admin_bar_styles() {
	wp_add_inline_style( 'admin-bar', '
		#wp-admin-bar-saw-customer-selector .ab-item {
			font-weight: 600;
			background: rgba(255, 255, 255, 0.1);
		}
		#wp-admin-bar-saw-customer-selector .ab-submenu .saw-customer-selected .ab-item {
			background: rgba(46, 162, 204, 0.2);
			font-weight: 600;
		}
		#wp-admin-bar-saw-customer-selector .ab-submenu a:hover {
			background: rgba(46, 162, 204, 0.3);
		}
	' );
}

// ========================================
// ČÁST 3: Načítání admin page tříd
// ========================================

/**
 * Load admin page classes
 * Přidat do load_dependencies() metody
 */
private function load_admin_page_classes() {
	// Phase 5 admin pages
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-customers.php';
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-content.php';
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-training-version.php';
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-audit-log.php';
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-email-queue.php';
}

// ========================================
// ČÁST 4: Helper funkce
// ========================================

/**
 * Get currently selected customer ID
 */
public static function get_selected_customer_id() {
	if ( ! session_id() ) {
		session_start();
	}
	return isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
}

/**
 * Get currently selected customer
 */
public static function get_selected_customer() {
	$customer_id = self::get_selected_customer_id();
	
	if ( ! $customer_id ) {
		return null;
	}
	
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
		$customer_id
	) );
}
