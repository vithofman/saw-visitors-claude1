<?php
/**
 * Customers Form Template - SIDEBAR OPTIMIZED
 * 
 * Create/Edit form for customers with complete data structure.
 * Optimized for both standalone page and sidebar display.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     12.0.1 - HOTFIX: Nonce field corrected for Base Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Ensure account_types exists
if (!isset($account_types)) {
    $account_types = array();
}
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? esc_html__('Upravit zákazníka', 'saw-visitors') : esc_html__('Nový zákazník', 'saw-visitors'); ?>
        </h1>
        <?php
        $back_url = $is_edit 
            ? home_url('/admin/settings/customers/' . ($item['id'] ?? '') . '/') 
            : home_url('/admin/settings/customers/');
        ?>
        <a href="<?php echo esc_url($back_url); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html__('Zpět na seznam', 'saw-visitors'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container">
    <form method="post" action="" enctype="multipart/form-data" class="saw-customer-form">
        <?php 
        // ✅ HOTFIX: Correct nonce field matching Base Controller expectations
        // wp_nonce_field($action, $name, $referer, $echo)
        $nonce_action = $is_edit ? 'saw_edit_customers' : 'saw_create_customers';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- BASIC INFORMATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo esc_html__('Základní informace', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required"><?php echo esc_html__('Název společnosti', 'saw-visitors'); ?></label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="status" class="saw-label saw-required"><?php echo esc_html__('Status', 'saw-visitors'); ?></label>
                        <select id="status" name="status" class="saw-input" required>
                            <option value="potential" <?php selected($item['status'] ?? '', 'potential'); ?>><?php echo esc_html__('Potenciální', 'saw-visitors'); ?></option>
                            <option value="active" <?php selected($item['status'] ?? '', 'active'); ?>><?php echo esc_html__('Aktivní', 'saw-visitors'); ?></option>
                            <option value="inactive" <?php selected($item['status'] ?? '', 'inactive'); ?>><?php echo esc_html__('Neaktivní', 'saw-visitors'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="account_type_id" class="saw-label"><?php echo esc_html__('Typ účtu', 'saw-visitors'); ?></label>
                        <select id="account_type_id" name="account_type_id" class="saw-input">
                            <option value=""><?php echo esc_html__('-- Vyberte typ účtu --', 'saw-visitors'); ?></option>
                            <?php if (!empty($account_types) && is_array($account_types)): ?>
                                <?php foreach ($account_types as $type): ?>
                                    <option value="<?php echo esc_attr($type['id']); ?>" 
                                            <?php selected($item['account_type_id'] ?? '', $type['id']); ?>>
                                        <?php echo esc_html($type['display_name'] ?? $type['name']); ?>
                                        <?php if (!empty($type['price'])): ?>
                                            (<?php echo esc_html(number_format($type['price'], 0, ',', ' ')); ?> Kč/měs)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled><?php echo esc_html__('Žádné typy účtů k dispozici', 'saw-visitors'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- COMPANY DETAILS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-building"></span>
                <strong><?php echo esc_html__('Údaje společnosti', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="ico" class="saw-label"><?php echo esc_html__('IČO', 'saw-visitors'); ?></label>
                        <input type="text" id="ico" name="ico" class="saw-input"
                               value="<?php echo esc_attr($item['ico'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="dic" class="saw-label"><?php echo esc_html__('DIČ', 'saw-visitors'); ?></label>
                        <input type="text" id="dic" name="dic" class="saw-input"
                               value="<?php echo esc_attr($item['dic'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        $id = 'logo';
                        $name = 'logo';
                        $current_file_url = $item['logo_url'] ?? '';
                        $label = __('Nahrát logo', 'saw-visitors');
                        $current_label = __('Současné logo', 'saw-visitors');
                        $help_text = __('Nahrajte logo ve formátu JPG, PNG, SVG nebo WebP (max 2MB)', 'saw-visitors');
                        $accept = 'image/jpeg,image/png,image/svg+xml,image/webp';
                        $show_preview = true;
                        $config = array();
                        
                        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        ?>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- ADDRESS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong><?php echo esc_html__('Adresa', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="address_street" class="saw-label"><?php echo esc_html__('Ulice a č.p.', 'saw-visitors'); ?></label>
                        <input type="text" id="address_street" name="address_street" class="saw-input"
                               value="<?php echo esc_attr($item['address_street'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="address_city" class="saw-label"><?php echo esc_html__('Město', 'saw-visitors'); ?></label>
                        <input type="text" id="address_city" name="address_city" class="saw-input"
                               value="<?php echo esc_attr($item['address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="address_zip" class="saw-label"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></label>
                        <input type="text" id="address_zip" name="address_zip" class="saw-input"
                               value="<?php echo esc_attr($item['address_zip'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- BILLING ADDRESS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-money-alt"></span>
                <strong><?php echo esc_html__('Fakturační adresa', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="billing_address_street" class="saw-label"><?php echo esc_html__('Ulice a č.p.', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_street" name="billing_address_street" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_street'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="billing_address_city" class="saw-label"><?php echo esc_html__('Město', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_city" name="billing_address_city" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="billing_address_zip" class="saw-label"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></label>
                        <input type="text" id="billing_address_zip" name="billing_address_zip" class="saw-input"
                               value="<?php echo esc_attr($item['billing_address_zip'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- CONTACT -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-email"></span>
                <strong><?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="contact_person" class="saw-label"><?php echo esc_html__('Kontaktní osoba', 'saw-visitors'); ?></label>
                        <input type="text" id="contact_person" name="contact_person" class="saw-input"
                               value="<?php echo esc_attr($item['contact_person'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_email" class="saw-label"><?php echo esc_html__('E-mail', 'saw-visitors'); ?></label>
                        <input type="email" id="contact_email" name="contact_email" class="saw-input"
                               value="<?php echo esc_attr($item['contact_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="contact_phone" class="saw-label"><?php echo esc_html__('Telefon', 'saw-visitors'); ?></label>
                        <input type="text" id="contact_phone" name="contact_phone" class="saw-input"
                               value="<?php echo esc_attr($item['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="website" class="saw-label"><?php echo esc_html__('Webové stránky', 'saw-visitors'); ?></label>
                        <input type="url" id="website" name="website" class="saw-input"
                               value="<?php echo esc_attr($item['website'] ?? ''); ?>"
                               placeholder="https://">
                    </div>
                </div>
            </div>
        </details>
        
        <!-- NOTES -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit-page"></span>
                <strong><?php echo esc_html__('Poznámky', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="notes" class="saw-label"><?php echo esc_html__('Interní poznámky', 'saw-visitors'); ?></label>
                        <textarea id="notes" name="notes" class="saw-input" rows="5"><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </details>
        
        <!-- SUBMIT BUTTONS -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? esc_html__('Uložit změny', 'saw-visitors') : esc_html__('Vytvořit zákazníka', 'saw-visitors'); ?>
            </button>
            
            <button type="button" class="saw-button saw-button-secondary saw-form-cancel-btn">
                <span class="dashicons dashicons-dismiss"></span>
                <?php echo esc_html__('Zrušit', 'saw-visitors'); ?>
            </button>
        </div>
    </form>
</div>