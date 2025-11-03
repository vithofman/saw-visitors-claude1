<?php
/**
 * SAW Database Helper
 * 
 * Utility třída pro práci s databází - helper metody pro dotazy, bezpečnost, multi-language
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Database_Helper {
    
    /**
     * Seznam všech tabulek v pořadí závislostí
     * TOTAL: 34 tables
     * 
     * @return array
     */
    public static function get_tables_order() {
        return array(
            // Core (5)
            'customers',
            'customer_api_keys',
            'users',
	    'permissions',
            'training_config',
            'account_types',
            
            // POI System (9)
            'beacons',
            'pois',
            'routes',
            'route_pois',
            'poi_content',
            'poi_media',
            'poi_pdfs',
            'poi_risks',
            'poi_additional_info',
            
            // Multi-tenant Core (5)
            'departments',
            'user_departments',
            'department_materials',
            'department_documents',
            'contact_persons',
  	    'branches',

            //Training language
	    'training_languages',
	    'training_language_branches',
            
            // Visitor Management (8)
            'companies',
            'invitations',
            'invitation_departments',
            'uploaded_docs',
            'visitors',
            'visits',
            'materials',
            'documents',
            
            // System (6)
            'audit_log',
            'error_log',
            'sessions',
            'password_resets',
            'rate_limits',
            'email_queue',
        );
    }
    
    /**
     * Získání plného názvu tabulky
     * 
     * @param string $table_name Název tabulky bez prefixu (např. 'pois')
     * @return string Plný název s prefixem (např. 'wp_saw_pois')
     */
    public static function get_table_name($table_name) {
        global $wpdb;
        return $wpdb->prefix . 'saw_' . $table_name;
    }
    
    /**
     * Kontrola existence tabulky
     * 
     * @param string $table_name Název tabulky bez prefixu
     * @return bool
     */
    public static function table_exists($table_name) {
        global $wpdb;
        $full_name = self::get_table_name($table_name);
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_name));
        return $result === $full_name;
    }
    
    /**
     * Získání všech jazyků použitých v systému pro zákazníka
     * 
     * @param int $customer_id Customer ID
     * @return array ['cs', 'en', 'de', ...]
     */
    public static function get_customer_languages($customer_id) {
        global $wpdb;
        
        $languages = array();
        
        // POI content languages
        if (self::table_exists('poi_content')) {
            $poi_langs = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT language FROM " . self::get_table_name('poi_content') . " WHERE customer_id = %d",
                $customer_id
            ));
            $languages = array_merge($languages, $poi_langs);
        }
        
        // Materials languages
        if (self::table_exists('materials')) {
            $mat_langs = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT language FROM " . self::get_table_name('materials') . " WHERE customer_id = %d",
                $customer_id
            ));
            $languages = array_merge($languages, $mat_langs);
        }
        
        // Department materials languages
        if (self::table_exists('department_materials')) {
            $dept_mat_langs = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT language FROM " . self::get_table_name('department_materials') . " WHERE customer_id = %d",
                $customer_id
            ));
            $languages = array_merge($languages, $dept_mat_langs);
        }
        
        return array_unique(array_filter($languages));
    }
    
    /**
     * Bezpečné vložení POI content
     * 
     * Příklad použití dynamických jazyků
     * 
     * @param array $data Data k vložení
     * @return int|false ID vloženého záznamu nebo false
     */
    public static function insert_poi_content($data) {
        global $wpdb;
        
        $required = array('customer_id', 'poi_id', 'language', 'title');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::get_table_name('poi_content') . " 
            WHERE customer_id = %d AND poi_id = %d AND language = %s",
            $data['customer_id'],
            $data['poi_id'],
            $data['language']
        ));
        
        if ($exists) {
            return self::update_poi_content($exists, $data);
        }
        
        $result = $wpdb->insert(
            self::get_table_name('poi_content'),
            array(
                'customer_id'          => $data['customer_id'],
                'poi_id'               => $data['poi_id'],
                'language'             => $data['language'],
                'title'                => $data['title'],
                'subtitle'             => $data['subtitle'] ?? null,
                'description'          => $data['description'] ?? null,
                'safety_instructions'  => $data['safety_instructions'] ?? null,
                'interesting_facts'    => $data['interesting_facts'] ?? null,
                'technical_specs'      => $data['technical_specs'] ?? null,
                'meta_description'     => $data['meta_description'] ?? null,
                'is_published'         => $data['is_published'] ?? 1,
            ),
            array(
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update POI content
     * 
     * @param int   $id   ID záznamu
     * @param array $data Data k aktualizaci
     * @return int|false Počet změněných řádků nebo false
     */
    public static function update_poi_content($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        $allowed_fields = array(
            'title'               => '%s',
            'subtitle'            => '%s',
            'description'         => '%s',
            'safety_instructions' => '%s',
            'interesting_facts'   => '%s',
            'technical_specs'     => '%s',
            'meta_description'    => '%s',
            'is_published'        => '%d',
        );
        
        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $formats[] = $format;
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            self::get_table_name('poi_content'),
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Získání POI content podle jazyka
     * 
     * @param int    $poi_id      POI ID
     * @param string $language    ISO kód (např. 'cs')
     * @param int    $customer_id Customer ID
     * @return object|null
     */
    public static function get_poi_content($poi_id, $language, $customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name('poi_content') . " 
            WHERE customer_id = %d AND poi_id = %d AND language = %s AND is_published = 1",
            $customer_id,
            $poi_id,
            $language
        ));
    }
    
    /**
     * Získání všech překladů pro POI
     * 
     * @param int $poi_id      POI ID
     * @param int $customer_id Customer ID
     * @return array Asociativní pole: ['cs' => object, 'en' => object, ...]
     */
    public static function get_poi_translations($poi_id, $customer_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name('poi_content') . " 
            WHERE customer_id = %d AND poi_id = %d AND is_published = 1 
            ORDER BY language",
            $customer_id,
            $poi_id
        ));
        
        $translations = array();
        foreach ($results as $row) {
            $translations[$row->language] = $row;
        }
        
        return $translations;
    }
    
    /**
     * Kontrola customer izolace
     * 
     * KRITICKÉ: Vždy kontroluj customer_id před smazáním/úpravou!
     * 
     * @param string $table_name  Název tabulky
     * @param int    $id          ID záznamu
     * @param int    $customer_id Customer ID
     * @return bool
     */
    public static function verify_customer_access($table_name, $id, $customer_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM " . self::get_table_name($table_name) . " WHERE id = %d",
            $id
        ));
        
        return (int) $result === (int) $customer_id;
    }
    
    /**
     * Smazání s kontrolou customer_id
     * 
     * Bezpečné mazání - zamezuje cross-customer data leak
     * 
     * @param string $table_name  Název tabulky
     * @param int    $id          ID záznamu
     * @param int    $customer_id Customer ID
     * @return bool
     */
    public static function safe_delete($table_name, $id, $customer_id) {
        global $wpdb;
        
        if (!self::verify_customer_access($table_name, $id, $customer_id)) {
            return false;
        }
        
        return $wpdb->delete(
            self::get_table_name($table_name),
            array(
                'id'          => $id,
                'customer_id' => $customer_id,
            ),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Získání počtu záznamů v tabulce
     * 
     * @param string $table_name  Název tabulky
     * @param int    $customer_id Optional customer filter
     * @return int
     */
    public static function get_table_count($table_name, $customer_id = null) {
        global $wpdb;
        
        $full_table_name = self::get_table_name($table_name);
        
        if ($customer_id && self::has_customer_id_column($table_name)) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$full_table_name}` WHERE customer_id = %d",
                $customer_id
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table_name}`");
        }
        
        return (int) $count;
    }
    
    /**
     * Kontrola, zda tabulka má sloupec customer_id
     * 
     * @param string $table_name Název tabulky
     * @return bool
     */
    public static function has_customer_id_column($table_name) {
        global $wpdb;
        
        $full_table_name = self::get_table_name($table_name);
        
        $columns = $wpdb->get_col($wpdb->prepare(
            "SHOW COLUMNS FROM `{$full_table_name}` LIKE %s",
            'customer_id'
        ));
        
        return !empty($columns);
    }
    
    /**
     * Bezpečné získání záznamu s customer kontrolou
     * 
     * @param string $table_name  Název tabulky
     * @param int    $id          ID záznamu
     * @param int    $customer_id Customer ID
     * @return object|null
     */
    public static function get_by_id($table_name, $id, $customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name($table_name) . " 
            WHERE id = %d AND customer_id = %d",
            $id,
            $customer_id
        ));
    }
    
    /**
     * Získání všech záznamů pro zákazníka
     * 
     * @param string $table_name  Název tabulky
     * @param int    $customer_id Customer ID
     * @param string $order_by    ORDER BY clause (např. 'created_at DESC')
     * @param int    $limit       Limit počtu záznamů
     * @return array
     */
    public static function get_all_by_customer($table_name, $customer_id, $order_by = 'id ASC', $limit = null) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::get_table_name($table_name) . " WHERE customer_id = %d",
            $customer_id
        );
        
        if ($order_by) {
            $sql .= " ORDER BY {$order_by}";
        }
        
        if ($limit) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Debug: Výpis struktury tabulky
     * 
     * @param string $table_name Název tabulky
     * @return array
     */
    public static function describe_table($table_name) {
        global $wpdb;
        return $wpdb->get_results("DESCRIBE " . self::get_table_name($table_name));
    }
    
    /**
     * Získání statistik databáze
     * 
     * @param int $customer_id Optional customer filter
     * @return array
     */
    public static function get_database_stats($customer_id = null) {
        $stats = array(
            'total_tables' => 0,
            'tables' => array(),
        );
        
        foreach (self::get_tables_order() as $table_name) {
            if (self::table_exists($table_name)) {
                $stats['total_tables']++;
                $stats['tables'][$table_name] = array(
                    'count' => self::get_table_count($table_name, $customer_id),
                    'has_customer_id' => self::has_customer_id_column($table_name),
                );
            }
        }
        
        return $stats;
    }
}