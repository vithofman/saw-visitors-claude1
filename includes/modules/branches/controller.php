<?php
/**
 * Branches Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.3.0
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
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        add_action('wp_ajax_saw_get_branches_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_branches', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_branches', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Index - List view
     */
    public function index() {
        $this->verify_module_access();
        $this->enqueue_assets();
        
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
            $filters['is_active'] = $is_active;
        }
        
        if ($is_headquarters !== '') {
            $filters['is_headquarters'] = $is_headquarters;
        }
        
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
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
        
        $this->enqueue_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_branches_form')) {
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
                    $this->set_flash('Pobočka byla úspěšně vytvořena', 'success');
                    $this->redirect(home_url('/admin/branches/'));
                }
            }
        }
        
        $item = [];
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
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
        
        $this->enqueue_assets();
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die('Pobočka nenalezena');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_branches_form')) {
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
                    $this->set_flash('Pobočka byla úspěšně aktualizována', 'success');
                    $this->redirect(home_url('/admin/branches/'));
                }
            }
            
            $item = $this->model->get_by_id($id);
        }
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
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
     * Enqueue assets
     */
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
    }
    
    /**
     * Before save hook
     */
    protected function before_save($data) {
        if ($this->file_uploader->should_remove_file('image_url')) {
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['image_url'])) {
                    $this->file_uploader->delete($existing['image_url']);
                }
            }
            $data['image_url'] = '';
            $data['image_thumbnail'] = '';
        }
        
        if (!empty($_FILES['image_url']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['image_url'], 'branches');
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['image_url'] = $upload['url'];
            $data['image_thumbnail'] = $upload['url'];
            
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
     * After save hook
     */
    protected function after_save($id) {
        $branch = $this->model->get_by_id($id);
        
        if (!empty($branch['customer_id'])) {
            delete_transient('branches_for_switcher_' . $branch['customer_id']);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Branches Controller] Cache invalidated for customer %d after saving branch %d',
                    $branch['customer_id'],
                    $id
                ));
            }
        }
    }
    
    /**
     * Format detail data for modal
     */
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        $item['full_address'] = $this->model->get_full_address($item);
        
        if (!empty($item['opening_hours'])) {
            $item['opening_hours_array'] = $this->model->get_opening_hours_as_array($item['opening_hours']);
        }
        
        $item['has_gps'] = !empty($item['latitude']) && !empty($item['longitude']);
        
        if ($item['has_gps']) {
            $item['google_maps_url'] = sprintf(
                'https://www.google.com/maps?q=%s,%s',
                $item['latitude'],
                $item['longitude']
            );
        }
        
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge-success' : 'saw-badge-secondary';
        
        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        $item['is_headquarters_badge_class'] = !empty($item['is_headquarters']) ? 'saw-badge-info' : 'saw-badge-secondary';
        
        $countries = [
            'CZ' => 'Česká republika',
            'SK' => 'Slovensko',
            'DE' => 'Německo',
            'AT' => 'Rakousko',
            'PL' => 'Polsko',
        ];
        
        if (!empty($item['country'])) {
            $item['country_name'] = $countries[$item['country']] ?? $item['country'];
        }
        
        return $item;
    }
}