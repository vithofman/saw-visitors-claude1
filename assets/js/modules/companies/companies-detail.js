/**
 * Companies Detail Modal - Merge & Tab Management
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
        initDetailModal();
    });

    /**
     * Initialize detail modal functionality
     */
    function initDetailModal() {
        // Check if we're on detail page
        const mergeBtn = document.getElementById('sawMergeBtn');
        if (!mergeBtn) return;

        // Global state
        window.SAWCompanyDetail = {
            currentTab: 'auto',
            companyId: getCompanyId()
        };

        // Bind events
        mergeBtn.addEventListener('click', openMergeContainer);
    }

    /**
     * Get company ID from page
     */
    function getCompanyId() {
        const detailContent = document.querySelector('.saw-detail-sidebar-content');
        if (!detailContent) return null;
        
        // Try to extract from URL or data attribute
        const urlMatch = window.location.pathname.match(/\/companies\/(\d+)\//);
        if (urlMatch) return parseInt(urlMatch[1]);
        
        // Try data attribute
        const dataId = detailContent.getAttribute('data-company-id');
        if (dataId) return parseInt(dataId);
        
        // Try to find in script tag (fallback for compatibility)
        const scripts = document.querySelectorAll('script');
        for (let script of scripts) {
            const match = script.textContent.match(/var\s+companyId\s*=\s*(\d+)/);
            if (match) return parseInt(match[1]);
        }
        
        return null;
    }

    /**
     * Open merge container and load content
     */
    function openMergeContainer() {
        const container = document.getElementById('sawMergeContainer');
        if (!container) return;
        container.classList.add('active');
        
        const currentTab = window.SAWCompanyDetail.currentTab;
        
        if (currentTab === 'auto') {
            loadAutoDetectionContent();
        }
    }

    /**
     * Load auto-detection merge content via AJAX
     */
    function loadAutoDetectionContent() {
        const content = document.getElementById('sawMergeAutoContent');
        if (!content) return;
        content.innerHTML = '<div class="saw-loading-state">⏳ Načítání...</div>';
        
        const companyId = window.SAWCompanyDetail.companyId;
        
        if (!companyId) {
            content.innerHTML = '<div class="saw-error-state">❌ Chyba: Nelze určit ID firmy</div>';
            return;
        }
        
        // CRITICAL: Use sawGlobal.nonce (correct unified nonce)
        const nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
            ? window.sawGlobal.nonce 
            : '';
        
        if (!nonce) {
            content.innerHTML = '<div class="saw-error-state">❌ Chyba: Nelze ověřit požadavek</div>';
            console.error('[Companies] No AJAX nonce available');
            return;
        }

        const ajaxurl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
            ? window.sawGlobal.ajaxurl 
            : '/wp-admin/admin-ajax.php';

        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'saw_show_merge_modal',
                nonce: nonce,  // ✅ FIXED: Using saw_ajax_nonce
                id: companyId
            })
        })
        .then(r => r.text())
        .then(html => {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const body = temp.querySelector('.saw-modal-body');
            content.innerHTML = body ? body.innerHTML : html;
        })
        .catch(e => {
            content.innerHTML = '<div class="saw-error-state">❌ ' + e.message + '</div>';
        });
    }

    /**
     * Close merge container
     */
    window.closeMerge = function() {
        const container = document.getElementById('sawMergeContainer');
        if (container) {
            container.classList.remove('active');
        }
    };

    /**
     * Switch between auto and manual tabs
     */
    window.switchTab = function(tab) {
        window.SAWCompanyDetail.currentTab = tab;
        
        // Update tab buttons
        document.querySelectorAll('.saw-merge-tab').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Find clicked button and activate it
        const event = window.event || arguments[0];
        if (event && event.target) {
            event.target.classList.add('active');
        } else {
            // Fallback: activate by tab value
            const tabs = document.querySelectorAll('.saw-merge-tab');
            if (tab === 'auto' && tabs[0]) tabs[0].classList.add('active');
            if (tab === 'manual' && tabs[1]) tabs[1].classList.add('active');
        }
        
        // Update content panels
        const autoPanel = document.getElementById('sawMergeAuto');
        const manualPanel = document.getElementById('sawMergeManual');
        
        if (autoPanel) autoPanel.style.display = tab === 'auto' ? 'block' : 'none';
        if (manualPanel) manualPanel.style.display = tab === 'manual' ? 'block' : 'none';
        
        // Update classes for styling
        if (autoPanel) {
            if (tab === 'auto') {
                autoPanel.classList.add('active');
            } else {
                autoPanel.classList.remove('active');
            }
        }
        if (manualPanel) {
            if (tab === 'manual') {
                manualPanel.classList.add('active');
            } else {
                manualPanel.classList.remove('active');
            }
        }
    };

    /**
     * Filter manual company list
     */
    window.filterManualList = function() {
        const searchInput = document.getElementById('sawManualSearch');
        if (!searchInput) return;
        const filter = searchInput.value.toLowerCase();
        const items = document.querySelectorAll('#sawManualList .saw-duplicate-item');
        
        items.forEach(item => {
            const name = item.getAttribute('data-name') || '';
            item.style.display = name.includes(filter) ? 'flex' : 'none';
        });
    };

    /**
     * Update merge button state
     */
    window.updateMergeButton = function() {
        const currentTab = window.SAWCompanyDetail.currentTab;
        const selector = currentTab === 'auto' 
            ? 'input[name="duplicate_ids[]"]:checked' 
            : 'input[name="manual_ids[]"]:checked';
        
        const selected = document.querySelectorAll(selector);
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
        const currentTab = window.SAWCompanyDetail.currentTab;
        const selector = currentTab === 'auto' 
            ? 'input[name="duplicate_ids[]"]:checked' 
            : 'input[name="manual_ids[]"]:checked';
        
        const selected = document.querySelectorAll(selector);
        
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
        const companyId = window.SAWCompanyDetail.companyId;
        
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
                master_id: companyId,
                duplicate_ids: JSON.stringify(duplicateIds)
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Use elegant alert if available, otherwise standard alert
                if (typeof window.showElegantAlert === 'function') {
                    window.showElegantAlert(true, data.data.message || 'Úspěšně sloučeno!');
                } else {
                    alert('✓ ' + (data.data.message || 'Úspěšně sloučeno!'));
                }
                
                closeMerge();
                setTimeout(() => window.location.reload(), 500);
            } else {
                const errorMsg = data.data && data.data.message 
                    ? data.data.message 
                    : 'Neznámá chyba';
                
                if (typeof window.showElegantAlert === 'function') {
                    window.showElegantAlert(false, 'Chyba při sloučení', errorMsg);
                } else {
                    alert('❌ Chyba: ' + errorMsg);
                }
                button.disabled = false;
                button.textContent = `Sloučit ${count} ${word}`;
            }
        })
        .catch(error => {
            console.error('[Companies] Merge error:', error);
            if (typeof window.showElegantAlert === 'function') {
                window.showElegantAlert(false, 'Chyba připojení', error.message);
            } else {
                alert('❌ Chyba při sloučení');
            }
            button.disabled = false;
            button.textContent = `Sloučit ${count} ${word}`;
        });
    };

    /**
     * Toggle visit details
     */
    window.toggleVisit = function(visitId) {
        const content = document.getElementById('visit-' + visitId);
        const icon = document.getElementById('icon-' + visitId);
        if (content && icon) {
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.classList.remove('expanded');
            } else {
                content.style.display = 'block';
                icon.classList.add('expanded');
            }
        }
    };

    /**
     * Show elegant alert (if not available globally)
     */
    window.showElegantAlert = function(isSuccess, title, message) {
        const alert = document.createElement('div');
        alert.className = `saw-elegant-alert ${isSuccess ? 'saw-alert-success' : 'saw-alert-error'}`;
        alert.innerHTML = `
            <div class="saw-alert-icon">${isSuccess ? '✓' : '✕'}</div>
            <div class="saw-alert-content">
                <strong>${title}</strong>
                <p>${message || ''}</p>
            </div>
            <button class="saw-alert-close" onclick="this.parentElement.remove()">×</button>
        `;
        document.body.appendChild(alert);
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 5000);
    };

})(jQuery);

