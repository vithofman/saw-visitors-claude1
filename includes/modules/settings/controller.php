<?php
/**
 * Settings Module Controller
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Settings_Controller 
{
    protected $config;
    protected $model;
    private $file_uploader;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/settings/';
        
        $this->config = require $module_path . 'config.php';
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Settings_Model();
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        add_action('wp_ajax_saw_delete_settings_logo', array($this, 'ajax_delete_logo'));
        add_action('wp_ajax_saw_toggle_dark_mode', array($this, 'ajax_toggle_dark_mode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
        SAW_Asset_Loader::enqueue_module('settings');
    }
    
    public function index() {
        $role = $this->get_current_role();
        
        // Základní záložka je dostupná všem
        // Admin záložka jen pro roli 'admin'
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // POST může dělat jen admin
            if ($role !== 'admin') {
                wp_die(
                    'Nemáte oprávnění upravovat nastavení firmy.',
                    'Přístup odepřen',
                    array('response' => 403)
                );
            }
            
            $customer_id = SAW_Context::get_customer_id();
            if (!$customer_id) {
                wp_die(
                    'Nebylo možné určit vašeho zákazníka.',
                    'Chyba konfigurace',
                    array('response' => 500)
                );
            }
            
            $this->handle_save($customer_id);
            return;
        }
        
        $view_data = array(
            'role' => $role,
            'icon' => $this->config['icon'],
            'flash' => $this->get_flash(),
            'customer' => null,
        );
        
        // Načtení dat zákazníka jen pro admina
        if ($role === 'admin') {
            $customer_id = SAW_Context::get_customer_id();
            
            if ($customer_id) {
                $customer = $this->model->get_customer($customer_id);
                $view_data['customer'] = $customer;
            }
        }
        
        $this->render_view($view_data);
    }
    
    private function handle_save($customer_id) {
        check_admin_referer('saw_settings_save');
        
        $data = array(
            'name' => $_POST['name'] ?? '',
            'ico' => $_POST['ico'] ?? '',
            'dic' => $_POST['dic'] ?? '',
        );
        
        if (empty($data['name'])) {
            $this->set_flash('Název firmy je povinný', 'error');
            wp_redirect(home_url('/admin/settings'));
            exit;
        }
        
        if ($this->file_uploader->should_remove_file('logo')) {
            $this->model->delete_logo($customer_id);
            $data['logo_url'] = null;
        }
        elseif (!empty($_FILES['logo']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload_result)) {
                $this->set_flash($upload_result->get_error_message(), 'error');
                wp_redirect(home_url('/admin/settings'));
                exit;
            }
            
            $data['logo_url'] = $upload_result['url'];
        }
        
        $result = $this->model->update_customer($customer_id, $data);
        
        if (is_wp_error($result)) {
            $this->set_flash($result->get_error_message(), 'error');
        } else {
            $this->set_flash('Údaje o zákazníkovi byly úspěšně uloženy', 'success');
        }
        
        wp_redirect(home_url('/admin/settings'));
        exit;
    }
    
    public function ajax_delete_logo() {
        saw_verify_ajax_unified();
        
        $role = $this->get_current_role();
        
        if ($role !== 'admin') {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Nebylo možné určit zákazníka'));
            return;
        }
        
        $this->model->delete_logo($customer_id);
        
        wp_send_json_success(array('message' => 'Logo bylo smazáno'));
    }
    
    /**
     * AJAX handler for dark mode toggle
     *
     * @since 1.0.0
     */
    public function ajax_toggle_dark_mode() {
        saw_verify_ajax_unified();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Uživatel není přihlášen'));
            return;
        }
        
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
        
        update_user_meta($user_id, 'saw_dark_mode', $enabled ? '1' : '0');
        
        wp_send_json_success(array(
            'message' => $enabled ? 'Tmavý režim zapnut' : 'Tmavý režim vypnut',
            'enabled' => $enabled
        ));
    }
    
    private function render_view($view_data) {
        if (!class_exists('SAW_App_Layout')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-layout.php';
        }
        
        ob_start();
        
        extract($view_data);
        
        $template_path = $this->config['path'] . 'view.php';
        if (file_exists($template_path)) {
            require $template_path;
        }
        
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, 'Nastavení', 'settings');
    }
    
    private function get_current_role() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        return null;
    }
    
    private function set_flash($message, $type = 'success') {
        if (!session_id()) {
            session_start();
        }
        $_SESSION['saw_flash'] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    private function get_flash() {
        if (!session_id()) {
            session_start();
        }
        
        if (isset($_SESSION['saw_flash'])) {
            $flash = $_SESSION['saw_flash'];
            unset($_SESSION['saw_flash']);
            return $flash;
        }
        
        return null;
    }
}
