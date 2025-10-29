<?php
/**
 * Customer Detail Modal Template
 * 
 * @package SAW_Visitors
 * @since 4.6.1 ENHANCED
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="saw-customer-detail-modal" class="saw-modal-overlay" style="display: none;">
    <div class="saw-modal-desktop">
        <div class="saw-modal-container">
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
            
            <div class="saw-modal-body" id="saw-modal-body">
                <div class="saw-modal-loading" id="saw-modal-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítám...</span>
                </div>
                
                <div class="saw-modal-content" id="saw-modal-content" style="display: none;">
                    <!-- Základní informace -->
                    <div class="saw-modal-section">
                        <h3 class="saw-modal-section-title">📋 Základní informace</h3>
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Název:</span>
                                <span class="saw-modal-info-value" id="saw-info-name">—</span>
                            </div>
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">IČO:</span>
                                <span class="saw-modal-info-value" id="saw-info-ico">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="saw-dic-row" style="display: none;">
                                <span class="saw-modal-info-label">DIČ:</span>
                                <span class="saw-modal-info-value" id="saw-info-dic">—</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Provozní adresa -->
                    <div class="saw-modal-section" id="operational-address-section" style="display: none;">
                        <h3 class="saw-modal-section-title">📍 Provozní adresa</h3>
                        <div class="saw-modal-address" id="saw-operational-address"></div>
                    </div>
                    
                    <!-- Fakturační adresa -->
                    <div class="saw-modal-section" id="billing-address-section" style="display: none;">
                        <h3 class="saw-modal-section-title">🧾 Fakturační adresa</h3>
                        <div class="saw-modal-address" id="saw-billing-address"></div>
                    </div>
                    
                    <!-- Kontaktní osoba -->
                    <div class="saw-modal-section" id="contact-section" style="display: none;">
                        <h3 class="saw-modal-section-title">👤 Kontaktní osoba</h3>
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row" id="contact-person-row" style="display: none;">
                                <span class="saw-modal-info-label">Jméno:</span>
                                <span class="saw-modal-info-value" id="saw-contact-person">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="contact-position-row" style="display: none;">
                                <span class="saw-modal-info-label">Funkce:</span>
                                <span class="saw-modal-info-value" id="saw-contact-position">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="contact-email-row" style="display: none;">
                                <span class="saw-modal-info-label">Email:</span>
                                <span class="saw-modal-info-value">
                                    <span id="saw-contact-email">—</span>
                                    <button class="copy-btn" data-copy="email" title="Zkopírovat" style="background: transparent; border: none; cursor: pointer; color: #0073aa; padding: 0; margin-left: 8px;">📋</button>
                                </span>
                            </div>
                            <div class="saw-modal-info-row" id="contact-phone-row" style="display: none;">
                                <span class="saw-modal-info-label">Telefon:</span>
                                <span class="saw-modal-info-value">
                                    <span id="saw-contact-phone">—</span>
                                    <button class="copy-btn" data-copy="phone" title="Zkopírovat" style="background: transparent; border: none; cursor: pointer; color: #0073aa; padding: 0; margin-left: 8px;">📋</button>
                                </span>
                            </div>
                            <div class="saw-modal-info-row" id="contact-website-row" style="display: none;">
                                <span class="saw-modal-info-label">Web:</span>
                                <span class="saw-modal-info-value">
                                    <a href="#" id="saw-contact-website" target="_blank" style="color: #0073aa; text-decoration: none;"></a>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Obchodní údaje -->
                    <div class="saw-modal-section" id="business-section">
                        <h3 class="saw-modal-section-title">💼 Obchodní údaje</h3>
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Status:</span>
                                <span class="saw-modal-info-value">
                                    <span class="saw-badge" id="saw-customer-status-badge"></span>
                                </span>
                            </div>
                            <div class="saw-modal-info-row" id="account-type-row">
                                <span class="saw-modal-info-label">Typ účtu:</span>
                                <span class="saw-modal-info-value">
                                    <span class="saw-badge" id="saw-account-type-badge"></span>
                                </span>
                            </div>
                            <div class="saw-modal-info-row" id="acquisition-row" style="display: none;">
                                <span class="saw-modal-info-label">Zdroj:</span>
                                <span class="saw-modal-info-value" id="saw-acquisition-source">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="subscription-row" style="display: none;">
                                <span class="saw-modal-info-label">Předplatné:</span>
                                <span class="saw-modal-info-value" id="saw-subscription-type">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="payment-row" style="display: none;">
                                <span class="saw-modal-info-label">Poslední platba:</span>
                                <span class="saw-modal-info-value" id="saw-last-payment">—</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Poznámky -->
                    <div class="saw-modal-section" id="notes-section" style="display: none;">
                        <h3 class="saw-modal-section-title">📝 Poznámky</h3>
                        <div class="saw-modal-notes" id="saw-info-notes"></div>
                    </div>
                    
                    <!-- Metadata -->
                    <div class="saw-modal-section">
                        <h3 class="saw-modal-section-title">ℹ️ Metadata</h3>
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Vytvořeno:</span>
                                <span class="saw-modal-info-value" id="saw-info-created">—</span>
                            </div>
                            <div class="saw-modal-info-row" id="saw-updated-row" style="display: none;">
                                <span class="saw-modal-info-label">Upraveno:</span>
                                <span class="saw-modal-info-value" id="saw-info-updated">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
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
    
    <!-- Mobile -->
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
        
        <div class="saw-modal-mobile-body" id="saw-modal-mobile-body"></div>
    </div>
</div>