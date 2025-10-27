<?php
/**
 * Hlavní třída pluginu SAW Visitors v4.6.1 (CLEAN VERSION)
 * 
 * Orchestruje základní komponenty:
 * - Loader (hooks management)
 * - URL Routing (frontend: /admin/, /manager/, /terminal/)
 * - Session management
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
		$this->define_hooks();
	}

	/**
	 * Load všechny závislosti
	 */
	private function load_dependencies() {
		// Core classes
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-loader.php';
		
		// Routing
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-router.php';
		
		// Auth & Session (pokud existují, jinak je vytvoříme později)
		if ( file_exists( SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-auth.php' ) ) {
			require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-auth.php';
		}
		
		if ( file_exists( SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-session.php' ) ) {
			require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-session.php';
		}
		
		if ( file_exists( SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-password.php' ) ) {
			require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-password.php';
		}
		
		if ( file_exists( SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php' ) ) {
			require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php';
		}
		
		if ( file_exists( SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-audit.php' ) ) {
			require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-audit.php';
		}
		
		$this->loader = new SAW_Loader();
	}

	/**
	 * Initialize session
	 */
	private function init_session() {
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}
	}

	/**
	 * Define all hooks
	 */
	private function define_hooks() {
		// Routing hooks
		$this->loader->add_action( 'init', $this, 'register_rewrite_rules' );
		$this->loader->add_filter( 'query_vars', $this, 'add_query_vars' );
		$this->loader->add_action( 'template_redirect', $this, 'handle_routing', 1 );
		
		// Public styles & scripts
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_scripts' );
		
		// Admin styles & scripts (pro WordPress admin - jen pokud Super Admin)
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_scripts' );
		
		// Minimální WP Admin menu (jen "O pluginu")
		$this->loader->add_action( 'admin_menu', $this, 'add_minimal_admin_menu' );
	}

	/**
	 * Add minimal WordPress admin menu (jen info o pluginu)
	 */
	public function add_minimal_admin_menu() {
		add_menu_page(
			'SAW Visitors',
			'SAW Visitors',
			'manage_options',
			'saw-visitors-about',
			array( $this, 'display_about_page' ),
			'dashicons-groups',
			30
		);
	}

	/**
	 * Display about page
	 */
	public function display_about_page() {
		?>
		<div class="wrap">
			<h1>SAW Visitors <?php echo esc_html( $this->version ); ?></h1>
			<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; max-width: 800px;">
				<h2>O pluginu</h2>
				<p><strong>Verze:</strong> <?php echo esc_html( $this->version ); ?></p>
				<p><strong>Popis:</strong> Komplexní systém pro správu návštěvníků s multi-tenant architekturou a frontend admin systémem.</p>
				
				<h3>Přístupové URL:</h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Super Admin:</strong> <a href="<?php echo home_url('/admin/'); ?>" target="_blank"><?php echo home_url('/admin/'); ?></a></li>
					<li><strong>Admin (zákazník):</strong> <a href="<?php echo home_url('/admin/'); ?>" target="_blank"><?php echo home_url('/admin/'); ?></a></li>
					<li><strong>Manager:</strong> <a href="<?php echo home_url('/manager/'); ?>" target="_blank"><?php echo home_url('/manager/'); ?></a></li>
					<li><strong>Terminal:</strong> <a href="<?php echo home_url('/terminal/'); ?>" target="_blank"><?php echo home_url('/terminal/'); ?></a></li>
				</ul>
				
				<h3>Technické informace:</h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li>PHP: <?php echo PHP_VERSION; ?> (požadováno: 8.1+)</li>
					<li>WordPress: <?php echo get_bloginfo('version'); ?> (požadováno: 6.0+)</li>
					<li>Multi-tenant: ✓</li>
					<li>Frontend admin: ✓</li>
				</ul>
				
				<hr style="margin: 30px 0;">
				
				<h3>Dokumentace:</h3>
				<p>Pro detailní technickou dokumentaci viz projektové soubory v <code>/mnt/project/</code></p>
				
				<h3>Podpora:</h3>
				<p>Email: <a href="mailto:support@sawuh.cz">support@sawuh.cz</a></p>
			</div>
		</div>
		<?php
	}

	// ========================================
	// ROUTING
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
		$this->router->dispatch();
		exit;
	}

	// ========================================
	// ENQUEUE SCRIPTS & STYLES
	// ========================================

	public function enqueue_admin_styles() {
		// Minimální styling pro WP Admin "O pluginu" stránku
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'saw-visitors-about' ) {
			wp_enqueue_style( 
				$this->plugin_name . '-admin', 
				SAW_VISITORS_PLUGIN_URL . 'assets/css/admin.css', 
				array(), 
				$this->version 
			);
		}
	}

	public function enqueue_admin_scripts() {
		// Zatím prázdné, později pro AJAX v "O pluginu"
	}

	public function enqueue_public_styles() {
		// Frontend styling (pro /admin/, /manager/, /terminal/)
		if ( get_query_var( 'saw_route' ) ) {
			wp_enqueue_style( 
				$this->plugin_name . '-public', 
				SAW_VISITORS_PLUGIN_URL . 'assets/css/public.css', 
				array(), 
				$this->version 
			);
		}
	}

	public function enqueue_public_scripts() {
		// Frontend JS
		if ( get_query_var( 'saw_route' ) ) {
			wp_enqueue_script( 
				$this->plugin_name . '-public', 
				SAW_VISITORS_PLUGIN_URL . 'assets/js/public.js', 
				array( 'jquery' ), 
				$this->version, 
				true 
			);
		}
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