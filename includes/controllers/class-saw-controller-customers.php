<?php
/**
 * SAW Controller Customers
 * 
 * Controller pro správu zákazníků - list, create, edit, delete, AJAX operace
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Customers {
    
    /**
     * Instance Customer modelu
     * 
     * @var SAW_Model_Customer
     */
    private $customer_model;
    
    /**
     * Konstruktor
     * 
     * Inicializuje model a registruje AJAX handlery
     */
    public function __construct() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-customer.php';
        $this->customer_model = new SAW_Model_Customer();
        
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_saw_admin_table_delete', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_saw_get_customers_for_switcher', array($this, 'ajax_get_customers_for_switcher'));
        add_action('wp_ajax_saw_switch_customer', array($this, 'ajax_switch_customer'));
    }
    
    /**
     * Enqueue customers assets
     * 
     * Načítá globální admin table CSS/JS a lokální customers CSS/JS
     */
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
        
        wp_localize_script(
            'saw-admin-table-ajax',
            'sawAdminTableAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('saw_admin_table_nonce'),
                'entity'  => 'customers',
            )
        );
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/pages/saw-customers.css')) {
            wp_enqueue_style(
                'saw-customers',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-customers.css',
                array('saw-admin-table'),
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
    
    /**
     * List customers - zobrazení seznamu zákazníků
     * 
     * @return void
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
    
    /**
     * Create customer - zobrazení formuláře a zpracování vytvoření
     * 
     * @return void
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            $data = array(
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'ico' => isset($_POST['ico']) ? sanitize_text_field($_POST['ico']) : '',
                'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_text_field($_POST['primary_color']) : '#1e40af',
            );
            
            $result = $this->customer_model->create($data);
            
            if (is_wp_error($result)) {
                $_SESSION['saw_customer_error'] = $result->get_error_message();
                wp_redirect('/admin/settings/customers/new/');
                exit;
            }
            
            wp_redirect('/admin/settings/customers/?created=1');
            exit;
        }
        
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
    
    /**
     * Edit customer - zobrazení formuláře a zpracování úpravy
     * 
     * @param int $id ID zákazníka
     * @return void
     */
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            wp_redirect('/admin/settings/customers/');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            $data = array(
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'ico' => isset($_POST['ico']) ? sanitize_text_field($_POST['ico']) : '',
                'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'primary_color' => isset($_POST['primary_color']) ? sanitize_text_field($_POST['primary_color']) : '#1e40af',
            );
            
            $result = $this->customer_model->update($id, $data);
            
            if (is_wp_error($result)) {
                $_SESSION['saw_customer_error'] = $result->get_error_message();
                wp_redirect('/admin/settings/customers/edit/' . $id . '/');
                exit;
            }
            
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
    
    /**
     * Get current user data
     * 
     * @return array User data pro layout
     */
    private function get_current_user_data() {
        $current_user = wp_get_current_user();
        
        return array(
            'id' => $current_user->ID,
            'email' => $current_user->user_email,
            'display_name' => $current_user->display_name,
        );
    }
    
    /**
     * Get current customer data (dynamicky ze session)
     * 
     * @return array Customer data pro layout
     */
    private function get_current_customer_data() {
        $customer_id = $this->get_session_customer_id();
        
        if (!$customer_id) {
            return $this->load_first_customer();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'saw_customers';
        
        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $customer_id
            ),
            ARRAY_A
        );
        
        if ($customer) {
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Controller: Loaded customer from session: ID ' . $customer['id']);
            }
            
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => $customer['address'] ?? '',
                'logo_url' => $customer['logo_url'] ?? '',
            );
        }
        
        return $this->load_first_customer();
    }
    
    /**
     * Get customer ID ze session nebo user meta
     * 
     * @return int|null Customer ID nebo null
     */
    private function get_session_customer_id() {
        if (isset($_SESSION['saw_current_customer_id'])) {
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Controller: Found customer ID in session: ' . $_SESSION['saw_current_customer_id']);
            }
            return intval($_SESSION['saw_current_customer_id']);
        }
        
        if (is_user_logged_in()) {
            $meta_id = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
            
            if ($meta_id) {
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
     * Load first customer jako fallback
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
     * 
     * @return void
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
     * 
     * @return void
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
     * 
     * @return void
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
     * AJAX: Switch customer - uloží do session + user meta
     * 
     * @return void
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
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log(sprintf(
                    'SAW Customer Switcher: User %d switched to customer %d (%s)',
                    $user_id,
                    $customer_id,
                    $customer->name
                ));
            }
        }
        
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