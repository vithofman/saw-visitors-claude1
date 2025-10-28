<?php
/**
 * SAW Customer Model - DEBUG VERSION
 * 
 * @package SAW_Visitors
 * @version 4.6.1 DEBUG
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Model_Customer {
    
    private $table_name;
    private $upload_dir;
    private $upload_url;
    
    private $allowed_mime_types = array(
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    );
    
    private $max_file_size = 5242880;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'saw_customers';
        
        error_log('🔧 SAW Model Customer: Constructor called');
        error_log('   Table name: ' . $this->table_name);
        
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/saw-visitors/saw-customers/';
        $this->upload_url = $upload['baseurl'] . '/saw-visitors/saw-customers/';
        
        error_log('   Upload dir: ' . $this->upload_dir);
        error_log('   Upload URL: ' . $this->upload_url);
        
        $this->ensure_upload_directory();
    }
    
    private function ensure_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            error_log('✅ Upload directory created: ' . $this->upload_dir);
            
            $htaccess_file = $this->upload_dir . '.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "# Allow only images\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp|svg)$\">\n    Order allow,deny\n    Allow from all\n</FilesMatch>\n<FilesMatch \"^(?!(.*\\.(jpg|jpeg|png|gif|webp|svg)$)).*$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>");
                error_log('✅ .htaccess created');
            }
            
            $index_file = $this->upload_dir . 'index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, "<?php\n// Silence is golden.\n");
                error_log('✅ index.php created');
            }
        }
    }
    
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
        
        $customers = $wpdb->get_results($sql, ARRAY_A);
        
        if (!empty($customers)) {
            foreach ($customers as &$customer) {
                $customer['logo_url_full'] = $this->get_logo_url($customer['logo_url']);
            }
        }
        
        return $customers;
    }
    
    public function count($search = '') {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
        
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
            $sql .= $wpdb->prepare(" AND (name LIKE %s OR ico LIKE %s OR address LIKE %s)", $search_term, $search_term, $search_term);
        }
        
        $count = $wpdb->get_var($sql);
        
        return (int) $count;
    }
    
    public function get_by_id($id) {
        global $wpdb;
        
        error_log('🔍 SAW Model Customer: get_by_id() called for ID: ' . $id);
        
        $customer = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($customer) {
            error_log('✅ Customer found: ' . $customer['name']);
            $customer['logo_url_full'] = $this->get_logo_url($customer['logo_url']);
        } else {
            error_log('❌ Customer NOT found for ID: ' . $id);
        }
        
        return $customer;
    }
    
    public function create($data) {
        global $wpdb;
        
        error_log('📝 SAW Model Customer: create() called');
        error_log('   Input data: ' . print_r($data, true));
        
        $validation = $this->validate($data);
        if (is_wp_error($validation)) {
            error_log('❌ Validation FAILED: ' . $validation->get_error_message());
            return $validation;
        }
        
        error_log('✅ Validation passed');
        
        $insert_data = array(
            'name'          => sanitize_text_field($data['name']),
            'ico'           => !empty($data['ico']) ? sanitize_text_field($data['ico']) : null,
            'address'       => !empty($data['address']) ? sanitize_textarea_field($data['address']) : null,
            'notes'         => !empty($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
            'primary_color' => !empty($data['primary_color']) ? sanitize_hex_color($data['primary_color']) : '#1e40af',
            'logo_url'      => null,
            'created_at'    => current_time('mysql'),
        );
        
        error_log('📦 Insert data prepared:');
        error_log('   ' . print_r($insert_data, true));
        
        if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
            error_log('📸 Logo file detected, attempting upload...');
            $logo_result = $this->upload_logo($_FILES['logo']);
            if (is_wp_error($logo_result)) {
                error_log('❌ Logo upload FAILED: ' . $logo_result->get_error_message());
                return $logo_result;
            }
            $insert_data['logo_url'] = $logo_result;
            error_log('✅ Logo uploaded: ' . $logo_result);
        }
        
        error_log('🚀 Attempting INSERT into table: ' . $this->table_name);
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            error_log('❌ INSERT FAILED!');
            error_log('   Last error: ' . $wpdb->last_error);
            error_log('   Last query: ' . $wpdb->last_query);
            return new WP_Error('db_error', 'Chyba při vytváření zákazníka: ' . $wpdb->last_error);
        }
        
        $insert_id = $wpdb->insert_id;
        error_log('✅ INSERT SUCCESS! New customer ID: ' . $insert_id);
        
        return $insert_id;
    }
    
    public function update($id, $data) {
        global $wpdb;
        
        error_log('✏️ SAW Model Customer: update() called for ID: ' . $id);
        error_log('   Input data: ' . print_r($data, true));
        
        $validation = $this->validate($data, $id);
        if (is_wp_error($validation)) {
            error_log('❌ Validation FAILED: ' . $validation->get_error_message());
            return $validation;
        }
        
        error_log('✅ Validation passed');
        
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
            error_log('📸 Logo file detected, attempting upload...');
            $old_customer = $this->get_by_id($id);
            
            $logo_result = $this->upload_logo($_FILES['logo']);
            if (is_wp_error($logo_result)) {
                error_log('❌ Logo upload FAILED: ' . $logo_result->get_error_message());
                return $logo_result;
            }
            
            if (!empty($old_customer['logo_url'])) {
                $this->delete_logo($old_customer['logo_url']);
                error_log('🗑️ Old logo deleted');
            }
            
            $update_data['logo_url'] = $logo_result;
            error_log('✅ Logo uploaded: ' . $logo_result);
        }
        
        if (empty($update_data)) {
            error_log('⚠️ No data to update');
            return new WP_Error('no_data', 'Žádná data k aktualizaci.');
        }
        
        error_log('📦 Update data prepared:');
        error_log('   ' . print_r($update_data, true));
        
        error_log('🚀 Attempting UPDATE in table: ' . $this->table_name);
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id)
        );
        
        if ($result === false) {
            error_log('❌ UPDATE FAILED!');
            error_log('   Last error: ' . $wpdb->last_error);
            error_log('   Last query: ' . $wpdb->last_query);
            return new WP_Error('db_error', 'Chyba při aktualizaci zákazníka: ' . $wpdb->last_error);
        }
        
        error_log('✅ UPDATE SUCCESS! Rows affected: ' . $result);
        
        return true;
    }
    
    public function delete($id) {
        global $wpdb;
        
        error_log('🗑️ SAW Model Customer: delete() called for ID: ' . $id);
        
        $customer = $this->get_by_id($id);
        if (!$customer) {
            error_log('❌ Customer NOT found for deletion');
            return new WP_Error('not_found', 'Zákazník nenalezen.');
        }
        
        if (!empty($customer['logo_url'])) {
            $this->delete_logo($customer['logo_url']);
            error_log('🗑️ Logo deleted');
        }
        
        $result = $wpdb->delete($this->table_name, array('id' => $id));
        
        if ($result === false) {
            error_log('❌ DELETE FAILED: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Chyba při mazání zákazníka: ' . $wpdb->last_error);
        }
        
        error_log('✅ DELETE SUCCESS!');
        
        return true;
    }
    
    private function validate($data, $id = null) {
        error_log('🔍 Validating data...');
        
        if (empty($data['name'])) {
            error_log('❌ Validation: Name is empty');
            return new WP_Error('name_required', 'Název zákazníka je povinný.');
        }
        
        if (!empty($data['ico'])) {
            $ico = sanitize_text_field($data['ico']);
            if (!preg_match('/^\d{6,12}$/', $ico)) {
                error_log('❌ Validation: ICO format invalid');
                return new WP_Error('ico_invalid', 'IČO musí být 6-12 číslic.');
            }
        }
        
        if (!empty($data['primary_color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['primary_color'])) {
            error_log('❌ Validation: Color format invalid');
            return new WP_Error('color_invalid', 'Neplatný formát barvy.');
        }
        
        error_log('✅ Validation: All checks passed');
        return true;
    }
    
    private function upload_logo($file) {
        error_log('📸 upload_logo() called');
        error_log('   File info: ' . print_r($file, true));
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log('❌ Upload error code: ' . $file['error']);
            return new WP_Error('upload_error', 'Chyba při nahrávání souboru.');
        }
        
        if ($file['size'] > $this->max_file_size) {
            error_log('❌ File too large: ' . $file['size'] . ' bytes');
            return new WP_Error('file_too_large', 'Soubor je příliš velký. Maximum je 5 MB.');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        error_log('   MIME type: ' . $mime_type);
        
        if (!in_array($mime_type, $this->allowed_mime_types)) {
            error_log('❌ Invalid MIME type');
            return new WP_Error('invalid_file_type', 'Neplatný typ souboru. Povolené jsou pouze obrázky.');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo-' . uniqid() . '.' . $extension;
        $filepath = $this->upload_dir . $filename;
        
        error_log('   Target filepath: ' . $filepath);
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log('❌ move_uploaded_file() FAILED');
            return new WP_Error('move_failed', 'Nepodařilo se uložit soubor.');
        }
        
        error_log('✅ File uploaded successfully: ' . $filename);
        
        return $filename;
    }
    
    private function delete_logo($logo_filename) {
        if (empty($logo_filename)) {
            return;
        }
        
        $filepath = $this->upload_dir . $logo_filename;
        
        if (file_exists($filepath)) {
            unlink($filepath);
            error_log('🗑️ Logo file deleted: ' . $filepath);
        }
    }
    
    private function get_logo_url($logo_filename) {
        if (empty($logo_filename)) {
            return '';
        }
        
        return $this->upload_url . $logo_filename;
    }
}