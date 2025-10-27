<?php
/**
 * Hlavní třída pluginu SAW Visitors
 * 
 * Stará se o:
 * - Načtení všech potřebných tříd
 * - Inicializaci komponent
 * - Registraci hooks přes Loader
 * - Nastavení internacionalizace
 *
 * @package SAW_Visitors
 */

// Zabránit přímému přístupu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Visitors {

	/**
	 * Loader instance pro správu hooks
	 * 
	 * @var SAW_Loader
	 */
	protected $loader;

	/**
	 * Jedinečný identifikátor pluginu
	 * 
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Verze pluginu
	 * 
	 * @var string
	 */
	protected $version;

	/**
	 * Konstruktor
	 * 
	 * Nastaví název a verzi pluginu, načte závislosti a definuje hooks.
	 */
	public function __construct() {
		$this->version = SAW_VISITORS_VERSION;
		$this->plugin_name = 'saw-visitors';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Načtení všech potřebných tříd
	 * 
	 * Autoloader pro naše třídy.
	 * V produkci je lepší použít Composer autoloader, ale pro FTP je toto jednodušší.
	 */
	private function load_dependencies() {
		// Načíst Loader třídu
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-loader.php';
		
		// Vytvořit instanci loaderu
		$this->loader = new SAW_Loader();
		
		// Zde budeme v budoucnu načítat další třídy:
		// - SAW_Auth (autentizace)
		// - SAW_Database (databázové operace)
		// - SAW_Email (email systém)
		// - SAW_Admin (admin rozhraní)
		// atd.
	}

	/**
	 * Nastavení lokalizace (překlady)
	 * 
	 * V budoucnu umožní překládat plugin do různých jazyků.
	 */
	private function set_locale() {
		$this->loader->add_action(
			'plugins_loaded',
			$this,
			'load_plugin_textdomain'
		);
	}

	/**
	 * Načtení textdomény pro překlady
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'saw-visitors',
			false,
			dirname( SAW_VISITORS_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Definice hooks pro WordPress admin rozhraní
	 * 
	 * Zde registrujeme všechny akce které se týkají WP admin panelu.
	 */
	private function define_admin_hooks() {
		// Enqueue admin styly a scripty
		$this->loader->add_action(
			'admin_enqueue_scripts',
			$this,
			'enqueue_admin_styles'
		);
		
		$this->loader->add_action(
			'admin_enqueue_scripts',
			$this,
			'enqueue_admin_scripts'
		);
		
		// Admin menu (zatím prázdné, ale připravené)
		$this->loader->add_action(
			'admin_menu',
			$this,
			'add_admin_menu'
		);
		
		// Admin notices (pro zobrazení zpráv v admin panelu)
		$this->loader->add_action(
			'admin_notices',
			$this,
			'display_admin_notices'
		);
	}

	/**
	 * Definice hooks pro veřejnou část (frontend)
	 * 
	 * Zde registrujeme všechny akce pro frontend.
	 */
	private function define_public_hooks() {
		// Enqueue frontend styly a scripty
		$this->loader->add_action(
			'wp_enqueue_scripts',
			$this,
			'enqueue_public_styles'
		);
		
		$this->loader->add_action(
			'wp_enqueue_scripts',
			$this,
			'enqueue_public_scripts'
		);
		
		// Custom rewrite rules (zatím prázdné, později pro /admin/, /visitor/ atd.)
		$this->loader->add_action(
			'init',
			$this,
			'register_rewrite_rules'
		);
	}

	/**
	 * Načtení admin stylů
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
	 * Načtení admin scriptů
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
	 * Načtení frontend stylů
	 */
	public function enqueue_public_styles() {
		// Zatím prázdné - později pro visitor formuláře
	}

	/**
	 * Načtení frontend scriptů
	 */
	public function enqueue_public_scripts() {
		// Zatím prázdné - později pro visitor formuláře
	}

	/**
	 * Přidání admin menu do WordPress
	 */
	public function add_admin_menu() {
		// Hlavní menu položka
		add_menu_page(
			'SAW Visitors',              // Název stránky
			'SAW Visitors',              // Text v menu
			'manage_options',            // Oprávnění (pouze admin)
			'saw-visitors',              // Slug
			array( $this, 'display_dashboard' ), // Callback funkce
			'dashicons-groups',          // Ikona
			30                           // Pozice v menu
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
	 * Zobrazení dashboard stránky
	 */
	public function display_dashboard() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="saw-dashboard">
				<div class="saw-welcome-panel">
					<h2>👋 Vítejte v SAW Visitors!</h2>
					<p>Plugin byl úspěšně aktivován. Verze: <strong><?php echo esc_html( $this->version ); ?></strong></p>
					
					<div class="saw-info-boxes">
						<div class="saw-info-box">
							<h3>🎯 První kroky</h3>
							<ol>
								<li>Vytvořte prvního zákazníka</li>
								<li>Přidejte oddělení</li>
								<li>Nahrajte školící materiály</li>
								<li>Vytvořte první pozvánku</li>
							</ol>
						</div>
						
						<div class="saw-info-box">
							<h3>📊 Statistiky</h3>
							<p>Aktivní návštěvy: <strong>0</strong></p>
							<p>Dnes návštěv: <strong>0</strong></p>
							<p>Tento měsíc: <strong>0</strong></p>
						</div>
						
						<div class="saw-info-box">
							<h3>⚙️ Systémové info</h3>
							<p>PHP verze: <strong><?php echo PHP_VERSION; ?></strong></p>
							<p>WordPress: <strong><?php echo get_bloginfo( 'version' ); ?></strong></p>
							<p>MySQL verze: <strong><?php echo $this->get_mysql_version(); ?></strong></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Zobrazení "O pluginu" stránky
	 */
	public function display_about() {
		?>
		<div class="wrap">
			<h1>O pluginu SAW Visitors</h1>
			
			<div class="saw-about">
				<h2>Verze <?php echo esc_html( $this->version ); ?></h2>
				
				<h3>📋 O pluginu</h3>
				<p>SAW Visitors je komplexní WordPress plugin pro správu návštěv s důrazem na BOZP/PO compliance a multi-tenant architekturu.</p>
				
				<h3>✨ Klíčové vlastnosti</h3>
				<ul>
					<li>Multi-tenant architektura s úplnou izolací dat</li>
					<li>Dual admin systém (Super Admin + Frontend Admin)</li>
					<li>Školící systém s verzováním</li>
					<li>Draft mode pro firmy</li>
					<li>Walk-in systém</li>
					<li>Check-in/out terminály</li>
					<li>GDPR compliance</li>
				</ul>
				
				<h3>🔧 Technické informace</h3>
				<ul>
					<li><strong>Verze:</strong> <?php echo esc_html( $this->version ); ?></li>
					<li><strong>PHP požadavky:</strong> 8.1+</li>
					<li><strong>WordPress požadavky:</strong> 6.0+</li>
					<li><strong>MySQL požadavky:</strong> 5.7+</li>
				</ul>
				
				<h3>📞 Podpora</h3>
				<p>Pro technickou podporu kontaktujte: <a href="mailto:support@sawuh.cz">support@sawuh.cz</a></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Zobrazení admin notices
	 */
	public function display_admin_notices() {
		// Kontrola PHP verze
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong>SAW Visitors:</strong> 
					Plugin vyžaduje PHP 8.1 nebo vyšší. Aktuální verze: <?php echo PHP_VERSION; ?>
				</p>
			</div>
			<?php
		}
		
		// Kontrola WordPress verze
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong>SAW Visitors:</strong> 
					Plugin vyžaduje WordPress 6.0 nebo vyšší. Aktuální verze: <?php echo $wp_version; ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Registrace custom rewrite rules
	 * 
	 * Později zde budou pravidla pro /admin/, /visitor/, /terminal/, atd.
	 */
	public function register_rewrite_rules() {
		// Zatím prázdné - připraveno pro budoucí implementaci
	}

	/**
	 * Helper funkce pro získání MySQL verze
	 */
	private function get_mysql_version() {
		global $wpdb;
		return $wpdb->get_var( "SELECT VERSION()" );
	}

	/**
	 * Spuštění loaderu
	 * 
	 * Tato metoda se volá z hlavního souboru pluginu.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Získání názvu pluginu
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Získání loader instance
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Získání verze pluginu
	 */
	public function get_version() {
		return $this->version;
	}
}