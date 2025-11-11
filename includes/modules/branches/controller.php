<?php
/**
 * Branches Module Controller
 *
 * FINAL v13.6.0 - COMPLETE
 * All methods aligned with customers module
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     13.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;

    private $file_uploader;

    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_get_branches_detail', array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_branches', array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_branches', array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_branches', array($this, 'ajax_load_sidebar'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Display list page with optional sidebar
     */
    public function index() {
        $this->render_list_view();
    }

    /**
     * AJAX delete handler - OVERRIDE
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
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
            wp_send_json_error(array('message' => 'Pobočka nenalezena'));
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(array('message' => $before_delete_result->get_error_message()));
            return;
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error(array('message' => 'Nelze smazat pobočku'));
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $this->after_delete($id);
        
        wp_send_json_success(array('message' => 'Pobočka byla úspěšně smazána'));
    }

    /**
     * Prepare form data from POST
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Customer ID z contextu
        $customer_id = $this->get_current_customer_id();
        if (empty($customer_id)) {
            return new WP_Error('no_customer_context', 'Není vybrán žádný aktivní zákazník.');
        }
        $data['customer_id'] = $customer_id;
        
        // Text fields
        $text_fields = array('name', 'code', 'street', 'city', 'postal_code', 'country', 'phone', 'email');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $value = sanitize_text_field($post[$field]);
                $data[$field] = !empty($value) ? $value : null;
            }
        }
        
        // Textarea fields
        $textarea_fields = array('notes', 'description');
        foreach ($textarea_fields as $field) {
            if (isset($post[$field])) {
                $value = sanitize_textarea_field($post[$field]);
                $data[$field] = !empty($value) ? $value : null;
            }
        }
        
        // Boolean fields
        $data['is_headquarters'] = !empty($post['is_headquarters']) ? 1 : 0;
        $data['is_active'] = !empty($post['is_active']) ? 1 : 0;
        
        // Numeric fields
        $data['sort_order'] = isset($post['sort_order']) ? intval($post['sort_order']) : 10;
        
        // File upload
        if (!empty($_FILES['image_url']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['image_url'], 'branches');
            
            if (is_wp_error($upload_result)) {
                return $upload_result;
            }
            
            if (isset($post['id'])) {
                $old_item = $this->model->get_by_id($post['id'], true);
                if (!empty($old_item['image_url'])) {
                    $this->file_uploader->delete($old_item['image_url']);
                }
            }
            
            $data['image_url'] = $upload_result['url'];
        }
        
        return $data;
    }

    /**
     * Before Save Hook
     */
    protected function before_save($data) {
        if (!empty($data['is_headquarters'])) {
            $customer_id = $data['customer_id'];
            
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'saw_branches',
                array('is_headquarters' => 0),
                array('customer_id' => $customer_id),
                array('%d'),
                array('%d')
            );
        }
        
        return $data;
    }

    /**
     * After save hook - cache invalidation
     */
    protected function after_save($id) {
        $this->invalidate_caches();
    }

    /**
     * Before Delete Hook
     */
    protected function before_delete($id) {
        $item = $this->model->get_by_id($id);
        
        if ($item && $item['is_headquarters']) {
            $count = $this->model->get_headquarters_count($item['customer_id']);
            
            if ($count <= 1) {
                return new WP_Error(
                    'delete_last_hq', 
                    'Nelze smazat poslední sídlo firmy. Nejprve označte jako sídlo jinou pobočku.'
                );
            }
        }
        
        return true;
    }
    
    /**
     * After delete hook - cache invalidation
     */
    protected function after_delete($id) {
        $this->invalidate_caches();
    }
    
    /**
     * Format data for detail view
     */
    protected function format_detail_data($item) {
        // Datumy
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        // Full address
        $address_parts = array();
        if (!empty($item['street'])) $address_parts[] = $item['street'];
        if (!empty($item['city']))   $address_parts[] = $item['city'];
        if (!empty($item['postal_code'])) $address_parts[] = $item['postal_code'];
        $item['full_address'] = implode(', ', $address_parts);
        
        // Map link
        if (!empty($item['full_address'])) {
            $item['map_link'] = 'https://www.google.com/maps/search/' . urlencode($item['full_address']);
        } else {
            $item['map_link'] = null;
        }
        
        // Image URL normalizace
        if (!empty($item['image_url'])) {
            if (strpos($item['image_url'], 'http') !== 0) {
                $upload_dir = wp_upload_dir();
                $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_url'], '/');
            }
        }
        
        return $item;
    }

    /**
     * Invalidate all caches after data changes
     */
    private function invalidate_caches() {
        if (function_exists('saw_clear_cache')) {
            saw_clear_cache('branches');
        }
    }

    /**
     * Enqueue module assets
     */
    protected function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
    }
    
    /**
     * Get current customer ID helper
     */
    private function get_current_customer_id() {
        if (class_exists('SAW_Context')) {
            $context_id = SAW_Context::get_customer_id();
            if ($context_id) {
                return $context_id;
            }
        }

        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            $saw_user = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                    get_current_user_id()
                ),
                ARRAY_A
            );
            if ($saw_user && $saw_user['customer_id']) {
                return intval($saw_user['customer_id']);
            }
        }
        
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_customer_id();
        }

        return null;
    }
}