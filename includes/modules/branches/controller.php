<?php
/**
 * Branches Module Controller
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $media_uploader;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/media-uploader/class-saw-media-uploader.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/media-uploader/class-saw-media-uploader.php';
            $this->media_uploader = new SAW_Media_Uploader();
        }
        
        add_action('wp_ajax_saw_get_branches_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_branches', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_branches', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        if ($this->media_uploader) {
            $this->media_uploader->enqueue_assets();
        }
    }
    
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        $item['full_address'] = $this->model->get_full_address($item);
        $item['opening_hours_array'] = $this->model->get_opening_hours_as_array($item['opening_hours'] ?? null);
        $item['has_gps'] = !empty($item['latitude']) && !empty($item['longitude']);
        
        if ($item['has_gps']) {
            $item['google_maps_url'] = sprintf(
                'https://www.google.com/maps/search/?api=1&query=%s,%s',
                $item['latitude'],
                $item['longitude']
            );
        }
        
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge-success' : 'saw-badge-secondary';
        
        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        $item['is_headquarters_badge_class'] = !empty($item['is_headquarters']) ? 'saw-badge-info' : 'saw-badge-secondary';
        
        $item['country_name'] = $this->get_country_name($item['country'] ?? 'CZ');
        
        return $item;
    }
    
    private function get_country_name($code) {
        $countries = [
            'CZ' => 'Česká republika',
            'SK' => 'Slovensko',
            'DE' => 'Německo',
            'AT' => 'Rakousko',
            'PL' => 'Polsko',
        ];
        
        return $countries[$code] ?? $code;
    }
    
    protected function before_delete($id) {
        if ($this->model->is_used_in_system($id)) {
            return new WP_Error(
                'cannot_delete_in_use',
                'Tuto pobočku nelze smazat, protože je používána v systému. Nejdříve odeberte všechny vazby na tuto pobočku.'
            );
        }
        
        return true;
    }
    
    protected function after_save($id) {
        // ✅ OPRAVA - smazání cache BEZ volání neexistující funkce
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_branches_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_branches_%'");
    }
}