<?php
/**
 * OOPP Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     2.0.0 - ADDED: Translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_OOPP_Model extends SAW_Base_Model 
{
    /**
     * Translation function
     * @var callable
     */
    private $tr;
    
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 600;
        
        // ============================================
        // TRANSLATIONS SETUP
        // ============================================
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'oopp') 
            : array();
        
        $this->tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
    }
    
    /**
     * Validace dat
     * 
     * Poznámka: Název se validuje z translations (první jazyk musí mít name)
     */
    public function validate($data, $id = 0) {
        $errors = array();
        $tr = $this->tr;
        
        // Validace name se provádí v controlleru z translations[first_lang][name]
        // Není potřeba kontrolovat data['name'], protože už není v hlavních datech
        
        if (empty($data['group_id'])) {
            $errors['group_id'] = $tr('validation_group_required', 'Skupina OOPP je povinná');
        } else {
            // Ověř že skupina existuje
            global $wpdb;
            $group_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_oopp_groups WHERE id = %d",
                $data['group_id']
            ));
            if (!$group_exists) {
                $errors['group_id'] = $tr('validation_group_invalid', 'Neplatná skupina OOPP');
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    /**
     * Vytvoření OOPP včetně vazeb
     */
    public function create($data) {
        // Extrahuj vazby před uložením
        $branch_ids = $data['branch_ids'] ?? array();
        $department_ids = $data['department_ids'] ?? array();
        unset($data['branch_ids'], $data['department_ids']);
        
        // Nastav customer_id pokud chybí
        if (empty($data['customer_id'])) {
            if (class_exists('SAW_Context')) {
                $data['customer_id'] = SAW_Context::get_customer_id();
            }
        }
        
        // Vytvoř hlavní záznam
        $oopp_id = parent::create($data);
        
        if (is_wp_error($oopp_id)) {
            return $oopp_id;
        }
        
        // Ulož vazby na pobočky
        $this->save_branch_relations($oopp_id, $branch_ids);
        
        // Ulož vazby na oddělení
        $this->save_department_relations($oopp_id, $department_ids);
        
        return $oopp_id;
    }
    
    /**
     * Aktualizace OOPP včetně vazeb
     */
    public function update($id, $data) {
        // Extrahuj vazby před uložením
        $branch_ids = $data['branch_ids'] ?? null;
        $department_ids = $data['department_ids'] ?? null;
        unset($data['branch_ids'], $data['department_ids']);
        
        // Aktualizuj hlavní záznam
        $result = parent::update($id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Aktualizuj vazby pokud byly poslány
        if ($branch_ids !== null) {
            $this->save_branch_relations($id, $branch_ids);
        }
        
        if ($department_ids !== null) {
            $this->save_department_relations($id, $department_ids);
        }
        
        return $result;
    }
    
    /**
     * Smazání OOPP (vazby se smažou automaticky přes FK CASCADE)
     */
    public function delete($id) {
        // Smaž překlady před smazáním OOPP
        $this->delete_translations($id);
        return parent::delete($id);
    }
    
    /**
     * Uložení vazeb na pobočky
     * Prázdné pole = platí pro všechny pobočky
     */
    public function save_branch_relations($oopp_id, $branch_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_oopp_branches';
        
        // Smaž stávající vazby
        $wpdb->delete($table, array('oopp_id' => $oopp_id), array('%d'));
        
        // Vlož nové vazby (pouze pokud jsou nějaké)
        if (!empty($branch_ids) && is_array($branch_ids)) {
            foreach ($branch_ids as $branch_id) {
                $wpdb->insert(
                    $table,
                    array(
                        'oopp_id' => $oopp_id,
                        'branch_id' => intval($branch_id),
                    ),
                    array('%d', '%d')
                );
            }
        }
        
        // Invaliduj cache
        $this->invalidate_cache();
    }
    
    /**
     * Uložení vazeb na oddělení
     * Prázdné pole = platí pro všechna oddělení
     */
    public function save_department_relations($oopp_id, $department_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_oopp_departments';
        
        // Smaž stávající vazby
        $wpdb->delete($table, array('oopp_id' => $oopp_id), array('%d'));
        
        // Vlož nové vazby (pouze pokud jsou nějaké)
        if (!empty($department_ids) && is_array($department_ids)) {
            foreach ($department_ids as $dept_id) {
                $wpdb->insert(
                    $table,
                    array(
                        'oopp_id' => $oopp_id,
                        'department_id' => intval($dept_id),
                    ),
                    array('%d', '%d')
                );
            }
        }
        
        // Invaliduj cache
        $this->invalidate_cache();
    }
    
    /**
     * Získání vazeb na pobočky pro OOPP
     */
    public function get_branch_ids($oopp_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT branch_id FROM {$wpdb->prefix}saw_oopp_branches WHERE oopp_id = %d",
            $oopp_id
        ));
    }
    
    /**
     * Získání vazeb na oddělení pro OOPP
     */
    public function get_department_ids($oopp_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT department_id FROM {$wpdb->prefix}saw_oopp_departments WHERE oopp_id = %d",
            $oopp_id
        ));
    }
    
    /**
     * Získání OOPP pro konkrétní oddělení a pobočku
     * Použito v training flow pro návštěvníky
     */
    public function get_for_department($department_id, $branch_id = null, $customer_id = null) {
        global $wpdb;
        
        if (class_exists('SAW_Context')) {
            $customer_id = $customer_id ?: SAW_Context::get_customer_id();
            $branch_id = $branch_id ?: SAW_Context::get_branch_id();
        }
        
        if (!$customer_id) {
            return array();
        }
        
        // Cache key
        $cache_key = "oopp_dept_{$department_id}_branch_{$branch_id}_customer_{$customer_id}";
        
        if (class_exists('SAW_Cache')) {
            $cached = SAW_Cache::get($cache_key, 'oopp');
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Query: OOPP které jsou buď:
        // 1. Přiřazené k tomuto oddělení, NEBO nemají žádné omezení na oddělení
        // 2. Přiřazené k této pobočce, NEBO nemají žádné omezení na pobočky
        $sql = "
            SELECT o.*, g.code as group_code, g.name as group_name
            FROM {$wpdb->prefix}saw_oopp o
            INNER JOIN {$wpdb->prefix}saw_oopp_groups g ON o.group_id = g.id
            WHERE o.customer_id = %d
            AND o.is_active = 1
            AND (
                -- Oddělení: buď přiřazeno k tomuto, nebo bez omezení
                EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}saw_oopp_departments od 
                    WHERE od.oopp_id = o.id AND od.department_id = %d
                )
                OR NOT EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}saw_oopp_departments od 
                    WHERE od.oopp_id = o.id
                )
            )
            AND (
                -- Pobočka: buď přiřazeno k této, nebo bez omezení
                EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}saw_oopp_branches ob 
                    WHERE ob.oopp_id = o.id AND ob.branch_id = %d
                )
                OR NOT EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}saw_oopp_branches ob 
                    WHERE ob.oopp_id = o.id
                )
            )
            ORDER BY g.display_order, o.id
        ";
        
        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $customer_id,
            $department_id,
            $branch_id
        ), ARRAY_A);
        
        $results = $results ?: array();
        
        // Cache result
        if (class_exists('SAW_Cache')) {
            SAW_Cache::set($cache_key, $results, 600, 'oopp');
        }
        
        return $results;
    }
    
    /**
     * Získání všech OOPP skupin
     */
    public function get_groups() {
        global $wpdb;
        
        $cache_key = 'oopp_groups_all';
        
        if (class_exists('SAW_Cache')) {
            $cached = SAW_Cache::get($cache_key, 'oopp');
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}saw_oopp_groups ORDER BY display_order ASC",
            ARRAY_A
        );
        
        $results = $results ?: array();
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::set($cache_key, $results, 3600, 'oopp');
        }
        
        return $results;
    }
    
    /**
     * Override get_by_id pro načtení vazeb
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if ($item && !is_wp_error($item)) {
            // Přidej vazby
            $item['branch_ids'] = $this->get_branch_ids($id);
            $item['department_ids'] = $this->get_department_ids($id);
            
            // Přidej překlady
            $item['translations'] = $this->get_translations($id);
            
            // Přidej group info
            global $wpdb;
            $group = $wpdb->get_row($wpdb->prepare(
                "SELECT code, name FROM {$wpdb->prefix}saw_oopp_groups WHERE id = %d",
                $item['group_id']
            ), ARRAY_A);
            
            if ($group) {
                $item['group_code'] = $group['code'];
                $item['group_name'] = $group['name'];
            }
        }
        
        return $item;
    }
    
    /**
     * Override get_all pro přidání virtual columns
     */
    public function get_all($filters = []) {
        $result = parent::get_all($filters);
        
        if (empty($result['items'])) {
            return $result;
        }
        
        global $wpdb;
        
        // Přidej virtual columns pro každý item
        foreach ($result['items'] as &$item) {
            // Group info
            if (!empty($item['group_id'])) {
                $group = $wpdb->get_row($wpdb->prepare(
                    "SELECT code, name FROM {$wpdb->prefix}saw_oopp_groups WHERE id = %d",
                    $item['group_id']
                ), ARRAY_A);
                
                if ($group) {
                    $item['group_code'] = $group['code'];
                    $item['group_name'] = $group['name'];
                    $item['group_display'] = $group['code'] . '. ' . $group['name'];
                }
            }
            
            // Branch count
            $branch_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_oopp_branches WHERE oopp_id = %d",
                $item['id']
            ));
            $item['branch_count'] = intval($branch_count);
            
            // Department count
            $dept_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_oopp_departments WHERE oopp_id = %d",
                $item['id']
            ));
            $item['department_count'] = intval($dept_count);
        }
        
        return $result;
    }
    
    /**
     * Získání všech překladů pro OOPP
     * 
     * @param int $oopp_id ID OOPP
     * @return array Asociativní pole ['cs' => [...], 'en' => [...]]
     */
    public function get_translations($oopp_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_oopp_translations';
        
        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE oopp_id = %d ORDER BY language_code ASC",
            $oopp_id
        ), ARRAY_A);
        
        $result = array();
        foreach ($translations as $trans) {
            $lang_code = $trans['language_code'];
            $result[$lang_code] = array(
                'name' => $trans['name'],
                'standards' => $trans['standards'],
                'risk_description' => $trans['risk_description'],
                'protective_properties' => $trans['protective_properties'],
                'usage_instructions' => $trans['usage_instructions'],
            );
        }
        
        return $result;
    }
    
    /**
     * Získání překladu pro konkrétní jazyk
     * 
     * @param int $oopp_id ID OOPP
     * @param string $language_code Jazykový kód (např. 'cs', 'en')
     * @return array|null Překlad nebo null pokud neexistuje
     */
    public function get_translation($oopp_id, $language_code) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_oopp_translations';
        
        $translation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE oopp_id = %d AND language_code = %s",
            $oopp_id,
            $language_code
        ), ARRAY_A);
        
        if (!$translation) {
            return null;
        }
        
        return array(
            'name' => $translation['name'],
            'standards' => $translation['standards'],
            'risk_description' => $translation['risk_description'],
            'protective_properties' => $translation['protective_properties'],
            'usage_instructions' => $translation['usage_instructions'],
        );
    }
    
    /**
     * Uložení/aktualizace překladu (UPSERT)
     * 
     * @param int $oopp_id ID OOPP
     * @param string $language_code Jazykový kód
     * @param array $data Data překladu
     * @return bool|WP_Error True při úspěchu, WP_Error při chybě
     */
    public function save_translation($oopp_id, $language_code, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_oopp_translations';
        
        // Validace
        if (empty($language_code)) {
            return new WP_Error('invalid_language', 'Language code is required');
        }
        
        // Připrav data
        $translation_data = array(
            'oopp_id' => intval($oopp_id),
            'language_code' => sanitize_text_field($language_code),
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'standards' => isset($data['standards']) ? sanitize_textarea_field($data['standards']) : null,
            'risk_description' => isset($data['risk_description']) ? sanitize_textarea_field($data['risk_description']) : null,
            'protective_properties' => isset($data['protective_properties']) ? sanitize_textarea_field($data['protective_properties']) : null,
            'usage_instructions' => isset($data['usage_instructions']) ? sanitize_textarea_field($data['usage_instructions']) : null,
        );
        
        // Zkontroluj zda už překlad existuje
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE oopp_id = %d AND language_code = %s",
            $oopp_id,
            $language_code
        ));
        
        if ($existing) {
            // Aktualizace
            $result = $wpdb->update(
                $table,
                $translation_data,
                array('id' => $existing),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Vložení
            $result = $wpdb->insert(
                $table,
                $translation_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save translation');
        }
        
        // Invaliduj cache
        $this->invalidate_cache();
        
        return true;
    }
    
    /**
     * Uložení všech překladů najednou
     * 
     * @param int $oopp_id ID OOPP
     * @param array $translations Asociativní pole ['cs' => [...], 'en' => [...]]
     * @return bool|WP_Error True při úspěchu
     */
    public function save_all_translations($oopp_id, $translations) {
        if (empty($translations) || !is_array($translations)) {
            return true;
        }
        
        foreach ($translations as $language_code => $data) {
            $result = $this->save_translation($oopp_id, $language_code, $data);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        
        return true;
    }
    
    /**
     * Smazání všech překladů pro OOPP
     * 
     * @param int $oopp_id ID OOPP
     * @return bool True při úspěchu
     */
    public function delete_translations($oopp_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_oopp_translations';
        
        $result = $wpdb->delete(
            $table,
            array('oopp_id' => $oopp_id),
            array('%d')
        );
        
        // Invaliduj cache
        $this->invalidate_cache();
        
        return $result !== false;
    }
    
    /**
     * Získání jazyků zákazníka z training_languages
     * 
     * @param int $customer_id ID zákazníka
     * @return array Pole jazyků ['code' => 'cs', 'name' => 'Čeština', 'flag' => '🇨🇿']
     */
    public function get_customer_languages($customer_id) {
        global $wpdb;
        
        $languages = $wpdb->get_results($wpdb->prepare(
            "SELECT language_code as code, language_name as name, flag_emoji as flag 
             FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d 
             ORDER BY language_name ASC",
            $customer_id
        ), ARRAY_A);
        
        return $languages ?: array();
    }
}