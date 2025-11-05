<?php
/**
 * Customers Module Controller - COMPLETE VERSION
 * 
 * @package SAW_Visitors
 * @version 3.1.0 - FIXED: Cache invalidation for modal + list
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_ajax_nonce')) {
            error_log('[CUSTOMERS AJAX] Nonce verification FAILED');
            wp_send_json_error([
                'message' => 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.'
            ]);
            return;
        }
        
        if (!current_user_can('read')) {
            error_log('[CUSTOMERS AJAX] Permission check FAILED');
            wp_send_json_error([
                'message' => 'Nedostatečná oprávnění k zobrazení detailu.'
            ]);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            error_log('[CUSTOMERS AJAX] Invalid ID');
            wp_send_json_error([
                'message' => 'Neplatné ID zákazníka.'
            ]);
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[CUSTOMERS AJAX] Loading detail - ID: %d, User: %d',
                $id,
                get_current_user_id()
            ));
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            error_log('[CUSTOMERS AJAX] Customer not found in DB - ID: ' . $id);
            wp_send_json_error([
                'message' => 'Zákazník nebyl nalezen v databázi.'
            ]);
            return;
        }
        
        $item = $this->format_detail_data($item);
        
        $template_path = $this->config['path'] . 'detail-modal-template.php';
        
        if (!file_exists($template_path)) {
            error_log('[CUSTOMERS AJAX] Template file NOT FOUND: ' . $template_path);
            wp_send_json_error([
                'message' => 'Chyba: Template soubor nebyl nalezen. Kontaktujte administrátora.'
            ]);
            return;
        }
        
        ob_start();
        
        try {
            require $template_path;
            $html = ob_get_clean();
            
            if (empty($html) || strlen($html) < 50) {
                throw new Exception('Template rendered empty or too short content');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[CUSTOMERS AJAX] SUCCESS - ID: %d, HTML length: %d bytes',
                    $id,
                    strlen($html)
                ));
            }
            
            wp_send_json_success([
                'html' => $html,
                'item' => $item
            ]);
            
        } catch (Exception $e) {
            ob_end_clean();
            error_log('[CUSTOMERS AJAX] Template rendering error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Chyba při zobrazení detailu: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Display list page
     */
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
    
    /**
     * Display create form
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet zákazníky');
        }
        
        $this->enqueue_assets();
        
        // ✅ LOAD ACCOUNT TYPES FOR DROPDOWN
        global $wpdb;
        $account_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, display_name, color, price 
                 FROM %i 
                 WHERE is_active = 1 
                 ORDER BY sort_order ASC, display_name ASC",
                $wpdb->prefix . 'saw_account_types'
            ),
            ARRAY_A
        );
        
        $item = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_customers_form')) {
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
                    $this->set_flash('Zákazník byl úspěšně vytvořen', 'success');
                    $this->redirect(home_url('/admin/settings/customers/'));
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
        
        $this->render_with_layout($content, 'Nový zákazník');
    }
    
    /**
     * Display edit form
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat zákazníky');
        }
        
        $this->enqueue_assets();
        
        // ✅ LOAD ACCOUNT TYPES FOR DROPDOWN
        global $wpdb;
        $account_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, display_name, color, price 
                 FROM %i 
                 WHERE is_active = 1 
                 ORDER BY sort_order ASC, display_name ASC",
                $wpdb->prefix . 'saw_account_types'
            ),
            ARRAY_A
        );
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die('Zákazník nenalezen');
        }
        
        // ✅ REVERSE MAPPING: subscription_type → account_type_id
        // Databáze má sloupeček 'subscription_type', ale formulář očekává 'account_type_id'
        if (isset($item['subscription_type'])) {
            $item['account_type_id'] = $item['subscription_type'];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[CUSTOMERS] Reverse mapping for edit: subscription_type (%s) → account_type_id',
                    $item['subscription_type'] ?? 'NULL'
                ));
            }
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
        
        // ✅ FIELD MAPPING: account_type_id → subscription_type
        // Formulář posílá 'account_type_id', ale databáze má sloupeček 'subscription_type'
        if (isset($data['account_type_id'])) {
            $data['subscription_type'] = $data['account_type_id'];
            unset($data['account_type_id']); // Odstraníme původní klíč
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[CUSTOMERS] Field mapping: account_type_id (%s) → subscription_type',
                    $data['subscription_type'] ?? 'NULL'
                ));
            }
        }
        
        return $data;
    }
    
    /**
     * Enqueue assets (file uploader, color picker)
     */
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
        $this->color_picker->enqueue_assets();
    }
    
    /**
     * Before save hook - handle file uploads
     */
    protected function before_save($data) {
        // Handle logo removal
        if ($this->file_uploader->should_remove_file('logo')) {
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url'])) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
            $data['logo_url'] = '';
        }
        
        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['logo_url'] = $upload['url'];
            
            // Delete old logo if updating
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url']) && $existing['logo_url'] !== $data['logo_url']) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * After save hook - clear ALL caches
     * ✅ FIXED: Proper cache invalidation for modal + list
     */
    protected function after_save($id) {
        // ✅ Invalidate transient caches
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
        delete_transient(sprintf('customers_item_%d', $id));
        
        // ✅ Invalidate SAW_Cache if available
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget(sprintf('customers_item_%d', $id));
            SAW_Cache::forget_pattern('customers_*');
        }
        
        // ✅ Invalidate WordPress object cache
        wp_cache_delete($id, 'saw_customers');
        wp_cache_delete('saw_customers_all', 'saw_customers');
        
        // ✅ Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[CUSTOMERS] Cache invalidated for ID: %d', $id));
        }
    }
    
    /**
     * Before delete hook - check dependencies
     */
    protected function before_delete($id) {
        global $wpdb;
        
        // Check for branches
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
        
        // Check for users
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
        
        // Check for visits
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
        
        // Check for invitations
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
    
    /**
     * After delete hook - cleanup
     */
    protected function after_delete($id) {
        $customer = $this->model->get_by_id($id);
        if (!empty($customer['logo_url'])) {
            $this->file_uploader->delete($customer['logo_url']);
        }
        
        // ✅ Full cache cleanup
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
        delete_transient(sprintf('customers_item_%d', $id));
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget(sprintf('customers_item_%d', $id));
            SAW_Cache::forget_pattern('customers_*');
        }
        
        wp_cache_delete($id, 'saw_customers');
        wp_cache_delete('saw_customers_all', 'saw_customers');
    }
    
    /**
     * Format detail data for modal
     */
    protected function format_detail_data($item) {
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        // Format logo URL
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
        
        // Format status
        $item['status_label'] = ucfirst($item['status'] ?? 'active');
        $item['status_badge_class'] = ($item['status'] ?? 'active') === 'active' 
            ? 'saw-badge-success' 
            : 'saw-badge-secondary';
        
        // Ensure colors are properly formatted
        if (!empty($item['primary_color']) && strpos($item['primary_color'], '#') !== 0) {
            $item['primary_color'] = '#' . $item['primary_color'];
        }
        
        if (!empty($item['secondary_color']) && strpos($item['secondary_color'], '#') !== 0) {
            $item['secondary_color'] = '#' . $item['secondary_color'];
        }
        
        return $item;
    }
}