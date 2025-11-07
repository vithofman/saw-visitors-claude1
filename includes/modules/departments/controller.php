<?php
/**
 * Departments Module Controller
 * 
 * Handles all HTTP requests for the Departments module including list view,
 * create, edit operations, and AJAX handlers for detail modal and delete.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
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
     * AJAX handlers are registered elsewhere in the system.
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
     * Display list of all departments
     * 
     * Shows paginated, searchable, and filterable list of departments
     * for the current branch context.
     * 
     * @since 1.0.0
     * @return void
     */
    public function index() {
        $this->verify_module_access();
        
        // Get and sanitize query parameters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
        
        // Get current branch context
        $current_branch_id = SAW_Context::get_branch_id();
        
        // Build filters array
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
            'branch_id' => $current_branch_id,
        );
        
        // Add is_active filter if set
        if ($is_active !== '') {
            $filters['is_active'] = intval($is_active);
        }
        
        // Fetch data from model
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        // Start output buffering
        ob_start();
        
        // Inject module-specific CSS if Style Manager is available
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        // Render flash messages (success/error notifications)
        $this->render_flash_messages();
        
        // Load list template
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        // Render within admin layout
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Create new department
     * 
     * Handles both GET (display form) and POST (process form) requests
     * for creating a new department.
     * 
     * @since 1.0.0
     * @return void
     */
    public function create() {
        $this->verify_module_access();
        
        // Check create permission
        if (!$this->can('create')) {
            $this->set_flash('Nemáte oprávnění vytvářet oddělení', 'error');
            $this->redirect(home_url('/admin/departments/'));
        }
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_departments_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            // Prepare form data
            $data = $this->prepare_form_data($_POST);
            
            // Validate scope access (customer/branch isolation)
            $scope_validation = $this->validate_scope_access($data, 'create');
            if (is_wp_error($scope_validation)) {
                $this->set_flash($scope_validation->get_error_message(), 'error');
                $this->redirect(home_url('/admin/departments/'));
            }
            
            // Run before_save hook (auto-set customer_id)
            $data = $this->before_save($data);
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            // Validate data
            $validation = $this->model->validate($data);
            if (is_wp_error($validation)) {
                $errors = $validation->get_error_data();
                $this->set_flash(implode('<br>', $errors), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            // Create record
            $result = $this->model->create($data);
            
            if (is_wp_error($result)) {
                $this->set_flash($result->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            // Run after_save hook
            $this->after_save($result);
            
            // Success - redirect to list
            $this->set_flash('Oddělení bylo úspěšně vytvořeno', 'success');
            $this->redirect(home_url('/admin/departments/'));
        }
        
        // Display form (GET request)
        $item = array();
        
        ob_start();
        
        // Inject module-specific CSS if available
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        // Load form template
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Nové oddělení');
    }
    
    /**
     * Edit existing department
     * 
     * Handles both GET (display form) and POST (process form) requests
     * for editing an existing department.
     * 
     * @since 1.0.0
     * @param int $id Department ID
     * @return void
     */
    public function edit($id) {
        $this->verify_module_access();
        
        // Check edit permission
        if (!$this->can('edit')) {
            $this->set_flash('Nemáte oprávnění upravovat oddělení', 'error');
            $this->redirect(home_url('/admin/departments/'));
        }
        
        // Get department record
        $id = intval($id);
        $item = $this->model->get_by_id($id);
        
        // Check if department exists and user has access
        if (!$item) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::not_found('Oddělení');
            } else {
                wp_die('Oddělení nebylo nalezeno');
            }
        }
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_departments_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            // Prepare form data
            $data = $this->prepare_form_data($_POST);
            $data['id'] = $id;
            
            // Validate scope access
            $scope_validation = $this->validate_scope_access($data, 'edit');
            if (is_wp_error($scope_validation)) {
                $this->set_flash($scope_validation->get_error_message(), 'error');
                $this->redirect(home_url('/admin/departments/edit/' . $id));
            }
            
            // Run before_save hook
            $data = $this->before_save($data);
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            // Validate data
            $validation = $this->model->validate($data, $id);
            if (is_wp_error($validation)) {
                $errors = $validation->get_error_data();
                $this->set_flash(implode('<br>', $errors), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            // Update record
            $result = $this->model->update($id, $data);
            
            if (is_wp_error($result)) {
                $this->set_flash($result->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            // Run after_save hook
            $this->after_save($id);
            
            // Success - redirect to list
            $this->set_flash('Oddělení bylo úspěšně aktualizováno', 'success');
            $this->redirect(home_url('/admin/departments/'));
        }
        
        // Display form (GET request)
        ob_start();
        
        // Inject module-specific CSS if available
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        // Load form template
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Upravit oddělení');
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
    private function prepare_form_data($post) {
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
     * in the detail modal. Can be extended to add computed fields or formatting.
     * 
     * @since 1.0.0
     * @param array $item Raw department data from database
     * @return array Formatted department data
     */
    protected function format_detail_data($item) {
        // Currently returns item as-is
        // Can be extended for:
        // - Date formatting
        // - Status badge generation
        // - Related data loading
        return $item;
    }
}