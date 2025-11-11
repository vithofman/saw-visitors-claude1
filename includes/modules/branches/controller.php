<?php
/**
 * Branches Module Controller - FINAL COMPLETE VERSION
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     14.0.0 - FINAL
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

    /**
     * File uploader instance
     * 
     * @var SAW_File_Uploader
     */
    private $file_uploader;

    /**
     * Constructor
     * 
     * Initializes controller, loads config, model, and components
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
     * 
     * Uses universal render_list_view() from Base Controller
     */
    public function index() {
        $this->render_list_view();
    }

    /**
     * AJAX delete handler - OVERRIDE
     * 
     * Handles AJAX delete requests with permission checking
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Chybí ID'));
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array('message' => 'Pobočka byla smazána'));
    }

    /**
     * Handle create POST
     * 
     * Processes form submission for creating new branch
     */
    protected function handle_create_post() {
        check_admin_referer('saw_create_branches', '_wpnonce');
        
        $data = $this->prepare_form_data($_POST);
        
        // Validate
        $validation = $this->model->validate($data);
        if (is_wp_error($validation)) {
            $this->set_flash($validation->get_error_message(), 'error');
            wp_redirect(home_url('/admin/branches/create'));
            exit;
        }
        
        // Create
        $id = $this->model->create($data);
        
        if (is_wp_error($id)) {
            $this->set_flash('Chyba při vytváření pobočky: ' . $id->get_error_message(), 'error');
            wp_redirect(home_url('/admin/branches/create'));
            exit;
        }
        
        $this->set_flash('Pobočka byla vytvořena', 'success');
        wp_redirect(home_url('/admin/branches/' . $id . '/'));
        exit;
    }

    /**
     * Handle edit POST
     * 
     * Processes form submission for editing existing branch
     * 
     * @param int $id Branch ID
     */
    protected function handle_edit_post($id) {
        check_admin_referer('saw_edit_branches', '_wpnonce');
        
        $data = $this->prepare_form_data($_POST);
        
        // Validate
        $validation = $this->model->validate($data, $id);
        if (is_wp_error($validation)) {
            $this->set_flash($validation->get_error_message(), 'error');
            wp_redirect(home_url('/admin/branches/' . $id . '/edit'));
            exit;
        }
        
        // Update
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            $this->set_flash('Chyba při ukládání: ' . $result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/branches/' . $id . '/edit'));
            exit;
        }
        
        $this->set_flash('Pobočka byla aktualizována', 'success');
        wp_redirect(home_url('/admin/branches/' . $id . '/'));
        exit;
    }

    /**
     * Prepare form data with customer_id and file upload
     * 
     * Processes POST data, sanitizes fields, auto-adds customer_id,
     * and handles file uploads
     * 
     * @param array $post Raw POST data
     * @return array Sanitized and processed data
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Process all fields from config
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $value = $post[$field_name];
                
                if (isset($field_config['sanitize']) && is_callable($field_config['sanitize'])) {
                    $value = call_user_func($field_config['sanitize'], $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                $data[$field_name] = $value;
            } elseif ($field_config['type'] === 'boolean') {
                $data[$field_name] = 0;
            }
        }
        
        // Auto-add customer_id from context if not set
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        // Handle file upload
        if (!empty($_FILES['image_url']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['image_url'], 'branches');
            
            if (!is_wp_error($upload_result)) {
                $data['image_url'] = $upload_result['url'];
                
                // Delete old image if editing
                if (!empty($post['id'])) {
                    $old_item = $this->model->get_by_id($post['id']);
                    if (!empty($old_item['image_url'])) {
                        $this->file_uploader->delete($old_item['image_url']);
                    }
                }
            }
        }
        
        return $data;
    }

    /**
     * Format data for detail view
     * 
     * Formats dates, URLs, and labels for display
     * 
     * @param array $item Raw item data
     * @return array Formatted item data
     */
    protected function format_detail_data($item) {
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        // Format image URL
        if (!empty($item['image_url']) && strpos($item['image_url'], 'http') !== 0) {
            $upload_dir = wp_upload_dir();
            $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_url'], '/');
        }
        
        // Format headquarters status
        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        
        // Format active status
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        
        return $item;
    }

    /**
     * Before delete validation
     * 
     * Checks if branch has related records that prevent deletion
     * 
     * @param int $id Branch ID
     * @return bool True to allow delete, false to prevent
     */
    protected function before_delete($id) {
        global $wpdb;
        
        // Check if branch has departments
        $departments_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE branch_id = %d",
            $id
        ));
        
        if ($departments_count > 0) {
            $this->set_flash(
                sprintf('Nelze smazat pobočku - obsahuje %d oddělení', $departments_count),
                'error'
            );
            return false;
        }
        
        return true;
    }

    /**
     * After save hook - cache invalidation
     * 
     * Called after successful create or update
     * 
     * @param int $id Branch ID
     */
    protected function after_save($id) {
        $this->invalidate_caches();
    }

    /**
     * Invalidate all module caches
     * 
     * Clears all transients related to branches
     */
    private function invalidate_caches() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->options,
            $wpdb->esc_like('_transient_branches_') . '%',
            $wpdb->esc_like('_transient_timeout_branches_') . '%'
        ));
    }

    /**
     * Enqueue module assets
     * 
     * Loads CSS and JavaScript for branches module
     */
    protected function enqueue_assets() {
    // Module styles/scripts
    if (class_exists('SAW_Asset_Manager')) {
        SAW_Asset_Manager::enqueue_module('branches');
    }
    
    // ✅ PŘIDEJ FILE UPLOAD ASSETS
    wp_enqueue_style(
        'saw-file-upload',
        SAW_VISITORS_PLUGIN_URL . 'includes/components/file-upload/file-upload.css',
        array(),
        SAW_VISITORS_VERSION
    );
    
    wp_enqueue_script(
        'saw-file-upload',
        SAW_VISITORS_PLUGIN_URL . 'includes/components/file-upload/file-upload.js',
        array('jquery'),
        SAW_VISITORS_VERSION,
        true
    );
}
}