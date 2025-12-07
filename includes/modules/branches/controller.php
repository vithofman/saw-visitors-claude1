<?php
/**
 * Branches Module Controller
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     17.2.0 - ADDED: Translation support for all messages
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
    
    /** @var array Translation strings */
    private $translations = array();

    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/branches/';

        $this->config         = require $module_path . 'config.php';
        $this->entity         = $this->config['entity'];
        $this->config['path'] = $module_path;

        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Branches_Model($this->config);

        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // Initialize translations
        $this->init_translations();

        add_action('wp_ajax_saw_get_branches_detail',   array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_branches',       array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_branches',       array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_branches', array($this, 'ajax_load_sidebar'));
        add_action('wp_ajax_saw_get_adjacent_branches', array($this, 'ajax_get_adjacent_id'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Initialize translations
     */
    private function init_translations() {
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $this->translations = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'branches') 
            : array();
    }
    
    /**
     * Get translation
     * 
     * @param string $key Translation key
     * @param string $fallback Fallback text
     * @return string
     */
    private function tr($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }

    /**
     * Override ajax_get_adjacent_id for branches
     * Branches table doesn't have branch_id column - it IS the branches table
     * Only filter by customer_id
     * 
     * @since 17.1.0
     * @return void Outputs JSON
     */
    public function ajax_get_adjacent_id() {
        saw_verify_ajax_unified();
        
        if (!$this->can('view')) {
            wp_send_json_error(array('message' => $this->tr('error_no_view_permission', 'Nemáte oprávnění zobrazit záznamy')));
            return;
        }
        
        $current_id = intval($_POST['id'] ?? 0);
        $direction = sanitize_text_field($_POST['direction'] ?? 'next');
        
        if (!$current_id) {
            wp_send_json_error(array('message' => $this->tr('error_missing_id', 'Chybí ID záznamu')));
            return;
        }
        
        if (!in_array($direction, array('next', 'prev'))) {
            wp_send_json_error(array('message' => $this->tr('error_invalid_direction', 'Neplatný směr navigace')));
            return;
        }
        
        $current_item = $this->model->get_by_id($current_id);
        if (!$current_item) {
            wp_send_json_error(array('message' => $this->tr('error_not_found', 'Záznam nenalezen')));
            return;
        }
        
        $customer_id = SAW_Context::get_customer_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        $where = array('1=1');
        $where_values = array();
        
        if ($customer_id) {
            $where[] = 'customer_id = %d';
            $where_values[] = $customer_id;
        }
        
        $where_clause = implode(' AND ', $where);
        $query = "SELECT id FROM {$table} WHERE {$where_clause} ORDER BY name ASC, id ASC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $ids = $wpdb->get_col($query);
        
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => $this->tr('error_database', 'Chyba databáze')));
            return;
        }
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => $this->tr('error_no_records', 'Žádné záznamy nenalezeny')));
            return;
        }
        
        $ids = array_map('intval', $ids);
        $current_id = intval($current_id);
        $current_index = array_search($current_id, $ids, true);
        
        if ($current_index === false) {
            wp_send_json_error(array('message' => $this->tr('error_not_in_list', 'Aktuální záznam není v seznamu')));
            return;
        }
        
        if ($direction === 'next') {
            $adjacent_index = ($current_index + 1) % count($ids);
        } else {
            $adjacent_index = ($current_index - 1 + count($ids)) % count($ids);
        }
        
        $adjacent_id = $ids[$adjacent_index];
        
        if (!$adjacent_id) {
            wp_send_json_error(array('message' => $this->tr('error_adjacent_not_found', 'Nepodařilo se najít sousední záznam')));
            return;
        }
        
        $route = $this->config['route'] ?? $this->entity;
        $detail_url = home_url('/admin/' . $route . '/' . $adjacent_id . '/');
        
        wp_send_json_success(array(
            'id' => $adjacent_id,
            'url' => $detail_url,
        ));
    }

    public function index() {
        $this->render_list_view();
    }

    /**
     * Override get_list_data for tabs + infinite scroll support
     */
    protected function get_list_data() {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'is_headquarters';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        if ($infinite_scroll_enabled) {
            $per_page = ($page === 1) 
                ? ($this->config['infinite_scroll']['initial_load'] ?? 100)
                : ($this->config['infinite_scroll']['per_page'] ?? 50);
        } else {
            $per_page = $this->config['list_config']['per_page'] ?? 20;
        }
        
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => $per_page,
        );
        
        $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
        
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            $url_value = isset($_GET[$tab_param]) ? sanitize_text_field(wp_unslash($_GET[$tab_param])) : null;
            
            if ($url_value === null || $url_value === '') {
                $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
            } else {
                $tab_found = false;
                foreach ($this->config['tabs']['tabs'] as $tab_key => $tab_config) {
                    if ($tab_config['filter_value'] !== null && 
                        (string)$tab_config['filter_value'] === (string)$url_value) {
                        $current_tab = (string)$tab_key;
                        $tab_found = true;
                        break;
                    }
                }
                
                if (!$tab_found) {
                    $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
                }
                
                if ($url_value !== null && $url_value !== '') {
                    $filters['tab'] = $url_value;
                }
            }
        }
        
        $data = $this->model->get_all($filters);
        $items = $data['items'] ?? array();
        $total = $data['total'] ?? 0;
        $total_pages = ceil($total / $per_page);
        
        $tab_counts = array();
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_counts = $this->model->get_tab_counts();
        }
        
        return array(
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => $total_pages,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'current_tab' => $current_tab,
            'tab_counts' => $tab_counts,
        );
    }

    public function ajax_delete() {
        saw_verify_ajax_unified();

        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => $this->tr('error_no_delete_permission', 'Nemáte oprávnění mazat záznamy')));
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => $this->tr('error_missing_id', 'Chybí ID')));
            return;
        }

        if ($this->before_delete($id) === false) {
            wp_send_json_error(array('message' => $this->tr('error_has_departments', 'Nelze smazat pobočku – má navázaná oddělení.')));
            return;
        }

        $result = $this->model->delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array('message' => $this->tr('success_deleted', 'Pobočka byla smazána')));
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

        $item['is_headquarters_label'] = !empty($item['is_headquarters']) 
            ? $this->tr('yes', 'Ano') 
            : $this->tr('no', 'Ne');
        $item['is_active_label'] = !empty($item['is_active']) 
            ? $this->tr('status_active', 'Aktivní') 
            : $this->tr('status_inactive', 'Neaktivní');

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
                sprintf($this->tr('error_has_departments_count', 'Nelze smazat pobočku – obsahuje %d oddělení'), $departments_count),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Auto-assign Czech language to new branch
     */
    protected function after_save($id) {
        global $wpdb;
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$branch) {
            return;
        }
        
        $customer_id = $branch['customer_id'];
        
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
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_language_branches 
             WHERE language_id = %d AND branch_id = %d",
            $czech_lang->id,
            $id
        ));
        
        if ($existing) {
            return;
        }
        
        $branch_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d",
            $customer_id
        ));
        
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
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/forms.css',
            array(),
            SAW_VISITORS_VERSION
        );

        wp_enqueue_script(
            'saw-file-upload',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/components/forms.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }

    /**
     * Get header meta for detail sidebar
     * 
     * Returns HTML for badges displayed in universal detail header.
     * Shows: code, headquarters badge, status
     * 
     * @since 17.0.0
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // 1. Kód pobočky
        if (!empty($item['code'])) {
            $meta_parts[] = '<span class="saw-badge-transparent">' . esc_html($item['code']) . '</span>';
        }
        
        // 2. Sídlo firmy / Pobočka
        if (!empty($item['is_headquarters'])) {
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-primary">🏛️ ' . esc_html($this->tr('badge_headquarters', 'Sídlo firmy')) . '</span>';
        } else {
            $meta_parts[] = '<span class="saw-badge-transparent">🏢 ' . esc_html($this->tr('badge_branch', 'Pobočka')) . '</span>';
        }
        
        // 3. Status
        if (!empty($item['is_active'])) {
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-success">✓ ' . esc_html($this->tr('status_active', 'Aktivní')) . '</span>';
        } else {
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-secondary">' . esc_html($this->tr('status_inactive', 'Neaktivní')) . '</span>';
        }
        
        return implode(' ', $meta_parts);
    }
}