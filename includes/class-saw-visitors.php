<?php
/**
 * Hlavní třída pluginu SAW Visitors
 * 
 * Orchestruje všechny komponenty pluginu:
 * - Loader (hooks management)
 * - Admin interface
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
		
		// Inicializovat loader
		$this->loader = new SAW_Loader();
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
		
		// Flush rewrite rules při aktivaci (handled in activator)
	}

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
		
		add_rewrite_rule(
			'^admin/companies/?$',
			'index.php?saw_route=admin&saw_action=companies',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/visitors/?$',
			'index.php?saw_route=admin&saw_action=visitors',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/departments/?$',
			'index.php?saw_route=admin&saw_action=departments',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/content/?$',
			'index.php?saw_route=admin&saw_action=content',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/statistics/?$',
			'index.php?saw_route=admin&saw_action=statistics',
			'top'
		);
		
		add_rewrite_rule(
			'^admin/settings/?$',
			'index.php?saw_route=admin&saw_action=settings',
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
		
		add_rewrite_rule(
			'^manager/invitations/?$',
			'index.php?saw_route=manager&saw_action=invitations',
			'top'
		);
		
		add_rewrite_rule(
			'^manager/visitors/?$',
			'index.php?saw_route=manager&saw_action=visitors',
			'top'
		);
		
		add_rewrite_rule(
			'^manager/statistics/?$',
			'index.php?saw_route=manager&saw_action=statistics',
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

	/**
	 * Load admin styles
	 */
	public function enqueue_admin_styles() {
		// Načíst pouze na našich admin stránkách
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'saw-visitors' ) === false ) {
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
		if ( ! $screen || strpos( $screen->id, 'saw-visitors' ) === false ) {
			return;
		}
		
		wp_enqueue_script(
			$this->plugin_name,
			SAW_VISITORS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);
		
		// Předat data do JavaScriptu
		wp_localize_script(
			$this->plugin_name,
			'sawVisitorsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'saw_admin_nonce' ),
				'pluginUrl' => SAW_VISITORS_PLUGIN_URL,
			)
		);
	}

	/**
	 * Load public styles
	 */
	public function enqueue_public_styles() {
		// Zatím prázdné - později pro visitor formuláře
	}

	/**
	 * Load public scripts
	 */
	public function enqueue_public_scripts() {
		// Zatím prázdné - později pro visitor formuláře
	}

	/**
	 * Add admin menu
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

	/**
	 * Display dashboard page
	 */
	public function display_dashboard() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="saw-dashboard">
				<div class="saw-welcome-panel">
					<h2>👋 Vítejte v SAW Visitors!</h2>
					<p>Plugin byl úspěšně aktivován. Nyní můžete začít konfigurovat zákazníky a oddělení.</p>
					
					<h3>🔗 Odkazy pro testování:</h3>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/admin/login/' ) ); ?>" target="_blank">Admin Login</a></li>
						<li><a href="<?php echo esc_url( home_url( '/manager/login/' ) ); ?>" target="_blank">Manager Login</a></li>
						<li><a href="<?php echo esc_url( home_url( '/terminal/login/' ) ); ?>" target="_blank">Terminal Login</a></li>
					</ul>
					
					<h3>📊 Statistiky:</h3>
					<p><em>Zatím neimplementováno - následující fáze vývoje.</em></p>
				</div>
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
				</ul>
				
				<h3>🔧 Další vývoj:</h3>
				<ul>
					<li>Phase 5: Super Admin WP Menu</li>
					<li>Phase 6-24: Další funkcionality</li>
				</ul>
			</div>
		</div>
		<?php
	}

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
