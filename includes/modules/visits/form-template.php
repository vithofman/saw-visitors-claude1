<?php

error_log("[VISITS FORM DEBUG] item defined: " . (isset($item) ? 'YES' : 'NO'));
error_log("[VISITS FORM DEBUG] form_item defined: " . (isset($form_item) ? 'YES' : 'NO'));
if (isset($item)) {
    error_log("[VISITS FORM DEBUG] item['id']: " . ($item['id'] ?? 'NOT SET'));
}
if (isset($form_item)) {
    error_log("[VISITS FORM DEBUG] form_item['id']: " . ($form_item['id'] ?? 'NOT SET'));
}

// DEBUG 2 - kontrola $is_edit a existing_visitors query
error_log("[VISITS FORM DEBUG 2] is_edit defined: " . (isset($is_edit) ? 'YES' : 'NO'));
error_log("[VISITS FORM DEBUG 2] is_edit value: " . ($is_edit ? 'TRUE' : 'FALSE'));
error_log("[VISITS FORM DEBUG 2] Condition check: is_edit=" . ($is_edit ? '1' : '0') . ", item[id]=" . (!empty($item['id']) ? $item['id'] : 'EMPTY'));

/**
 * Visits Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.3.0 - FIXED: Added inline init script for reliable AJAX loading
 */

if (!defined('ABSPATH')) exit;

// Translations
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') ? saw_get_translations($lang, 'admin', 'visits') : [];
$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

if (!class_exists('SAW_Component_Select_Create')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/select-create/class-saw-component-select-create.php';
}

global $wpdb;

$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_edit = !empty($item);
$item = $item ?? array();

$customer_id = SAW_Context::get_customer_id();
$context_branch_id = SAW_Context::get_branch_id();
$branches = $branches ?? array();
$companies = $companies ?? array();

// Get languages for translations
$languages = $config['form_languages'] ?? array();

// Get current user language
$user_lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $user_lang = SAW_Component_Language_Switcher::get_user_language();
}

// Find active language index
$active_lang_index = 0;
foreach ($languages as $idx => $lang) {
    if ($lang['code'] === $user_lang) {
        $active_lang_index = $idx;
        break;
    }
}

// Load action info data for edit mode
$action_info_translations = array();
$action_documents = array();
$action_oopp = array();
$has_action_data = false;

if ($is_edit && !empty($item['id'])) {
    // Instantiate model
    $model = null;
    if (class_exists('SAW_Module_Visits_Model')) {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/';
        $config_file = $module_path . 'config.php';
        if (file_exists($config_file)) {
            $config = require $config_file;
            $config['path'] = $module_path;
            require_once $module_path . 'model.php';
            $model = new SAW_Module_Visits_Model($config);
        }
    }
    
    // Load action info
    if ($model && method_exists($model, 'get_action_info')) {
        $action_info = $model->get_action_info($item['id']);
        
        if ($action_info && !empty($action_info['id'])) {
            // Load translations
            if (method_exists($model, 'get_action_info_translations')) {
                $action_info_translations = $model->get_action_info_translations($action_info['id']);
            }
            
            // Load documents
            $action_documents = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_visit_action_documents 
                 WHERE visit_id = %d ORDER BY sort_order ASC",
                $item['id']
            ), ARRAY_A);
            
            // Load OOPP
            $action_oopp = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_visit_action_oopp 
                 WHERE visit_id = %d ORDER BY sort_order ASC",
                $item['id']
            ), ARRAY_A);
            
            // Check if there's any data
            $has_action_data = !empty($action_info_translations) || 
                              !empty($action_documents) || 
                              !empty($action_oopp);
        }
    }
}

// Load branches
if (empty($branches) && $customer_id) {
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches', $customer_id
    ), ARRAY_A);
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// Load companies
if (empty($companies) && $customer_id) {
    $sql = $wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_archived = 0",
        $wpdb->prefix . 'saw_companies', $customer_id
    );
    if ($context_branch_id) {
        $sql .= $wpdb->prepare(" AND branch_id = %d", $context_branch_id);
    }
    $sql .= " ORDER BY name ASC";
    $companies_data = $wpdb->get_results($sql, ARRAY_A);
    $companies = array();
    foreach ($companies_data as $company) {
        $companies[$company['id']] = $company['name'];
    }
}

// ⭐ FIX: Pobočka je vždy z kontextu (branchswitcher), není editovatelná
// V edit mode použijeme hodnotu z item, jinak vždy z kontextu
$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    $selected_branch_id = $item['branch_id'];
} else {
    // CREATE mode nebo pokud není v item - vždy použít kontext
    $selected_branch_id = $context_branch_id;
}

// Pokud stále není pobočka, zkusit načíst z branchswitcher
if (!$selected_branch_id && class_exists('SAW_Context')) {
    $selected_branch_id = SAW_Context::get_branch_id();
}

// Determine if visit has company (legal person) or is physical person
$has_company = 1; // Default: legal person (for new visits)
if ($is_edit) {
    // In edit mode, check if company_id exists and is not NULL
    if (array_key_exists('company_id', $item)) {
        // Key exists - check if it's NULL or empty
        $has_company = (!empty($item['company_id']) && $item['company_id'] !== null) ? 1 : 0;
    } else {
        // Key doesn't exist in $item - query database to be sure
        if (!empty($item['id'])) {
            global $wpdb;
            $company_id = $wpdb->get_var($wpdb->prepare(
                "SELECT company_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
                intval($item['id'])
            ));
            $has_company = (!empty($company_id) && $company_id !== null) ? 1 : 0;
        }
    }
}

$existing_host_ids = array();
if ($is_edit && !empty($item['id'])) {
    $existing_host_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM %i WHERE visit_id = %d",
        $wpdb->prefix . 'saw_visit_hosts',
        $item['id']
    ));
}

// === VISITORS DATA FOR EDIT MODE ===
$existing_visitors = array();
$visitors_mode = 'create';
if ($is_edit && !empty($item['id'])) {
    $visitors_mode = 'edit';
    $existing_visitors = $wpdb->get_results($wpdb->prepare(
        "SELECT id, first_name, last_name, email, phone, position
         FROM {$wpdb->prefix}saw_visitors 
         WHERE visit_id = %d 
         ORDER BY id ASC",
        intval($item['id'])
    ), ARRAY_A);
}

// DEBUG 3 - výsledek SQL dotazu
error_log("[VISITS FORM DEBUG 3] visitors_mode: " . $visitors_mode);
error_log("[VISITS FORM DEBUG 3] existing_visitors count: " . count($existing_visitors));
error_log("[VISITS FORM DEBUG 3] existing_visitors data: " . json_encode($existing_visitors));

// Připravit překlady pro JS
$visitors_translations = array(
    'title_add' => $tr('title_add_visitor', 'Přidat návštěvníka'),
    'title_edit' => $tr('title_edit_visitor', 'Upravit návštěvníka'),
    'btn_add' => $tr('btn_add_visitor', 'Přidat návštěvníka'),
    'btn_save' => $tr('btn_save_visitor', 'Uložit návštěvníka'),
    'confirm_delete' => $tr('confirm_delete_visitor', 'Opravdu chcete odebrat tohoto návštěvníka?'),
    'error_required' => $tr('error_required_fields', 'Vyplňte povinná pole (jméno a příjmení).'),
    'error_email' => $tr('error_invalid_email', 'Zadejte platný email.'),
    'error_duplicate' => $tr('error_duplicate_email', 'Návštěvník s tímto emailem již je v seznamu.'),
    'person_singular' => $tr('person_singular', 'návštěvník'),
    'person_few' => $tr('person_few', 'návštěvníci'),
    'person_many' => $tr('person_many', 'návštěvníků'),
);

$form_action = $is_edit 
    ? home_url('/admin/visits/' . $item['id'] . '/edit')
    : home_url('/admin/visits/create');
?>

<style>
/* Visitor Type Toggle Styles */
.saw-radio-toggle input:checked + .saw-radio-toggle-content {
    border-color: #0073aa !important;
    background: #f0f6fc !important;
    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1) !important;
}

.saw-radio-toggle:hover .saw-radio-toggle-content {
    border-color: #0073aa !important;
}

.saw-radio-toggle input:checked + .saw-radio-toggle-content > div:first-child {
    color: #0073aa !important;
}

/* Rotation animation for loading spinner */
@keyframes rotation {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? $tr('form_title_edit', 'Upravit návštěvu') : $tr('form_title_create', 'Nová návštěva'); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-back-button">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php endif; ?>
            <?php echo $tr('btn_back_to_list', 'Zpět na seznam'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-visits">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" 
          class="saw-visit-form"
          data-visitors-mode="<?php echo esc_attr($visitors_mode); ?>"
          data-visit-id="<?php echo !empty($item['id']) ? intval($item['id']) : ''; ?>"
          data-visitors-data="<?php echo esc_attr(json_encode($existing_visitors)); ?>"
          data-visitors-translations="<?php echo esc_attr(json_encode($visitors_translations)); ?>">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_visits' : 'saw_create_visits';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <div id="visit-main-form">
        
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-admin-generic"></span>
                <?php endif; ?>
                <strong><?php echo $tr('form_section_basic', 'Základní informace'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch - FIXED: Neměnná z branchswitcher, pole je disabled -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label saw-required"><?php echo $tr('form_branch', 'Pobočka'); ?></label>
                        <!-- Hidden input pro odeslání hodnoty -->
                        <input type="hidden" name="branch_id" id="branch_id_hidden" value="<?php echo $selected_branch_id ? esc_attr($selected_branch_id) : ''; ?>">
                        <!-- Select je disabled, pouze pro zobrazení -->
                        <select id="branch_id" class="saw-input" disabled style="background-color: #f0f0f1; cursor: not-allowed;" aria-label="<?php echo esc_attr($tr('form_branch', 'Pobočka')); ?>">
                            <option value="">-- <?php echo $tr('form_select_branch', 'Vyberte pobočku'); ?> --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option value="<?php echo esc_attr($branch_id); ?>" <?php selected($selected_branch_id, $branch_id); ?>>
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selected_branch_id): ?>
                            <p class="saw-field-hint" style="margin-top: 4px; font-size: 13px; color: #646970; display: flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <?php echo esc_html($tr('form_branch_locked', 'Pobočka je určena z branchswitcher a nelze ji změnit')); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ⭐ NEW: Physical vs Legal Person Radio - Styled Toggle -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required"><?php echo $tr('form_visitor_type', 'Typ návštěvníka'); ?></label>
                        <div class="saw-visitor-type-toggle" style="display: flex; gap: 16px; margin-top: 12px; flex-wrap: wrap;">
                            <label class="saw-radio-toggle" style="flex: 1; min-width: 200px; position: relative; cursor: pointer;">
                                <input type="radio" 
                                       name="has_company" 
                                       value="1" 
                                       <?php checked($has_company, 1); ?>
                                       style="position: absolute; opacity: 0; pointer-events: none;">
                                <div class="saw-radio-toggle-content" style="padding: 16px; border: 2px solid #dcdcde; border-radius: 8px; transition: all 0.2s; background: #fff;">
                                    <div style="font-weight: 600; margin-bottom: 4px; color: #1d2327; font-size: 15px;"><?php echo $tr('form_legal_person', 'Právnická osoba'); ?></div>
                                    <div style="font-size: 13px; color: #646970;">Firma, instituce</div>
                                </div>
                            </label>
                            <label class="saw-radio-toggle" style="flex: 1; min-width: 200px; position: relative; cursor: pointer;">
                                <input type="radio" 
                                       name="has_company" 
                                       value="0" 
                                       <?php checked($has_company, 0); ?>
                                       style="position: absolute; opacity: 0; pointer-events: none;">
                                <div class="saw-radio-toggle-content" style="padding: 16px; border: 2px solid #dcdcde; border-radius: 8px; transition: all 0.2s; background: #fff;">
                                    <div style="font-weight: 600; margin-bottom: 4px; color: #1d2327; font-size: 15px;"><?php echo $tr('form_physical_person', 'Fyzická osoba'); ?></div>
                                    <div style="font-size: 13px; color: #646970;">Soukromá osoba</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- ⭐ Conditional Company Field -->
                <div class="saw-form-row field-company-row" style="<?php echo $has_company ? '' : 'display: none;'; ?>">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        // ⭐ FIX: Use neutral field name to prevent browser autocomplete from recognizing it as "company"
                        // Backend will remap 'visit_company_selection' back to 'company_id'
                        $company_select = new SAW_Component_Select_Create('visit_company_selection', array(
                            'label' => $tr('form_company', 'Firma'),
                            'options' => $companies,
                            'selected' => $item['company_id'] ?? '',
                            'required' => false, // JavaScript will handle this dynamically
                            'placeholder' => '-- ' . $tr('form_select_company', 'Vyberte firmu') . ' --',
                            'inline_create' => array(
                                'enabled' => true,
                                'target_module' => 'companies',
                                'button_text' => '+ ' . $tr('form_new_company', 'Nová firma'),
                                'prefill' => array(
                                    'branch_id' => $selected_branch_id,
                                    'customer_id' => $customer_id,
                                ),
                            ),
                        ));
                        $company_select->render();
                        ?>
                    </div>
                </div>
                
                <!-- Visit Type & Status -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="visit_type" class="saw-label saw-required"><?php echo $tr('form_visit_type', 'Typ návštěvy'); ?></label>
                        <select name="visit_type" id="visit_type" class="saw-input" required>
                            <option value="planned" <?php selected($item['visit_type'] ?? 'planned', 'planned'); ?>><?php echo $tr('type_planned', 'Plánovaná'); ?></option>
                            <option value="walk_in" <?php selected($item['visit_type'] ?? '', 'walk_in'); ?>><?php echo $tr('type_walk_in', 'Walk-in'); ?></option>
                        </select>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="status" class="saw-label saw-required"><?php echo $tr('form_status', 'Stav'); ?></label>
                        <select name="status" id="status" class="saw-input" required>
                            <option value="draft" <?php selected($item['status'] ?? '', 'draft'); ?>><?php echo $tr('status_draft', 'Koncept'); ?></option>
                            <option value="pending" <?php selected($item['status'] ?? 'pending', 'pending'); ?>><?php echo $tr('status_pending', 'Čekající'); ?></option>
                            <option value="confirmed" <?php selected($item['status'] ?? '', 'confirmed'); ?>><?php echo $tr('status_confirmed', 'Potvrzená'); ?></option>
                            <option value="in_progress" <?php selected($item['status'] ?? '', 'in_progress'); ?>><?php echo $tr('status_in_progress', 'Probíhající'); ?></option>
                            <option value="completed" <?php selected($item['status'] ?? '', 'completed'); ?>><?php echo $tr('status_completed', 'Dokončená'); ?></option>
                            <option value="cancelled" <?php selected($item['status'] ?? '', 'cancelled'); ?>><?php echo $tr('status_cancelled', 'Zrušená'); ?></option>
                        </select>
                    </div>
                </div>
                
                <!-- Schedule Days (Multi-day support) - IMPROVED LAYOUT -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label"><?php echo $tr('form_schedule_days', 'Dny návštěvy'); ?></label>
                        
                        <div id="visit-schedule-container" class="saw-schedule-container">
                            <?php
                            $schedules = array();
                            if ($is_edit && !empty($item['id'])) {
                                $schedules = $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM %i WHERE visit_id = %d ORDER BY sort_order ASC",
                                    $wpdb->prefix . 'saw_visit_schedules',
                                    $item['id']
                                ), ARRAY_A);
                            }
                            
                            if (empty($schedules)) {
                                // Prefill date and time from URL parameters (when clicking on calendar)
                                $prefill_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
                                // Extract date part if datetime format (YYYY-MM-DDTHH:mm:ss)
                                if (strpos($prefill_date, 'T') !== false) {
                                    $prefill_date = explode('T', $prefill_date)[0];
                                }
                                
                                $prefill_time_from = '';
                                $prefill_time_to = '';
                                
                                // Get time from URL parameter
                                if (isset($_GET['time'])) {
                                    $prefill_time_from = sanitize_text_field($_GET['time']);
                                    // Calculate time_to as +1 hour
                                    if (!empty($prefill_time_from)) {
                                        $time_parts = explode(':', $prefill_time_from);
                                        $hours = intval($time_parts[0]);
                                        $minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
                                        $next_hour = ($hours + 1) % 24;
                                        $prefill_time_to = sprintf('%02d:%02d', $next_hour, $minutes);
                                    }
                                } elseif (isset($_GET['date']) && strpos($_GET['date'], 'T') !== false) {
                                    // Extract from datetime format
                                    $datetime_parts = explode('T', $_GET['date']);
                                    if (isset($datetime_parts[1])) {
                                        $time_part = $datetime_parts[1];
                                        $time_only = explode(':', $time_part)[0] . ':' . explode(':', $time_part)[1];
                                        $prefill_time_from = $time_only;
                                        // Calculate +1 hour
                                        $time_parts = explode(':', $prefill_time_from);
                                        $hours = intval($time_parts[0]);
                                        $minutes = intval($time_parts[1]);
                                        $next_hour = ($hours + 1) % 24;
                                        $prefill_time_to = sprintf('%02d:%02d', $next_hour, $minutes);
                                    }
                                }
                                
                                $schedules = array(array(
                                    'date' => $prefill_date, 
                                    'time_from' => $prefill_time_from, 
                                    'time_to' => $prefill_time_to, 
                                    'notes' => ''
                                ));
                            }
                            
                            foreach ($schedules as $index => $schedule): ?>
                                <div class="saw-schedule-row" data-index="<?php echo $index; ?>">
                                    <div class="saw-schedule-row-fields">
                                        <!-- Row 1: Date (full width) -->
                                        <div class="saw-schedule-field-group">
                                            <div class="saw-schedule-field-group-row date-row">
                                                <div class="saw-schedule-field saw-schedule-date">
                                                    <label><?php echo $tr('form_date', 'Datum'); ?></label>
                                                    <input type="date" 
                                                           name="schedule_dates[]" 
                                                           value="<?php echo esc_attr($schedule['date'] ?? ''); ?>" 
                                                           class="saw-input saw-schedule-date-input"
                                                           required>
                                                </div>
                                            </div>
                                            
                                            <!-- Row 2: Time From | Time To (50/50) -->
                                            <div class="saw-schedule-field-group-row time-row">
                                                <div class="saw-schedule-field saw-schedule-time">
                                                    <label><?php echo $tr('form_time_from', 'Čas od'); ?></label>
                                                    <input type="time" 
                                                           name="schedule_times_from[]" 
                                                           value="<?php echo esc_attr($schedule['time_from'] ?? ''); ?>" 
                                                           class="saw-input saw-schedule-time-input">
                                                </div>
                                                
                                                <div class="saw-schedule-field saw-schedule-time">
                                                    <label><?php echo $tr('form_time_to', 'Čas do'); ?></label>
                                                    <input type="time" 
                                                           name="schedule_times_to[]" 
                                                           value="<?php echo esc_attr($schedule['time_to'] ?? ''); ?>" 
                                                           class="saw-input saw-schedule-time-input">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Row 3: Notes (full width) -->
                                        <div class="saw-schedule-field saw-schedule-notes-full">
                                            <label><?php echo $tr('form_note', 'Poznámka (volitelné)'); ?></label>
                                            <input type="text" 
                                                   name="schedule_notes[]" 
                                                   value="<?php echo esc_attr($schedule['notes'] ?? ''); ?>" 
                                                   class="saw-input saw-schedule-notes-input"
                                                   placeholder="<?php echo esc_attr($tr('form_note_placeholder', 'Poznámka k danému dni')); ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- Row 4: Action Buttons (bottom right) -->
                                    <div class="saw-schedule-row-actions">
                                        <button type="button" 
                                                class="saw-remove-schedule-day" 
                                                title="<?php echo esc_attr($tr('btn_remove_day', 'Odstranit den')); ?>"
                                                <?php echo count($schedules) === 1 ? 'disabled' : ''; ?>>
                                            <?php if (class_exists('SAW_Icons')): ?>
                                                <?php echo SAW_Icons::get('trash-2'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-trash"></span>
                                            <?php endif; ?>
                                        </button>
                                        <button type="button" class="saw-add-schedule-day-inline" title="<?php echo esc_attr($tr('btn_add_day', 'Přidat další den')); ?>">
                                            <?php if (class_exists('SAW_Icons')): ?>
                                                <?php echo SAW_Icons::get('plus'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-plus-alt"></span>
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <p class="saw-field-hint">
                            <?php echo $tr('form_schedule_hint', 'Přidejte jeden nebo více dnů, kdy návštěva proběhne. Každý den může mít různý čas.'); ?>
                        </p>
                    </div>
                </div>
                
                <!-- ================================================
                     VISITORS SECTION
                     ================================================ -->
                <!-- Visitors Section - Modern Design -->
                <div class="saw-visitors-section">
                    <div class="saw-section-header">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <?php echo esc_html($tr('section_visitors', 'Návštěvníci')); ?>
                        </h4>
                        <button type="button" id="btn-add-visitor">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <?php echo esc_html($tr('btn_add_visitor', 'Přidat návštěvníka')); ?>
                        </button>
                    </div>
                    
                    <!-- Seznam návštěvníků (renderuje JS) -->
                    <div id="visitors-list-container">
                        <!-- Empty State - Modern -->
                        <div id="visitors-empty-state">
                            <div class="saw-empty-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <p><?php echo esc_html($tr('visitors_empty', 'Zatím nebyli přidáni žádní návštěvníci')); ?></p>
                            <p class="saw-text-muted"><?php echo esc_html($tr('visitors_empty_hint', 'Klikněte na tlačítko "Přidat návštěvníka" výše')); ?></p>
                        </div>
                        
                        <!-- Seznam karet (plní JS) -->
                        <div id="visitors-list"></div>
                        
                        <!-- Counter - Modern -->
                        <div id="visitors-counter" style="display: none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            <?php echo esc_html($tr('visitors_total', 'Celkem:')); ?>
                            <strong><span id="visitors-count">0</span></strong>
                            <span id="visitors-count-label"><?php echo esc_html($tr('visitors_label', 'návštěvníků')); ?></span>
                        </div>
                    </div>
                    
                    <!-- Hidden input pro JSON data -->
                    <input type="hidden" name="visitors_json" id="visitors-json-input" value="[]">
                </div>
                
                <!-- Invitation Email -->
                <div class="saw-form-row" style="margin-top: 24px;">
                    <div class="saw-form-group saw-col-12">
                        <label for="invitation_email" class="saw-label"><?php echo $tr('form_invitation_email', 'Email pro pozvánku'); ?></label>
                        <input type="email" name="invitation_email" id="invitation_email" class="saw-input" value="<?php echo esc_attr($item['invitation_email'] ?? ''); ?>" placeholder="email@example.com">
                    </div>
                </div>
                
                <!-- Purpose -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="purpose" class="saw-label"><?php echo $tr('form_purpose', 'Účel návštěvy'); ?></label>
                        <textarea name="purpose" id="purpose" class="saw-input" rows="3" placeholder="<?php echo esc_attr($tr('form_purpose_placeholder', 'Stručný popis účelu návštěvy...')); ?>"><?php echo esc_textarea($item['purpose'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Hosts (loaded via AJAX when branch changes) -->
                <div class="saw-form-row field-hosts-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required">
                            <?php echo $tr('form_hosts', 'Koho navštěvují'); ?>
                            <span id="host-counter" class="saw-badge saw-badge-primary" style="display: inline-block; margin-left: 12px;">
                                <span id="host-selected">0</span> / <span id="host-total">0</span>
                            </span>
                        </label>
                        
                        <div class="saw-host-controls" style="display: none; margin-bottom: 12px; gap: 12px; align-items: center;">
                            <input type="text" id="host-search" class="saw-input" placeholder="🔍 <?php echo esc_attr($tr('form_hosts_search', 'Hledat uživatele...')); ?>" style="flex: 1; max-width: 300px;">
                            <label style="display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer; user-select: none;">
                                <input type="checkbox" id="select-all-host" style="margin: 0; cursor: pointer;">
                                <span style="font-weight: 600; color: #2c3338;"><?php echo $tr('form_select_all', 'Vybrat vše'); ?></span>
                            </label>
                        </div>
                        
                        <div id="hosts-list" style="border: 2px solid #dcdcde; border-radius: 6px; max-height: 320px; overflow-y: auto; background: #fff;">
                            <?php if ($selected_branch_id): ?>
                                <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">
                                    <?php if (class_exists('SAW_Icons')): ?>
                                        <span style="animation: rotation 1s infinite linear; display: inline-block;"><?php echo SAW_Icons::get('refresh-cw'); ?></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear; display: inline-block;"></span>
                                    <?php endif; ?> 
                                    Načítám uživatele...
                                </p>
                            <?php else: ?>
                                <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">
                                    <?php echo $tr('form_select_branch_first', 'Nejprve vyberte pobočku výše'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================
             SPECIFICKÉ INFORMACE PRO AKCI
             ================================================ -->
        <details class="saw-form-section saw-form-section-action-info" <?php echo $has_action_data ? 'open' : ''; ?>>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('alert-triangle'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-warning"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_action_info', 'Specifické informace pro akci')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <p class="saw-help-text" style="margin-bottom: 16px; color: #6b7280;">
                    <?php echo esc_html($tr('help_action_info', 'Pokyny, dokumenty a OOPP, které návštěvníci uvidí NAVÍC k běžnému školení a vztahují se výhradně k této návštěvě.')); ?>
                </p>
                
                <!-- Jazykové záložky -->
                <?php if (!empty($languages) && count($languages) > 1): ?>
                <div class="saw-language-tabs-wrapper">
                    <button type="button" class="saw-language-tab-nav saw-language-tab-nav-prev" aria-label="Předchozí jazyky">
                        <?php if (class_exists('SAW_Icons')): ?>
                            <?php echo SAW_Icons::get('chevron-left'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php endif; ?>
                    </button>
                    <div class="saw-language-tabs">
                        <?php foreach ($languages as $index => $language): ?>
                            <button 
                                type="button" 
                                class="saw-language-tab <?php echo $index === $active_lang_index ? 'active' : ''; ?>" 
                                data-tab="action-lang-<?php echo esc_attr($language['code']); ?>"
                            >
                                <?php echo !empty($language['flag']) ? esc_html($language['flag']) : '🌐'; ?> 
                                <?php echo esc_html($language['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="saw-language-tab-nav saw-language-tab-nav-next" aria-label="Další jazyky">
                        <?php if (class_exists('SAW_Icons')): ?>
                            <?php echo SAW_Icons::get('chevron-right'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <?php endif; ?>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Obsah záložek -->
                <div class="saw-language-contents">
                    <?php foreach ($languages as $index => $language): 
                        $lang_code = $language['code'];
                        $lang_trans = $action_info_translations[$lang_code] ?? array();
                    ?>
                        <div 
                            class="saw-language-content" 
                            data-tab-content="action-lang-<?php echo esc_attr($lang_code); ?>"
                            style="<?php echo $index === $active_lang_index ? 'display: block;' : 'display: none;'; ?>"
                        >
                            
                            <!-- Název akce -->
                            <div class="saw-form-row">
                                <div class="saw-form-group saw-col-12">
                                    <label for="action_name_<?php echo esc_attr($lang_code); ?>" class="saw-label <?php echo $index === $active_lang_index ? 'saw-required' : ''; ?>">
                                        <?php echo esc_html($tr('field_action_name', 'Název akce')); ?>
                                        <?php if ($index === $active_lang_index): ?>
                                            <span class="saw-required-marker">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" 
                                           name="action_info_translations[<?php echo esc_attr($lang_code); ?>][name]" 
                                           id="action_name_<?php echo esc_attr($lang_code); ?>" 
                                           class="saw-input" 
                                           value="<?php echo esc_attr($lang_trans['name'] ?? ''); ?>" 
                                           placeholder="<?php echo esc_attr($tr('placeholder_action_name', 'např. Dláždění parkoviště, Revize elektro...')); ?>"
                                           <?php echo $index === $active_lang_index ? 'required' : ''; ?>>
                                    <p class="saw-help-text">
                                        <?php echo esc_html($tr('help_action_name', 'Krátký identifikátor akce. Použije se jako nadpis v sekci specifických informací.')); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Popis akce -->
                            <div class="saw-form-row" style="margin-top: 16px;">
                                <div class="saw-form-group saw-col-12">
                                    <label for="action_description_<?php echo esc_attr($lang_code); ?>" class="saw-label">
                                        <?php echo esc_html($tr('field_action_description', 'Popis akce')); ?>
                                    </label>
                                    <textarea 
                                        name="action_info_translations[<?php echo esc_attr($lang_code); ?>][description]" 
                                        id="action_description_<?php echo esc_attr($lang_code); ?>" 
                                        class="saw-input" 
                                        rows="3"
                                        placeholder="<?php echo esc_attr($tr('placeholder_action_description', 'Krátký popis, co se bude na akci dít...')); ?>"><?php echo esc_textarea($lang_trans['description'] ?? ''); ?></textarea>
                                    <p class="saw-help-text">
                                        <?php echo esc_html($tr('help_action_description', 'Volitelný popis akce pro lepší orientaci.')); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Specifické pokyny (WYSIWYG) -->
                            <div class="saw-form-row" style="margin-top: 16px;">
                                <div class="saw-form-group saw-col-12">
                                    <label for="action_content_text_<?php echo esc_attr($lang_code); ?>" class="saw-label">
                                        <?php echo esc_html($tr('field_action_content', 'Specifické pokyny')); ?>
                                    </label>
                                    <?php
                                    wp_editor(
                                        $lang_trans['content_text'] ?? '',
                                        'action_content_text_' . $lang_code,
                                        array(
                                            'textarea_name' => 'action_info_translations[' . $lang_code . '][content_text]',
                                            'textarea_rows' => 8,
                                            'media_buttons' => false,
                                            'teeny' => false,
                                            'quicktags' => true,
                                            'tinymce' => array(
                                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                                'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                                'toolbar3' => '',
                                                'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                            ),
                                        )
                                    );
                                    ?>
                                </div>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Dokumenty k akci (nejsou jazykové) -->
                <div class="saw-action-info-content" style="margin-top: 24px;">
                    
                    <!-- Dokumenty k akci -->
                    <div class="saw-form-row" style="margin-top: 24px;">
                        <div class="saw-form-group saw-col-12">
                            <label class="saw-label">
                                <?php if (class_exists('SAW_Icons')): ?>
                                    <span style="display: inline-flex; align-items: center; margin-right: 6px; color: var(--saw-warning);"><?php echo SAW_Icons::get('file-text', 'saw-icon--md'); ?></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-media-document" style="font-size: 18px; vertical-align: middle; margin-right: 6px; color: #f59e0b; display: inline-block; font-family: dashicons !important;"></span>
                                <?php endif; ?>
                                <?php echo esc_html($tr('field_action_documents', 'Dokumenty k akci')); ?>
                            </label>
                            
                            <?php
                            // Prepare existing files for component
                            $existing_action_docs = array();
                            if (!empty($action_documents)) {
                                $upload_dir = wp_upload_dir();
                                foreach ($action_documents as $doc) {
                                    $doc_url = $upload_dir['baseurl'] . '/' . ltrim($doc['file_path'], '/');
                                    $existing_action_docs[] = array(
                                        'id' => $doc['id'], // Database ID - important for tracking
                                        'url' => $doc_url,
                                        'path' => $doc['file_path'],
                                        'name' => $doc['file_name'],
                                        'size' => $doc['file_size'],
                                        'type' => $doc['mime_type'] ?? 'application/octet-stream',
                                        'extension' => strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION)),
                                    );
                                }
                            }
                            
                            // Load file upload component
                            if (!function_exists('saw_file_upload_input')) {
                                require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                            }
                            
                            saw_file_upload_input(array(
                                'name' => 'action_documents[]',
                                'id' => 'action-documents-upload',
                                'multiple' => true,
                                'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf,.jpg,.jpeg,.png',
                                'max_size' => 10485760, // 10MB
                                'max_files' => 0, // No limit
                                'context' => 'visit_action_documents',
                                'class' => 'saw-action-documents-upload',
                                'existing_files' => $existing_action_docs,
                            ));
                            ?>
                        </div>
                    </div>
                    
                    <!-- OOPP pro akci -->
                    <div class="saw-form-row" style="margin-top: 24px;">
                        <div class="saw-form-group saw-col-12">
                            <label class="saw-label">
                                <?php if (class_exists('SAW_Icons')): ?>
                                    <span style="display: inline-flex; align-items: center; margin-right: 6px; color: var(--saw-warning);"><?php echo SAW_Icons::get('shield', 'saw-icon--md'); ?></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-shield" style="font-size: 18px; vertical-align: middle; margin-right: 6px; color: #f59e0b; display: inline-block; font-family: dashicons !important;"></span>
                                <?php endif; ?>
                                <?php echo esc_html($tr('field_action_oopp', 'Ochranné pomůcky pro tuto akci')); ?>
                            </label>
                            <p class="saw-help-text" style="margin-bottom: 16px; color: #6b7280; font-size: 13px;">
                                <?php echo esc_html($tr('help_action_oopp_intro', 'Vyberte OOPP, které jsou specifické pro tuto akci. Tyto pomůcky se zobrazí návštěvníkům navíc k běžnému školení.')); ?>
                            </p>
                            
                            <?php
                            // Načíst OOPP pro akce (is_global = 0)
                            $action_oopp_options = array();
                            if ($customer_id) {
                                $action_oopp_options = $wpdb->get_results($wpdb->prepare(
                                    "SELECT o.id, t.name, g.name as group_name
                                     FROM {$wpdb->prefix}saw_oopp o
                                     LEFT JOIN {$wpdb->prefix}saw_oopp_translations t ON o.id = t.oopp_id AND t.language_code = 'cs'
                                     LEFT JOIN {$wpdb->prefix}saw_oopp_groups g ON o.group_id = g.id
                                     WHERE o.customer_id = %d 
                                       AND o.is_active = 1 
                                       AND o.is_global = 0
                                     ORDER BY g.display_order, t.name",
                                    $customer_id
                                ), ARRAY_A);
                            }
                            
                            // Vybrané OOPP pro tuto návštěvu
                            $selected_oopp_ids = array();
                            if (!empty($action_oopp)) {
                                $selected_oopp_ids = array_column($action_oopp, 'oopp_id');
                            }
                            ?>
                            
                            <?php if (empty($action_oopp_options)): ?>
                                <div class="saw-alert saw-alert-info" style="padding: 16px; background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px; margin-bottom: 12px;">
                                    <p style="margin: 0 0 8px 0; color: #1e40af; font-size: 14px;">
                                        <strong>💡 Tip:</strong> <?php echo esc_html($tr('no_action_oopp', 'Zatím nemáte vytvořené žádné OOPP pro konkrétní akce.')); ?>
                                    </p>
                                    <a href="<?php echo esc_url(home_url('/admin/oopp/create/')); ?>" class="saw-link" style="color: #2563eb; text-decoration: underline; font-weight: 500;">
                                        <?php echo esc_html($tr('create_action_oopp', 'Vytvořit nové OOPP pro akce')); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="saw-oopp-selector" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px;">
                                    <div class="saw-oopp-available" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                                        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #374151; display: flex; align-items: center; gap: 8px;">
                                            <?php if (class_exists('SAW_Icons')): ?>
                                                <?php echo SAW_Icons::get('list', 'saw-icon--sm'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-list-view" style="font-size: 16px; color: #6b7280; display: inline-block; font-family: dashicons !important;"></span>
                                            <?php endif; ?>
                                            <?php echo esc_html($tr('available_oopp', 'Dostupné pomůcky')); ?>
                                        </h4>
                                        <div class="saw-oopp-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($action_oopp_options as $oopp): ?>
                                                <?php if (!in_array($oopp['id'], $selected_oopp_ids)): ?>
                                                    <div class="saw-oopp-item" data-id="<?php echo esc_attr($oopp['id']); ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; transition: all 0.2s ease;">
                                                        <div style="flex: 1; min-width: 0;">
                                                            <div class="saw-oopp-name" style="font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 2px;"><?php echo esc_html($oopp['name']); ?></div>
                                                            <?php if (!empty($oopp['group_name'])): ?>
                                                                <div class="saw-oopp-group" style="font-size: 12px; color: #6b7280;"><?php echo esc_html($oopp['group_name']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="button" class="saw-btn-icon saw-add-oopp" title="Přidat" style="background: #10b981; color: #fff; border: none; border-radius: 4px; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; flex-shrink: 0; transition: all 0.2s ease;">+</button>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="saw-oopp-selected" style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px;">
                                        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #92400e; display: flex; align-items: center; gap: 8px;">
                                            <?php if (class_exists('SAW_Icons')): ?>
                                                <span style="color: var(--saw-warning);"><?php echo SAW_Icons::get('check-circle', 'saw-icon--sm'); ?></span>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-yes-alt" style="font-size: 16px; color: #f59e0b; display: inline-block; font-family: dashicons !important;"></span>
                                            <?php endif; ?>
                                            <?php echo esc_html($tr('selected_oopp', 'Vybrané pro akci')); ?>
                                        </h4>
                                        <div class="saw-oopp-list" id="selected-action-oopp" style="display: flex; flex-direction: column; gap: 8px; min-height: 60px;">
                                            <?php if (empty($selected_oopp_ids)): ?>
                                                <div style="padding: 20px; text-align: center; color: #92400e; font-size: 13px; font-style: italic;">
                                                    <?php echo esc_html($tr('no_selected_oopp', 'Zatím není vybráno žádné OOPP')); ?>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($action_oopp_options as $oopp): ?>
                                                    <?php if (in_array($oopp['id'], $selected_oopp_ids)): ?>
                                                        <?php 
                                                        $selected_oopp = array_filter($action_oopp, function($a) use ($oopp) {
                                                            return $a['oopp_id'] == $oopp['id'];
                                                        });
                                                        $selected_oopp = reset($selected_oopp);
                                                        ?>
                                                        <div class="saw-oopp-item selected" data-id="<?php echo esc_attr($oopp['id']); ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border: 1px solid #f59e0b; border-radius: 6px; transition: all 0.2s ease;">
                                                            <div style="flex: 1; min-width: 0;">
                                                                <div class="saw-oopp-name" style="font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 4px;"><?php echo esc_html($oopp['name']); ?></div>
                                                                <label class="saw-checkbox-inline" style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; cursor: pointer;">
                                                                    <input type="checkbox" 
                                                                           name="action_oopp_required[<?php echo esc_attr($oopp['id']); ?>]" 
                                                                           value="1" 
                                                                           <?php checked($selected_oopp['is_required'] ?? 1, 1); ?>
                                                                           style="margin: 0; cursor: pointer;">
                                                                    <span><?php echo esc_html($tr('required', 'Povinné')); ?></span>
                                                                </label>
                                                            </div>
                                                            <button type="button" class="saw-btn-icon saw-remove-oopp" title="Odebrat" style="background: #ef4444; color: #fff; border: none; border-radius: 4px; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; transition: all 0.2s ease;">✕</button>
                                                            <input type="hidden" name="action_oopp_ids[]" value="<?php echo esc_attr($oopp['id']); ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="saw-alert saw-alert-info" style="margin-top: 16px; padding: 12px 16px; background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px;">
                                <p style="margin: 0; color: #1e40af; font-size: 13px; line-height: 1.5;">
                                    <strong>💡 Tip:</strong> <?php echo esc_html($tr('help_action_oopp', 'Nové OOPP pro akce vytvoříte v modulu OOPP s typem "Pro konkrétní akce".')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? $tr('btn_save_changes', 'Uložit změny') : $tr('btn_create_visit', 'Vytvořit návštěvu'); ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-button saw-button-secondary">
                    <?php echo $tr('btn_cancel', 'Zrušit'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        </div>
        <!-- ================================================
             NESTED VISITOR FORM (skrytý, zobrazí se při přidání/editaci)
             ================================================ -->
        <div id="visitor-nested-form" class="saw-nested-form" style="display: none;">
            <div class="saw-nested-form-header">
                <button type="button" class="saw-btn-back" id="btn-visitor-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <?php echo esc_html($tr('btn_back', 'Zpět')); ?>
                </button>
                <h4 id="visitor-form-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?php echo esc_html($tr('title_add_visitor', 'Přidat návštěvníka')); ?>
                </h4>
            </div>
            
            <div class="saw-nested-form-body">
                <!-- Jméno a Příjmení - 2 sloupce -->
                <div class="saw-form-row-group">
                    <div class="saw-form-row">
                        <label for="visitor-first-name" class="saw-label">
                            <?php echo esc_html($tr('field_first_name', 'Jméno')); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="visitor-first-name" 
                               class="saw-input" 
                               placeholder="<?php echo esc_attr($tr('placeholder_first_name', 'Zadejte jméno')); ?>"
                               maxlength="100"
                               autocomplete="off">
                    </div>
                    
                    <div class="saw-form-row">
                        <label for="visitor-last-name" class="saw-label">
                            <?php echo esc_html($tr('field_last_name', 'Příjmení')); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="visitor-last-name" 
                               class="saw-input" 
                               placeholder="<?php echo esc_attr($tr('placeholder_last_name', 'Zadejte příjmení')); ?>"
                               maxlength="100"
                               autocomplete="off">
                    </div>
                </div>
                
                <!-- Email -->
                <div class="saw-form-row">
                    <label for="visitor-email" class="saw-label">
                        <?php echo esc_html($tr('field_email', 'Email')); ?>
                    </label>
                    <input type="email" 
                           id="visitor-email" 
                           class="saw-input" 
                           maxlength="255">
                </div>
                
                <!-- Telefon -->
                <div class="saw-form-row">
                    <label for="visitor-phone" class="saw-label">
                        <?php echo esc_html($tr('field_phone', 'Telefon')); ?>
                    </label>
                    <input type="tel" 
                           id="visitor-phone" 
                           class="saw-input" 
                           maxlength="50">
                </div>
                
                <!-- Pozice -->
                <div class="saw-form-row">
                    <label for="visitor-position" class="saw-label">
                        <?php echo esc_html($tr('field_position', 'Pozice / Funkce')); ?>
                    </label>
                    <input type="text" 
                           id="visitor-position" 
                           class="saw-input" 
                           maxlength="100">
                </div>
            </div>
            
            <div class="saw-nested-form-footer">
                <button type="button" class="saw-btn saw-btn-secondary" id="btn-visitor-cancel">
                    <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
                </button>
                <button type="button" class="saw-btn saw-btn-primary" id="btn-visitor-save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <?php echo esc_html($tr('btn_save_visitor', 'Uložit návštěvníka')); ?>
                </button>
            </div>
        </div>
        
    </form>
</div>

<!-- ================================================
     INLINE SCRIPTS - MUSÍ BÝT NA KONCI!
     Spustí se IHNED po vložení do DOM (důležité pro AJAX)
     ================================================ -->
<script>
jQuery(document).ready(function($) {
    // Handle has_company radio button change
    $('input[name="has_company"]').on('change', function() {
        var hasCompany = $(this).val();
        var $companyRow = $('.field-company-row');
        var $hiddenInput = $('#saw-select-visit_company_selection-value');
        
        if (hasCompany === '0') {
            // Fyzická osoba - skrýt pole a vynulovat hodnotu
            $companyRow.slideUp(200);
            if ($hiddenInput.length) {
                $hiddenInput.val('');
            }
        } else {
            // Právnická osoba - zobrazit pole
            $companyRow.slideDown(200);
            // Pokud už je nějaká hodnota vybraná, ponechat ji
        }
    });
    
    // FIX: Remove required attribute from hidden/inactive fields BEFORE submit
    // This must be done BEFORE HTML5 validation runs, so we do it on form submit attempt
    // (click on submit button) rather than actual submit event
    function syncRequiredAttributesBeforeSubmit($form) {
        $form.find('input[required], textarea[required]').each(function() {
            var $field = $(this);
            var $parentContent = $field.closest('.saw-language-content');
            var $detailsSection = $field.closest('details');
            
            // Check if field is in a closed <details> element (main issue)
            var isInClosedDetails = $detailsSection.length > 0 && !$detailsSection.prop('open');
            
            // Check if field is in inactive language tab
            var isInHiddenTab = $parentContent.length > 0 && $parentContent.css('display') === 'none';
            
            // Check if field is not visible or in display:none element
            var isNotVisible = !$field.is(':visible') || $field.closest('[style*="display: none"]').length > 0;
            
            // Remove required if field is not accessible
            if (isInClosedDetails || isInHiddenTab || isNotVisible) {
                $field.removeAttr('required');
            }
        });
    }
    
    // Sync required attributes when submit button is clicked (BEFORE form submit)
    $(document).on('click', '.saw-visit-form button[type="submit"], .saw-visit-form input[type="submit"]', function(e) {
        var $form = $(this).closest('form');
        if ($form.length) {
            syncRequiredAttributesBeforeSubmit($form);
        }
    });
    
    // Also sync on actual form submit as fallback
    $('.saw-visit-form').on('submit', function(e) {
        var $form = $(this);
        syncRequiredAttributesBeforeSubmit($form);
        
        // Get uploaded files from file upload component
        var uploadedActionDocs = [];
        var $uploadContainer = $form.find('#action-documents-upload').closest('.saw-file-upload-modern-container');
        if ($uploadContainer.length) {
            var uploadInstance = $uploadContainer.data('saw-file-upload-instance');
            if (uploadInstance && typeof uploadInstance.getUploadedFiles === 'function') {
                uploadedActionDocs = uploadInstance.getUploadedFiles();
            }
        }
        
        // Add uploaded files data to form
        if (uploadedActionDocs.length > 0) {
            var $hiddenInput = $('<input>', {
                type: 'hidden',
                name: 'action_documents_uploaded',
                value: JSON.stringify(uploadedActionDocs)
            });
            $form.append($hiddenInput);
        }
        
        // Get existing document IDs (from existing files that weren't removed)
        var existingDocIds = [];
        if (uploadInstance && typeof uploadInstance.files !== 'undefined') {
            uploadInstance.files.forEach(function(fileObj) {
                // Check if it's an existing file (not removed) and has a database ID
                if (fileObj.isExisting && fileObj.metadata && fileObj.metadata.id) {
                    existingDocIds.push(parseInt(fileObj.metadata.id));
                }
            });
        }
        
        if (existingDocIds.length > 0) {
            var $docIdsInput = $('<input>', {
                type: 'hidden',
                name: 'action_document_ids',
                value: JSON.stringify(existingDocIds)
            });
            $form.append($docIdsInput);
        }
        
        // FIX: Get company_id from hidden input
        // ⭐ FIX v3.8.0: Use new field name 'visit_company_selection'
        var hasCompany = $form.find('input[name="has_company"]:checked').val();
        var $hiddenInput = $('#saw-select-visit_company_selection-value');
        
        if ($hiddenInput.length) {
            var val = $hiddenInput.val();
            
            // Ujistit se, že hodnota je nastavená
            if (hasCompany === '1' && !val) {
                // Pokud je právnická osoba, ale hodnota není nastavená, zkusit najít v selectu
                var $select = $('#saw-select-visit_company_selection');
                if ($select.length) {
                    val = $select.val();
                    $hiddenInput.val(val);
                }
            }
            
            // Pokud je fyzická osoba, vynulovat
            if (hasCompany === '0') {
                $hiddenInput.val('');
            }
        }
        
        // Fix dates
        var dates = [];
        $form.find('input[name="schedule_dates[]"]').each(function() {
            var date = $(this).val().trim();
            if (date) dates.push(date);
        });
        
        if (dates.length > 0) {
            dates.sort();
            $form.find('input[name="planned_date_from"]').remove();
            $form.find('input[name="planned_date_to"]').remove();
            $form.append('<input type="hidden" name="planned_date_from" value="' + dates[0] + '">');
            $form.append('<input type="hidden" name="planned_date_to" value="' + dates[dates.length - 1] + '">');
        }
    });
});
</script>

<!-- ================================================
     KRITICKÉ: Inline script pro inicializaci při AJAX/navigaci
     Tento script se spustí IHNED po vložení HTML do DOM
     ================================================ -->
<script>
(function($) {
    console.log('[Visits Form] Inline init script executed at:', new Date().toISOString());
    
    // Prepare data for SAWVisitorsManager
    window.sawVisitorsFormData = {
        mode: '<?php echo esc_js($visitors_mode); ?>',
        visitId: <?php echo $is_edit ? intval($item['id']) : 'null'; ?>,
        existingVisitors: <?php echo json_encode($existing_visitors); ?>,
        translations: <?php echo json_encode($visitor_translations); ?>
    };
    
    console.log('[Visits Form] Data prepared:', {
        mode: window.sawVisitorsFormData.mode,
        visitId: window.sawVisitorsFormData.visitId,
        visitorsCount: window.sawVisitorsFormData.existingVisitors.length
    });
    
    // ⭐ FIX v3.6.0: Polling approach - wait for SAWVisitorsManager to be available
    // This handles timing issues when inline script runs before saw-visits.js
    function tryInitVisitorsManager(attempts) {
        attempts = attempts || 0;
        
        console.log('[Visits Form] Attempt', attempts + 1, '- checking for SAWVisitorsManager...');
        
        if (typeof window.SAWVisitorsManager !== 'undefined') {
            console.log('[Visits Form] ✅ SAWVisitorsManager found! Calling init()');
            window.SAWVisitorsManager.init();
            return;
        }
        
        if (attempts < 50) { // Max 5 seconds (50 * 100ms)
            setTimeout(function() {
                tryInitVisitorsManager(attempts + 1);
            }, 100);
        } else {
            console.error('[Visits Form] ❌ SAWVisitorsManager not available after 5 seconds');
        }
    }
    
    // Start polling immediately
    tryInitVisitorsManager(0);
    
    // Also trigger events for other components that might need them
    $(document).trigger('saw:page-loaded');
    
    // ========================================
    // LANGUAGE TABS FOR ACTION INFO
    // ========================================
    
    var $actionTabsContainer = $('.saw-form-section-action-info .saw-language-tabs');
    var $actionTabs = $('.saw-form-section-action-info .saw-language-tab');
    var $actionPrevBtn = $('.saw-form-section-action-info .saw-language-tab-nav-prev');
    var $actionNextBtn = $('.saw-form-section-action-info .saw-language-tab-nav-next');
    
    if ($actionTabsContainer.length) {
        function updateActionNavButtons() {
            var scrollLeft = $actionTabsContainer.scrollLeft();
            var scrollWidth = $actionTabsContainer[0].scrollWidth;
            var clientWidth = $actionTabsContainer[0].clientWidth;
            
            $actionPrevBtn.prop('disabled', scrollLeft === 0);
            $actionNextBtn.prop('disabled', scrollLeft >= scrollWidth - clientWidth - 1);
        }
        
        // Update buttons on scroll
        $actionTabsContainer.on('scroll', updateActionNavButtons);
        
        // Update buttons on resize
        $(window).on('resize', updateActionNavButtons);
        
        // Initial update
        updateActionNavButtons();
        
        // Navigation buttons
        $actionPrevBtn.on('click', function() {
            if (!$(this).prop('disabled')) {
                $actionTabsContainer.animate({
                    scrollLeft: $actionTabsContainer.scrollLeft() - 200
                }, 300);
            }
        });
        
        $actionNextBtn.on('click', function() {
            if (!$(this).prop('disabled')) {
                $actionTabsContainer.animate({
                    scrollLeft: $actionTabsContainer.scrollLeft() + 200
                }, 300);
            }
        });
        
        // Tab click handler
        $actionTabs.on('click', function() {
            var $tab = $(this);
            var targetTab = $tab.data('tab');
            
            // Remove active class from all tabs
            $actionTabs.removeClass('active');
            
            // Add active class to clicked tab
            $tab.addClass('active');
            
            // Hide all content and remove required from all inputs
            $('.saw-form-section-action-info .saw-language-content').each(function() {
                $(this).hide();
                $(this).find('input[type="text"], textarea').removeAttr('required');
                $(this).find('label').removeClass('saw-required');
                $(this).find('.saw-required-marker').remove();
            });
            
            // Show target content and add required to first input
            var $targetContent = $('.saw-form-section-action-info .saw-language-content[data-tab-content="' + targetTab + '"]');
            $targetContent.show();
            var $firstInput = $targetContent.find('input[name*="[name]"]').first();
            if ($firstInput.length) {
                $firstInput.attr('required', 'required');
                $firstInput.closest('.saw-form-group').find('label').addClass('saw-required');
                if (!$firstInput.closest('.saw-form-group').find('.saw-required-marker').length) {
                    $firstInput.closest('.saw-form-group').find('label').append('<span class="saw-required-marker">*</span>');
                }
            }
            
            // Scroll clicked tab into view
            var tabOffset = $tab.position().left + $actionTabsContainer.scrollLeft();
            var tabWidth = $tab.outerWidth();
            var containerWidth = $actionTabsContainer.outerWidth();
            var scrollLeft = $actionTabsContainer.scrollLeft();
            
            if (tabOffset < scrollLeft) {
                // Tab is to the left, scroll to show it
                $actionTabsContainer.animate({
                    scrollLeft: tabOffset - 20
                }, 300);
            } else if (tabOffset + tabWidth > scrollLeft + containerWidth) {
                // Tab is to the right, scroll to show it
                $actionTabsContainer.animate({
                    scrollLeft: tabOffset - containerWidth + tabWidth + 20
                }, 300);
            }
        });
    }
    
    // ========================================
    // OOPP SELECTOR FOR ACTION INFO
    // ========================================
    $(document).on('click', '.saw-form-section-action-info .saw-add-oopp', function(e) {
        e.preventDefault();
        var $item = $(this).closest('.saw-oopp-item');
        var ooppId = $item.data('id');
        var ooppName = $item.find('.saw-oopp-name').text();
        var ooppGroup = $item.find('.saw-oopp-group').text() || '';
        
        // Přesunout do vybraných
        var $selectedList = $('#selected-action-oopp');
        
        // Odstranit prázdný stav
        $selectedList.find('div:contains("Zatím není vybráno")').remove();
        
        // Vytvořit nový item
        var $newItem = $('<div>')
            .addClass('saw-oopp-item selected')
            .attr('data-id', ooppId)
            .html(
                '<div style="flex: 1; min-width: 0;">' +
                '<div class="saw-oopp-name" style="font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 4px;">' + ooppName + '</div>' +
                '<label class="saw-checkbox-inline" style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; cursor: pointer;">' +
                '<input type="checkbox" name="action_oopp_required[' + ooppId + ']" value="1" checked style="margin: 0; cursor: pointer;">' +
                '<span>Povinné</span>' +
                '</label>' +
                '</div>' +
                '<button type="button" class="saw-btn-icon saw-remove-oopp" title="Odebrat" style="background: #ef4444; color: #fff; border: none; border-radius: 4px; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; transition: all 0.2s ease;">✕</button>' +
                '<input type="hidden" name="action_oopp_ids[]" value="' + ooppId + '">'
            )
            .css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px',
                'padding': '10px 12px',
                'background': '#fff',
                'border': '1px solid #f59e0b',
                'border-radius': '6px',
                'transition': 'all 0.2s ease'
            });
        
        $selectedList.append($newItem);
        
        // Odstranit z dostupných
        $item.remove();
    });
    
    $(document).on('click', '.saw-form-section-action-info .saw-remove-oopp', function(e) {
        e.preventDefault();
        var $item = $(this).closest('.saw-oopp-item');
        var ooppId = $item.data('id');
        var ooppName = $item.find('.saw-oopp-name').text();
        var ooppGroup = $item.find('.saw-oopp-group').text() || '';
        
        // Přesunout zpět do dostupných
        var $availableList = $('.saw-oopp-available .saw-oopp-list');
        
        // Vytvořit nový item
        var $newItem = $('<div>')
            .addClass('saw-oopp-item')
            .attr('data-id', ooppId)
            .html(
                '<div style="flex: 1; min-width: 0;">' +
                '<div class="saw-oopp-name" style="font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 2px;">' + ooppName + '</div>' +
                (ooppGroup ? '<div class="saw-oopp-group" style="font-size: 12px; color: #6b7280;">' + ooppGroup + '</div>' : '') +
                '</div>' +
                '<button type="button" class="saw-btn-icon saw-add-oopp" title="Přidat" style="background: #10b981; color: #fff; border: none; border-radius: 4px; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; flex-shrink: 0; transition: all 0.2s ease;">+</button>'
            )
            .css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px',
                'padding': '10px 12px',
                'background': '#fff',
                'border': '1px solid #e5e7eb',
                'border-radius': '6px',
                'transition': 'all 0.2s ease'
            });
        
        $availableList.append($newItem);
        
        // Odstranit z vybraných
        $item.remove();
        
        // Pokud není žádné vybrané, zobrazit prázdný stav
        if ($('#selected-action-oopp .saw-oopp-item').length === 0) {
            $('#selected-action-oopp').html('<div style="padding: 20px; text-align: center; color: #92400e; font-size: 13px; font-style: italic;">Zatím není vybráno žádné OOPP</div>');
        }
    });
    
    // ========================================
    // INITIALIZE ACTIVE LANGUAGE ON SECTION OPEN
    // ========================================
    function initializeActiveLanguage() {
        var $section = $('.saw-form-section-action-info');
        if ($section.length && ($section.prop('open') || $section.attr('open'))) {
            var $activeTab = $section.find('.saw-language-tab.active');
            if ($activeTab.length) {
                var targetTab = $activeTab.data('tab');
                var $targetContent = $section.find('.saw-language-content[data-tab-content="' + targetTab + '"]');
                $targetContent.show();
                
                // Zajistit, že required atributy jsou správně nastavené
                var $firstInput = $targetContent.find('input[name*="[name]"]').first();
                if ($firstInput.length) {
                    $firstInput.attr('required', 'required');
                    $firstInput.closest('.saw-form-group').find('label').addClass('saw-required');
                    if (!$firstInput.closest('.saw-form-group').find('.saw-required-marker').length) {
                        $firstInput.closest('.saw-form-group').find('label').append('<span class="saw-required-marker">*</span>');
                    }
                }
            }
        }
    }
    
    // Inicializace při načtení stránky
    $(document).ready(function() {
        // Remove required from fields in closed details sections on page load
        syncRequiredAttributesForDetails();
        
        initializeActiveLanguage();
        
        // Inicializace TinyMCE editorů pro action info
        if (typeof wp !== 'undefined' && wp.editor && wp.editor.initialize) {
            $('.saw-form-section-action-info textarea.wp-editor-area').each(function() {
                var editorId = $(this).attr('id');
                if (editorId && !tinyMCE.get(editorId)) {
                    // Počkat, až bude DOM připraven
                    setTimeout(function() {
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                            // Editor už existuje
                            return;
                        }
                        // Zkusit znovu inicializovat
                        if (wp.editor && wp.editor.initialize) {
                            try {
                                wp.editor.initialize(editorId, {
                                    tinymce: {
                                        toolbar1: 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                        toolbar2: 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                        block_formats: 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                    },
                                    media_buttons: false,
                                    quicktags: true,
                                });
                            } catch(e) {
                                console.error('[Visits Form] Error initializing TinyMCE:', e);
                            }
                        }
                    }, 500);
                }
            });
        }
    });
    
    // Helper function to sync required attributes with details open state
    function syncRequiredAttributesForDetails() {
        $('details.saw-form-section').each(function() {
            var $details = $(this);
            var isOpen = $details.prop('open');
            
            if (!isOpen) {
                // Remove required from all fields in closed details
                $details.find('input[required], textarea[required]').removeAttr('required');
            }
        });
    }
    
    // Inicializace při otevření sekce
    $('.saw-form-section-action-info').on('toggle', function() {
        var $section = $(this);
        var isOpen = $section.prop('open');
        
        // Sync required attributes when section is toggled
        if (!isOpen) {
            // Remove required from fields in closed section
            $section.find('input[required], textarea[required]').removeAttr('required');
        }
        
        if (isOpen) {
            setTimeout(function() {
                initializeActiveLanguage();
                
                // Znovu inicializovat TinyMCE po otevření sekce
                if (typeof wp !== 'undefined' && wp.editor && wp.editor.initialize) {
                    $('.saw-form-section-action-info textarea.wp-editor-area').each(function() {
                        var editorId = $(this).attr('id');
                        if (editorId && !tinyMCE.get(editorId)) {
                            setTimeout(function() {
                                if (wp.editor && wp.editor.initialize) {
                                    try {
                                        wp.editor.initialize(editorId, {
                                            tinymce: {
                                                toolbar1: 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                                toolbar2: 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                                block_formats: 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                            },
                                            media_buttons: false,
                                            quicktags: true,
                                        });
                                    } catch(e) {
                                        console.error('[Visits Form] Error initializing TinyMCE:', e);
                                    }
                                }
                            }, 300);
                        }
                    });
                }
            }, 100);
        }
    });
    
    // Sync required attributes for ALL details sections on toggle
    $(document).on('toggle', 'details.saw-form-section', function() {
        var $details = $(this);
        var isOpen = $details.prop('open');
        
        if (!isOpen) {
            // Remove required from fields in closed details
            $details.find('input[required], textarea[required]').removeAttr('required');
        }
    });
    
})(jQuery);
</script>