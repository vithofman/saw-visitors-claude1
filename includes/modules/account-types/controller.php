<?php
/**
 * Account Types Module Controller
 * 
 * Handles all HTTP requests and business logic for account types:
 * - List view with search, sorting, pagination
 * - Create new account type
 * - Edit existing account type
 * - Form data preparation and validation
 * - Color picker integration
 * - Flash messages
 * - Permissions and scope validation
 * - Cache invalidation after changes
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @since       1.0.0
 * @version     2.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Account Types Controller Class
 * 
 * @extends SAW_Base_Controller
 */
class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * Color picker component instance
     * 
     * @var SAW_Color_Picker
     * @since 1.0.0
     */
    private $color_picker;
    
    /**
     * Constructor
     * 
     * Loads configuration, initializes model, and sets up color picker component.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/account-types/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Account_Types_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker_assets'));
    }
    
    /**
     * Enqueue color picker assets
     * 
     * Loads CSS and JavaScript for color picker component.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_color_picker_assets() {
        $this->color_picker->enqueue_assets();
    }
    
    /**
     * List view - Display all account types
     * 
     * Shows paginated list of account types with:
     * - Search functionality
     * - Sorting by column
     * - Filters (active/inactive)
     * - Pagination
     * 
     * @since 1.0.0
     * @return void
     */
    public function index() {
        $this->verify_module_access();
        
        // Get and sanitize request parameters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
        );
        
        // Fetch data from model
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        ob_start();
        
        // Inject module-specific CSS if style manager available
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Create view - Display form and handle creation
     * 
     * GET:  Shows empty form for creating new account type
     * POST: Processes form submission and creates new record
     * 
     * @since 1.0.0
     * @return void
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            $this->set_flash(__('Nemáte oprávnění vytvářet typy účtů', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_account_types_form')) {
                wp_die(__('Neplatný bezpečnostní token', 'saw-visitors'));
            }
            
            $data = $this->prepare_form_data($_POST);
            
            // Validate scope access
            $scope_validation = $this->validate_scope_access($data, 'create');
            if (is_wp_error($scope_validation)) {
                $this->set_flash($scope_validation->get_error_message(), 'error');
                $this->redirect(home_url('/admin/settings/account-types/'));
            }
            
            // Before save hook
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
            
            // After save hook
            $this->after_save($result);
            
            $this->set_flash(__('Typ účtu byl úspěšně vytvořen', 'saw-visitors'), 'success');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        $item = array();
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, __('Nový typ účtu', 'saw-visitors'));
    }
    
    /**
     * Edit view - Display form and handle update
     * 
     * GET:  Shows form pre-filled with existing account type data
     * POST: Processes form submission and updates record
     * 
     * @since 1.0.0
     * @param int $id Account type ID
     * @return void
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            $this->set_flash(__('Nemáte oprávnění upravovat typy účtů', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        $id = intval($id);
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            $this->set_flash(__('Typ účtu nebyl nalezen', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        // Check customer isolation if applicable
        if (isset($item['customer_id']) && !$this->can_access_item($item)) {
            $this->set_flash(__('Nemáte oprávnění k tomuto záznamu', 'saw-visitors'), 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_account_types_form')) {
                wp_die(__('Neplatný bezpečnostní token', 'saw-visitors'));
            }
            
            $data = $this->prepare_form_data($_POST);
            $data['id'] = $id;
            
            // Validate scope access
            $scope_validation = $this->validate_scope_access($data, 'edit');
            if (is_wp_error($scope_validation)) {
                $this->set_flash($scope_validation->get_error_message(), 'error');
                $this->redirect(home_url('/admin/settings/account-types/edit/' . $id));
            }
            
            // Before save hook
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
            
            // After save hook
            $this->after_save($id);
            
            $this->set_flash(__('Typ účtu byl úspěšně aktualizován', 'saw-visitors'), 'success');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, __('Upravit typ účtu', 'saw-visitors'));
    }
    
    /**
     * Prepare form data from POST request
     * 
     * Sanitizes and formats all form fields:
     * - Text fields (name, display_name, description, color)
     * - Numeric fields (price, sort_order)
     * - Textarea (features - converts to array)
     * - Checkbox (is_active)
     * 
     * @since 1.0.0
     * @param array $post POST data from form submission
     * @return array Sanitized and formatted data
     */
    private function prepare_form_data($post) {
        $data = array();
        
        // Text fields
        $text_fields = array('name', 'display_name', 'description', 'color');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        // Price field
        if (isset($post['price'])) {
            $data['price'] = floatval($post['price']);
        }
        
        // Features field (convert textarea to array)
        if (isset($post['features'])) {
            $features_text = sanitize_textarea_field($post['features']);
            $features_array = array_filter(
                array_map('trim', explode("\n", $features_text)),
                function($line) {
                    return !empty($line);
                }
            );
            $data['features'] = $features_array;
        }
        
        // Sort order field
        if (isset($post['sort_order'])) {
            $data['sort_order'] = intval($post['sort_order']);
        }
        
        // Is active checkbox
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        return $data;
    }
    
    /**
     * Before save hook
     * 
     * Validates data before saving to database.
     * Currently validates color format (hex color with #).
     * 
     * @since 1.0.0
     * @param array $data Data to be saved
     * @return array|WP_Error Validated data or WP_Error on failure
     */
    protected function before_save($data) {
        // Validate color format
        if (!empty($data['color']) && !preg_match('/^#[0-9a-f]{6}$/i', $data['color'])) {
            return new WP_Error('invalid_color', __('Neplatný formát barvy', 'saw-visitors'));
        }
        
        return $data;
    }
    
    /**
     * After save hook
     * 
     * Performs cleanup after successful save operation.
     * Invalidates account types list cache.
     * 
     * @since 1.0.0
     * @param int $id Account type ID that was saved
     * @return void
     */
    protected function after_save($id) {
        delete_transient('account_types_list');
    }
}