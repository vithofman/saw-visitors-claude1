<?php
/**
 * Bottom Sheet Component
 * 
 * Modal overlay from bottom for event details.
 * Content is loaded dynamically via AJAX.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar/Partials
 * @version     1.0.0
 * @since       3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Bottom Sheet Backdrop -->
<div class="saw-bottom-sheet-backdrop" id="saw-bottom-sheet-backdrop" aria-hidden="true"></div>

<!-- Bottom Sheet Container -->
<div class="saw-bottom-sheet" id="saw-bottom-sheet" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="saw-bottom-sheet-title">
    
    <!-- Drag Handle -->
    <div class="saw-bottom-sheet__handle" aria-label="Táhni pro zavření">
        <div class="saw-bottom-sheet__handle-bar"></div>
    </div>
    
    <!-- Content Container (loaded via AJAX) -->
    <div class="saw-bottom-sheet__content" id="saw-bottom-sheet-content">
        <!-- Loading state - shown initially -->
        <div class="saw-bottom-sheet__loading" id="saw-bottom-sheet-loading">
            <div class="saw-bottom-sheet__loading-spinner"></div>
            <span>Načítám detail...</span>
        </div>
    </div>
    
</div>

<!-- Event Detail Template (used by JavaScript) -->
<template id="saw-event-detail-template">
    <div class="saw-sheet-event">
        <span class="saw-sheet-event__status" data-status></span>
        
        <h2 class="saw-sheet-event__title" id="saw-bottom-sheet-title" data-title></h2>
        
        <div class="saw-sheet-event__meta">
            <div class="saw-sheet-event__meta-row">
                <span class="saw-sheet-event__meta-icon">📅</span>
                <div>
                    <span class="saw-sheet-event__meta-text" data-date></span>
                    <span class="saw-sheet-event__meta-label">Datum návštěvy</span>
                </div>
            </div>
            
            <div class="saw-sheet-event__meta-row">
                <span class="saw-sheet-event__meta-icon">🕐</span>
                <div>
                    <span class="saw-sheet-event__meta-text" data-time></span>
                    <span class="saw-sheet-event__meta-label">Čas</span>
                </div>
            </div>
            
            <div class="saw-sheet-event__meta-row">
                <span class="saw-sheet-event__meta-icon">📍</span>
                <div>
                    <span class="saw-sheet-event__meta-text" data-location></span>
                    <span class="saw-sheet-event__meta-label">Místo</span>
                </div>
            </div>
            
            <div class="saw-sheet-event__meta-row">
                <span class="saw-sheet-event__meta-icon">👥</span>
                <div>
                    <span class="saw-sheet-event__meta-text" data-persons></span>
                    <span class="saw-sheet-event__meta-label">Počet návštěvníků</span>
                </div>
            </div>
        </div>
        
        <div class="saw-sheet-event__purpose" data-purpose-container style="display: none;">
            <div class="saw-sheet-event__purpose-label">Účel návštěvy</div>
            <p class="saw-sheet-event__purpose-text" data-purpose></p>
        </div>
        
        <div class="saw-sheet-event__divider"></div>
        
        <div class="saw-sheet-event__actions">
            <a href="#" class="saw-sheet-event__action-btn saw-sheet-event__action-btn--primary" data-detail-url>
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                </svg>
                Detail
            </a>
            <a href="#" class="saw-sheet-event__action-btn saw-sheet-event__action-btn--secondary" data-edit-url>
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                </svg>
                Upravit
            </a>
        </div>
        
        <div class="saw-sheet-event__export" data-export-container style="display: none;">
            <div class="saw-sheet-event__export-label">Přidat do kalendáře</div>
            <div class="saw-sheet-event__export-links" data-export-links></div>
        </div>
    </div>
</template>
