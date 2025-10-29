<?php
/**
 * SAW Controller Customers
 * 
 * @package SAW_Visitors
 * @version 4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Customers {
    
    private $customer_model;
    
    public function __construct() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-customer.php';
        $this->customer_model = new SAW_Model_Customer();
        
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_saw_admin_table_delete', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_saw_get_customers_for_switcher', array($this, 'ajax_get_customers_for_switcher'));
        add_action('wp_ajax_saw_switch_customer', array($this, 'ajax_switch_customer'));
        add_action('wp_ajax_saw_get_customer_detail', array($this, 'ajax_get_customer_detail'));
    }
    
    private function enqueue_customers_assets() {
        wp_enqueue_style(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'saw-admin-table-ajax',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table-ajax.js',
            array('jquery', 'saw-admin-table'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-admin-table-ajax', 'sawAdminTableAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('saw_admin_table_nonce'),
            'entity'  => 'customers',
        ));
        
        wp_enqueue_style(
            'saw-admin-table-form',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table-form.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/pages/saw-customers-specific.css')) {
            wp_enqueue_style(
                'saw-customers-specific',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-customers-specific.css',
                array('saw-admin-table-form'),
                SAW_VISITORS_VERSION
            );
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/pages/saw-customers.js')) {
            wp_enqueue_script(
                'saw-customers',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-customers.js',
                array('jquery', 'saw-admin-table'),
                SAW_VISITORS_VERSION,
                true
            );
        }
    }
    
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $account_type_filter = isset($_GET['account_type']) ? sanitize_text_field($_GET['account_type']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $allowed_orderby = array('name', 'ico', 'status', 'created_at');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }
        
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $filters = array(
            'search'  => $search,
            'orderby' => $orderby,
            'order'   => $order,
            'limit'   => $per_page,
            'offset'  => $offset,
        );
        
        if ($status_filter) {
            $filters['status'] = $status_filter;
        }
        
        if ($account_type_filter) {
            $filters['account_type'] = $account_type_filter;
        }
        
        $customers = $this->customer_model->get_all($filters);
        $total_customers = $this->customer_model->count($filters);
        $total_pages = ceil($total_customers / $per_page);
        
        $account_types_for_filter = array();
        global $wpdb;
        $types = $wpdb->get_results("SELECT id, display_name FROM {$wpdb->prefix}saw_account_types ORDER BY display_name ASC", ARRAY_A);
        foreach ($types as $type) {
            $account_types_for_filter[$type['id']] = $type['display_name'];
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
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
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
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'potential',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_hex_color($_POST['primary_color']) : '#1e40af',
                'admin_language' => isset($_POST['admin_language']) ? sanitize_text_field($_POST['admin_language']) : 'cs',
            );
            
            $result = $this->customer_model->create($data);
            
            if (is_wp_error($result)) {
                wp_die($result->get_error_message());
            }
            
            wp_redirect(home_url('/admin/settings/customers/?created=1'));
            exit;
        }
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Nový zákazník', 'customers', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            wp_die('Zákazník nenalezen.');
        }
        
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
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'potential',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_hex_color($_POST['primary_color']) : '#1e40af',
                'admin_language' => isset($_POST['admin_language']) ? sanitize_text_field($_POST['admin_language']) : 'cs',
            );
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = wp_upload_dir();
                $target_dir = $upload_dir['basedir'] . '/saw-customers/';
                
                if (!file_exists($target_dir)) {
                    wp_mkdir_p($target_dir);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'svg');
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'customer-' . $id . '-' . time() . '.' . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                        if (!empty($customer['logo_url'])) {
                            $old_file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $customer['logo_url']);
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }
                        
                        $data['logo_url'] = $upload_dir['baseurl'] . '/saw-customers/' . $new_filename;
                    }
                }
            }
            
            if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
                if (!empty($customer['logo_url'])) {
                    $upload_dir = wp_upload_dir();
                    $old_file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $customer['logo_url']);
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                $data['logo_url'] = '';
            }
            
            $result = $this->customer_model->update($id, $data);
            
            if (is_wp_error($result)) {
                wp_die($result->get_error_message());
            }
            
            wp_redirect(home_url('/admin/settings/customers/?updated=1'));
            exit;
        }
        
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
        
        wp_redirect(home_url('/admin/settings/customers/?deleted=1'));
        exit;
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
        
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen.'));
        }
        
        if (!empty($customer['logo_url'])) {
            $upload_dir = wp_upload_dir();
            $logo_file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $customer['logo_url']);
            if (file_exists($logo_file)) {
                @unlink($logo_file);
            }
        }
        
        $result = $this->customer_model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Zákazník byl úspěšně smazán'));
    }
    
    public function ajax_get_customer_detail() {
        check_ajax_referer('saw_customer_modal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění'));
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Neplatné ID zákazníka'));
        }
        
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
        }
        
        $status_labels = array(
            'potential' => 'Potenciální',
            'active' => 'Aktivní',
            'inactive' => 'Neaktivní',
        );
        
        $language_labels = array(
            'cs' => 'Čeština',
            'en' => 'English',
            'de' => 'Deutsch',
        );
        
        $subscription_type_labels = array(
            'free' => 'Zdarma',
            'basic' => 'Basic',
            'pro' => 'Pro',
            'enterprise' => 'Enterprise',
        );
        
        $customer['status_label'] = $status_labels[$customer['status']] ?? $customer['status'];
        $customer['admin_language_label'] = $language_labels[$customer['admin_language']] ?? $customer['admin_language'];
        $customer['subscription_type_label'] = $subscription_type_labels[$customer['subscription_type'] ?? 'free'] ?? 'Zdarma';
        
        $customer['formatted_operational_address'] = trim(
            ($customer['address_street'] ?? '') . ' ' . 
            ($customer['address_number'] ?? '') . ', ' . 
            ($customer['address_city'] ?? '') . ' ' . 
            ($customer['address_zip'] ?? '')
        );
        
        if ($customer['formatted_operational_address'] === ', ') {
            $customer['formatted_operational_address'] = '';
        }
        
        $customer['formatted_billing_address'] = trim(
            ($customer['billing_address_street'] ?? '') . ' ' . 
            ($customer['billing_address_number'] ?? '') . ', ' . 
            ($customer['billing_address_city'] ?? '') . ' ' . 
            ($customer['billing_address_zip'] ?? '')
        );
        
        if ($customer['formatted_billing_address'] === ', ') {
            $customer['formatted_billing_address'] = '';
        }
        
        if (!empty($customer['created_at'])) {
            $customer['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($customer['created_at']));
        }
        
        if (!empty($customer['updated_at'])) {
            $customer['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($customer['updated_at']));
        }
        
        if (!empty($customer['last_payment_date'])) {
            $customer['last_payment_date_formatted'] = date_i18n('d.m.Y', strtotime($customer['last_payment_date']));
        }
        
        wp_send_json_success(array('customer' => $customer));
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
        
        wp_send_json_success(array(
            'message' => 'Zákazník byl přepnut.',
            'customer' => array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                'logo_url' => $customer['logo_url'] ?? '',
            ),
        ));
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
}