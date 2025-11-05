<?php
/**
 * Departments Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Departments_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/departments/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Departments_Model($this->config);
        
        // AJAX handlers registered automatically by SAW_Visitors core
    }
    
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
        $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
        
        $filters = [
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
        ];
        
        if ($branch_id > 0) {
            $filters['branch_id'] = $branch_id;
        }
        
        if ($is_active !== '') {
            $filters['is_active'] = intval($is_active);
        }
        
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
            wp_die('Nemáte oprávnění vytvářet oddělení');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_departments_form')) {
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
            
            $this->set_flash('Oddělení bylo úspěšně vytvořeno', 'success');
            $this->redirect(home_url('/admin/departments/'));
        }
        
        $item = [];
        
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
        
        $this->render_with_layout($content, 'Nové oddělení');
    }
    
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat oddělení');
        }
        
        $id = intval($id);
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::not_found('Oddělení');
            } else {
                wp_die('Oddělení nebylo nalezeno');
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_departments_form')) {
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
            
            $this->set_flash('Oddělení bylo úspěšně aktualizováno', 'success');
            $this->redirect(home_url('/admin/departments/'));
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
        
        $this->render_with_layout($content, 'Upravit oddělení');
    }
    
    private function prepare_form_data($post) {
        $data = [];
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        $text_fields = ['department_number', 'name', 'description'];
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (isset($post['training_version'])) {
            $data['training_version'] = intval($post['training_version']);
        }
        
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
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
    
    /**
     * Format detail data for AJAX modal
     */
    protected function format_detail_data($item) {
        // Model already formats everything in get_by_id()
        return $item;
    }
}