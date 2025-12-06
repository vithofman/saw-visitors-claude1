<?php
/**
 * SAW PWA - Progressive Web App Support
 *
 * Handles PWA functionality:
 * - Service Worker rewrite rules
 * - Manifest serving
 * - Cache versioning
 *
 * @package    SAW_Visitors
 * @subpackage PWA
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW PWA Class
 *
 * @since 1.0.0
 */
class SAW_PWA {
    
    /**
     * Singleton instance
     *
     * @var SAW_PWA
     */
    private static $instance = null;
    
    /**
     * PWA assets directory URL
     *
     * @var string
     */
    private $pwa_url;
    
    /**
     * PWA assets directory path
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
        
        // Přidej version comment pro cache busting (pouze verze pluginu, ne timestamp)
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
     * @return string
     */
    public function get_theme_color() {
        return '#667eea';
    }
    
    /**
     * Check if PWA is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        // Můžeš přidat podmínku pro zapnutí/vypnutí PWA
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