<?php
/**
 * Account Types Module Controller
 * 
 * @package SAW_Visitors
 * @version 2.4.0 - Permissions & Scope Fix
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $color_picker;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/account-types/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Account_Types_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_color_picker_assets']);
    }
    
    public function enqueue_color_picker_assets() {
        $this->color_picker->enqueue_assets();
    }
    
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
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
            $this->set_flash('Nemáte oprávnění vytvářet typy účtů', 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_account_types_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            
            $scope_validation = $this->validate_scope_access($data, 'create');
            if (is_wp_error($scope_validation)) {
                $this->set_flash($scope_validation->get_error_message(), 'error');
                $this->redirect(home_url('/admin/settings/account-types/'));
            }
            
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
            
            $this->set_flash('Typ účtu byl úspěšně vytvořen', 'success');
            $this->redirect(home_url('/admin/settings/account-types/'));
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
        
        $this->render_with_layout($content, 'Nový typ účtu');
    }
    
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            $this->set_flash('Nemáte oprávnění upravovat typy účtů', 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        $id = intval($id);
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            $this->set_flash('Typ účtu nebyl nalezen', 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        if (isset($item['customer_id']) && !$this->can_access_item($item)) {
            $this->set_flash('Nemáte oprávnění k tomuto záznamu', 'error');
            $this->redirect(home_url('/admin/settings/account-types/'));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_account_types_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data['id'] = $id;
            
            $scope_validation = $this->validate_scope_access($data, 'edit');
            if (is_wp_error($scope_validation)) {
                $this->set_flash($scope_validation->get_error_message(), 'error');
                $this->redirect(home_url('/admin/settings/account-types/edit/' . $id));
            }
            
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
            
            $this->set_flash('Typ účtu byl úspěšně aktualizován', 'success');
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
        
        $this->render_with_layout($content, 'Upravit typ účtu');
    }
    
    private function prepare_form_data($post) {
        $data = [];
        
        $text_fields = ['name', 'display_name', 'description', 'color'];
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (isset($post['price'])) {
            $data['price'] = floatval($post['price']);
        }
        
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
        
        if (isset($post['sort_order'])) {
            $data['sort_order'] = intval($post['sort_order']);
        }
        
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        return $data;
    }
    
    protected function before_save($data) {
        if (!empty($data['color']) && !preg_match('/^#[0-9a-f]{6}$/i', $data['color'])) {
            return new WP_Error('invalid_color', 'Neplatný formát barvy');
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        delete_transient('account_types_list');
    }
}