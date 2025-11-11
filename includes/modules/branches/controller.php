<?php
/**
 * Branches Module Controller
 *
 * REFACTORED to new architecture. Extends SAW_Base_Controller.
 * UPDATED (v12.0.7) - CRITICAL FIX: Renamed ajax_get_gps() to
 * ajax_get_gps_coordinates() to match the universal AJAX handler.
 * Removed all ajax add_action() calls.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @since       9.0.0 (Refactored)
 * @version     12.0.7 (AJAX-Rename-Fix)
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

class SAW_Module_Branches_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;

    private $file_uploader;

    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // Všechny AJAX add_action() jsou odstraněny (řeší saw-visitors.php)
        
        // Registrace pro CSS/JS zůstává
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Display list page with optional sidebar
     */
    public function index() {
        $this->render_list_view();
    }

    /**
     * AJAX delete handler - OVERRIDE
     * (Tato metoda je nyní volána přes saw_universal_ajax_handler)
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('delete')) { 
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
            return;
        }
        
        $item = $this->model->get_by_id($id);
        if (!$item) {
            wp_send_json_error(array('message' => 'Pobočka nenalezena'));
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(array('message' => $before_delete_result->get_error_message()));
            return;
        }
        
        $result = $this->model->delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $this->after_delete($id);
        wp_send_json_success(array('message' => 'Pobočka byla úspěšně smazána'));
    }

    /**
     * Prepare form data from POST
     */
    protected function prepare_form_data($post) {
        $data = parent::prepare_form_data($post);
        
        $data['customer_id'] = SAW_Context::get_active_customer_id();
        if (empty($data['customer_id'])) {
            return new WP_Error('no_customer_context', __('Není vybrán žádný aktivní zákazník.', 'saw-visitors'));
        }

        if (!empty($_FILES['image_url']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['image_url'], 'branches');
            if (is_wp_error($upload_result)) {
                return $upload_result;
            }
            if (isset($post['id'])) {
                $old_item = $this->model->get_by_id($post['id'], true);
                if (!empty($old_item['image_url'])) {
                    $this->file_uploader->delete($old_item['image_url']);
                }
            }
            $data['image_url'] = $upload_result['url'];
        }

        $opening_hours = $post['opening_hours'] ?? array();
        $data['opening_hours'] = wp_json_encode($opening_hours);
        
        $data['is_headquarters'] = !empty($post['is_headquarters']) ? 1 : 0;
        $data['is_active'] = !empty($post['is_active']) ? 1 : 0;
        $data['sort_order'] = !empty($post['sort_order']) ? intval($post['sort_order']) : 10;
        $data['country'] = $post['country'] ?? 'CZ';
        $data['description'] = $post['description'] ?? null;
        $data['metadata'] = $post['metadata'] ?? null;
        
        return $data;
    }

    /**
     * Before Save Hook
     */
    protected function before_save($id, $data) {
        if (!empty($data['is_headquarters'])) {
            $customer_id = $data['customer_id'] ?? SAW_Context::get_active_customer_id();
            $this->model->set_new_headquarters($id, $customer_id);
        }
        return $data;
    }

    /**
     * Before Delete Hook
     */
    protected function before_delete($id) {
        $item = $this->model->get_by_id($id);
        if ($item && $item['is_headquarters']) {
            $count = $this->model->get_headquarters_count($item['customer_id']);
            if ($count <= 1) {
                return new WP_Error('delete_last_hq', __('Nelze smazat poslední sídlo firmy. Nejprve označte jako sídlo jinou pobočku.', 'saw-visitors'));
            }
        }
        return true;
    }
    
    /**
     * Format data for detail view
     */
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        $item['opening_hours_array'] = json_decode($item['opening_hours'] ?? '[]', true);
        if (!is_array($item['opening_hours_array'])) {
            $item['opening_hours_array'] = array();
        }
        $address_parts = array();
        if (!empty($item['street'])) $address_parts[] = $item['street'];
        if (!empty($item['city']))   $address_parts[] = $item['city'];
        if (!empty($item['postal_code']))    $address_parts[] = $item['postal_code'];
        $item['full_address'] = implode(', ', $address_parts);
        if (!empty($item['latitude']) && !empty($item['longitude'])) {
            $item['map_link'] = sprintf('http://googleusercontent.com/maps/google.com/0', $item['latitude'], $item['longitude']);
        } elseif (!empty($item['full_address'])) {
            $item['map_link'] = 'http://googleusercontent.com/maps/google.com/1' . urlencode($item['full_address']);
        } else {
            $item['map_link'] = null;
        }
        if (!empty($item['image_url'])) {
            if (strpos($item['image_url'], 'http') !== 0) {
                $upload_dir = wp_upload_dir();
                $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_url'], '/');
            }
        }
        return $item;
    }

    /**
     * Enqueue module assets
     */
    protected function enqueue_assets() {
        if (SAW_Router::get_instance()->get_current_module() === $this->entity) {
            $version = defined('SAW_VISITORS_VERSION') ? SAW_VISITORS_VERSION : time();
            $module_url = SAW_VISITORS_PLUGIN_URL . 'includes/modules/branches/';
            
            wp_enqueue_style(
                'saw-module-branches',
                $module_url . 'assets/styles.css',
                array(),
                $version
            );
            
            wp_enqueue_script(
                'saw-module-branches',
                $module_url . 'assets/scripts.js',
                array('jquery'),
                $version,
                true
            );
            
            wp_localize_script('saw-module-branches', 'sawBranches', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_ajax_nonce'),
                'text' => array(
                    'loadingGps' => __('Načítám GPS...', 'saw-visitors'),
                    'loadGps' => __('Načíst souřadnice', 'saw-visitors'),
                    'hqWarning' => __('Tato akce přesune označení "Sídlo" z jiné pobočky. Pokračovat?', 'saw-visitors'),
                ),
            ));
        }
        
        $this->file_uploader->enqueue_assets();
    }
    
    // ============================================
    // MODULE-SPECIFIC AJAX
    // ============================================

    public function ajax_get_gps_coordinates() { // Přejmenováno pro univerzální handler
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('edit') && !$this->can('create')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění.'));
        }
        
        $address = sanitize_text_field($_POST['address'] ?? '');
        if (empty($address)) {
            wp_send_json_error(array('message' => 'Adresa je prázdná.'));
        }
        
        $coords = $this->model->get_gps_coordinates($address);
        
        if (is_wp_error($coords)) {
            wp_send_json_error(array('message' => $coords->get_error_message()));
        } else {
            wp_send_json_success($coords);
        }
    }

    public function ajax_check_headquarters() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $customer_id = $this->get_current_customer_id(); // Použití helperu
        $exclude_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $hq_exists = $this->model->get_headquarters_count($customer_id, $exclude_id);
        
        wp_send_json_success(array('hq_exists' => $hq_exists));
    }

    /**
     * *** HELPER (Copied from customers/model.php) ***
     * Get current customer ID from context or user data
     */
    private function get_current_customer_id() {
        if (class_exists('SAW_Context')) {
            $context_id = SAW_Context::get_customer_id();
            if ($context_id) {
                return $context_id;
            }
        }

        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            $saw_user = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                    get_current_user_id()
                ),
                ARRAY_A
            );
            if ($saw_user && $saw_user['customer_id']) {
                return intval($saw_user['customer_id']);
            }
        }
        
        if (class_exists('SAW_Context')) {
             return SAW_Context::get_active_customer_id();
        }

        return null;
    }
}