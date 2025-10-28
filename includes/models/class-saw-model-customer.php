<?php
/**
 * SAW Customer Model
 * Customer management - CRUD operations, logo upload, validation
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Model_Customer {
    
    /**
     * Database table
     */
    private $table_name;
    
    /**
     * Upload directory for logos
     */
    private $upload_dir;
    private $upload_url;
    
    /**
     * Allowed MIME types for logos
     */
    private $allowed_mime_types = array(
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    );
    
    /**
     * Maximum file size (5MB)
     */
    private $max_file_size = 5242880;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'saw_customers';
        
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/saw-visitors/saw-customers/';
        $this->upload_url = $upload['baseurl'] . '/saw-visitors/saw-customers/';
        
        $this->ensure_upload_directory();
    }
    
    /**
     * Ensure upload directory exists
     */
    private function ensure_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            $htaccess_file = $this->upload_dir . '.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "# Allow only images\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp|svg)$\">\n    Order allow,deny\n    Allow from all\n</FilesMatch>\n<FilesMatch \"^(?!(.*\\.(jpg|jpeg|png|gif|webp|svg)$)).*$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>");
            }
            
            $index_file = $this->upload_dir . 'index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, "<?php\n// Silence is golden.\n");
            }
        }
    }
    
    /**
     * Get all customers
     * 
     * @param array $args Filters
     * @return array
     */
    public function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'search'  => '',
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => 999,
            'offset'  => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $orderby = in_array($args['orderby'], array('id', 'name', 'ico', 'created_at')) 
            ? $args['orderby'] 
            : 'name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);
        
        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $sql .= $wpdb->prepare(" AND (name LIKE %s OR ico LIKE %s OR address LIKE %s)", $search, $search, $search);
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        error_log('SAW Customer Model SQL: ' . $sql);
        
        $customers = $wpdb->get_results($sql, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('SAW Customer Model ERROR: ' . $wpdb->last_error);
            return array();
        }
        
        error_log('SAW Customer Model: Found ' . count((array) $customers) . ' customers');
        
        if (!is_array($customers)) {
            error_log('SAW Customer Model WARNING: get_results returned non-array: ' . gettype($customers));
            $customers = array();
        }
        
        foreach ($customers as &$customer) {
            $customer['logo_url_full'] = $this->get_logo_url($customer['logo_url']);
        }
        
        return $customers;
    }
    
    /**
     * Get customer count
     * 
     * @param string $search Search term
     * @return int
     */
    public function count($search = '') {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
        
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
            $sql .= $wpdb->prepare(" AND (name LIKE %s OR ico LIKE %s OR address LIKE %s)", $search_term, $search_term, $search_term);
        }
        
        error_log('SAW Customer Model COUNT SQL: ' . $sql);
        
        $count = $wpdb->get_var($sql);
        
        if ($wpdb->last_error) {
            error_log('SAW Customer Model COUNT ERROR: ' . $wpdb->last_error);
            return 0;
        }
        
        error_log('SAW Customer Model COUNT: ' . (int) $count);
        
        return (int) $count;
    }
    
    /**
     * Get customer by ID
     * 
     * @param int $id Customer ID
     * @return array|null
     */
    public function get_by_id($id) {
        global $wpdb;
        
        $customer = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($customer) {
            $customer['logo_url_full'] = $this->get_logo_url($customer['logo_url']);
        }
        
        return $customer;
    }
    
    /**
     * Create new customer
     * 
     * @param array $data Customer data
     * @return int|WP_Error Customer ID or error
     */
    public function create($data) {
        global $wpdb;
        
        $validation = $this->validate($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $insert_data = array(
            'name'          => sanitize_text_field($data['name']),
            'ico'           => !empty($data['ico']) ? sanitize_text_field($data['ico']) : null,
            'address'       => !empty($data['address']) ? sanitize_textarea_field($data['address']) : null,
            'notes'         => !empty($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
            'primary_color' => !empty($data['primary_color']) ? sanitize_hex_color($data['primary_color']) : '#1e40af',
            'logo_url'      => null,
            'created_at'    => current_time('mysql'),
        );
        
        if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
            $logo_result = $this->upload_logo($_FILES['logo']);
            if (is_wp_error($logo_result)) {
                return $logo_result;
            }
            $insert_data['logo_url'] = $logo_result;
        }
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            error_log('SAW Customer Model CREATE ERROR: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Chyba při vytváření zákazníka: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update customer
     * 
     * @param int   $id   Customer ID
     * @param array $data Data to update
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
        
        if (isset($data['ico'])) {
            $update_data['ico'] = !empty($data['ico']) ? sanitize_text_field($data['ico']) : null;
        }
        
        if (isset($data['address'])) {
            $update_data['address'] = !empty($data['address']) ? sanitize_textarea_field($data['address']) : null;
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = !empty($data['notes']) ? sanitize_textarea_field($data['notes']) : null;
        }
        
        if (isset($data['primary_color'])) {
            $update_data['primary_color'] = sanitize_hex_color($data['primary_color']);
        }
        
        if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
            $old_customer = $this->get_by_id($id);
            
            $logo_result = $this->upload_logo($_FILES['logo']);
            if (is_wp_error($logo_result)) {
                return $logo_result;
            }
            
            if (!empty($old_customer['logo_url'])) {
                $this->delete_logo($old_customer['logo_url']);
            }
            
            $update_data['logo_url'] = $logo_result;
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
            error_log('SAW Customer Model UPDATE ERROR: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Chyba při aktualizaci zákazníka: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Delete customer
     * 
     * @param int $id Customer ID
     * @return bool|WP_Error
     */
    public function delete($id) {
        global $wpdb;
        
        $customer = $this->get_by_id($id);
        if (!$customer) {
            return new WP_Error('not_found', 'Zákazník nenalezen.');
        }
        
        if (!empty($customer['logo_url'])) {
            $this->delete_logo($customer['logo_url']);
        }
        
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result === false) {
            error_log('SAW Customer Model DELETE ERROR: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Chyba při mazání zákazníka: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Validate customer data
     * 
     * @param array $data Data to validate
     * @param int   $id   Customer ID (for update)
     * @return bool|WP_Error
     */
    private function validate($data, $id = null) {
        if (empty($data['name'])) {
            return new WP_Error('name_required', 'Název zákazníka je povinný.');
        }
        
        if (!empty($data['ico'])) {
            $ico = sanitize_text_field($data['ico']);
            if (!preg_match('/^\d{8}$/', $ico)) {
                return new WP_Error('ico_invalid', 'IČO musí být 8 číslic.');
            }
        }
        
        if (!empty($data['primary_color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['primary_color'])) {
            return new WP_Error('color_invalid', 'Neplatný formát barvy.');
        }
        
        return true;
    }
    
    /**
     * Upload logo
     * 
     * @param array $file $_FILES['logo']
     * @return string|WP_Error
     */
    private function upload_logo($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Chyba při nahrávání souboru.');
        }
        
        if ($file['size'] > $this->max_file_size) {
            return new WP_Error('file_too_large', 'Soubor je příliš velký. Maximum je 5 MB.');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_mime_types)) {
            return new WP_Error('invalid_file_type', 'Neplatný typ souboru. Povolené jsou pouze obrázky.');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo-' . uniqid() . '.' . $extension;
        $filepath = $this->upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('move_failed', 'Nepodařilo se uložit soubor.');
        }
        
        return $filename;
    }
    
    /**
     * Delete logo
     * 
     * @param string $logo_url Logo filename
     * @return bool
     */
    private function delete_logo($logo_url) {
        if (empty($logo_url)) {
            return false;
        }
        
        $filepath = $this->upload_dir . $logo_url;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Get full logo URL
     * 
     * @param string|null $logo_url Filename
     * @return string|null
     */
    private function get_logo_url($logo_url) {
        if (empty($logo_url)) {
            return null;
        }
        
        return $this->upload_url . $logo_url;
    }
}