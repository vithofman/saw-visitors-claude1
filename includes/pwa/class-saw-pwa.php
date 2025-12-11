<?php
/**
 * SAW PWA (Progressive Web App) Support
 *
 * Enables PWA functionality for terminal - installable app, offline support, etc.
 *
 * @package    SAW_Visitors
 * @subpackage PWA
 * @since      1.0.0
 * @version    2.0.0 - Added AJAX handlers for health check
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW PWA Class
 *
 * Handles PWA manifest, service worker, and meta tags.
 */
class SAW_PWA {
    
    /**
     * Singleton instance
     *
     * @var SAW_PWA
     */
    private static $instance = null;
    
    /**
     * PWA assets URL
     *
     * @var string
     */
    private $pwa_url;
    
    /**
     * PWA assets path
     *
     * @var string
     */
    private $pwa_path;
    
    /**
     * Get singleton instance
     *
     * @return SAW_PWA
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->pwa_url = SAW_VISITORS_PLUGIN_URL . 'assets/pwa/';
        $this->pwa_path = SAW_VISITORS_PLUGIN_DIR . 'assets/pwa/';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Rewrite rules pro service worker
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_pwa_requests'], 1);
        
        // Přidej Service-Worker-Allowed header
        add_filter('wp_headers', [$this, 'add_sw_headers']);
        
        // AJAX handlers pro PWA health check
        add_action('wp_ajax_saw_heartbeat', [$this, 'handle_heartbeat']);
        add_action('wp_ajax_nopriv_saw_heartbeat', [$this, 'handle_heartbeat']);
        add_action('wp_ajax_saw_check_session', [$this, 'handle_check_session']);
        add_action('wp_ajax_nopriv_saw_check_session', [$this, 'handle_check_session']);
    }
    
    /**
     * Register rewrite rules for PWA files
     *
     * Service Worker MUSÍ být servírován z root pro scope "/"
     */
    public function register_rewrite_rules() {
        // /sw.js -> serves service-worker.js
        add_rewrite_rule(
            '^sw\.js$',
            'index.php?saw_pwa_file=service-worker',
            'top'
        );
        
        // /manifest.json -> serves manifest.json (alternativní cesta)
        add_rewrite_rule(
            '^manifest\.json$',
            'index.php?saw_pwa_file=manifest',
            'top'
        );
    }
    
    /**
     * Register query variables
     *
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function register_query_vars($vars) {
        $vars[] = 'saw_pwa_file';
        return $vars;
    }
    
    /**
     * Handle PWA file requests
     */
    public function handle_pwa_requests() {
        $pwa_file = get_query_var('saw_pwa_file');
        
        if (empty($pwa_file)) {
            return;
        }
        
        switch ($pwa_file) {
            case 'service-worker':
                $this->serve_service_worker();
                break;
                
            case 'manifest':
                $this->serve_manifest();
                break;
        }
    }
    
    /**
     * Serve service worker file
     */
    private function serve_service_worker() {
        $sw_file = $this->pwa_path . 'service-worker.js';
        
        if (!file_exists($sw_file)) {
            status_header(404);
            exit('Service Worker not found');
        }
        
        // Headers pro Service Worker
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        
        // Přidej version comment pro cache busting
        echo "// Version: " . SAW_VISITORS_VERSION . "\n\n";
        
        readfile($sw_file);
        exit;
    }
    
    /**
     * Serve manifest file
     */
    private function serve_manifest() {
        $manifest_file = $this->pwa_path . 'manifest.json';
        
        if (!file_exists($manifest_file)) {
            status_header(404);
            exit('Manifest not found');
        }
        
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=86400'); // 24h cache
        
        readfile($manifest_file);
        exit;
    }
    
    /**
     * Add Service-Worker-Allowed header
     *
     * @param array $headers
     * @return array
     */
    public function add_sw_headers($headers) {
        // Přidej header pouze pro JS soubory z PWA složky
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/assets/pwa/') !== false) {
            $headers['Service-Worker-Allowed'] = '/';
        }
        return $headers;
    }
    
    // ============================================
    // AJAX HANDLERS PRO PWA HEALTH CHECK
    // ============================================
    
    /**
     * Heartbeat handler - kontroluje zda server odpovídá
     * 
     * Voláno z pwa-register.js pro kontrolu connectivity
     * po návratu uživatele z pozadí.
     *
     * @since 2.0.0
     */
    public function handle_heartbeat() {
        wp_send_json_success([
            'alive' => true,
            'time'  => current_time('mysql')
        ]);
    }
    
    /**
     * Session check handler - kontroluje zda je uživatel přihlášen
     * 
     * Voláno z pwa-register.js po návratu z pozadí pro detekci
     * expirované session. Pokud session vypršela, frontend
     * zobrazí notifikaci a přesměruje na login.
     *
     * @since 2.0.0
     */
    public function handle_check_session() {
        $logged_in = false;
        $session_type = 'none';
        
        // 1. Zkontroluj SAW session (primární metoda)
        if (class_exists('SAW_Session')) {
            $session = SAW_Session::get_instance();
            if ($session && $session->is_logged_in()) {
                $logged_in = true;
                $session_type = 'saw';
            }
        }
        
        // 2. Zkontroluj WordPress session (fallback)
        if (!$logged_in && is_user_logged_in()) {
            $logged_in = true;
            $session_type = 'wordpress';
        }
        
        // 3. Zkontroluj custom PHP session (fallback)
        if (!$logged_in) {
            // Zajisti že session je nastartovaná
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            
            if (isset($_SESSION['saw_user_id']) && !empty($_SESSION['saw_user_id'])) {
                $logged_in = true;
                $session_type = 'php_session';
            }
        }
        
        wp_send_json_success([
            'logged_in'    => $logged_in,
            'session_type' => $session_type,
            'time'         => current_time('mysql')
        ]);
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    /**
     * Get manifest URL
     *
     * @return string
     */
    public function get_manifest_url() {
        // Použij rewritten URL pro čistší path
        return home_url('/manifest.json');
    }
    
    /**
     * Get PWA assets URL
     *
     * @return string
     */
    public function get_pwa_url() {
        return $this->pwa_url;
    }
    
    /**
     * Get theme color
     *
     * Darker purple that matches terminal gradient and works well with light text.
     * Original was #667eea which had poor contrast with dark text.
     *
     * @since 1.1.0 Changed from #667eea to #312e81 for better contrast
     * @return string
     */
    public function get_theme_color() {
        return '#312e81';
    }
    
    /**
     * Check if PWA is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return true;
    }
    
    /**
     * Flush rewrite rules (call on activation)
     */
    public static function flush_rules() {
        $instance = self::instance();
        $instance->register_rewrite_rules();
        flush_rewrite_rules(false);
    }
}

// Inicializace
SAW_PWA::instance();