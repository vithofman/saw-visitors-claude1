<?php
/**
 * Companies Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     3.1.0 - FIXED: Inline merge content (no modal wrapper)
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_Companies_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/companies/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Companies_Model($this->config);
    }
    
    public function index() {
        $this->render_list_view();
    }

    protected function enqueue_assets() {
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('companies');
        }
    }
    
    protected function prepare_form_data($post) {
        $data = array();
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        $text_fields = array('name', 'ico', 'street', 'city', 'zip', 'country', 'phone');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (isset($post['email'])) {
            $data['email'] = sanitize_email($post['email']);
        }
        
        if (isset($post['website'])) {
            $data['website'] = esc_url_raw($post['website']);
        }
        
        $data['is_archived'] = isset($post['is_archived']) ? 1 : 0;
        
        return $data;
    }
    
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        return $data;
    }
    
    protected function format_detail_data($item) {
        error_log('[Companies Controller] format_detail_data called for ID: ' . ($item['id'] ?? 'unknown'));
        
        global $wpdb;
        
        if (!empty($item['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_branches',
                $item['branch_id']
            ), ARRAY_A);
            
            if ($branch) {
                $item['branch_name'] = $branch['name'];
                error_log('[Companies Controller] Added branch_name: ' . $branch['name']);
            }
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

        // Load change history for this company
        if (!empty($item['id']) && class_exists('SAW_Audit')) {
            try {
                $entity_type = $this->config['entity'] ?? 'companies';
                $change_history = SAW_Audit::get_entity_history($entity_type, $item['id']);
                if (!empty($change_history)) {
                    $item['change_history'] = $change_history;
                    $item['has_audit_info'] = true;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW Audit] Failed to load change history for companies: ' . $e->getMessage());
                }
            }
        }

        error_log('[Companies Controller] format_detail_data completed. Item keys: ' . implode(', ', array_keys($item)));
        
        return $item;
    }
    
    public function get_display_name($item) {
        return $item['name'] ?? 'Nová firma';
    }
    
    /**
     * Get header meta for detail sidebar
     * 
     * Returns HTML for badges/info displayed in universal detail header.
     * 
     * @since 7.3.0
     * @param array $item Item data
     * @return string HTML for header meta
     */
    protected function get_detail_header_meta($item) {
    // Načíst překlady
    $lang = 'cs';
    if (class_exists('SAW_Component_Language_Switcher')) {
        $lang = SAW_Component_Language_Switcher::get_user_language();
    }
    $t = function_exists('saw_get_translations') 
        ? saw_get_translations($lang, 'admin', 'companies') 
        : [];
    
    $tr = function($key, $fallback) use ($t) {
        return $t[$key] ?? $fallback;
    };
    
    $meta_parts = array();
    
    if (!empty($item['ico'])) {
        $meta_parts[] = '<span class="saw-badge-transparent">' . esc_html($tr('ico_label', 'IČO')) . ': ' . esc_html($item['ico']) . '</span>';
    }
    
    if (!empty($item['is_archived'])) {
        $meta_parts[] = '<span class="saw-badge-transparent saw-badge-archived">' . esc_html($tr('status_archived', 'Archivováno')) . '</span>';
    } else {
        $meta_parts[] = '<span class="saw-badge-transparent">✓ ' . esc_html($tr('status_active', 'Aktivní')) . '</span>';
    }
    
    return implode('', $meta_parts);
}
    
    public function ajax_inline_create() {
        saw_verify_ajax_unified();
        
        if (!$this->can('create')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění vytvářet firmy'));
            return;
        }
        
        $data = $this->prepare_form_data($_POST);
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
            return;
        }
        
        $validation = $this->model->validate($data);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            wp_send_json_error(array('message' => implode('<br>', $errors)));
            return;
        }
        
        $result = $this->model->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $item = $this->model->get_by_id($result);
        
        wp_send_json_success(array(
            'id' => $result,
            'name' => $this->get_display_name($item),
        ));
    }
    
    // ==========================================
    // ✅ MERGE FUNCTIONALITY - AJAX HANDLERS
    // ==========================================
    
    /**
     * ✅ FIXED: Returns clean HTML content (no modal wrapper)
     * For inline UI in detail sidebar
     * 
     * @since 3.1.0
     * @return void Outputs HTML
     */
    public function ajax_show_merge_modal() {
        saw_verify_ajax_unified();
        
        if (!$this->can('edit')) {
            echo '<div class="saw-error-state">❌ Nemáte oprávnění</div>';
            wp_die();
        }
        
        $master_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$master_id) {
            echo '<div class="saw-error-state">❌ Chybí ID firmy</div>';
            wp_die();
        }
        
        $master = $this->model->get_by_id($master_id);
        
        if (!$master) {
            echo '<div class="saw-error-state">❌ Firma nebyla nalezena</div>';
            wp_die();
        }
        
        $suggestions = $this->model->find_similar_companies(
            $master['name'], 
            $master['branch_id'],
            $master_id
        );
        
        error_log(sprintf(
            'SAW Merge: Found %d similar companies for "%s" (ID: %d)',
            count($suggestions),
            $master['name'],
            $master_id
        ));
        
        // ✅ RENDER ONLY CONTENT (not full modal wrapper)
        if (!empty($suggestions)): ?>
            
            <div class="saw-help-text">
                💡 <strong>Našli jsme <?php echo count($suggestions); ?> podobných firem.</strong><br>
                Vyberte firmy, které chcete sloučit pod hlavní záznam.
            </div>
            
            <div class="saw-merge-warning">
                <strong>⚠️ Tato akce je nevratná!</strong>
                <p class="saw-merge-warning-text-p">
                    Vybrané firmy budou <strong>trvale smazány</strong> a jejich veškerá historie (návštěvy, kontakty) 
                    bude přesunuta pod hlavní firmu <strong><?php echo esc_html($master['name']); ?></strong>.
                </p>
            </div>
            
            <div class="saw-duplicate-list">
                <?php foreach ($suggestions as $company): ?>
                <label class="saw-duplicate-item">
                    <input type="checkbox" 
                           name="duplicate_ids[]" 
                           value="<?php echo intval($company['id']); ?>"
                           onchange="updateMergeButton()">
                    
                    <div class="saw-dup-info">
                        <strong><?php echo esc_html($company['name']); ?></strong>
                        
                        <div class="saw-dup-meta">
                            <span class="saw-similarity-badge">
                                ✓ <?php echo intval($company['similarity']); ?>% shoda
                            </span>
                            
                            <span class="saw-visit-count">
                                📋 <?php echo intval($company['visit_count']); ?> návštěv
                            </span>
                            
                            <?php if (!empty($company['ico'])): ?>
                            <span class="saw-ico-badge">
                                IČO: <?php echo esc_html($company['ico']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <div class="saw-merge-actions">
                <button class="saw-btn saw-btn-primary" 
                        id="sawMergeButton"
                        onclick="confirmMerge()" 
                        type="button"
                        disabled>
                    Sloučit vybrané
                </button>
            </div>
            
        <?php else: ?>
            
            <div class="saw-no-duplicates">
                ✓ Nebyly nalezeny žádné podobné firmy
            </div>
            
        <?php endif;
        
        wp_die();
    }
    
    public function ajax_merge_companies() {
        // ✅ FIX: Start buffering immediately to catch any unexpected output
        ob_start();
        
        saw_verify_ajax_unified();
        
        if (!$this->can('delete')) {
            wp_send_json_error(array(
                'message' => 'Nemáte oprávnění ke sloučení firem'
            ));
            return;
        }
        
        $master_id = isset($_POST['master_id']) ? intval($_POST['master_id']) : 0;
        $duplicate_ids_json = isset($_POST['duplicate_ids']) ? $_POST['duplicate_ids'] : '[]';
        
        $duplicate_ids = json_decode(stripslashes($duplicate_ids_json), true);
        
        if (!is_array($duplicate_ids)) {
            $duplicate_ids = array();
        }
        
        $duplicate_ids = array_map('intval', $duplicate_ids);
        $duplicate_ids = array_filter($duplicate_ids);
        
        error_log(sprintf(
            'SAW Merge Request: Master=%d, Duplicates=%s',
            $master_id,
            implode(',', $duplicate_ids)
        ));
        
        if (!$master_id) {
            wp_send_json_error(array(
                'message' => 'Chybí ID hlavní firmy'
            ));
            return;
        }
        
        if (empty($duplicate_ids)) {
            wp_send_json_error(array(
                'message' => 'Nevybrali jste žádné firmy ke sloučení'
            ));
            return;
        }
        
        $master = $this->model->get_by_id($master_id);
        if (!$master) {
            wp_send_json_error(array(
                'message' => 'Hlavní firma nebyla nalezena'
            ));
            return;
        }
        
        $result = $this->model->merge_companies($master_id, $duplicate_ids);
        
        if (is_wp_error($result)) {
            error_log('SAW Merge Error: ' . $result->get_error_message());
            
            wp_send_json_error(array(
                'message' => 'Chyba při sloučení: ' . $result->get_error_message()
            ));
            return;
        }
        
        $count = count($duplicate_ids);
        $message = sprintf(
            'Úspěšně sloučeno %d %s pod firmu "%s"',
            $count,
            $count === 1 ? 'firma' : ($count < 5 ? 'firmy' : 'firem'),
            $master['name']
        );
        
        error_log('SAW Merge Success: ' . $message);
        
        // ✅ FIX: Clean any unexpected output (warnings, notices) before sending JSON
        ob_end_clean();
        
        wp_send_json_success(array(
            'message' => $message,
            'merged_count' => $count,
            'master_id' => $master_id
        ));
    }
    
    /**
     * Get initials from name
     * 
     * Robust implementation that handles missing mb_string extension
     * and invalid input.
     * 
     * @since 18.0.1
     * @param string $first_name First name
     * @param string $last_name Last name
     * @return string Initials (e.g. "JD")
     */
    public static function get_initials($first_name, $last_name) {
        $first = '';
        $last = '';
        
        if (function_exists('mb_substr')) {
            $first = !empty($first_name) ? mb_substr($first_name, 0, 1) : '';
            $last = !empty($last_name) ? mb_substr($last_name, 0, 1) : '';
        } else {
            $first = !empty($first_name) ? substr($first_name, 0, 1) : '';
            $last = !empty($last_name) ? substr($last_name, 0, 1) : '';
        }
        
        return strtoupper($first . $last);
    }

    public function ajax_get_duplicate_stats() {
        saw_verify_ajax_unified();
        
        if (!$this->can('view')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $customer_id = SAW_Context::get_customer_id();
        
        $duplicates = $this->model->find_all_duplicates($customer_id);
        
        wp_send_json_success(array(
            'total_groups' => count($duplicates),
            'total_companies' => array_sum(array_column($duplicates, 'count')),
            'groups' => array_slice($duplicates, 0, 5)
        ));
    }
}