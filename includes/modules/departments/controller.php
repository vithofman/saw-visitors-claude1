<?php
/**
 * Departments Module Controller - COMPLETE
 * 
 * @package SAW_Visitors
 * @version 1.1.0 - FIXED: Added all CRUD methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Departments_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Departments_Model($this->config);
        
        add_action('wp_ajax_saw_get_departments_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_departments', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_departments', [$this, 'ajax_delete']);
    }
    
    /**
     * Index - List view
     */
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $filters = [
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
        ];
        
        if ($is_active !== '') {
            $filters['is_active'] = $is_active;
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
    
    /**
     * Create - New department form
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet oddělení');
        }
        
        $item = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_departments_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
                $item = $data;
            } else {
                $result = $this->model->create($data);
                
                if (is_wp_error($result)) {
                    $this->set_flash($result->get_error_message(), 'error');
                    $item = $data;
                } else {
                    $this->after_save($result);
                    $this->set_flash('Oddělení bylo úspěšně vytvořeno', 'success');
                    $this->redirect(home_url('/admin/departments/'));
                }
            }
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
        
        $this->render_with_layout($content, 'Nové oddělení');
    }
    
    /**
     * Edit - Update department form
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat oddělení');
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die('Oddělení nebylo nalezeno');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_departments_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
            } else {
                $result = $this->model->update($id, $data);
                
                if (is_wp_error($result)) {
                    $this->set_flash($result->get_error_message(), 'error');
                } else {
                    $this->after_save($id);
                    $this->set_flash('Oddělení bylo úspěšně aktualizováno', 'success');
                    $this->redirect(home_url('/admin/departments/'));
                }
            }
            
            $item = $this->model->get_by_id($id);
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
    
    /**
     * Prepare form data for save
     */
    private function prepare_form_data($post) {
        $data = [];
        
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $value = $post[$field_name];
                
                if (isset($field_config['sanitize']) && is_callable($field_config['sanitize'])) {
                    $value = call_user_func($field_config['sanitize'], $value);
                }
                
                $data[$field_name] = $value;
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        if (isset($post['id'])) {
            $data['id'] = intval($post['id']);
        }
        
        return $data;
    }
    
    /**
     * Before save hook
     */
    protected function before_save($data) {
        // Auto-set customer_id if not present
        if (empty($data['customer_id'])) {
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
                if ($customer_id) {
                    $data['customer_id'] = $customer_id;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * After save hook
     */
    protected function after_save($id) {
        // Clear any cached departments list
        delete_transient('departments_list');
    }
    
    /**
     * Before delete hook
     */
    protected function before_delete($id) {
        if ($this->model->is_used_in_system($id)) {
            return new WP_Error(
                'department_in_use',
                'Toto oddělení nelze smazat, protože je používáno v systému (návštěvy, pozvánky nebo uživatelé).'
            );
        }
        
        return true;
    }
    
    /**
     * After delete hook
     */
    protected function after_delete($id) {
        delete_transient('departments_list');
    }
}