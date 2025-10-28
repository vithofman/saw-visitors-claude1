<?php
/**
 * Customers Controller - REFACTORED pro SAW_Admin_Table
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Customers_Controller {
    
    private $customer_model;
    
    public function __construct() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-customer.php';
        $this->customer_model = new SAW_Customer();
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_saw_admin_table_delete', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_saw_get_customers_for_switcher', array($this, 'ajax_get_customers_for_switcher'));
        add_action('wp_ajax_saw_switch_customer', array($this, 'ajax_switch_customer'));
    }
    
    /**
     * Enqueue customers assets - REFACTORED
     */
    private function enqueue_customers_assets() {
        // ✅ GLOBÁLNÍ ADMIN TABLE (tabulka, search, sort, pagination, delete)
        wp_enqueue_style(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-admin-table.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-admin-table.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'saw-admin-table-ajax',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-admin-table-ajax.js',
            array('jquery', 'saw-admin-table'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize global admin table
        wp_localize_script(
            'saw-admin-table-ajax',
            'sawAdminTableAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('saw_admin_table_nonce'),
                'entity'  => 'customers',
            )
        );
        
        // ✅ CUSTOMERS-SPECIFIC (logo upload, color picker)
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/saw-customers-specific.css')) {
            wp_enqueue_style(
                'saw-customers-specific',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-customers-specific.css',
                array('saw-admin-table'),
                SAW_VISITORS_VERSION
            );
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/saw-customers-specific.js')) {
            wp_enqueue_script(
                'saw-customers-specific',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-customers-specific.js',
                array('jquery', 'saw-admin-table'),
                SAW_VISITORS_VERSION,
                true
            );
        }
    }
    
    /**
     * List customers
     */
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        
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
        
        $total_customers = $this->customer_model->count($search);
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
        
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-list.php';
    }
    
    /**
     * AJAX search customers
     */
    public function ajax_search_customers() {
        if (!check_ajax_referer('saw_admin_table_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Neplatný token'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'name';
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'ASC';
        $per_page = 20;
        
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
        
        $total_customers = $this->customer_model->count($search);
        $total_pages = ceil($total_customers / $per_page);
        
        wp_send_json_success(array(
            'customers'      => $customers,
            'total_customers' => $total_customers,
            'total_pages'     => $total_pages,
            'current_page'    => $page,
        ));
    }
    
    /**
     * AJAX delete customer
     */
    public function ajax_delete_customer() {
        if (!check_ajax_referer('saw_admin_table_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Neplatný token'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
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
    
    /**
     * Create customer
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle form submission
            // TODO: Implement create logic
        }
        
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
    }
    
    /**
     * Edit customer
     */
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            wp_die('Zákazník nenalezen.', 'Chyba', array('response' => 404));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle form submission
            // TODO: Implement edit logic
        }
        
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
    }
    
    /**
     * Delete customer
     */
    public function delete($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_customer_' . $id)) {
            wp_die('Neplatný token.', 'Chyba', array('response' => 403));
        }
        
        $result = $this->customer_model->delete($id);
        
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(array('error' => urlencode($result->get_error_message())), home_url('/admin/settings/customers/')));
            exit;
        }
        
        wp_redirect(add_query_arg('deleted', '1', home_url('/admin/settings/customers/')));
        exit;
    }
    
    /**
     * AJAX get customers for switcher (SuperAdmin)
     */
    public function ajax_get_customers_for_switcher() {
        if (!check_ajax_referer('saw_customer_switcher_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Neplatný token'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $customers = $this->customer_model->get_all(array(
            'search'  => $search,
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => 50,
            'offset'  => 0,
        ));
        
        wp_send_json_success(array('customers' => $customers));
    }
    
    /**
     * AJAX switch customer (SuperAdmin)
     */
    public function ajax_switch_customer() {
        if (!check_ajax_referer('saw_customer_switcher_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Neplatný token'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Neplatné ID zákazníka'));
        }
        
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
        }
        
        $_SESSION['selected_customer_id'] = $customer_id;
        
        wp_send_json_success(array(
            'message'  => 'Zákazník byl úspěšně přepnut',
            'customer' => $customer,
        ));
    }
}