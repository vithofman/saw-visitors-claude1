/**
 * SAW Form Autosave
 * 
 * Automatic form data saving to sessionStorage.
 * Restores data when user returns to form.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Debounce function
     * 
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @return {Function} Debounced function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Get form identifier
     * 
     * @param {jQuery} $form - Form element
     * @return {string} Form identifier
     */
    function getFormId($form) {
        // Try ID first
        const id = $form.attr('id');
        if (id) {
            return id;
        }

        // Try name
        const name = $form.attr('name');
        if (name) {
            return name;
        }

        // Try action URL
        const action = $form.attr('action');
        if (action) {
            return action.replace(/[^a-zA-Z0-9]/g, '_');
        }

        // Fallback: use class or generate from URL
        const classes = $form.attr('class');
        if (classes) {
            const classMatch = classes.match(/saw-[\w-]+-form/);
            if (classMatch) {
                return classMatch[0];
            }
        }

        // Last resort: use current URL
        return 'form_' + window.location.pathname.replace(/[^a-zA-Z0-9]/g, '_');
    }

    /**
     * Serialize form data
     * 
     * @param {jQuery} $form - Form element
     * @return {array} Serialized form data
     */
    function serializeFormData($form) {
        return $form.serializeArray();
    }

    /**
     * Restore form data
     * 
     * @param {jQuery} $form - Form element
     * @param {array} data - Form data to restore
     * @return {void}
     */
    function restoreFormData($form, data) {
        if (!data || !Array.isArray(data)) {
            return;
        }

        data.forEach(function(item) {
            const name = item.name;
            const value = item.value;

            // Handle different input types
            const $field = $form.find('[name="' + name + '"]');

            if ($field.length) {
                const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

                switch (fieldType) {
                    case 'checkbox':
                        $field.prop('checked', value === '1' || value === 'on' || value === true);
                        break;
                    case 'radio':
                        $field.filter('[value="' + value + '"]').prop('checked', true);
                        break;
                    case 'select-one':
                    case 'select':
                        $field.val(value);
                        break;
                    case 'select-multiple':
                        const values = Array.isArray(value) ? value : [value];
                        $field.val(values);
                        break;
                    default:
                        $field.val(value);
                        break;
                }

                // Trigger change event to update any dependent fields
                $field.trigger('change');
            }
        });
    }

    /**
     * Show autosave indicator
     * 
     * @return {void}
     */
    function showAutosaveIndicator() {
        if (typeof window.sawShowToast === 'function') {
            window.sawShowToast('Automaticky uloženo', 'success');
        }
    }

    /**
     * Show restore prompt
     * 
     * @param {Function} onRestore - Callback when user chooses to restore
     * @param {Function} onDiscard - Callback when user chooses to discard
     * @return {void}
     */
    function showRestorePrompt(onRestore, onDiscard) {
        if (confirm('Máte neuložená data z předchozího sezení. Chcete je obnovit?')) {
            if (onRestore) {
                onRestore();
            }
        } else {
            if (onDiscard) {
                onDiscard();
            }
        }
    }

    /**
     * Initialize form autosave for a single form
     * 
     * @param {jQuery} $form - Form element
     * @return {void}
     */
    function initFormAutosave($form) {
        if (!$form.length) {
            return;
        }

        const formId = getFormId($form);

        // Check if form data exists
        if (window.stateManager && window.stateManager.hasFormData(formId)) {
            const savedData = window.stateManager.restoreFormData(formId);
            
            if (savedData) {
                showRestorePrompt(
                    function() {
                        // Restore data
                        restoreFormData($form, savedData);
                        console.log('✅ Form data restored for:', formId);
                    },
                    function() {
                        // Discard data
                        if (window.stateManager) {
                            window.stateManager.clearFormData(formId);
                        }
                        console.log('🗑️ Form data discarded for:', formId);
                    }
                );
            }
        }

        // Debounced save function (2 seconds after last input)
        const saveFormData = debounce(function() {
            if (!window.stateManager) {
                return;
            }

            const formData = serializeFormData($form);
            window.stateManager.saveFormData(formId, formData);
            
            // Show indicator (only once per session to avoid spam)
            if (!$form.data('autosave-indicator-shown')) {
                showAutosaveIndicator();
                $form.data('autosave-indicator-shown', true);
            }

            console.log('💾 Form data auto-saved for:', formId);
        }, 2000);

        // Listen to form changes
        $form.on('input change', 'input, textarea, select', saveFormData);

        // Clear data on successful submit
        $form.on('submit', function() {
            // Wait a bit to ensure form was actually submitted
            setTimeout(function() {
                if (window.stateManager) {
                    window.stateManager.clearFormData(formId);
                    console.log('🗑️ Form data cleared after submit for:', formId);
                }
            }, 500);
        });
    }

    /**
     * Initialize all forms on page
     * 
     * @return {void}
     */
    function initAllForms() {
        // Find all forms (with .saw-form class or any form with ID)
        const $forms = $('form.saw-form, form[id]');

        $forms.each(function() {
            const $form = $(this);
            initFormAutosave($form);
        });

        console.log('✅ Form autosave initialized for', $forms.length, 'form(s)');
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        // Wait a bit for forms to be fully rendered
        setTimeout(function() {
            initAllForms();
        }, 100);

        // Also initialize forms loaded dynamically (e.g., via AJAX)
        $(document).on('saw:page-loaded', function() {
            setTimeout(function() {
                initAllForms();
            }, 100);
        });
    });

    // Export for manual initialization if needed
    if (typeof window.SAW === 'undefined') {
        window.SAW = {};
    }
    window.SAW.FormAutosave = {
        init: initAllForms,
        initForm: initFormAutosave
    };

})(jQuery);

