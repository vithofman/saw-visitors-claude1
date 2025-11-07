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
 * @version     3.0.0 - SIDEBAR SUPPORT
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
<div class="saw-detail-header" style="display: flex; gap: 20px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
    <?php if (!empty($item['logo_url_full'])): ?>
        <img src="<?php echo esc_url($item['logo_url_full']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             style="max-width: 100px; max-height: 100px; object-fit: contain; border-radius: 8px; border: 2px solid #e5e7eb; padding: 12px; background: #fff;">
    <?php else: ?>
        <div style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 8px; border: 2px solid #e5e7eb;">
            <span class="dashicons dashicons-building" style="font-size: 48px; color: #9ca3af; width: 48px; height: 48px;"></span>
        </div>
    <?php endif; ?>
    
    <div style="flex: 1; min-width: 0;">
        <h2 style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #111827;">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
            <?php if (!empty($item['id'])): ?>
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px; font-weight: 600; color: #6b7280;">
                    ID: <?php echo esc_html($item['id']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (isset($item['status'])): ?>
                <?php
                $status_config = array(
                    'potential' => array('Potenciální', '#fbbf24', '#78350f'),
                    'active' => array('Aktivní', '#10b981', '#065f46'),
                    'inactive' => array('Neaktivní', '#94a3b8', '#475569'),
                );
                $status = $status_config[$item['status']] ?? array('Neznámý', '#94a3b8', '#475569');
                ?>
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: <?php echo esc_attr($status[1]); ?>22; color: <?php echo esc_attr($status[2]); ?>; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                    <span style="font-size: 8px;">●</span>
                    <?php echo esc_html($status[0]); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['ico']) || !empty($item['dic'])): ?>
            <div style="font-size: 13px; color: #6b7280;">
                <?php if (!empty($item['ico'])): ?>
                    <span style="margin-right: 12px;">
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
    <div class="saw-detail-section" style="margin-bottom: 20px; padding: 16px; background: #fafbfc; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">
            <span>🏢</span>
            <?php echo esc_html__('Sídlo společnosti', 'saw-visitors'); ?>
        </h3>
        <div style="font-size: 14px; line-height: 1.6; color: #111827;">
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
    <div class="saw-detail-section" style="margin-bottom: 20px; padding: 16px; background: #fafbfc; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">
            <span>👤</span>
            <?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; gap: 8px; font-size: 14px;">
            <?php if (!empty($item['contact_person'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Osoba:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px; color: #111827;"><?php echo esc_html($item['contact_person']); ?></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_email'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Email:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px;">
                        <a href="mailto:<?php echo esc_attr($item['contact_email']); ?>" style="color: #3b82f6; text-decoration: none;">
                            <?php echo esc_html($item['contact_email']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_phone'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Telefon:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px;">
                        <a href="tel:<?php echo esc_attr($item['contact_phone']); ?>" style="color: #3b82f6; text-decoration: none;">
                            <?php echo esc_html($item['contact_phone']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['website'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Web:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px;">
                        <a href="<?php echo esc_url($item['website']); ?>" target="_blank" style="color: #3b82f6; text-decoration: none;">
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
    <div class="saw-detail-section" style="margin-bottom: 20px; padding: 16px; background: #fafbfc; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">
            <span>📄</span>
            <?php echo esc_html__('Fakturační adresa', 'saw-visitors'); ?>
        </h3>
        <div style="font-size: 14px; line-height: 1.6; color: #111827;">
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
    <div class="saw-detail-section" style="margin-bottom: 20px; padding: 16px; background: #fafbfc; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">
            <span>💼</span>
            <?php echo esc_html__('Obchodní informace', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; gap: 8px; font-size: 14px;">
            <?php if (!empty($item['subscription_type'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Předplatné:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px; color: #111827;">
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
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Poslední platba:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px; color: #111827;">
                        <?php echo esc_html(date_i18n('d.m.Y', strtotime($item['last_payment_date']))); ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- BRANDING -->
    <?php if (!empty($item['primary_color']) || !empty($item['admin_language_default'])): ?>
    <div class="saw-detail-section" style="margin-bottom: 20px; padding: 16px; background: #fafbfc; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">
            <span>🎨</span>
            <?php echo esc_html__('Branding', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; gap: 8px; font-size: 14px;">
            <?php if (!empty($item['primary_color'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Primární barva:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px; color: #111827;">
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; border: 1px solid #e5e7eb; background-color: <?php echo esc_attr($item['primary_color']); ?>;"></span>
                            <code><?php echo esc_html(strtoupper($item['primary_color'])); ?></code>
                        </span>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['admin_language_default'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600; color: #6b7280;"><?php echo esc_html__('Jazyk:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px; color: #111827;">
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
    <div class="saw-detail-section" style="margin-bottom: 20px; padding: 16px; background: #fafbfc; border: 1px solid #e5e7eb; border-radius: 8px;">
        <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">
            <span>📝</span>
            <?php echo esc_html__('Poznámky', 'saw-visitors'); ?>
        </h3>
        <div style="font-size: 14px; line-height: 1.6; color: #374151; white-space: pre-wrap;">
            <?php echo nl2br(esc_html($item['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- METADATA -->
    <div class="saw-detail-section" style="padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;">
        <dl style="margin: 0; display: grid; gap: 6px; font-size: 12px; color: #6b7280;">
            <?php if (!empty($item['created_at_formatted'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600;"><?php echo esc_html__('Vytvořeno:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px;"><?php echo esc_html($item['created_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <div>
                    <dt style="display: inline; font-weight: 600;"><?php echo esc_html__('Aktualizováno:', 'saw-visitors'); ?></dt>
                    <dd style="display: inline; margin: 0 0 0 4px;"><?php echo esc_html($item['updated_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    
</div>