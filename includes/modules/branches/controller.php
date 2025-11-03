<?php
/**
 * Branches Module Controller - OPRAVENÁ VERZE
 * 
 * ✅ OPRAVY:
 * 1. AJAX handlery pro branch switcher nyní kontrolují jenom is_user_logged_in()
 * 2. Odstraněna zbytečně přísná kontrola current_user_can('read')
 * 3. SAW uživatelé (admin, manager) teď můžou přepínat pobočky
 * 
 * @package SAW_Visitors
 * @version 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

error_log('🔥 BRANCHES CONTROLLER: File loaded at ' . date('H:i:s'));

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    
    public function __construct() {
        error_log('🔥 BRANCHES CONTROLLER: __construct() called');
        
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // ✅ AJAX handlery pro branch switcher
        add_action('wp_ajax_saw_get_branches_for_switcher', [$this, 'ajax_get_branches_for_switcher']);
        add_action('wp_ajax_saw_switch_branch', [$this, 'ajax_switch_branch']);
        
        // Standardní CRUD AJAX
        add_action('wp_ajax_saw_get_branches_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_branches', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_branches', [$this, 'ajax_delete']);
        
        error_log('🔥 BRANCHES CONTROLLER: AJAX actions registered');
        
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
        // Clear cache after save
        if (!empty($this->config['customer_id'])) {
            delete_transient('branches_for_switcher_' . $this->config['customer_id']);
        }
    }
    
    /**
     * ✅ OPRAVENO: AJAX handler pro načtení poboček do switcheru
     * 
     * ZMĚNY:
     * - Odstraněna kontrola current_user_can('read')
     * - Nyní stačí být přihlášený (is_user_logged_in())
     * - SAW uživatelé (admin, manager) nemají WP capability 'read', ale mají session
     */
    public function ajax_get_branches_for_switcher() {
        error_log('🔥 BRANCHES CONTROLLER: ajax_get_branches_for_switcher() CALLED');
        
        // ✅ KRITICKÁ OPRAVA: Kontrola jenom přihlášení
        if (!is_user_logged_in()) {
            error_log('❌ BRANCHES CONTROLLER: User not logged in');
            wp_send_json_error(['message' => 'Musíte být přihlášeni']);
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_branch_switcher')) {
            error_log('❌ BRANCHES CONTROLLER: Invalid nonce');
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
            return;
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        error_log('📊 BRANCHES CONTROLLER: customer_id = ' . $customer_id);
        
        if (!$customer_id) {
            error_log('❌ BRANCHES CONTROLLER: No customer_id provided');
            wp_send_json_error(['message' => 'Chybí ID zákazníka']);
            return;
        }
        
        // Try cache first
        $cache_key = 'branches_for_switcher_' . $customer_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            error_log('✅ BRANCHES CONTROLLER: Returning cached branches');
            wp_send_json_success([
                'branches' => $cached,
                'current_branch_id' => $this->get_current_branch_id(),
                'cached' => true
            ]);
            return;
        }
        
        // Load branches from database
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        error_log('🔍 BRANCHES CONTROLLER: Querying database...');
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, code, city, street, is_headquarters, sort_order 
             FROM {$table} 
             WHERE customer_id = %d 
             AND is_active = 1 
             ORDER BY sort_order ASC, is_headquarters DESC, name ASC",
            $customer_id
        ), ARRAY_A);
        
        error_log('📦 BRANCHES CONTROLLER: Found ' . count($branches) . ' branches');
        
        // Format for dropdown
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
        
        // Cache for 30 minutes
        set_transient($cache_key, $formatted, 1800);
        
        error_log('✅ BRANCHES CONTROLLER: Sending success response');
        
        wp_send_json_success([
            'branches' => $formatted,
            'current_branch_id' => $this->get_current_branch_id()
        ]);
    }
    
    /**
     * ✅ OPRAVENO: AJAX handler pro přepnutí pobočky
     * 
     * ZMĚNY:
     * - Odstraněna kontrola current_user_can('read')
     * - Nyní stačí být přihlášený
     */
    public function ajax_switch_branch() {
        error_log('🔥 BRANCHES CONTROLLER: ajax_switch_branch() CALLED');
        
        // ✅ KRITICKÁ OPRAVA: Kontrola jenom přihlášení
        if (!is_user_logged_in()) {
            error_log('❌ BRANCHES CONTROLLER: User not logged in');
            wp_send_json_error(['message' => 'Musíte být přihlášeni']);
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_branch_switcher')) {
            error_log('❌ BRANCHES CONTROLLER: Invalid nonce');
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
            return;
        }
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        error_log('🔄 BRANCHES CONTROLLER: Switching to branch_id = ' . $branch_id);
        
        if ($branch_id <= 0) {
            // Allow clearing branch selection
            $this->clear_branch_session();
            error_log('✅ BRANCHES CONTROLLER: Branch cleared');
            wp_send_json_success([
                'message' => 'Pobočka byla odstraněna',
                'branch_id' => null
            ]);
            return;
        }
        
        // Verify branch exists and is active
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, customer_id FROM {$table} WHERE id = %d AND is_active = 1",
            $branch_id
        ), ARRAY_A);
        
        if (!$branch) {
            error_log('❌ BRANCHES CONTROLLER: Branch not found or inactive');
            wp_send_json_error(['message' => 'Pobočka nebyla nalezena']);
            return;
        }
        
        // Save to user meta (if logged in)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $customer_id = $branch['customer_id'];
            
            // Save both general and customer-specific meta
            update_user_meta($user_id, 'saw_current_branch_id', $branch_id);
            update_user_meta($user_id, 'saw_branch_customer_' . $customer_id, $branch_id);
            
            error_log('💾 BRANCHES CONTROLLER: Saved to user_meta for user ' . $user_id);
        }
        
        // Save to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_branch_id'] = $branch_id;
        
        error_log('✅ BRANCHES CONTROLLER: Branch switched to ' . $branch_id . ' (' . $branch['name'] . ')');
        
        wp_send_json_success([
            'message' => 'Pobočka byla úspěšně přepnuta',
            'branch_id' => $branch_id,
            'branch_name' => $branch['name']
        ]);
    }
    
    /**
     * Get current branch ID from session/user meta
     * 
     * @return int|null
     */
    private function get_current_branch_id() {
        // Try session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_branch_id'])) {
            return intval($_SESSION['saw_current_branch_id']);
        }
        
        // Try user meta
        if (is_user_logged_in()) {
            $meta_id = get_user_meta(get_current_user_id(), 'saw_current_branch_id', true);
            if ($meta_id) {
                $_SESSION['saw_current_branch_id'] = intval($meta_id);
                return intval($meta_id);
            }
        }
        
        return null;
    }
    
    /**
     * Clear branch from session and user meta
     */
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