<?php
/**
 * Customers Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Customers_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    private $color_picker;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/customers/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        add_action('wp_ajax_saw_get_customers_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_customers', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_customers', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * AJAX: Get detail for modal
     */
    public function ajax_get_detail() {
        // DEBUG: Log incoming request
        error_log('[CUSTOMERS AJAX] Request received');
        error_log('[CUSTOMERS AJAX] POST data: ' . print_r($_POST, true));
        
        // DEBUG: Temporarily disabled nonce check
        // TODO: Re-enable after fixing nonce generation
        /*
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_ajax_nonce')) {
            error_log('[CUSTOMERS AJAX] Nonce verification FAILED');
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
            return;
        }
        */
        
        if (!current_user_can('read')) {
            error_log('[CUSTOMERS AJAX] Permission check FAILED');
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        error_log('[CUSTOMERS AJAX] Customer ID: ' . $id);
        
        if (!$id) {
            error_log('[CUSTOMERS AJAX] Invalid ID');
            wp_send_json_error(['message' => 'Neplatné ID']);
            return;
        }
        
        $item = $this->model->get_by_id($id);
        error_log('[CUSTOMERS AJAX] Item found: ' . ($item ? 'YES' : 'NO'));
        
        if (!$item) {
            error_log('[CUSTOMERS AJAX] Customer not found in DB');
            wp_send_json_error(['message' => 'Zákazník nebyl nalezen']);
            return;
        }
        
        $item = $this->format_detail_data($item);
        
        $template_path = $this->config['path'] . 'detail-modal-template.php';
        error_log('[CUSTOMERS AJAX] Template path: ' . $template_path);
        error_log('[CUSTOMERS AJAX] Template exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));
        
        ob_start();
        require $template_path;
        $html = ob_get_clean();
        
        error_log('[CUSTOMERS AJAX] HTML length: ' . strlen($html));
        error_log('[CUSTOMERS AJAX] Sending success response');
        
        wp_send_json_success([
            'html' => $html,
            'item' => $item
        ]);
    }
    
    public function index() {
        $this->verify_module_access();
        $this->enqueue_assets();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
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
        
        if ($status !== '') {
            $filters['status'] = $status;
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
            wp_die('Nemáte oprávnění vytvářet zákazníky');
        }
        
        $this->enqueue_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_customers_form')) {
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
                    $this->set_flash('Zákazník byl úspěšně vytvořen', 'success');
                    $this->redirect(home_url('/admin/settings/customers/'));
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
        
        $this->render_with_layout($content, 'Nový zákazník');
    }
    
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat zákazníky');
        }
        
        $this->enqueue_assets();
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die('Zákazník nenalezen');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_customers_form')) {
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
                    $this->set_flash('Zákazník byl úspěšně aktualizován', 'success');
                    $this->redirect(home_url('/admin/settings/customers/'));
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
        
        $this->render_with_layout($content, 'Upravit zákazníka');
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
        $this->file_uploader->enqueue_assets();
        $this->color_picker->enqueue_assets();
    }
    
    protected function before_save($data) {
        if ($this->file_uploader->should_remove_file('logo')) {
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url'])) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
            $data['logo_url'] = '';
        }
        
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['logo_url'] = $upload['url'];
            
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url']) && $existing['logo_url'] !== $data['logo_url']) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
    }
    
    protected function before_delete($id) {
        global $wpdb;
        
        $branches_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d",
            $id
        ));
        
        if ($branches_count > 0) {
            return new WP_Error(
                'customer_has_branches',
                sprintf('Zákazníka nelze smazat. Má %d poboček. Nejprve je smažte.', $branches_count)
            );
        }
        
        $users_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d",
            $id
        ));
        
        if ($users_count > 0) {
            return new WP_Error(
                'customer_has_users',
                sprintf('Zákazníka nelze smazat. Má %d uživatelů. Nejprve je smažte.', $users_count)
            );
        }
        
        $visits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE customer_id = %d",
            $id
        ));
        
        if ($visits_count > 0) {
            return new WP_Error(
                'customer_has_visits',
                sprintf('Zákazníka nelze smazat. Má %d návštěv v historii.', $visits_count)
            );
        }
        
        $invitations_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_invitations WHERE customer_id = %d",
            $id
        ));
        
        if ($invitations_count > 0) {
            return new WP_Error(
                'customer_has_invitations',
                sprintf('Zákazníka nelze smazat. Má %d pozvánek.', $invitations_count)
            );
        }
        
        return true;
    }
    
    protected function after_delete($id) {
        $customer = $this->model->get_by_id($id);
        if (!empty($customer['logo_url'])) {
            $this->file_uploader->delete($customer['logo_url']);
        }
        
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
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
        
        if (!empty($item['logo_url'])) {
            if (strpos($item['logo_url'], 'http') === 0) {
                $item['logo_url_full'] = $item['logo_url'];
            } else {
                $upload_dir = wp_upload_dir();
                $item['logo_url_full'] = $upload_dir['baseurl'] . '/' . ltrim($item['logo_url'], '/');
            }
        } else {
            $item['logo_url_full'] = '';
        }
        
        $item['status_label'] = ucfirst($item['status'] ?? 'active');
        $item['status_badge_class'] = ($item['status'] ?? 'active') === 'active' ? 'saw-badge-success' : 'saw-badge-secondary';
        
        global $wpdb;
        
        $item['branches_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d",
            $item['id']
        ));
        
        $item['users_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d",
            $item['id']
        ));
        
        if (!empty($item['account_type_id'])) {
            $account_type = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}saw_account_types WHERE id = %d",
                $item['account_type_id']
            ), ARRAY_A);
            
            $item['account_type_name'] = $account_type['name'] ?? 'Neznámý';
        } else {
            $item['account_type_name'] = 'Nezadáno';
        }
        
        return $item;
    }
}