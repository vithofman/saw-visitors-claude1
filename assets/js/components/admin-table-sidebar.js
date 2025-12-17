/**
 * SAW Admin Table Sidebar JavaScript
 * 
 * Handles sidebar interactions including:
 * - Delete button functionality (floating action button in detail view)
 * - Related items navigation
 * 
 * @package SAW_Visitors
 * @version 6.0.0
 * @since 5.4.2 - Delete handler moved here from app.js
 */

(function($) {
    'use strict';

    // ========================================
    // DELETE BUTTON HANDLER
    // ========================================
    
    /**
     * Initialize delete button handler for detail sidebar
     * 
     * Handles click on floating delete button (.saw-floating-action-btn.delete)
     * in the detail sidebar view.
     * 
     * @since 5.4.2
     */
    function initDeleteButton() {
        console.log('[SAW Sidebar] Initializing delete button handler');
        
        // Remove existing handlers to prevent duplicates
        $(document).off('click.saw-sidebar-delete');
        
        // Use event delegation for dynamically loaded content
        // Handles: New sidebar FAB delete button, FAB group delete button, sidebar floating actions, and old delete button classes
        $(document).on('click.saw-sidebar-delete', '.sa-sidebar-fab--delete, .saw-floating-action-btn.delete, .saw-sidebar-floating-actions .saw-delete-btn, .sa-fab-group .saw-delete-btn, .sa-sidebar-footer .sa-delete-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const itemId = $btn.data('id');
            const entity = $btn.data('entity');
            const itemName = $btn.data('name') || '#' + itemId;
            
            console.log('[SAW Sidebar] Delete button clicked:', { itemId, entity, itemName });
            
            // Validate required data
            if (!itemId) {
                console.error('[SAW Sidebar] Missing item ID');
                alert('Chyba: Chybí ID záznamu');
                return;
            }
            
            if (!entity) {
                console.error('[SAW Sidebar] Missing entity');
                alert('Chyba: Chybí typ záznamu');
                return;
            }
            
            // Confirm dialog
            const confirmMessage = 'Opravdu chcete smazat: ' + itemName + '?';
            if (!confirm(confirmMessage)) {
                console.log('[SAW Sidebar] User cancelled delete');
                return;
            }
            
            // Disable button during AJAX request
            $btn.prop('disabled', true).addClass('is-loading');
            
            // Get AJAX configuration from sawGlobal
            const ajaxUrl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
                ? window.sawGlobal.ajaxurl 
                : '/wp-admin/admin-ajax.php';
            
            const nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : '';
            
            if (!nonce) {
                console.error('[SAW Sidebar] Missing nonce - sawGlobal not available');
                alert('Chyba: Nepodařilo se ověřit požadavek. Zkuste obnovit stránku.');
                $btn.prop('disabled', false).removeClass('is-loading');
                return;
            }
            
            // Build AJAX action name
            // Entity can be with hyphens (e.g., 'account-types') or underscores ('account_types')
            // Convert to underscores for AJAX action: saw_delete_account_types
            const entityForAction = entity.replace(/-/g, '_');
            const ajaxAction = 'saw_delete_' + entityForAction;
            
            console.log('[SAW Sidebar] Sending delete request:', { 
                ajaxAction, 
                itemId, 
                entity,
                ajaxUrl 
            });
            
            // Send AJAX delete request
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    id: itemId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('[SAW Sidebar] Delete response:', response);
                    
                    if (response.success) {
                        // Show success message
                        const successMsg = response.data?.message || 'Záznam byl úspěšně smazán';
                        
                        if (typeof window.sawShowToast === 'function') {
                            window.sawShowToast(successMsg, 'success');
                        }
                        
                        // Navigate back to list after short delay
                        setTimeout(function() {
                            // Build redirect URL - convert entity to URL slug
                            const entitySlug = entity.replace(/_/g, '-');
                            const listUrl = window.location.origin + '/admin/' + entitySlug + '/';
                            
                            console.log('[SAW Sidebar] Redirecting to:', listUrl);
                            
                            // Use View Transition API if available
                            if (window.viewTransition && typeof window.viewTransition.navigateTo === 'function') {
                                window.viewTransition.navigateTo(listUrl);
                            } else {
                                window.location.href = listUrl;
                            }
                        }, 500);
                    } else {
                        // Show error message
                        const errorMsg = response.data?.message || 'Nepodařilo se smazat záznam';
                        console.error('[SAW Sidebar] Delete failed:', errorMsg);
                        
                        if (typeof window.sawShowToast === 'function') {
                            window.sawShowToast(errorMsg, 'error');
                        } else {
                            alert('Chyba: ' + errorMsg);
                        }
                        
                        $btn.prop('disabled', false).removeClass('is-loading');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[SAW Sidebar] AJAX error:', { 
                        status: xhr.status, 
                        statusText: xhr.statusText, 
                        error: error,
                        responseText: xhr.responseText 
                    });
                    
                    // Determine error message based on status code
                    let errorMsg = 'Chyba při mazání záznamu';
                    
                    if (xhr.status === 403) {
                        errorMsg = 'Nemáte oprávnění smazat tento záznam';
                    } else if (xhr.status === 404) {
                        errorMsg = 'Záznam nebyl nalezen';
                    } else if (xhr.status === 0) {
                        errorMsg = 'Chyba připojení k serveru';
                    } else if (xhr.responseJSON?.data?.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    
                    if (typeof window.sawShowToast === 'function') {
                        window.sawShowToast(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                    
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            });
        });
        
        console.log('[SAW Sidebar] Delete button handler initialized');
    }

    // ========================================
    // SAVE FAB BUTTON HANDLER
    // ========================================
    
    /**
     * Initialize save FAB button loading state
     * 
     * Adds loading spinner to save FAB when form is submitted.
     * 
     * @since 6.1.0
     */
    function initSaveFabButton() {
        console.log('[SAW Sidebar] Initializing save FAB button handler');
        
        // Remove existing handlers
        $(document).off('click.saw-sidebar-save');
        
        // Add loading state on save FAB click
        $(document).on('click.saw-sidebar-save', '.sa-sidebar-fab--save', function(e) {
            const $btn = $(this);
            const formId = $btn.attr('form');
            const $form = $('#' + formId);
            
            // Check if form is valid (HTML5 validation)
            if ($form.length && $form[0].checkValidity && !$form[0].checkValidity()) {
                // Form invalid - browser will show validation messages
                return;
            }
            
            // Add loading state
            $btn.addClass('is-loading');
            
            // Remove loading after timeout (fallback if form submission fails)
            setTimeout(function() {
                $btn.removeClass('is-loading');
            }, 10000);
        });
        
        console.log('[SAW Sidebar] Save FAB button handler initialized');
    }

    // ========================================
    // RELATED ITEMS NAVIGATION
    // ========================================
    
    /**
     * Initialize related items click handler
     * 
     * Handles navigation to related records from the detail sidebar.
     * 
     * @since 5.6.0
     */
    function initRelatedItems() {
        console.log('[SAW Sidebar] Initializing related items handler');
        
        // Related items are standard links, but we can add enhanced navigation
        $(document).off('click.saw-sidebar-related');
        
        $(document).on('click.saw-sidebar-related', '.saw-related-item-link', function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            
            if (!href) {
                return;
            }
            
            console.log('[SAW Sidebar] Related item clicked:', href);
            
            // Use View Transition if available
            if (window.viewTransition && typeof window.viewTransition.navigateTo === 'function') {
                e.preventDefault();
                window.viewTransition.navigateTo(href);
            }
            // Otherwise let browser handle normally
        });
    }

    // ========================================
    // INITIALIZATION
    // ========================================
    
    /**
     * Initialize all sidebar handlers
     */
    function init() {
        initDeleteButton();
        initSaveFabButton();
        initRelatedItems();
        console.log('[SAW Sidebar] All handlers initialized');
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        init();
    });
    
    // Re-initialize after AJAX page load (SPA navigation)
    $(document).on('saw:page-loaded', function() {
        console.log('[SAW Sidebar] Re-initializing after AJAX load');
        init();
    });
    
    // Export for external use if needed
    window.SAWSidebar = {
        init: init,
        initDeleteButton: initDeleteButton,
        initSaveFabButton: initSaveFabButton,
        initRelatedItems: initRelatedItems
    };

})(jQuery);