<?php
/**
 * Departments Module Controller
 * 
 * Handles all HTTP requests for the Departments module.
 * Uses universal Base Controller methods and AJAX handlers trait.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     2.0.0 - REFACTORED to use Base Controller universal methods
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

/**
 * Departments Controller Class
 * 
 * Extends SAW_Base_Controller to provide standard CRUD operations
 * for managing organizational departments within branches.
 * 
 * @since 1.0.0
 */
class SAW_Module_Departments_Controller extends SAW_Base_Controller 
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
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/departments/';
        
        // Load configuration
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        // Initialize model
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Departments_Model($this->config);
    }
    
    /**
     * Display list of all departments with optional sidebar
     * 
     * Uses universal render_list_view() from Base Controller.
     * Automatically handles sidebar modes (detail, create, edit).
     * 
     * @since 1.0.0
     * @return void
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Prepare and sanitize form data from POST request
     * 
     * Extracts and sanitizes all department fields from the POST array.
     * 
     * @since 1.0.0
     * @param array $post Raw POST data
     * @return array Sanitized form data
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Customer ID (hidden field)
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        // Branch ID
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        // Text fields
        $text_fields = array('department_number', 'name', 'description');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        // Training version (integer)
        if (isset($post['training_version'])) {
            $data['training_version'] = intval($post['training_version']);
        }
        
        // Active status (checkbox)
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
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
     * Can be used for cache invalidation, logging, or related record updates.
     * 
     * @since 1.0.0
     * @param int $id Department ID (new ID for create, existing ID for update)
     * @return void
     */
    protected function after_save($id) {
        // Currently no post-save logic needed
        // Can be extended for:
        // - Cache invalidation
        // - Activity logging
        // - Related record updates
    }
    
    /**
     * Format department data for detail modal
     * 
     * Transforms raw database data into a format suitable for display
     * in the detail modal. Adds branch name lookup.
     * 
     * @since 1.0.0
     * @param array $item Raw department data from database
     * @return array Formatted department data
     */
    protected function format_detail_data($item) {
        global $wpdb;
        
        // Load branch name
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
        
        return $item;
    }
    
    /**
     * Load related data for detail sidebar - OVERRIDE
     * 
     * Custom implementation to handle junction table (saw_user_departments)
     * for many-to-many relationship between departments and users.
     * 
     * @since 2.0.0
     * @param int $item_id Department ID
     * @return array|null Related data grouped by relation key, or null
     */
    
}