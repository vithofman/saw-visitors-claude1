(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        $('.saw-branch-active-checkbox').on('change', function() {
            const branchId = $(this).data('branch-id');
            const isActive = $(this).is(':checked');
            
            const $defaultCheckbox = $(`.saw-branch-default-checkbox[data-branch-id="${branchId}"]`);
            const $orderInput = $(`input[name="branches[${branchId}][display_order]"]`);
            
            if (isActive) {
                $defaultCheckbox.prop('disabled', false);
                $orderInput.prop('disabled', false);
            } else {
                $defaultCheckbox.prop('checked', false).prop('disabled', true);
                $orderInput.val(0).prop('disabled', true);
            }
        });
        
        $('.saw-branch-default-checkbox').on('change', function() {
            if ($(this).is(':checked')) {
                const currentBranchId = $(this).data('branch-id');
                
                $('.saw-branch-default-checkbox').not(this).each(function() {
                    const otherBranchId = $(this).data('branch-id');
                    $(this).prop('checked', false);
                });
            }
        });
        
        $('#language_code').on('change', function() {
            const code = $(this).val();
            const languageNames = {
                'cs': 'Čeština',
                'en': 'English',
                'sk': 'Slovenčina',
                'de': 'Deutsch',
                'pl': 'Polski',
                'uk': 'Українська',
                'ru': 'Русский'
            };
            const flags = {
                'cs': '🇨🇿',
                'en': '🇬🇧',
                'sk': '🇸🇰',
                'de': '🇩🇪',
                'pl': '🇵🇱',
                'uk': '🇺🇦',
                'ru': '🇷🇺'
            };
            
            if (languageNames[code] && !$('#language_name').val()) {
                $('#language_name').val(languageNames[code]);
            }
            
            if (flags[code] && !$('#flag_emoji').val()) {
                $('#flag_emoji').val(flags[code]);
            }
        });
        
        $('.saw-language-form').on('submit', function(e) {
            const name = $('#language_name').val().trim();
            const code = $('#language_code').val();
            const flag = $('#flag_emoji').val().trim();
            
            if (!name || !code || !flag) {
                e.preventDefault();
                alert('Vyplňte všechna povinná pole!');
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
        
        $('input[name^="branches"][name$="[display_order]"]').on('blur', function() {
            let value = parseInt($(this).val());
            
            if (isNaN(value) || value < 0) {
                $(this).val(0);
            }
        });
        
    });
    
})(jQuery);
