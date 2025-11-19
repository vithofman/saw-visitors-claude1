/**
 * SAW Companies Module Scripts
 * 
 * Handles form validation and AJAX submission for nested create.
 * 
 * @package SAW_Visitors
 * @subpackage Modules/Companies
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    const SAW_Companies = {

        init: function () {
            if ($('.saw-company-form').length) {
                this.initForm();
            }
        },

        initForm: function () {
            const $form = $('.saw-company-form');
            const isNested = sawCompanies.isNested === '1';

            console.log('[Companies Form] Loaded');
            console.log('[Companies Form] Edit mode:', sawCompanies.isEdit);
            console.log('[Companies Form] Nested mode:', isNested);

            $form.on('submit', function (e) {
                if (isNested) {
                    e.preventDefault();
                    SAW_Companies.handleNestedSubmit($(this));
                } else {
                    if (!SAW_Companies.validateForm($(this))) {
                        e.preventDefault();
                    }
                }
            });
        },

        validateForm: function ($form) {
            const branchId = $form.find('#branch_id').val();
            const name = $form.find('#name').val().trim();
            const email = $form.find('#email').val().trim();
            const website = $form.find('#website').val().trim();

            // Branch validation
            if (!branchId) {
                alert('Vyberte prosím pobočku');
                $form.find('#branch_id').focus();
                return false;
            }

            // Name validation
            if (!name) {
                alert('Vyplňte prosím název firmy');
                $form.find('#name').focus();
                return false;
            }

            // Email validation
            if (email && !this.isValidEmail(email)) {
                alert('Zadejte prosím platný email');
                $form.find('#email').focus();
                return false;
            }

            // Website validation
            if (website && !this.isValidURL(website)) {
                alert('Zadejte prosím platnou webovou adresu (včetně https://)');
                $form.find('#website').focus();
                return false;
            }

            return true;
        },

        handleNestedSubmit: function ($form) {
            console.log('[Companies Form] NESTED MODE - AJAX submit triggered');

            if (!this.validateForm($form)) {
                return false;
            }

            $.ajax({
                url: sawCompanies.ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=saw_inline_create_companies&nonce=' + sawCompanies.nonce,
                success: function (response) {
                    console.log('[Companies Form] AJAX response:', response);

                    if (response.success) {
                        // Get target field from nested wrapper
                        const $wrapper = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
                        const targetField = $wrapper.attr('data-target-field');

                        console.log('[Companies Form] Success! Target field:', targetField);

                        // Call global handler
                        if (window.SAWSelectCreate) {
                            window.SAWSelectCreate.handleInlineSuccess(response.data, targetField);
                        } else {
                            console.error('[Companies Form] SAWSelectCreate not available!');
                            alert('Firma byla vytvořena, ale nepodařilo se aktualizovat formulář');
                        }
                    } else {
                        alert(response.data?.message || 'Chyba při ukládání firmy');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[Companies Form] AJAX error:', error);
                    alert('Chyba při komunikaci se serverem');
                }
            });
        },

        isValidEmail: function (email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        isValidURL: function (url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        }
    };

    $(document).ready(function () {
        SAW_Companies.init();
    });

})(jQuery);
