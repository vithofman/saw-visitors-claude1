<?php
/**
 * Branches Module Controller
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     16.1.0 - Simplified after_save (cache handled by model)
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

    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';

        $this->config         = require $module_path . 'config.php';
        $this->entity         = $this->config['entity'];
        $this->config['path'] = $module_path;

        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);

        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();

        add_action('wp_ajax_saw_get_branches_detail',   array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_branches',       array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_branches',       array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_branches', array($this, 'ajax_load_sidebar'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function index() {
        $this->render_list_view();
    }

    public function ajax_delete() {
        saw_verify_ajax_unified();

        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => 'Chybí ID'));
            return;
        }

        if ($this->before_delete($id) === false) {
            wp_send_json_error(array('message' => 'Nelze smazat pobočku – má navázaná oddělení.'));
            return;
        }

        $result = $this->model->delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array('message' => 'Pobočka byla smazána'));
    }

    protected function prepare_form_data($post) {
        $data = array();

        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $value = $post[$field_name];

                if (isset($field_config['sanitize']) && is_callable($field_config['sanitize'])) {
                    $value = call_user_func($field_config['sanitize'], $value);
                } else {
                    $value = sanitize_text_field($value);
                }

                $data[$field_name] = $value;
            } elseif (!empty($field_config['type']) && $field_config['type'] === 'boolean') {
                $data[$field_name] = 0;
            }
        }

        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }

        if (!empty($_FILES['image_url']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['image_url'], 'branches');

            if (!is_wp_error($upload_result)) {
                $data['image_url'] = $upload_result['url'];

                if (!empty($post['id'])) {
                    $old_item = $this->model->get_by_id((int) $post['id']);
                    if (!empty($old_item['image_url'])) {
                        $this->file_uploader->delete($old_item['image_url']);
                    }
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

        if (!empty($item['image_url']) && strpos($item['image_url'], 'http') !== 0) {
            $upload_dir        = wp_upload_dir();
            $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_url'], '/');
        }

        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        $item['is_active_label']       = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';

        return $item;
    }

    protected function before_save($data, $id = null) {
        global $wpdb;

        if (!empty($data['is_headquarters'])) {
            $customer_id = !empty($data['customer_id']) ? (int) $data['customer_id'] : 0;

            if (!$customer_id && $id) {
                $existing = $this->model->get_by_id($id);
                $customer_id = !empty($existing['customer_id']) ? (int) $existing['customer_id'] : 0;
            }

            if (!$customer_id && class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }

            if ($customer_id) {
                $table = $wpdb->prefix . 'saw_branches';

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} 
                         SET is_headquarters = 0 
                         WHERE customer_id = %d",
                        $customer_id
                    )
                );
            }
        }

        return $data;
    }

    protected function before_delete($id) {
        global $wpdb;

        $departments_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE branch_id = %d",
            $id
        ));

        if ($departments_count > 0) {
            $this->set_flash(
                sprintf('Nelze smazat pobočku – obsahuje %d oddělení', $departments_count),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Auto-assign Czech language to new branch
     * 
     * Model already invalidated cache, so we don't need to here.
     */
    protected function after_save($id) {
        global $wpdb;
        
        // Get branch (bypass cache if needed)
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$branch) {
            return;
        }
        
        $customer_id = $branch['customer_id'];
        
        // Find or create Czech language
        $czech_lang = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = 'cs'",
            $customer_id
        ));
        
        if (!$czech_lang) {
            $wpdb->insert(
                $wpdb->prefix . 'saw_training_languages',
                array(
                    'customer_id' => $customer_id,
                    'language_code' => 'cs',
                    'language_name' => 'Čeština',
                    'flag_emoji' => '🇨🇿',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                )
            );
            
            $czech_lang = (object) array('id' => $wpdb->insert_id);
        }
        
        // Check if already assigned
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_language_branches 
             WHERE language_id = %d AND branch_id = %d",
            $czech_lang->id,
            $id
        ));
        
        if ($existing) {
            return;
        }
        
        // Check if first branch
        $branch_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d",
            $customer_id
        ));
        
        // Assign Czech to branch
        $wpdb->insert(
            $wpdb->prefix . 'saw_training_language_branches',
            array(
                'language_id' => $czech_lang->id,
                'branch_id' => $id,
                'is_default' => ($branch_count == 1) ? 1 : 0,
                'is_active' => 1,
                'display_order' => 0,
                'created_at' => current_time('mysql'),
            )
        );
    }

    protected function enqueue_assets() {
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('branches');
        }

        wp_enqueue_style(
            'saw-file-upload',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/forms.css', // Consolidated: includes file-upload styles
            array(),
            SAW_VISITORS_VERSION
        );

        wp_enqueue_script(
            'saw-file-upload',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/components/forms.js', // Consolidated: includes file-upload
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
}