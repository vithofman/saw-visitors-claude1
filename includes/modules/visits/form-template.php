<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Component_Select_Create')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/select-create/class-saw-component-select-create.php';
}

$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_edit = !empty($item);
$item = $item ?? array();

$customer_id = SAW_Context::get_customer_id();
$context_branch_id = SAW_Context::get_branch_id();
$branches = $branches ?? array();
$companies = $companies ?? array();

if (empty($branches) && $customer_id) {
    global $wpdb;
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches', $customer_id
    ), ARRAY_A);
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

if (empty($companies) && $customer_id) {
    $sql = $wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d",
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
            <?php echo $is_edit ? 'Upravit návštěvu' : 'Nová návštěva'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
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
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label saw-required">Pobočka</label>
                        <select name="branch_id" id="branch_id" class="saw-input" required>
                            <option value="">-- Vyberte pobočku --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option value="<?php echo esc_attr($branch_id); ?>" <?php selected($selected_branch_id, $branch_id); ?>>
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        $company_select = new SAW_Component_Select_Create('company_id', array(
                            'label' => 'Firma',
                            'options' => $companies,
                            'selected' => $item['company_id'] ?? '',
                            'required' => true,
                            'placeholder' => '-- Vyberte firmu --',
                            'inline_create' => array(
                                'enabled' => true,
                                'target_module' => 'companies',
                                'button_text' => '+ Nová firma',
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
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="visit_type" class="saw-label saw-required">Typ návštěvy</label>
                        <select name="visit_type" id="visit_type" class="saw-input" required>
                            <option value="planned" <?php selected($item['visit_type'] ?? 'planned', 'planned'); ?>>Plánovaná</option>
                            <option value="walk_in" <?php selected($item['visit_type'] ?? '', 'walk_in'); ?>>Walk-in</option>
                        </select>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="status" class="saw-label saw-required">Stav</label>
                        <select name="status" id="status" class="saw-input" required>
                            <option value="draft" <?php selected($item['status'] ?? '', 'draft'); ?>>Koncept</option>
                            <option value="pending" <?php selected($item['status'] ?? 'pending', 'pending'); ?>>Čekající</option>
                            <option value="confirmed" <?php selected($item['status'] ?? '', 'confirmed'); ?>>Potvrzená</option>
                            <option value="in_progress" <?php selected($item['status'] ?? '', 'in_progress'); ?>>Probíhající</option>
                            <option value="completed" <?php selected($item['status'] ?? '', 'completed'); ?>>Dokončená</option>
                            <option value="cancelled" <?php selected($item['status'] ?? '', 'cancelled'); ?>>Zrušená</option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">Dny návštěvy</label>
                        
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
                                $schedules = array(array('date' => '', 'time_from' => '', 'time_to' => '', 'notes' => ''));
                            }
                            
                            foreach ($schedules as $index => $schedule): ?>
                                <div class="saw-schedule-row" data-index="<?php echo $index; ?>">
                                    <div class="saw-schedule-row-fields">
                                        <div class="saw-schedule-field-group">
                                            <div class="saw-schedule-field saw-schedule-date">
                                                <label>Datum</label>
                                                <input type="date" 
                                                       name="schedule_dates[]" 
                                                       value="<?php echo esc_attr($schedule['date'] ?? ''); ?>" 
                                                       class="saw-input saw-schedule-date-input"
                                                       required>
                                            </div>
                                            
                                            <div class="saw-schedule-field saw-schedule-time">
                                                <label>Čas od</label>
                                                <input type="time" 
                                                       name="schedule_times_from[]" 
                                                       value="<?php echo esc_attr($schedule['time_from'] ?? ''); ?>" 
                                                       class="saw-input saw-schedule-time-input">
                                            </div>
                                            
                                            <div class="saw-schedule-field saw-schedule-time">
                                                <label>Čas do</label>
                                                <input type="time" 
                                                       name="schedule_times_to[]" 
                                                       value="<?php echo esc_attr($schedule['time_to'] ?? ''); ?>" 
                                                       class="saw-input saw-schedule-time-input">
                                            </div>
                                        </div>
                                        
                                        <div class="saw-schedule-field saw-schedule-notes-full">
                                            <label>Poznámka (volitelné)</label>
                                            <input type="text" 
                                                   name="schedule_notes[]" 
                                                   value="<?php echo esc_attr($schedule['notes'] ?? ''); ?>" 
                                                   class="saw-input saw-schedule-notes-input"
                                                   placeholder="Poznámka k danému dni">
                                        </div>
                                    </div>
                                    
                                    <div class="saw-schedule-row-actions">
                                        <button type="button" 
                                                class="saw-remove-schedule-day" 
                                                title="Odstranit den"
                                                <?php echo count($schedules) === 1 ? 'disabled' : ''; ?>>
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                        <button type="button" class="saw-add-schedule-day-inline" title="Přidat další den">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <p class="saw-field-hint">
                            Přidejte jeden nebo více dnů, kdy návštěva proběhne. Každý den může mít různý čas.
                        </p>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="invitation_email" class="saw-label">Email pro pozvánku</label>
                        <input type="email" name="invitation_email" id="invitation_email" class="saw-input" value="<?php echo esc_attr($item['invitation_email'] ?? ''); ?>" placeholder="email@example.com">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="purpose" class="saw-label">Účel návštěvy</label>
                        <textarea name="purpose" id="purpose" class="saw-input" rows="3" placeholder="Stručný popis účelu návštěvy..."><?php echo esc_textarea($item['purpose'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="saw-form-row field-hosts-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required">
                            Koho navštěvují
                            <span id="host-counter" style="display: inline-block; margin-left: 12px; padding: 4px 12px; background: #0073aa; color: white; border-radius: 4px; font-size: 13px; font-weight: 600;">
                                <span id="host-selected">0</span> / <span id="host-total">0</span>
                            </span>
                        </label>
                        
                        <div class="saw-host-controls" style="display: none; margin-bottom: 12px; gap: 12px; align-items: center;">
                            <input type="text" id="host-search" class="saw-input" placeholder="🔍 Hledat uživatele..." style="flex: 1; max-width: 300px;">
                            <label style="display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer; user-select: none;">
                                <input type="checkbox" id="select-all-host" style="margin: 0; cursor: pointer;">
                                <span style="font-weight: 600; color: #2c3338;">Vybrat vše</span>
                            </label>
                        </div>
                        
                        <div id="hosts-list" style="border: 2px solid #dcdcde; border-radius: 6px; max-height: 320px; overflow-y: auto; background: #fff;">
                            <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">
                                Nejprve vyberte pobočku výše
                            </p>
                        </div>
                    </div>
                </div>
                
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit návštěvu'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>

<style>
<?php readfile(SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/visits.css'); ?>
</style>

<script>
window.sawVisitExistingHosts = <?php echo json_encode(array_map('intval', $existing_host_ids)); ?>;
<?php readfile(SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/visits.js'); ?>
</script>