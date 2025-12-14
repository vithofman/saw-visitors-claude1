<?php
/**
 * OOPP Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     2.0.0 - ADDED: Translation support
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

class SAW_Module_OOPP_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    
    /**
     * Název tabulky pro přímé DB operace
     * @var string
     */
    private $table_name;
    
    /**
     * Translation function
     * @var callable
     */
    private $tr;
    
    public function __construct() {
        global $wpdb;
        
        // ============================================
        // TRANSLATIONS SETUP
        // ============================================
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'oopp') 
            : array();
        
        $this->tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        // ============================================
        // MODULE SETUP
        // ============================================
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        // Uložíme název tabulky pro použití v after_save
        $this->table_name = $wpdb->prefix . $this->config['table'];
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_OOPP_Model($this->config);
        
        // File uploader pro obrázky
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // Register assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Index - seznam OOPP
     */
    public function index() {
        $tr = $this->tr;
        
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die($tr('error_no_permission', 'Nemáte oprávnění.'), 403);
        }
        
        // Přidej jazyky a translations do config pro formulář
        $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 0;
        if ($customer_id) {
            $this->config['form_languages'] = $this->model->get_customer_languages($customer_id);
        } else {
            $this->config['form_languages'] = array();
        }
        
        $this->render_list_view();
    }
    
    /**
     * Override handle_edit_mode pro přidání translations
     */
    protected function handle_edit_mode($sidebar_context) {
        $item = parent::handle_edit_mode($sidebar_context);
        
        if ($item && !empty($item['id'])) {
            // Načti překlady
            $item['translations'] = $this->model->get_translations($item['id']);
        } else {
            $item['translations'] = array();
        }
        
        return $item;
    }
    
    /**
     * Override handle_create_mode pro přidání prázdných translations
     */
    protected function handle_create_mode() {
        $result = parent::handle_create_mode();
        
        if ($result === null) {
            return null;
        }
        
        // Přidej prázdné translations
        $result['translations'] = array();
        
        return $result;
    }
    
    /**
     * Enqueue module assets
     */
    public function enqueue_assets() {
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('oopp');
        }
        
        // File upload komponenta
        if (class_exists('SAW_Component_File_Upload')) {
            SAW_Component_File_Upload::enqueue_assets();
        }
        
        // Localize script
        wp_localize_script('saw-module-oopp', 'sawOOPPData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'groups' => $this->model->get_groups(),
        ));
    }
    
    /**
     * Příprava dat z formuláře
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Customer ID (automaticky z kontextu)
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        // Textová pole už NEJSOU v hlavních datech - jsou v translations
        // Ponecháme pouze základní pole bez textových
        
        // Skupina (povinné pole)
        if (isset($post['group_id']) && !empty($post['group_id'])) {
            $data['group_id'] = intval($post['group_id']);
        } else {
            // group_id je povinné - pokud chybí, necháme validaci to zachytit
            // ale přidáme debug log
            if (class_exists('SAW_Logger')) {
                SAW_Logger::debug('[SAW OOPP Controller] group_id missing in POST data');
            }
        }
        
        // Aktivní
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        // Pobočky (pole IDs)
        $data['branch_ids'] = array();
        if (isset($post['branch_ids']) && is_array($post['branch_ids'])) {
            $data['branch_ids'] = array_map('intval', $post['branch_ids']);
        }
        
        // Oddělení (pole IDs)
        $data['department_ids'] = array();
        if (isset($post['department_ids']) && is_array($post['department_ids'])) {
            $data['department_ids'] = array_map('intval', $post['department_ids']);
        }
        
        // Typ použití (is_global)
        if (isset($post['is_global'])) {
            $data['is_global'] = intval($post['is_global']) === 1 ? 1 : 0;
        } else {
            // Default: globální
            $data['is_global'] = 1;
        }
        
        // Translations se zpracují v after_save
        
        return $data;
    }
    
    /**
     * Before save - nastav customer_id a validuj translations
     */
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            if (class_exists('SAW_Context')) {
                $data['customer_id'] = SAW_Context::get_customer_id();
            }
        }
        
        // ✅ OPRAVA: Validace translations - kontroluj aktivní jazyk nebo alespoň jeden s name
        if (isset($_POST['translations']) && is_array($_POST['translations']) && !empty($_POST['translations'])) {
            $translations = $_POST['translations'];
            
            // Zjisti aktivní jazyk z formuláře (ten s required atributem)
            $active_lang = null;
            $user_lang = 'cs';
            if (class_exists('SAW_Component_Language_Switcher')) {
                $user_lang = SAW_Component_Language_Switcher::get_user_language();
            }
            
            // Zkus najít aktivní jazyk (podle UX switcheru nebo prvního s name)
            if (!empty($translations[$user_lang]['name']) && !empty(trim($translations[$user_lang]['name']))) {
                $active_lang = $user_lang;
            } else {
                // Najdi první jazyk s vyplněným name
                foreach ($translations as $lang_code => $trans_data) {
                    if (!empty($trans_data['name']) && !empty(trim($trans_data['name']))) {
                        $active_lang = $lang_code;
                        break;
                    }
                }
            }
            
            // Pokud není žádný jazyk s name, vrať chybu
            if (!$active_lang) {
                $tr = $this->tr;
                return new WP_Error('validation_error', $tr('validation_name_required', 'Název musí být vyplněn alespoň v jednom jazyce'));
            }
        }
        
        return $data;
    }
    
    /**
     * After save - zpracuj file upload a překlady
     * 
     * FIXED: Používáme $this->table_name místo $this->model->table (protected property)
     */
    protected function after_save($id) {
        global $wpdb;
        
        // Handle image upload
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Get old image path before update for audit log
            $old_image_path = $wpdb->get_var($wpdb->prepare(
                "SELECT image_path FROM {$this->table_name} WHERE id = %d",
                $id
            ));
            
            $upload_result = $this->file_uploader->upload(
                $_FILES['image'],
                'oopp'
            );
            
            if (!is_wp_error($upload_result)) {
                // Extract relative path from full path
                $upload_dir = wp_upload_dir();
                $relative_path = str_replace($upload_dir['basedir'] . '/', '', $upload_result['path']);
                
                // FIXED: Používáme $this->table_name
                $wpdb->update(
                    $this->table_name,
                    array('image_path' => $relative_path),
                    array('id' => $id),
                    array('%s'),
                    array('%d')
                );
                
                // Log image change to audit history
                if (class_exists('SAW_Audit')) {
                    try {
                        $entity_type = $this->config['entity'] ?? 'oopp';
                        $old_values = array('image_path' => $old_image_path);
                        $new_values = array('image_path' => $relative_path);
                        $changed_fields = array('image_path');
                        
                        $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
                        $branch_id = class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
                        
                        SAW_Audit::log_change(array(
                            'entity_type' => $entity_type,
                            'entity_id' => $id,
                            'action' => 'updated',
                            'old_values' => $old_values,
                            'new_values' => $new_values,
                            'changed_fields' => $changed_fields,
                            'customer_id' => $customer_id,
                            'branch_id' => $branch_id,
                        ));
                    } catch (Exception $e) {
                        if (class_exists('SAW_Logger')) {
                            SAW_Logger::error('[SAW OOPP] Failed to log image change: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // Handle image removal
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            // Get current image path for deletion and audit log
            // FIXED: Používáme $this->table_name
            $old_image_path = $wpdb->get_var($wpdb->prepare(
                "SELECT image_path FROM {$this->table_name} WHERE id = %d",
                $id
            ));
            
            // Remove file if exists
            if ($old_image_path) {
                $upload_dir = wp_upload_dir();
                $full_path = $upload_dir['basedir'] . '/' . ltrim($old_image_path, '/');
                
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
            
            // Clear path in DB
            // FIXED: Používáme $this->table_name
            $wpdb->update(
                $this->table_name,
                array('image_path' => null),
                array('id' => $id),
                array('%s'),
                array('%d')
            );
            
            // Log image removal to audit history
            if (class_exists('SAW_Audit') && $old_image_path) {
                try {
                    $entity_type = $this->config['entity'] ?? 'oopp';
                    $old_values = array('image_path' => $old_image_path);
                    $new_values = array('image_path' => null);
                    $changed_fields = array('image_path');
                    
                    $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
                    $branch_id = class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
                    
                    SAW_Audit::log_change(array(
                        'entity_type' => $entity_type,
                        'entity_id' => $id,
                        'action' => 'updated',
                        'old_values' => $old_values,
                        'new_values' => $new_values,
                        'changed_fields' => $changed_fields,
                        'customer_id' => $customer_id,
                        'branch_id' => $branch_id,
                    ));
                } catch (Exception $e) {
                    if (class_exists('SAW_Logger')) {
                        SAW_Logger::error('[SAW OOPP] Failed to log image removal: ' . $e->getMessage());
                    }
                }
            }
        }
        
        // Handle branch and department relations - log changes
        if (isset($_POST['branch_ids']) || isset($_POST['department_ids'])) {
            // Get old relations before update
            $old_branch_ids = $this->model->get_branch_ids($id);
            $old_department_ids = $this->model->get_department_ids($id);
            
            $new_branch_ids = isset($_POST['branch_ids']) && is_array($_POST['branch_ids']) 
                ? array_map('intval', $_POST['branch_ids']) 
                : array();
            $new_department_ids = isset($_POST['department_ids']) && is_array($_POST['department_ids']) 
                ? array_map('intval', $_POST['department_ids']) 
                : array();
            
            // Check if branch_ids changed
            sort($old_branch_ids);
            sort($new_branch_ids);
            if ($old_branch_ids !== $new_branch_ids) {
                if (class_exists('SAW_Audit')) {
                    try {
                        $entity_type = $this->config['entity'] ?? 'oopp';
                        $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
                        $branch_id = class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
                        
                        SAW_Audit::log_change(array(
                            'entity_type' => $entity_type,
                            'entity_id' => $id,
                            'action' => 'updated',
                            'old_values' => array('branch_ids' => $old_branch_ids),
                            'new_values' => array('branch_ids' => $new_branch_ids),
                            'changed_fields' => array('branch_ids' => array(
                                'old' => implode(', ', $old_branch_ids),
                                'new' => implode(', ', $new_branch_ids)
                            )),
                            'customer_id' => $customer_id,
                            'branch_id' => $branch_id,
                        ));
                    } catch (Exception $e) {
                        if (class_exists('SAW_Logger')) {
                            SAW_Logger::error('[SAW OOPP] Failed to log branch_ids change: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            // Check if department_ids changed
            sort($old_department_ids);
            sort($new_department_ids);
            if ($old_department_ids !== $new_department_ids) {
                if (class_exists('SAW_Audit')) {
                    try {
                        $entity_type = $this->config['entity'] ?? 'oopp';
                        $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
                        $branch_id = class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
                        
                        SAW_Audit::log_change(array(
                            'entity_type' => $entity_type,
                            'entity_id' => $id,
                            'action' => 'updated',
                            'old_values' => array('department_ids' => $old_department_ids),
                            'new_values' => array('department_ids' => $new_department_ids),
                            'changed_fields' => array('department_ids' => array(
                                'old' => implode(', ', $old_department_ids),
                                'new' => implode(', ', $new_department_ids)
                            )),
                            'customer_id' => $customer_id,
                            'branch_id' => $branch_id,
                        ));
                    } catch (Exception $e) {
                        if (class_exists('SAW_Logger')) {
                            SAW_Logger::error('[SAW OOPP] Failed to log department_ids change: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // Handle translations
        if (isset($_POST['translations']) && is_array($_POST['translations'])) {
            // Get old translations before update for audit log
            $old_translations = $this->model->get_translations($id);
            
            $translation_result = $this->model->save_all_translations($id, $_POST['translations']);
            if (is_wp_error($translation_result)) {
                // Log error but don't fail the save - translations can be added later
                if (class_exists('SAW_Logger')) {
                    SAW_Logger::error('[SAW OOPP] Failed to save translations for OOPP #' . $id . ': ' . $translation_result->get_error_message());
                }
            } else {
                // Log translation changes to audit history
                if (class_exists('SAW_Audit')) {
                    try {
                        // Compare old and new translations to find changes
                        $new_translations = $_POST['translations'];
                        $translation_changes = array();
                        
                        // Check all languages
                        $all_languages = array_unique(array_merge(array_keys($old_translations), array_keys($new_translations)));
                        
                        foreach ($all_languages as $lang_code) {
                            $old_trans = $old_translations[$lang_code] ?? array();
                            $new_trans = $new_translations[$lang_code] ?? array();
                            
                            // Check each translation field
                            $translation_fields = array('name', 'standards', 'risk_description', 'protective_properties', 'usage_instructions');
                            foreach ($translation_fields as $field) {
                                $old_value = $old_trans[$field] ?? null;
                                $new_value = $new_trans[$field] ?? null;
                                
                                // Normalize empty strings to null
                                $old_value = ($old_value === '') ? null : $old_value;
                                $new_value = ($new_value === '') ? null : $new_value;
                                
                                // If values differ, log the change
                                if ($old_value !== $new_value) {
                                    $field_key = 'translation_' . $lang_code . '_' . $field;
                                    $translation_changes[$field_key] = array(
                                        'old' => $old_value,
                                        'new' => $new_value
                                    );
                                }
                            }
                        }
                        
                        // Log translation changes if any
                        if (!empty($translation_changes)) {
                            $entity_type = $this->config['entity'] ?? 'oopp';
                            $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
                            $branch_id = class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
                            
                            SAW_Audit::log_change(array(
                                'entity_type' => $entity_type,
                                'entity_id' => $id,
                                'action' => 'updated',
                                'old_values' => array(),
                                'new_values' => array(),
                                'changed_fields' => $translation_changes,
                                'customer_id' => $customer_id,
                                'branch_id' => $branch_id,
                            ));
                        }
                    } catch (Exception $e) {
                        if (class_exists('SAW_Logger')) {
                            SAW_Logger::error('[SAW OOPP] Failed to log translation changes: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Format detail data - přidej extra info pro sidebar
     */
    protected function format_detail_data($item) {
        if (empty($item) || empty($item['id'])) {
            return $item;
        }
        
        global $wpdb;
        
        // Načti group info
        if (!empty($item['group_id'])) {
            $group = $wpdb->get_row($wpdb->prepare(
                "SELECT code, name FROM {$wpdb->prefix}saw_oopp_groups WHERE id = %d",
                $item['group_id']
            ), ARRAY_A);
            
            if ($group) {
                $item['group_code'] = $group['code'];
                $item['group_name'] = $group['name'];
                $item['group_display'] = $group['code'] . '. ' . $group['name'];
            }
        }
        
        // Načti vazby na pobočky
        $item['branch_ids'] = $this->model->get_branch_ids($item['id']);
        
        if (!empty($item['branch_ids'])) {
            $placeholders = implode(',', array_fill(0, count($item['branch_ids']), '%d'));
            $branches = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE id IN ($placeholders) ORDER BY name",
                ...$item['branch_ids']
            ), ARRAY_A);
            $item['branches'] = $branches;
        } else {
            $item['branches'] = array();
            $item['branches_all'] = true;  // Platí pro všechny pobočky
        }
        
        // Načti vazby na oddělení
        $item['department_ids'] = $this->model->get_department_ids($item['id']);
        
        if (!empty($item['department_ids'])) {
            $placeholders = implode(',', array_fill(0, count($item['department_ids']), '%d'));
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}saw_departments WHERE id IN ($placeholders) ORDER BY name",
                ...$item['department_ids']
            ), ARRAY_A);
            $item['departments'] = $departments;
        } else {
            $item['departments'] = array();
            $item['departments_all'] = true;  // Platí pro všechna oddělení
        }
        
        // Image URL
        if (!empty($item['image_path'])) {
            $upload_dir = wp_upload_dir();
            $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_path'], '/');
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
        
        // Load change history for this OOPP
        if (!empty($item['id']) && class_exists('SAW_Audit')) {
            try {
                $entity_type = $this->config['entity'] ?? 'oopp';
                $change_history = SAW_Audit::get_entity_history($entity_type, $item['id']);
                if (!empty($change_history)) {
                    $item['change_history'] = $change_history;
                    $item['has_audit_info'] = true;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW Audit] Failed to load change history for oopp: ' . $e->getMessage());
                }
            }
        }
        
        // Add languages for detail view
        $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : ($item['customer_id'] ?? 0);
        if ($customer_id) {
            $item['detail_languages'] = $this->model->get_customer_languages($customer_id);
        } else {
            $item['detail_languages'] = array();
        }
        
        // Get display name for header (use current language or first available translation)
        $translations = $item['translations'] ?? array();
        $lang = 'cs'; // Default language
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        // Try current language first, then default language, then first available
        if (!empty($translations[$lang]['name'])) {
            $item['header_display_name'] = $translations[$lang]['name'];
        } elseif (!empty($translations['cs']['name'])) {
            $item['header_display_name'] = $translations['cs']['name'];
        } elseif (!empty($translations) && is_array($translations)) {
            $first_translation = reset($translations);
            $item['header_display_name'] = $first_translation['name'] ?? '';
        }
        
        // Set empty header_meta to prevent ID badge
        if (empty($item['header_meta'])) {
            $item['header_meta'] = '';
        }

        return $item;
    }
    
    /**
     * AJAX: Získání OOPP skupin
     */
    public function ajax_get_oopp_groups() {
        saw_verify_ajax_unified();
        
        $groups = $this->model->get_groups();
        
        wp_send_json_success(array(
            'groups' => $groups,
        ));
    }
    
    /**
     * AJAX: Uložení vazeb na pobočky
     */
    public function ajax_save_branches() {
        saw_verify_ajax_unified();
        
        $tr = $this->tr;
        
        if (!$this->can('edit')) {
            wp_send_json_error(array('message' => $tr('error_no_permission_short', 'Nemáte oprávnění')));
        }
        
        $oopp_id = intval($_POST['oopp_id'] ?? 0);
        $branch_ids = isset($_POST['branch_ids']) ? array_map('intval', (array)$_POST['branch_ids']) : array();
        
        if (!$oopp_id) {
            wp_send_json_error(array('message' => $tr('error_invalid_id', 'Neplatné ID')));
        }
        
        $this->model->save_branch_relations($oopp_id, $branch_ids);
        
        wp_send_json_success(array(
            'message' => $tr('msg_branches_saved', 'Pobočky uloženy'),
            'branch_count' => count($branch_ids),
        ));
    }
    
    /**
     * AJAX: Uložení vazeb na oddělení
     */
    public function ajax_save_departments() {
        saw_verify_ajax_unified();
        
        $tr = $this->tr;
        
        if (!$this->can('edit')) {
            wp_send_json_error(array('message' => $tr('error_no_permission_short', 'Nemáte oprávnění')));
        }
        
        $oopp_id = intval($_POST['oopp_id'] ?? 0);
        $department_ids = isset($_POST['department_ids']) ? array_map('intval', (array)$_POST['department_ids']) : array();
        
        if (!$oopp_id) {
            wp_send_json_error(array('message' => $tr('error_invalid_id', 'Neplatné ID')));
        }
        
        $this->model->save_department_relations($oopp_id, $department_ids);
        
        wp_send_json_success(array(
            'message' => $tr('msg_departments_saved', 'Oddělení uložena'),
            'department_count' => count($department_ids),
        ));
    }
    
    /**
     * AJAX: Získání OOPP pro oddělení (pro training flow)
     */
    public function ajax_get_for_department() {
        saw_verify_ajax_unified();
        
        $tr = $this->tr;
        
        $department_id = intval($_POST['department_id'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);
        
        if (!$department_id) {
            wp_send_json_error(array('message' => $tr('error_invalid_department', 'Neplatné oddělení')));
        }
        
        $oopp_items = $this->model->get_for_department($department_id, $branch_id);
        
        wp_send_json_success(array(
            'items' => $oopp_items,
            'count' => count($oopp_items),
        ));
    }
}