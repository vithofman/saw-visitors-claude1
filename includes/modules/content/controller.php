<?php
/**
 * Content Module Controller
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Content_Controller 
{
    protected $config;
    protected $model;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/content/';
        
        $this->config = require $module_path . 'config.php';
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Content_Model();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets() {
        // WordPress media library
        wp_enqueue_media();
        
        wp_enqueue_style(
            'saw-content-module',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/content/content.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-content-module',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/content/content.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
    
    public function index() {
        $role = $this->get_current_role();
        
        // Manager může vidět POUZE sekci oddělení, ostatní role vidí vše
        if ($role !== 'admin' && $role !== 'super_admin' && $role !== 'manager') {
            wp_die(
                'Nemáte oprávnění zobrazit tuto stránku.',
                'Přístup odepřen',
                array('response' => 403)
            );
        }
        
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            wp_die(
                'Nebylo možné určit vašeho zákazníka.',
                'Chyba konfigurace',
                array('response' => 500)
            );
        }
        
        $languages = $this->model->get_training_languages($customer_id);
        
        if (empty($languages)) {
            wp_die(
                'Nejdříve musíte vytvořit alespoň jeden jazyk školení.',
                'Žádné jazyky',
                array('response' => 404)
            );
        }
        
        // CRITICAL: Load WordPress media templates
        add_action('admin_footer', 'wp_print_media_templates');
        add_action('wp_footer', 'wp_print_media_templates');
        
        // CRITICAL: Give admin and manager full editor capabilities
        add_filter('user_has_cap', function($allcaps) use ($role) {
            if ($role === 'admin' || $role === 'super_admin' || $role === 'manager') {
                $allcaps['edit_posts'] = true;
                $allcaps['upload_files'] = true;
                $allcaps['edit_files'] = true;
            }
            return $allcaps;
        });
        
        $view_data = array(
            'icon' => $this->config['icon'],
            'languages' => $languages,
            'customer_id' => $customer_id,
        );
        
        $this->render_view($view_data);
    }
    
    private function render_view($view_data) {
        if (!class_exists('SAW_App_Layout')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-layout.php';
        }
        
        ob_start();
        
        extract($view_data);
        
        $template_path = $this->config['path'] . 'view.php';
        if (file_exists($template_path)) {
            require $template_path;
        }
        
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, 'Správa obsahu', 'content');
    }
    
    private function get_current_role() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        return null;
    }
}
