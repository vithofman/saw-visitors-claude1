<?php
/**
 * Departments Form Template
 * 
 * Form for creating new departments or editing existing ones.
 * Used by both create() and edit() controller methods.
 * 
 * Available Variables:
 * @var array $item Department data (empty array for create, populated for edit)
 * @var bool $is_edit True if editing existing department, false if creating new
 * @var int $customer_id Current customer ID from context
 * @var array $branches List of available branches for the current customer
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine if this is edit mode
$is_edit = !empty($item);
$item = $item ?? array();

// Get current customer context
$customer_id = SAW_Context::get_customer_id();

// Fetch branches for dropdown (only active branches)
global $wpdb;
$branches = array();
if ($customer_id) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
}
?>

<!-- Page Header -->
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

<!-- Form Container -->
<div class="saw-form-container">
    <form method="post" class="saw-department-form">
        
        <!-- CSRF Protection -->
        <?php wp_nonce_field('saw_departments_form', 'saw_nonce'); ?>
        
        <!-- Hidden Fields -->
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <!-- ================================================ -->
        <!-- SECTION: Basic Information -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch + Department Number Row -->
                <div class="saw-form-row">
                    
                    <!-- Branch Selection -->
                    <div class="saw-form-group saw-col-6">
                        <label for="branch_id" class="saw-label saw-required">
                            Pobočka
                        </label>
                        <select 
                            id="branch_id" 
                            name="branch_id" 
                            class="saw-select"
                            required
                        >
                            <option value="">Vyberte pobočku</option>
                            <?php foreach ($branches as $branch): ?>
                                <option 
                                    value="<?php echo esc_attr($branch['id']); ?>"
                                    <?php selected(!empty($item['branch_id']) ? $item['branch_id'] : '', $branch['id']); ?>
                                >
                                    <?php echo esc_html($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="saw-help-text">
                            Pobočka ke které oddělení patří
                        </span>
                    </div>
                    
                    <!-- Department Number (Optional) -->
                    <div class="saw-form-group saw-col-6">
                        <label for="department_number" class="saw-label">
                            Číslo oddělení
                        </label>
                        <input 
                            type="text" 
                            id="department_number" 
                            name="department_number" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['department_number'] ?? ''); ?>"
                            placeholder="ODD-001"
                        >
                        <span class="saw-help-text">
                            Interní číslo oddělení (volitelné)
                        </span>
                    </div>
                </div>
                
                <!-- Department Name Row -->
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="name" class="saw-label saw-required">
                            Název oddělení
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                            required
                            placeholder="Výroba"
                        >
                        <span class="saw-help-text">
                            Název oddělení
                        </span>
                    </div>
                </div>
                
                <!-- Description Row -->
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="description" class="saw-label">
                            Popis
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="saw-textarea"
                            rows="4"
                            placeholder="Volitelný popis oddělení..."
                        ><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                        <span class="saw-help-text">
                            Volitelný popis oddělení
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- SECTION: Training -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <strong>Školení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Training Version Row -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="training_version" class="saw-label">
                            Verze školení
                        </label>
                        <input 
                            type="number" 
                            id="training_version" 
                            name="training_version" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['training_version'] ?? '1'); ?>"
                            min="1"
                            max="999"
                            placeholder="1"
                        >
                        <span class="saw-help-text">
                            Aktuální verze školení pro oddělení
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- SECTION: Availability Settings -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení dostupnosti</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Active Status Checkbox -->
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                name="is_active" 
                                value="1"
                                <?php checked(!empty($item['is_active']) ? $item['is_active'] : 1, 1); ?>
                            >
                            <span class="saw-checkbox-text">
                                <strong>Aktivní oddělení</strong>
                                <small>Pouze aktivní oddělení jsou dostupná pro výběr</small>
                            </span>
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- FORM ACTIONS -->
        <!-- ================================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit oddělení'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
        
    </form>
</div>