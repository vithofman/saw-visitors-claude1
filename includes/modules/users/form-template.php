<?php
/**
 * Users Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     14.0.0 - Inline script triggers saw:page-loaded
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'users') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// SETUP
// ============================================
$is_edit = !empty($item['id']);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

global $wpdb;

$customer_id = SAW_Context::get_customer_id();

// Load branches for current customer
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

// Super admin can select customers
$customers = [];
if (current_user_can('manage_options')) {
    $customers = $wpdb->get_results(
        $wpdb->prepare("SELECT id, name FROM %i ORDER BY name ASC", $wpdb->prefix . 'saw_customers'),
        ARRAY_A
    );
}

// Pre-fill branch_id from context when creating
$default_branch_id = null;
if (!$is_edit) {
    $context_branch_id = SAW_Context::get_branch_id();
    if ($context_branch_id) {
        $default_branch_id = $context_branch_id;
    }
}

// Get email from WP user if editing
$email = '';
if ($is_edit && !empty($item['wp_user_id'])) {
    $wp_user = get_userdata($item['wp_user_id']);
    if ($wp_user) {
        $email = $wp_user->user_email;
    }
} elseif (isset($item['email'])) {
    $email = $item['email'];
}

// ============================================
// LOAD EXISTING DEPARTMENT IDS (for edit mode)
// This is passed to JS via data-existing attribute
// ============================================
$existing_department_ids = [];
if ($is_edit && !empty($item['id'])) {
    $existing_department_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
        intval($item['id'])
    ));
    $existing_department_ids = array_map('intval', $existing_department_ids);
}
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('form_title_edit', 'Upravit uživatele')) 
                : esc_html($tr('form_title_create', 'Nový uživatel')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/users/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html($tr('btn_back_to_list', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-users">
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
        
        <!-- ============================================ -->
        <!-- SECTION: Basic Info -->
        <!-- ============================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-users"></span>
                <strong><?php echo esc_html($tr('section_basic', 'Základní údaje')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Role + Email -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="role" class="saw-label">
                            <?php echo esc_html($tr('field_role', 'Role')); ?> <span class="saw-required">*</span>
                        </label>
                        <select name="role" id="role" class="saw-select" required>
                            <option value=""><?php echo esc_html($tr('select_placeholder', '-- Vyberte --')); ?></option>
                            <?php if (current_user_can('manage_options')): ?>
                            <option value="super_admin" <?php selected($item['role'] ?? '', 'super_admin'); ?>>
                                <?php echo esc_html($tr('role_super_admin_desc', 'Super Admin (celý systém)')); ?>
                            </option>
                            <?php endif; ?>
                            <option value="admin" <?php selected($item['role'] ?? '', 'admin'); ?>>
                                <?php echo esc_html($tr('role_admin_desc', 'Admin (všechny pobočky)')); ?>
                            </option>
                            <option value="super_manager" <?php selected($item['role'] ?? '', 'super_manager'); ?>>
                                <?php echo esc_html($tr('role_super_manager_desc', 'Super Manager (jedna pobočka)')); ?>
                            </option>
                            <option value="manager" <?php selected($item['role'] ?? '', 'manager'); ?>>
                                <?php echo esc_html($tr('role_manager_desc', 'Manager (oddělení)')); ?>
                            </option>
                            <option value="terminal" <?php selected($item['role'] ?? '', 'terminal'); ?>>
                                <?php echo esc_html($tr('role_terminal_desc', 'Terminál')); ?>
                            </option>
                        </select>
                        <span class="saw-help-text"><?php echo esc_html($tr('help_role', 'Určuje úroveň přístupu')); ?></span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label">
                            <?php echo esc_html($tr('field_email', 'Email')); ?> <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="saw-input" 
                            value="<?php echo esc_attr($email); ?>"
                            required
                            <?php echo $is_edit ? 'readonly' : ''; ?>
                        >
                        <span class="saw-help-text"><?php echo esc_html($tr('help_email', 'Slouží jako přihlašovací jméno')); ?></span>
                    </div>
                </div>
                
                <!-- First name + Last name -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="first_name" class="saw-label">
                            <?php echo esc_html($tr('field_first_name', 'Jméno')); ?> <span class="saw-required">*</span>
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
                            <?php echo esc_html($tr('field_last_name', 'Příjmení')); ?> <span class="saw-required">*</span>
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
                
                <!-- Position -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="position" class="saw-label">
                            <?php echo esc_html($tr('field_position', 'Funkce')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="position" 
                            id="position" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['position'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('placeholder_position', 'např. Vedoucí výroby, BOZP technik')); ?>"
                        >
                        <span class="saw-help-text"><?php echo esc_html($tr('help_position', 'Pracovní pozice uživatele')); ?></span>
                    </div>
                </div>
                
                <!-- Is Active -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1"
                                <?php checked($item['is_active'] ?? 1, 1); ?>
                            >
                            <span><?php echo esc_html($tr('field_is_active', 'Aktivní uživatel')); ?></span>
                        </label>
                        <span class="saw-help-text"><?php echo esc_html($tr('help_is_active', 'Neaktivní uživatel se nemůže přihlásit')); ?></span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- SECTION: Customer (super admins only) -->
        <!-- ============================================ -->
        <?php if (current_user_can('manage_options')): ?>
        <details class="saw-form-section field-customer" style="display:none;">
            <summary>
                <span class="dashicons dashicons-building"></span>
                <strong><?php echo esc_html($tr('section_customer', 'Zákazník')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="customer-select" class="saw-label">
                            <?php echo esc_html($tr('field_customer', 'Zákazník')); ?>
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
        
        <!-- ============================================ -->
        <!-- SECTION: Branch & Departments -->
        <!-- ============================================ -->
        <details class="saw-form-section field-branch-departments" style="display:none;" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong><?php echo esc_html($tr('section_branch_departments', 'Pobočka a oddělení')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch select -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label">
                            <?php echo esc_html($tr('field_branch', 'Pobočka')); ?> 
                            <span class="saw-required field-branch-required" style="display:none;">*</span>
                        </label>
                        <select name="branch_id" id="branch_id" class="saw-select">
                            <option value=""><?php echo esc_html($tr('select_placeholder', '-- Vyberte --')); ?></option>
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
                        <span class="saw-help-text field-branch-help"><?php echo esc_html($tr('help_branch', 'Uživatel uvidí data pouze z této pobočky')); ?></span>
                    </div>
                </div>
                
                <!-- Departments (for manager role) -->
                <div class="saw-form-row field-departments-row" style="display:none; margin-top: 20px;">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            <?php echo esc_html($tr('field_departments', 'Oddělení')); ?> <span class="saw-required">*</span>
                        </label>
                        
                        <!-- Controls (Search + Select All + Counter) -->
                        <div class="saw-dept-controls" style="margin-bottom: 12px; display: none;">
                            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                                <input 
                                    type="text" 
                                    id="dept-search" 
                                    class="saw-input" 
                                    placeholder="<?php echo esc_attr($tr('placeholder_search_departments', '🔍 Hledat oddělení...')); ?>"
                                    style="flex: 1; min-width: 200px; margin: 0;"
                                >
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <label class="saw-checkbox-label" style="margin: 0; padding: 8px 14px; background: #f0f0f1; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                                        <input type="checkbox" id="select-all-dept" style="margin: 0;">
                                        <span style="font-weight: 600; font-size: 14px;"><?php echo esc_html($tr('btn_select_all', 'Vybrat vše')); ?></span>
                                    </label>
                                    <div id="dept-counter" style="padding: 6px 12px; background: #0073aa; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; white-space: nowrap;">
                                        <span id="dept-selected">0</span>/<span id="dept-total">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Departments list - data-existing contains pre-selected IDs -->
                        <div id="departments-list" 
                             data-existing="<?php echo esc_attr(json_encode(array_values($existing_department_ids))); ?>"
                             style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                            <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">
                                <?php echo esc_html($tr('departments_select_branch_first', 'Nejprve vyberte pobočku výše')); ?>
                            </p>
                        </div>
                        
                        <span class="saw-help-text"><?php echo esc_html($tr('help_departments', 'Vyberte jedno nebo více oddělení, která manager uvidí')); ?></span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- SECTION: PIN (terminal only) -->
        <!-- ============================================ -->
        <details class="saw-form-section field-pin" style="display:none;">
            <summary>
                <span class="dashicons dashicons-lock"></span>
                <strong><?php echo esc_html($tr('section_pin', 'PIN pro přihlášení')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="pin" class="saw-label">
                            <?php echo esc_html($tr('field_pin', 'PIN (4 číslice)')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="pin" 
                            id="pin" 
                            class="saw-input" 
                            maxlength="4"
                            pattern="\d{4}"
                            placeholder="<?php echo esc_attr($tr('placeholder_pin', 'např. 1234')); ?>"
                        >
                        <span class="saw-help-text"><?php echo esc_html($tr('help_pin', 'Slouží pro přihlášení na terminálu')); ?></span>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- FORM ACTIONS -->
        <!-- ============================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save_changes', 'Uložit změny')) 
                    : esc_html($tr('btn_create_user', 'Vytvořit uživatele')); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/users/')); ?>" class="saw-button saw-button-secondary">
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
        </div>
        
    </form>
</div>

<script>
// Trigger initialization after AJAX sidebar load
(function() {
    console.log('[SAW Users] Inline script executed');
    console.log('[SAW Users] data-existing:', document.getElementById('departments-list')?.getAttribute('data-existing'));
    if (typeof jQuery !== 'undefined' && typeof window.sawGlobal !== 'undefined') {
        jQuery('#role').removeData('saw-v13');
        setTimeout(function() {
            jQuery(document).trigger('saw:page-loaded');
        }, 50);
    }
})();
</script>