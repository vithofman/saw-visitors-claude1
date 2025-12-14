<?php
/**
 * Customers Detail Sidebar Template
 *
 * Matches branches/departments industrial style.
 * Header (with status + account type badges) is rendered by detail-sidebar.php
 * via get_detail_header_meta() in controller.
 *
 * NO ID DISPLAYED - as per requirement.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers
 * @version     2.1.0 - NO ID, header meta in blue header
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
    ? saw_get_translations($lang, 'admin', 'customers') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html($tr('error_not_found', 'Zákazník nebyl nalezen')) . '</div>';
    return;
}

// ============================================
// LOAD RELATED DATA
// ============================================
global $wpdb;

$branches_count = 0;
$users_count = 0;
$branches = array();

if (!empty($item['id'])) {
    $customer_id = intval($item['id']);
    
    // Count branches
    $branches_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d",
        $customer_id
    ));
    
    // Count users
    $users_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d",
        $customer_id
    ));
    
    // Get first 5 branches
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code, is_headquarters, is_active 
         FROM {$wpdb->prefix}saw_branches 
         WHERE customer_id = %d 
         ORDER BY is_headquarters DESC, name ASC
         LIMIT 5",
        $customer_id
    ), ARRAY_A) ?: array();
}

// Subscription labels
$subscription_labels = array(
    'monthly' => $tr('subscription_monthly', 'Měsíční'),
    'yearly' => $tr('subscription_yearly', 'Roční'),
    'trial' => $tr('subscription_trial', 'Zkušební'),
);

// Language labels
$language_labels = array(
    'cs' => '🇨🇿 Čeština',
    'en' => '🇬🇧 English',
    'de' => '🇩🇪 Deutsch',
    'sk' => '🇸🇰 Slovenčina',
);

// Check sections
$has_address = !empty($item['address_street']) || !empty($item['address_city']) || !empty($item['address_zip']);
$has_billing = !empty($item['billing_address_street']) || !empty($item['billing_address_city']) || !empty($item['billing_address_zip']);
$has_contact = !empty($item['contact_person']) || !empty($item['contact_email']) || !empty($item['contact_phone']) || !empty($item['website']);
?>

<!-- Header with name, logo, status badge, account type badge is rendered by detail-sidebar.php -->

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- STATISTICS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📊 <?php echo esc_html($tr('section_statistics', 'Statistiky')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('stat_branches', 'Poboček')); ?></span>
                    <span class="saw-info-val"><strong><?php echo $branches_count; ?></strong></span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('stat_users', 'Uživatelů')); ?></span>
                    <span class="saw-info-val"><strong><?php echo $users_count; ?></strong></span>
                </div>
            </div>
        </div>
        
        <!-- BUSINESS INFO (IČO, DIČ only - Status & Account Type are in header) -->
        <?php if (!empty($item['ico']) || !empty($item['dic'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">💼 <?php echo esc_html($tr('section_business', 'Obchodní údaje')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['ico'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_ico', 'IČO')); ?></span>
                    <span class="saw-info-val"><code><?php echo esc_html($item['ico']); ?></code></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['dic'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_dic', 'DIČ')); ?></span>
                    <span class="saw-info-val"><code><?php echo esc_html($item['dic']); ?></code></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- COMPANY ADDRESS -->
        <?php if ($has_address): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🏢 <?php echo esc_html($tr('section_address', 'Sídlo společnosti')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-address-block">
                    <?php if (!empty($item['address_street']) || !empty($item['address_number'])): ?>
                        <div><?php echo esc_html(trim(($item['address_street'] ?? '') . ' ' . ($item['address_number'] ?? ''))); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['address_city']) || !empty($item['address_zip'])): ?>
                        <div><?php echo esc_html(trim(($item['address_zip'] ?? '') . ' ' . ($item['address_city'] ?? ''))); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['address_country'])): ?>
                        <div style="color: #666;"><?php echo esc_html($item['address_country']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- BILLING ADDRESS -->
        <?php if ($has_billing): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📄 <?php echo esc_html($tr('section_billing', 'Fakturační adresa')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-address-block">
                    <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_number'])): ?>
                        <div><?php echo esc_html(trim(($item['billing_address_street'] ?? '') . ' ' . ($item['billing_address_number'] ?? ''))); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['billing_address_city']) || !empty($item['billing_address_zip'])): ?>
                        <div><?php echo esc_html(trim(($item['billing_address_zip'] ?? '') . ' ' . ($item['billing_address_city'] ?? ''))); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['billing_address_country'])): ?>
                        <div style="color: #666;"><?php echo esc_html($item['billing_address_country']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- CONTACT INFO -->
        <?php if ($has_contact): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📞 <?php echo esc_html($tr('section_contact', 'Kontaktní údaje')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['contact_person'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_contact_person', 'Osoba')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['contact_person']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['contact_email'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_email', 'Email')); ?></span>
                    <span class="saw-info-val">
                        <a href="mailto:<?php echo esc_attr($item['contact_email']); ?>">
                            <?php echo esc_html($item['contact_email']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['contact_phone'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_phone', 'Telefon')); ?></span>
                    <span class="saw-info-val">
                        <a href="tel:<?php echo esc_attr($item['contact_phone']); ?>">
                            <?php echo esc_html($item['contact_phone']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['website'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_website', 'Web')); ?></span>
                    <span class="saw-info-val">
                        <a href="<?php echo esc_url($item['website']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($item['website']); ?> ↗
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- SUBSCRIPTION -->
        <?php if (!empty($item['subscription_type']) || !empty($item['last_payment_date'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">💳 <?php echo esc_html($tr('section_subscription', 'Předplatné')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['subscription_type'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_subscription_type', 'Typ')); ?></span>
                    <span class="saw-info-val">
                        <?php
                        $sub_class = array('monthly' => 'info', 'yearly' => 'success', 'trial' => 'warning');
                        ?>
                        <span class="saw-badge saw-badge-<?php echo esc_attr($sub_class[$item['subscription_type']] ?? 'secondary'); ?>">
                            <?php echo esc_html($subscription_labels[$item['subscription_type']] ?? $item['subscription_type']); ?>
                        </span>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['last_payment_date']) && $item['last_payment_date'] !== '0000-00-00'): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_last_payment', 'Poslední platba')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('d.m.Y', strtotime($item['last_payment_date']))); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- SETTINGS -->
        <?php if (!empty($item['admin_language_default']) || !empty($item['primary_color'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">⚙️ <?php echo esc_html($tr('section_settings', 'Nastavení')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['admin_language_default'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_default_language', 'Jazyk')); ?></span>
                    <span class="saw-info-val"><?php echo $language_labels[$item['admin_language_default']] ?? esc_html(strtoupper($item['admin_language_default'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['primary_color'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('label_primary_color', 'Barva')); ?></span>
                    <span class="saw-info-val">
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="width: 20px; height: 20px; border-radius: 4px; background: <?php echo esc_attr($item['primary_color']); ?>; border: 1px solid rgba(0,0,0,0.1);"></span>
                            <code><?php echo esc_html($item['primary_color']); ?></code>
                        </span>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- NOTES -->
        <?php if (!empty($item['notes'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📝 <?php echo esc_html($tr('section_notes', 'Poznámky')); ?></h4>
            </div>
            <div class="saw-section-body">
                <p style="margin: 0; line-height: 1.6; color: #666; font-style: italic;">
                    <?php echo nl2br(esc_html($item['notes'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- BRANCHES -->
        <?php if (!empty($branches)): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🏢 <?php echo esc_html($tr('section_branches', 'Pobočky')); ?> <span class="saw-visit-badge-count"><?php echo $branches_count; ?></span></h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php foreach ($branches as $branch): ?>
                <a href="<?php echo esc_url(home_url('/admin/branches/' . intval($branch['id']) . '/')); ?>" 
                   class="saw-info-row" 
                   style="display: flex; padding: 12px 20px; text-decoration: none; border-bottom: 1px solid #f0f0f0;">
                    <span class="saw-info-label" style="flex-shrink: 0;">
                        <?php echo !empty($branch['is_headquarters']) ? '🏛️' : '🏢'; ?>
                    </span>
                    <span class="saw-info-val" style="flex: 1;">
                        <?php echo esc_html($branch['name']); ?>
                        <?php if (!empty($branch['code'])): ?>
                            <span style="color: #888; font-size: 12px;">[<?php echo esc_html($branch['code']); ?>]</span>
                        <?php endif; ?>
                        <?php if (empty($branch['is_active'])): ?>
                            <span class="saw-badge saw-badge-secondary" style="margin-left: 8px; font-size: 10px;"><?php echo esc_html($tr('status_inactive', 'Neaktivní')); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
                
                <?php if ($branches_count > 5): ?>
                <a href="<?php echo esc_url(home_url('/admin/branches/?customer_id=' . intval($item['id']))); ?>" 
                   style="display: block; padding: 12px 20px; text-align: center; color: #0077B5; font-weight: 600; text-decoration: none;">
                    → <?php echo esc_html($tr('show_all', 'Zobrazit všechny')); ?> (<?php echo $branches_count; ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- METADATA -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🕐 <?php echo esc_html($tr('section_metadata', 'Metadata')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['created_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('meta_created', 'Vytvořeno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['created_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('meta_updated', 'Změněno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['updated_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <?php
    // Include audit history component
    require SAW_VISITORS_PLUGIN_DIR . 'includes/components/detail-audit-history.php';
    ?>
</div>

<style>
/* Address block styling */
.saw-address-block {
    line-height: 1.7;
    font-size: 14px;
}
</style>