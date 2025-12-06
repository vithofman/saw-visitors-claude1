<?php
/**
 * SAW UI Translations Service
 * 
 * Centrální služba pro správu překladů UI textů.
 * Implementuje hierarchické načítání s fallback logikou.
 * 
 * Použití:
 * - saw_t('title', 'cs', 'terminal', 'video') - získá překlad
 * - saw_get_translations('cs', 'terminal', 'video') - získá všechny pro stránku
 * 
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      5.1.0
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Translations Class
 * 
 * Singleton služba pro správu UI překladů.
 * 
 * @since 5.1.0
 */
class SAW_Translations {
    
    /**
     * Singleton instance
     * 
     * @var SAW_Translations|null
     */
    private static $instance = null;
    
    /**
     * In-memory cache
     * 
     * @var array
     */
    private $cache = [];
    
    /**
     * Default language code
     * 
     * @var string
     */
    private $default_language = 'cs';
    
    /**
     * Fallback language code
     * 
     * @var string
     */
    private $fallback_language = 'en';
    
    /**
     * Available languages (cached)
     * 
     * @var array|null
     */
    private $available_languages = null;
    
    /**
     * Cache TTL in seconds
     * 
     * @var int
     */
    private $cache_ttl = 3600;
    
    /**
     * Database table names
     * 
     * @var string
     */
    private $table_translations;
    private $table_languages;
    
    /**
     * Whether tables exist
     * 
     * @var bool|null
     */
    private $tables_exist = null;
    
    // =========================================================================
    // SINGLETON & INITIALIZATION
    // =========================================================================
    
    /**
     * Get singleton instance
     * 
     * @return SAW_Translations
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        global $wpdb;
        
        $this->table_translations = $wpdb->prefix . 'saw_ui_translations';
        $this->table_languages = $wpdb->prefix . 'saw_ui_languages';
        
        $this->load_settings();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Check if tables exist
     * 
     * @return bool
     */
    private function tables_exist() {
        if ($this->tables_exist !== null) {
            return $this->tables_exist;
        }
        
        global $wpdb;
        
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $this->table_languages)
        );
        
        $this->tables_exist = ($table_exists !== null);
        
        return $this->tables_exist;
    }
    
    /**
     * Load settings from database
     */
    private function load_settings() {
        if (!$this->tables_exist()) {
            return;
        }
        
        global $wpdb;
        
        // Load default language
        $default = $wpdb->get_var(
            "SELECT language_code FROM {$this->table_languages} 
             WHERE is_default = 1 AND is_active = 1 LIMIT 1"
        );
        if ($default) {
            $this->default_language = $default;
        }
        
        // Load fallback language
        $fallback = $wpdb->get_var(
            "SELECT language_code FROM {$this->table_languages} 
             WHERE is_fallback = 1 AND is_active = 1 LIMIT 1"
        );
        if ($fallback) {
            $this->fallback_language = $fallback;
        }
    }
    
    // =========================================================================
    // MAIN PUBLIC API
    // =========================================================================
    
    /**
     * Get single translation
     * 
     * Fallback chain: requested_lang → fallback_lang → default_lang → key
     * 
     * @param string      $key          Translation key (e.g., 'title', 'confirm')
     * @param string      $lang         Language code (e.g., 'cs', 'en')
     * @param string      $context      Context (terminal, invitation, admin, common)
     * @param string|null $section      Section within context (video, risks, etc.)
     * @param array       $replacements Placeholder replacements ['name' => 'John']
     * @return string Translated text or key if not found
     */
    public function get($key, $lang, $context, $section = null, $replacements = []) {
        // Check if tables exist
        if (!$this->tables_exist()) {
            return $key;
        }
        
        // Normalize language
        $lang = $this->normalize_language($lang);
        
        // Try to find in requested language (section specific first, then general)
        $text = $this->find_translation($key, $lang, $context, $section);
        
        // If section specific not found, try context general
        if ($text === null && $section !== null) {
            $text = $this->find_translation($key, $lang, $context, null);
        }
        
        // Try common
        if ($text === null && $context !== 'common') {
            $text = $this->find_translation($key, $lang, 'common', null);
        }
        
        // Fallback to fallback language
        if ($text === null && $lang !== $this->fallback_language) {
            $text = $this->find_translation($key, $this->fallback_language, $context, $section);
            
            if ($text === null && $section !== null) {
                $text = $this->find_translation($key, $this->fallback_language, $context, null);
            }
            
            if ($text === null && $context !== 'common') {
                $text = $this->find_translation($key, $this->fallback_language, 'common', null);
            }
        }
        
        // Fallback to default language
        if ($text === null && $lang !== $this->default_language && $this->fallback_language !== $this->default_language) {
            $text = $this->find_translation($key, $this->default_language, $context, $section);
            
            if ($text === null && $section !== null) {
                $text = $this->find_translation($key, $this->default_language, $context, null);
            }
            
            if ($text === null && $context !== 'common') {
                $text = $this->find_translation($key, $this->default_language, 'common', null);
            }
        }
        
        // Return key if nothing found
        if ($text === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SAW_Translations] Missing: {$context}/{$section}/{$key} [{$lang}]");
            }
            return $key;
        }
        
        // Apply replacements
        if (!empty($replacements)) {
            foreach ($replacements as $placeholder => $value) {
                $text = str_replace('{' . $placeholder . '}', $value, $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Get all translations for a page/section
     * 
     * Hierarchically merges:
     * 1. common (base)
     * 2. context general (context, section = NULL)
     * 3. context specific (context, section)
     * 
     * Later values override earlier (specific wins over general).
     * 
     * @param string      $lang    Language code
     * @param string      $context Context
     * @param string|null $section Section
     * @return array Key => text pairs
     */
    public function get_for_page($lang, $context, $section = null) {
        if (!$this->tables_exist()) {
            return [];
        }
        
        $lang = $this->normalize_language($lang);
        $cache_key = "page_{$lang}_{$context}_{$section}";
        
        // Check memory cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Check transient cache
        $transient_key = "saw_t_{$cache_key}";
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            $this->cache[$cache_key] = $cached;
            return $cached;
        }
        
        // Start with fallback language translations (as base)
        $translations = [];
        
        if ($lang !== $this->fallback_language) {
            // Load fallback first
            $fallback_translations = $this->build_page_translations($this->fallback_language, $context, $section);
            $translations = $fallback_translations;
        }
        
        // Load requested language (overrides fallback)
        $lang_translations = $this->build_page_translations($lang, $context, $section);
        $translations = array_merge($translations, $lang_translations);
        
        // Cache
        $this->cache[$cache_key] = $translations;
        set_transient($transient_key, $translations, $this->cache_ttl);
        
        return $translations;
    }
    
    /**
     * Build translations array for specific language
     * 
     * @param string      $lang
     * @param string      $context
     * @param string|null $section
     * @return array
     */
    private function build_page_translations($lang, $context, $section) {
        $translations = [];
        
        // 1. Load common translations (base)
        $common = $this->load_from_db($lang, 'common', null);
        $translations = array_merge($translations, $common);
        
        // 2. Load context general (section = NULL)
        if ($context !== 'common') {
            $context_general = $this->load_from_db($lang, $context, null);
            $translations = array_merge($translations, $context_general);
        }
        
        // 3. Load context specific (with section)
        if ($section !== null) {
            $context_specific = $this->load_from_db($lang, $context, $section);
            $translations = array_merge($translations, $context_specific);
        }
        
        return $translations;
    }
    
    /**
     * Get translations for JavaScript
     * 
     * Returns object structure for JS consumption.
     * 
     * @param string      $lang
     * @param string      $context
     * @param string|null $section
     * @return array
     */
    public function get_for_js($lang, $context, $section = null) {
        $flat = $this->get_for_page($lang, $context, $section);
        
        return [
            'lang' => $lang,
            'context' => $context,
            'section' => $section,
            'strings' => $flat,
        ];
    }
    
    // =========================================================================
    // LANGUAGE MANAGEMENT
    // =========================================================================
    
    /**
     * Get available UI languages
     * 
     * @param bool $active_only Only return active languages
     * @return array
     */
    public function get_available_languages($active_only = true) {
        if (!$this->tables_exist()) {
            return [];
        }
        
        if ($this->available_languages !== null && $active_only) {
            return $this->available_languages;
        }
        
        global $wpdb;
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        $languages = $wpdb->get_results(
            "SELECT language_code, language_name, native_name, flag_emoji, 
                    is_default, is_fallback, sort_order
             FROM {$this->table_languages} 
             {$where}
             ORDER BY sort_order ASC, language_name ASC",
            ARRAY_A
        );
        
        if ($active_only) {
            $this->available_languages = $languages ?: [];
        }
        
        return $languages ?: [];
    }
    
    /**
     * Check if language is available
     * 
     * @param string $lang Language code
     * @return bool
     */
    public function is_language_available($lang) {
        $languages = $this->get_available_languages();
        foreach ($languages as $language) {
            if ($language['language_code'] === $lang) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Normalize language code
     * 
     * MVP: cs = čeština, cokoliv jiného = en (pokud není v DB)
     * 
     * @param string $lang
     * @return string
     */
    public function normalize_language($lang) {
        if (empty($lang)) {
            return $this->default_language;
        }
        
        $lang = strtolower(trim($lang));
        
        // Normalize common variants
        if ($lang === 'cz') {
            $lang = 'cs';
        }
        
        // Check if language is available in DB
        if ($this->is_language_available($lang)) {
            return $lang;
        }
        
        // Fallback
        return $this->fallback_language;
    }
    
    /**
     * Get default language
     * 
     * @return string
     */
    public function get_default_language() {
        return $this->default_language;
    }
    
    /**
     * Get fallback language
     * 
     * @return string
     */
    public function get_fallback_language() {
        return $this->fallback_language;
    }
    
    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================
    
    /**
     * Clear all translation caches
     */
    public function clear_cache() {
        global $wpdb;
        
        // Clear memory cache
        $this->cache = [];
        $this->available_languages = null;
        
        // Clear all translation transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_saw_t_%' 
             OR option_name LIKE '_transient_timeout_saw_t_%'"
        );
        
        // Clear object cache group if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('saw_translations');
        }
    }
    
    /**
     * Clear cache for specific language
     * 
     * @param string $lang
     */
    public function clear_cache_for_language($lang) {
        global $wpdb;
        
        // Clear from memory
        foreach (array_keys($this->cache) as $key) {
            if (strpos($key, "_{$lang}_") !== false || strpos($key, "page_{$lang}") !== false) {
                unset($this->cache[$key]);
            }
        }
        
        // Clear transients for this language
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '%saw_t_%' . $lang . '%'
        ));
    }
    
    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================
    
    /**
     * Find single translation in database
     * 
     * @param string      $key
     * @param string      $lang
     * @param string      $context
     * @param string|null $section
     * @return string|null
     */
    private function find_translation($key, $lang, $context, $section = null) {
        global $wpdb;
        
        // Build cache key
        $cache_key = "single_{$key}_{$lang}_{$context}_{$section}";
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Query database
        if ($section !== null) {
            $text = $wpdb->get_var($wpdb->prepare(
                "SELECT translation_text 
                 FROM {$this->table_translations} 
                 WHERE translation_key = %s 
                 AND language_code = %s 
                 AND context = %s 
                 AND section = %s",
                $key, $lang, $context, $section
            ));
        } else {
            $text = $wpdb->get_var($wpdb->prepare(
                "SELECT translation_text 
                 FROM {$this->table_translations} 
                 WHERE translation_key = %s 
                 AND language_code = %s 
                 AND context = %s 
                 AND section IS NULL",
                $key, $lang, $context
            ));
        }
        
        $this->cache[$cache_key] = $text;
        
        return $text;
    }
    
    /**
     * Load translations from database for context/section
     * 
     * @param string      $lang
     * @param string      $context
     * @param string|null $section
     * @return array
     */
    private function load_from_db($lang, $context, $section = null) {
        global $wpdb;
        
        if ($section !== null) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT translation_key, translation_text 
                 FROM {$this->table_translations} 
                 WHERE language_code = %s 
                 AND context = %s 
                 AND section = %s",
                $lang, $context, $section
            ), ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT translation_key, translation_text 
                 FROM {$this->table_translations} 
                 WHERE language_code = %s 
                 AND context = %s 
                 AND section IS NULL",
                $lang, $context
            ), ARRAY_A);
        }
        
        $translations = [];
        foreach ($results ?: [] as $row) {
            $translations[$row['translation_key']] = $row['translation_text'];
        }
        
        return $translations;
    }
    
    // =========================================================================
    // ADMIN METHODS (pro budoucí admin modul)
    // =========================================================================
    
    /**
     * Save translation
     * 
     * @param string      $key
     * @param string      $lang
     * @param string      $context
     * @param string|null $section
     * @param string      $text
     * @param string|null $description
     * @return bool|int
     */
    public function save($key, $lang, $context, $section, $text, $description = null) {
        global $wpdb;
        
        $data = [
            'translation_key' => $key,
            'language_code' => $lang,
            'context' => $context,
            'section' => $section,
            'translation_text' => $text,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        ];
        
        // Try to find existing
        if ($section !== null) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_translations} 
                 WHERE translation_key = %s 
                 AND language_code = %s 
                 AND context = %s 
                 AND section = %s",
                $key, $lang, $context, $section
            ));
        } else {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_translations} 
                 WHERE translation_key = %s 
                 AND language_code = %s 
                 AND context = %s 
                 AND section IS NULL",
                $key, $lang, $context
            ));
        }
        
        if ($existing_id) {
            $result = $wpdb->update(
                $this->table_translations,
                $data,
                ['id' => $existing_id]
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($this->table_translations, $data);
        }
        
        // Clear cache
        $this->clear_cache_for_language($lang);
        
        return $result;
    }
    
    /**
     * Delete translation
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        global $wpdb;
        
        // Get language before delete for cache clearing
        $lang = $wpdb->get_var($wpdb->prepare(
            "SELECT language_code FROM {$this->table_translations} WHERE id = %d",
            $id
        ));
        
        $result = $wpdb->delete($this->table_translations, ['id' => $id]);
        
        if ($lang) {
            $this->clear_cache_for_language($lang);
        }
        
        return $result !== false;
    }
    
    /**
     * Import translations from array
     * 
     * @param array $translations Array of ['key', 'lang', 'context', 'section', 'text', 'description']
     * @return int Number of imported
     */
    public function import($translations) {
        $count = 0;
        
        foreach ($translations as $t) {
            $result = $this->save(
                $t['key'],
                $t['lang'],
                $t['context'],
                $t['section'] ?? null,
                $t['text'],
                $t['description'] ?? null
            );
            
            if ($result !== false) {
                $count++;
            }
        }
        
        $this->clear_cache();
        
        return $count;
    }
    
    /**
     * Export translations to array
     * 
     * @param string|null $context
     * @param string|null $lang
     * @return array
     */
    public function export($context = null, $lang = null) {
        global $wpdb;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($context !== null) {
            $where .= " AND context = %s";
            $params[] = $context;
        }
        
        if ($lang !== null) {
            $where .= " AND language_code = %s";
            $params[] = $lang;
        }
        
        $sql = "SELECT translation_key as `key`, language_code as lang, 
                       context, section, translation_text as text, description
                FROM {$this->table_translations} {$where}
                ORDER BY context, section, translation_key, language_code";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get all translations for admin listing
     * 
     * @param array $filters
     * @return array
     */
    public function get_all_for_admin($filters = []) {
        global $wpdb;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['context'])) {
            $where .= " AND context = %s";
            $params[] = $filters['context'];
        }
        
        if (!empty($filters['section'])) {
            $where .= " AND section = %s";
            $params[] = $filters['section'];
        }
        
        if (!empty($filters['language'])) {
            $where .= " AND language_code = %s";
            $params[] = $filters['language'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (translation_key LIKE %s OR translation_text LIKE %s)";
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql = "SELECT * FROM {$this->table_translations} {$where} 
                ORDER BY context, section, translation_key, language_code";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    /**
     * Get sections for context
     * 
     * @param string $context
     * @return array
     */
    public function get_sections($context) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT section 
             FROM {$this->table_translations} 
             WHERE context = %s AND section IS NOT NULL 
             ORDER BY section",
            $context
        )) ?: [];
    }
}