<?php
/**
 * SAW Table Translations Helper
 *
 * Provides translation loading and access for SAW Table components.
 * Uses hierarchical loading: common → context → context/section
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Translations Class
 *
 * @since 3.0.0
 */
class SAW_Table_Translations {
    
    /**
     * Loaded translations
     * @var array
     */
    private $translations = [];
    
    /**
     * Current language code
     * @var string
     */
    private $language = 'cs';
    
    /**
     * Context (e.g., 'admin', 'terminal', 'frontend')
     * @var string
     */
    private $context = 'admin';
    
    /**
     * Section/module (e.g., 'visits', 'companies')
     * @var string|null
     */
    private $section = null;
    
    /**
     * Constructor
     *
     * @param string      $context  Context (e.g., 'admin')
     * @param string|null $section  Section/module (e.g., 'visits')
     * @param string|null $language Language code (auto-detect if null)
     */
    public function __construct($context = 'admin', $section = null, $language = null) {
        $this->context = $context;
        $this->section = $section;
        
        // Determine language
        $this->language = $language ?? $this->detectLanguage();
        
        // Load translations
        $this->loadTranslations();
    }
    
    /**
     * Detect user's language preference
     *
     * @return string Language code
     */
    private function detectLanguage() {
        // Try SAW language switcher first
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
            if ($lang) {
                return $lang;
            }
        }
        
        // Try WordPress locale
        $locale = get_locale();
        if ($locale) {
            // Extract language code (e.g., 'cs_CZ' -> 'cs')
            $parts = explode('_', $locale);
            return $parts[0];
        }
        
        // Default to Czech
        return 'cs';
    }
    
    /**
     * Load translations from database
     *
     * Uses hierarchical loading:
     * 1. common (section=NULL) - global UI texts
     * 2. {context} (section=NULL) - context-wide texts
     * 3. {context}/{section} - module-specific texts
     */
    private function loadTranslations() {
        // Use saw_get_translations if available
        if (function_exists('saw_get_translations')) {
            $this->translations = saw_get_translations(
                $this->language,
                $this->context,
                $this->section
            );
            return;
        }
        
        // Fallback: load directly from database
        $this->translations = $this->loadFromDatabase();
    }
    
    /**
     * Load translations directly from database
     *
     * @return array
     */
    private function loadFromDatabase() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'saw_ui_translations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }
        
        $translations = [];
        
        // 1. Load common translations (context=NULL, section=NULL)
        $common = $wpdb->get_results($wpdb->prepare(
            "SELECT translation_key, translation_text 
             FROM {$table} 
             WHERE language_code = %s 
               AND context IS NULL 
               AND section IS NULL",
            $this->language
        ), ARRAY_A);
        
        foreach ($common as $row) {
            $translations[$row['translation_key']] = $row['translation_text'];
        }
        
        // 2. Load context translations (context={context}, section=NULL)
        if ($this->context) {
            $context_trans = $wpdb->get_results($wpdb->prepare(
                "SELECT translation_key, translation_text 
                 FROM {$table} 
                 WHERE language_code = %s 
                   AND context = %s 
                   AND section IS NULL",
                $this->language,
                $this->context
            ), ARRAY_A);
            
            foreach ($context_trans as $row) {
                $translations[$row['translation_key']] = $row['translation_text'];
            }
        }
        
        // 3. Load section translations (context={context}, section={section})
        if ($this->context && $this->section) {
            $section_trans = $wpdb->get_results($wpdb->prepare(
                "SELECT translation_key, translation_text 
                 FROM {$table} 
                 WHERE language_code = %s 
                   AND context = %s 
                   AND section = %s",
                $this->language,
                $this->context,
                $this->section
            ), ARRAY_A);
            
            foreach ($section_trans as $row) {
                $translations[$row['translation_key']] = $row['translation_text'];
            }
        }
        
        return $translations;
    }
    
    /**
     * Get translation
     *
     * @param string      $key      Translation key
     * @param string|null $fallback Fallback value if key not found
     * @return string
     */
    public function get($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }
    
    /**
     * Get translation (alias for get)
     *
     * @param string      $key      Translation key
     * @param string|null $fallback Fallback value
     * @return string
     */
    public function tr($key, $fallback = null) {
        return $this->get($key, $fallback);
    }
    
    /**
     * Check if translation exists
     *
     * @param string $key Translation key
     * @return bool
     */
    public function has($key) {
        return isset($this->translations[$key]);
    }
    
    /**
     * Get all translations
     *
     * @return array
     */
    public function all() {
        return $this->translations;
    }
    
    /**
     * Get current language
     *
     * @return string
     */
    public function getLanguage() {
        return $this->language;
    }
    
    /**
     * Get current context
     *
     * @return string
     */
    public function getContext() {
        return $this->context;
    }
    
    /**
     * Get current section
     *
     * @return string|null
     */
    public function getSection() {
        return $this->section;
    }
    
    /**
     * Create translator callback for renderers
     *
     * Returns a callable that can be passed to renderers.
     *
     * @return callable
     */
    public function createTranslator() {
        $translations = $this->translations;
        
        return function($key, $fallback = null) use ($translations) {
            return $translations[$key] ?? $fallback ?? $key;
        };
    }
    
    /**
     * Czech pluralization helper
     *
     * Handles Czech grammar for counts:
     * - 1: singular (1 záznam)
     * - 2-4: few (2 záznamy)
     * - 5+: many (5 záznamů)
     *
     * @param int    $count        Number to pluralize
     * @param string $singular_key Translation key for singular
     * @param string $few_key      Translation key for 2-4
     * @param string $many_key     Translation key for 5+
     * @return string
     */
    public function pluralize($count, $singular_key, $few_key, $many_key) {
        $count = intval($count);
        
        if ($count === 1) {
            return $this->get($singular_key, $singular_key);
        } elseif ($count >= 2 && $count <= 4) {
            return $this->get($few_key, $few_key);
        } else {
            return $this->get($many_key, $many_key);
        }
    }
    
    /**
     * Format record count with Czech grammar
     *
     * @param int $count Number of records
     * @return string Formatted string (e.g., "5 záznamů")
     */
    public function formatRecordCount($count) {
        $word = $this->pluralize(
            $count,
            'record_singular',   // 1 záznam
            'record_few',        // 2-4 záznamy
            'record_many'        // 5+ záznamů
        );
        
        // Fallback if keys not found
        if ($word === 'record_singular') {
            $word = 'záznam';
        } elseif ($word === 'record_few') {
            $word = 'záznamy';
        } elseif ($word === 'record_many') {
            $word = 'záznamů';
        }
        
        return "{$count} {$word}";
    }
    
    /**
     * Format person count with Czech grammar
     *
     * @param int $count Number of persons
     * @return string Formatted string (e.g., "5 osob")
     */
    public function formatPersonCount($count) {
        $word = $this->pluralize(
            $count,
            'person_singular',   // 1 osoba
            'person_few',        // 2-4 osoby
            'person_many'        // 5+ osob
        );
        
        // Fallback if keys not found
        if ($word === 'person_singular') {
            $word = 'osoba';
        } elseif ($word === 'person_few') {
            $word = 'osoby';
        } elseif ($word === 'person_many') {
            $word = 'osob';
        }
        
        return "{$count} {$word}";
    }
    
    /**
     * Get formatted date label
     *
     * @param string $key Translation key (e.g., 'field_created_at')
     * @return string
     */
    public function getDateLabel($key) {
        $defaults = [
            'field_created_at' => 'Vytvořeno',
            'field_updated_at' => 'Změněno',
            'field_date_from' => 'Od',
            'field_date_to' => 'Do',
        ];
        
        return $this->get($key, $defaults[$key] ?? $key);
    }
    
    /**
     * Get formatted action label
     *
     * @param string $action Action name (edit, delete, create, etc.)
     * @return string
     */
    public function getActionLabel($action) {
        $defaults = [
            'edit' => 'Upravit',
            'delete' => 'Smazat',
            'create' => 'Vytvořit',
            'save' => 'Uložit',
            'cancel' => 'Zrušit',
            'close' => 'Zavřít',
            'view' => 'Zobrazit',
            'back' => 'Zpět',
        ];
        
        $key = 'btn_' . $action;
        return $this->get($key, $defaults[$action] ?? ucfirst($action));
    }
    
    /**
     * Reload translations
     *
     * Useful when language changes during runtime.
     *
     * @param string|null $language New language code (null to re-detect)
     */
    public function reload($language = null) {
        if ($language !== null) {
            $this->language = $language;
        } else {
            $this->language = $this->detectLanguage();
        }
        
        $this->loadTranslations();
    }
    
    /**
     * Merge additional translations
     *
     * @param array $additional Additional translations to merge
     */
    public function merge($additional) {
        $this->translations = array_merge($this->translations, $additional);
    }
}
