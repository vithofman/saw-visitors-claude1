<?php
/**
 * Visits Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.2.0 - Multi-language support
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

$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    $selected_branch_id = $item['branch_id'];
} elseif (!$is_edit && $context_branch_id) {
    $selected_branch_id = $context_branch_id;
}

// Determine if visit has company (legal person) or is physical person
$has_company = 1; // Default: legal person
if ($is_edit && isset($item['company_id'])) {
    $has_company = !empty($item['company_id']) ? 1 : 0;
}

$existing_host_ids = array();
if ($is_edit && !empty($item['id'])) {
    $existing_host_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM %i WHERE visit_id = %d",
        $wpdb->prefix . 'saw_visit_hosts',
        $item['id']
    ));
}

$form_action = $is_edit 
    ? home_url('/admin/visits/' . $item['id'] . '/edit')
    : home_url('/admin/visits/create');
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? $tr('form_title_edit', 'Upravit návštěvu') : $tr('form_title_create', 'Nová návštěva'); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo $tr('btn_back_to_list', 'Zpět na seznam'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-visits">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-visit-form">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_visits' : 'saw_create_visits';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo $tr('form_section_basic', 'Základní informace'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label saw-required"><?php echo $tr('form_branch', 'Pobočka'); ?></label>
                        <select name="branch_id" id="branch_id" class="saw-input" required>
                            <option value="">-- <?php echo $tr('form_select_branch', 'Vyberte pobočku'); ?> --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option value="<?php echo esc_attr($branch_id); ?>" <?php selected($selected_branch_id, $branch_id); ?>>
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- ⭐ NEW: Physical vs Legal Person Radio -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required"><?php echo $tr('form_visitor_type', 'Typ návštěvníka'); ?></label>
                        <div class="saw-radio-group" style="display: flex; gap: 24px; margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" 
                                       name="has_company" 
                                       value="1" 
                                       <?php checked($has_company, 1); ?>
                                       style="margin: 0;">
                                <span style="font-weight: 500;"><?php echo $tr('form_legal_person', 'Právnická osoba (firma, instituce)'); ?></span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" 
                                       name="has_company" 
                                       value="0" 
                                       <?php checked($has_company, 0); ?>
                                       style="margin: 0;">
                                <span style="font-weight: 500;"><?php echo $tr('form_physical_person', 'Fyzická osoba'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- ⭐ Conditional Company Field -->
                <div class="saw-form-row field-company-row" style="<?php echo $has_company ? '' : 'display: none;'; ?>">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        $company_select = new SAW_Component_Select_Create('company_id', array(
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
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                        <button type="button" class="saw-add-schedule-day-inline" title="<?php echo esc_attr($tr('btn_add_day', 'Přidat další den')); ?>">
                                            <span class="dashicons dashicons-plus-alt"></span>
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
                
                <!-- Invitation Email -->
                <div class="saw-form-row">
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
                            <span id="host-counter" style="display: inline-block; margin-left: 12px; padding: 4px 12px; background: #0073aa; color: white; border-radius: 4px; font-size: 13px; font-weight: 600;">
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
                            <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">
                                <?php echo $tr('form_select_branch_first', 'Nejprve vyberte pobočku výše'); ?>
                            </p>
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
        
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // ✅ Automaticky vypočítat planned_date_from a planned_date_to z schedule_dates[]
    // a přidat je jako hidden inputy před odesláním formuláře
    $('.saw-visit-form').on('submit', function(e) {
        var $form = $(this);
        var dates = [];
        
        // Získat všechny hodnoty z schedule_dates[]
        $form.find('input[name="schedule_dates[]"]').each(function() {
            var date = $(this).val().trim();
            if (date) {
                dates.push(date);
            }
        });
        
        // Pokud máme data, vypočítat min a max
        if (dates.length > 0) {
            dates.sort();
            var planned_date_from = dates[0];
            var planned_date_to = dates[dates.length - 1];
            
            // Odstranit existující hidden inputy (pokud existují)
            $form.find('input[name="planned_date_from"]').remove();
            $form.find('input[name="planned_date_to"]').remove();
            
            // Přidat nové hidden inputy
            $form.append('<input type="hidden" name="planned_date_from" value="' + planned_date_from + '">');
            $form.append('<input type="hidden" name="planned_date_to" value="' + planned_date_to + '">');
            
            console.log('[Visits Form] Calculated planned dates:', {
                from: planned_date_from,
                to: planned_date_to,
                all_dates: dates
            });
        }
    });
});
</script>