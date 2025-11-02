/**
 * SAW Branch Switcher - JavaScript
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

(function($) {
    'use strict';
    
    // ✅ TŘÍDA MUSÍ BÝT GLOBÁLNÍ
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
            console.log('🚀 Branch Switcher: init() called');
            
            if (!this.button.length || !this.dropdown.length) {
                console.error('❌ Branch Switcher: Button or dropdown not found');
                return;
            }
            
            if (typeof sawBranchSwitcher === 'undefined') {
                console.error('❌ Branch Switcher: sawBranchSwitcher object not found');
                return;
            }
            
            this.customerId = parseInt(this.container.data('customer-id'));
            this.currentBranchId = parseInt(this.button.data('current-branch-id')) || null;
            
            console.log('✅ Branch Switcher: customerId =', this.customerId);
            console.log('✅ Branch Switcher: currentBranchId =', this.currentBranchId);
            
            this.button.on('click', (e) => {
                e.stopPropagation();
                console.log('🖱️ Branch Switcher: Button clicked');
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
            
            console.log('✅ Branch Switcher: Initialized successfully');
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            console.log('📂 Branch Switcher: Opening dropdown');
            this.isOpen = true;
            this.dropdown.addClass('active');
            
            if (this.branches.length === 0) {
                console.log('📥 Branch Switcher: Loading branches...');
                this.loadBranches();
            }
        }
        
        close() {
            console.log('📁 Branch Switcher: Closing dropdown');
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        loadBranches() {
            if (this.isLoading || !this.customerId) {
                console.log('⚠️ Branch Switcher: Already loading or no customer ID');
                return;
            }
            
            this.isLoading = true;
            this.showLoading();
            
            console.log('🌐 Branch Switcher: Calling AJAX...');
            console.log('  URL:', sawBranchSwitcher.ajaxurl);
            console.log('  Customer ID:', this.customerId);
            console.log('  Nonce:', sawBranchSwitcher.nonce);
            
            $.ajax({
                url: sawBranchSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_get_branches_for_switcher',
                    customer_id: this.customerId,
                    nonce: sawBranchSwitcher.nonce
                },
                success: (response) => {
                    console.log('✅ Branch Switcher: AJAX Success', response);
                    this.isLoading = false;
                    
                    if (response.success && response.data && response.data.branches) {
                        this.branches = response.data.branches;
                        
                        if (response.data.current_branch_id) {
                            this.currentBranchId = parseInt(response.data.current_branch_id);
                        }
                        
                        console.log('📋 Branch Switcher: Loaded', this.branches.length, 'branches');
                        this.renderBranches();
                    } else {
                        console.error('❌ Branch Switcher: Invalid response', response);
                        this.showError(response.data?.message || 'Nepodařilo se načíst pobočky');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ Branch Switcher: AJAX Error', status, error);
                    this.isLoading = false;
                    this.showError('Chyba serveru při načítání poboček');
                }
            });
        }
        
        renderBranches() {
            console.log('🎨 Branch Switcher: Rendering', this.branches.length, 'branches');
            
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
                console.log('🖱️ Branch clicked:', branchId);
                this.switchBranch(branchId);
            });
            
            console.log('✅ Branch Switcher: Branches rendered');
        }
        
        switchBranch(branchId) {
            if (branchId === this.currentBranchId) {
                this.close();
                return;
            }
            
            console.log('🔄 Branch Switcher: Switching to branch', branchId);
            
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
                    console.log('✅ Branch Switcher: Switch success', response);
                    
                    if (response.success) {
                        window.location.reload();
                    } else {
                        this.button.find('.saw-branch-name').text(originalText);
                        this.button.prop('disabled', false);
                        alert(response.data?.message || 'Chyba při přepínání pobočky');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ Branch Switcher: Switch error', status, error);
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
    };
    
})(jQuery);

// ✅ INICIALIZACE VNĚ CLOSURE
jQuery(document).ready(function($) {
    console.log('🚀 Branch Switcher: Document ready');
    
    if ($('#sawBranchSwitcher').length === 0) {
        console.warn('⚠️ Branch Switcher: Container not found in DOM');
        return;
    }
    
    if (typeof sawBranchSwitcher === 'undefined') {
        console.error('❌ Branch Switcher: sawBranchSwitcher object not found');
        return;
    }
    
    console.log('✅ Branch Switcher: Creating instance...');
    new BranchSwitcher();
});