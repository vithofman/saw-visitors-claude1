/**
 * Account Types JavaScript
 *
 * @package SAW_Visitors
 */

(function($) {
    'use strict';
    
    const AccountTypes = {
        
        init: function() {
            console.log('💳 Account Types: Initializing...');
            this.bindEvents();
            this.syncColorInputs();
            console.log('✅ Account Types: Initialized');
        },
        
        bindEvents: function() {
            $('#add-account-type-btn').off('click').on('click', function(e) {
                e.preventDefault();
                AccountTypes.openFormModal();
            });
            
            $(document).off('click', '.saw-edit-account-type').on('click', '.saw-edit-account-type', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                AccountTypes.openFormModal(id);
            });
            
            $(document).off('click', '.saw-table-action-view').on('click', '.saw-table-action-view', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                AccountTypes.showDetail(id);
            });
            
            $(document).off('click', '.saw-table-action-edit').on('click', '.saw-table-action-edit', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                AccountTypes.openFormModal(id);
            });
            
            $(document).off('submit', '#account-type-form').on('submit', '#account-type-form', function(e) {
                e.preventDefault();
                AccountTypes.submitForm($(this));
            });
            
            $(document).off('input', '#color').on('input', '#color', function() {
                $('#color-text').val($(this).val().toUpperCase());
            });
            
            $(document).off('input', '#color-text').on('input', '#color-text', function() {
                const color = $(this).val();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    $('#color').val(color);
                }
            });
            
            $(document).off('input', '#display_name').on('input', '#display_name', function() {
                const $nameInput = $('#name');
                if (!$nameInput.val() || $nameInput.data('manual-edit') !== true) {
                    const slug = AccountTypes.slugify($(this).val());
                    $nameInput.val(slug);
                }
            });
            
            $(document).off('input', '#name').on('input', '#name', function() {
                $(this).data('manual-edit', true);
            });
        },
        
        openFormModal: function(id) {
            const $modal = $('#account-type-form-modal');
            const $form = $('#account-type-form');
            const $title = $('#form-modal-title');
            
            $form[0].reset();
            $form.find('input[name="id"]').val('');
            $form.find('.saw-form-message').hide();
            $('#name').removeData('manual-edit');
            
            if (id) {
                $title.text('Edit Account Type');
                AccountTypes.loadAccountType(id);
            } else {
                $title.text('Add Account Type');
                $('#color').val('#6b7280');
                $('#color-text').val('#6b7280');
            }
            
            $('#account-type-detail-modal').hide();
            
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
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Ukládám...');
            
            const formData = $form.serialize() + '&action=saw_account_type_save&nonce=' + sawAccountTypesData.nonce;
            
            $.ajax({
                url: sawAccountTypesData.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        if (window.SAWNotifications) {
                            window.SAWNotifications.success(response.data.message);
                        }
                        
                        setTimeout(function() {
                            window.location.href = '/admin/settings/account-types/';
                        }, 500);
                    } else {
                        if (window.SAWNotifications) {
                            window.SAWNotifications.error(response.data.message);
                        } else {
                            alert('Chyba: ' + response.data.message);
                        }
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    if (window.SAWNotifications) {
                        window.SAWNotifications.error('Nastala chyba při ukládání');
                    } else {
                        alert('Nastala chyba při ukládání');
                    }
                    $submitBtn.prop('disabled', false).text(originalText);
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
    
    $(document).ready(function() {
        AccountTypes.init();
    });
    
    $(document).on('saw:scripts-reinitialized', function() {
        console.log('🔄 Account Types: Reinitializing after AJAX navigation...');
        AccountTypes.init();
    });
    
    window.AccountTypes = AccountTypes;
    
})(jQuery);