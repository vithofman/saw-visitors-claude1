<?php
/**
 * Departments Form Template - SIMPLIFIED
 * 
 * Form for creating/editing departments with branches form styling.
 * Auto-prefills branch from branch switcher context.
 * Training version removed - not needed for departments.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     3.1.0 - SIMPLIFIED: Removed training_version field
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if we're in sidebar mode
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Determine if this is edit mode
$is_edit = !empty($item);
$item = $item ?? array();

// Get current customer context
$customer_id = SAW_Context::get_customer_id();

// ✅ KRITICKY DŮLEŽITÉ: Získat branch_id z branch switcheru
$context_branch_id = SAW_Context::get_branch_id();

// Get branches from parent scope (passed from list-template or controller)
$branches = $branches ?? array();

// If branches not provided, fetch them
if (empty($branches) && $customer_id) {
    global $wpdb;
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
    
    $branches = array();
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// ✅ KLÍČOVÁ LOGIKA: Předvyplnění pobočky
// Priorita: 1) Existující hodnota (edit mode) -> 2) Branch switcher -> 3) Prázdné
$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    // Edit mode: použij existující hodnotu
    $selected_branch_id = $item['branch_id'];
} elseif (!$is_edit && $context_branch_id) {
    // Create mode + branch switcher má hodnotu: použij ji
    $selected_branch_id = $context_branch_id;
}

// Form action URL
$form_action = $is_edit 
    ? home_url('/admin/departments/' . $item['id'] . '/edit')
    : home_url('/admin/departments/create');
?>

<?php if (!$in_sidebar): ?>
<!-- Page Header (only when NOT in sidebar) -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit oddělení' : 'Nové oddělení'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Form Container (stejný wrapper jako branches) -->
<div class="saw-form-container saw-module-departments">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-department-form">
        <?php 
        // ✅ Correct nonce field matching Base Controller expectations
        $nonce_action = $is_edit ? 'saw_edit_departments' : 'saw_create_departments';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- Hidden Fields -->
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <!-- ================================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch Selection + Department Number -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="branch_id" class="saw-label saw-required">
                            Pobočka
                        </label>
                        <select 
                            name="branch_id" 
                            id="branch_id" 
                            class="saw-input" 
                            required
                            <?php echo $is_edit ? 'disabled' : ''; ?>
                        >
                            <option value="">-- Vyberte pobočku --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option 
                                    value="<?php echo esc_attr($branch_id); ?>"
                                    <?php selected($selected_branch_id, $branch_id); ?>
                                >
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($is_edit): ?>
                            <!-- Hidden field to submit branch_id when disabled -->
                            <input type="hidden" name="branch_id" value="<?php echo esc_attr($item['branch_id'] ?? ''); ?>">
                        <?php endif; ?>
                        
                        <?php if (!$is_edit && !$context_branch_id): ?>
                            <p class="saw-help-text" style="color: #d63638; margin-top: 4px;">
                                ⚠️ Není vybrána žádná pobočka v branch switcheru. Vyberte pobočku manuálně.
                            </p>
                        <?php elseif (!$is_edit && $context_branch_id): ?>
                            <p class="saw-help-text" style="color: #00a32a; margin-top: 4px;">
                                ✅ Pobočka předvyplněna z branch switcheru
                            </p>
                        <?php else: ?>
                            <p class="saw-help-text">Pobočka ke které oddělení patří</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="department_number" class="saw-label">
                            Číslo oddělení
                        </label>
                        <input 
                            type="text" 
                            name="department_number" 
                            id="department_number" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['department_number'] ?? ''); ?>"
                            placeholder="např. 001"
                        >
                    </div>
                </div>
                
                <!-- Department Name -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="name" class="saw-label saw-required">
                            Název oddělení
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                            placeholder="např. Výroba, Sklad, IT, Administrativa"
                            required
                        >
                    </div>
                </div>
                
                <!-- Active Status -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                id="is_active" 
                                value="1"
                                <?php checked(isset($item['is_active']) ? $item['is_active'] : 1, 1); ?>
                            >
                            <span>Aktivní oddělení</span>
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- POPIS A POZNÁMKY -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit-page"></span>
                <strong>Popis a poznámky</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label for="description" class="saw-label">
                        Popis
                    </label>
                    <textarea 
                        name="description" 
                        id="description" 
                        class="saw-textarea"
                        rows="4"
                        placeholder="Volitelný popis oddělení, jeho funkce a zodpovědnosti..."
                    ><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                    <p class="saw-help-text">Interní poznámky viditelné pouze administrátorům</p>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- FORM ACTIONS -->
        <!-- ================================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit oddělení'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>