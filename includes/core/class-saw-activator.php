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
        error_log('========================================');
        error_log('[SAW Activator] START AKTIVACE');
        error_log('========================================');
        
        self::check_requirements();
        self::create_database_tables();
        self::insert_default_data();
        self::create_upload_directories();
        self::set_default_options();
        self::register_and_flush_rewrite_rules();
        
        error_log('[SAW Activator] Plugin aktivován v ' . SAW_VISITORS_VERSION);
        error_log('========================================');
        error_log('[SAW Activator] KONEC AKTIVACE');
        error_log('========================================');
    }

    /**
     * Kontrola požadavků
     * 
     * @return void
     */
    private static function check_requirements() {
        error_log('[SAW Activator] Kontrola požadavků...');
        
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            error_log('[SAW Activator] ERROR: PHP verze ' . PHP_VERSION . ' je příliš stará');
            wp_die('SAW Visitors vyžaduje PHP 8.1+. Vaše verze: ' . PHP_VERSION);
        }
        
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            error_log('[SAW Activator] ERROR: WordPress verze ' . $wp_version . ' je příliš stará');
            wp_die('SAW Visitors vyžaduje WordPress 6.0+. Vaše verze: ' . $wp_version);
        }
        
        error_log('[SAW Activator] ✓ Požadavky splněny (PHP ' . PHP_VERSION . ', WP ' . $wp_version . ')');
    }

    /**
     * Vytvoření databázových tabulek
     * 
     * @return void
     */
    private static function create_database_tables() {
        error_log('[SAW Activator] Vytváření databázových tabulek...');
        
        $db_file = SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-database.php';
        
        error_log('[SAW Activator] Hledám soubor: ' . $db_file);
        
        if (!file_exists($db_file)) {
            error_log('[SAW Activator] ERROR: Soubor class-saw-database.php NEEXISTUJE!');
            error_log('[SAW Activator] Plugin dir: ' . SAW_VISITORS_PLUGIN_DIR);
            error_log('[SAW Activator] Hledaný soubor: ' . $db_file);
            return;
        }
        
        error_log('[SAW Activator] ✓ Soubor existuje, načítám...');
        
        require_once $db_file;
        
        if (!class_exists('SAW_Database')) {
            error_log('[SAW Activator] ERROR: Třída SAW_Database neexistuje po načtení souboru!');
            return;
        }
        
        error_log('[SAW Activator] ✓ Třída SAW_Database načtena');
        error_log('[SAW Activator] Volám SAW_Database::create_tables()...');
        
        $result = SAW_Database::create_tables();
        
        if ($result) {
            error_log('[SAW Activator] ✓ Tabulky vytvořeny úspěšně');
        } else {
            error_log('[SAW Activator] ✗ Chyba při vytváření tabulek');
        }
    }

    /**
     * Vložení výchozích dat
     * 
     * @return void
     */
    private static function insert_default_data() {
        error_log('[SAW Activator] Vkládání výchozích dat...');
        
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        // Kontrola existence tabulky customers
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $prefix . 'customers'
        ));
        
        if ($table_exists !== $prefix . 'customers') {
            error_log('[SAW Activator] ERROR: Tabulka customers neexistuje!');
            return;
        }
        
        error_log('[SAW Activator] ✓ Tabulka customers existuje');
        
        // Kontrola, zda už existují zákazníci
        $customers_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}customers`");
        
        if ($customers_count > 0) {
            error_log('[SAW Activator] ✓ Zákazníci již existují (' . $customers_count . '), přeskakuji vložení demo dat');
            return;
        }
        
        error_log('[SAW Activator] Vkládám demo zákazníka...');
        
        // Vložení demo zákazníka
        $result = $wpdb->insert(
            $prefix . 'customers',
            array(
                'name'          => 'Demo Zákazník',
                'ico'           => '12345678',
                'address'       => 'Demo ulice 123, Praha',
                'primary_color' => '#0073aa',
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('[SAW Activator] ERROR: Chyba při vkládání demo zákazníka: ' . $wpdb->last_error);
        } else {
            error_log('[SAW Activator] ✓ Demo zákazník vložen (ID: ' . $wpdb->insert_id . ')');
        }
    }

    /**
     * Vytvoření upload složek
     * 
     * @return void
     */
    private static function create_upload_directories() {
        error_log('[SAW Activator] Vytváření upload složek...');
        
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/saw-visitor-docs';
        
        error_log('[SAW Activator] Base dir: ' . $base_dir);
        
        if (!file_exists($base_dir)) {
            $result = wp_mkdir_p($base_dir);
            if ($result) {
                error_log('[SAW Activator] ✓ Base složka vytvořena: ' . $base_dir);
            } else {
                error_log('[SAW Activator] ERROR: Nelze vytvořit base složku: ' . $base_dir);
            }
        } else {
            error_log('[SAW Activator] ✓ Base složka již existuje');
        }
        
        $subdirs = array('materials', 'visitor-uploads', 'risk-docs');
        foreach ($subdirs as $subdir) {
            $path = $base_dir . '/' . $subdir;
            if (!file_exists($path)) {
                $result = wp_mkdir_p($path);
                if ($result) {
                    error_log('[SAW Activator] ✓ Podsložka vytvořena: ' . $subdir);
                } else {
                    error_log('[SAW Activator] ERROR: Nelze vytvořit podsložku: ' . $subdir);
                }
            } else {
                error_log('[SAW Activator] ✓ Podsložka již existuje: ' . $subdir);
            }
        }
    }

    /**
     * Nastavení výchozích options
     * 
     * @return void
     */
    private static function set_default_options() {
        error_log('[SAW Activator] Nastavuji výchozí options...');
        
        add_option('saw_db_version', SAW_VISITORS_VERSION);
        add_option('saw_plugin_activated', current_time('mysql'));
        
        error_log('[SAW Activator] ✓ Options nastaveny (saw_db_version: ' . SAW_VISITORS_VERSION . ')');
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
        error_log('[SAW Activator] Registrace rewrite rules...');
        
        $router_file = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
        
        if (!file_exists($router_file)) {
            error_log('[SAW Activator] Router file neexistuje: ' . $router_file);
            return;
        }
        
        error_log('[SAW Activator] ✓ Router file existuje');
        
        require_once $router_file;
        
        if (!class_exists('SAW_Router')) {
            error_log('[SAW Activator] ERROR: Třída SAW_Router neexistuje');
            return;
        }
        
        error_log('[SAW Activator] ✓ Třída SAW_Router načtena');
        
        $router = new SAW_Router();
        
        if (method_exists($router, 'register_routes')) {
            $router->register_routes();
            error_log('[SAW Activator] ✓ Routes zaregistrovány');
        } else {
            error_log('[SAW Activator] ERROR: Metoda register_routes() neexistuje');
        }
        
        flush_rewrite_rules();
        
        error_log('[SAW Activator] ✓ Rewrite rules flushnuto');
    }
}