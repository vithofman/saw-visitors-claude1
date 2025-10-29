<?php
/**
 * SAW Customer Model
 * 
 * @package SAW_Visitors
 * @version 4.7.1 COMPLETE
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Model_Customer {
    
    private $table_name;
    private $account_types_table;
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
        $this->account_types_table = $wpdb->prefix . 'saw_account_types';
        
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/saw-visitors/saw-customers/';
        $this->upload_url = $upload['baseurl'] . '/saw-visitors/saw-customers/';
        
        $this->ensure_upload_directory();
    }
    
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
    
    public function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'search'  => '',
            'status'  => '',
            'account_type' => '',
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => 999,
            'offset'  => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $orderby = in_array($args['orderby'], array('id', 'name', 'ico', 'status', 'created_at')) 
            ? $args['orderby'] 
            : 'name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);
        
        $sql = "SELECT 
            c.*,
            at.display_name as account_type_display_name,
            at.color as account_type_color
        FROM {$this->table_name} c
        LEFT JOIN {$this->account_types_table} at ON c.account_type_id = at.id
        WHERE 1=1";
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $sql .= $wpdb->prepare(
                " AND (c.name LIKE %s OR c.ico LIKE %s OR c.contact_email LIKE %s OR c.address_city LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        if (!empty($args['status'])) {
            $sql .= $wpdb->prepare(" AND c.status = %s", sanitize_text_field($args['status']));
        }
        
        if (!empty($args['account_type'])) {
            $sql .= $wpdb->prepare(" AND c.account_type_id = %d", intval($args['account_type']));
        }
        
        $sql .= " ORDER BY c.{$orderby} {$order}";
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $customers = $wpdb->get_results($sql, ARRAY_A);
        
        if (!empty($customers)) {
            foreach ($customers as &$customer) {
                $customer['logo_url_full'] = $this->get_logo_url($customer['logo_url']);
            }
        }
        
        return $customers;
    }
    
    public function count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'search' => '',
            'status' => '',
            'account_type' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $sql .= $wpdb->prepare(
                " AND (name LIKE %s OR ico LIKE %s OR contact_email LIKE %s OR address_city LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        if (!empty($args['status'])) {
            $sql .= $wpdb->prepare(" AND status = %s", sanitize_text_field($args['status']));
        }
        
        if (!empty($args['account_type'])) {
            $sql .= $wpdb->prepare(" AND account_type_id = %d", intval($args['account_type']));
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    public function get_by_id($id) {
        global $wpdb;
        
        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    c.*,
                    at.display_name as account_type_display_name,
                    at.color as account_type_color
                FROM {$this->table_name} c
                LEFT JOIN {$this->account_types_table} at ON c.account_type_id = at.id
                WHERE c.id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if ($customer) {
            $customer['logo_url_full'] = $this->get_logo_url($customer['logo_url']);
            $customer['has_billing_address'] = !empty($customer['billing_address_street']);
        }
        
        return $customer;
    }
    
    public function get_by_status($status, $args = array()) {
        $args['status'] = $status;
        return $this->get_all($args);
    }
    
    public function get_by_account_type($account_type_id, $args = array()) {
        $args['account_type'] = $account_type_id;
        return $this->get_all($args);
    }
    
    public function get_active_customers($args = array()) {
        return $this->get_by_status('active', $args);
    }
    
    public function count_by_status() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            ARRAY_A
        );
        
        $counts = array(
            'potential' => 0,
            'active'    => 0,
            'inactive'  => 0,
        );
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        
        return $counts;
    }
    
    public function format_address($customer, $type = 'operational') {
        $prefix = $type === 'billing' ? 'billing_' : '';
        
        $parts = array();
        
        $street = trim($customer[$prefix . 'address_street'] ?? '');
        $number = trim($customer[$prefix . 'address_number'] ?? '');
        
        if ($street && $number) {
            $parts[] = $street . ' ' . $number;
        } elseif ($street) {
            $parts[] = $street;
        }
        
        $zip = trim($customer[$prefix . 'address_zip'] ?? '');
        $city = trim($customer[$prefix . 'address_city'] ?? '');
        
        if ($zip && $city) {
            $parts[] = $zip . ' ' . $city;
        } elseif ($city) {
            $parts[] = $city;
        }
        
        $country = trim($customer[$prefix . 'address_country'] ?? '');
        if ($country && $country !== 'Česká republika') {
            $parts[] = $country;
        }
        
        return implode(', ', array_filter($parts));
    }
    
    public function create($data) {
        global $wpdb;
        
        error_log('🚀 Model: create() - START');
        error_log('   Incoming data: ' . print_r($data, true));
        
        $validation = $this->validate($data);
        if (is_wp_error($validation)) {
            error_log('❌ Validation failed: ' . $validation->get_error_message());
            return $validation;
        }
        
        $insert_data = array(
            'name'                      => sanitize_text_field($data['name']),
            'ico'                       => !empty($data['ico']) ? sanitize_text_field($data['ico']) : null,
            'dic'                       => !empty($data['dic']) ? sanitize_text_field($data['dic']) : null,
            
            'address_street'            => !empty($data['address_street']) ? sanitize_text_field($data['address_street']) : null,
            'address_number'            => !empty($data['address_number']) ? sanitize_text_field($data['address_number']) : null,
            'address_city'              => !empty($data['address_city']) ? sanitize_text_field($data['address_city']) : null,
            'address_zip'               => !empty($data['address_zip']) ? sanitize_text_field($data['address_zip']) : null,
            'address_country'           => !empty($data['address_country']) ? sanitize_text_field($data['address_country']) : 'Česká republika',
            
            'billing_address_street'    => !empty($data['billing_address_street']) ? sanitize_text_field($data['billing_address_street']) : null,
            'billing_address_number'    => !empty($data['billing_address_number']) ? sanitize_text_field($data['billing_address_number']) : null,
            'billing_address_city'      => !empty($data['billing_address_city']) ? sanitize_text_field($data['billing_address_city']) : null,
            'billing_address_zip'       => !empty($data['billing_address_zip']) ? sanitize_text_field($data['billing_address_zip']) : null,
            'billing_address_country'   => !empty($data['billing_address_country']) ? sanitize_text_field($data['billing_address_country']) : null,
            
            'contact_person'            => !empty($data['contact_person']) ? sanitize_text_field($data['contact_person']) : null,
            'contact_position'          => !empty($data['contact_position']) ? sanitize_text_field($data['contact_position']) : null,
            'contact_email'             => !empty($data['contact_email']) ? sanitize_email($data['contact_email']) : null,
            'contact_phone'             => !empty($data['contact_phone']) ? sanitize_text_field($data['contact_phone']) : null,
            'website'                   => !empty($data['website']) ? esc_url_raw($data['website']) : null,
            
            'account_type_id'           => !empty($data['account_type_id']) ? intval($data['account_type_id']) : null,
            'status'                    => !empty($data['status']) ? sanitize_text_field($data['status']) : 'potential',
            'acquisition_source'        => !empty($data['acquisition_source']) ? sanitize_text_field($data['acquisition_source']) : null,
            'subscription_type'         => !empty($data['subscription_type']) ? sanitize_text_field($data['subscription_type']) : 'monthly',
            'last_payment_date'         => !empty($data['last_payment_date']) ? sanitize_text_field($data['last_payment_date']) : null,
            
            'primary_color'             => !empty($data['primary_color']) ? sanitize_hex_color($data['primary_color']) : '#1e40af',
            'admin_language_default'    => !empty($data['admin_language_default']) ? sanitize_text_field($data['admin_language_default']) : 'cs',
            'notes'                     => !empty($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
            'logo_url'                  => null,
            'created_at'                => current_time('mysql'),
        );
        
        if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
            $logo_result = $this->upload_logo($_FILES['logo']);
            if (is_wp_error($logo_result)) {
                return $logo_result;
            }
            $insert_data['logo_url'] = $logo_result;
        }
        
        error_log('📦 Prepared insert data: ' . print_r($insert_data, true));
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            error_log('❌ INSERT failed: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Chyba při vytváření zákazníka: ' . $wpdb->last_error);
        }
        
        $insert_id = $wpdb->insert_id;
        error_log('✅ Customer created with ID: ' . $insert_id);
        
        return $insert_id;
    }
    
    public function update($id, $data) {
        global $wpdb;
        
        error_log('🚀 Model: update() - START for ID: ' . $id);
        
        $validation = $this->validate($data, $id);
        if (is_wp_error($validation)) {
            error_log('❌ Validation failed: ' . $validation->get_error_message());
            return $validation;
        }
        
        $update_data = array();
        
        $fields = array(
            'name', 'ico', 'dic',
            'address_street', 'address_number', 'address_city', 'address_zip', 'address_country',
            'billing_address_street', 'billing_address_number', 'billing_address_city', 'billing_address_zip', 'billing_address_country',
            'contact_person', 'contact_position', 'contact_email', 'contact_phone', 'website',
            'status', 'acquisition_source', 'subscription_type', 'last_payment_date',
            'primary_color', 'admin_language_default', 'notes'
        );
        
        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            
            $value = $data[$field];
            
            if (in_array($field, array('name', 'ico', 'dic', 'address_street', 'address_number', 'address_city', 'address_zip', 'address_country', 'billing_address_street', 'billing_address_number', 'billing_address_city', 'billing_address_zip', 'billing_address_country', 'contact_person', 'contact_position', 'contact_phone', 'status', 'acquisition_source', 'subscription_type', 'last_payment_date', 'admin_language_default'))) {
                $update_data[$field] = !empty($value) ? sanitize_text_field($value) : null;
            } elseif ($field === 'contact_email') {
                $update_data[$field] = !empty($value) ? sanitize_email($value) : null;
            } elseif ($field === 'website') {
                $update_data[$field] = !empty($value) ? esc_url_raw($value) : null;
            } elseif ($field === 'primary_color') {
                $update_data[$field] = sanitize_hex_color($value);
            } elseif ($field === 'notes') {
                $update_data[$field] = !empty($value) ? sanitize_textarea_field($value) : null;
            }
        }
        
        if (isset($data['account_type_id'])) {
            $update_data['account_type_id'] = !empty($data['account_type_id']) ? intval($data['account_type_id']) : null;
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
        
        error_log('📦 Prepared update data: ' . print_r($update_data, true));
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id)
        );
        
        if ($result === false) {
            error_log('❌ UPDATE failed: ' . $wpdb->last_error);
            return new WP_Error('db_error', 'Chyba při aktualizaci zákazníka: ' . $wpdb->last_error);
        }
        
        error_log('✅ Customer updated successfully');
        
        return true;
    }
    
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
            return new WP_Error('db_error', 'Chyba při mazání zákazníka: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    public function get_by_ico($ico) {
        global $wpdb;
        
        $ico = sanitize_text_field($ico);
        
        if (empty($ico)) {
            return null;
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE ico = %s",
            $ico
        );
        
        $result = $wpdb->get_row($sql, ARRAY_A);
        
        return $result;
    }
    
    private function validate($data, $id = null) {
        if (empty($data['name'])) {
            return new WP_Error('name_required', 'Název zákazníka je povinný.');
        }
        
        if (!empty($data['ico']) && !preg_match('/^\d{6,12}$/', sanitize_text_field($data['ico']))) {
            return new WP_Error('ico_invalid', 'IČO musí být 6-12 číslic.');
        }
        
        if (!empty($data['contact_email']) && !is_email($data['contact_email'])) {
            return new WP_Error('email_invalid', 'Neplatný formát emailu.');
        }
        
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            return new WP_Error('url_invalid', 'Neplatný formát URL.');
        }
        
        if (!empty($data['status']) && !in_array($data['status'], array('potential', 'active', 'inactive'))) {
            return new WP_Error('status_invalid', 'Neplatný status.');
        }
        
        if (!empty($data['account_type_id'])) {
            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->account_types_table} WHERE id = %d",
                intval($data['account_type_id'])
            ));
            
            if (!$exists) {
                return new WP_Error('account_type_invalid', 'Typ účtu neexistuje.');
            }
        }
        
        if (!empty($data['primary_color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['primary_color'])) {
            return new WP_Error('color_invalid', 'Neplatný formát barvy.');
        }
        
        return true;
    }
    
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
    
    private function delete_logo($logo_filename) {
        if (empty($logo_filename)) {
            return;
        }
        
        $filepath = $this->upload_dir . $logo_filename;
        
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    private function get_logo_url($logo_filename) {
        if (empty($logo_filename)) {
            return '';
        }
        
        return $this->upload_url . $logo_filename;
    }
}