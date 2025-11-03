<?php
/**
 * Branches Module Controller - CLEANED
 * 
 * ✅ NO HARDCODED FILTERS - only permissions-based scope
 * 
 * @package SAW_Visitors
 * @version 1.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // AJAX handlers
        add_action('wp_ajax_saw_get_branches_for_switcher', [$this, 'ajax_get_branches_for_switcher']);
        add_action('wp_ajax_saw_switch_branch', [$this, 'ajax_switch_branch']);
        add_action('wp_ajax_saw_get_branches_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_branches', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_branches', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
    }
    
    protected function before_save($data) {
        // Handle image upload/removal
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
    
    protected function after_save($id) {
        $branch = $this->model->get_by_id($id);
        if (!empty($branch['customer_id'])) {
            delete_transient('branches_for_switcher_' . $branch['customer_id']);
        }
    }
    
    public function ajax_get_branches_for_switcher() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musíte být přihlášeni']);
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_branch_switcher')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
            return;
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(['message' => 'Chybí ID zákazníka']);
            return;
        }
        
        $cache_key = 'branches_for_switcher_' . $customer_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            wp_send_json_success([
                'branches' => $cached,
                'current_branch_id' => $this->get_current_branch_id(),
                'cached' => true
            ]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, code, city, street, is_headquarters, sort_order 
             FROM {$table} 
             WHERE customer_id = %d 
             AND is_active = 1 
             ORDER BY sort_order ASC, is_headquarters DESC, name ASC",
            $customer_id
        ), ARRAY_A);
        
        $formatted = array_map(function($branch) {
            $address = '';
            if (!empty($branch['street']) && !empty($branch['city'])) {
                $address = $branch['street'] . ', ' . $branch['city'];
            } elseif (!empty($branch['city'])) {
                $address = $branch['city'];
            }
            
            return [
                'id' => intval($branch['id']),
                'name' => $branch['name'],
                'code' => $branch['code'] ?? '',
                'city' => $branch['city'] ?? '',
                'address' => $address,
                'is_headquarters' => (bool)$branch['is_headquarters']
            ];
        }, $branches);
        
        set_transient($cache_key, $formatted, 1800);
        
        wp_send_json_success([
            'branches' => $formatted,
            'current_branch_id' => $this->get_current_branch_id()
        ]);
    }
    
    public function ajax_switch_branch() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musíte být přihlášeni']);
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_branch_switcher')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
            return;
        }
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if ($branch_id <= 0) {
            $this->clear_branch_session();
            wp_send_json_success([
                'message' => 'Pobočka byla odstraněna',
                'branch_id' => null
            ]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, customer_id FROM {$table} WHERE id = %d AND is_active = 1",
            $branch_id
        ), ARRAY_A);
        
        if (!$branch) {
            wp_send_json_error(['message' => 'Pobočka nebyla nalezena']);
            return;
        }
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $customer_id = $branch['customer_id'];
            
            update_user_meta($user_id, 'saw_current_branch_id', $branch_id);
            update_user_meta($user_id, 'saw_branch_customer_' . $customer_id, $branch_id);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_branch_id'] = $branch_id;
        
        wp_send_json_success([
            'message' => 'Pobočka byla úspěšně přepnuta',
            'branch_id' => $branch_id,
            'branch_name' => $branch['name']
        ]);
    }
    
    private function get_current_branch_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_branch_id'])) {
            return intval($_SESSION['saw_current_branch_id']);
        }
        
        if (is_user_logged_in()) {
            $meta_id = get_user_meta(get_current_user_id(), 'saw_current_branch_id', true);
            if ($meta_id) {
                $_SESSION['saw_current_branch_id'] = intval($meta_id);
                return intval($meta_id);
            }
        }
        
        return null;
    }
    
    private function clear_branch_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['saw_current_branch_id']);
        
        if (is_user_logged_in()) {
            delete_user_meta(get_current_user_id(), 'saw_current_branch_id');
        }
    }
}