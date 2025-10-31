<?php
/**
 * Account Types Module Model
 * 
 * Model pro správu typů účtů. Dědí z Base Model a přidává:
 * - Validaci unique name (slug)
 * - Převod features (textarea → JSON → array)
 * - Check zda typ používají zákazníci (před deletem)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since   4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     * 
     * Inicializuje model s konfigurací z config.php.
     * 
     * @param array $config Konfigurace modulu
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 3600;
    }
    
    /**
     * Validace dat
     * 
     * Override z Base Model - přidává custom validaci:
     * - Povinné pole 'name' a 'display_name'
     * - Unique check pro 'name' (slug)
     * - Validace price (musí být >= 0)
     * - Validace color (hex formát)
     * 
     * @param array $data Data k validaci
     * @param int   $id   ID editovaného záznamu (0 = nový)
     * @return true|WP_Error True pokud OK, WP_Error pokud chyba
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        // === POVINNÁ POLE ===
        
        // Name (slug) je povinný
        if (empty($data['name'])) {
            $errors['name'] = 'Interní název je povinný';
        } else {
            // Check unique name (slug musí být unikátní)
            if ($this->name_exists($data['name'], $id)) {
                $errors['name'] = 'Typ účtu s tímto názvem již existuje';
            }
            
            // Name musí být validní slug (jen lowercase, číslice, pomlčky)
            if (!preg_match('/^[a-z0-9\-]+$/', $data['name'])) {
                $errors['name'] = 'Interní název může obsahovat jen malá písmena, číslice a pomlčky';
            }
        }
        
        // Display name je povinný
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Zobrazovaný název je povinný';
        }
        
        // === NEPOVINNÁ POLE S VALIDACÍ ===
        
        // Price musí být >= 0
        if (isset($data['price'])) {
            $price = floatval($data['price']);
            if ($price < 0) {
                $errors['price'] = 'Cena nemůže být záporná';
            }
        }
        
        // Color musí být hex (#RRGGBB)
        if (!empty($data['color'])) {
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $data['color'])) {
                $errors['color'] = 'Neplatný formát barvy (musí být #RRGGBB)';
            }
        }
        
        // Sort order musí být integer >= 0
        if (isset($data['sort_order'])) {
            $sort = intval($data['sort_order']);
            if ($sort < 0) {
                $errors['sort_order'] = 'Pořadí nemůže být záporné';
            }
        }
        
        // Pokud jsou chyby, vrať WP_Error
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if name (slug) exists
     * 
     * Kontroluje zda už existuje account type se stejným názvem (slug).
     * Při editaci vylučuje sám sebe.
     * 
     * @param string $name       Slug k ověření
     * @param int    $exclude_id ID které má být vyloučeno z checku
     * @return bool True pokud existuje, false pokud ne
     */
    private function name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND id != %d",
            $name,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Override: Create
     * 
     * Před uložením zpracuj features (textarea → JSON).
     * Volá parent create pro samotné uložení.
     * 
     * @param array $data Data k uložení
     * @return int|WP_Error ID nového záznamu nebo WP_Error
     */
    public function create($data) {
        // Zpracuj features před uložením
        $data = $this->process_features_for_save($data);
        
        return parent::create($data);
    }
    
    /**
     * Override: Update
     * 
     * Před uložením zpracuj features (textarea → JSON).
     * Volá parent update pro samotné uložení.
     * 
     * @param int   $id   ID záznamu
     * @param array $data Data k aktualizaci
     * @return true|WP_Error True pokud OK, WP_Error pokud chyba
     */
    public function update($id, $data) {
        // Zpracuj features před uložením
        $data = $this->process_features_for_save($data);
        
        return parent::update($id, $data);
    }
    
    /**
     * Process features pro uložení
     * 
     * Převede textarea (každý řádek = 1 feature) na JSON string.
     * Example input:  "Feature 1\nFeature 2\nFeature 3"
     * Example output: '["Feature 1","Feature 2","Feature 3"]'
     * 
     * @param array $data Data obsahující features
     * @return array Upravená data s features jako JSON
     */
    private function process_features_for_save($data) {
        if (isset($data['features']) && is_string($data['features'])) {
            // Rozděl podle řádků
            $lines = explode("\n", $data['features']);
            
            // Odstranění prázdných řádků a trim
            $features = array_filter(array_map('trim', $lines));
            
            // Převeď na JSON
            $data['features'] = !empty($features) ? json_encode(array_values($features)) : null;
        }
        
        return $data;
    }
    
    /**
     * Get features as array
     * 
     * Převede JSON string z DB na array pro zobrazení.
     * 
     * @param string|null $features_json JSON string z DB
     * @return array Array features nebo prázdné pole
     */
    public function get_features_as_array($features_json) {
        if (empty($features_json)) {
            return [];
        }
        
        $features = json_decode($features_json, true);
        
        return is_array($features) ? $features : [];
    }
    
    /**
     * Check if account type is used by customers
     * 
     * Před smazáním zkontroluj, zda tento typ nepoužívá nějaký zákazník.
     * Pokud ano, nelze ho smazat.
     * 
     * @param int $id ID account type
     * @return bool True pokud používán, false pokud ne
     */
    public function is_used_by_customers($id) {
        global $wpdb;
        
        $customers_table = $wpdb->prefix . 'saw_customers';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$customers_table}'") !== $customers_table) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$customers_table} WHERE account_type_id = %d",
            $id
        );
        
        $count = $wpdb->get_var($query);
        
        return $count > 0;
    }
    
    /**
     * Override: Get all
     * 
     * Standardní get_all z Base Model, ale defaultně řadí podle sort_order.
     * 
     * @param array $filters Filtry pro dotaz
     * @return array Pole items nebo array['items' => [], 'total' => int]
     */
    public function get_all($filters = []) {
        // Defaultně řaď podle sort_order ASC
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        return parent::get_all($filters);
    }
}
