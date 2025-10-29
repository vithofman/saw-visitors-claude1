<?php
/**
 * SAW Controller Customers - DEBUG VERSION
 * 
 * @package SAW_Visitors
 * @version 4.7.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Customers {
    
    private $customer_model;
    
    public function __construct() {
        error_log('🟢 SAW Controller Customers: __construct() called');
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-customer.php';
        $this->customer_model = new SAW_Model_Customer();
        
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_saw_admin_table_delete', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_saw_get_customers_for_switcher', array($this, 'ajax_get_customers_for_switcher'));
        add_action('wp_ajax_saw_switch_customer', array($this, 'ajax_switch_customer'));
    }
    
    private function enqueue_customers_assets() {
        wp_enqueue_style('saw-admin-table', SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table.css', array(), SAW_VISITORS_VERSION);
        wp_enqueue_script('saw-admin-table', SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table.js', array('jquery'), SAW_VISITORS_VERSION, true);
        wp_enqueue_script('saw-admin-table-ajax', SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table-ajax.js', array('jquery', 'saw-admin-table'), SAW_VISITORS_VERSION, true);
        
        wp_localize_script('saw-admin-table-ajax', 'sawAdminTableAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('saw_admin_table_nonce'),
            'entity'  => 'customers',
        ));
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/pages/saw-customers.css')) {
            wp_enqueue_style('saw-customers', SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-customers.css', array('saw-admin-table'), SAW_VISITORS_VERSION);
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/pages/saw-customers-form.css')) {
            wp_enqueue_style('saw-customers-form', SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-customers-form.css', array(), SAW_VISITORS_VERSION);
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/pages/saw-customers.js')) {
            wp_enqueue_script('saw-customers', SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-customers.js', array('jquery', 'saw-admin-table'), SAW_VISITORS_VERSION, true);
        }
    }
    
    public function index() {
        error_log('🟢 SAW Controller: index() called');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $allowed_orderby = array('name', 'ico', 'created_at');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }
        
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $customers = $this->customer_model->get_all(array(
            'search'  => $search,
            'orderby' => $orderby,
            'order'   => $order,
            'limit'   => $per_page,
            'offset'  => $offset,
        ));
        
        $total_customers = $this->customer_model->count(array('search' => $search));
        $total_pages = ceil($total_customers / $per_page);
        
        $message = '';
        $message_type = '';
        
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            $message = 'Zákazník byl úspěšně smazán.';
            $message_type = 'success';
        }
        
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            $message = 'Zákazník byl úspěšně aktualizován.';
            $message_type = 'success';
        }
        
        if (isset($_GET['created']) && $_GET['created'] === '1') {
            $message = 'Zákazník byl úspěšně vytvořen.';
            $message_type = 'success';
        }
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/list.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Správa zákazníků', 'customers', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function create() {
        error_log('🟢 SAW Controller: create() called');
        error_log('   Request Method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('   Request URI: ' . $_SERVER['REQUEST_URI']);
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log('🔵 POST request detected');
            error_log('   All POST keys: ' . implode(', ', array_keys($_POST)));
            error_log('   POST data (full): ' . print_r($_POST, true));
            
            if (!isset($_POST['saw_customer_nonce'])) {
                error_log('❌❌❌ CRITICAL: saw_customer_nonce NOT FOUND in POST data!');
                error_log('   Available fields: ' . print_r(array_keys($_POST), true));
                
                echo '<pre>';
                echo 'DEBUG: Nonce field missing from POST data<br>';
                echo 'POST keys: ' . implode(', ', array_keys($_POST)) . '<br>';
                echo 'Expected: saw_customer_nonce<br>';
                echo '</pre>';
                
                wp_die('Chybí bezpečnostní token (saw_customer_nonce). Zkuste znovu načíst formulář.');
            }
            
            error_log('✅ Nonce field found: ' . $_POST['saw_customer_nonce']);
            
            $nonce_verify = wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form');
            error_log('   Nonce verification result: ' . ($nonce_verify ? 'TRUE' : 'FALSE'));
            
            if (!$nonce_verify) {
                error_log('❌ Nonce verification FAILED');
                error_log('   Nonce value: ' . $_POST['saw_customer_nonce']);
                error_log('   Action: saw_customer_form');
                
                echo '<pre>';
                echo 'DEBUG: Nonce verification failed<br>';
                echo 'Nonce value: ' . $_POST['saw_customer_nonce'] . '<br>';
                echo 'Action: saw_customer_form<br>';
                echo '</pre>';
                
                wp_die('Bezpečnostní kontrola selhala. Zkuste znovu načíst formulář.');
            }
            
            error_log('✅ Nonce verified successfully');
            
            $data = array(
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'ico' => isset($_POST['ico']) ? sanitize_text_field($_POST['ico']) : '',
                'dic' => isset($_POST['dic']) ? sanitize_text_field($_POST['dic']) : '',
                'address_street' => isset($_POST['address_street']) ? sanitize_text_field($_POST['address_street']) : '',
                'address_number' => isset($_POST['address_number']) ? sanitize_text_field($_POST['address_number']) : '',
                'address_city' => isset($_POST['address_city']) ? sanitize_text_field($_POST['address_city']) : '',
                'address_zip' => isset($_POST['address_zip']) ? sanitize_text_field($_POST['address_zip']) : '',
                'address_country' => isset($_POST['address_country']) ? sanitize_text_field($_POST['address_country']) : 'Česká republika',
                'billing_address_street' => isset($_POST['billing_address_street']) ? sanitize_text_field($_POST['billing_address_street']) : '',
                'billing_address_number' => isset($_POST['billing_address_number']) ? sanitize_text_field($_POST['billing_address_number']) : '',
                'billing_address_city' => isset($_POST['billing_address_city']) ? sanitize_text_field($_POST['billing_address_city']) : '',
                'billing_address_zip' => isset($_POST['billing_address_zip']) ? sanitize_text_field($_POST['billing_address_zip']) : '',
                'billing_address_country' => isset($_POST['billing_address_country']) ? sanitize_text_field($_POST['billing_address_country']) : '',
                'contact_person' => isset($_POST['contact_person']) ? sanitize_text_field($_POST['contact_person']) : '',
                'contact_position' => isset($_POST['contact_position']) ? sanitize_text_field($_POST['contact_position']) : '',
                'contact_email' => isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '',
                'contact_phone' => isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '',
                'website' => isset($_POST['website']) ? esc_url_raw($_POST['website']) : '',
                'account_type_id' => isset($_POST['account_type_id']) ? intval($_POST['account_type_id']) : null,
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'potential',
                'acquisition_source' => isset($_POST['acquisition_source']) ? sanitize_text_field($_POST['acquisition_source']) : '',
                'subscription_type' => isset($_POST['subscription_type']) ? sanitize_text_field($_POST['subscription_type']) : 'monthly',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_text_field($_POST['primary_color']) : '#1e40af',
                'admin_language_default' => isset($_POST['admin_language_default']) ? sanitize_text_field($_POST['admin_language_default']) : 'cs',
            );
            
            error_log('🚀 Calling customer_model->create()...');
            $result = $this->customer_model->create($data);
            
            if (is_wp_error($result)) {
                error_log('❌ Create FAILED: ' . $result->get_error_message());
                wp_die($result->get_error_message());
            }
            
            error_log('✅ Customer created successfully! ID: ' . $result);
            wp_redirect('/admin/settings/customers/?created=1');
            exit;
        }
        
        $is_edit = false;
        $customer = array(
            'name' => '',
            'ico' => '',
            'dic' => '',
            'address_street' => '',
            'address_number' => '',
            'address_city' => '',
            'address_zip' => '',
            'address_country' => 'Česká republika',
            'billing_address_street' => '',
            'billing_address_number' => '',
            'billing_address_city' => '',
            'billing_address_zip' => '',
            'billing_address_country' => '',
            'contact_person' => '',
            'contact_position' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'website' => '',
            'notes' => '',
            'primary_color' => '#1e40af',
            'logo_url' => '',
            'logo_url_full' => '',
            'status' => 'potential',
            'subscription_type' => 'monthly',
            'admin_language_default' => 'cs',
        );
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer_data = $this->get_current_customer_data();
            $layout->render($content, 'Nový zákazník', 'customers', $user, $customer_data);
        } else {
            echo $content;
        }
    }
    
    public function edit($id) {
        error_log('🟢 SAW Controller: edit() called for ID: ' . $id);
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            error_log('❌ Customer ID ' . $id . ' not found');
            wp_redirect('/admin/settings/customers/');
            exit;
        }
        
        $this->enqueue_customers_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['saw_customer_nonce']) || !wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            $data = array(
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'ico' => isset($_POST['ico']) ? sanitize_text_field($_POST['ico']) : '',
                'dic' => isset($_POST['dic']) ? sanitize_text_field($_POST['dic']) : '',
                'address_street' => isset($_POST['address_street']) ? sanitize_text_field($_POST['address_street']) : '',
                'address_number' => isset($_POST['address_number']) ? sanitize_text_field($_POST['address_number']) : '',
                'address_city' => isset($_POST['address_city']) ? sanitize_text_field($_POST['address_city']) : '',
                'address_zip' => isset($_POST['address_zip']) ? sanitize_text_field($_POST['address_zip']) : '',
                'address_country' => isset($_POST['address_country']) ? sanitize_text_field($_POST['address_country']) : 'Česká republika',
                'billing_address_street' => isset($_POST['billing_address_street']) ? sanitize_text_field($_POST['billing_address_street']) : '',
                'billing_address_number' => isset($_POST['billing_address_number']) ? sanitize_text_field($_POST['billing_address_number']) : '',
                'billing_address_city' => isset($_POST['billing_address_city']) ? sanitize_text_field($_POST['billing_address_city']) : '',
                'billing_address_zip' => isset($_POST['billing_address_zip']) ? sanitize_text_field($_POST['billing_address_zip']) : '',
                'billing_address_country' => isset($_POST['billing_address_country']) ? sanitize_text_field($_POST['billing_address_country']) : '',
                'contact_person' => isset($_POST['contact_person']) ? sanitize_text_field($_POST['contact_person']) : '',
                'contact_position' => isset($_POST['contact_position']) ? sanitize_text_field($_POST['contact_position']) : '',
                'contact_email' => isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '',
                'contact_phone' => isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '',
                'website' => isset($_POST['website']) ? esc_url_raw($_POST['website']) : '',
                'account_type_id' => isset($_POST['account_type_id']) ? intval($_POST['account_type_id']) : null,
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'potential',
                'acquisition_source' => isset($_POST['acquisition_source']) ? sanitize_text_field($_POST['acquisition_source']) : '',
                'subscription_type' => isset($_POST['subscription_type']) ? sanitize_text_field($_POST['subscription_type']) : 'monthly',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_text_field($_POST['primary_color']) : '#1e40af',
                'admin_language_default' => isset($_POST['admin_language_default']) ? sanitize_text_field($_POST['admin_language_default']) : 'cs',
            );
            
            $result = $this->customer_model->update($id, $data);
            
            if (is_wp_error($result)) {
                error_log('❌ Update FAILED: ' . $result->get_error_message());
                wp_die($result->get_error_message());
            }
            
            error_log('✅ Customer updated successfully! ID: ' . $id);
            wp_redirect('/admin/settings/customers/?updated=1');
            exit;
        }
        
        $is_edit = true;
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer_data = $this->get_current_customer_data();
            $layout->render($content, 'Upravit zákazníka', 'customers', $user, $customer_data);
        } else {
            echo $content;
        }
    }
    
    public function delete($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_customer_' . $id)) {
            wp_die('Bezpečnostní kontrola selhala.');
        }
        
        $result = $this->customer_model->delete($id);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        wp_redirect('/admin/settings/customers/?deleted=1');
        exit;
    }
    
    private function get_current_user_data() {
        $user_id = isset($_SESSION['saw_user_id']) ? absint($_SESSION['saw_user_id']) : 0;
        
        if (!$user_id) {
            return null;
        }
        
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d",
            $user_id
        ), ARRAY_A);
        
        return $user;
    }
    
    private function get_current_customer_data() {
        $customer_id = $this->get_current_customer_id();
        
        if (!$customer_id) {
            return $this->load_first_customer();
        }
        
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            return $this->load_first_customer();
        }
        
        return $customer;
    }
    
    private function get_current_customer_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_customer_id'])) {
            return absint($_SESSION['saw_current_customer_id']);
        }
        
        if (is_user_logged_in()) {
            $meta_id = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
            
            if ($meta_id) {
                $_SESSION['saw_current_customer_id'] = $meta_id;
                return $meta_id;
            }
        }
        
        return null;
    }
    
    private function load_first_customer() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_customers';
        
        $customer = $wpdb->get_row(
            "SELECT * FROM {$table_name} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            $_SESSION['saw_current_customer_id'] = intval($customer['id']);
            
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'saw_current_customer_id', intval($customer['id']));
            }
            
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                'logo_url' => $customer['logo_url'] ?? '',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        );
    }
    
    public function ajax_search_customers() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění.'));
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'name';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'ASC';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 20;
        
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $allowed_orderby = array('name', 'ico', 'created_at');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }
        
        $offset = ($page - 1) * $per_page;
        
        $customers = $this->customer_model->get_all(array(
            'search'  => $search,
            'orderby' => $orderby,
            'order'   => $order,
            'limit'   => $per_page,
            'offset'  => $offset,
        ));
        
        $total_customers = $this->customer_model->count(array('search' => $search));
        $total_pages = ceil($total_customers / $per_page);
        
        wp_send_json_success(array(
            'customers' => $customers,
            'total' => $total_customers,
            'total_pages' => $total_pages,
            'current_page' => $page,
        ));
    }
    
    public function ajax_delete_customer() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění.'));
        }
        
        $entity = isset($_POST['entity']) ? sanitize_text_field($_POST['entity']) : '';
        
        if ($entity !== 'customers') {
            wp_send_json_error(array('message' => 'Neplatná entita'));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
        }
        
        $result = $this->customer_model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Zákazník byl úspěšně smazán'));
    }
    
    public function ajax_get_customers_for_switcher() {
        check_ajax_referer('saw_customer_switcher_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění.'));
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $customers = $this->customer_model->get_all(array(
            'search' => $search,
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 50,
        ));
        
        wp_send_json_success(array(
            'customers' => $customers,
        ));
    }
    
    public function ajax_switch_customer() {
        check_ajax_referer('saw_customer_switcher_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění.'));
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if ($customer_id <= 0) {
            wp_send_json_error(array('message' => 'Neplatné ID zákazníka.'));
        }
        
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen.'));
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_current_customer_id'] = $customer_id;
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'saw_current_customer_id', $customer_id);
        }
        
        $session_id = session_id();
        
        wp_send_json_success(array(
            'message' => 'Zákazník byl přepnut.',
            'customer' => array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
            ),
            'session_id' => $session_id,
            'debug' => array(
                'session_saved' => isset($_SESSION['saw_current_customer_id']),
                'user_meta_saved' => is_user_logged_in(),
            ),
        ));
    }
}