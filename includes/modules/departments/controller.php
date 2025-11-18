<?php
/**
 * Departments Module Controller
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     3.0.0 - CLEAN REVERT with RBAC
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
if (!trait_exists('SAW_AJAX_Handlers')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';

class SAW_Module_Departments_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/departments/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Departments_Model($this->config);
    }
    
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }

    protected function prepare_form_data($post) {
        $data = array();
        if (isset($post['customer_id'])) $data['customer_id'] = intval($post['customer_id']);
        if (isset($post['branch_id'])) $data['branch_id'] = intval($post['branch_id']);
        $fields = array('department_number', 'name', 'description');
        foreach ($fields as $f) if (isset($post[$f])) $data[$f] = sanitize_text_field($post[$f]);
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        return $data;
    }
    
    protected function before_save($data) {
        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        // Pobočku necháme na uživateli/formuláři nebo na Base Controlleru
        return $data;
    }

    protected function format_detail_data($item) {
        global $wpdb;
        if (!empty($item['branch_id'])) {
            $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d", $item['branch_id']));
            if ($name) $item['branch_name'] = $name;
        }
        return $item;
    }
}