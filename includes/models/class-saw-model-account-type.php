<?php
/**
 * SAW Account Type Model
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Model_Account_Type {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'saw_account_types';
    }
    
    /**
     * Získat všechny typy účtů
     * 
     * @param bool $active_only Pouze aktivní typy
     * @return array
     */
    public function get_all($active_only = true) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table_name}";
        
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        
        $sql .= " ORDER BY sort_order ASC";
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Získat typ účtu podle ID
     * 
     * @param int $id ID typu účtu
     * @return array|null
     */
    public function get_by_id($id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );
    }
    
    /**
     * Získat typ účtu podle interního názvu
     * 
     * @param string $name Interní název (basic, bronze, silver, gold, vip)
     * @return array|null
     */
    public function get_by_name($name) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE name = %s", $name),
            ARRAY_A
        );
    }
    
    /**
     * Získat typy účtů ve formátu pro <select>
     * 
     * @return array [id => display_name]
     */
    public function get_for_select() {
        $types = $this->get_all(true);
        $options = array();
        
        foreach ($types as $type) {
            $options[$type['id']] = $type['display_name'];
        }
        
        return $options;
    }
    
    /**
     * Získat aktivní typy s barvami pro badges
     * 
     * @return array
     */
    public function get_active_types_with_colors() {
        $types = $this->get_all(true);
        $result = array();
        
        foreach ($types as $type) {
            $result[] = array(
                'id'           => $type['id'],
                'name'         => $type['name'],
                'display_name' => $type['display_name'],
                'color'        => $type['color'],
                'price'        => $type['price'],
            );
        }
        
        return $result;
    }
    
    /**
     * Vytvořit nový typ účtu
     * 
     * @param array $data Data typu účtu
     * @return int|WP_Error ID nového záznamu nebo chyba
     */
    public function create($data) {
        global $wpdb;
        
        $validation = $this->validate($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $insert_data = array(
            'name'         => sanitize_text_field($data['name']),
            'display_name' => sanitize_text_field($data['display_name']),
            'color'        => sanitize_hex_color($data['color']),
            'price'        => floatval($data['price']),
            'features'     => isset($data['features']) ? wp_json_encode($data['features']) : null,
            'sort_order'   => isset($data['sort_order']) ? intval($data['sort_order']) : 0,
            'is_active'    => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Chyba při vytváření typu účtu: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Aktualizovat typ účtu
     * 
     * @param int   $id   ID typu účtu
     * @param array $data Data k aktualizaci
     * @return bool|WP_Error
     */
    public function update($id, $data) {
        global $wpdb;
        
        $validation = $this->validate($data, $id);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['display_name'])) {
            $update_data['display_name'] = sanitize_text_field($data['display_name']);
        }
        
        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']);
        }
        
        if (isset($data['price'])) {
            $update_data['price'] = floatval($data['price']);
        }
        
        if (isset($data['features'])) {
            $update_data['features'] = is_array($data['features']) 
                ? wp_json_encode($data['features']) 
                : $data['features'];
        }
        
        if (isset($data['sort_order'])) {
            $update_data['sort_order'] = intval($data['sort_order']);
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'Žádná data k aktualizaci.');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Chyba při aktualizaci typu účtu: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Smazat typ účtu
     * 
     * @param int $id ID typu účtu
     * @return bool|WP_Error
     */
    public function delete($id) {
        global $wpdb;
        
        $type = $this->get_by_id($id);
        if (!$type) {
            return new WP_Error('not_found', 'Typ účtu nenalezen.');
        }
        
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result === false) {
            return new WP_Error('db_error', 'Chyba při mazání typu účtu: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Validace dat
     * 
     * @param array    $data Data k validaci
     * @param int|null $id   ID pro update (kontrola duplicity)
     * @return bool|WP_Error
     */
    private function validate($data, $id = null) {
        if (empty($data['name'])) {
            return new WP_Error('name_required', 'Název typu účtu je povinný.');
        }
        
        if (empty($data['display_name'])) {
            return new WP_Error('display_name_required', 'Zobrazovaný název je povinný.');
        }
        
        if (!empty($data['color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            return new WP_Error('color_invalid', 'Neplatný formát barvy.');
        }
        
        global $wpdb;
        $name = sanitize_text_field($data['name']);
        
        $query = $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE name = %s",
            $name
        );
        
        if ($id) {
            $query .= $wpdb->prepare(" AND id != %d", $id);
        }
        
        $existing = $wpdb->get_var($query);
        
        if ($existing) {
            return new WP_Error('name_exists', 'Typ účtu s tímto názvem již existuje.');
        }
        
        return true;
    }
}