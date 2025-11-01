/**
 * SAW Branch Switcher - JavaScript
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

(function($) {
    'use strict';
    
    class BranchSwitcher {
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
            
            this.customerId = this.container.data('customer-id');
            this.currentBranchId = this.button.data('current-branch-id');
            
            // Button click
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            // Outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawBranchSwitcher').length) {
                    this.close();
                }
            });
            
            // ESC key
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
            
            // Load branches if not loaded
            if (this.branches.length === 0) {
                this.loadBranches();
            }
        }
        
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        loadBranches() {
            if (this.isLoading || !this.customerId) return;
            
            this.isLoading = true;
            this.showLoading();
            
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
                    
                    if (response.success && response.data) {
                        this.branches = response.data;
                        this.renderBranches();
                    } else {
                        this.showError('Nepodařilo se načíst pobočky');
                    }
                },
                error: () => {
                    this.isLoading = false;
                    this.showError('Chyba serveru');
                }
            });
        }
        
        renderBranches() {
            if (this.branches.length === 0) {
                this.list.html('<div class="saw-branch-empty">Zákazník nemá žádné pobočky</div>');
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
                            <div class="saw-branch-item-name">${branch.name}</div>
                            ${branch.address ? `<div class="saw-branch-item-address">${branch.address}</div>` : ''}
                        </div>
                        ${isActive ? '<span class="saw-branch-item-check">✓</span>' : ''}
                    </div>
                `;
            });
            
            this.list.html(html);
            
            // Attach click handlers
            this.list.find('.saw-branch-item').on('click', (e) => {
                const branchId = $(e.currentTarget).data('branch-id');
                this.switchBranch(branchId);
            });
        }
        
        switchBranch(branchId) {
            if (branchId === this.currentBranchId) {
                this.close();
                return;
            }
            
            this.button.prop('disabled', true);
            
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
                        // Update button text
                        const branch = this.branches.find(b => b.id === branchId);
                        if (branch) {
                            this.button.find('.saw-branch-name').text(branch.name);
                            this.currentBranchId = branchId;
                        }
                        this.close();
                    } else {
                        alert(response.data?.message || 'Chyba při přepínání pobočky');
                    }
                    this.button.prop('disabled', false);
                },
                error: () => {
                    alert('Chyba serveru');
                    this.button.prop('disabled', false);
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
                <div class="saw-branch-error">${message}</div>
            `);
        }
    }
    
    // Initialize
    $(document).ready(function() {
        new BranchSwitcher();
    });
    
})(jQuery);
