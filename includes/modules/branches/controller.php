<?php
/**
 * Branches Module Controller - FIXED VERSION v1.2.0
 * 
 * CRITICAL FIXES:
 * - ✅ REMOVED duplicate AJAX handlers (ajax_get_branches_for_switcher, ajax_switch_branch)
 *   These are now handled ONLY by SAW_Component_Branch_Switcher
 * - ✅ Fixed __DIR__ issue for lazy loading
 * - ✅ Kept only module-specific handlers (detail, search, delete)
 * 
 * PRESERVED:
 * - ✅ All module functionality (list, create, edit, delete)
 * - ✅ File upload handling
 * - ✅ Cache invalidation
 * 
 * @package SAW_Visitors
 * @version 1.2.0 - LAZY LOADING FIX
 * @since 4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    
    public function __construct() {
        // ✅ FIX: Use absolute path instead of __DIR__ for lazy loading
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // ================================================
        // ✅ CRITICAL FIX: REMOVED DUPLICATE AJAX HANDLERS
        // ================================================
        // These are now handled by SAW_Component_Branch_Switcher:
        // - ajax_get_branches_for_switcher (REMOVED)
        // - ajax_switch_branch (REMOVED)
        //
        // Only module-specific handlers remain:
        add_action('wp_ajax_saw_get_branches_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_branches', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_branches', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
    }
    
    /**
     * Before save hook
     * Handle file uploads
     * 
     * @param array $data
     * @return array
     */
    protected function before_save($data) {
        // Handle file removal
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
        
        // Handle file upload
        if (!empty($_FILES['image_url']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['image_url'], 'branches');
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['image_url'] = $upload['url'];
            $data['image_thumbnail'] = $upload['url'];
            
            // Delete old file if exists
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
     * Invalidate cache for branch switcher
     * 
     * @param int $id
     */
    protected function after_save($id) {
        $branch = $this->model->get_by_id($id);
        
        if (!empty($branch['customer_id'])) {
            // Invalidate branch switcher cache
            delete_transient('branches_for_switcher_' . $branch['customer_id']);
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Branches Controller] Cache invalidated for customer %d after saving branch %d',
                    $branch['customer_id'],
                    $id
                ));
            }
        }
    }
}