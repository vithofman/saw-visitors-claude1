(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // AUTO-FILL AND PREVIEW UPDATE
        // ================================================
        $('#language_code').on('change', function() {
            const $selected = $(this).find('option:selected');
            const code = $selected.val();
            const name = $selected.data('name');
            const flag = $selected.data('flag');
            
            if (code && name && flag) {
                // Update hidden fields
                $('#language_name').val(name);
                $('#flag_emoji').val(flag);
                
                // Update preview
                updatePreview(flag, name, code);
            } else {
                // Clear preview
                $('#flag-preview').empty();
            }
        });
        
        // ================================================
        // UPDATE PREVIEW
        // ================================================
        function updatePreview(flag, name, code) {
            const $preview = $('#flag-preview');
            
            const html = `
                <span class="saw-flag-emoji">${flag}</span>
                <span class="saw-flag-name">${name}</span>
                <span class="saw-flag-code">${code.toUpperCase()}</span>
            `;
            
            $preview.html(html);
        }
        
        // Initialize preview on load if editing
        if ($('#language_code').val()) {
            const $selected = $('#language_code').find('option:selected');
            const code = $selected.val();
            const name = $('#language_name').val() || $selected.data('name');
            const flag = $('#flag_emoji').val() || $selected.data('flag');
            
            if (code && name && flag) {
                updatePreview(flag, name, code);
            }
        }
        
        // ================================================
        // SELECT ALL BRANCHES
        // ================================================
        $('#select-all-branches').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.saw-branch-active-checkbox').prop('checked', isChecked).trigger('change');
        });
        
        // Update "select all" based on individual checkboxes
        $('.saw-branch-active-checkbox').on('change', function() {
            const total = $('.saw-branch-active-checkbox').length;
            const checked = $('.saw-branch-active-checkbox:checked').length;
            $('#select-all-branches').prop('checked', checked === total);
        });
        
        // ================================================
        // BRANCH ACTIVE TOGGLE
        // ================================================
        $('.saw-branch-active-checkbox').on('change', function() {
            const branchId = $(this).data('branch-id');
            const isActive = $(this).is(':checked');
            
            const $defaultRadio = $(`.saw-branch-default-radio[data-branch-id="${branchId}"]`);
            const $orderInput = $(`input[name="branches[${branchId}][display_order]"]`);
            
            if (isActive) {
                $defaultRadio.prop('disabled', false);
                $orderInput.prop('disabled', false);
            } else {
                $defaultRadio.prop('checked', false).prop('disabled', true);
                $(`.saw-branch-default-hidden[data-branch-id="${branchId}"]`).val('0');
                $orderInput.val(0).prop('disabled', true);
            }
        });
        
        // ================================================
        // DEFAULT BRANCH RADIO
        // ================================================
        $('.saw-branch-default-radio').on('change', function() {
            const branchId = $(this).data('branch-id');
            
            $('.saw-branch-default-hidden').val('0');
            $(`.saw-branch-default-hidden[data-branch-id="${branchId}"]`).val('1');
        });
        
        // ================================================
        // ORDER INPUT VALIDATION
        // ================================================
        $('.saw-order-input').on('blur', function() {
            let value = parseInt($(this).val());
            
            if (isNaN(value) || value < 0) {
                $(this).val(0);
            }
        });
        
        // ================================================
        // FORM VALIDATION
        // ================================================
        $('.saw-language-form').on('submit', function(e) {
            const code = $('#language_code').val();
            
            if (!code) {
                e.preventDefault();
                alert('Vyberte jazyk!');
                return false;
            }
            
            const hasActiveBranches = $('.saw-branch-active-checkbox:checked').length > 0;
            
            if (!hasActiveBranches) {
                const confirmed = confirm('Jazyk není aktivován pro žádnou pobočku. Chcete přesto pokračovat?');
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
        
    });
    
})(jQuery);