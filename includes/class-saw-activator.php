<?php
/**
 * SAW Activator
 * 
 * Třída pro aktivaci pluginu - kontrola požadavků, vytvoření DB, složek
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Activator {

    /**
     * Aktivace pluginu
     * 
     * KRITICKÉ: Veškerý výstup je zachycen v saw-visitors.php pomocí ob_start/ob_end_clean
     * Proto můžeme bezpečně volat metody, které by mohly produkovat output
     * 
     * @return void
     */
    public static function activate() {
        self::check_requirements();
        self::create_database_tables();
        self::insert_default_data();
        self::create_upload_directories();
        self::set_default_options();
        self::register_and_flush_rewrite_rules();
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('[SAW Visitors] Plugin aktivován v ' . SAW_VISITORS_VERSION);
        }
    }

    /**
     * Kontrola požadavků
     * 
     * @return void
     */
    private static function check_requirements() {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            wp_die('SAW Visitors vyžaduje PHP 8.1+. Vaše verze: ' . PHP_VERSION);
        }
        
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            wp_die('SAW Visitors vyžaduje WordPress 6.0+. Vaše verze: ' . $wp_version);
        }
    }

    /**
     * Vytvoření databázových tabulek
     * 
     * @return void
     */
    private static function create_database_tables() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-database.php';
        SAW_Database::create_tables();
    }

    /**
     * Vložení výchozích dat
     * 
     * @return void
     */
    private static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        // Kontrola, zda už existují zákazníci
        $customers_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}customers`");
        
        if ($customers_count > 0) {
            return;
        }
        
        // Vložení demo zákazníka
        $wpdb->insert(
            $prefix . 'customers',
            array(
                'name'          => 'Demo Zákazník',
                'ico'           => '12345678',
                'address'       => 'Demo ulice 123, Praha',
                'primary_color' => '#0073aa',
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    /**
     * Vytvoření upload složek
     * 
     * @return void
     */
    private static function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/saw-visitor-docs';
        
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }
        
        $subdirs = array('materials', 'visitor-uploads', 'risk-docs');
        foreach ($subdirs as $subdir) {
            $path = $base_dir . '/' . $subdir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
    }

    /**
     * Nastavení výchozích options
     * 
     * @return void
     */
    private static function set_default_options() {
        add_option('saw_db_version', SAW_VISITORS_VERSION);
        add_option('saw_plugin_activated', current_time('mysql'));
    }

    /**
     * Registrace a flush rewrite rules
     * 
     * KRITICKÉ: Musíme manuálně zaregistrovat rewrite rules před flush,
     * protože 'init' hook se při aktivaci ještě nespustil
     * 
     * @return void
     */
    private static function register_and_flush_rewrite_rules() {
        $router_file = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
        
        if (!file_exists($router_file)) {
            return;
        }
        
        require_once $router_file;
        
        if (!class_exists('SAW_Router')) {
            return;
        }
        
        $router = new SAW_Router();
        
        if (method_exists($router, 'register_routes')) {
            $router->register_routes();
        }
        
        flush_rewrite_rules();
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('[SAW Visitors] Rewrite rules registrovány a flushnuty');
        }
    }
}