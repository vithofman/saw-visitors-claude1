<?php
/**
 * Hlavní třída pluginu SAW Visitors
 * 
 * Orchestruje všechny komponenty pluginu:
 * - Loader (hooks management)
 * - Admin interface (Phase 5: Super Admin menu)
 * - Public interface
 * - URL Routing (Phase 4)
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Visitors {

	/**
	 * Loader instance
	 */
	protected $loader;

	/**
	 * Plugin name
	 */
	protected $plugin_name;

	/**
	 * Plugin version
	 */
	protected $version;

	/**
	 * Router instance
	 */
	protected $router;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->plugin_name = 'saw-visitors';
		$this->version = SAW_VISITORS_VERSION;
		
		$this->load_dependencies();
		$this->init_session();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_routing_hooks();
	}

	/**
	 * Load všechny závislosti
	 */
	private function load_dependencies() {
		// Core classes
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-loader.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-auth.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-session.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-password.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-audit.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/middleware.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin-access-control.php';
		
		// Phase 4: Router
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-router.php';
		
		// Phase 5: Admin page classes
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-customers.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-content.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-training-version.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-audit-log.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/admin/class-saw-admin-email-queue.php';
		
		// Inicializovat loader
		$this->loader = new SAW_Loader();
	}

	/**
	 * Initialize session (Phase 5)
	 * Zajistit že session existuje pro customer selection
	 */
	private function init_session() {
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}
	}

	/**
	 * Definovat admin hooks
	 */
	private function define_admin_hooks() {
		// Admin menu
		$this->loader->add_action( 'admin_menu', $this, 'add_admin_menu' );
		
		// Admin styles & scripts
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_scripts' );
		
		// Phase 5: Customer management
		$this->loader->add_action( 'admin_init', $this, 'init_customer_session' );
		$this->loader->add_action( 'admin_bar_menu', $this, 'add_customer_dropdown_to_admin_bar', 100 );
		$this->loader->add_action( 'admin_init', $this, 'handle_customer_switch' );
	}

	/**
	 * Definovat public hooks
	 */
	private function define_public_hooks() {
		// Frontend styles & scripts
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_scripts' );
	}

	/**
	 * Definovat routing hooks (Phase 4)
	 */
	private function define_routing_hooks() {
		// Rewrite rules
		$this->loader->add_action( 'init', $this, 'register_rewrite_rules' );
		
		// Query vars
		$this->loader->add_filter( 'query_vars', $this, 'add_query_vars' );
		
		// Template redirect (hlavní routing dispatcher)
		$this->loader->add_action( 'template_redirect', $this, 'handle_routing', 1 );
	}

	// ========================================
	// PHASE 5: CUSTOMER SESSION MANAGEMENT
	// ========================================

	/**
	 * Initialize customer session
	 * Nastaví výchozího zákazníka pokud není vybrán
	 */
	public function init_customer_session() {
		// Pouze pro přihlášené Super Adminy
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
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
						'saw_action'  => 'switch_customer',
						'customer_id' => $customer->id,
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
			wp_die( 'Nemáte oprávnění k této akci.', 'Přístup zamítnut', array( 'response' => 403 ) );
		}
		
		$customer_id = isset( $_GET['customer_id'] ) ? intval( $_GET['customer_id'] ) : 0;
		
		if ( ! $customer_id ) {
			wp_die( 'Neplatné ID zákazníka.', 'Chyba', array( 'response' => 400 ) );
		}
		
		check_admin_referer( 'saw_switch_customer_' . $customer_id );
		
		// Ověřit že zákazník existuje
		global $wpdb;
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, name FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );
		
		if ( ! $customer ) {
			wp_die( 'Zákazník nenalezen.', 'Chyba', array( 'response' => 404 ) );
		}
		
		// Nastavit session
		$_SESSION['saw_selected_customer_id'] = $customer_id;
		
		// Log audit
		SAW_Audit::log( array(
			'action'      => 'customer_switched',
			'customer_id' => $customer_id,
			'details'     => 'Super Admin switched to customer: ' . $customer->name,
		) );
		
		// Redirect zpět na aktuální stránku
		$redirect_url = remove_query_arg( array( 'saw_action', 'customer_id', '_wpnonce' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get current selected customer ID
	 */
	public static function get_selected_customer_id() {
		return isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
	}

	// ========================================
	// PHASE 5: ADMIN MENU
	// ========================================

	/**
	 * Add admin menu (PHASE 5: ROZŠÍŘENÁ VERZE)
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
		
		// Skryté stránky pro edit/create zákazníka
		add_submenu_page(
			null,
			'Přidat zákazníka',
			'',
			'manage_options',
			'saw-customers-new',
			array( 'SAW_Admin_Customers', 'edit_page' )
		);
		
		add_submenu_page(
			null,
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
	// PHASE 4: URL ROUTING
	// ========================================

	/**
	 * Register rewrite rules (Phase 4)
	 */
	public function register_rewrite_rules() {
		// Admin routes
		add_rewrite_rule(
			'^admin/login/?$',
			'index.php?saw_route=admin&saw_action=login',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/dashboard/?$',
			'index.php?saw_route=admin&saw_action=dashboard',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/invitations/?$',
			'index.php?saw_route=admin&saw_action=invitations',
			'top'
		);
		
		// Manager routes
		add_rewrite_rule(
			'^manager/login/?$',
			'index.php?saw_route=manager&saw_action=login',
			'top'
		);
		
		add_rewrite_rule(
			'^manager/dashboard/?$',
			'index.php?saw_route=manager&saw_action=dashboard',
			'top'
		);
		
		// Terminal routes
		add_rewrite_rule(
			'^terminal/login/?$',
			'index.php?saw_route=terminal&saw_action=login',
			'top'
		);
		
		add_rewrite_rule(
			'^terminal/checkin/?$',
			'index.php?saw_route=terminal&saw_action=checkin',
			'top'
		);
		
		add_rewrite_rule(
			'^terminal/checkout/?$',
			'index.php?saw_route=terminal&saw_action=checkout',
			'top'
		);
		
		// Visitor routes (public)
		add_rewrite_rule(
			'^visitor/invitation/([a-zA-Z0-9]+)/?$',
			'index.php?saw_route=visitor&saw_action=invitation&saw_token=$matches[1]',
			'top'
		);
		
		add_rewrite_rule(
			'^visitor/draft/([a-zA-Z0-9]+)/?$',
			'index.php?saw_route=visitor&saw_action=draft&saw_token=$matches[1]',
			'top'
		);
		
		add_rewrite_rule(
			'^visitor/walkin/?$',
			'index.php?saw_route=visitor&saw_action=walkin',
			'top'
		);
		
		add_rewrite_rule(
			'^visitor/training/([a-zA-Z0-9]+)/?$',
			'index.php?saw_route=visitor&saw_action=training&saw_token=$matches[1]',
			'top'
		);
		
		// Logout (universal)
		add_rewrite_rule(
			'^logout/?$',
			'index.php?saw_route=logout',
			'top'
		);
	}

	/**
	 * Add custom query vars (Phase 4)
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'saw_route';
		$vars[] = 'saw_action';
		$vars[] = 'saw_token';
		$vars[] = 'saw_id';
		return $vars;
	}

	/**
	 * Handle routing (Phase 4)
	 * 
	 * Hlavní dispatcher - předává kontrolu routeru
	 */
	public function handle_routing() {
		// Zkontrolovat jestli je to naše route
		$route = get_query_var( 'saw_route', '' );
		
		if ( empty( $route ) ) {
			return; // Není naše route, pokračovat normálně
		}
		
		// Inicializovat router a předat mu kontrolu
		$this->router = new SAW_Router();
		$this->router->dispatch();
		
		// Router ukončí WordPress processing pomocí exit
	}

	// ========================================
	// ASSETS (STYLES & SCRIPTS)
	// ========================================

	/**
	 * Load admin styles
	 */
	public function enqueue_admin_styles() {
		// Načíst pouze na našich admin stránkách
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'saw-' ) === false ) {
			return;
		}
		
		wp_enqueue_style(
			$this->plugin_name,
			SAW_VISITORS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Load admin scripts
	 */
	public function enqueue_admin_scripts() {
		// Načíst pouze na našich admin stránkách
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'saw-' ) === false ) {
			return;
		}
		
		wp_enqueue_script(
			$this->plugin_name,
			SAW_VISITORS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);
		
		// Localize script pro AJAX
		wp_localize_script(
			$this->plugin_name,
			'sawAdmin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'saw_admin_nonce' ),
			)
		);
	}

	/**
	 * Load public styles
	 */
	public function enqueue_public_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			SAW_VISITORS_PLUGIN_URL . 'assets/css/public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Load public scripts
	 */
	public function enqueue_public_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			SAW_VISITORS_PLUGIN_URL . 'assets/js/public.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	// ========================================
	// ADMIN PAGE DISPLAYS
	// ========================================

	/**
	 * Display dashboard page
	 */
	public function display_dashboard() {
		$selected_customer_id = self::get_selected_customer_id();
		
		?>
		<div class="wrap saw-dashboard">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( ! $selected_customer_id ): ?>
				<div class="notice notice-warning">
					<p><strong>Žádný zákazník není vybrán.</strong> Vytvořte zákazníka v sekci "Zákazníci".</p>
				</div>
			<?php else: ?>
				<?php
				global $wpdb;
				$customer = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
					$selected_customer_id
				) );
				?>
				
				<div class="saw-welcome-panel">
					<h2>Vítejte v SAW Visitors Dashboard</h2>
					<p>Spravujete zákazníka: <strong><?php echo esc_html( $customer->name ); ?></strong></p>
					
					<h3>📋 Dostupné funkce:</h3>
					<ul>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-customers' ) ); ?>">Správa zákazníků</a> - CRUD operace</li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-content' ) ); ?>">Správa obsahu</a> - Školící materiály a dokumenty</li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-training-version' ) ); ?>">Verze školení</a> - Reset verze školení</li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-audit-log' ) ); ?>">Audit Log</a> - Prohlížeč auditního záznamu</li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue' ) ); ?>">Email Queue</a> - Monitoring emailové fronty</li>
					</ul>
					
					<h3>🔗 Frontend přístupy:</h3>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/admin/login/' ) ); ?>" target="_blank">Admin Login</a> - Pro administrátory</li>
						<li><a href="<?php echo esc_url( home_url( '/manager/login/' ) ); ?>" target="_blank">Manager Login</a> - Pro manažery</li>
						<li><a href="<?php echo esc_url( home_url( '/terminal/login/' ) ); ?>" target="_blank">Terminal Login</a> - Pro terminály</li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display about page
	 */
	public function display_about() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="saw-about">
				<h2>SAW Visitors v<?php echo esc_html( $this->version ); ?></h2>
				<p>Plugin pro správu návštěvníků s multi-tenant architekturou.</p>
				
				<h3>📋 Instalované komponenty:</h3>
				<ul>
					<li>✅ Phase 0: Multi-tenant Foundation</li>
					<li>✅ Phase 1: Core Setup</li>
					<li>✅ Phase 2: Database Tables (22/22)</li>
					<li>✅ Phase 3: Auth System</li>
					<li>✅ Phase 4: URL Routing</li>
					<li>✅ Phase 5: Super Admin WP Menu</li>
				</ul>
				
				<h3>🔧 Technické informace:</h3>
				<ul>
					<li><strong>Verze:</strong> <?php echo esc_html( $this->version ); ?></li>
					<li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
					<li><strong>WordPress:</strong> <?php echo get_bloginfo( 'version' ); ?></li>
					<li><strong>MySQL:</strong> <?php global $wpdb; echo $wpdb->db_version(); ?></li>
				</ul>
				
				<h3>📖 Dokumentace:</h3>
				<p>Pro více informací o použití pluginu, kontaktujte vývojáře.</p>
			</div>
		</div>
		<?php
	}

	// ========================================
	// PUBLIC METHODS
	// ========================================

	/**
	 * Run loader
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get plugin name
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Get version
	 */
	public function get_version() {
		return $this->version;
	}
}
