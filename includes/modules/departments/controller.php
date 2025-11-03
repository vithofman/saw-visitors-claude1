<?php
/**
 * Departments Module Controller - CLEANED
 * 
 * ✅ NO HARDCODED FILTERS - only permissions-based scope
 * 
 * @package SAW_Visitors
 * @version 1.0.1
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
    
    protected function before_delete($id) {
        if ($this->model->is_used_in_system($id)) {
            return new WP_Error(
                'department_in_use',
                'Toto oddělení nelze smazat, protože je používáno v systému (návštěvy, pozvánky nebo uživatelé).'
            );
        }
        
        return true;
    }
}