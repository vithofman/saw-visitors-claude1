<?php
/**
 * SAW Database Installer
 * 
 * Automatická instalace všech tabulek pomocí WordPress dbDelta()
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Installer {
    
    /**
     * Instalace databáze
     * 
     * @return bool
     */
    public static function install() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $prefix = $wpdb->prefix . 'saw_';
        $charset_collate = $wpdb->get_charset_collate();
        
        $schemas_dir = dirname(__FILE__) . '/schemas/';
        $tables = self::get_tables_order();
        
        foreach ($tables as $table_name) {
            $schema_file = $schemas_dir . 'schema-' . str_replace('_', '-', $table_name) . '.php';
            
            if (!file_exists($schema_file)) {
                error_log("SAW Installer: Schema file missing: {$schema_file}");
                continue;
            }
            
            require_once $schema_file;
            
            $function_name = 'saw_get_schema_' . $table_name;
            
            if (!function_exists($function_name)) {
                error_log("SAW Installer: Function missing: {$function_name}");
                continue;
            }
            
            $full_table_name = $prefix . $table_name;
            
            if ($table_name === 'users') {
                $sql = $function_name($full_table_name, $prefix, $wpdb->users, $charset_collate);
            } else {
                $sql = $function_name($full_table_name, $prefix, $charset_collate);
            }
            
            dbDelta($sql);
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log("SAW Installer: Created/updated table: {$full_table_name}");
            }
        }
        
        self::insert_default_data();
        
        update_option('saw_db_version', '4.6.1');
        
        return true;
    }
    
    /**
     * Pořadí tabulek podle závislostí
     * 
     * @return array
     */
    private static function get_tables_order() {
        return array(
            'customers',
            'customer_api_keys',
            'users',
            'training_config',
            'beacons',
            'pois',
            'routes',
            'route_pois',
            'poi_content',
            'poi_media',
            'poi_pdfs',
            'poi_risks',
            'poi_additional_info',
            'departments',
            'user_departments',
            'department_materials',
            'department_documents',
            'contact_persons',
            'companies',
            'invitations',
            'invitation_departments',
            'uploaded_docs',
            'visitors',
            'visits',
            'materials',
            'documents',
            'audit_log',
            'error_log',
            'sessions',
            'password_resets',
            'rate_limits',
            'email_queue',
        );
    }
    
    /**
     * Vložení výchozích dat (volitelné)
     * 
     * @return void
     */
    private static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'saw_';
        
        $customer_exists = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}customers");
        
        if ($customer_exists > 0) {
            return;
        }
        
        $wpdb->insert(
            $prefix . 'customers',
            array(
                'name'          => 'Demo Zákazník',
                'ico'           => '12345678',
                'address'       => 'Demo ulice 123, 100 00 Praha',
                'primary_color' => '#1e40af',
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        $customer_id = $wpdb->insert_id;
        
        $wpdb->insert(
            $prefix . 'training_config',
            array(
                'customer_id'         => $customer_id,
                'training_version'    => 1,
                'skip_threshold_days' => 365,
            ),
            array('%d', '%d', '%d')
        );
    }
    
    /**
     * Deinstalace (smaže všechny tabulky)
     * 
     * POZOR: Toto je destruktivní operace!
     * 
     * @return void
     */
    public static function uninstall() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        $tables = array_reverse(self::get_tables_order());
        
        foreach ($tables as $table_name) {
            $full_table_name = $prefix . $table_name;
            $wpdb->query("DROP TABLE IF EXISTS {$full_table_name}");
        }
        
        delete_option('saw_db_version');
    }
}