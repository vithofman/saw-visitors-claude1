/**
 * Permissions Module - JavaScript FIXED
 * 
 * @package SAW_Visitors
 * @version 1.0.1
 * @since 4.10.0
 */

(function($) {
    'use strict';
    
    const PermissionsManager = {
        
        nonce: sawPermissionsData?.nonce || '',
        ajaxUrl: sawPermissionsData?.ajaxUrl || ajaxurl,
        homeUrl: sawPermissionsData?.homeUrl || '',
        saveTimeout: null,
        
        init: function() {
            console.log('[Permissions] Initializing...', {
                nonce: this.nonce,
                ajaxUrl: this.ajaxUrl
            });
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('change', '.permission-checkbox', this.handlePermissionChange.bind(this));
            $(document).on('change', '.scope-select', this.handleScopeChange.bind(this));
            $(document).on('change', '#role-select', this.handleRoleChange.bind(this));
            $(document).on('click', '#btn-allow-all', this.allowAll.bind(this));
            $(document).on('click', '#btn-deny-all', this.denyAll.bind(this));
            $(document).on('click', '#btn-reset', this.resetToDefaults.bind(this));
            console.log('[Permissions] Events bound');
        },
        
        getCurrentRole: function() {
            return $('#role-select').val();
        },
        
        showSaveIndicator: function(success = true) {
            clearTimeout(this.saveTimeout);
            
            const $indicator = $('#save-indicator');
            const $icon = $indicator.find('.dashicons');
            const $text = $indicator.find('.save-text');
            
            if (success) {
                $icon.removeClass('dashicons-warning').addClass('dashicons-saved');
                $text.text('Uloženo');
                $indicator.removeClass('error').addClass('success');
            } else {
                $icon.removeClass('dashicons-saved').addClass('dashicons-warning');
                $text.text('Chyba');
                $indicator.removeClass('success').addClass('error');
            }
            
            $indicator.fadeIn(200);
            
            this.saveTimeout = setTimeout(function() {
                $indicator.fadeOut(200);
            }, 2000);
        },
        
        updatePermission: function(module, action, allowed, scope) {
            const self = this;
            const role = this.getCurrentRole();
            
            const postData = {
                action: 'saw_update_permission',
                nonce: this.nonce,
                role: role,
                module: module,
                permission_action: action,  // CRITICAL FIX: was 'action'
                allowed: allowed ? 1 : 0,
                scope: scope || 'all'
            };
            
            console.log('[Permissions] Updating permission:', postData);
            
            $.post(this.ajaxUrl, postData)
            .done(function(response) {
                console.log('[Permissions] Response:', response);
                if (response.success) {
                    self.showSaveIndicator(true);
                } else {
                    self.showSaveIndicator(false);
                    console.error('[Permissions] Update failed:', response.data);
                    alert('Chyba: ' + (response.data?.message || 'Neznámá chyba'));
                }
            })
            .fail(function(xhr, status, error) {
                self.showSaveIndicator(false);
                console.error('[Permissions] AJAX error:', {xhr, status, error});
                alert('AJAX chyba: ' + error);
            });
        },
        
        handlePermissionChange: function(e) {
            const $checkbox = $(e.target);
            const module = $checkbox.data('module');
            const action = $checkbox.data('action');
            const allowed = $checkbox.is(':checked');
            
            console.log('[Permissions] Checkbox changed:', {module, action, allowed});
            
            const $indicator = $checkbox.siblings('.toggle-indicator');
            $indicator.toggleClass('active', allowed);
            
            const $row = $checkbox.closest('tr');
            const $scopeSelect = $row.find('.scope-select');
            const scope = $scopeSelect.val();
            
            this.updatePermission(module, action, allowed, scope);
        },
        
        handleScopeChange: function(e) {
            const $select = $(e.target);
            const module = $select.data('module');
            const action = $select.data('action');
            const scope = $select.val();
            
            console.log('[Permissions] Scope changed:', {module, action, scope});
            
            const $row = $select.closest('tr');
            const $checkbox = $row.find('.permission-checkbox[data-action="' + action + '"]');
            const allowed = $checkbox.is(':checked');
            
            this.updatePermission(module, action, allowed, scope);
        },
        
        handleRoleChange: function(e) {
            const role = $(e.target).val();
            console.log('[Permissions] Role changed:', role);
            window.location.href = this.homeUrl + '?role=' + role;
        },
        
        allowAll: function(e) {
            e.preventDefault();
            
            if (!confirm('Opravdu chcete povolit všechna oprávnění pro tuto roli?')) {
                return;
            }
            
            console.log('[Permissions] Allowing all...');
            $('.permission-checkbox:not(:checked)').each(function() {
                $(this).prop('checked', true).trigger('change');
            });
        },
        
        denyAll: function(e) {
            e.preventDefault();
            
            if (!confirm('Opravdu chcete zakázat všechna oprávnění pro tuto roli?')) {
                return;
            }
            
            console.log('[Permissions] Denying all...');
            $('.permission-checkbox:checked').each(function() {
                $(this).prop('checked', false).trigger('change');
            });
        },
        
        resetToDefaults: function(e) {
            e.preventDefault();
            
            if (!confirm('Opravdu chcete resetovat oprávnění na výchozí hodnoty? Tato akce je nevratná.')) {
                return;
            }
            
            const self = this;
            const role = this.getCurrentRole();
            
            console.log('[Permissions] Resetting to defaults for role:', role);
            
            $.post(this.ajaxUrl, {
                action: 'saw_reset_permissions',
                nonce: this.nonce,
                role: role
            })
            .done(function(response) {
                console.log('[Permissions] Reset response:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert('Chyba při resetování: ' + (response.data?.message || 'Neznámá chyba'));
                }
            })
            .fail(function(xhr, status, error) {
                console.error('[Permissions] Reset AJAX error:', {xhr, status, error});
                alert('Chyba při resetování: ' + error);
            });
        }
    };
    
    $(document).ready(function() {
        console.log('[Permissions] DOM ready, initializing manager...');
        PermissionsManager.init();
    });
    
})(jQuery);