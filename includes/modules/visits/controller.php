<?php
/**
 * Visits Module Controller
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.1.0 - FIXED: Simplified to match Departments pattern
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
if (!trait_exists('SAW_AJAX_Handlers')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';

class SAW_Module_Visits_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visits_Model($this->config);
        
        // Register custom AJAX
        add_action('wp_ajax_saw_get_hosts_by_branch', array($this, 'ajax_get_hosts_by_branch'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    public function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('visits');
        
        // Pass existing hosts to JS if editing
        $existing_hosts = array();
        if (isset($_GET['id'])) {
            global $wpdb;
            $existing_hosts = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                intval($_GET['id'])
            ));
            $existing_hosts = array_map('intval', $existing_hosts);
        }
        
        wp_localize_script('saw-visits', 'sawVisits', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'existing_hosts' => $existing_hosts
        ));
    }

    protected function prepare_form_data($post) {
        $data = array();
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $sanitize = $field_config['sanitize'] ?? 'sanitize_text_field';
                $data[$field_name] = $sanitize($post[$field_name]);
            }
        }
        return $data;
    }
    
    protected function before_save($data) {
        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        return $data;
    }

    protected function format_detail_data($item) {
        if (empty($item)) return $item;
        
        if (!empty($item['company_id'])) {
            global $wpdb;
            $company = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_companies WHERE id = %d", $item['company_id']), ARRAY_A);
            $item['company_data'] = $company;
        }
        
        if (!empty($item['branch_id'])) {
            global $wpdb;
            $branch = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d", $item['branch_id']), ARRAY_A);
            if ($branch) $item['branch_name'] = $branch['name'];
        }
        
        return $item;
    }
    
    public function ajax_get_hosts_by_branch() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        global $wpdb;
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, role FROM {$wpdb->prefix}saw_users WHERE branch_id = %d AND is_active = 1 ORDER BY last_name, first_name",
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(array('hosts' => $hosts));
    }
}
