/**
 * Permissions Module - JavaScript
 * 
 * Handles the interactive permissions matrix UI including:
 * - Permission toggle checkboxes with AJAX save
 * - Scope selector dropdowns
 * - Role switcher
 * - Bulk actions (Allow All, Deny All, Reset to Defaults)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Permissions
 * @since       4.10.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.1
 */

(function($) {
    'use strict';
    
    /**
     * Permissions Manager Object
     * 
     * Main controller for permissions matrix interactions.
     * 
     * @since 4.10.0
     */
    const PermissionsManager = {
        
        /**
         * Nonce for AJAX security
         * @type {string}
         */
        nonce: sawPermissionsData?.nonce || '',
        
        /**
         * AJAX URL endpoint
         * @type {string}
         */
        ajaxUrl: sawPermissionsData?.ajaxUrl || ajaxurl,
        
        /**
         * Home URL for redirects
         * @type {string}
         */
        homeUrl: sawPermissionsData?.homeUrl || '',
        
        /**
         * Timeout ID for save indicator auto-hide
         * @type {number|null}
         */
        saveTimeout: null,
        
        /**
         * Initialize the permissions manager
         * 
         * Sets up all event listeners and bindings.
         * 
         * @since 4.10.0
         * @return {void}
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind all event listeners
         * 
         * Attaches event handlers to permission checkboxes, scope selectors,
         * role selector, and action buttons.
         * 
         * @since 4.10.0
         * @return {void}
         */
        bindEvents: function() {
            $(document).on('change', '.permission-checkbox', this.handlePermissionChange.bind(this));
            $(document).on('change', '.scope-select', this.handleScopeChange.bind(this));
            $(document).on('change', '#role-select', this.handleRoleChange.bind(this));
            $(document).on('click', '#btn-allow-all', this.allowAll.bind(this));
            $(document).on('click', '#btn-deny-all', this.denyAll.bind(this));
            $(document).on('click', '#btn-reset', this.resetToDefaults.bind(this));
        },
        
        /**
         * Get currently selected role from dropdown
         * 
         * @since 4.10.0
         * @return {string} Role slug
         */
        getCurrentRole: function() {
            return $('#role-select').val();
        },
        
        /**
         * Show save indicator (toast notification)
         * 
         * Displays a temporary success or error message after save operations.
         * Auto-hides after 2 seconds.
         * 
         * @since 4.10.0
         * @param {boolean} success - True for success message, false for error
         * @return {void}
         */
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
        
        /**
         * Update permission via AJAX
         * 
         * Sends permission change to server and updates UI based on response.
         * 
         * @since 4.10.0
         * @param {string} module - Module slug
         * @param {string} action - Action type (list, view, create, edit, delete)
         * @param {boolean} allowed - Whether permission is allowed
         * @param {string} scope - Permission scope (all, own_customer, own_branch)
         * @return {void}
         */
        updatePermission: function(module, action, allowed, scope) {
            const self = this;
            const role = this.getCurrentRole();
            
            const postData = {
                action: 'saw_update_permission',
                nonce: this.nonce,
                role: role,
                module: module,
                permission_action: action,
                allowed: allowed ? 1 : 0,
                scope: scope || 'all'
            };
            
            $.post(this.ajaxUrl, postData)
            .done(function(response) {
                if (response.success) {
                    self.showSaveIndicator(true);
                } else {
                    self.showSaveIndicator(false);
                    // TODO: Replace alert with custom notification system
                    alert('Chyba: ' + (response.data?.message || 'Neznámá chyba'));
                }
            })
            .fail(function(xhr, status, error) {
                self.showSaveIndicator(false);
                // TODO: Replace alert with custom notification system
                alert('AJAX chyba: ' + error);
            });
        },
        
        /**
         * Handle permission checkbox change
         * 
         * Triggered when user clicks a permission checkbox.
         * Updates visual indicator and saves change via AJAX.
         * 
         * @since 4.10.0
         * @param {Event} e - Change event
         * @return {void}
         */
        handlePermissionChange: function(e) {
            const $checkbox = $(e.target);
            const module = $checkbox.data('module');
            const action = $checkbox.data('action');
            const allowed = $checkbox.is(':checked');
            
            // Update visual indicator
            const $indicator = $checkbox.siblings('.toggle-indicator');
            $indicator.toggleClass('active', allowed);
            
            // Get current scope for this module
            const $row = $checkbox.closest('tr');
            const $scopeSelect = $row.find('.scope-select');
            const scope = $scopeSelect.val();
            
            // Save to server
            this.updatePermission(module, action, allowed, scope);
        },
        
        /**
         * Handle scope selector change
         * 
         * Triggered when user changes the scope dropdown for a module.
         * Updates ALL permissions for that module with new scope.
         * 
         * @since 4.10.0
         * @param {Event} e - Change event
         * @return {void}
         */
        handleScopeChange: function(e) {
            const $select = $(e.target);
            const module = $select.data('module');
            const scope = $select.val();
            
            const $row = $select.closest('tr');
            const actions = ['list', 'view', 'create', 'edit', 'delete'];
            
            // Update scope for ALL actions of this module
            actions.forEach(action => {
                const $checkbox = $row.find('.permission-checkbox[data-action="' + action + '"]');
                const allowed = $checkbox.is(':checked');
                
                this.updatePermission(module, action, allowed, scope);
            });
        },
        
        /**
         * Handle role selector change
         * 
         * Reloads page with new role parameter when user switches roles.
         * 
         * @since 4.10.0
         * @param {Event} e - Change event
         * @return {void}
         */
        handleRoleChange: function(e) {
            const role = $(e.target).val();
            window.location.href = this.homeUrl + '?role=' + role;
        },
        
        /**
         * Allow all permissions for current role
         * 
         * Bulk action to check all permission checkboxes.
         * Requires user confirmation.
         * 
         * @since 4.10.0
         * @param {Event} e - Click event
         * @return {void}
         */
        allowAll: function(e) {
            e.preventDefault();
            
            // TODO: Replace confirm with custom modal
            if (!confirm('Opravdu chcete povolit všechna oprávnění pro tuto roli?')) {
                return;
            }
            
            $('.permission-checkbox:not(:checked)').each(function() {
                $(this).prop('checked', true).trigger('change');
            });
        },
        
        /**
         * Deny all permissions for current role
         * 
         * Bulk action to uncheck all permission checkboxes.
         * Requires user confirmation.
         * 
         * @since 4.10.0
         * @param {Event} e - Click event
         * @return {void}
         */
        denyAll: function(e) {
            e.preventDefault();
            
            // TODO: Replace confirm with custom modal
            if (!confirm('Opravdu chcete zakázat všechna oprávnění pro tuto roli?')) {
                return;
            }
            
            $('.permission-checkbox:checked').each(function() {
                $(this).prop('checked', false).trigger('change');
            });
        },
        
        /**
         * Reset permissions to default values
         * 
         * Resets all permissions for current role to their default state.
         * Requires user confirmation and reloads page on success.
         * 
         * @since 4.10.0
         * @param {Event} e - Click event
         * @return {void}
         */
        resetToDefaults: function(e) {
            e.preventDefault();
            
            // TODO: Replace confirm with custom modal
            if (!confirm('Opravdu chcete resetovat oprávnění na výchozí hodnoty? Tato akce je nevratná.')) {
                return;
            }
            
            const self = this;
            const role = this.getCurrentRole();
            
            $.post(this.ajaxUrl, {
                action: 'saw_reset_permissions',
                nonce: this.nonce,
                role: role
            })
            .done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    // TODO: Replace alert with custom notification system
                    alert('Chyba při resetování: ' + (response.data?.message || 'Neznámá chyba'));
                }
            })
            .fail(function(xhr, status, error) {
                // TODO: Replace alert with custom notification system
                alert('Chyba při resetování: ' + error);
            });
        }
    };
    
    /**
     * Initialize on document ready
     * 
     * @since 4.10.0
     */
    $(document).ready(function() {
        PermissionsManager.init();
    });
    
})(jQuery);