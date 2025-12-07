<?php
/**
 * Departments Form Template
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     4.1.0 - FIXED: Correct CSS classes matching branches style
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
    ? saw_get_translations($lang, 'admin', 'departments') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// SETUP
// ============================================
$is_edit = !empty($item['id']);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Get current customer context
$customer_id = SAW_Context::get_customer_id();
$context_branch_id = SAW_Context::get_branch_id();

// Get branches
$branches = $branches ?? array();
if (empty($branches) && $customer_id) {
    global $wpdb;
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
    
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// Branch selection priority
$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    $selected_branch_id = $item['branch_id'];
} elseif (!$is_edit && $context_branch_id) {
    $selected_branch_id = $context_branch_id;
}

$form_action = $is_edit 
    ? home_url('/admin/departments/' . $item['id'] . '/edit')
    : home_url('/admin/departments/create');
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('label_edit_department', 'Upravit oddělení')) 
                : esc_html($tr('label_new_department', 'Nové oddělení')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html($tr('btn_back', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-departments">
    <form method="post" action="<?php echo esc_url($form_action); ?>" class="saw-department-form">
        <?php
        $nonce_action = $is_edit ? 'saw_edit_departments' : 'saw_create_departments';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <!-- ============================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ============================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo esc_html($tr('form_section_basic', 'Základní informace')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch Selection + Department Number -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="branch_id" class="saw-label saw-required">
                            <?php echo esc_html($tr('field_branch', 'Pobočka')); ?>
                        </label>
                        <select 
                            name="branch_id" 
                            id="branch_id" 
                            class="saw-input" 
                            required
                            <?php echo $is_edit ? 'disabled' : ''; ?>
                        >
                            <option value=""><?php echo esc_html($tr('placeholder_select_branch', '-- Vyberte pobočku --')); ?></option>
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
                            <input type="hidden" name="branch_id" value="<?php echo esc_attr($item['branch_id'] ?? ''); ?>">
                        <?php endif; ?>
                        
                        <?php if (!$is_edit && !$context_branch_id): ?>
                            <p class="saw-help-text saw-help-text-error">
                                ⚠️ <?php echo esc_html($tr('hint_branch_not_selected', 'Není vybrána žádná pobočka v branch switcheru. Vyberte pobočku manuálně.')); ?>
                            </p>
                        <?php elseif (!$is_edit && $context_branch_id): ?>
                            <p class="saw-help-text saw-help-text-success">
                                ✅ <?php echo esc_html($tr('hint_branch_from_switcher', 'Pobočka předvyplněna z branch switcheru')); ?>
                            </p>
                        <?php else: ?>
                            <p class="saw-help-text"><?php echo esc_html($tr('field_branch_help', 'Pobočka ke které oddělení patří')); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="department_number" class="saw-label">
                            <?php echo esc_html($tr('field_department_number', 'Číslo oddělení')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="department_number" 
                            id="department_number" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['department_number'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('placeholder_department_number', 'např. 001')); ?>"
                        >
                    </div>
                </div>
                
                <!-- Department Name -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="name" class="saw-label saw-required">
                            <?php echo esc_html($tr('field_name', 'Název oddělení')); ?>
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr($tr('placeholder_name', 'např. Výroba, Sklad, IT, Administrativa')); ?>"
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
                                <?php checked(!empty($item['is_active']) || !$is_edit); ?>
                            >
                            <span><?php echo esc_html($tr('label_active_department', 'Aktivní oddělení')); ?></span>
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- POPIS A POZNÁMKY -->
        <!-- ============================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit-page"></span>
                <strong><?php echo esc_html($tr('form_section_notes', 'Popis a poznámky')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label for="description" class="saw-label">
                        <?php echo esc_html($tr('field_description', 'Popis')); ?>
                    </label>
                    <textarea 
                        name="description" 
                        id="description" 
                        class="saw-textarea"
                        rows="4"
                        placeholder="<?php echo esc_attr($tr('placeholder_description', 'Volitelný popis oddělení, jeho funkce a zodpovědnosti...')); ?>"
                    ><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                    <p class="saw-help-text"><?php echo esc_html($tr('hint_description', 'Interní poznámky viditelné pouze administrátorům')); ?></p>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================ -->
        <!-- FORM ACTIONS -->
        <!-- ============================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save', 'Uložit změny')) 
                    : esc_html($tr('btn_create', 'Vytvořit oddělení')); ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-button saw-button-secondary">
                    <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>