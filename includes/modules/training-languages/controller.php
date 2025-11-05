<?php
/**
 * Training Languages Controller - REFACTORED v2.2 FINAL
 * 
 * FIXES:
 * - ✅ ALL 3 inject_module_css() now use 'training-languages' slug
 * - ✅ Removed manual AJAX registration (SAW_Visitors does it automatically)
 * - ✅ Uses SAW_Context for customer_id (no sessions)
 * - ✅ Full CRUD implementation
 * - ✅ Proper error handling
 * 
 * @package SAW_Visitors
 * @version 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Training_Languages_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Training_Languages_Model($this->config);
        
        // Auto-setup for Czech
        if (file_exists($module_path . 'class-auto-setup.php')) {
            require_once $module_path . 'class-auto-setup.php';
        }
        
        // ✅ NO manual AJAX registration - SAW_Visitors::register_module_ajax_handlers() does it
    }
    
    /**
     * Index - List view
     */
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'language_name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $filters = [
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
        ];
        
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        ob_start();
        
        // ✅ FIXED #1: Use 'training-languages' slug
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css('training-languages');
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Create - New language
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet jazyky');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_training_languages_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            
            $data = $this->before_save($data);
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            $validation = $this->model->validate($data);
            if (is_wp_error($validation)) {
                $errors = $validation->get_error_data();
                $this->set_flash(implode('<br>', $errors), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            $result = $this->model->create($data);
            
            if (is_wp_error($result)) {
                $this->set_flash($result->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            $this->after_save($result);
            
            $this->set_flash('Jazyk byl úspěšně vytvořen', 'success');
            $this->redirect(home_url('/admin/training-languages/'));
        }
        
        $item = [];
        $customer_id = SAW_Context::get_customer_id();
        
        // Get branches for customer
        global $wpdb;
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, code, city FROM %i 
             WHERE customer_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $wpdb->prefix . 'saw_branches',
            $customer_id
        ), ARRAY_A);
        
        ob_start();
        
        // ✅ FIXED #2: Use 'training-languages' slug
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css('training-languages');
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Nový jazyk');
    }
    
    /**
     * Edit - Update language
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat jazyky');
        }
        
        $id = intval($id);
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::not_found('Jazyk');
            } else {
                wp_die('Jazyk nebyl nalezen');
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_training_languages_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data['id'] = $id;
            
            $data = $this->before_save($data);
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            $validation = $this->model->validate($data, $id);
            if (is_wp_error($validation)) {
                $errors = $validation->get_error_data();
                $this->set_flash(implode('<br>', $errors), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            $result = $this->model->update($id, $data);
            
            if (is_wp_error($result)) {
                $this->set_flash($result->get_error_message(), 'error');
                $this->redirect($_SERVER['REQUEST_URI']);
            }
            
            $this->after_save($id);
            
            $this->set_flash('Jazyk byl úspěšně aktualizován', 'success');
            $this->redirect(home_url('/admin/training-languages/'));
        }
        
        // Get branches with current activation status
        $branches = $this->model->get_branches_for_language($id);
        
        ob_start();
        
        // ✅ FIXED #3: Use 'training-languages' slug
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css('training-languages');
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Upravit jazyk');
    }
    
    /**
     * Prepare form data from POST
     * 
     * @param array $post POST data
     * @return array Sanitized data
     */
    private function prepare_form_data($post) {
        $data = [];
        
        // Text fields
        $text_fields = ['language_code', 'language_name', 'flag_emoji'];
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        // Branches data
        if (!empty($post['branches'])) {
            $branches = [];
            
            foreach ($post['branches'] as $branch_id => $branch_data) {
                if (!empty($branch_data['active'])) {
                    $branches[$branch_id] = [
                        'active' => 1,
                        'is_default' => !empty($branch_data['is_default']) ? 1 : 0,
                        'display_order' => intval($branch_data['display_order'] ?? 0),
                    ];
                }
            }
            
            $data['branches'] = $branches;
        }
        
        return $data;
    }
    
    /**
     * Before save hook - auto-set customer_id
     * 
     * @param array $data Data to save
     * @return array|WP_Error Modified data or error
     */
    protected function before_save($data) {
        // Auto-set customer_id from context
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        return $data;
    }
    
    /**
     * After save hook - cache invalidation
     * 
     * @param int $id Item ID
     */
    protected function after_save($id) {
        // Cache disabled - no invalidation needed
    }
}