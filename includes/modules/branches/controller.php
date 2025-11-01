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
    
    private $file_uploader;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        add_action('wp_ajax_saw_get_branches_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_branches', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_branches', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
    }
    
    protected function before_save($data) {
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
            $upload = $this->file_uploader->upload($_FILES['image_url'], 'customers');
            
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
        
        // ✅ FIX: Explicitní == 1 check místo !empty() kvůli string "0" vs int 0
        $item['is_active_label'] = ($item['is_active'] == 1) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = ($item['is_active'] == 1) ? 'saw-badge-success' : 'saw-badge-secondary';
        
        $item['is_headquarters_label'] = ($item['is_headquarters'] == 1) ? 'Ano' : 'Ne';
        $item['is_headquarters_badge_class'] = ($item['is_headquarters'] == 1) ? 'saw-badge-info' : 'saw-badge-secondary';
        
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
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_branches_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_branches_%'");
    }
}