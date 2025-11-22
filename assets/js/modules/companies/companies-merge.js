/**
 * Companies Merge Modal - Standalone Functionality
 * 
 * Handles merge modal when loaded as separate component.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     1.0.0
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
     */
    window.updateMergeButton = function() {
        const selected = document.querySelectorAll('input[name="duplicate_ids[]"]:checked');
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

    /**
     * Confirm and execute merge
     */
    window.confirmMerge = function() {
        const selected = document.querySelectorAll('input[name="duplicate_ids[]"]:checked');
        
        if (selected.length === 0) {
            alert('Vyberte alespoň jednu firmu ke sloučení');
            return;
        }
        
        const count = selected.length;
        const totalVisits = Array.from(selected).reduce((sum, checkbox) => {
            const visitCountEl = checkbox.closest('.saw-duplicate-item')
                .querySelector('.saw-visit-count');
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
        button.disabled = true;
        button.textContent = 'Slučuji...';
        
        const duplicateIds = Array.from(selected).map(cb => cb.value);
        
        // Get master_id from modal data attribute
        const modal = document.querySelector('.saw-merge-modal');
        const masterId = modal ? modal.getAttribute('data-master-id') : null;
        
        if (!masterId) {
            alert('❌ Chyba: Nelze určit hlavní firmu');
            button.disabled = false;
            button.textContent = `Sloučit ${count} ${word}`;
            return;
        }
        
        // CRITICAL: Use sawGlobal.nonce
        const nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
            ? window.sawGlobal.nonce 
            : '';
        
        if (!nonce) {
            alert('❌ Chyba: Nelze ověřit požadavek');
            button.disabled = false;
            button.textContent = `Sloučit ${count} ${word}`;
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
                nonce: nonce,  // ✅ FIXED: Using saw_ajax_nonce
                master_id: masterId,
                duplicate_ids: JSON.stringify(duplicateIds)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✓ ' + (data.data.message || 'Úspěšně sloučeno!'));
                closeMergeModal();
                setTimeout(() => window.location.reload(), 500);
            } else {
                const errorMsg = data.data && data.data.message 
                    ? data.data.message 
                    : 'Neznámá chyba';
                
                alert('❌ Chyba: ' + errorMsg);
                button.disabled = false;
                button.textContent = `Sloučit ${count} ${word}`;
            }
        })
        .catch(error => {
            console.error('[Companies] Merge error:', error);
            alert('❌ Chyba při sloučení');
            button.disabled = false;
            button.textContent = `Sloučit ${count} ${word}`;
        });
    };

})(jQuery);

