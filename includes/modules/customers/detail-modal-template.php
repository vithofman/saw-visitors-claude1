<?php
/**
 * Customers Detail Modal Template - COMPLETE VERSION
 * 
 * Displays ALL data from wp_saw_customers table including:
 * - Company logo and basic info
 * - Company address (headquarters)
 * - Billing address
 * - Contact person details
 * - Website and online presence
 * - Business information (subscription, account type, payment info)
 * - Settings and branding (colors, language)
 * - Notes
 * - Metadata (created/updated timestamps)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Customers/Templates
 * @since       1.0.0
 * @version     2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate data
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger" style="padding: var(--saw-space-xl); border-radius: var(--saw-border-radius-md); background: var(--saw-danger-light); color: var(--saw-danger); border-left: 4px solid var(--saw-danger);">';
    echo '<strong>' . esc_html__('Chyba:', 'saw-visitors') . '</strong> ' . esc_html__('Zákazník nebyl nalezen nebo data nejsou dostupná.', 'saw-visitors');
    echo '</div>';
    return;
}
?>

<!-- ========================================
     HEADER WITH LOGO AND BASIC INFO
     ======================================== -->
<div class="saw-detail-header">
    <?php if (!empty($item['logo_url_full'])): ?>
        <img src="<?php echo esc_url($item['logo_url_full']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo"
             style="max-width: 120px; max-height: 120px; object-fit: contain; border-radius: var(--saw-border-radius-lg); border: 2px solid var(--saw-border-color); padding: var(--saw-space-md); background: #ffffff; box-shadow: var(--saw-shadow-sm);">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder" style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--saw-gray-50) 0%, var(--saw-gray-100) 100%); border-radius: var(--saw-border-radius-lg); border: 2px solid var(--saw-border-color);">
            <span class="dashicons dashicons-building" style="font-size: 56px; color: var(--saw-gray-500); width: 56px; height: 56px;"></span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-info" style="flex: 1; min-width: 0;">
        <h2 style="margin: 0 0 10px 0; font-size: 26px; font-weight: 700; color: var(--saw-gray-900); letter-spacing: -0.02em; line-height: 1.2;">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--saw-space-md); margin-bottom: var(--saw-space-sm);">
            <?php if (!empty($item['id'])): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: var(--saw-gray-50); border: var(--saw-border-width) solid var(--saw-gray-200); border-radius: var(--saw-border-radius-md); font-size: var(--saw-font-size-sm); font-weight: 600; color: var(--saw-gray-700); font-family: 'SF Mono', Monaco, monospace;">
                    <span style="color: var(--saw-gray-500);"><?php echo esc_html__('ID:', 'saw-visitors'); ?></span>
                    <?php echo esc_html($item['id']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (isset($item['status'])): ?>
                <?php
                $status_badges = array(
                    'potential' => array(esc_html__('Potenciální', 'saw-visitors'), '#fbbf24', '#78350f'),
                    'active' => array(esc_html__('Aktivní', 'saw-visitors'), '#10b981', '#065f46'),
                    'inactive' => array(esc_html__('Neaktivní', 'saw-visitors'), '#94a3b8', '#475569'),
                    'suspended' => array(esc_html__('Pozastaveno', 'saw-visitors'), '#f59e0b', '#92400e'),
                );
                $status_info = isset($status_badges[$item['status']]) ? $status_badges[$item['status']] : array(esc_html__('Neznámý', 'saw-visitors'), '#94a3b8', '#475569');
                ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: <?php echo esc_attr($status_info[1]); ?>22; color: <?php echo esc_attr($status_info[2]); ?>; border-radius: 20px; font-size: var(--saw-font-size-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    <span style="font-size: 10px;">●</span>
                    <?php echo esc_html($status_info[0]); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['subscription_type'])): ?>
                <?php
                $sub_badges = array(
                    'monthly' => array(esc_html__('Měsíční', 'saw-visitors'), '#3b82f6'),
                    'yearly' => array(esc_html__('Roční', 'saw-visitors'), '#8b5cf6'),
                    'trial' => array(esc_html__('Zkušební', 'saw-visitors'), '#ec4899'),
                );
                $sub_info = isset($sub_badges[$item['subscription_type']]) ? $sub_badges[$item['subscription_type']] : array(esc_html__('Jiný', 'saw-visitors'), '#64748b');
                ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: <?php echo esc_attr($sub_info[1]); ?>22; color: <?php echo esc_attr($sub_info[1]); ?>; border-radius: 20px; font-size: var(--saw-font-size-xs); font-weight: 700; letter-spacing: 0.05em;">
                    <?php echo esc_html($sub_info[0]); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['ico']) || !empty($item['dic'])): ?>
            <div style="font-size: var(--saw-font-size-sm); color: var(--saw-gray-500); font-weight: 500;">
                <?php if (!empty($item['ico'])): ?>
                    <span style="margin-right: var(--saw-space-lg);">
                        <strong style="color: var(--saw-gray-500);"><?php echo esc_html__('IČO:', 'saw-visitors'); ?></strong> 
                        <span style="font-family: monospace; color: var(--saw-gray-700);"><?php echo esc_html($item['ico']); ?></span>
                    </span>
                <?php endif; ?>
                <?php if (!empty($item['dic'])): ?>
                    <span>
                        <strong style="color: var(--saw-gray-500);"><?php echo esc_html__('DIČ:', 'saw-visitors'); ?></strong> 
                        <span style="font-family: monospace; color: var(--saw-gray-700);"><?php echo esc_html($item['dic']); ?></span>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================
     DETAIL SECTIONS
     ======================================== -->
<div class="saw-detail-sections">
    
    <!-- COMPANY ADDRESS (HEADQUARTERS) -->
    <?php if (!empty($item['address_street']) || !empty($item['address_city']) || !empty($item['address_zip'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">🏢</span>
            <?php echo esc_html__('Sídlo společnosti', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: var(--saw-space-lg) var(--saw-space-2xl); font-size: var(--saw-font-size-base);">
            <?php if (!empty($item['address_street']) || !empty($item['address_number'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Ulice a číslo', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <?php 
                    echo esc_html($item['address_street']);
                    if (!empty($item['address_number'])) {
                        echo ' ' . esc_html($item['address_number']);
                    }
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['address_city'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Město', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['address_city']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['address_zip'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500; font-family: monospace;"><?php echo esc_html($item['address_zip']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['address_country'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Stát', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['address_country']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- BILLING ADDRESS -->
    <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_city']) || !empty($item['billing_address_zip'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">🧾</span>
            <?php echo esc_html__('Fakturační adresa', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: var(--saw-space-lg) var(--saw-space-2xl); font-size: var(--saw-font-size-base);">
            <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_number'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Ulice a číslo', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <?php 
                    echo esc_html($item['billing_address_street']);
                    if (!empty($item['billing_address_number'])) {
                        echo ' ' . esc_html($item['billing_address_number']);
                    }
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['billing_address_city'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Město', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['billing_address_city']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['billing_address_zip'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500; font-family: monospace;"><?php echo esc_html($item['billing_address_zip']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['billing_address_country'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Stát', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['billing_address_country']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- CONTACT PERSON -->
    <?php if (!empty($item['contact_person']) || !empty($item['contact_email']) || !empty($item['contact_phone'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">👤</span>
            <?php echo esc_html__('Kontaktní osoba', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: var(--saw-space-lg) var(--saw-space-2xl); font-size: var(--saw-font-size-base);">
            <?php if (!empty($item['contact_person'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Jméno', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['contact_person']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_position'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Pozice', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['contact_position']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_email'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Email', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <a href="<?php echo esc_url('mailto:' . $item['contact_email']); ?>" 
                       style="color: var(--saw-primary); text-decoration: none; font-weight: 600; transition: var(--saw-transition-base); border-bottom: 2px solid transparent;"
                       onmouseover="this.style.color='var(--saw-primary-hover)'; this.style.borderBottomColor='var(--saw-primary-hover)';"
                       onmouseout="this.style.color='var(--saw-primary)'; this.style.borderBottomColor='transparent';">
                        <?php echo esc_html($item['contact_email']); ?>
                    </a>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_phone'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Telefon', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <a href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $item['contact_phone'])); ?>" 
                       style="color: var(--saw-primary); text-decoration: none; font-weight: 600; transition: var(--saw-transition-base); border-bottom: 2px solid transparent;"
                       onmouseover="this.style.color='var(--saw-primary-hover)'; this.style.borderBottomColor='var(--saw-primary-hover)';"
                       onmouseout="this.style.color='var(--saw-primary)'; this.style.borderBottomColor='transparent';">
                        <?php echo esc_html($item['contact_phone']); ?>
                    </a>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- WEBSITE AND ONLINE PRESENCE -->
    <?php if (!empty($item['website'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">🌐</span>
            <?php echo esc_html__('Online presence', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: var(--saw-space-lg) var(--saw-space-2xl); font-size: var(--saw-font-size-base);">
            <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Webové stránky', 'saw-visitors'); ?></dt>
            <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                <a href="<?php echo esc_url($item['website']); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   style="color: var(--saw-primary); text-decoration: none; font-weight: 600; transition: var(--saw-transition-base); border-bottom: 2px solid transparent; display: inline-flex; align-items: center; gap: 6px;"
                   onmouseover="this.style.color='var(--saw-primary-hover)'; this.style.borderBottomColor='var(--saw-primary-hover)';"
                   onmouseout="this.style.color='var(--saw-primary)'; this.style.borderBottomColor='transparent';">
                    <?php 
                    $parsed_url = parse_url($item['website']);
                    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : $item['website'];
                    echo esc_html($domain); 
                    ?>
                    <span style="font-size: var(--saw-font-size-xs); opacity: 0.7;">↗</span>
                </a>
            </dd>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- BUSINESS INFORMATION -->
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">💼</span>
            <?php echo esc_html__('Obchodní informace', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 160px 1fr; gap: var(--saw-space-lg) var(--saw-space-2xl); font-size: var(--saw-font-size-base);">
            <?php if (!empty($item['subscription_type'])): ?>
                <?php
                // Fetch account type details from database
                global $wpdb;
                $account_type = $wpdb->get_row($wpdb->prepare(
                    "SELECT display_name, color, price FROM %i WHERE id = %d",
                    $wpdb->prefix . 'saw_account_types',
                    $item['subscription_type']
                ), ARRAY_A);
                ?>
                
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Typ účtu', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <?php if ($account_type): ?>
                        <span class="saw-badge" style="background-color: <?php echo esc_attr($account_type['color']); ?>; color: #fff; border-color: <?php echo esc_attr($account_type['color']); ?>;">
                            <?php echo esc_html($account_type['display_name']); ?>
                        </span>
                        <?php if (!empty($account_type['price']) && $account_type['price'] > 0): ?>
                            <span style="color: var(--saw-gray-500); font-size: var(--saw-font-size-sm); margin-left: var(--saw-space-sm);">
                                (<?php echo esc_html(number_format($account_type['price'], 0, ',', ' ')); ?> <?php echo esc_html__('Kč/měs.', 'saw-visitors'); ?>)
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: var(--saw-danger);"><?php echo esc_html__('Typ účtu nenalezen', 'saw-visitors'); ?></span>
                    <?php endif; ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['acquisition_source'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Zdroj akvizice', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;"><?php echo esc_html($item['acquisition_source']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['subscription_type'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Typ předplatného', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <?php
                    $sub_names = array(
                        'monthly' => esc_html__('Měsíční', 'saw-visitors'),
                        'yearly' => esc_html__('Roční', 'saw-visitors'),
                        'trial' => esc_html__('Zkušební', 'saw-visitors'),
                    );
                    echo isset($sub_names[$item['subscription_type']]) ? esc_html($sub_names[$item['subscription_type']]) : esc_html($item['subscription_type']);
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['last_payment_date']) && $item['last_payment_date'] != '0000-00-00'): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Poslední platba', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500; font-family: monospace;">
                    <?php echo esc_html(date_i18n('d.m.Y', strtotime($item['last_payment_date']))); ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
    <!-- SETTINGS AND BRANDING -->
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">🎨</span>
            <?php echo esc_html__('Nastavení a branding', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 160px 1fr; gap: var(--saw-space-lg) var(--saw-space-2xl); font-size: var(--saw-font-size-base);">
            <?php if (!empty($item['primary_color'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Primární barva', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500; display: flex; align-items: center; gap: 14px;">
                    <span style="display: inline-block; width: 40px; height: 40px; border-radius: var(--saw-border-radius-md); border: 2px solid var(--saw-gray-200); background-color: <?php echo esc_attr($item['primary_color']); ?>; box-shadow: var(--saw-shadow-md);"></span>
                    <code style="font-family: 'SF Mono', Monaco, monospace; font-size: var(--saw-font-size-sm); font-weight: 600; color: var(--saw-gray-700); background: var(--saw-gray-50); padding: 6px var(--saw-space-md); border-radius: var(--saw-border-radius-sm); letter-spacing: 0.5px;">
                        <?php echo esc_html(strtoupper($item['primary_color'])); ?>
                    </code>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['admin_language_default'])): ?>
                <dt style="font-size: var(--saw-font-size-sm); font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Výchozí jazyk', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-weight: 500;">
                    <?php
                    $lang_names = array(
                        'cs' => '🇨🇿 ' . esc_html__('Čeština', 'saw-visitors'),
                        'en' => '🇬🇧 ' . esc_html__('Angličtina', 'saw-visitors'),
                        'de' => '🇩🇪 ' . esc_html__('Němčina', 'saw-visitors'),
                        'sk' => '🇸🇰 ' . esc_html__('Slovenština', 'saw-visitors'),
                    );
                    echo isset($lang_names[$item['admin_language_default']]) ? $lang_names[$item['admin_language_default']] : esc_html(strtoupper($item['admin_language_default']));
                    ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
    <!-- NOTES -->
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 var(--saw-space-xl) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); padding-bottom: var(--saw-space-lg); border-bottom: 2px solid var(--saw-border-color);">
            <span style="font-size: 20px;">📝</span>
            <?php echo esc_html__('Poznámky', 'saw-visitors'); ?>
        </h3>
        <div style="padding: var(--saw-space-lg) var(--saw-space-xl); background: var(--saw-gray-50); border-radius: var(--saw-border-radius-md); border: var(--saw-border-width) solid var(--saw-border-color);">
            <p style="margin: 0; color: var(--saw-gray-700); line-height: 1.7; font-size: var(--saw-font-size-base); white-space: pre-wrap; word-wrap: break-word;">
                <?php echo nl2br(esc_html($item['notes'])); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- METADATA (CREATION DATE, ID) -->
    <div class="saw-detail-section saw-detail-meta" style="background: #fafbfc; border: var(--saw-border-width) solid var(--saw-border-color);">
        <dl style="margin: 0; display: grid; grid-template-columns: 160px 1fr; gap: var(--saw-space-md) var(--saw-space-2xl); font-size: var(--saw-font-size-sm);">
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Vytvořeno', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-500); font-weight: 500; font-family: monospace;">
                    <?php echo esc_html($item['created_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('Aktualizováno', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-500); font-weight: 500; font-family: monospace;">
                    <?php echo esc_html($item['updated_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['id'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500); text-transform: uppercase; letter-spacing: 0.06em;"><?php echo esc_html__('ID záznamu', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-500); font-weight: 500; font-family: monospace;">
                    #<?php echo esc_html($item['id']); ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>

<style>
/* Responsive adjustments for mobile devices */
@media screen and (max-width: 782px) {
    .saw-detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--saw-space-lg);
    }
    
    .saw-detail-section dl {
        grid-template-columns: 1fr !important;
        gap: var(--saw-space-sm) !important;
    }
    
    .saw-detail-section dt {
        margin-top: var(--saw-space-md);
        padding-top: var(--saw-space-md);
        border-top: var(--saw-border-width) solid var(--saw-gray-200);
    }
    
    .saw-detail-section dt:first-child {
        margin-top: 0;
        padding-top: 0;
        border-top: none;
    }
}
</style>