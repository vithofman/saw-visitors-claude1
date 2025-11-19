/**
 * Training Languages Module Scripts
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/TrainingLanguages
 * @version     3.9.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ================================================
        // AUTO-FILL AND PREVIEW UPDATE
        // ================================================
        $('#language_code').on('change', function () {
            const $selected = $(this).find('option:selected');
            const code = $selected.val();
            const name = $selected.data('name');
            const flag = $selected.data('flag');

            if (code && name && flag) {
                // Update hidden fields
                $('#language_name').val(name);
                $('#flag_emoji').val(flag);

                // Update preview
                const html = `
                    <span class="saw-flag-emoji">${flag}</span>
                    <span class="saw-flag-name">${name}</span>
                    <span class="saw-flag-code">${code.toUpperCase()}</span>
                `;
                $('#flag-preview').html(html);
            } else {
                // Clear preview
                $('#flag-preview').empty();
            }
        });

        // Initialize preview on load if editing
        if ($('#language_code').val()) {
            const $selected = $('#language_code').find('option:selected');
            const code = $selected.val();
            const name = $('#language_name').val() || $selected.data('name');
            const flag = $('#flag_emoji').val() || $selected.data('flag');

            if (code && name && flag) {
                const html = `
                    <span class="saw-flag-emoji">${flag}</span>
                    <span class="saw-flag-name">${name}</span>
                    <span class="saw-flag-code">${code.toUpperCase()}</span>
                `;
                $('#flag-preview').html(html);
            }
        }

        // ================================================
        // BRANCH ACTIVE TOGGLE
        // ================================================
        $('.saw-branch-active-checkbox').on('change', function () {
            const branchId = $(this).data('branch-id');
            const isActive = $(this).is(':checked');

            // Update hidden input - CRITICAL FIX for form submission
            $(`.saw-branch-active-hidden[data-branch-id="${branchId}"]`).val(isActive ? '1' : '0');

            const $row = $(this).closest('.saw-branch-glossy-row');
            const $defaultRadio = $(`.saw-branch-default-radio[data-branch-id="${branchId}"]`);
            const $orderInput = $(`input[name="branches[${branchId}][display_order]"]`);

            if (isActive) {
                $row.addClass('is-active');
                $defaultRadio.prop('disabled', false);
                $orderInput.prop('disabled', false);
            } else {
                $row.removeClass('is-active');
                $defaultRadio.prop('checked', false).prop('disabled', true);
                $(`.saw-branch-default-hidden[data-branch-id="${branchId}"]`).val('0');
                $(`.saw-radio-pill-compact[data-branch-id="${branchId}"]`).removeClass('is-selected');
                $orderInput.val(0).prop('disabled', true);
            }
        });

        // ================================================
        // DEFAULT BRANCH RADIO
        // ================================================
        $('.saw-branch-default-radio').on('change', function () {
            $('.saw-radio-pill-compact').removeClass('is-selected');
            $('.saw-branch-default-hidden').val('0');

            const branchId = $(this).data('branch-id');
            $(this).closest('.saw-radio-pill-compact').addClass('is-selected');
            $(`.saw-branch-default-hidden[data-branch-id="${branchId}"]`).val('1');
        });

        // ================================================
        // SELECT ALL BRANCHES
        // ================================================
        $('#select-all-branches').on('change', function () {
            $('.saw-branch-active-checkbox').prop('checked', $(this).is(':checked')).trigger('change');
        });

        // ================================================
        // FORM VALIDATION
        // ================================================
        $('.saw-language-form').on('submit', function (e) {
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
