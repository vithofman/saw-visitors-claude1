<?php
/**
 * Customers Controller - FIXED WITH DEBUG
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
        
        // 🔍 DEBUG: Log že se controller inicializoval
        error_log('SAW Customers Controller: Initialized, AJAX handler registered');
    }
    
    /**
     * ✅ FIXED: Načíst VŠECHNY potřebné CSS a JS PŘÍMO
     */
    private function enqueue_customers_assets() {
        // 1. Tables CSS (MUSÍ BÝT PRVNÍ!)
        wp_enqueue_style(
            'saw-visitors-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-tables.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        // 2. Customers CSS (specifické styly)
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/saw-customers.css')) {
            wp_enqueue_style(
                'saw-visitors-customers',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-customers.css',
                array('saw-visitors-tables'),
                SAW_VISITORS_VERSION
            );
        }
        
        // 3. Customers JS (pokud existuje)
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/saw-customers.js')) {
            wp_enqueue_script(
                'saw-visitors-customers',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-customers.js',
                array('jquery'),
                SAW_VISITORS_VERSION,
                true
            );
        }
        
        // 4. ✅ AJAX JS (HLAVNÍ!)
        wp_enqueue_script(
            'saw-visitors-customers-ajax',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-customers-ajax.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // 5. Localize script
        wp_localize_script(
            'saw-visitors-customers-ajax',
            'sawCustomersAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('saw_customers_ajax_nonce')
            )
        );
        
        // 🔍 DEBUG: Log že se assets načetly
        error_log('SAW Customers Controller: Assets enqueued');
    }
    
    /**
     * List customers
     */
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // ✅ KRITICKÉ: Načíst CSS TADY!
        $this->enqueue_customers_assets();
        
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
        
        // Validate orderby
        $allowed_orderby = array('name', 'ico', 'created_at', 'id');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }
        
        // Validate order
        $order = strtoupper($order);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $customers = array();
        $total_customers = 0;
        
        if ($this->customer_model) {
            // ✅ POUŽIJ ARGS ARRAY (pro starý model)
            $customers = $this->customer_model->get_all(array(
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'limit' => $per_page,
                'offset' => ($page - 1) * $per_page
            ));
            
            $total_customers = $this->customer_model->count($search);
        }
        
        $total_pages = $total_customers > 0 ? ceil($total_customers / $per_page) : 1;
        
        $message = '';
        $message_type = '';
        
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'created':
                    $message = 'Zákazník byl úspěšně vytvořen.';
                    $message_type = 'success';
                    break;
                case 'updated':
                    $message = 'Zákazník byl úspěšně aktualizován.';
                    $message_type = 'success';
                    break;
                case 'deleted':
                    $message = 'Zákazník byl úspěšně smazán.';
                    $message_type = 'success';
                    break;
                case 'error':
                    $message = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : 'Došlo k chybě.';
                    $message_type = 'error';
                    break;
            }
        }
        
        $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-list.php';
        
        if (file_exists($template_file)) {
            $data = compact('customers', 'total_customers', 'total_pages', 'page', 'search', 'orderby', 'order', 'message', 'message_type');
            
            ob_start();
            extract($data);
            include $template_file;
            $content = ob_get_clean();
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $customer = $this->get_current_customer_data();
                $layout->render($content, 'Správa zákazníků', 'customers', $user, $customer);
            } else {
                echo $content;
            }
        } else {
            wp_die('Template nenalezen: ' . $template_file);
        }
    }
    
    /**
     * ✅ FIXED: AJAX Search s kompatibilitou pro starý model
     */
    public function ajax_search_customers() {
        // 🔍 DEBUG: Log že AJAX handler byl zavolán
        error_log('SAW AJAX: ajax_search_customers called');
        error_log('SAW AJAX: POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!check_ajax_referer('saw_customers_ajax_nonce', 'nonce', false)) {
            error_log('SAW AJAX Error: Invalid nonce');
            error_log('SAW AJAX Error: Expected nonce for action: saw_customers_ajax_nonce');
            error_log('SAW AJAX Error: Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NONE'));
            wp_send_json_error(array('message' => 'Neplatný bezpečnostní token.'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('SAW AJAX Error: No permissions for user ID: ' . get_current_user_id());
            wp_send_json_error(array('message' => 'Nemáte oprávnění.'));
            return;
        }
        
        try {
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'name';
            $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';
            $per_page = 20;
            
            // Validate orderby
            $allowed_orderby = array('name', 'ico', 'created_at', 'id');
            if (!in_array($orderby, $allowed_orderby)) {
                $orderby = 'name';
            }
            
            // Validate order
            $order = strtoupper($order);
            if (!in_array($order, array('ASC', 'DESC'))) {
                $order = 'ASC';
            }
            
            error_log('SAW AJAX: search=' . $search . ' page=' . $page . ' orderby=' . $orderby . ' order=' . $order);
            
            if (!$this->customer_model) {
                error_log('SAW AJAX Error: Customer model not initialized');
                wp_send_json_error(array('message' => 'Model zákazníků není inicializován.'));
                return;
            }
            
            // ✅ POUŽIJ ARGS ARRAY (pro starý model)
            $customers = $this->customer_model->get_all(array(
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'limit' => $per_page,
                'offset' => ($page - 1) * $per_page
            ));
            
            $total_customers = $this->customer_model->count($search);
            $total_pages = $total_customers > 0 ? ceil($total_customers / $per_page) : 1;
            
            error_log('SAW AJAX: Found ' . count($customers) . ' customers (total: ' . $total_customers . ')');
            error_log('SAW AJAX: Sending success response');
            
            wp_send_json_success(array(
                'customers' => $customers,
                'total_customers' => $total_customers,
                'total_pages' => $total_pages,
                'current_page' => $page
            ));
            
        } catch (Exception $e) {
            error_log('SAW AJAX Exception: ' . $e->getMessage());
            error_log('SAW AJAX Exception trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Došlo k chybě: ' . $e->getMessage()));
        }
    }
    
    /**
     * Create new customer
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // ✅ KRITICKÉ: Načíst CSS TADY!
        $this->enqueue_customers_assets();
        
        // POST handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            if ($this->customer_model) {
                $result = $this->customer_model->create($_POST);
                
                if (is_wp_error($result)) {
                    wp_redirect('/admin/settings/customers/?message=error&error_msg=' . urlencode($result->get_error_message()));
                    exit;
                }
                
                wp_redirect('/admin/settings/customers/?message=created');
                exit;
            }
        }
        
        // Render form
        $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
        
        if (file_exists($template_file)) {
            ob_start();
            
            // CRITICAL: Set variables for template
            $is_edit = false;
            $customer = array(
                'name' => '',
                'ico' => '',
                'address' => '',
                'notes' => '',
                'primary_color' => '#1e40af',
                'logo_url_full' => ''
            );
            
            include $template_file;
            $content = ob_get_clean();
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $current_customer = $this->get_current_customer_data();
                $layout->render($content, 'Nový zákazník', 'customers', $user, $current_customer);
            } else {
                echo $content;
            }
        } else {
            wp_die('Template nenalezen: ' . $template_file);
        }
    }
    
    /**
     * Edit customer
     */
    public function edit($customer_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // ✅ KRITICKÉ: Načíst CSS TADY!
        $this->enqueue_customers_assets();
        
        $customer = null;
        if ($this->customer_model) {
            $customer = $this->customer_model->get_by_id($customer_id);
        }
        
        if (!$customer) {
            wp_die('Zákazník nenalezen.', 'Chyba', array('response' => 404));
        }
        
        // POST handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_form')) {
                wp_die('Bezpečnostní kontrola selhala.');
            }
            
            if ($this->customer_model) {
                $result = $this->customer_model->update($customer_id, $_POST);
                
                if (is_wp_error($result)) {
                    wp_redirect('/admin/settings/customers/?message=error&error_msg=' . urlencode($result->get_error_message()));
                    exit;
                }
                
                wp_redirect('/admin/settings/customers/?message=updated');
                exit;
            }
        }
        
        // Render form
        $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
        
        if (file_exists($template_file)) {
            ob_start();
            
            // CRITICAL: Set variables for template
            $is_edit = true;
            
            include $template_file;
            $content = ob_get_clean();
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $current_customer = $this->get_current_customer_data();
                $layout->render($content, 'Upravit zákazníka', 'customers', $user, $current_customer);
            } else {
                echo $content;
            }
        } else {
            wp_die('Template nenalezen: ' . $template_file);
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
     * Get current customer data
     */
    private function get_current_customer_data() {
        return array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'address' => 'Praha 1, Hlavní 123',
        );
    }
}