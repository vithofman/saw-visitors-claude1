<?php
/**
 * Customers Controller - FINAL FIXED VERSION (CHAT 7)
 * 
 * ✅ OPRAVENO v CHAT 7:
 * - get_current_customer_data() - dynamické načítání ze session
 * - ajax_switch_customer() - ukládá do session + user meta
 * - Přidány helper metody pro session management
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
     * Enqueue customers assets
     */
    private function enqueue_customers_assets() {
        // Global admin table
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
        
        wp_localize_script(
            'saw-admin-table-ajax',
            'sawAdminTableAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('saw_admin_table_nonce'),
                'entity'  => 'customers',
            )
        );
        
        // Customers-specific assets
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
     * List customers - WITH LAYOUT
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
        
        // Capture template output
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-list.php';
        $content = ob_get_clean();
        
        // Render with layout
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Správa zákazníků', 'customers', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    /**
     * CREATE - Fully implemented
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        // POST handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            // Prepare data
            $data = array(
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'ico' => isset($_POST['ico']) ? sanitize_text_field($_POST['ico']) : '',
                'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_text_field($_POST['primary_color']) : '#1e40af',
            );
            
            // Create customer
            $result = $this->customer_model->create($data);
            
            if (is_wp_error($result)) {
                $_SESSION['saw_customer_error'] = $result->get_error_message();
                wp_redirect('/admin/settings/customers/new/');
                exit;
            }
            
            // Redirect s success message
            wp_redirect('/admin/settings/customers/?created=1');
            exit;
        }
        
        // Render form
        $is_edit = false;
        $customer = array(
            'name' => '',
            'ico' => '',
            'address' => '',
            'notes' => '',
            'primary_color' => '#1e40af',
            'logo_url' => '',
            'logo_url_full' => '',
        );
        
        // Capture template
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
        $content = ob_get_clean();
        
        // Render with layout
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer_data = $this->get_current_customer_data();
            $layout->render($content, 'Nový zákazník', 'customers', $user, $customer_data);
        } else {
            echo $content;
        }
    }
    
    /**
     * EDIT - Fully implemented
     */
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        // Load customer
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            wp_die('Zákazník nenalezen.', 'Chyba', array('response' => 404));
        }
        
        // POST handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            // Prepare data
            $data = array(
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'ico' => isset($_POST['ico']) ? sanitize_text_field($_POST['ico']) : '',
                'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_text_field($_POST['primary_color']) : '#1e40af',
            );
            
            // Update customer
            $result = $this->customer_model->update($id, $data);
            
            if (is_wp_error($result)) {
                $_SESSION['saw_customer_error'] = $result->get_error_message();
                wp_redirect('/admin/settings/customers/edit/' . $id . '/');
                exit;
            }
            
            // Redirect with success
            wp_redirect('/admin/settings/customers/?updated=1');
            exit;
        }
        
        // Set edit mode
        $is_edit = true;
        
        // Capture template
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
        $content = ob_get_clean();
        
        // Render with layout
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer_data = $this->get_current_customer_data();
            $layout->render($content, 'Upravit zákazníka', 'customers', $user, $customer_data);
        } else {
            echo $content;
        }
    }
    
    /**
     * Get current user data
     */
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => 'admin',
            );
        }
        
        return array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
        );
    }
    
    /**
     * ✅ FIXED (CHAT 7): Get current customer data DYNAMICALLY
     * 
     * @return array Customer data
     */
    private function get_current_customer_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_customers';
        
        // Initialize session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 1. CHECK IF SUPERADMIN
        if (current_user_can('manage_options')) {
            // Load from session
            $customer_id = $this->get_selected_customer_id_from_session();
            
            if ($customer_id) {
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d",
                    $customer_id
                ), ARRAY_A);
                
                if ($customer) {
                    if (defined('SAW_DEBUG') && SAW_DEBUG) {
                        error_log('SAW Controller: SuperAdmin loaded customer from session: ID ' . $customer_id);
                    }
                    
                    return array(
                        'id' => $customer['id'],
                        'name' => $customer['name'],
                        'ico' => $customer['ico'] ?? '',
                        'address' => $customer['address'] ?? '',
                        'logo_url' => $customer['logo_url'] ?? '',
                    );
                }
            }
            
            // Fallback: Load first customer
            return $this->load_first_customer();
        }
        
        // 2. ADMIN/MANAGER: Load their customer
        // TODO: Implement when saw_users table is ready
        return $this->load_first_customer();
    }
    
    /**
     * ✅ NEW (CHAT 7): Get selected customer ID from session
     * 
     * @return int|null Customer ID or null
     */
    private function get_selected_customer_id_from_session() {
        // 1. Try PHP session
        if (isset($_SESSION['saw_current_customer_id'])) {
            return intval($_SESSION['saw_current_customer_id']);
        }
        
        // 2. Try WP user meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $meta_id = get_user_meta($user_id, 'saw_current_customer_id', true);
            
            if ($meta_id) {
                $meta_id = intval($meta_id);
                
                // Sync back to session
                $_SESSION['saw_current_customer_id'] = $meta_id;
                
                if (defined('SAW_DEBUG') && SAW_DEBUG) {
                    error_log('SAW Controller: Loaded customer ID from user meta: ' . $meta_id);
                }
                
                return $meta_id;
            }
        }
        
        return null;
    }
    
    /**
     * ✅ NEW (CHAT 7): Load first customer (fallback)
     * 
     * @return array Customer data
     */
    private function load_first_customer() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_customers';
        
        $customer = $wpdb->get_row(
            "SELECT * FROM {$table_name} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            // Save as default in session
            $_SESSION['saw_current_customer_id'] = intval($customer['id']);
            
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'saw_current_customer_id', intval($customer['id']));
            }
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Controller: Loaded first customer as fallback: ID ' . $customer['id']);
            }
            
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => $customer['address'] ?? '',
                'logo_url' => $customer['logo_url'] ?? '',
            );
        }
        
        // Ultimate fallback
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('SAW Controller: No customers found in database!');
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        );
    }
    
    /**
     * AJAX: Search customers
     */
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
        
        $total_customers = $this->customer_model->count($search);
        $total_pages = ceil($total_customers / $per_page);
        
        wp_send_json_success(array(
            'customers' => $customers,
            'total' => $total_customers,
            'total_pages' => $total_pages,
            'current_page' => $page,
        ));
    }
    
    /**
     * AJAX: Delete customer
     */
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
    
    /**
     * AJAX: Get customers for switcher (SuperAdmin only)
     */
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
    
    /**
     * ✅ FIXED (CHAT 7): AJAX switch customer - with session + user meta
     */
    public function ajax_switch_customer() {
        check_ajax_referer('saw_customer_switcher_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění.'));
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if ($customer_id <= 0) {
            wp_send_json_error(array('message' => 'Neplatné ID zákazníka.'));
        }
        
        // Verify customer exists
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen.'));
        }
        
        // ✅ KRITICKÁ ZMĚNA: Save to BOTH session and user meta
        
        // 1. PHP Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_current_customer_id'] = $customer_id;
        
        // 2. WP User Meta (persistence across sessions)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'saw_current_customer_id', $customer_id);
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log(sprintf(
                    'SAW Customer Switcher: User %d switched to customer %d (%s)',
                    $user_id,
                    $customer_id,
                    $customer->name
                ));
            }
        }
        
        // 3. Session ID for debugging
        $session_id = session_id();
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('SAW Customer Switcher: Session ID: ' . $session_id);
        }
        
        wp_send_json_success(array(
            'message' => 'Zákazník byl přepnut.',
            'customer' => array(
                'id' => $customer->id,
                'name' => $customer->name,
                'ico' => $customer->ico ?? '',
                'address' => $customer->address ?? '',
            ),
            'session_id' => $session_id,
            'debug' => array(
                'session_saved' => isset($_SESSION['saw_current_customer_id']),
                'user_meta_saved' => is_user_logged_in(),
            ),
        ));
    }
}