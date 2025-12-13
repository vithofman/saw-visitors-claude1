<?php
/**
 * Translations Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Translations
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Module_Translations_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate translation data
     */
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['translation_key'])) {
            $errors['translation_key'] = 'Klíč překladu je povinný';
        } elseif (strlen($data['translation_key']) > 100) {
            $errors['translation_key'] = 'Klíč překladu může mít maximálně 100 znaků';
        }
        
        if (empty($data['language_code'])) {
            $errors['language_code'] = 'Kód jazyka je povinný';
        } elseif (!preg_match('/^[a-z]{2}$/', $data['language_code'])) {
            $errors['language_code'] = 'Kód jazyka musí být dvoupísmenný (např. cs, en)';
        }
        
        if (empty($data['context'])) {
            $errors['context'] = 'Kontext je povinný';
        }
        
        if (empty($data['translation_text'])) {
            $errors['translation_text'] = 'Text překladu je povinný';
        }
        
        // Check unique combination: translation_key + language_code + context + section
        if (!empty($data['translation_key']) && !empty($data['language_code']) && !empty($data['context'])) {
            $section = !empty($data['section']) ? $data['section'] : null;
            
            if ($this->translation_exists($data['translation_key'], $data['language_code'], $data['context'], $section, $id)) {
                $errors['translation_key'] = 'Překlad s tímto klíčem, jazykem, kontextem a sekcí již existuje';
            }
        }
        
        if (!empty($data['section']) && strlen($data['section']) > 50) {
            $errors['section'] = 'Sekce může mít maximálně 50 znaků';
        }
        
        if (!empty($data['description']) && strlen($data['description']) > 255) {
            $errors['description'] = 'Popis může mít maximálně 255 znaků';
        }
        
        if (!empty($data['placeholders']) && strlen($data['placeholders']) > 255) {
            $errors['placeholders'] = 'Placeholdery mohou mít maximálně 255 znaků';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if translation exists
     */
    private function translation_exists($translation_key, $language_code, $context, $section, $exclude_id = 0) {
        global $wpdb;
        
        $where = array(
            'translation_key = %s',
            'language_code = %s',
            'context = %s',
        );
        $where_values = array($translation_key, $language_code, $context);
        
        if ($section !== null && $section !== '') {
            $where[] = 'section = %s';
            $where_values[] = $section;
        } else {
            $where[] = '(section IS NULL OR section = \'\')';
        }
        
        if ($exclude_id > 0) {
            $where[] = 'id != %d';
            $where_values[] = $exclude_id;
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $where);
        
        return (bool) $wpdb->get_var($wpdb->prepare($sql, ...$where_values));
    }
    
    /**
     * Get all translations with filters
     */
    public function get_all($filters = array()) {
        global $wpdb;
        
        $cache_key = $this->get_cache_key_with_scope('list', $filters);
        $cached = $this->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $page = isset($filters['page']) ? intval($filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 50;
        // ⭐ KRITICKÁ OPRAVA: Podpora vlastního offsetu pro infinite scroll
        if (isset($filters['offset']) && $filters['offset'] >= 0) {
            $offset = intval($filters['offset']);
        } else {
            $offset = ($page - 1) * $per_page;
        }
        
        // Build WHERE conditions
        $where = array('1=1');
        $where_values = array();
        
        // Language code filter
        if (!empty($filters['language_code'])) {
            $where[] = 'language_code = %s';
            $where_values[] = $filters['language_code'];
        }
        
        // Context filter
        if (!empty($filters['context'])) {
            $where[] = 'context = %s';
            $where_values[] = $filters['context'];
        }
        
        // Section filter
        if (!empty($filters['section'])) {
            $where[] = 'section = %s';
            $where_values[] = $filters['section'];
        }
        
        // Search filter - rozšířené vyhledávání v klíči, kontextu, sekci, textu a popisu
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(translation_key LIKE %s OR context LIKE %s OR section LIKE %s OR translation_text LIKE %s OR description LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$where_values));
        
        // Main query
        $orderby = isset($filters['orderby']) ? $filters['orderby'] : 'translation_key';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Ensure orderby is safe
        $allowed_orderby = array('id', 'translation_key', 'language_code', 'context', 'section', 'created_at', 'updated_at');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'translation_key';
        }
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE {$where_sql}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$where_values), ARRAY_A);
        
        $result = array(
            'items' => $items ?: array(),
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $per_page > 0 ? ceil($total / $per_page) : 0,
        );
        
        $this->set_cache($cache_key, $result);
        
        return $result;
    }
    
    /**
     * Get available contexts
     */
    public function get_available_contexts() {
        global $wpdb;
        
        $contexts = $wpdb->get_col(
            "SELECT DISTINCT context FROM {$this->table} WHERE context IS NOT NULL ORDER BY context ASC"
        );
        
        return $contexts ?: array();
    }
    
    /**
     * Get available sections for context
     */
    public function get_available_sections($context = null) {
        global $wpdb;
        
        if ($context) {
            $sections = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT section FROM {$this->table} 
                 WHERE context = %s AND section IS NOT NULL AND section != ''
                 ORDER BY section ASC",
                $context
            ));
        } else {
            $sections = $wpdb->get_col(
                "SELECT DISTINCT section FROM {$this->table} 
                 WHERE section IS NOT NULL AND section != ''
                 ORDER BY section ASC"
            );
        }
        
        return $sections ?: array();
    }
    
    /**
     * Get available language codes
     */
    public function get_available_languages() {
        global $wpdb;
        
        $languages = $wpdb->get_col(
            "SELECT DISTINCT language_code FROM {$this->table} ORDER BY language_code ASC"
        );
        
        return $languages ?: array();
    }
}

