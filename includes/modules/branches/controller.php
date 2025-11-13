<?php
/**
 * Branches Module Controller - FINAL COMPLETE VERSION
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     14.4.0 - stabilní HQ logika v after_save
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

    /**
     * File uploader instance
     *
     * @var SAW_File_Uploader
     */
    private $file_uploader;

    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';

        $this->config         = require $module_path . 'config.php';
        $this->entity         = $this->config['entity']; // 'branches'
        $this->config['path'] = $module_path;

        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);

        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();

        // AJAX handlers
        add_action('wp_ajax_saw_get_branches_detail',   array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_branches',       array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_branches',       array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_branches', array($this, 'ajax_load_sidebar'));

        // Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * List stránka (použije Base::render_list_view)
     */
    public function index() {
        $this->render_list_view();
    }

    /**
     * AJAX delete handler
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');

        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => 'Chybí ID'));
            return;
        }

        // Kontrola návazností
        if ($this->before_delete($id) === false) {
            wp_send_json_error(array('message' => 'Nelze smazat pobočku – má navázaná oddělení.'));
            return;
        }

        $result = $this->model->delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Po smazání jen smažeme cache
        $this->invalidate_caches();

        wp_send_json_success(array('message' => 'Pobočka byla smazána'));
    }

    /**
     * Příprava dat z formuláře
     */
    protected function prepare_form_data($post) {
        $data = array();

        // Všechna pole definovaná v configu
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
                // Checkboxy = 0, když v POST nejsou
                $data[$field_name] = 0;
            }
        }

        // customer_id z kontextu, pokud není v POST
        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }

        // Upload obrázku (logo pobočky)
        if (!empty($_FILES['image_url']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['image_url'], 'branches');

            if (!is_wp_error($upload_result)) {
                $data['image_url'] = $upload_result['url'];

                // Při editaci smazat starý obrázek
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

    /**
     * Format detailu pro sidebar
     */
    protected function format_detail_data($item) {
        // Datum vytvoření / změny
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }

        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }

        // Obrázek – relativní cesta → absolutní URL
        if (!empty($item['image_url']) && strpos($item['image_url'], 'http') !== 0) {
            $upload_dir        = wp_upload_dir();
            $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_url'], '/');
        }

        // HQ / aktivní labely
        $item['is_headquarters_label'] = !empty($item['is_headquarters']) ? 'Ano' : 'Ne';
        $item['is_active_label']       = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';

        return $item;
    }

    /**
     * Before delete – kontrola, jestli má pobočka oddělení
     */
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
     * After save – zde vynutíme „maximálně jedno sídlo na zákazníka“
     *
     * Volá se jak po create, tak po update (Base controller).
     */
    protected function after_save($id) {
        global $wpdb;

        // Invalidace cache, ať taháme čerstvá data
        $this->invalidate_caches();

        $branch = $this->model->get_by_id($id);
        if (!$branch) {
            return;
        }

        $customer_id = !empty($branch['customer_id']) ? (int) $branch['customer_id'] : 0;
        if (!$customer_id) {
            return;
        }

        // Pokud tato pobočka NENÍ sídlo, nic dál nevynucujeme
        if (empty($branch['is_headquarters'])) {
            return;
        }

        $table = $wpdb->prefix . 'saw_branches';

        // ✅ Všechny OSTATNÍ pobočky stejného zákazníka přepnout na 0
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} 
                 SET is_headquarters = 0 
                 WHERE customer_id = %d AND id != %d AND is_headquarters = 1",
                $customer_id,
                (int) $id
            )
        );

        // Aktuální záznam už má po uložení správnou hodnotu (1),
        // není potřeba ho znovu přepisovat.

        // Cache po HQ změně už jsme invalidovali na začátku.
    }

    /**
     * Invalidate all module caches
     */
    private function invalidate_caches() {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->options,
            $wpdb->esc_like('_transient_branches_') . '%',
            $wpdb->esc_like('_transient_timeout_branches_') . '%'
        ));
    }

    /**
     * Enqueue module assets
     */
    protected function enqueue_assets() {
        if (class_exists('SAW_Asset_Manager')) {
            SAW_Asset_Manager::enqueue_module('branches');
        }

        // File upload assets
        wp_enqueue_style(
            'saw-file-upload',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/file-upload/file-upload.css',
            array(),
            SAW_VISITORS_VERSION
        );

        wp_enqueue_script(
            'saw-file-upload',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/file-upload/file-upload.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
}
