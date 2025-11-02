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
            
            if (typeof sawBranchSwitcher === 'undefined') {
                console.error('Branch Switcher: sawBranchSwitcher object not found');
                return;
            }
            
            this.customerId = parseInt(this.container.data('customer-id'));
            this.currentBranchId = parseInt(this.button.data('current-branch-id')) || null;
            
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
                    
                    if (response.success && response.data && response.data.branches) {
                        this.branches = response.data.branches;
                        
                        if (response.data.current_branch_id) {
                            this.currentBranchId = parseInt(response.data.current_branch_id);
                        }
                        
                        this.renderBranches();
                    } else {
                        this.showError(response.data?.message || 'Nepodařilo se načíst pobočky');
                    }
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    console.error('Branch Switcher Error:', status, error);
                    this.showError('Chyba serveru při načítání poboček');
                }
            });
        }
        
        renderBranches() {
            if (this.branches.length === 0) {
                this.list.html(`
                    <div class="saw-branch-empty">
                        <p>Zákazník nemá žádné pobočky</p>
                        <a href="${window.location.origin}/admin/branches/new/" class="saw-branch-create-button">
                            ➕ Vytvořit pobočku
                        </a>
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
            
            // Zobrazit loading state v buttonu
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
                        // ✅ OPRAVA: Po přepnutí pobočky refreshnout stránku
                        // aby se načetla nová pobočka ze session a aktualizoval sidebar
                        window.location.reload();
                    } else {
                        // Vrátit původní text při chybě
                        this.button.find('.saw-branch-name').text(originalText);
                        this.button.prop('disabled', false);
                        alert(response.data?.message || 'Chyba při přepínání pobočky');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Branch Switch Error:', status, error);
                    // Vrátit původní text při chybě
                    this.button.find('.saw-branch-name').text(originalText);
                    this.button.prop('disabled', false);
                    alert('Chyba serveru při přepínání pobočky');
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
                <div class="saw-branch-error">${this.escapeHtml(message)}</div>
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
    }
    
    $(document).ready(function() {
        new BranchSwitcher();
    });
    
})(jQuery);