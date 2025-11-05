<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/account-types/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Account_Types_Model($this->config);
        
        add_action('wp_ajax_saw_get_account_types_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_account_types', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_account_types', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
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
    
    public function create() {
        echo '<h1>CREATE PAGE</h1>';
    }
    
    public function edit($id) {
        echo '<h1>EDIT PAGE: ' . $id . '</h1>';
    }
    
    public function enqueue_assets() {
        // Empty for now
    }
    
    /**
     * Format detail data for modal
     * CRITICAL: This is called by ajax_get_detail from trait
     */
    protected function format_detail_data($item) {
        $item = parent::format_detail_data($item);
        
        // Format features
        if (!empty($item['features'])) {
            $features = json_decode($item['features'], true);
            $item['features_array'] = is_array($features) ? $features : [];
        } else {
            $item['features_array'] = [];
        }
        
        // Format price
        if (isset($item['price'])) {
            $item['price_formatted'] = number_format($item['price'], 2, ',', ' ') . ' Kc';
        }
        
        // Format status
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivni' : 'Neaktivni';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge-success' : 'saw-badge-secondary';
        
        return $item;
    }
}