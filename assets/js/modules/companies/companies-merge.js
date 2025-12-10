/**
 * Companies Merge Modal - Standalone Functionality
 * 
 * Handles merge modal when loaded as separate component (standalone modal).
 * Does NOT override functions if companies-detail.js already defined them.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     1.1.0 - FIXED: No longer conflicts with companies-detail.js
 * @since       5.2.0
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {
        initMergeModal();
    });

    /**
     * Initialize merge modal (standalone)
     */
    function initMergeModal() {
        const overlay = document.getElementById('sawMergeModalOverlay');
        if (!overlay) return;

        // Bind close events
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMergeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMergeModal();
            }
        });
    }

    /**
     * Close merge modal with animation
     */
    window.closeMergeModal = function() {
        const overlay = document.getElementById('sawMergeModalOverlay');
        if (overlay) {
            overlay.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => overlay.remove(), 300);
        }
    };

    /**
     * Update merge button state
     * 
     * IMPORTANT: Only define if not already defined by companies-detail.js
     * This prevents overwriting the more complete version that handles tabs.
     */
    if (typeof window.updateMergeButton === 'undefined') {
        window.updateMergeButton = function() {
            // Check if we're in detail sidebar context (has SAWCompanyDetail)
            if (window.SAWCompanyDetail && window.SAWCompanyDetail.currentTab) {
                const currentTab = window.SAWCompanyDetail.currentTab;
                const selector = currentTab === 'auto' 
                    ? 'input[name="duplicate_ids[]"]:checked' 
                    : 'input[name="manual_ids[]"]:checked';
                var selected = document.querySelectorAll(selector);
            } else {
                // Standalone modal - only duplicate_ids
                var selected = document.querySelectorAll('input[name="duplicate_ids[]"]:checked');
            }
            
            const button = document.getElementById('sawMergeButton');
            
            if (button) {
                button.disabled = selected.length === 0;
                
                if (selected.length > 0) {
                    const count = selected.length;
                    const word = count === 1 ? 'firmu' : (count < 5 ? 'firmy' : 'firem');
                    button.textContent = `Sloučit ${count} ${word}`;
                } else {
                    button.textContent = 'Sloučit vybrané';
                }
            }
        };
    }

    /**
     * Confirm and execute merge
     * 
     * IMPORTANT: Only define if not already defined by companies-detail.js
     */
    if (typeof window.confirmMerge === 'undefined') {
        window.confirmMerge = function() {
            // Determine which checkboxes to use based on context
            var selected;
            if (window.SAWCompanyDetail && window.SAWCompanyDetail.currentTab) {
                const currentTab = window.SAWCompanyDetail.currentTab;
                const selector = currentTab === 'auto' 
                    ? 'input[name="duplicate_ids[]"]:checked' 
                    : 'input[name="manual_ids[]"]:checked';
                selected = document.querySelectorAll(selector);
            } else {
                selected = document.querySelectorAll('input[name="duplicate_ids[]"]:checked');
            }
            
            if (selected.length === 0) {
                alert('Vyberte alespoň jednu firmu ke sloučení');
                return;
            }
            
            const count = selected.length;
            const totalVisits = Array.from(selected).reduce((sum, checkbox) => {
                const visitCountEl = checkbox.closest('.saw-duplicate-item')
                    ?.querySelector('.saw-visit-count');
                if (!visitCountEl) return sum;
                const visitCountText = visitCountEl.textContent;
                const match = visitCountText.match(/\d+/);
                return sum + (match ? parseInt(match[0]) : 0);
            }, 0);
            
            const word = count === 1 ? 'firmu' : (count < 5 ? 'firmy' : 'firem');
            const message = `Opravdu chcete sloučit ${count} ${word}?\n\n` +
                            `Bude přesunuto celkem ${totalVisits} návštěv.\n\n` +
                            `TATO AKCE JE NEVRATNÁ!`;
            
            if (!confirm(message)) {
                return;
            }
            
            const button = document.getElementById('sawMergeButton');
            if (button) {
                button.disabled = true;
                button.textContent = 'Slučuji...';
            }
            
            const duplicateIds = Array.from(selected).map(cb => cb.value);
            
            // Get master_id - try multiple sources
            let masterId = null;
            
            // Source 1: SAWCompanyDetail (detail sidebar)
            if (window.SAWCompanyDetail && window.SAWCompanyDetail.companyId) {
                masterId = window.SAWCompanyDetail.companyId;
            }
            
            // Source 2: Modal data attribute (standalone modal)
            if (!masterId) {
                const modal = document.querySelector('.saw-merge-modal');
                if (modal) {
                    masterId = modal.getAttribute('data-master-id');
                }
            }
            
            // Source 3: URL pattern
            if (!masterId) {
                const urlMatch = window.location.pathname.match(/\/companies\/(\d+)\/?/);
                if (urlMatch) {
                    masterId = urlMatch[1];
                }
            }
            
            // Source 4: Data attribute on sidebar content
            if (!masterId) {
                const sidebarContent = document.querySelector('.saw-detail-sidebar-content');
                if (sidebarContent) {
                    masterId = sidebarContent.getAttribute('data-company-id');
                }
            }
            
            if (!masterId) {
                alert('❌ Chyba: Nelze určit hlavní firmu');
                if (button) {
                    button.disabled = false;
                    button.textContent = `Sloučit ${count} ${word}`;
                }
                return;
            }
            
            // CRITICAL: Use sawGlobal.nonce
            const nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : '';
            
            if (!nonce) {
                alert('❌ Chyba: Nelze ověřit požadavek');
                if (button) {
                    button.disabled = false;
                    button.textContent = `Sloučit ${count} ${word}`;
                }
                return;
            }

            const ajaxurl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
                ? window.sawGlobal.ajaxurl 
                : '/wp-admin/admin-ajax.php';

            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'saw_merge_companies',
                    nonce: nonce,
                    master_id: masterId,
                    duplicate_ids: JSON.stringify(duplicateIds)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + (data.data.message || 'Úspěšně sloučeno!'));
                    
                    // Close appropriate container
                    if (typeof closeMergeModal === 'function' && document.getElementById('sawMergeModalOverlay')) {
                        closeMergeModal();
                    } else if (typeof closeMerge === 'function') {
                        closeMerge();
                    }
                    
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    const errorMsg = data.data && data.data.message 
                        ? data.data.message 
                        : 'Neznámá chyba';
                    
                    alert('❌ Chyba: ' + errorMsg);
                    if (button) {
                        button.disabled = false;
                        button.textContent = `Sloučit ${count} ${word}`;
                    }
                }
            })
            .catch(error => {
                console.error('[Companies] Merge error:', error);
                alert('❌ Chyba při sloučení');
                if (button) {
                    button.disabled = false;
                    button.textContent = `Sloučit ${count} ${word}`;
                }
            });
        };
    }

})(jQuery);