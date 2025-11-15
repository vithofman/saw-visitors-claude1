<?php
/**
 * Visits Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
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

class SAW_Module_Visits_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * Constructor - Initialize controller with config and model
     * 
     * Loads module configuration and initializes model.
     * AJAX handlers are registered via universal system.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visits_Model($this->config);
    }
    
    /**
     * Display list of all companies with optional sidebar
     * 
     * Uses universal render_list_view() from Base Controller.
     * Automatically handles sidebar modes (detail, create, edit).
     * 
     * OVERRIDE: Explicitly adds branch_id filter from context
     * 
     * @since 1.0.0
     * @return void
     */
    public function index() {
        // Get branch ID from context BEFORE calling render_list_view
        $branch_id = SAW_Context::get_branch_id();
        
        // Get list data with explicit branch filter
        $filters = array();
        if ($branch_id) {
            $filters['branch_id'] = $branch_id;
        }
        
        // Pass filters to render_list_view
        $list_data = $this->get_list_data($filters);
        
        $this->render_list_view($list_data);
    }
    
    /**
     * AJAX: Get hosts by branch
     * 
     * Returns list of users (admin, super_manager, manager) for selected branch.
     * Used for dynamic loading of host checkboxes in visit form.
     * 
     * @since 1.0.0
     * @return void (JSON response)
     */
    public function ajax_get_hosts_by_branch() {
        // Verify nonce
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        global $wpdb;
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
            return;
        }
        
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.role, u.email
             FROM %i u
             WHERE u.customer_id = %d 
             AND u.branch_id = %d
             AND u.role IN ('admin', 'super_manager', 'manager')
             AND u.is_active = 1
             ORDER BY u.last_name, u.first_name",
            $wpdb->prefix . 'saw_users',
            $customer_id,
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(array('hosts' => $hosts));
    }
    
    /**
     * Prepare and sanitize form data from POST request
     * 
     * Extracts and sanitizes all company fields from the POST array.
     * 
     * @since 1.0.0
     * @param array $post Raw POST data
     * @return array Sanitized form data
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        if (isset($post['company_id'])) {
            $data['company_id'] = intval($post['company_id']);
        }
        
        $text_fields = array('visit_type', 'status', 'planned_date_from', 'planned_date_to', 'purpose');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (isset($post['invitation_email'])) {
            $data['invitation_email'] = sanitize_email($post['invitation_email']);
        }
        
        return $data;
    }
    
    /**
     * Hook: Before save operations
     * 
     * Auto-sets customer_id from context if not provided.
     * Can be extended for additional pre-save logic.
     * 
     * @since 1.0.0
     * @param array $data Form data
     * @return array|WP_Error Modified data or error
     */
    protected function before_save($data) {
        // Auto-set customer_id from context if empty
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        return $data;
    }
    
    /**
     * Hook: After save operations
     * 
     * Called after successful create or update.
     * Handles host assignments for visits.
     * 
     * FIXED: Merged duplicate method - now handles both cache invalidation and host saving
     * 
     * @since 1.0.0
     * @param int $id Visit ID (new ID for create, existing ID for update)
     * @return void
     */
    protected function after_save($id) {
        // Save hosts if provided in POST data
        if (isset($_POST['hosts']) && is_array($_POST['hosts'])) {
            $this->model->save_hosts($id, array_map('intval', $_POST['hosts']));
        }
        
        // Additional post-save logic can be added here:
        // - Cache invalidation (handled by model)
        // - Activity logging
        // - Notification sending
        // - Related record updates
    }
    
    /**
     * Format company data for detail modal
     * 
     * Transforms raw database data into a format suitable for display
     * in the detail modal. Adds branch name lookup.
     * 
     * @since 1.0.0
     * @param array $item Raw company data from database
     * @return array Formatted company data
     */
    protected function format_detail_data($item) {
        global $wpdb;
        
        if (!empty($item['company_id'])) {
            $company = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_companies',
                $item['company_id']
            ), ARRAY_A);
            
            if ($company) {
                $item['company_name'] = $company['name'];
            }
        }
        
        if (!empty($item['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_branches',
                $item['branch_id']
            ), ARRAY_A);
            
            if ($branch) {
                $item['branch_name'] = $branch['name'];
            }
        }
        
        // Load hosts for this visit
        if (!empty($item['id'])) {
            $item['hosts'] = $this->model->get_hosts($item['id']);
        }
        
        return $item;
    }
}