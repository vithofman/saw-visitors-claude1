<?php
/**
 * Companies Form Template
 * 
 * Form for creating/editing companies with auto-prefilled branch from branch switcher.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     1.1.0 - Added AJAX inline create support
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if we're in sidebar mode
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// ✅ NEW: Check if we're in nested inline create mode
$is_nested = isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create'];

// Determine if this is edit mode
$is_edit = !empty($item);
$item = $item ?? array();

// Get current customer context
$customer_id = SAW_Context::get_customer_id();

// Get branch_id from branch switcher
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

// Pre-fill branch logic: 1) Existing value (edit) -> 2) Branch switcher -> 3) Empty
$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    $selected_branch_id = $item['branch_id'];
} elseif (!$is_edit && $context_branch_id) {
    $selected_branch_id = $context_branch_id;
}

// Form action URL
$form_action = $is_edit 
    ? home_url('/admin/companies/' . $item['id'] . '/edit')
    : home_url('/admin/companies/create');
?>

<?php if (!$in_sidebar): ?>
<!-- Page Header (only when NOT in sidebar) -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit firmu' : 'Nová firma'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/companies/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Form Container -->
<div class="saw-form-container saw-module-companies">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-company-form">
        <?php 
        // Correct nonce field matching Base Controller expectations
        $nonce_action = $is_edit ? 'saw_edit_companies' : 'saw_create_companies';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- Hidden Fields -->
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <?php if ($is_nested): ?>
            <!-- ✅ NEW: Hidden field to trigger AJAX mode in Base Controller -->
            <input type="hidden" name="_ajax_inline_create" value="1">
        <?php endif; ?>
        
        <!-- ================================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Branch Selection + IČO -->
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
                            <p class="saw-help-text saw-help-text-error">
                                ⚠️ Není vybrána žádná pobočka v branch switcheru. Vyberte pobočku manuálně.
                            </p>
                        <?php elseif (!$is_edit && $context_branch_id): ?>
                            <p class="saw-help-text saw-help-text-success">
                                ✅ Pobočka předvyplněna z branch switcheru
                            </p>
                        <?php else: ?>
                            <p class="saw-help-text">Pobočka ke které firma patří</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="ico" class="saw-label">
                            IČO
                        </label>
                        <input 
                            type="text" 
                            name="ico" 
                            id="ico" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['ico'] ?? ''); ?>"
                            placeholder="např. 12345678"
                            maxlength="20"
                        >
                    </div>
                </div>
                
                <!-- Company Name -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="name" class="saw-label saw-required">
                            Název firmy
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                            placeholder="např. ABC s.r.o., XYZ a.s."
                            required
                        >
                    </div>
                </div>
                
                <!-- Archived Status -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_archived" 
                                id="is_archived" 
                                value="1"
                                <?php checked(isset($item['is_archived']) ? $item['is_archived'] : 0, 1); ?>
                            >
                            <span>Archivovat firmu</span>
                        </label>
                        <p class="saw-help-text">Archivované firmy nejsou dostupné pro výběr</p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- ADRESA -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Adresa sídla</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Street -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="street" class="saw-label">
                            Ulice a číslo popisné
                        </label>
                        <input 
                            type="text" 
                            name="street" 
                            id="street" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['street'] ?? ''); ?>"
                            placeholder="např. Hlavní 123"
                        >
                    </div>
                </div>
                
                <!-- City + ZIP -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="city" class="saw-label">
                            Město
                        </label>
                        <input 
                            type="text" 
                            name="city" 
                            id="city" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['city'] ?? ''); ?>"
                            placeholder="např. Praha, Brno"
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="zip" class="saw-label">
                            PSČ
                        </label>
                        <input 
                            type="text" 
                            name="zip" 
                            id="zip" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['zip'] ?? ''); ?>"
                            placeholder="např. 110 00"
                            maxlength="20"
                        >
                    </div>
                </div>
                
                <!-- Country -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="country" class="saw-label">
                            Země
                        </label>
                        <input 
                            type="text" 
                            name="country" 
                            id="country" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['country'] ?? 'Česká republika'); ?>"
                            placeholder="Česká republika"
                        >
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- KONTAKTNÍ ÚDAJE -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-email"></span>
                <strong>Kontaktní údaje</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Email -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="email" class="saw-label">
                            Email
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                            placeholder="např. info@firma.cz"
                        >
                    </div>
                </div>
                
                <!-- Phone -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="phone" class="saw-label">
                            Telefon
                        </label>
                        <input 
                            type="text" 
                            name="phone" 
                            id="phone" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['phone'] ?? ''); ?>"
                            placeholder="např. +420 123 456 789"
                            maxlength="50"
                        >
                    </div>
                </div>
                
                <!-- Website -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="website" class="saw-label">
                            Web
                        </label>
                        <input 
                            type="url" 
                            name="website" 
                            id="website" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['website'] ?? ''); ?>"
                            placeholder="např. https://www.firma.cz"
                        >
                        <p class="saw-help-text">Webová stránka firmy (včetně https://)</p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- FORM ACTIONS -->
        <!-- ================================================ -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit firmu'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/companies/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>
<!-- End Form Container -->