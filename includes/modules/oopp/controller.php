<?php
/**
 * OOPP Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_OOPP_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_OOPP_Model($this->config);
        
        // File uploader pro obrázky
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // Register assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Index - seznam OOPP
     */
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    /**
     * Enqueue module assets
     */
    public function enqueue_assets() {
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('oopp');
        }
        
        // File upload komponenta
        if (class_exists('SAW_Component_File_Upload')) {
            SAW_Component_File_Upload::enqueue_assets();
        }
        
        // Localize script
        wp_localize_script('saw-module-oopp', 'sawOOPPData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'groups' => $this->model->get_groups(),
        ));
    }
    
    /**
     * Příprava dat z formuláře
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Customer ID (automaticky z kontextu)
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        // Základní pole
        $text_fields = array('name', 'standards', 'risk_description', 'protective_properties', 
                            'usage_instructions', 'maintenance_instructions', 'storage_instructions');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_textarea_field($post[$field]);
            }
        }
        
        // Skupina
        if (isset($post['group_id'])) {
            $data['group_id'] = intval($post['group_id']);
        }
        
        // Pořadí
        if (isset($post['display_order'])) {
            $data['display_order'] = intval($post['display_order']);
        }
        
        // Aktivní
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        // Pobočky (pole IDs)
        $data['branch_ids'] = array();
        if (isset($post['branch_ids']) && is_array($post['branch_ids'])) {
            $data['branch_ids'] = array_map('intval', $post['branch_ids']);
        }
        
        // Oddělení (pole IDs)
        $data['department_ids'] = array();
        if (isset($post['department_ids']) && is_array($post['department_ids'])) {
            $data['department_ids'] = array_map('intval', $post['department_ids']);
        }
        
        return $data;
    }
    
    /**
     * Before save - nastav customer_id
     */
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            if (class_exists('SAW_Context')) {
                $data['customer_id'] = SAW_Context::get_customer_id();
            }
        }
        return $data;
    }
    
    /**
     * After save - zpracuj file upload
     */
    protected function after_save($id) {
        // Handle image upload
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = $this->file_uploader->upload(
                $_FILES['image'],
                'oopp'
            );
            
            if (!is_wp_error($upload_result)) {
                global $wpdb;
                
                // Extract relative path from full path
                $upload_dir = wp_upload_dir();
                $relative_path = str_replace($upload_dir['basedir'] . '/', '', $upload_result['path']);
                
                $wpdb->update(
                    $this->model->table,
                    array('image_path' => $relative_path),
                    array('id' => $id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        // Handle image removal
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            global $wpdb;
            
            // Get current image path for deletion
            $current_path = $wpdb->get_var($wpdb->prepare(
                "SELECT image_path FROM {$this->model->table} WHERE id = %d",
                $id
            ));
            
            // Remove file if exists
            if ($current_path) {
                $upload_dir = wp_upload_dir();
                $full_path = $upload_dir['basedir'] . '/' . ltrim($current_path, '/');
                
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
            
            // Clear path in DB
            $wpdb->update(
                $this->model->table,
                array('image_path' => null),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Format detail data - přidej extra info pro sidebar
     */
    protected function format_detail_data($item) {
        if (empty($item) || empty($item['id'])) {
            return $item;
        }
        
        global $wpdb;
        
        // Načti group info
        if (!empty($item['group_id'])) {
            $group = $wpdb->get_row($wpdb->prepare(
                "SELECT code, name FROM {$wpdb->prefix}saw_oopp_groups WHERE id = %d",
                $item['group_id']
            ), ARRAY_A);
            
            if ($group) {
                $item['group_code'] = $group['code'];
                $item['group_name'] = $group['name'];
                $item['group_display'] = $group['code'] . '. ' . $group['name'];
            }
        }
        
        // Načti vazby na pobočky
        $item['branch_ids'] = $this->model->get_branch_ids($item['id']);
        
        if (!empty($item['branch_ids'])) {
            $placeholders = implode(',', array_fill(0, count($item['branch_ids']), '%d'));
            $branches = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE id IN ($placeholders) ORDER BY name",
                ...$item['branch_ids']
            ), ARRAY_A);
            $item['branches'] = $branches;
        } else {
            $item['branches'] = array();
            $item['branches_all'] = true;  // Platí pro všechny pobočky
        }
        
        // Načti vazby na oddělení
        $item['department_ids'] = $this->model->get_department_ids($item['id']);
        
        if (!empty($item['department_ids'])) {
            $placeholders = implode(',', array_fill(0, count($item['department_ids']), '%d'));
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}saw_departments WHERE id IN ($placeholders) ORDER BY name",
                ...$item['department_ids']
            ), ARRAY_A);
            $item['departments'] = $departments;
        } else {
            $item['departments'] = array();
            $item['departments_all'] = true;  // Platí pro všechna oddělení
        }
        
        // Image URL
        if (!empty($item['image_path'])) {
            $upload_dir = wp_upload_dir();
            $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_path'], '/');
        }
        
        // Formatted dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * AJAX: Získání OOPP skupin
     */
    public function ajax_get_oopp_groups() {
        saw_verify_ajax_unified();
        
        $groups = $this->model->get_groups();
        
        wp_send_json_success(array(
            'groups' => $groups,
        ));
    }
    
    /**
     * AJAX: Uložení vazeb na pobočky
     */
    public function ajax_save_branches() {
        saw_verify_ajax_unified();
        
        if (!$this->can('edit')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $oopp_id = intval($_POST['oopp_id'] ?? 0);
        $branch_ids = isset($_POST['branch_ids']) ? array_map('intval', (array)$_POST['branch_ids']) : array();
        
        if (!$oopp_id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
        }
        
        $this->model->save_branch_relations($oopp_id, $branch_ids);
        
        wp_send_json_success(array(
            'message' => 'Pobočky uloženy',
            'branch_count' => count($branch_ids),
        ));
    }
    
    /**
     * AJAX: Uložení vazeb na oddělení
     */
    public function ajax_save_departments() {
        saw_verify_ajax_unified();
        
        if (!$this->can('edit')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $oopp_id = intval($_POST['oopp_id'] ?? 0);
        $department_ids = isset($_POST['department_ids']) ? array_map('intval', (array)$_POST['department_ids']) : array();
        
        if (!$oopp_id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
        }
        
        $this->model->save_department_relations($oopp_id, $department_ids);
        
        wp_send_json_success(array(
            'message' => 'Oddělení uložena',
            'department_count' => count($department_ids),
        ));
    }
    
    /**
     * AJAX: Získání OOPP pro oddělení (pro training flow)
     */
    public function ajax_get_for_department() {
        saw_verify_ajax_unified();
        
        $department_id = intval($_POST['department_id'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);
        
        if (!$department_id) {
            wp_send_json_error(array('message' => 'Neplatné oddělení'));
        }
        
        $oopp_items = $this->model->get_for_department($department_id, $branch_id);
        
        wp_send_json_success(array(
            'items' => $oopp_items,
            'count' => count($oopp_items),
        ));
    }
}

