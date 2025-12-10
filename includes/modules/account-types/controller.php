<?php
/**
 * Account Types Module Controller
 *
 * COMPLETE SAW TABLE IMPLEMENTATION
 * Uses SAW_Form_Renderer and SAW_Detail_Renderer
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     12.0.0 - SAW Table Complete
 */

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
    }
    
    /**
     * Index - render list page
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Render list view
     */
    protected function render_list_view() {
        if (method_exists($this, 'verify_module_access')) {
            $this->verify_module_access();
        }
        
        $this->enqueue_assets();
        
        ob_start();
        
        $current_tab = $this->get_current_tab();
        
        $args = [];
        if ($current_tab === 'active') {
            $args['is_active'] = 1;
        } elseif ($current_tab === 'inactive') {
            $args['is_active'] = 0;
        }
        
        if (!empty($_GET['search'])) {
            $args['search'] = sanitize_text_field($_GET['search']);
        }
        
        $args['orderby'] = sanitize_key($_GET['orderby'] ?? 'sort_order');
        $args['order'] = strtoupper(sanitize_key($_GET['order'] ?? 'ASC'));
        
        $result = $this->model->get_all($args);
        $items = isset($result['items']) ? $result['items'] : (is_array($result) ? $result : []);
        
        foreach ($items as &$item) {
            $item['customers_count'] = $this->count_customers($item['id']);
        }
        unset($item);
        
        $tab_counts = [
            'all' => $this->model->count(),
            'active' => $this->model->count(['is_active' => 1]),
            'inactive' => $this->model->count(['is_active' => 0]),
        ];
        
        $total = count($items);
        
        $sidebar_mode = null;
        $detail_item = null;
        
        if (method_exists($this, 'get_sidebar_context')) {
            $context = $this->get_sidebar_context();
            $sidebar_mode = $context['mode'] ?? null;
            
            if ($sidebar_mode === 'detail' && !empty($context['id'])) {
                $detail_item = $this->model->get_by_id($context['id']);
                if ($detail_item) {
                    $detail_item = $this->format_detail_data($detail_item);
                }
            }
        }
        
        $config = $this->config;
        $entity = $this->entity;
        
        if (method_exists($this, 'render_flash_messages')) {
            echo '<div class="saw-module-wrapper" data-entity="' . esc_attr($this->entity) . '">';
            $this->render_flash_messages();
        }
        
        include $this->config['path'] . 'list-template.php';
        
        if (method_exists($this, 'render_flash_messages')) {
            echo '</div>';
        }
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Get display name for detail header
     */
    public function get_display_name($item) {
        return $item['display_name'] ?? $item['name'] ?? 'Typ účtu';
    }
    
    /**
     * Format detail data
     */
    protected function format_detail_data($item) {
        $item['customers_count'] = $this->count_customers($item['id']);
        return $item;
    }
    
    /**
     * Count customers using this account type
     */
    protected function count_customers($account_type_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
            $account_type_id
        ));
    }
    
    // =========================================
    // AJAX HANDLERS - SAW TABLE IMPLEMENTATION
    // =========================================
    
    /**
     * AJAX: Load sidebar (form or detail)
     * 
     * THIS IS THE CRITICAL METHOD THAT WAS MISSING!
     * Called by AJAX Registry dispatch() for saw_load_sidebar_account_types
     */
    public function ajax_load_sidebar() {
        // Verify nonce - use unified nonce
        if (function_exists('saw_verify_ajax_unified')) {
            saw_verify_ajax_unified();
        } else {
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'saw_ajax_nonce')) {
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }
        }
        
        $mode = sanitize_key($_POST['mode'] ?? 'detail');
        $id = intval($_POST['id'] ?? 0);
        
        $item = null;
        if ($id) {
            $item = $this->model->get_by_id($id);
            if ($item) {
                $item = $this->format_detail_data($item);
            }
        }
        
        // Render based on mode
        if ($mode === 'create' || $mode === 'edit') {
            $html = $this->render_form_sidebar($item);
        } else {
            $html = $this->render_detail_sidebar($item);
        }
        
        wp_send_json_success([
            'html' => $html,
            'item' => $item,
        ]);
    }
    
    /**
     * Render form sidebar using SAW_Form_Renderer
     */
    protected function render_form_sidebar($item = null) {
        if (!class_exists('SAW_Form_Renderer')) {
            $renderer_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/renderers/class-form-renderer.php';
            if (file_exists($renderer_path)) {
                require_once $renderer_path;
            }
        }
        
        if (class_exists('SAW_Form_Renderer')) {
            return SAW_Form_Renderer::render($this->config, $item, $this->entity);
        }
        
        // Fallback: simple form
        return $this->render_fallback_form($item);
    }
    
    /**
     * Render detail sidebar using SAW_Detail_Renderer
     */
    protected function render_detail_sidebar($item) {
        if (!$item) {
            return '<div class="sawt-alert sawt-alert-warning">Záznam nenalezen</div>';
        }
        
        if (!class_exists('SAW_Detail_Renderer')) {
            $renderer_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/renderers/class-detail-renderer.php';
            if (file_exists($renderer_path)) {
                require_once $renderer_path;
            }
        }
        
        if (class_exists('SAW_Detail_Renderer')) {
            return SAW_Detail_Renderer::render($this->config, $item, [], $this->entity);
        }
        
        // Fallback: simple detail
        return $this->render_fallback_detail($item);
    }
    
    /**
     * Fallback form when SAW_Form_Renderer is not available
     */
    protected function render_fallback_form($item = null) {
        $is_edit = !empty($item);
        $title = $is_edit ? 'Upravit typ účtu' : 'Nový typ účtu';
        
        ob_start();
        ?>
        <div class="sawt-form-sidebar" data-entity="<?php echo esc_attr($this->entity); ?>">
            <header class="sawt-sidebar-header">
                <div class="sawt-sidebar-header-left">
                    <span class="sawt-sidebar-icon">📝</span>
                    <h3 class="sawt-sidebar-title"><?php echo esc_html($title); ?></h3>
                </div>
                <div class="sawt-sidebar-header-right">
                    <button type="button" class="sawt-sidebar-close" data-close-sidebar>×</button>
                </div>
            </header>
            
            <form class="sawt-form" method="post" data-entity="<?php echo esc_attr($this->entity); ?>" data-id="<?php echo esc_attr($item['id'] ?? 0); ?>">
                <?php wp_nonce_field('saw_' . ($is_edit ? 'update' : 'create') . '_account_types', 'nonce'); ?>
                
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
                <?php endif; ?>
                
                <input type="hidden" name="action" value="<?php echo esc_attr($is_edit ? 'saw_update_account_types' : 'saw_create_account_types'); ?>">
                
                <div class="sawt-sidebar-content">
                    <div class="sawt-form-body">
                        
                        <div class="sawt-form-field">
                            <label class="sawt-form-label" for="field-name">
                                Název <span class="sawt-form-required">*</span>
                            </label>
                            <input type="text" 
                                   id="field-name" 
                                   name="name" 
                                   value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                                   class="sawt-form-input" 
                                   required>
                        </div>
                        
                        <div class="sawt-form-field">
                            <label class="sawt-form-label" for="field-display_name">
                                Zobrazovaný název
                            </label>
                            <input type="text" 
                                   id="field-display_name" 
                                   name="display_name" 
                                   value="<?php echo esc_attr($item['display_name'] ?? ''); ?>" 
                                   class="sawt-form-input">
                        </div>
                        
                        <div class="sawt-form-field">
                            <label class="sawt-form-label" for="field-description">
                                Popis
                            </label>
                            <textarea id="field-description" 
                                      name="description" 
                                      class="sawt-form-textarea" 
                                      rows="3"><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="sawt-form-field">
                            <label class="sawt-form-label" for="field-color">
                                Barva
                            </label>
                            <input type="color" 
                                   id="field-color" 
                                   name="color" 
                                   value="<?php echo esc_attr($item['color'] ?? '#3b82f6'); ?>" 
                                   class="sawt-form-color">
                        </div>
                        
                        <div class="sawt-form-field">
                            <label class="sawt-form-label" for="field-sort_order">
                                Pořadí řazení
                            </label>
                            <input type="number" 
                                   id="field-sort_order" 
                                   name="sort_order" 
                                   value="<?php echo esc_attr($item['sort_order'] ?? 0); ?>" 
                                   class="sawt-form-input" 
                                   min="0">
                        </div>
                        
                        <div class="sawt-form-field">
                            <label class="sawt-form-checkbox-label">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" 
                                       id="field-is_active" 
                                       name="is_active" 
                                       value="1" 
                                       class="sawt-form-checkbox"
                                       <?php checked(!empty($item['is_active']) || !$is_edit); ?>>
                                <span class="sawt-form-checkbox-text">Aktivní</span>
                            </label>
                        </div>
                        
                    </div>
                </div>
                
                <footer class="sawt-sidebar-footer">
                    <button type="button" class="sawt-btn sawt-btn-secondary" data-close-sidebar>
                        Zrušit
                    </button>
                    <button type="submit" class="sawt-btn sawt-btn-primary">
                        <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit'; ?>
                    </button>
                </footer>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Fallback detail when SAW_Detail_Renderer is not available
     */
    protected function render_fallback_detail($item) {
        ob_start();
        ?>
        <div class="sawt-detail-sidebar" data-entity="<?php echo esc_attr($this->entity); ?>">
            <header class="sawt-sidebar-header sawt-sidebar-header-blue">
                <div class="sawt-sidebar-header-content">
                    <h3 class="sawt-sidebar-title"><?php echo esc_html($item['display_name'] ?? $item['name']); ?></h3>
                    <div class="sawt-sidebar-badges">
                        <?php if (!empty($item['color'])): ?>
                        <span class="sawt-badge" style="background-color: <?php echo esc_attr($item['color']); ?>">
                            &nbsp;
                        </span>
                        <?php endif; ?>
                        <span class="sawt-badge-transparent sawt-badge-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $item['is_active'] ? '✓ Aktivní' : '⏸ Neaktivní'; ?>
                        </span>
                    </div>
                </div>
                <div class="sawt-sidebar-header-right">
                    <div class="sawt-sidebar-nav">
                        <button type="button" class="sawt-sidebar-nav-btn" data-nav="prev" title="Předchozí">‹</button>
                        <button type="button" class="sawt-sidebar-nav-btn" data-nav="next" title="Další">›</button>
                    </div>
                    <button type="button" class="sawt-sidebar-close" data-close-sidebar>×</button>
                </div>
            </header>
            
            <div class="sawt-sidebar-content">
                
                <!-- Info section -->
                <div class="sawt-industrial-section">
                    <div class="sawt-section-head">
                        <h4 class="sawt-section-title">📋 Informace</h4>
                    </div>
                    <div class="sawt-section-body">
                        <?php if (!empty($item['description'])): ?>
                        <div class="sawt-info-row">
                            <span class="sawt-info-label">Popis</span>
                            <span class="sawt-info-val"><?php echo esc_html($item['description']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="sawt-info-row">
                            <span class="sawt-info-label">Pořadí řazení</span>
                            <span class="sawt-info-val"><?php echo esc_html($item['sort_order'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics section -->
                <div class="sawt-industrial-section">
                    <div class="sawt-section-head">
                        <h4 class="sawt-section-title">📊 Statistiky</h4>
                    </div>
                    <div class="sawt-section-body">
                        <div class="sawt-info-row">
                            <span class="sawt-info-label">Počet zákazníků</span>
                            <span class="sawt-info-val sawt-info-val-bold"><?php echo intval($item['customers_count'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Metadata section -->
                <div class="sawt-industrial-section">
                    <div class="sawt-section-head">
                        <h4 class="sawt-section-title">🕐 Metadata</h4>
                    </div>
                    <div class="sawt-section-body">
                        <?php if (!empty($item['created_at'])): ?>
                        <div class="sawt-info-row">
                            <span class="sawt-info-label">Vytvořeno</span>
                            <span class="sawt-info-val"><?php echo date_i18n('d.m.Y H:i', strtotime($item['created_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['updated_at'])): ?>
                        <div class="sawt-info-row">
                            <span class="sawt-info-label">Změněno</span>
                            <span class="sawt-info-val"><?php echo date_i18n('d.m.Y H:i', strtotime($item['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
            
            <footer class="sawt-sidebar-footer">
                <a href="<?php echo home_url('/admin/account-types/' . $item['id'] . '/edit/'); ?>" 
                   class="sawt-btn sawt-btn-primary"
                   data-action="edit">
                    ✏️ Upravit
                </a>
                <?php if (empty($item['customers_count'])): ?>
                <button type="button" 
                        class="sawt-btn sawt-btn-danger" 
                        data-action="delete"
                        data-id="<?php echo esc_attr($item['id']); ?>"
                        data-confirm="Opravdu chcete smazat tento typ účtu?">
                    🗑️ Smazat
                </button>
                <?php endif; ?>
            </footer>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get detail (for backward compatibility)
     */
    public function ajax_get_detail() {
        if (function_exists('saw_verify_ajax_unified')) {
            saw_verify_ajax_unified();
        } else {
            check_ajax_referer('saw_ajax_nonce', 'nonce');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $item = $this->model->get_by_id($id);
        if (!$item) {
            wp_send_json_error(['message' => 'Nenalezeno']);
        }
        
        $item = $this->format_detail_data($item);
        $html = $this->render_detail_sidebar($item);
        
        wp_send_json_success([
            'html' => $html,
            'item' => $item
        ]);
    }
    
    /**
     * AJAX: Search
     */
    public function ajax_search() {
        if (function_exists('saw_verify_ajax_unified')) {
            saw_verify_ajax_unified();
        } else {
            check_ajax_referer('saw_ajax_nonce', 'nonce');
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $items = $this->model->get_all(['search' => $search]);
        
        wp_send_json_success(['items' => $items['items'] ?? $items]);
    }
    
    /**
     * AJAX: Delete
     */
    public function ajax_delete() {
        if (function_exists('saw_verify_ajax_unified')) {
            saw_verify_ajax_unified();
        } else {
            check_ajax_referer('saw_ajax_nonce', 'nonce');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $count = $this->count_customers($id);
        if ($count > 0) {
            wp_send_json_error(['message' => "Nelze smazat - používá {$count} zákazníků"]);
        }
        
        $result = $this->model->delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Smazáno']);
    }
    
    /**
     * AJAX: Create
     */
    public function ajax_create() {
        if (function_exists('saw_verify_ajax_unified')) {
            saw_verify_ajax_unified();
        } else {
            check_ajax_referer('saw_create_account_types', 'nonce');
        }
        
        $data = $this->get_form_data();
        
        $errors = $this->validate_form_data($data);
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Opravte chyby ve formuláři',
                'errors' => $errors
            ]);
        }
        
        $id = $this->model->create($data);
        
        if (is_wp_error($id)) {
            wp_send_json_error(['message' => $id->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => 'Vytvořeno',
            'id' => $id
        ]);
    }
    
    /**
     * AJAX: Update
     */
    public function ajax_update() {
        if (function_exists('saw_verify_ajax_unified')) {
            saw_verify_ajax_unified();
        } else {
            check_ajax_referer('saw_update_account_types', 'nonce');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $data = $this->get_form_data();
        
        $errors = $this->validate_form_data($data, $id);
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Opravte chyby ve formuláři',
                'errors' => $errors
            ]);
        }
        
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => 'Uloženo',
            'id' => $id
        ]);
    }
    
    /**
     * Get form data from POST
     */
    protected function get_form_data() {
        return [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'display_name' => sanitize_text_field($_POST['display_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'color' => sanitize_hex_color($_POST['color'] ?? '#3b82f6'),
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'is_active' => intval($_POST['is_active'] ?? 1),
        ];
    }
    
    /**
     * Validate form data
     */
    protected function validate_form_data($data, $id = null) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Název je povinný';
        }
        
        return $errors;
    }
}
