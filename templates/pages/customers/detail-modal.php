<?php
/**
 * Customer Detail Modal Template
 * 
 * @package SAW_Visitors
 * @version 4.7.4
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="saw-customer-detail-modal" class="saw-modal-overlay" style="display: none;">
    <!-- DESKTOP VIEW -->
    <div class="saw-modal-desktop">
        <div class="saw-modal-container">
            <!-- Header -->
            <div class="saw-modal-header" id="saw-modal-header">
                <div class="saw-modal-header-content">
                    <div class="saw-modal-logo" id="saw-modal-logo" style="display: none;">
                        <img src="" alt="Logo" id="saw-modal-logo-img">
                    </div>
                    
                    <div class="saw-modal-title-group">
                        <h2 class="saw-modal-title" id="saw-modal-title">Detail zákazníka</h2>
                        <p class="saw-modal-ico" id="saw-modal-ico"></p>
                    </div>
                </div>
                
                <button type="button" class="saw-modal-close" id="saw-modal-close" title="Zavřít">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <!-- Tabs -->
            <div class="saw-modal-tabs">
                <button type="button" class="saw-modal-tab active" data-tab="basic">
                    <span class="dashicons dashicons-admin-home"></span>
                    Základní
                </button>
                <button type="button" class="saw-modal-tab" data-tab="contact">
                    <span class="dashicons dashicons-email"></span>
                    Kontakt
                </button>
                <button type="button" class="saw-modal-tab" data-tab="business">
                    <span class="dashicons dashicons-portfolio"></span>
                    Obchodní
                </button>
                <button type="button" class="saw-modal-tab" data-tab="system">
                    <span class="dashicons dashicons-admin-settings"></span>
                    Systém
                </button>
            </div>
            
            <!-- Body -->
            <div class="saw-modal-body" id="saw-modal-body">
                <!-- Loading -->
                <div class="saw-modal-loading" id="saw-modal-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítám...</span>
                </div>
                
                <!-- Content -->
                <div class="saw-modal-content" id="saw-modal-content" style="display: none;">
                    
                    <!-- TAB: Základní informace -->
                    <div class="saw-modal-tab-panel active" id="saw-tab-basic">
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Název:</span>
                                <span class="saw-modal-info-value" id="basic-name">—</span>
                            </div>
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">IČO:</span>
                                <span class="saw-modal-info-value" id="basic-ico">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="basic-dic-row" style="display: none;">
                                <span class="saw-modal-info-label">DIČ:</span>
                                <span class="saw-modal-info-value" id="basic-dic">—</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 32px;" id="operational-address-section">
                            <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #2563eb;">
                                <span class="dashicons dashicons-location" style="vertical-align: text-bottom;"></span>
                                Provozní adresa
                            </h3>
                            <div id="operational-address" style="font-size: 15px; color: #374151; line-height: 1.8;"></div>
                        </div>
                        
                        <div style="margin-top: 32px;" id="billing-address-section">
                            <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #2563eb;">
                                <span class="dashicons dashicons-media-document" style="vertical-align: text-bottom;"></span>
                                Fakturační adresa
                            </h3>
                            <div id="billing-address" style="font-size: 15px; color: #374151; line-height: 1.8;"></div>
                        </div>
                    </div>
                    
                    <!-- TAB: Kontakt -->
                    <div class="saw-modal-tab-panel" id="saw-tab-contact">
                        <div id="contact-section">
                            <div class="saw-modal-info-grid">
                                <div class="saw-modal-info-row" id="contact-person-row" style="display: none;">
                                    <span class="saw-modal-info-label">Jméno:</span>
                                    <span class="saw-modal-info-value" id="contact-person">—</span>
                                </div>
                                <div class="saw-modal-info-row" id="contact-position-row" style="display: none;">
                                    <span class="saw-modal-info-label">Funkce:</span>
                                    <span class="saw-modal-info-value" id="contact-position">—</span>
                                </div>
                                <div class="saw-modal-info-row" id="contact-email-row" style="display: none;">
                                    <span class="saw-modal-info-label">Email:</span>
                                    <span class="saw-modal-info-value">
                                        <span id="contact-email">—</span>
                                        <button class="copy-btn" data-copy="email" title="Zkopírovat">📋</button>
                                    </span>
                                </div>
                                <div class="saw-modal-info-row" id="contact-phone-row" style="display: none;">
                                    <span class="saw-modal-info-label">Telefon:</span>
                                    <span class="saw-modal-info-value">
                                        <span id="contact-phone">—</span>
                                        <button class="copy-btn" data-copy="phone" title="Zkopírovat">📋</button>
                                    </span>
                                </div>
                                <div class="saw-modal-info-row" id="contact-website-row" style="display: none;">
                                    <span class="saw-modal-info-label">Web:</span>
                                    <span class="saw-modal-info-value">
                                        <a href="#" id="contact-website" target="_blank"></a>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TAB: Obchodní údaje -->
                    <div class="saw-modal-tab-panel" id="saw-tab-business">
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Status:</span>
                                <span class="saw-modal-info-value">
                                    <span class="saw-badge" id="business-status-badge"></span>
                                </span>
                            </div>
                            <div class="saw-modal-info-row" id="business-account-type-row">
                                <span class="saw-modal-info-label">Typ účtu:</span>
                                <span class="saw-modal-info-value">
                                    <span class="saw-badge" id="business-account-type-badge"></span>
                                </span>
                            </div>
                            <div class="saw-modal-info-row" id="business-acquisition-row" style="display: none;">
                                <span class="saw-modal-info-label">Zdroj akvizice:</span>
                                <span class="saw-modal-info-value" id="business-acquisition-source">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="business-subscription-row" style="display: none;">
                                <span class="saw-modal-info-label">Předplatné:</span>
                                <span class="saw-modal-info-value" id="business-subscription-type">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="business-payment-row" style="display: none;">
                                <span class="saw-modal-info-label">Poslední platba:</span>
                                <span class="saw-modal-info-value" id="business-last-payment">—</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 32px;" id="business-notes-section">
                            <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #2563eb;">
                                <span class="dashicons dashicons-edit" style="vertical-align: text-bottom;"></span>
                                Poznámky
                            </h3>
                            <div class="saw-modal-notes" id="business-notes"></div>
                        </div>
                    </div>
                    
                    <!-- TAB: Systém -->
                    <div class="saw-modal-tab-panel" id="saw-tab-system">
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Primární barva:</span>
                                <span class="saw-modal-info-value">
                                    <span id="system-primary-color-value">#1e40af</span>
                                    <span id="system-primary-color-preview" style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; border: 1px solid #e5e7eb; margin-left: 8px;"></span>
                                </span>
                            </div>
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Výchozí jazyk:</span>
                                <span class="saw-modal-info-value" id="system-language">—</span>
                            </div>
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Vytvořeno:</span>
                                <span class="saw-modal-info-value" id="system-created-at">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="system-updated-row" style="display: none;">
                                <span class="saw-modal-info-label">Upraveno:</span>
                                <span class="saw-modal-info-value" id="system-updated-at">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="saw-modal-footer" id="saw-modal-footer" style="display: none;">
                <button type="button" class="saw-btn saw-btn-secondary" id="saw-modal-delete">
                    <span class="dashicons dashicons-trash"></span>
                    Smazat
                </button>
                <button type="button" class="saw-btn saw-btn-primary" id="saw-modal-edit">
                    <span class="dashicons dashicons-edit"></span>
                    Upravit
                </button>
            </div>
        </div>
    </div>
    
    <!-- MOBILE VIEW -->
    <div class="saw-modal-mobile">
        <div class="saw-modal-mobile-header" id="saw-modal-mobile-header">
            <button type="button" class="saw-modal-mobile-close" id="saw-modal-mobile-close">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </button>
            <h2 class="saw-modal-mobile-title" id="saw-modal-mobile-title">Detail zákazníka</h2>
            <div class="saw-modal-mobile-actions">
                <button type="button" class="saw-modal-mobile-action" id="saw-modal-mobile-edit" title="Upravit">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="saw-modal-mobile-action saw-modal-mobile-action-danger" id="saw-modal-mobile-delete" title="Smazat">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        
        <div class="saw-modal-mobile-tabs">
            <button type="button" class="saw-modal-tab active" data-tab="basic">
                <span class="dashicons dashicons-admin-home"></span>
                Základní
            </button>
            <button type="button" class="saw-modal-tab" data-tab="contact">
                <span class="dashicons dashicons-email"></span>
                Kontakt
            </button>
            <button type="button" class="saw-modal-tab" data-tab="business">
                <span class="dashicons dashicons-portfolio"></span>
                Obchodní
            </button>
            <button type="button" class="saw-modal-tab" data-tab="system">
                <span class="dashicons dashicons-admin-settings"></span>
                Systém
            </button>
        </div>
        
        <div class="saw-modal-mobile-body">
            <!-- Stejný obsah jako desktop, synchronizuje se automaticky -->
        </div>
    </div>
</div>