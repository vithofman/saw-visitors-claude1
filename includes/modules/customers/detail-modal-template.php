<?php
/**
 * Customers Detail Template - SIDEBAR OPTIMIZED
 * 
 * Displays customer detail information.
 * Optimized for both modal and sidebar display.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     11.0.0 - REFACTORED: Removed all inline styles, using global CSS
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
    <?php if (!empty($item['logo_url_full'])): ?>
        <img src="<?php echo esc_url($item['logo_url_full']); ?>" 
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
                <span class="saw-badge saw-badge-secondary">
                    ID: <?php echo esc_html($item['id']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (isset($item['status'])): ?>
                <?php
                $status_map = array(
                    'potential' => 'warning',
                    'active' => 'success',
                    'inactive' => 'secondary',
                );
                $status_labels = array(
                    'potential' => 'Potenciální',
                    'active' => 'Aktivní',
                    'inactive' => 'Neaktivní',
                );
                $badge_type = $status_map[$item['status']] ?? 'secondary';
                $badge_label = $status_labels[$item['status']] ?? 'Neznámý';
                ?>
                <span class="saw-badge saw-badge-<?php echo esc_attr($badge_type); ?>">
                    <?php echo esc_html($badge_label); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['ico']) || !empty($item['dic'])): ?>
            <div class="saw-detail-header-meta">
                <?php if (!empty($item['ico'])): ?>
                    <span>
                        <strong>IČO:</strong> <code><?php echo esc_html($item['ico']); ?></code>
                    </span>
                <?php endif; ?>
                <?php if (!empty($item['dic'])): ?>
                    <span>
                        <strong>DIČ:</strong> <code><?php echo esc_html($item['dic']); ?></code>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- DETAIL SECTIONS -->
<div class="saw-detail-sections">
    
    <!-- COMPANY ADDRESS -->
    <?php if (!empty($item['address_street']) || !empty($item['address_city'])): ?>
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
    
    <!-- CONTACT INFORMATION -->
    <?php if (!empty($item['contact_person']) || !empty($item['contact_email']) || !empty($item['contact_phone'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">👤</span>
            <?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['contact_person'])): ?>
                <div>
                    <dt><?php echo esc_html__('Osoba:', 'saw-visitors'); ?></dt>
                    <dd><?php echo esc_html($item['contact_person']); ?></dd>
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
                        <a href="<?php echo esc_url($item['website']); ?>" target="_blank">
                            <?php echo esc_html($item['website']); ?> ↗
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- BILLING ADDRESS -->
    <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_city'])): ?>
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
        </div>
    </div>
    <?php endif; ?>
    
    <!-- BUSINESS INFO -->
    <?php if (!empty($item['subscription_type']) || !empty($item['account_type_id'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">💼</span>
            <?php echo esc_html__('Obchodní informace', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['subscription_type'])): ?>
                <div>
                    <dt><?php echo esc_html__('Předplatné:', 'saw-visitors'); ?></dt>
                    <dd>
                        <?php
                        $sub_names = array(
                            'monthly' => __('Měsíční', 'saw-visitors'),
                            'yearly' => __('Roční', 'saw-visitors'),
                            'trial' => __('Zkušební', 'saw-visitors'),
                        );
                        echo esc_html($sub_names[$item['subscription_type']] ?? $item['subscription_type']);
                        ?>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['last_payment_date']) && $item['last_payment_date'] != '0000-00-00'): ?>
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
    
    <!-- BRANDING -->
    <?php if (!empty($item['admin_language_default'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">🎨</span>
            <?php echo esc_html__('Nastavení', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['admin_language_default'])): ?>
                <div>
                    <dt><?php echo esc_html__('Jazyk:', 'saw-visitors'); ?></dt>
                    <dd>
                        <?php
                        $langs = array('cs' => '🇨🇿 Čeština', 'en' => '🇬🇧 English', 'de' => '🇩🇪 Deutsch');
                        echo $langs[$item['admin_language_default']] ?? esc_html(strtoupper($item['admin_language_default']));
                        ?>
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