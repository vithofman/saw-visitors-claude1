<?php
/**
 * Customers Detail Modal Template - COMPLETE VERSION
 * 
 * Zobrazuje VŠECHNA data z tabulky wp_saw_customers
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - COMPLETE
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validace dat
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger" style="padding: 20px; border-radius: 8px; background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626;">';
    echo '<strong>Chyba:</strong> Zákazník nebyl nalezen nebo data nejsou dostupná.';
    echo '</div>';
    return;
}
?>

<!-- ========================================
     HEADER S LOGEM A ZÁKLADNÍMI ÚDAJI
     ======================================== -->
<div class="saw-detail-header">
    <?php if (!empty($item['logo_url_full'])): ?>
        <img src="<?php echo esc_url($item['logo_url_full']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo"
             style="max-width: 120px; max-height: 120px; object-fit: contain; border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder" style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 12px; border: 2px solid #e2e8f0;">
            <span class="dashicons dashicons-building" style="font-size: 56px; color: #9ca3af; width: 56px; height: 56px;"></span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-info" style="flex: 1; min-width: 0;">
        <h2 style="margin: 0 0 10px 0; font-size: 26px; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; line-height: 1.2;">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 8px;">
            <?php if (!empty($item['id'])): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; color: #475569; font-family: 'SF Mono', Monaco, monospace;">
                    <span style="color: #94a3b8;">ID:</span>
                    <?php echo esc_html($item['id']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (isset($item['status'])): ?>
                <?php
                $status_badges = [
                    'potential' => ['Potenciální', '#fbbf24', '#78350f'],
                    'active' => ['Aktivní', '#10b981', '#065f46'],
                    'inactive' => ['Neaktivní', '#94a3b8', '#475569'],
                    'suspended' => ['Pozastaveno', '#f59e0b', '#92400e'],
                ];
                $status_info = $status_badges[$item['status']] ?? ['Neznámý', '#94a3b8', '#475569'];
                ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: <?php echo $status_info[1]; ?>22; color: <?php echo $status_info[2]; ?>; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    <span style="font-size: 10px;">●</span>
                    <?php echo esc_html($status_info[0]); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['subscription_type'])): ?>
                <?php
                $sub_badges = [
                    'monthly' => ['Měsíční', '#3b82f6'],
                    'yearly' => ['Roční', '#8b5cf6'],
                    'trial' => ['Zkušební', '#ec4899'],
                ];
                $sub_info = $sub_badges[$item['subscription_type']] ?? ['Jiný', '#64748b'];
                ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: <?php echo $sub_info[1]; ?>22; color: <?php echo $sub_info[1]; ?>; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.05em;">
                    <?php echo esc_html($sub_info[0]); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['ico']) || !empty($item['dic'])): ?>
            <div style="font-size: 14px; color: #64748b; font-weight: 500;">
                <?php if (!empty($item['ico'])): ?>
                    <span style="margin-right: 16px;">
                        <strong style="color: #94a3b8;">IČO:</strong> 
                        <span style="font-family: monospace; color: #475569;"><?php echo esc_html($item['ico']); ?></span>
                    </span>
                <?php endif; ?>
                <?php if (!empty($item['dic'])): ?>
                    <span>
                        <strong style="color: #94a3b8;">DIČ:</strong> 
                        <span style="font-family: monospace; color: #475569;"><?php echo esc_html($item['dic']); ?></span>
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
    
    <!-- SÍDLO (ADDRESS) -->
    <?php if (!empty($item['address_street']) || !empty($item['address_city']) || !empty($item['address_zip'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">🏢</span>
            Sídlo společnosti
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 16px 24px; font-size: 15px;">
            <?php if (!empty($item['address_street']) || !empty($item['address_number'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Ulice a číslo</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                    <?php 
                    echo esc_html($item['address_street']);
                    if (!empty($item['address_number'])) {
                        echo ' ' . esc_html($item['address_number']);
                    }
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['address_city'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Město</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['address_city']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['address_zip'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">PSČ</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500; font-family: monospace;"><?php echo esc_html($item['address_zip']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['address_country'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Stát</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['address_country']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- FAKTURAČNÍ ADRESA (BILLING ADDRESS) -->
    <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_city']) || !empty($item['billing_address_zip'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">🧾</span>
            Fakturační adresa
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 16px 24px; font-size: 15px;">
            <?php if (!empty($item['billing_address_street']) || !empty($item['billing_address_number'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Ulice a číslo</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                    <?php 
                    echo esc_html($item['billing_address_street']);
                    if (!empty($item['billing_address_number'])) {
                        echo ' ' . esc_html($item['billing_address_number']);
                    }
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['billing_address_city'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Město</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['billing_address_city']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['billing_address_zip'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">PSČ</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500; font-family: monospace;"><?php echo esc_html($item['billing_address_zip']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['billing_address_country'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Stát</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['billing_address_country']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- KONTAKTNÍ OSOBA -->
    <?php if (!empty($item['contact_person']) || !empty($item['contact_email']) || !empty($item['contact_phone'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">👤</span>
            Kontaktní osoba
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 16px 24px; font-size: 15px;">
            <?php if (!empty($item['contact_person'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Jméno</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['contact_person']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_position'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Pozice</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['contact_position']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_email'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Email</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                    <a href="mailto:<?php echo esc_attr($item['contact_email']); ?>" 
                       style="color: #3b82f6; text-decoration: none; font-weight: 600; transition: all 0.2s; border-bottom: 2px solid transparent;"
                       onmouseover="this.style.color='#2563eb'; this.style.borderBottomColor='#2563eb';"
                       onmouseout="this.style.color='#3b82f6'; this.style.borderBottomColor='transparent';">
                        <?php echo esc_html($item['contact_email']); ?>
                    </a>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['contact_phone'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Telefon</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                    <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $item['contact_phone'])); ?>" 
                       style="color: #3b82f6; text-decoration: none; font-weight: 600; transition: all 0.2s; border-bottom: 2px solid transparent;"
                       onmouseover="this.style.color='#2563eb'; this.style.borderBottomColor='#2563eb';"
                       onmouseout="this.style.color='#3b82f6'; this.style.borderBottomColor='transparent';">
                        <?php echo esc_html($item['contact_phone']); ?>
                    </a>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- WEBOVÉ STRÁNKY A ONLINE -->
    <?php if (!empty($item['website'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">🌐</span>
            Online presence
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 16px 24px; font-size: 15px;">
            <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Webové stránky</dt>
            <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                <a href="<?php echo esc_url($item['website']); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   style="color: #3b82f6; text-decoration: none; font-weight: 600; transition: all 0.2s; border-bottom: 2px solid transparent; display: inline-flex; align-items: center; gap: 6px;"
                   onmouseover="this.style.color='#2563eb'; this.style.borderBottomColor='#2563eb';"
                   onmouseout="this.style.color='#3b82f6'; this.style.borderBottomColor='transparent';">
                    <?php 
                    $parsed_url = parse_url($item['website']);
                    $domain = $parsed_url['host'] ?? $item['website'];
                    echo esc_html($domain); 
                    ?>
                    <span style="font-size: 12px; opacity: 0.7;">↗</span>
                </a>
            </dd>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- OBCHODNÍ INFORMACE -->
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">💼</span>
            Obchodní informace
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 160px 1fr; gap: 16px 24px; font-size: 15px;">
            <?php if (!empty($item['account_type_id'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Typ účtu</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">ID: <?php echo esc_html($item['account_type_id']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['acquisition_source'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Zdroj akvizice</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;"><?php echo esc_html($item['acquisition_source']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['subscription_type'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Typ předplatného</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                    <?php
                    $sub_names = [
                        'monthly' => 'Měsíční',
                        'yearly' => 'Roční',
                        'trial' => 'Zkušební',
                    ];
                    echo esc_html($sub_names[$item['subscription_type']] ?? $item['subscription_type']);
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['last_payment_date']) && $item['last_payment_date'] != '0000-00-00'): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Poslední platba</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500; font-family: monospace;">
                    <?php echo esc_html(date_i18n('d.m.Y', strtotime($item['last_payment_date']))); ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
    <!-- NASTAVENÍ A BRANDING -->
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">🎨</span>
            Nastavení a branding
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 160px 1fr; gap: 16px 24px; font-size: 15px;">
            <?php if (!empty($item['primary_color'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Primární barva</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500; display: flex; align-items: center; gap: 14px;">
                    <span style="display: inline-block; width: 40px; height: 40px; border-radius: 8px; border: 2px solid #cbd5e1; background-color: <?php echo esc_attr($item['primary_color']); ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></span>
                    <code style="font-family: 'SF Mono', Monaco, monospace; font-size: 14px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 6px; letter-spacing: 0.5px;">
                        <?php echo esc_html(strtoupper($item['primary_color'])); ?>
                    </code>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['admin_language_default'])): ?>
                <dt style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">Výchozí jazyk</dt>
                <dd style="margin: 0; color: #1e293b; font-weight: 500;">
                    <?php
                    $lang_names = [
                        'cs' => '🇨🇿 Čeština',
                        'en' => '🇬🇧 Angličtina',
                        'de' => '🇩🇪 Němčina',
                        'sk' => '🇸🇰 Slovenština',
                    ];
                    echo $lang_names[$item['admin_language_default']] ?? strtoupper($item['admin_language_default']);
                    ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
    <!-- POZNÁMKY -->
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3 style="display: flex; align-items: center; gap: 10px; margin: 0 0 20px 0; font-size: 17px; font-weight: 700; color: #0f172a; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0;">
            <span style="font-size: 20px;">📝</span>
            Poznámky
        </h3>
        <div style="padding: 16px 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
            <p style="margin: 0; color: #475569; line-height: 1.7; font-size: 15px; white-space: pre-wrap; word-wrap: break-word;">
                <?php echo nl2br(esc_html($item['notes'])); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- METADATA (DATUM VYTVOŘENÍ, ID) -->
    <div class="saw-detail-section saw-detail-meta" style="background: #fafbfc; border: 1px solid #e2e8f0;">
        <dl style="margin: 0; display: grid; grid-template-columns: 160px 1fr; gap: 12px 24px; font-size: 13px;">
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt style="font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em;">Vytvořeno</dt>
                <dd style="margin: 0; color: #64748b; font-weight: 500; font-family: monospace;">
                    <?php echo esc_html($item['created_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt style="font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em;">Aktualizováno</dt>
                <dd style="margin: 0; color: #64748b; font-weight: 500; font-family: monospace;">
                    <?php echo esc_html($item['updated_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['id'])): ?>
                <dt style="font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em;">ID záznamu</dt>
                <dd style="margin: 0; color: #64748b; font-weight: 500; font-family: monospace;">
                    #<?php echo esc_html($item['id']); ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>

<style>
/* Responsive úpravy pro mobily */
@media screen and (max-width: 782px) {
    .saw-detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .saw-detail-section dl {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
    }
    
    .saw-detail-section dt {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
    }
    
    .saw-detail-section dt:first-child {
        margin-top: 0;
        padding-top: 0;
        border-top: none;
    }
}
</style>