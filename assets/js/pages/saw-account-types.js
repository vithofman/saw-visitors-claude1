/**
 * Account Types JavaScript
 *
 * @package SAW_Visitors
 */

(function($) {
    'use strict';
    
    const AccountTypes = {
        
        init: function() {
            this.bindEvents();
            this.syncColorInputs();
        },
        
        bindEvents: function() {
            // Add new account type button
            $('#add-account-type-btn').on('click', function(e) {
                e.preventDefault();
                AccountTypes.openFormModal();
            });
            
            // Edit button in detail modal
            $(document).on('click', '.saw-edit-account-type', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                AccountTypes.openFormModal(id);
            });
            
            // View button in table
            $(document).on('click', '.saw-table-action-view', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                AccountTypes.showDetail(id);
            });
            
            // Edit button in table
            $(document).on('click', '.saw-table-action-edit', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                AccountTypes.openFormModal(id);
            });
            
            // Form submit
            $(document).on('submit', '#account-type-form', function(e) {
                e.preventDefault();
                AccountTypes.submitForm($(this));
            });
            
            // Color picker sync
            $(document).on('input', '#color', function() {
                $('#color-text').val($(this).val().toUpperCase());
            });
            
            $(document).on('input', '#color-text', function() {
                const color = $(this).val();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    $('#color').val(color);
                }
            });
            
            // Name to slug conversion
            $(document).on('input', '#display_name', function() {
                const $nameInput = $('#name');
                if (!$nameInput.val() || $nameInput.data('manual-edit') !== true) {
                    const slug = AccountTypes.slugify($(this).val());
                    $nameInput.val(slug);
                }
            });
            
            $(document).on('input', '#name', function() {
                $(this).data('manual-edit', true);
            });
        },
        
        openFormModal: function(id) {
            const $modal = $('#account-type-form-modal');
            const $form = $('#account-type-form');
            const $title = $('#form-modal-title');
            
            // Reset form
            $form[0].reset();
            $form.find('input[name="id"]').val('');
            $form.find('.saw-form-message').hide();
            $('#name').removeData('manual-edit');
            
            if (id) {
                // Edit mode - load data
                $title.text('Edit Account Type');
                AccountTypes.loadAccountType(id);
            } else {
                // Add mode
                $title.text('Add Account Type');
                $('#color').val('#6b7280');
                $('#color-text').val('#6b7280');
            }
            
            // Close detail modal if open
            $('#account-type-detail-modal').hide();
            
            // Open form modal
            $modal.fadeIn(200);
        },
        
        loadAccountType: function(id) {
            $.ajax({
                url: sawAccountTypesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saw_account_types_list',
                    nonce: sawAccountTypesData.nonce,
                    page: 1,
                    per_page: 1,
                    search: '',
                    filters: { id: id }
                },
                success: function(response) {
                    if (response.success && response.data.items.length > 0) {
                        const accountType = response.data.items[0];
                        AccountTypes.populateForm(accountType);
                    }
                },
                error: function() {
                    alert('Failed to load account type data');
                }
            });
        },
        
        populateForm: function(data) {
            $('#account-type-form input[name="id"]').val(data.id);
            $('#display_name').val(data.display_name);
            $('#name').val(data.name).data('manual-edit', true);
            $('#color').val(data.color);
            $('#color-text').val(data.color);
            $('#price').val(data.price);
            $('#features').val(data.features || '');
            $('#sort_order').val(data.sort_order);
            $('#is_active').prop('checked', data.is_active == 1);
        },
        
        submitForm: function($form) {
            const $submitBtn = $form.find('button[type="submit"]');
            const $message = $form.find('.saw-form-message');
            
            $submitBtn.prop('disabled', true).text('Saving...');
            $message.hide();
            
            $.ajax({
                url: sawAccountTypesData.ajaxUrl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        $message
                            .removeClass('error')
                            .addClass('success')
                            .text(response.data.message)
                            .fadeIn();
                        
                        setTimeout(function() {
                            $('#account-type-form-modal').fadeOut(200);
                            if (typeof window.sawAdminTable !== 'undefined') {
                                window.sawAdminTable.reload();
                            }
                        }, 1000);
                    } else {
                        $message
                            .removeClass('success')
                            .addClass('error')
                            .text(response.data.message)
                            .fadeIn();
                    }
                },
                error: function() {
                    $message
                        .removeClass('success')
                        .addClass('error')
                        .text('An error occurred. Please try again.')
                        .fadeIn();
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text($form.find('input[name="id"]').val() ? 'Update Account Type' : 'Add Account Type');
                }
            });
        },
        
        showDetail: function(id) {
            $.ajax({
                url: sawAccountTypesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saw_account_types_list',
                    nonce: sawAccountTypesData.nonce,
                    page: 1,
                    per_page: 1,
                    search: '',
                    filters: { id: id }
                },
                success: function(response) {
                    if (response.success && response.data.items.length > 0) {
                        AccountTypes.populateDetailModal(response.data.items[0]);
                    }
                }
            });
        },
        
        populateDetailModal: function(data) {
            const $modal = $('#account-type-detail-modal');
            
            $modal.find('[data-field="id"]').text(data.id);
            $modal.find('[data-field="display_name"]').text(data.display_name);
            $modal.find('[data-field="name"]').text(data.name);
            
            $modal.find('.saw-color-preview').css('background-color', data.color);
            $modal.find('[data-field="color"]').not('.saw-color-preview').text(data.color);
            
            $modal.find('[data-field="price"]').text('$' + parseFloat(data.price).toFixed(2));
            $modal.find('[data-field="sort_order"]').text(data.sort_order);
            $modal.find('[data-field="features"]').text(data.features || 'N/A');
            
            const statusHtml = data.is_active == 1 
                ? '<span class="saw-status-badge saw-status-active">Active</span>'
                : '<span class="saw-status-badge saw-status-inactive">Inactive</span>';
            $modal.find('[data-field="is_active"]').html(statusHtml);
            
            $modal.find('[data-field="created_at"]').text(AccountTypes.formatDateTime(data.created_at));
            $modal.find('[data-field="updated_at"]').text(data.updated_at ? AccountTypes.formatDateTime(data.updated_at) : 'N/A');
            
            $modal.find('.saw-edit-account-type').data('id', data.id);
            
            $modal.fadeIn(200);
        },
        
        syncColorInputs: function() {
            const $colorPicker = $('#color');
            const $colorText = $('#color-text');
            
            if ($colorPicker.length && $colorText.length) {
                $colorText.val($colorPicker.val().toUpperCase());
            }
        },
        
        slugify: function(text) {
            return text
                .toString()
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '_')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '_')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        },
        
        formatDateTime: function(datetime) {
            const date = new Date(datetime);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        AccountTypes.init();
    });
    
})(jQuery);
