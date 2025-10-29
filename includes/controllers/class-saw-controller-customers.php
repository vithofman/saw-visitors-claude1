<?php
/**
 * SAW Controller Customers
 * 
 * @package SAW_Visitors
 * @version 4.6.1 ENHANCED
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Customers {
    
    private $customer_model;
    private $account_type_model;
    
    public function __construct() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-customer.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-account-type.php';
        
        $this->customer_model = new SAW_Model_Customer();
        $this->account_type_model = new SAW_Model_Account_Type();
        
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search_customers'));
        add_action('wp_ajax_saw_admin_table_delete', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_saw_get_customer_detail', array($this, 'ajax_get_customer_detail'));
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
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/pages/saw-customers.js')) {
            wp_enqueue_script('saw-customers', SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-customers.js', array('jquery', 'saw-admin-table'), SAW_VISITORS_VERSION, true);
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/css/pages/saw-customer-detail-modal.css')) {
            wp_enqueue_style('saw-customer-detail-modal', SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-customer-detail-modal.css', array(), SAW_VISITORS_VERSION);
        }
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'assets/js/pages/saw-customer-detail-modal.js')) {
            wp_enqueue_script('saw-customer-detail-modal', SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-customer-detail-modal.js', array('jquery'), SAW_VISITORS_VERSION, true);
        }
    }
    
    /**
     * Seznam zákazníků
     * 
     * @return void
     */
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $account_type_filter = isset($_GET['account_type']) ? intval($_GET['account_type']) : '';
        
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'search'       => $search,
            'status'       => $status_filter,
            'account_type' => $account_type_filter,
            'orderby'      => $orderby,
            'order'        => $order,
            'limit'        => $per_page,
            'offset'       => $offset,
        );
        
        $customers = $this->customer_model->get_all($args);
        $total_customers = $this->customer_model->count(array(
            'search'       => $search,
            'status'       => $status_filter,
            'account_type' => $account_type_filter,
        ));
        
        $total_pages = ceil($total_customers / $per_page);
        
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $message_type = isset($_GET['message_type']) ? sanitize_text_field($_GET['message_type']) : '';
        
        $status_options = $this->get_status_options();
        $account_types_for_filter = $this->account_type_model->get_for_select();
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/list.php';
    }
    
    /**
     * Formulář pro vytvoření zákazníka
     * 
     * @return void
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $this->enqueue_customers_assets();
        
        $account_types = $this->account_type_model->get_for_select();
        $status_options = $this->get_status_options();
        $acquisition_options = $this->get_acquisition_options();
        $subscription_options = $this->get_subscription_options();
        $language_options = $this->get_language_options();
        
        $customer = null;
        $is_edit = false;
        $back_url = home_url('/admin/settings/customers/');
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/form.php';
    }
    
    /**
     * Uložení nového zákazníka
     * 
     * @return void
     */
    public function store() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.');
        }
        
        if (!isset($_POST['saw_customer_nonce']) || !wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_save')) {
            wp_die('Neplatný bezpečnostní token.');
        }
        
        $validation = $this->validate_customer_data($_POST);
        if (is_wp_error($validation)) {
            $error_message = urlencode($validation->get_error_message());
            wp_redirect(home_url('/admin/settings/customers/new/?message=' . $error_message . '&message_type=error'));
            exit;
        }
        
        $result = $this->customer_model->create($_POST);
        
        if (is_wp_error($result)) {
            $error_message = urlencode($result->get_error_message());
            wp_redirect(home_url('/admin/settings/customers/new/?message=' . $error_message . '&message_type=error'));
            exit;
        }
        
        wp_redirect(home_url('/admin/settings/customers/?message=' . urlencode('Zákazník vytvořen.') . '&message_type=success'));
        exit;
    }
    
    /**
     * Formulář pro editaci zákazníka
     * 
     * @param int $id ID zákazníka
     * @return void
     */
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.');
        }
        
        $this->enqueue_customers_assets();
        
        $customer = $this->customer_model->get_by_id($id);
        
        if (!$customer) {
            wp_die('Zákazník nenalezen.', 'Chyba 404', array('response' => 404));
        }
        
        $account_types = $this->account_type_model->get_for_select();
        $status_options = $this->get_status_options();
        $acquisition_options = $this->get_acquisition_options();
        $subscription_options = $this->get_subscription_options();
        $language_options = $this->get_language_options();
        
        $is_edit = true;
        $back_url = home_url('/admin/settings/customers/');
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'templates/pages/customers/form.php';
    }
    
    /**
     * Aktualizace zákazníka
     * 
     * @param int $id ID zákazníka
     * @return void
     */
    public function update($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.');
        }
        
        if (!isset($_POST['saw_customer_nonce']) || !wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_customer_save')) {
            wp_die('Neplatný bezpečnostní token.');
        }
        
        $validation = $this->validate_customer_data($_POST, $id);
        if (is_wp_error($validation)) {
            $error_message = urlencode($validation->get_error_message());
            wp_redirect(home_url('/admin/settings/customers/edit/' . $id . '/?message=' . $error_message . '&message_type=error'));
            exit;
        }
        
        $result = $this->customer_model->update($id, $_POST);
        
        if (is_wp_error($result)) {
            $error_message = urlencode($result->get_error_message());
            wp_redirect(home_url('/admin/settings/customers/edit/' . $id . '/?message=' . $error_message . '&message_type=error'));
            exit;
        }
        
        wp_redirect(home_url('/admin/settings/customers/?message=' . urlencode('Zákazník aktualizován.') . '&message_type=success'));
        exit;
    }
    
    /**
     * AJAX: Získat detail zákazníka pro modal
     * 
     * @return void
     */
    public function ajax_get_customer_detail() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění.'));
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Neplatné ID zákazníka.'));
        }
        
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen.'));
        }
        
        $operational_address = $this->customer_model->format_address($customer, 'operational');
        $billing_address = $this->customer_model->format_address($customer, 'billing');
        
        $status_options = $this->get_status_options();
        $acquisition_options = $this->get_acquisition_options();
        $subscription_options = $this->get_subscription_options();
        
        $response = array(
            'id'                        => $customer['id'],
            'name'                      => $customer['name'],
            'ico'                       => $customer['ico'],
            'dic'                       => $customer['dic'],
            'operational_address'       => $operational_address,
            'billing_address'           => $billing_address,
            'has_billing_address'       => $customer['has_billing_address'],
            'contact_person'            => $customer['contact_person'],
            'contact_position'          => $customer['contact_position'],
            'contact_email'             => $customer['contact_email'],
            'contact_phone'             => $customer['contact_phone'],
            'website'                   => $customer['website'],
            'status'                    => $customer['status'],
            'status_label'              => $status_options[$customer['status']]['label'] ?? '',
            'status_color'              => $status_options[$customer['status']]['color'] ?? '#6b7280',
            'account_type_display_name' => $customer['account_type_display_name'] ?? 'Bez typu',
            'account_type_color'        => $customer['account_type_color'] ?? '#6b7280',
            'acquisition_source'        => $acquisition_options[$customer['acquisition_source']] ?? '',
            'subscription_type'         => $subscription_options[$customer['subscription_type']] ?? '',
            'last_payment_date'         => $customer['last_payment_date'] ?? '',
            'primary_color'             => $customer['primary_color'],
            'admin_language_default'    => $customer['admin_language_default'],
            'notes'                     => $customer['notes'],
            'logo_url_full'             => $customer['logo_url_full'],
            'created_at'                => $customer['created_at'],
            'updated_at'                => $customer['updated_at'],
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: Smazat zákazníka
     * 
     * @return void
     */
    public function ajax_delete_customer() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění.'));
        }
        
        $customer_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Neplatné ID zákazníka.'));
        }
        
        $result = $this->customer_model->delete($customer_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Zákazník smazán.'));
    }
    
    /**
     * AJAX: Vyhledávání zákazníků
     * 
     * @return void
     */
    public function ajax_search_customers() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění.'));
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $account_type = isset($_POST['account_type']) ? intval($_POST['account_type']) : '';
        
        $customers = $this->customer_model->get_all(array(
            'search'       => $search,
            'status'       => $status,
            'account_type' => $account_type,
            'limit'        => 50,
        ));
        
        wp_send_json_success($customers);
    }
    
    /**
     * Validace dat zákazníka
     * 
     * @param array    $data POST data
     * @param int|null $id   ID pro update
     * @return bool|WP_Error
     */
    private function validate_customer_data($data, $id = null) {
        if (empty($data['name'])) {
            return new WP_Error('name_required', 'Název zákazníka je povinný.');
        }
        
        if (!empty($data['contact_email']) && !is_email($data['contact_email'])) {
            return new WP_Error('email_invalid', 'Neplatný formát emailu.');
        }
        
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            return new WP_Error('url_invalid', 'Neplatný formát URL.');
        }
        
        if (!empty($data['account_type_id'])) {
            $account_type = $this->account_type_model->get_by_id(intval($data['account_type_id']));
            if (!$account_type) {
                return new WP_Error('account_type_invalid', 'Vybraný typ účtu neexistuje.');
            }
        }
        
        return true;
    }
    
    /**
     * Možnosti statusu
     * 
     * @return array
     */
    private function get_status_options() {
        return array(
            'potential' => array('label' => 'Potenciální', 'color' => '#f59e0b', 'icon' => '⏳'),
            'active'    => array('label' => 'Aktivní',     'color' => '#10b981', 'icon' => '✅'),
            'inactive'  => array('label' => 'Neaktivní',   'color' => '#ef4444', 'icon' => '❌'),
        );
    }
    
    /**
     * Možnosti zdroje akvizice
     * 
     * @return array
     */
    private function get_acquisition_options() {
        return array(
            'recommendation'   => 'Doporučení',
            'advertising'      => 'Reklama',
            'web'              => 'Webový formulář',
            'personal_contact' => 'Osobní kontakt',
            'other'            => 'Jiné',
        );
    }
    
    /**
     * Možnosti typu předplatného
     * 
     * @return array
     */
    private function get_subscription_options() {
        return array(
            'one_time' => 'Jednorázové',
            'monthly'  => 'Měsíční',
            'yearly'   => 'Roční',
        );
    }
    
    /**
     * Možnosti jazyka
     * 
     * @return array
     */
    private function get_language_options() {
        return array(
            'cs' => 'Čeština 🇨🇿',
            'en' => 'English 🇬🇧',
        );
    }
}