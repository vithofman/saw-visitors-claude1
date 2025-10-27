<?php
/**
 * Hlavní třída pluginu SAW Visitors (UPDATED Phase 5 - v4.6.1)
 * 
 * Orchestruje všechny komponenty pluginu:
 * - Loader (hooks management)
 * - Admin interface (Phase 5: Super Admin menu s customer dropdown)
 * - Public interface
 * - URL Routing (Phase 4)
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Visitors {

	protected $loader;
	protected $plugin_name;
	protected $version;
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
		
		$this->loader = new SAW_Loader();
	}

	/**
	 * Initialize session (Phase 5)
	 */
	private function init_session() {
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}
	}

	/**
	 * Define admin hooks
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
	 * Define public hooks
	 */
	private function define_public_hooks() {
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_scripts' );
	}

	/**
	 * Define routing hooks (Phase 4)
	 */
	private function define_routing_hooks() {
		$this->loader->add_action( 'init', $this, 'register_rewrite_rules' );
		$this->loader->add_filter( 'query_vars', $this, 'add_query_vars' );
		$this->loader->add_action( 'template_redirect', $this, 'handle_routing', 1 );
	}

	// ========================================
	// PHASE 5: ADMIN MENU & CUSTOMER MANAGEMENT
	// ========================================

	/**
	 * Add admin menu (Phase 5 - ROZŠÍŘENÁ VERZE)
	 */
	public function add_admin_menu() {
		// Hlavní menu
		add_menu_page(
			'SAW Visitors',
			'SAW Visitors',
			'manage_options',
			'saw-visitors',
			array( $this, 'display_dashboard' ),
			'dashicons-groups',
			30
		);
		
		// Dashboard
		add_submenu_page(
			'saw-visitors',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'saw-visitors',
			array( $this, 'display_dashboard' )
		);
		
		// Zákazníci
		add_submenu_page(
			'saw-visitors',
			'Zákazníci',
			'Zákazníci',
			'manage_options',
			'saw-customers',
			array( 'SAW_Admin_Customers', 'list_page' )
		);
		
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
		
		// Správa obsahu
		add_submenu_page(
			'saw-visitors',
			'Správa obsahu',
			'Správa obsahu',
			'manage_options',
			'saw-content',
			array( 'SAW_Admin_Content', 'main_page' )
		);
		
		// Verze školení
		add_submenu_page(
			'saw-visitors',
			'Verze školení',
			'Verze školení',
			'manage_options',
			'saw-training-version',
			array( 'SAW_Admin_Training_Version', 'main_page' )
		);
		
		// Audit Log
		add_submenu_page(
			'saw-visitors',
			'Audit Log',
			'Audit Log',
			'manage_options',
			'saw-audit-log',
			array( 'SAW_Admin_Audit_Log', 'main_page' )
		);
		
		// Email Queue
		add_submenu_page(
			'saw-visitors',
			'Email Queue',
			'Email Queue',
			'manage_options',
			'saw-email-queue',
			array( 'SAW_Admin_Email_Queue', 'main_page' )
		);
		
		// O pluginu
		add_submenu_page(
			'saw-visitors',
			'O pluginu',
			'O pluginu',
			'manage_options',
			'saw-visitors-about',
			array( $this, 'display_about' )
		);
	}

	/**
	 * Initialize customer session
	 */
	public function init_customer_session() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
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
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'saw-' ) === false ) {
			return;
		}

		global $wpdb;
		$customers = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}saw_customers ORDER BY name ASC" );
		
		if ( empty( $customers ) ) {
			return;
		}

		$selected_id = isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
		$selected_customer = null;

		foreach ( $customers as $customer ) {
			if ( $customer->id === $selected_id ) {
				$selected_customer = $customer;
				break;
			}
		}

		$title = $selected_customer ? '👤 ' . $selected_customer->name : '👤 Vyberte zákazníka';

		$wp_admin_bar->add_node( array(
			'id'    => 'saw-customer-selector',
			'title' => $title,
			'href'  => '#',
		) );

		foreach ( $customers as $customer ) {
			$is_current = ( $customer->id === $selected_id );
			$class = $is_current ? 'saw-customer-selected' : '';

			$wp_admin_bar->add_node( array(
				'id'     => 'saw-customer-' . $customer->id,
				'parent' => 'saw-customer-selector',
				'title'  => ( $is_current ? '✓ ' : '' ) . $customer->name,
				'href'   => wp_nonce_url(
					add_query_arg( array(
						'saw_action'  => 'switch_customer',
						'customer_id' => $customer->id,
					) ),
					'saw_switch_customer_' . $customer->id
				),
				'meta'   => array( 'class' => $class ),
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

		global $wpdb;
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, name FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );

		if ( ! $customer ) {
			wp_die( 'Zákazník nenalezen.' );
		}

		$_SESSION['saw_selected_customer_id'] = $customer_id;

		SAW_Audit::log( array(
			'action'      => 'customer_switched',
			'customer_id' => $customer_id,
			'details'     => 'Super Admin switched to customer: ' . $customer->name,
		) );

		$redirect_url = remove_query_arg( array( 'saw_action', 'customer_id', '_wpnonce' ) );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Display dashboard
	 */
	public function display_dashboard() {
		$customer_id = isset( $_SESSION['saw_selected_customer_id'] ) ? intval( $_SESSION['saw_selected_customer_id'] ) : 0;
		
		if ( ! $customer_id ) {
			?>
			<div class="wrap">
				<h1>Dashboard</h1>
				<div class="notice notice-warning">
					<p><strong>⚠️ Nejprve vyberte zákazníka</strong> z dropdownu v horní liště.</p>
				</div>
			</div>
			<?php
			return;
		}

		global $wpdb;
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
			$customer_id
		) );

		// Stats
		$stats = array(
			'active_visits'   => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE customer_id = %d AND status = 'active'",
				$customer_id
			) ),
			'planned_visits'  => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}saw_invitations WHERE customer_id = %d AND status = 'sent' AND visit_date >= CURDATE()",
				$customer_id
			) ),
			'total_visitors'  => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE customer_id = %d",
				$customer_id
			) ),
			'departments'     => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE customer_id = %d",
				$customer_id
			) ),
		);

		?>
		<div class="wrap">
			<h1>
				Dashboard
				<span style="background: #2271b1; color: white; padding: 5px 15px; border-radius: 4px; font-size: 14px; margin-left: 10px;">
					<?php echo esc_html( $customer->name ); ?>
				</span>
			</h1>

			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center;">
					<div style="font-size: 48px; font-weight: 700; color: #00a32a; margin-bottom: 10px;">
						<?php echo esc_html( $stats['active_visits'] ); ?>
					</div>
					<div style="color: #646970; font-size: 14px; text-transform: uppercase; font-weight: 600;">
						Aktivní návštěvy
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center;">
					<div style="font-size: 48px; font-weight: 700; color: #2271b1; margin-bottom: 10px;">
						<?php echo esc_html( $stats['planned_visits'] ); ?>
					</div>
					<div style="color: #646970; font-size: 14px; text-transform: uppercase; font-weight: 600;">
						Plánované návštěvy
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center;">
					<div style="font-size: 48px; font-weight: 700; color: #2271b1; margin-bottom: 10px;">
						<?php echo esc_html( $stats['total_visitors'] ); ?>
					</div>
					<div style="color: #646970; font-size: 14px; text-transform: uppercase; font-weight: 600;">
						Celkem návštěvníků
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center;">
					<div style="font-size: 48px; font-weight: 700; color: #2271b1; margin-bottom: 10px;">
						<?php echo esc_html( $stats['departments'] ); ?>
					</div>
					<div style="color: #646970; font-size: 14px; text-transform: uppercase; font-weight: 600;">
						Oddělení
					</div>
				</div>
			</div>

			<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-top: 20px;">
				<h2 style="margin-top: 0;">🚀 Rychlé akce</h2>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-content' ) ); ?>" class="button button-primary">Spravovat obsah</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-training-version' ) ); ?>" class="button">Verze školení</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-audit-log' ) ); ?>" class="button">Audit Log</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saw-email-queue' ) ); ?>" class="button">Email Queue</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Display about page
	 */
	public function display_about() {
		?>
		<div class="wrap">
			<h1>O pluginu SAW Visitors</h1>
			<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
				<p><strong>Verze:</strong> <?php echo esc_html( $this->version ); ?></p>
				<p><strong>Popis:</strong> Komplexní systém pro správu návštěvníků s multi-tenant architekturou.</p>
				<h3>Hlavní funkce:</h3>
				<ul>
					<li>Multi-tenant architektura (více zákazníků v jedné instalaci)</li>
					<li>Školící materiály per jazyk (CS, EN, DE, UK)</li>
					<li>Verzování školení (force re-training)</li>
					<li>Draft mode (firma vyplňuje návštěvníky)</li>
					<li>Skip training (pokud do 1 roku)</li>
					<li>Walk-in návštěvníci</li>
					<li>Email queue s retry mechanismem</li>
					<li>Audit log</li>
					<li>Compliance reporting</li>
				</ul>
			</div>
		</div>
		<?php
	}

	// ========================================
	// ENQUEUE SCRIPTS & STYLES
	// ========================================

	public function enqueue_admin_styles() {
		wp_enqueue_style( $this->plugin_name, SAW_VISITORS_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version, 'all' );
		
		// Inline styles pro admin bar dropdown
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

	public function enqueue_admin_scripts() {
		wp_enqueue_script( $this->plugin_name, SAW_VISITORS_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $this->version, false );
	}

	public function enqueue_public_styles() {
		wp_enqueue_style( $this->plugin_name, SAW_VISITORS_PLUGIN_URL . 'assets/css/public.css', array(), $this->version, 'all' );
	}

	public function enqueue_public_scripts() {
		wp_enqueue_script( $this->plugin_name, SAW_VISITORS_PLUGIN_URL . 'assets/js/public.js', array( 'jquery' ), $this->version, false );
	}

	// ========================================
	// PHASE 4: ROUTING
	// ========================================

	public function register_rewrite_rules() {
		add_rewrite_rule( '^admin/?', 'index.php?saw_route=admin', 'top' );
		add_rewrite_rule( '^admin/(.+)', 'index.php?saw_route=admin&saw_path=$matches[1]', 'top' );
		
		add_rewrite_rule( '^manager/?', 'index.php?saw_route=manager', 'top' );
		add_rewrite_rule( '^manager/(.+)', 'index.php?saw_route=manager&saw_path=$matches[1]', 'top' );
		
		add_rewrite_rule( '^terminal/?', 'index.php?saw_route=terminal', 'top' );
		add_rewrite_rule( '^terminal/(.+)', 'index.php?saw_route=terminal&saw_path=$matches[1]', 'top' );
		
		add_rewrite_rule( '^visitor/?', 'index.php?saw_route=visitor', 'top' );
		add_rewrite_rule( '^visitor/(.+)', 'index.php?saw_route=visitor&saw_path=$matches[1]', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'saw_route';
		$vars[] = 'saw_path';
		return $vars;
	}

	public function handle_routing() {
		$route = get_query_var( 'saw_route' );
		
		if ( ! $route ) {
			return;
		}

		$this->router = new SAW_Router();
		$this->router->dispatch( $route, get_query_var( 'saw_path' ) );
		exit;
	}

	// ========================================
	// RUN
	// ========================================

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
