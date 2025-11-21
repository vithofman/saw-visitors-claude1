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

    // Expose globally for use by global inline create handler
    window.SAW_Companies = window.SAW_Companies || {

        init: function () {
            const $forms = $('.saw-company-form');
            console.log('[Companies] Init called, found forms:', $forms.length);
            if ($forms.length) {
                $forms.each((index, form) => {
                    console.log('[Companies] Initializing form', index, form);
                    this.initForm($(form));
                });
            }
        },

        initForm: function ($form) {
            // Simplified: Global handler in forms.js will catch submit events
            // This method is kept for backward compatibility and potential future use
            // Skip if form already initialized
            if ($form && $form.length && !$form.data('saw-initialized')) {
                $form.data('saw-initialized', true);
                console.log('[Companies Form] Form initialized');
            }
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
            console.log('[Companies] handleNestedSubmit called by global handler');
            
            // Validate form before submission
            if (!this.validateForm($form)) {
                return false;
            }

            // Get nonce and AJAX URL from sawGlobal (always available)
            const nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : '';
            const ajaxurl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
                ? window.sawGlobal.ajaxurl 
                : '/wp-admin/admin-ajax.php';
            
            if (!nonce) {
                console.error('[Companies] No nonce available');
                alert('Chyba: Nelze ověřit požadavek. Zkuste obnovit stránku.');
                return false;
            }
            
            console.log('[Companies] Submitting via AJAX');
            
            const formData = $form.serialize() + '&action=saw_inline_create_companies&nonce=' + encodeURIComponent(nonce);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('[Companies] AJAX response:', response);

                    if (response.success) {
                        // Get target field from nested wrapper
                        const $wrapper = $('.saw-sidebar-wrapper[data-is-nested="1"]').last();
                        const targetField = $wrapper.attr('data-target-field');

                        console.log('[Companies] Success! Target field:', targetField);

                        // Call global handler to update select and close nested sidebar
                        if (window.SAWSelectCreate && window.SAWSelectCreate.handleInlineSuccess) {
                            window.SAWSelectCreate.handleInlineSuccess(response.data, targetField);
                        } else {
                            console.error('[Companies] SAWSelectCreate not available!');
                            alert('Firma byla vytvořena, ale nepodařilo se aktualizovat formulář');
                        }
                    } else {
                        alert(response.data?.message || 'Chyba při ukládání firmy');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[Companies] AJAX error:', error, 'Status:', xhr.status);
                    
                    if (xhr.status === 403) {
                        alert('Chyba: Oprávnění zamítnuto. Možná problém s nonce. Zkuste obnovit stránku.');
                    } else if (xhr.status === 0) {
                        alert('Chyba: Nelze se připojit k serveru. Zkontrolujte připojení.');
                    } else {
                        const errorMsg = xhr.responseJSON?.data?.message || error;
                        alert('Chyba při komunikaci se serverem: ' + errorMsg);
                    }
                }
            });
            
            return true;
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
    
    // Also expose as const for internal use
    const SAW_Companies = window.SAW_Companies;

    // Note: Submit handling is now done by global handler in forms.js
    // The global handler will detect _ajax_inline_create and call handleNestedSubmit()
    // This keeps the module JS focused on module-specific logic
    
    // Initialize on document ready
    $(document).ready(function () {
        console.log('[Companies] Document ready, initializing');
        SAW_Companies.init();
    });
    
    // Also initialize immediately if DOM is already ready (for dynamically loaded content)
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        console.log('[Companies] DOM already ready, initializing immediately');
        setTimeout(() => SAW_Companies.init(), 0);
    }
    
    // Force initialization on window load as well
    $(window).on('load', function() {
        console.log('[Companies] Window loaded, re-initializing');
        $('.saw-company-form').removeData('saw-initialized');
        SAW_Companies.init();
    });

    // Re-initialize when new content is loaded via AJAX (e.g., nested sidebar)
    $(document).on('saw:page-loaded', function () {
        console.log('[Companies] saw:page-loaded event triggered, re-initializing');
        // Remove initialization flags to allow re-initialization
        $('.saw-company-form').removeData('saw-initialized');
        // Small delay to ensure DOM is ready
        setTimeout(() => {
            SAW_Companies.init();
        }, 50);
    });
    
    
    // Re-initialize when new content is loaded via AJAX
    // The global handler in forms.js will catch submit events, but we still need to initialize forms
    $(document).on('saw:page-loaded', function () {
        console.log('[Companies] saw:page-loaded event triggered, re-initializing');
        $('.saw-company-form').removeData('saw-initialized');
        setTimeout(() => SAW_Companies.init(), 50);
    });

})(jQuery);
