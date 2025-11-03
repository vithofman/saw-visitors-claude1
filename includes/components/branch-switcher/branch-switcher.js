/**
 * SAW Branch Switcher - JavaScript (FIXED VERSION v2)
 * 
 * CRITICAL FIXES:
 * - ✅ Uses correct nonce (saw_branch_switcher)
 * - ✅ Sends customer_id (optional, PHP uses SAW_Context as primary)
 * - ✅ Better error handling and logging
 * - ✅ Handles 400/403 errors properly
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 * @version 2.0.0
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
                    customer_id: this.customerId, // ✅ Send for reference, but PHP uses SAW_Context
                    nonce: sawBranchSwitcher.nonce // ✅ CRITICAL: Use saw_branch_switcher nonce
                },
                success: (response) => {
                    this.isLoading = false;
                    
                    console.log('[Branch Switcher] Response received:', response);
                    
                    if (!response || !response.success) {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba načítání poboček';
                        this.showError(message);
                        return;
                    }
                    
                    if (!response.data || !Array.isArray(response.data.branches)) {
                        this.showError('Neplatná odpověď serveru');
                        return;
                    }
                    
                    this.branches = response.data.branches;
                    
                    if (response.data.current_branch_id) {
                        this.currentBranchId = parseInt(response.data.current_branch_id);
                    }
                    
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