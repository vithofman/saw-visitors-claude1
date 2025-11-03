/**
 * SAW Branch Switcher - JavaScript (FIXED VERSION)
 * 
 * @package SAW_Visitors
 * @since 4.7.0
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
                return;
            }
            
            if (typeof sawBranchSwitcher === 'undefined') {
                return;
            }
            
            this.customerId = parseInt(this.container.data('customer-id'));
            this.currentBranchId = parseInt(this.button.data('current-branch-id')) || null;
            
            // ✅ OPRAVA: Přidán customer_id do data atributu
            if (!this.customerId || this.customerId === 0 || isNaN(this.customerId)) {
                this.showError('Neplatné ID zákazníka');
                return;
            }
            
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
                return;
            }
            
            if (!this.customerId) {
                this.showError('Chybí ID zákazníka');
                return;
            }
            
            this.isLoading = true;
            this.showLoading();
            
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_branches_for_switcher',
                    customer_id: this.customerId, // ✅ customer_id added
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    this.isLoading = false;
                    
                    if (!response.success || !response.data || !Array.isArray(response.data.branches)) {
                        this.showError('Chyba načítání poboček');
                        return;
                    }
                    
                    this.branches = response.data.branches;
                    
                    if (response.data.current_branch_id) {
                        this.currentBranchId = parseInt(response.data.current_branch_id);
                    }
                    
                    this.renderBranches();
                },
                error: () => {
                    this.isLoading = false;
                    this.showError('Chyba serveru');
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
            
            this.list.find('.saw-branch-item').on('click', (e) => {
                const branchId = parseInt($(e.currentTarget).data('branch-id'));
                this.switchBranch(branchId);
            });
        }
        
        switchBranch(branchId) {
            if (branchId === this.currentBranchId) {
                this.close();
                return;
            }
            
            this.button.prop('disabled', true);
            const originalText = this.button.find('.saw-branch-name').text();
            this.button.find('.saw-branch-name').text('Přepínání...');
            
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_branch',
                    branch_id: branchId,
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        this.button.find('.saw-branch-name').text(originalText);
                        this.button.prop('disabled', false);
                        alert(response.data?.message || 'Chyba při přepínání pobočky');
                    }
                },
                error: () => {
                    this.button.find('.saw-branch-name').text(originalText);
                    this.button.prop('disabled', false);
                    alert('Chyba serveru');
                }
            });
        }
        
        showLoading() {
            this.list.html(`
                <div class="saw-branch-loading">
                    <div class="saw-spinner"></div>
                    <span>Načítání poboček...</span>
                </div>
            `);
        }
        
        showError(message) {
            this.list.html(`
                <div class="saw-branch-error">
                    <span>⚠️ ${this.escapeHtml(message)}</span>
                </div>
            `);
        }
        
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    };
    
})(jQuery);

jQuery(document).ready(function($) {
    if ($('#sawBranchSwitcher').length === 0) {
        return;
    }
    
    if (typeof sawBranchSwitcher === 'undefined') {
        return;
    }
    
    new BranchSwitcher();
});