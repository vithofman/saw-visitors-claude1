<?php
/**
 * Branches Module Controller
 * 
 * REFACTORED v3.0.0:
 * ✅ NO manual AJAX registration (SAW_Visitors handles it)
 * ✅ NO custom ajax_get_detail (trait handles it)
 * ✅ Uses SAW_Context instead of sessions
 * ✅ Full CRUD implementation
 * ✅ Cache invalidation
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        // File uploader for images
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
            $this->file_uploader = new SAW_File_Uploader();
        }
        
        // ✅ NO manual AJAX registration - SAW_Visitors does it automatically
    }
    
    /**
     * Index - List view
     */
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
        $is_headquarters = isset($_GET['is_headquarters']) ? sanitize_text_field($_GET['is_headquarters']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'sort_order';
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
            $filters['is_active'] = intval($is_active);
        }
        
        if ($is_headquarters !== '') {
            $filters['is_headquarters'] = intval($is_headquarters);
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
     * Create - New branch form
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet pobočky');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_branches_form')) {
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
            
            $this->set_flash('Pobočka byla úspěšně vytvořena', 'success');
            $this->redirect(home_url('/admin/branches/'));
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
        
        $this->render_with_layout($content, 'Nová pobočka');
    }
    
    /**
     * Edit - Edit branch form
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat pobočky');
        }
        
        $id = intval($id);
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::not_found('Pobočka');
            } else {
                wp_die('Pobočka nebyla nalezena');
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_branches_form')) {
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
            
            $this->set_flash('Pobočka byla úspěšně aktualizována', 'success');
            $this->redirect(home_url('/admin/branches/'));
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
        
        $this->render_with_layout($content, 'Upravit pobočku');
    }
    
    /**
     * Prepare form data
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
        // Auto-set customer_id from context
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        // Handle file upload removal
        if ($this->file_uploader && $this->file_uploader->should_remove_file('image_url')) {
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['image_url'])) {
                    $this->file_uploader->delete($existing['image_url']);
                }
            }
            $data['image_url'] = '';
            $data['image_thumbnail'] = '';
        }
        
        // Handle file upload
        if ($this->file_uploader && !empty($_FILES['image_url']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['image_url'], 'branches');
            
            if (is_wp_error($upload)) {
                return $upload;
            }
            
            $data['image_url'] = $upload['url'];
            $data['image_thumbnail'] = $upload['url'];
            
            // Delete old image
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['image_url']) && $existing['image_url'] !== $data['image_url']) {
                    $this->file_uploader->delete($existing['image_url']);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * After save hook - Cache invalidation
     */
    protected function after_save($id) {
        $branch = $this->model->get_by_id($id);
        
        if (!empty($branch['customer_id'])) {
            // Invalidate branch switcher cache
            delete_transient('branches_for_switcher_' . $branch['customer_id']);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Branches] Cache invalidated for customer %d after saving branch %d',
                    $branch['customer_id'],
                    $id
                ));
            }
        }
    }
}