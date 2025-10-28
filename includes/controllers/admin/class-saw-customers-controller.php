<?php
/**
 * Customers Controller - FIXED CSS LOADING
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
    }
    
    /**
     * ✅ NOVÁ METODA: Ruční načtení CSS a JS
     */
    private function enqueue_customers_assets() {
        // CSS
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/saw-customers.css')) {
            wp_enqueue_style(
                'saw-visitors-customers',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-customers.css',
                array(),
                SAW_VISITORS_VERSION
            );
        }
        
        // JS (pokud existuje)
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/saw-customers.js')) {
            wp_enqueue_script(
                'saw-visitors-customers',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-customers.js',
                array('jquery'),
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
        
        // ✅ KRITICKÉ: Načíst CSS TADY!
        $this->enqueue_customers_assets();
        
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $customers = [];
        $total_customers = 0;
        
        if ($this->customer_model) {
            $customers = $this->customer_model->get_all($page, $per_page, $search);
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
            $data = compact('customers', 'total_customers', 'total_pages', 'page', 'search', 'message', 'message_type');
            
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