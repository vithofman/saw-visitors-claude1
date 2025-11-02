<?php
/**
 * Departments Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Departments_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Departments_Model($this->config);
        
        add_action('wp_ajax_saw_get_departments_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_departments', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_departments', [$this, 'ajax_delete']);
    }
    
    protected function before_save($data) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($data['customer_id'])) {
            $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
            
            if (!$customer_id) {
                global $wpdb;
                $customer_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1");
            }
            
            $data['customer_id'] = $customer_id;
        }
        
        return $data;
    }
    
    protected function before_delete($id) {
        if ($this->model->is_used_in_system($id)) {
            wp_die('Toto oddělení nelze smazat, protože je používáno v systému (návštěvy, pozvánky nebo uživatelé).');
        }
    }
}
