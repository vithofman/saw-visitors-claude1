<?php
/**
 * Account Types Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.1.0
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
        
        add_action('wp_ajax_saw_get_account_types_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_account_types', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_account_types', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function index() {
        $this->verify_module_access();
        $this->enqueue_assets();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
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
    
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet typy účtů');
        }
        
        $this->enqueue_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_account_types_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
            } else {
                $id = $this->model->create($data);
                
                if (is_wp_error($id)) {
                    $this->set_flash($id->get_error_message(), 'error');
                } else {
                    $this->after_save($id);
                    $this->set_flash('Typ účtu byl úspěšně vytvořen', 'success');
                    $this->redirect(home_url('/admin/settings/account-types/'));
                }
            }
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
            wp_die('Nemáte oprávnění upravovat typy účtů');
        }
        
        $this->enqueue_assets();
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die('Typ účtu nenalezen');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_account_types_form')) {
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
                    $this->set_flash('Typ účtu byl úspěšně aktualizován', 'success');
                    $this->redirect(home_url('/admin/settings/account-types/'));
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
        
        $this->render_with_layout($content, 'Upravit typ účtu');
    }
    
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
    
    public function enqueue_assets() {
        $this->color_picker->enqueue_assets();
    }
    
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        $item['features_array'] = $this->model->get_features_as_array($item['features'] ?? null);
        
        if (isset($item['price'])) {
            $item['price_formatted'] = number_format($item['price'], 0, ',', ' ') . ' Kč';
        }
        
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge-success' : 'saw-badge-secondary';
        
        return $item;
    }
    
    protected function before_delete($id) {
        if ($this->model->is_used_by_customers($id)) {
            return new WP_Error(
                'cannot_delete_in_use',
                'Tento typ účtu nelze smazat, protože ho používá jeden nebo více zákazníků. Nejdříve přiřaďte zákazníkům jiný typ účtu.'
            );
        }
        
        return true;
    }
    
    protected function after_save($id) {
        delete_transient('account_types_list');
        delete_transient('account_types_count');
        delete_transient('customers_list');
        delete_transient('customers_count');
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log("SAW: Account Type saved - ID: {$id}");
        }
    }
}