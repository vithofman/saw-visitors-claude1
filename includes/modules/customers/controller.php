<?php
/**
 * Customers Module Controller
 *
 * Main controller for the Customers module with sidebar support.
 * Handles CRUD operations, file uploads, AJAX requests, and sidebar context.
 *
 * Features:
 * - List view with search, filtering, sorting, pagination (inherited)
 * - Create/Edit forms in sidebar (inherited)
 * - Detail view in sidebar (inherited)
 * - AJAX sidebar loading (inherited from Base Controller)
 * - AJAX detail modal (backward compatible)
 * - AJAX search and delete
 * - Dependency validation (branches, users, visits, invitations)
 * - Comprehensive cache invalidation
 * - File upload handling (logo)
 * - Related data support (branches)
 *
 * @package SAW_Visitors
 * @version 12.2.0 - FÁZE 3: index() uses render_list_view()
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customers Module Controller Class
 *
 * Extends base controller and uses AJAX handlers trait.
 * Manages all customer-related operations including CRUD, file uploads, AJAX, and sidebar.
 *
 * @since 4.6.1
 */
class SAW_Module_Customers_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * File uploader instance
     *
     * @since 3.1.0
     * @var SAW_File_Uploader
     */
    private $file_uploader;
    
    /**
     * Constructor
     *
     * Initializes controller, loads config, model, components,
     * and registers AJAX handlers.
     *
     * @since 4.6.1
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/customers/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        add_action('wp_ajax_saw_get_customers_detail', array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_customers', array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_customers', array($this, 'ajax_load_sidebar'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Display list page with optional sidebar
     *
     * Now uses universal render_list_view() from Base Controller.
     *
     * @since 12.2.0 - FÁZE 3
     * @return void
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * AJAX delete handler - OVERRIDE
     *
     * Override trait method to use config-based permissions instead of SAW_Permissions.
     * Uses can('delete') which checks capabilities from config.
     *
     * @since 11.2.1
     * @return void
     */
    public function ajax_delete() {
        saw_verify_ajax_unified();
        
        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
            return;
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(array('message' => $before_delete_result->get_error_message()));
            return;
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error(array('message' => 'Nelze smazat zákazníka'));
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $this->after_delete($id);
        
        wp_send_json_success(array('message' => 'Zákazník byl úspěšně smazán'));
    }
    
    /**
     * Format data for detail view
     *
     * Adds computed fields and formatting for detail display.
     *
     * @since 12.0.0 - Config-driven lookups
     * @param array $item Raw item data
     * @return array Formatted item data
     */
    protected function format_detail_data($item) {
        $account_types = $this->load_lookup_from_config('account_types');
        
        if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
            $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
        } else {
            $item['account_type_display'] = 'Nezadáno';
        }
        
        if (!empty($item['logo_url'])) {
            if (strpos($item['logo_url'], 'http') === 0) {
            } else {
                $upload_dir = wp_upload_dir();
                $item['logo_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['logo_url'], '/');
            }
        }
        
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        $item['status_label'] = $this->get_status_label($item['status'] ?? '');
        
        return $item;
    }
    
    /**
     * Format row data for list table
     *
     * @since 8.0.0
     * @param array $item Raw item data
     * @param array $account_types Account types lookup
     * @return array Formatted row data
     */
    private function format_row_data($item, $account_types) {
        if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
            $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
        } else {
            $item['account_type_display'] = '-';
        }
        
        if (!empty($item['logo_path'])) {
            $upload_dir = wp_upload_dir();
            $item['logo_url'] = $upload_dir['baseurl'] . '/' . $item['logo_path'];
        }
        
        return $item;
    }
    
    /**
     * Get status label
     *
     * @since 8.0.0
     * @param string $status Status value
     * @return string Status label
     */
    private function get_status_label($status) {
        $labels = array(
            'potential' => 'Potenciální',
            'active' => 'Aktivní',
            'inactive' => 'Neaktivní',
        );
        
        return $labels[$status] ?? 'Neznámý';
    }
    
    /**
     * Prepare form data from POST
     * 
     * Override base controller method to handle file uploads and custom fields.
     * 
     * @since 11.3.0
     * @param array $post POST data
     * @return array|WP_Error Prepared data or error
     */
    protected function prepare_form_data($post) {
        $data = array(
            'name' => sanitize_text_field($post['name'] ?? ''),
            'ico' => sanitize_text_field($post['ico'] ?? ''),
            'dic' => sanitize_text_field($post['dic'] ?? ''),
            
            'address_street' => sanitize_text_field($post['address_street'] ?? ''),
            'address_number' => sanitize_text_field($post['address_number'] ?? ''),
            'address_city' => sanitize_text_field($post['address_city'] ?? ''),
            'address_zip' => sanitize_text_field($post['address_zip'] ?? ''),
            'address_country' => sanitize_text_field($post['address_country'] ?? 'Česká republika'),
            
            'billing_address_street' => sanitize_text_field($post['billing_address_street'] ?? ''),
            'billing_address_number' => sanitize_text_field($post['billing_address_number'] ?? ''),
            'billing_address_city' => sanitize_text_field($post['billing_address_city'] ?? ''),
            'billing_address_zip' => sanitize_text_field($post['billing_address_zip'] ?? ''),
            'billing_address_country' => sanitize_text_field($post['billing_address_country'] ?? ''),
            
            'contact_person' => sanitize_text_field($post['contact_person'] ?? ''),
            'contact_email' => sanitize_email($post['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($post['contact_phone'] ?? ''),
            
            'website' => esc_url_raw($post['website'] ?? ''),
            'status' => sanitize_text_field($post['status'] ?? 'potential'),
            'account_type_id' => !empty($post['account_type_id']) ? intval($post['account_type_id']) : null,
            'notes' => sanitize_textarea_field($post['notes'] ?? ''),
        );
        
        if (!empty($_FILES['logo']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload_result)) {
                return $upload_result;
            }
            
            if (isset($post['id'])) {
                $old_customer = $this->model->get_by_id($post['id']);
                if (!empty($old_customer['logo_url'])) {
                    $this->file_uploader->delete($old_customer['logo_url']);
                }
            }
            
            $data['logo_url'] = $upload_result['url'];
        }
        
        return $data;
    }
    
    /**
     * After save hook - cache invalidation
     * 
     * @since 11.2.0
     * @param int $id Item ID
     */
    protected function after_save($id) {
        $this->invalidate_caches();
    }
    
    /**
     * Before delete hook - dependency validation
     * 
     * Database has CASCADE delete, so we just log dependencies and allow deletion.
     * 
     * @since 11.2.2
     * @param int $id Customer ID
     * @return bool Always true (CASCADE delete handles dependencies)
     */
    protected function before_delete($id) {
        global $wpdb;
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            $branches = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d", $id
            ));
            $users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d", $id
            ));
            $departments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE customer_id = %d", $id
            ));
        }
        
        return true;
    }
    
    /**
     * After delete hook - cache invalidation
     * 
     * @since 11.2.1
     * @param int $id Customer ID
     */
    protected function after_delete($id) {
        $this->invalidate_caches();
    }
    
    /**
     * Invalidate all caches after data changes
     *
     * @since 8.0.0
     * @return void
     */
    private function invalidate_caches() {
        if (function_exists('saw_clear_cache')) {
            saw_clear_cache('customers');
        }
    }
    
    /**
     * Enqueue module assets
     *
     * @since 11.3.0
     * @return void
     */
    protected function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
        SAW_Asset_Loader::enqueue_module('customers');
    }
}