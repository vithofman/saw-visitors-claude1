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

    // Timeout for form data (5 minutes)
    const FORM_DATA_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds

    // Excluded form selectors (forms that should NOT use autosave)
    const EXCLUDED_FORM_SELECTORS = [
        '.saw-invitation-form',
        '.saw-terminal-form',
        '[data-no-autosave="true"]',
        '#loginform',
        '#search-form'
    ];

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
     * Get full URL including query string
     * 
     * @return {string} Full URL (pathname + search)
     */
    function getFullUrl() {
        return window.location.pathname + window.location.search;
    }

    /**
     * Check if form should be excluded from autosave
     * 
     * @param {jQuery} $form - Form element
     * @return {boolean} True if form should be excluded
     */
    function isExcludedForm($form) {
        // Check selectors
        for (let i = 0; i < EXCLUDED_FORM_SELECTORS.length; i++) {
            if ($form.is(EXCLUDED_FORM_SELECTORS[i])) {
                console.log('[FormAutosave] Form excluded by selector:', EXCLUDED_FORM_SELECTORS[i]);
                return true;
            }
        }

        // Check if form is inside excluded containers
        if ($form.closest('.saw-invitation-container, .saw-terminal-container').length > 0) {
            console.log('[FormAutosave] Form excluded - inside invitation/terminal container');
            return true;
        }

        // Check data attribute
        if ($form.attr('data-no-autosave') === 'true') {
            console.log('[FormAutosave] Form excluded by data-no-autosave attribute');
            return true;
        }

        return false;
    }

    /**
     * Check if form data is empty (ignoring nonce and action fields)
     * 
     * @param {array} data - Serialized form data
     * @return {boolean} True if data is empty
     */
    function isEmptyFormData(data) {
        if (!data || !Array.isArray(data) || data.length === 0) {
            return true;
        }

        // Filter out nonce and action fields
        const userFields = data.filter(function(item) {
            const name = item.name || '';
            return name.indexOf('_wpnonce') === -1 && 
                   name.indexOf('_wp_http_referer') === -1 &&
                   name !== 'action' &&
                   name !== 'invitation_action';
        });

        // Check if any user field has a value
        for (let i = 0; i < userFields.length; i++) {
            const value = userFields[i].value || '';
            if (value.trim() !== '') {
                return false;
            }
        }

        return true;
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
            // Include query string for unique ID
            return id + '_' + getFullUrl().replace(/[^a-zA-Z0-9]/g, '_');
        }

        // Try name
        const name = $form.attr('name');
        if (name) {
            return name + '_' + getFullUrl().replace(/[^a-zA-Z0-9]/g, '_');
        }

        // Try action URL
        const action = $form.attr('action');
        if (action) {
            return action.replace(/[^a-zA-Z0-9]/g, '_') + '_' + getFullUrl().replace(/[^a-zA-Z0-9]/g, '_');
        }

        // Fallback: use class or generate from URL
        const classes = $form.attr('class');
        if (classes) {
            const classMatch = classes.match(/saw-[\w-]+-form/);
            if (classMatch) {
                return classMatch[0] + '_' + getFullUrl().replace(/[^a-zA-Z0-9]/g, '_');
            }
        }

        // Last resort: use current URL (with query string)
        return 'form_' + getFullUrl().replace(/[^a-zA-Z0-9]/g, '_');
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
     * @param {number} timestamp - Timestamp when data was saved
     * @return {void}
     */
    function showRestorePrompt(onRestore, onDiscard, timestamp) {
        let message = 'Máte neuložená data z předchozího sezení. Chcete je obnovit?';
        
        // Add time info if available
        if (timestamp) {
            const minutesAgo = Math.floor((Date.now() - timestamp) / 60000);
            if (minutesAgo > 0) {
                message += ' (uloženo před ' + minutesAgo + ' minutami)';
            }
        }
        
        if (confirm(message)) {
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

        // Check if form should be excluded
        if (isExcludedForm($form)) {
            console.log('[FormAutosave] Form excluded from autosave:', $form.attr('id') || $form.attr('class'));
            return;
        }

        const formId = getFormId($form);

        // Check if form data exists (with validation)
        if (window.stateManager && window.stateManager.hasFormData(formId)) {
            // Get saved data with timestamp for prompt
            let savedTimestamp = null;
            try {
                const savedDataStr = sessionStorage.getItem('saw_form_' + formId);
                if (savedDataStr) {
                    const parsed = JSON.parse(savedDataStr);
                    savedTimestamp = parsed.timestamp || null;
                }
            } catch (e) {
                // Ignore
            }
            
            const savedData = window.stateManager.restoreFormData(formId);
            
            if (savedData && !isEmptyFormData(savedData)) {
                showRestorePrompt(
                    function() {
                        // Restore data
                        restoreFormData($form, savedData);
                        console.log('[FormAutosave] ✅ Form data restored for:', formId);
                    },
                    function() {
                        // Discard data
                        if (window.stateManager) {
                            window.stateManager.clearFormData(formId);
                        }
                        console.log('[FormAutosave] 🗑️ Form data discarded for:', formId);
                    },
                    savedTimestamp
                );
            } else {
                // Data is empty or invalid, clear it
                if (window.stateManager) {
                    window.stateManager.clearFormData(formId);
                }
            }
        }

        // Debounced save function (2 seconds after last input)
        const saveFormData = debounce(function() {
            if (!window.stateManager) {
                return;
            }

            const formData = serializeFormData($form);
            
            // Don't save if data is empty
            if (isEmptyFormData(formData)) {
                console.log('[FormAutosave] Skipping save - form data is empty');
                return;
            }
            
            window.stateManager.saveFormData(formId, formData);
            
            // Show indicator (only once per session to avoid spam)
            if (!$form.data('autosave-indicator-shown')) {
                showAutosaveIndicator();
                $form.data('autosave-indicator-shown', true);
            }

            console.log('[FormAutosave] 💾 Form data auto-saved for:', formId);
        }, 2000);

        // Listen to form changes
        $form.on('input change', 'input, textarea, select', saveFormData);

        // Clear data on submit (BEFORE submit, not after)
        $form.on('submit', function() {
            // Clear immediately (redirect may be faster than setTimeout)
            if (window.stateManager) {
                window.stateManager.clearFormData(formId);
                console.log('[FormAutosave] 🗑️ Form data cleared on submit for:', formId);
            }
        });
    }

    /**
     * Initialize all forms on page
     * 
     * @return {void}
     */
    function initAllForms() {
        // Cleanup expired form data first
        if (window.stateManager && typeof window.stateManager.cleanupExpiredFormData === 'function') {
            window.stateManager.cleanupExpiredFormData();
        }

        // Find all forms (with .saw-form class or any form with ID)
        const $forms = $('form.saw-form, form[id]');

        $forms.each(function() {
            const $form = $(this);
            initFormAutosave($form);
        });

        console.log('[FormAutosave] ✅ Form autosave initialized for', $forms.length, 'form(s)');
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

