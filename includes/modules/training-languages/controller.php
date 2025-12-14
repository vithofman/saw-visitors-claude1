<?php
/**
 * Training Languages Controller
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    5.0.0 - ADDED: get_display_name, get_detail_header_meta for blue header
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Training_Languages_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /** @var array Translation strings */
    private $translations = array();
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Training_Languages_Model($this->config);
        
        if (file_exists($module_path . 'class-auto-setup.php')) {
            require_once $module_path . 'class-auto-setup.php';
        }
        
        // Initialize translations
        $this->init_translations();
        
        // Enqueue assets with high priority
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 999);
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
            ? saw_get_translations($lang, 'admin', 'training_languages') 
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
     * Main Entry Point
     */
    public function index() {
        $sidebar_data = $this->process_sidebar_context();
        if ($sidebar_data === null) return;
        
        $this->config = array_merge($this->config, $sidebar_data);
        
        if ($this->get_sidebar_mode() === 'create') {
            $this->config['available_branches'] = $this->model->get_available_branches();
            $this->config['languages_data'] = require $this->config['path'] . 'languages-data.php';
        } 
        elseif ($this->get_sidebar_mode() === 'edit') {
            $this->config['languages_data'] = require $this->config['path'] . 'languages-data.php';
        }
        
        $this->render_list_view();
    }
    
    /**
     * Get display name for detail header
     * 
     * @param array $item Item data
     * @return string Display name
     */
    public function get_display_name($item) {
        return $item['language_name'] ?? $this->tr('singular', 'Jazyk školení');
    }
    
    /**
     * Get tab counts for list view
     * 
     * @return array Tab counts ['all' => int, 'with_branches' => int, 'without_branches' => int]
     */
    protected function get_tab_counts() {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        if (!$customer_id) {
            return array('all' => 0, 'with_branches' => 0, 'without_branches' => 0);
        }
        
        $table = $wpdb->prefix . 'saw_training_languages';
        $branches_table = $wpdb->prefix . 'saw_training_language_branches';
        
        $counts = array();
        
        // All
        $counts['all'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d",
            $customer_id
        ));
        
        // With branches (branches_count > 0) - use subquery
        $counts['with_branches'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT l.id
                FROM {$table} l
                LEFT JOIN {$branches_table} lb ON l.id = lb.language_id
                WHERE l.customer_id = %d
                GROUP BY l.id
                HAVING COUNT(CASE WHEN lb.is_active = 1 THEN 1 END) > 0
            ) AS subquery",
            $customer_id
        ));
        
        // Without branches (branches_count = 0) - use subquery
        $counts['without_branches'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT l.id
                FROM {$table} l
                LEFT JOIN {$branches_table} lb ON l.id = lb.language_id
                WHERE l.customer_id = %d
                GROUP BY l.id
                HAVING COUNT(CASE WHEN lb.is_active = 1 THEN 1 END) = 0
            ) AS subquery",
            $customer_id
        ));
        
        return $counts;
    }
    
    /**
     * Get header meta for detail sidebar (blue header)
     * 
     * Shows: flag emoji, language code, branches count
     * NO ID displayed!
     * 
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta = array();
        
        // Flag emoji (large)
        if (!empty($item['flag_emoji'])) {
            $meta[] = '<span class="saw-header-flag">' . esc_html($item['flag_emoji']) . '</span>';
        }
        
        // Language code
        if (!empty($item['language_code'])) {
            $meta[] = '<span class="saw-badge-transparent saw-badge-code">' . esc_html(strtoupper($item['language_code'])) . '</span>';
        }
        
        // Branches count
        $branches_count = 0;
        if (!empty($item['active_branches'])) {
            $branches_count = count($item['active_branches']);
        }
        $meta[] = '<span class="saw-badge-transparent">' . $branches_count . ' ' . esc_html($this->tr('header_branches', 'poboček')) . '</span>';
        
        // Required badge for Czech
        if (($item['language_code'] ?? '') === 'cs') {
            $meta[] = '<span class="saw-badge-transparent saw-badge-info">' . esc_html($this->tr('badge_required', 'Povinný')) . '</span>';
        }
        
        return implode(' ', $meta);
    }
    
    /**
     * Format detail data
     * 
     * @param array $item Item data
     * @return array Formatted item
     */
    protected function format_detail_data($item) {
        if (empty($item)) {
            return $item;
        }
        
        // Format audit fields and dates (audit history support)
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
            $item['created_at_relative'] = human_time_diff(strtotime($item['created_at']), current_time('timestamp')) . ' ' . __('před', 'saw-visitors');
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
            $item['updated_at_relative'] = human_time_diff(strtotime($item['updated_at']), current_time('timestamp')) . ' ' . __('před', 'saw-visitors');
        }
        
        // Set flag for audit info availability
        $item['has_audit_info'] = !empty($item['created_by']) || !empty($item['updated_by']) || 
                                  !empty($item['created_at']) || !empty($item['updated_at']);
        
        // Load change history for this training language
        if (!empty($item['id']) && class_exists('SAW_Audit')) {
            try {
                $entity_type = $this->config['entity'] ?? 'training_languages';
                $change_history = SAW_Audit::get_entity_history($entity_type, $item['id']);
                if (!empty($change_history)) {
                    $item['change_history'] = $change_history;
                    $item['has_audit_info'] = true;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW Audit] Failed to load change history for training-languages: ' . $e->getMessage());
                }
            }
        }
        
        return $item;
    }
    
    /**
     * Prepare form data from POST
     */
    protected function prepare_form_data($post) {
        $data = [];
        
        $text_fields = ['language_code', 'language_name', 'flag_emoji'];
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (!empty($post['branches'])) {
            $branches = [];
            foreach ($post['branches'] as $branch_id => $branch_data) {
                $is_active = intval($branch_data['active'] ?? 0);
                
                if ($is_active === 1) {
                    $branches[intval($branch_id)] = [
                        'active' => 1,
                        'is_default' => intval($branch_data['is_default'] ?? 0),
                        'display_order' => intval($branch_data['display_order'] ?? 0),
                    ];
                }
            }
            $data['branches'] = $branches;
        } else {
            $data['branches'] = [];
        }
        
        return $data;
    }
    
    /**
     * Before save hook
     */
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        return $data;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        wp_enqueue_style('dashicons');
        SAW_Asset_Loader::enqueue_module('training-languages');
    }
}