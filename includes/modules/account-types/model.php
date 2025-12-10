<?php
/**
 * Account Types Module Model
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - SAW Table Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Model extends SAW_Base_Model 
{
    /**
     * Constructor
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    /**
     * Validate data
     *
     * @param array $data Data to validate
     * @param int $id Existing ID (for update)
     * @return true|WP_Error
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Systémový název je povinný';
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Zobrazovaný název je povinný';
        }
        
        // Check name format (slug)
        if (!empty($data['name']) && !preg_match('/^[a-z0-9\-_]+$/', $data['name'])) {
            $errors['name'] = 'Systémový název může obsahovat pouze malá písmena, číslice, pomlčky a podtržítka';
        }
        
        // Check unique name
        if (!empty($data['name']) && $this->name_exists($data['name'], $id)) {
            $errors['name'] = 'Typ účtu s tímto názvem již existuje';
        }
        
        if (empty($errors)) {
            return true;
        }
        
        return new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Check if name exists
     *
     * @param string $name Name to check
     * @param int $exclude_id ID to exclude
     * @return bool
     */
    private function name_exists($name, $exclude_id = 0) {
        global $wpdb;
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND id != %d",
            $name,
            $exclude_id
        ));
    }
    
    /**
     * Get by field value
     *
     * @param string $field Field name
     * @param mixed $value Value to search
     * @return array|null
     */
    public function get_by_field($field, $value) {
        global $wpdb;
        
        // Whitelist allowed fields
        $allowed_fields = ['id', 'name', 'display_name', 'is_active'];
        if (!in_array($field, $allowed_fields)) {
            return null;
        }
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$field} = %s LIMIT 1",
            $value
        ), ARRAY_A);
        
        return $result ?: null;
    }
    
    /**
     * Get by ID with formatting
     *
     * @param int $id
     * @param bool $bypass_cache
     * @return array|null
     */
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if (!$item) {
            return null;
        }
        
        return $this->format_item($item);
    }
    
    /**
     * Format item with computed fields
     *
     * @param array $item
     * @return array
     */
    protected function format_item($item) {
        // Features array
        if (!empty($item['features'])) {
            $features = json_decode($item['features'], true);
            $item['features_array'] = is_array($features) ? $features : [];
        } else {
            $item['features_array'] = [];
        }
        
        // Price formatted
        $price = floatval($item['price'] ?? 0);
        $item['price_formatted'] = $price > 0 
            ? number_format($price, 0, ',', ' ') . ' Kč' 
            : 'Zdarma';
        
        // Dates formatted
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Get all with default sorting
     *
     * @param array $filters
     * @return array
     */
    public function get_all($filters = []) {
        if (!isset($filters['orderby'])) {
            $filters['orderby'] = 'sort_order';
            $filters['order'] = 'ASC';
        }
        
        $items = parent::get_all($filters);
        
        // Format each item
        return array_map([$this, 'format_item'], $items);
    }
    
    /**
     * Count records
     *
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $values = [];
        
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = %d';
            $values[] = intval($filters['is_active']);
        }
        
        $where_sql = implode(' AND ', $where);
        
        if (!empty($values)) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}",
                ...$values
            );
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Create with features processing
     *
     * @param array $data
     * @return int|WP_Error
     */
    public function create($data) {
        $data = $this->process_features($data);
        $data = $this->process_booleans($data);
        return parent::create($data);
    }
    
    /**
     * Update with features processing
     *
     * @param int $id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update($id, $data) {
        $data = $this->process_features($data);
        $data = $this->process_booleans($data);
        return parent::update($id, $data);
    }
    
    /**
     * Process features array to JSON
     *
     * @param array $data
     * @return array
     */
    private function process_features($data) {
        if (isset($data['features']) && is_array($data['features'])) {
            $features = array_filter($data['features'], function($f) {
                return !empty(trim($f));
            });
            $data['features'] = !empty($features) 
                ? json_encode(array_values($features), JSON_UNESCAPED_UNICODE) 
                : null;
        }
        return $data;
    }
    
    /**
     * Process boolean fields
     *
     * @param array $data
     * @return array
     */
    private function process_booleans($data) {
        $bool_fields = [
            'is_active',
            'has_api_access',
            'has_custom_branding',
            'has_advanced_reports',
            'has_sso',
            'has_priority_support',
        ];
        
        foreach ($bool_fields as $field) {
            if (isset($data[$field])) {
                $data[$field] = intval($data[$field]) ? 1 : 0;
            }
        }
        
        return $data;
    }
    
    /**
     * Get for select dropdown
     *
     * @return array [id => name]
     */
    public function get_for_select() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT id, display_name FROM {$this->table} 
             WHERE is_active = 1 
             ORDER BY sort_order ASC, display_name ASC",
            ARRAY_A
        );
        
        $options = [];
        foreach ($results as $row) {
            $options[$row['id']] = $row['display_name'];
        }
        
        return $options;
    }
}
