<?php
/**
 * Users Form Template - PRODUCTION v5.0.2
 * 
 * @package SAW_Visitors
 * @version 5.0.2 - FIXED: Department checkboxes now properly load selected values on edit
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item['id']);
$page_title = $is_edit ? 'Upravit uživatele' : 'Nový uživatel';

global $wpdb;

$customer_id = SAW_Context::get_customer_id();

// Načteme pobočky pro aktuálního zákazníka
$branches = [];
if ($customer_id > 0) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code, city 
         FROM %i
         WHERE customer_id = %d AND is_active = 1 
         ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
}

// Super admin může vybírat zákazníky
$customers = [];
if (current_user_can('manage_options')) {
    $customers = $wpdb->get_results(
        $wpdb->prepare("SELECT id, name FROM %i ORDER BY name ASC", $wpdb->prefix . 'saw_customers'),
        ARRAY_A
    );
}

// Předvyplnění branch_id z kontextu při ADD
$default_branch_id = null;
if (!$is_edit) {
    $context_branch_id = SAW_Context::get_branch_id();
    if ($context_branch_id) {
        $default_branch_id = $context_branch_id;
    }
}


?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo esc_html($page_title); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/users/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" action="" class="saw-user-form">
        <?php 
$nonce_action = $is_edit ? 'saw_edit_users' : 'saw_create_users';
wp_nonce_field($nonce_action, '_wpnonce', false);
?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>
        
        <!-- Role -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-users"></span>
                <strong>Základní údaje</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="role" class="saw-label">
                            Role <span class="saw-required">*</span>
                        </label>
                        <select name="role" id="role" class="saw-select" required>
                            <option value="">-- Vyberte --</option>
                            <?php if (current_user_can('manage_options')): ?>
                            <option value="super_admin" <?php selected($item['role'] ?? '', 'super_admin'); ?>>
                                Super Admin (celý systém)
                            </option>
                            <?php endif; ?>
                            <option value="admin" <?php selected($item['role'] ?? '', 'admin'); ?>>
                                Admin (všechny pobočky)
                            </option>
                            <option value="super_manager" <?php selected($item['role'] ?? '', 'super_manager'); ?>>
                                Super Manager (jedna pobočka)
                            </option>
                            <option value="manager" <?php selected($item['role'] ?? '', 'manager'); ?>>
                                Manager (oddělení)
                            </option>
                            <option value="terminal" <?php selected($item['role'] ?? '', 'terminal'); ?>>
                                Terminál
                            </option>
                        </select>
                        <span class="saw-help-text">Určuje úroveň přístupu</span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label">
                            Email <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                            required
                            <?php echo $is_edit ? 'readonly' : ''; ?>
                        >
                        <span class="saw-help-text">Slouží jako přihlašovací jméno</span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="first_name" class="saw-label">
                            Jméno <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="first_name" 
                            id="first_name" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="last_name" class="saw-label">
                            Příjmení <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="last_name" 
                            id="last_name" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="position" class="saw-label">
                            Funkce
                        </label>
                        <input 
                            type="text" 
                            name="position" 
                            id="position" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['position'] ?? ''); ?>"
                            placeholder="např. Vedoucí výroby, BOZP technik"
                        >
                        <span class="saw-help-text">Pracovní pozice uživatele</span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1"
                                <?php checked($item['is_active'] ?? 1, 1); ?>
                            >
                            <span>Aktivní uživatel</span>
                        </label>
                        <span class="saw-help-text">Neaktivní uživatel se nemůže přihlásit</span>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- Zákazník (pouze pro super admins) -->
        <?php if (current_user_can('manage_options')): ?>
        <details class="saw-form-section field-customer" style="display:none;">
            <summary>
                <span class="dashicons dashicons-building"></span>
                <strong>Zákazník</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="customer-select" class="saw-label">
                            Zákazník
                        </label>
                        <select name="customer_id" id="customer-select" class="saw-select">
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo esc_attr($customer['id']); ?>"
                                        <?php selected($customer['id'], $customer_id); ?>>
                                    <?php echo esc_html($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </details>
        <?php else: ?>
            <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        <?php endif; ?>
        
        <!-- Pobočka a oddělení v JEDNÉ sekci -->
        <details class="saw-form-section field-branch-departments" style="display:none;" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Pobočka a oddělení</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label">
                            Pobočka <span class="saw-required field-branch-required" style="display:none;">*</span>
                        </label>
                        <select name="branch_id" id="branch_id" class="saw-select">
                            <option value="">-- Vyberte --</option>
                            <?php 
                            $selected_branch_id = $item['branch_id'] ?? $default_branch_id ?? '';
                            
                            foreach ($branches as $branch): 
                                $code = !empty($branch['code']) ? $branch['code'] : '';
                                $city = !empty($branch['city']) ? $branch['city'] : '';
                                
                                $label = $branch['name'];
                                if ($code || $city) {
                                    $parts = array_filter([$code, $city]);
                                    $label .= ' (' . implode(', ', $parts) . ')';
                                }
                            ?>
                                <option value="<?php echo esc_attr($branch['id']); ?>"
                                        data-customer="<?php echo esc_attr($customer_id); ?>"
                                        <?php selected($selected_branch_id, $branch['id']); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="saw-help-text field-branch-help">Uživatel uvidí data pouze z této pobočky</span>
                    </div>
                </div>
                
                <!-- Oddělení s profesionálním UX -->
                <div class="saw-form-row field-departments-row" style="display:none; margin-top: 20px;">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            Oddělení <span class="saw-required">*</span>
                        </label>
                        
                        <!-- Ovládací prvky (Search + Select All + Counter) -->
                        <div class="saw-dept-controls" style="margin-bottom: 12px; display: none;">
                            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                                <input 
                                    type="text" 
                                    id="dept-search" 
                                    class="saw-input" 
                                    placeholder="🔍 Hledat oddělení..."
                                    style="flex: 1; min-width: 200px; margin: 0;"
                                >
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <label class="saw-checkbox-label" style="margin: 0; padding: 8px 14px; background: #f0f0f1; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                                        <input type="checkbox" id="select-all-dept" style="margin: 0;">
                                        <span style="font-weight: 600; font-size: 14px;">Vybrat vše</span>
                                    </label>
                                    <div id="dept-counter" style="padding: 6px 12px; background: #0073aa; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; white-space: nowrap;">
                                        <span id="dept-selected">0</span>/<span id="dept-total">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Seznam oddělení -->
                        <div id="departments-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                            <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Nejprve vyberte pobočku výše</p>
                        </div>
                        
                        <span class="saw-help-text">Vyberte jedno nebo více oddělení, která manager uvidí</span>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- PIN pro terminály -->
        <details class="saw-form-section field-pin" style="display:none;">
            <summary>
                <span class="dashicons dashicons-lock"></span>
                <strong>PIN pro přihlášení</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="pin" class="saw-label">
                            PIN (4 číslice)
                        </label>
                        <input 
                            type="text" 
                            name="pin" 
                            id="pin" 
                            class="saw-input" 
                            maxlength="4"
                            pattern="\d{4}"
                            placeholder="např. 1234"
                        >
                        <span class="saw-help-text">Slouží pro přihlášení na terminálu</span>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- Tlačítka -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-btn saw-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit uživatele'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/users/')); ?>" class="saw-btn saw-btn-secondary">
                Zrušit
            </a>
        </div>
    </form>
</div>
