<?php
/**
 * Training Languages Controller - REFACTORED
 * 
 * @package SAW_Visitors
 * @version 2.0.0
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
    }
    
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
            "SELECT id, name, code, city FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $customer_id
        ), ARRAY_A);
        
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
        
        $this->render_with_layout($content, 'Nový jazyk');
    }
    
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
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Upravit jazyk');
    }
    
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
    
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        // Cache disabled - no invalidation needed
    }
}
