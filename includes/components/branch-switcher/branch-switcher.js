/**
 * SAW Branch Switcher - JavaScript (ULTIMATE DEBUG v3.0.0)
 * 
 * CRITICAL FIXES:
 * - ✅ Uses correct nonce (saw_branch_switcher)
 * - ✅ Complete DEBUG logging
 * - ✅ Handles empty arrays properly
 * - ✅ Better error messages
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 * @version 3.0.0 - ULTIMATE DEBUG
 */

(function($) {
    'use strict';
    
    window.BranchSwitcher = class BranchSwitcher {
        constructor() {
            this.container = $('#sawBranchSwitcher');
            this.button = $('#sawBranchSwitcherButton');
            this.dropdown = $('#sawBranchSwitcherDropdown');
            this.list = $('#sawBranchSwitcherList');
            this.branches = [];
            this.customerId = null;
            this.currentBranchId = null;
            this.isOpen = false;
            this.isLoading = false;
            
            this.init();
        }
        
        init() {
            if (!this.button.length || !this.dropdown.length) {
                console.log('[Branch Switcher] Elements not found');
                return;
            }
            
            if (typeof sawBranchSwitcher === 'undefined') {
                console.error('[Branch Switcher] sawBranchSwitcher object not found!');
                return;
            }
            
            // Get customer ID from data attribute
            this.customerId = parseInt(this.container.data('customer-id'));
            this.currentBranchId = parseInt(this.button.data('current-branch-id')) || null;
            
            console.log('[Branch Switcher] Initialized', {
                customerId: this.customerId,
                currentBranchId: this.currentBranchId,
                ajaxurl: sawBranchSwitcher.ajaxurl,
                hasNonce: !!sawBranchSwitcher.nonce
            });
            
            // Validate customer ID
            if (!this.customerId || this.customerId === 0 || isNaN(this.customerId)) {
                console.error('[Branch Switcher] Invalid customer ID:', this.customerId);
                this.showError('Neplatné ID zákazníka');
                return;
            }
            
            // Event listeners
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawBranchSwitcher').length) {
                    this.close();
                }
            });
            
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
            
            if (this.branches.length === 0) {
                this.loadBranches();
            }
        }
        
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        loadBranches() {
            if (this.isLoading) {
                console.log('[Branch Switcher] Already loading...');
                return;
            }
            
            if (!this.customerId) {
                this.showError('Chybí ID zákazníka');
                return;
            }
            
            this.isLoading = true;
            this.showLoading();
            
            console.log('[Branch Switcher] Loading branches...', {
                action: 'saw_get_branches_for_switcher',
                customer_id: this.customerId,
                nonce: sawBranchSwitcher.nonce
            });
            
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_branches_for_switcher',
                    customer_id: this.customerId,
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    this.isLoading = false;
                    
                    // ========================================
                    // ULTIMATE DEBUG
                    // ========================================
                    console.log('%c[Branch Switcher] RAW RESPONSE:', 'background: blue; color: white; padding: 2px 5px;', response);
                    console.log('[Branch Switcher] Response type:', typeof response);
                    console.log('[Branch Switcher] response.success:', response ? response.success : 'NO RESPONSE');
                    console.log('[Branch Switcher] response.data:', response ? response.data : 'NO RESPONSE');
                    
                    if (response && response.data) {
                        console.log('[Branch Switcher] response.data.branches:', response.data.branches);
                        console.log('[Branch Switcher] Type of branches:', typeof response.data.branches);
                        console.log('[Branch Switcher] Is array?', Array.isArray(response.data.branches));
                        console.log('[Branch Switcher] Constructor:', response.data.branches ? response.data.branches.constructor.name : 'N/A');
                        
                        // Try to iterate
                        if (response.data.branches) {
                            console.log('[Branch Switcher] Keys:', Object.keys(response.data.branches));
                            console.log('[Branch Switcher] Length:', response.data.branches.length);
                        }
                    }
                    console.log('%c[Branch Switcher] DEBUG END', 'background: blue; color: white; padding: 2px 5px;');
                    // ========================================
                    
                    // Check response exists and has success
                    if (!response || !response.success) {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba načítání poboček';
                        console.error('[Branch Switcher] Response not successful:', message);
                        this.showError(message);
                        return;
                    }
                    
                    // Check data exists
                    if (!response.data) {
                        console.error('[Branch Switcher] No response.data!');
                        this.showError('Chybí data v odpovědi');
                        return;
                    }
                    
                    // Check branches exists
                    if (!response.data.branches) {
                        console.error('[Branch Switcher] No response.data.branches!');
                        this.showError('Chybí seznam poboček');
                        return;
                    }
                    
                    // Check if branches is array
                    if (!Array.isArray(response.data.branches)) {
                        console.error('[Branch Switcher] branches is NOT an array!');
                        console.error('[Branch Switcher] Type:', typeof response.data.branches);
                        console.error('[Branch Switcher] Constructor:', response.data.branches.constructor.name);
                        console.error('[Branch Switcher] Value:', response.data.branches);
                        
                        // Try to convert to array
                        if (typeof response.data.branches === 'object') {
                            console.warn('[Branch Switcher] Attempting to convert object to array...');
                            response.data.branches = Object.values(response.data.branches);
                            console.log('[Branch Switcher] Converted to array:', response.data.branches);
                        } else {
                            this.showError('Neplatný formát dat (není pole)');
                            return;
                        }
                    }
                    
                    this.branches = response.data.branches;
                    
                    if (response.data.current_branch_id) {
                        this.currentBranchId = parseInt(response.data.current_branch_id);
                    }
                    
                    console.log('%c[Branch Switcher] SUCCESS!', 'background: green; color: white; padding: 2px 5px;');
                    console.log('[Branch Switcher] Loaded', this.branches.length, 'branches');
                    
                    this.renderBranches();
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    
                    console.error('[Branch Switcher] AJAX error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    
                    let message = 'Chyba serveru';
                    
                    if (xhr.status === 400) {
                        message = 'Chybný požadavek - zkontrolujte nonce';
                    } else if (xhr.status === 403) {
                        message = 'Nedostatečná oprávnění';
                    } else if (xhr.status === 404) {
                        message = 'AJAX endpoint nenalezen';
                    } else if (xhr.status === 0) {
                        message = 'Nelze se připojit k serveru';
                    }
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                    } catch (e) {
                        // Can't parse response
                    }
                    
                    this.showError(message);
                }
            });
        }
        
        renderBranches() {
            if (this.branches.length === 0) {
                this.list.html(`
                    <div class="saw-branch-empty">
                        <p>Zákazník nemá žádné pobočky</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            
            this.branches.forEach(branch => {
                const isActive = branch.id === this.currentBranchId;
                const activeClass = isActive ? 'active' : '';
                
                html += `
                    <div class="saw-branch-item ${activeClass}" data-branch-id="${branch.id}">
                        <span class="saw-branch-item-icon">🏢</span>
                        <div class="saw-branch-item-info">
                            <div class="saw-branch-item-name">${this.escapeHtml(branch.name)}</div>
                            ${branch.address ? `<div class="saw-branch-item-address">${this.escapeHtml(branch.address)}</div>` : ''}
                        </div>
                        ${isActive ? '<span class="saw-branch-item-check">✓</span>' : ''}
                    </div>
                `;
            });
            
            this.list.html(html);
            
            // Attach click handlers
            this.list.find('.saw-branch-item').on('click', (e) => {
                const branchId = parseInt($(e.currentTarget).data('branch-id'));
                this.switchBranch(branchId);
            });
        }
        
        switchBranch(branchId) {
            console.log('[Branch Switcher] Switching to branch:', branchId);
            
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_branch',
                    branch_id: branchId,
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    console.log('[Branch Switcher] Switch response:', response);
                    
                    if (response && response.success) {
                        // Reload page to apply new branch context
                        window.location.reload();
                    } else {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba přepnutí pobočky';
                        alert(message);
                    }
                },
                error: (xhr) => {
                    console.error('[Branch Switcher] Switch error:', xhr);
                    alert('Chyba serveru při přepínání pobočky');
                }
            });
        }
        
        showLoading() {
            this.list.html(`
                <div class="saw-branch-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítání...</span>
                </div>
            `);
        }
        
        showError(message) {
            console.error('[Branch Switcher] Error:', message);
            this.list.html(`
                <div class="saw-branch-error">
                    <p>❌ ${this.escapeHtml(message)}</p>
                </div>
            `);
        }
        
        escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        console.log('[Branch Switcher] Document ready, initializing...');
        if ($('#sawBranchSwitcher').length) {
            window.branchSwitcher = new BranchSwitcher();
        } else {
            console.log('[Branch Switcher] Component not found in DOM');
        }
    });
    
})(jQuery);