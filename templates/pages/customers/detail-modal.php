<?php
/**
 * Customer Detail Modal Template
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Modal overlay -->
<div id="saw-customer-detail-modal" class="saw-modal-overlay" style="display: none;">
    <!-- Desktop: Modal s blur backdrop -->
    <div class="saw-modal-desktop">
        <!-- Modal container -->
        <div class="saw-modal-container">
            <!-- Header -->
            <div class="saw-modal-header">
                <div class="saw-modal-header-content">
                    <!-- Logo zákazníka -->
                    <div class="saw-modal-logo" id="saw-modal-logo" style="display: none;">
                        <img src="" alt="Logo" id="saw-modal-logo-img">
                    </div>
                    
                    <!-- Název a IČO -->
                    <div class="saw-modal-title-group">
                        <h2 class="saw-modal-title" id="saw-modal-title">Detail zákazníka</h2>
                        <p class="saw-modal-ico" id="saw-modal-ico"></p>
                    </div>
                </div>
                
                <!-- Close button -->
                <button type="button" class="saw-modal-close" id="saw-modal-close" title="Zavřít">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <!-- Body -->
            <div class="saw-modal-body" id="saw-modal-body">
                <!-- Loading state -->
                <div class="saw-modal-loading" id="saw-modal-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítám...</span>
                </div>
                
                <!-- Content -->
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
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Adresa:</span>
                                <span class="saw-modal-info-value" id="saw-info-address">—</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Branding -->
                    <div class="saw-modal-section">
                        <h3 class="saw-modal-section-title">🎨 Branding</h3>
                        <div class="saw-modal-info-grid">
                            <div class="saw-modal-info-row">
                                <span class="saw-modal-info-label">Primární barva:</span>
                                <span class="saw-modal-info-value">
                                    <span class="saw-modal-color-preview" id="saw-info-color"></span>
                                    <span id="saw-info-color-hex">—</span>
                                </span>
                            </div>
                        </div>
                        <div class="saw-modal-logo-preview" id="saw-modal-logo-preview" style="display: none;">
                            <img src="" alt="Logo zákazníka" id="saw-info-logo">
                        </div>
                    </div>
                    
                    <!-- Poznámky -->
                    <div class="saw-modal-section" id="saw-notes-section" style="display: none;">
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
    
    <!-- Mobile: Fullscreen view -->
    <div class="saw-modal-mobile">
        <div class="saw-modal-mobile-header">
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
        
        <div class="saw-modal-mobile-body" id="saw-modal-mobile-body">
            <!-- Same content as desktop, shared via JS -->
        </div>
    </div>
</div>