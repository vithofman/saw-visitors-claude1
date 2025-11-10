<?php
/**
 * Customers Detail Template - COMPLETE WITH ALL DB FIELDS
 * 
 * Displays ALL customer information from database.
 * Shows all address fields, contact info, business data.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     12.0.0 - ALL DB FIELDS ADDED
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate data
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">';
    echo '<strong>' . esc_html__('Chyba:', 'saw-visitors') . '</strong> ';
    echo esc_html__('Zákazník nebyl nalezen nebo data nejsou dostupná.', 'saw-visitors');
    echo '</div>';
    return;
}
?>

<!-- HEADER WITH LOGO -->
<div class="saw-detail-header">
    <?php if (!empty($item['logo_url'])): ?>
        <img src="<?php echo esc_url($item['logo_url']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder">
            <span class="dashicons dashicons-building"></span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-content">
        <h2 class="saw-detail-header-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div class="saw-detail-header-badges">
            <?php if (!empty($item['id'])): ?>
                <span class="saw-badge saw-badge-light">
                    ID: <?php echo esc_html($item['id']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['ico'])): ?>
                <span class="saw-badge saw-badge-light">
                    IČO: <?php echo esc_html($item['ico']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (isset($item['status'])): ?>
                <?php
                $status_map = array(
                    'potential' => 'warning',
                    'active' => 'success',
                    'inactive' => 'secondary',
                );
                $badge_type = $status_map[$item['status']] ?? 'secondary';
                ?>
                <span class="saw-badge saw-badge-<?php echo esc_attr($badge_type); ?>">
                    <?php echo esc_html($item['status_label'] ?? 'Neznámý'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DETAIL SECTIONS -->
<div class="saw-detail-sections">
    
    <!-- BUSINESS INFO -->
    <?php if (!empty($item['account_type_display']) || !empty($item['ico']) || !empty($item['dic']) || !empty($item['acquisition_source'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">💼</span>
            <?php echo esc_html__('Obchodní informace', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['account_type_display']) && $item['account_type_display'] !== 'Nezadáno'): ?>
                <div>
                    <dt><?php echo esc_html__('Typ účtu:', 'saw-visitors'); ?></dt>
                    <dd>
                        <span class="saw-badge saw-badge-info">
                            <?php echo esc_html($item['account_type_display']); ?>
                        </span>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['ico'])): ?>
                <div>
                    <dt><?php echo esc_html__('IČO:', 'saw-visitors'); ?></dt>
                    <dd><code><?php echo esc_html($item['ico']); ?></code></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['dic'])): ?>
                <div>
                    <dt><?php echo esc_html__('DIČ:', 'saw-visitors'); ?></dt>
                    <dd><code><?php echo esc_html($item['dic']); ?></code></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['acquisition_source'])): ?>
                <div>
                    <dt><?php echo esc_html__('Zdroj akvizice:', 'saw-visitors'); ?></dt>
                    <dd><?php echo esc_html($item['acquisition_source']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- COMPANY ADDRESS -->
    <?php 
    $has_address = !empty($item['address_street']) || !empty($item['address_city']) || !empty($item['address_zip']);
    ?>
    <?php if ($has_address): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">🏢</span>
            <?php echo esc_html__('Sídlo společnosti', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content">
            <?php if (!empty($item['address_street']) || !empty($item['address_number'])): ?>
                <div><?php echo esc_html(trim(($item['address_street'] ?? '') . ' ' . ($item['address_number'] ?? ''))); ?></div>
            <?php endif; ?>
            <?php if (!empty($item['address_city']) || !empty($item['address_zip'])): ?>
                <div><?php echo esc_html(trim(($item['address_zip'] ?? '') . ' ' . ($item['address_city'] ?? ''))); ?></div>
            <?php endif; ?>
            <?php if (!empty($item['address_country'])): ?>
                <div><?php echo esc_html($item['address_country']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- BILLING ADDRESS -->
    <?php 
    $has_billing = !empty($item['billing_address_street']) || !empty($item['billing_address_city']) || !empty($item['billing_address_zip']);
    ?>
    <?php if ($has_billing): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📄</span>
            <?php echo esc_html__('Fakturační adresa', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content">
            <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_number'])): ?>
                <div><?php echo esc_html(trim(($item['billing_address_street'] ?? '') . ' ' . ($item['billing_address_number'] ?? ''))); ?></div>
            <?php endif; ?>
            <?php if (!empty($item['billing_address_city']) || !empty($item['billing_address_zip'])): ?>
                <div><?php echo esc_html(trim(($item['billing_address_zip'] ?? '') . ' ' . ($item['billing_address_city'] ?? ''))); ?></div>
            <?php endif; ?>
            <?php if (!empty($item['billing_address_country'])): ?>
                <div><?php echo esc_html($item['billing_address_country']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- CONTACT INFORMATION -->
    <?php 
    $has_contact = !empty($item['contact_person']) || !empty($item['contact_email']) || !empty($item['contact_phone']) || !empty($item['website']);
    ?>
    <?php if ($has_contact): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📞</span>
            <?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['contact_person'])): ?>
                <div>
                    <dt><?php echo esc_html__('Kontaktní osoba:', 'saw-visitors'); ?></dt>
                    <dd>
                        <?php echo esc_html($item['contact_person']); ?>
                        <?php if (!empty($item['contact_position'])): ?>
                            <span class="saw-text-muted"> - <?php echo esc_html($item['contact_position']); ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_email'])): ?>
                <div>
                    <dt><?php echo esc_html__('Email:', 'saw-visitors'); ?></dt>
                    <dd>
                        <a href="mailto:<?php echo esc_attr($item['contact_email']); ?>">
                            <?php echo esc_html($item['contact_email']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_phone'])): ?>
                <div>
                    <dt><?php echo esc_html__('Telefon:', 'saw-visitors'); ?></dt>
                    <dd>
                        <a href="tel:<?php echo esc_attr($item['contact_phone']); ?>">
                            <?php echo esc_html($item['contact_phone']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['website'])): ?>
                <div>
                    <dt><?php echo esc_html__('Web:', 'saw-visitors'); ?></dt>
                    <dd>
                        <a href="<?php echo esc_url($item['website']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($item['website']); ?> 
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- SUBSCRIPTION & PAYMENTS -->
    <?php if (!empty($item['subscription_type']) || !empty($item['last_payment_date'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">💳</span>
            <?php echo esc_html__('Předplatné a platby', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['subscription_type'])): ?>
                <div>
                    <dt><?php echo esc_html__('Typ předplatného:', 'saw-visitors'); ?></dt>
                    <dd>
                        <?php
                        $sub_names = array(
                            'monthly' => __('Měsíční', 'saw-visitors'),
                            'yearly' => __('Roční', 'saw-visitors'),
                            'trial' => __('Zkušební', 'saw-visitors'),
                        );
                        $sub_badge = array(
                            'monthly' => 'info',
                            'yearly' => 'success',
                            'trial' => 'warning',
                        );
                        $badge_type = $sub_badge[$item['subscription_type']] ?? 'secondary';
                        ?>
                        <span class="saw-badge saw-badge-<?php echo esc_attr($badge_type); ?>">
                            <?php echo esc_html($sub_names[$item['subscription_type']] ?? $item['subscription_type']); ?>
                        </span>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['last_payment_date']) && $item['last_payment_date'] !== '0000-00-00'): ?>
                <div>
                    <dt><?php echo esc_html__('Poslední platba:', 'saw-visitors'); ?></dt>
                    <dd>
                        <?php echo esc_html(date_i18n('d.m.Y', strtotime($item['last_payment_date']))); ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- BRANDING & SETTINGS -->
    <?php if (!empty($item['admin_language_default']) || !empty($item['primary_color'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">🎨</span>
            <?php echo esc_html__('Údaje o společnosti', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['admin_language_default'])): ?>
                <div>
                    <dt><?php echo esc_html__('Výchozí jazyk:', 'saw-visitors'); ?></dt>
                    <dd>
                        <?php
                        $langs = array(
                            'cs' => '🇨🇿 Čeština',
                            'en' => '🇬🇧 English',
                            'de' => '🇩🇪 Deutsch',
                            'sk' => '🇸🇰 Slovenčina',
                        );
                        echo $langs[$item['admin_language_default']] ?? esc_html(strtoupper($item['admin_language_default']));
                        ?>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['primary_color'])): ?>
                <div>
                    <dt><?php echo esc_html__('Primární barva:', 'saw-visitors'); ?></dt>
                    <dd>
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 24px; height: 24px; border-radius: 6px; background: <?php echo esc_attr($item['primary_color']); ?>; border: 1px solid rgba(0,0,0,0.1);"></span>
                            <code><?php echo esc_html($item['primary_color']); ?></code>
                        </span>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- NOTES -->
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📝</span>
            <?php echo esc_html__('Poznámky', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content saw-detail-section-content-preformatted">
            <?php echo nl2br(esc_html($item['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- METADATA -->
    <div class="saw-detail-section saw-detail-section-metadata">
        <dl class="saw-detail-list">
            <?php if (!empty($item['created_at_formatted'])): ?>
                <div>
                    <dt><?php echo esc_html__('Vytvořeno:', 'saw-visitors'); ?></dt>
                    <dd><?php echo esc_html($item['created_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <div>
                    <dt><?php echo esc_html__('Aktualizováno:', 'saw-visitors'); ?></dt>
                    <dd><?php echo esc_html($item['updated_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    
</div>