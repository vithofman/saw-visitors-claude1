<?php
/**
 * Hlavní třída pluginu SAW Visitors
 * 
 * Orchestruje všechny komponenty pluginu přes SAW_Loader.
 * ❌ ODSTRANĚNO: WP Admin menu (používáme vlastní frontend)
 * ✅ ZACHOVÁNO: Routing, Auth, Database, Public flow
 *
 * @package SAW_Visitors
 */

// Zabránít přímému přístupu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Visitors {

	/**
	 * Loader instance
	 * 
	 * @var SAW_Loader
	 */
	protected $loader;

	/**
	 * Plugin name
	 * 
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Plugin version
	 * 
	 * @var string
	 */
	protected $version;

	/**
	 * Konstruktor
	 * 
	 * Načte dependencies a nastaví locale, hooks pro assets a routing.
	 */
	public function __construct() {
		$this->plugin_name = 'saw-visitors';
		$this->version = SAW_VISITORS_VERSION;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_public_hooks();
		$this->define_routing_hooks();
	}

	/**
	 * Načtení všech potřebných tříd
	 * 
	 * Core třídy pro fungování pluginu:
	 * - Loader (hooks manager)
	 * - i18n (překlady)
	 * - Database (schema)
	 * - Auth (session management)
	 * - Router (custom URLs)
	 * - Audit (logging)
	 */
	private function load_dependencies() {
		// Core classes
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-loader.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-i18n.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-auth.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-session.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-router.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-audit.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/middleware.php';

		// Create loader instance
		$this->loader = new SAW_Loader();
	}

	/**
	 * Nastavení lokalizace (překlady)
	 * 
	 * Registruje hook pro načtení překladů z /languages/
	 */
	private function set_locale() {
		$plugin_i18n = new SAW_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Registrace public hooks (assets pro veřejnou část)
	 * 
	 * Enqueue CSS/JS pro:
	 * - Visitor flow (terminál, checkin, školení)
	 * - Frontend admin aplikaci (když už ji budeme mít hotovou)
	 */
	private function define_public_hooks() {
		// Enqueue public styles and scripts
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_scripts' );
	}

	/**
	 * Registrace routing hooks
	 * 
	 * Custom URL struktury:
	 * - /admin/* (Admin + SuperAdmin frontend)
	 * - /manager/* (Manager frontend)
	 * - /terminal/* (Visitor checkin)
	 * - /visit/* (Visitor flow - školení, podpis, checkout)
	 */
	private function define_routing_hooks() {
		$router = new SAW_Router();
		
		// Rewrite rules
		$this->loader->add_action( 'init', $router, 'register_rewrite_rules' );
		
		// Query vars
		$this->loader->add_filter( 'query_vars', $router, 'register_query_vars' );
		
		// Template redirect
		$this->loader->add_action( 'template_redirect', $router, 'handle_routes', 5 );
		
		// Template include
		$this->loader->add_filter( 'template_include', $router, 'load_blank_template', 99 );
	}

	/**
	 * Spustit loader
	 * 
	 * Zaregistruje všechny actions a filters do WordPressu.
	 * Volá se v saw-visitors.php po vytvoření instance této třídy.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get plugin name
	 * 
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get loader
	 * 
	 * @return SAW_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Get version
	 * 
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	// ========================================
	// ENQUEUE SCRIPTS & STYLES
	// ========================================

	/**
	 * Enqueue public styles
	 * 
	 * CSS pro:
	 * - Visitor flow (terminál, školení, checkout)
	 * - Frontend aplikaci (až ji vytvoříme)
	 */
	public function enqueue_public_styles() {
		// Public CSS (visitor flow)
		wp_enqueue_style( 
			$this->plugin_name, 
			SAW_VISITORS_PLUGIN_URL . 'assets/css/public.css', 
			array(), 
			$this->version, 
			'all' 
		);
	}

	/**
	 * Enqueue public scripts
	 * 
	 * JS pro:
	 * - Visitor flow
	 * - Frontend aplikaci (až ji vytvoříme)
	 */
	public function enqueue_public_scripts() {
		// Public JS (visitor flow)
		wp_enqueue_script( 
			$this->plugin_name, 
			SAW_VISITORS_PLUGIN_URL . 'assets/js/public.js', 
			array( 'jquery' ), 
			$this->version, 
			false 
		);
	}
}
