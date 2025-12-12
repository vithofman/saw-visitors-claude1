/**
 * SAW Visitors - Access Credentials Management
 *
 * Handles PIN code and Invitation Token management UI in visit detail modal.
 * Provides functionality for viewing, copying, extending, and managing
 * access credentials for planned visits.
 *
 * @package    SAW_Visitors
 * @subpackage Admin/JS
 * @since      3.6.0
 * @author     SAW Development Team
 *
 * Dependencies:
 * - jQuery
 * - sawGlobal (WordPress localized script data)
 *
 * Usage:
 * - SAW_Access.toggle(visitId)      - Toggle expanded/collapsed view
 * - SAW_Access.copy(text, button)   - Copy text to clipboard
 * - SAW_Access.extend(visitId, type, hours) - Extend expiration
 * - SAW_Access.generatePin(visitId) - Generate new PIN code
 * - SAW_Access.sendInvitation(visitId) - Send invitation email
 */

(function($, window, document) {
    'use strict';

    // ============================================================================
    // CONFIGURATION
    // ============================================================================

    /**
     * Default configuration options
     * Can be overridden via sawAccessConfig global
     */
    var CONFIG = $.extend({
        // Animation duration for toggle (ms)
        animationDuration: 200,

        // Delay before page reload after successful action (ms)
        reloadDelay: 1200,

        // Copy feedback duration (ms)
        copyFeedbackDuration: 2000,

        // Debug mode - logs to console
        debug: false,

        // Translations (can be overridden from PHP)
        i18n: {
            manage: 'Spravovat',
            close: 'Zavřít',
            copied: 'Zkopírováno',
            copyFailed: 'Kopírování selhalo',
            confirmGeneratePin: 'Vygenerovat PIN kód pro tuto návštěvu?\n\nPIN bude platný do konce plánovaného termínu + 24 hodin.',
            confirmResend: 'Znovu odeslat pozvánku?\n\nEmail s aktuálním odkazem a PIN bude odeslán znovu.',
            enterDateTime: 'Zadejte datum a čas',
            invalidDate: 'Neplatné datum',
            serverError: 'Chyba komunikace se serverem',
            pinExtended: 'Platnost PIN prodloužena',
            tokenExtended: 'Platnost odkazu prodloužena',
            pinGenerated: 'PIN vygenerován',
            invitationSent: 'Pozvánka odeslána',
            error: 'Chyba'
        }
    }, window.sawAccessConfig || {});

    // ============================================================================
    // UTILITY FUNCTIONS
    // ============================================================================

    /**
     * Log message to console if debug mode is enabled
     *
     * @param {string} message - Message to log
     * @param {*} [data] - Optional data to log
     */
    function log(message, data) {
        if (CONFIG.debug && window.console && console.log) {
            if (data !== undefined) {
                console.log('[SAW_Access] ' + message, data);
            } else {
                console.log('[SAW_Access] ' + message);
            }
        }
    }

    /**
     * Log error to console
     *
     * @param {string} message - Error message
     * @param {*} [error] - Error object or data
     */
    function logError(message, error) {
        if (window.console && console.error) {
            if (error !== undefined) {
                console.error('[SAW_Access] ' + message, error);
            } else {
                console.error('[SAW_Access] ' + message);
            }
        }
    }

    /**
     * Get translation string
     *
     * @param {string} key - Translation key
     * @param {string} [fallback] - Fallback if key not found
     * @returns {string}
     */
    function __(key, fallback) {
        return CONFIG.i18n[key] || fallback || key;
    }

    /**
     * Get AJAX URL from WordPress localized data
     *
     * @returns {string}
     */
    function getAjaxUrl() {
        if (typeof sawGlobal !== 'undefined' && sawGlobal.ajaxurl) {
            return sawGlobal.ajaxurl;
        }
        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }
        return '/wp-admin/admin-ajax.php';
    }

    /**
     * Get nonce from WordPress localized data
     *
     * @returns {string}
     */
    function getNonce() {
        if (typeof sawGlobal !== 'undefined' && sawGlobal.nonce) {
            return sawGlobal.nonce;
        }
        return '';
    }

    /**
     * Format date to SQL datetime format
     *
     * @param {Date} date - Date object
     * @returns {string} - Format: YYYY-MM-DD HH:MM:SS
     */
    function formatToSQL(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }

        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');

        return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':00';
    }

    /**
     * Get ISO string for datetime-local input (without timezone offset)
     *
     * @param {Date} date - Date object
     * @returns {string} - Format: YYYY-MM-DDTHH:MM
     */
    function formatToDateTimeLocal(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }

        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');

        return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
    }

    // ============================================================================
    // NOTIFICATION SYSTEM
    // ============================================================================

    /**
     * Show notification to user
     *
     * Tries to use existing SAW notification system, falls back to alert
     *
     * @param {string} type - Notification type: 'success', 'error', 'warning', 'info'
     * @param {string} message - Message to display
     */
    function showNotification(type, message) {
        log('Notification [' + type + ']: ' + message);

        // Try SAW notification system
        if (typeof sawNotify !== 'undefined') {
            if (typeof sawNotify[type] === 'function') {
                sawNotify[type](message);
                return;
            }
            if (typeof sawNotify.show === 'function') {
                sawNotify.show(message, type);
                return;
            }
        }

        // Try Toastr
        if (typeof toastr !== 'undefined' && typeof toastr[type] === 'function') {
            toastr[type](message);
            return;
        }

        // Try SweetAlert2
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                title: message,
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }

        // Fallback to native alert with emoji prefix
        var prefix = '';
        switch (type) {
            case 'success':
                prefix = '✅ ';
                break;
            case 'error':
                prefix = '❌ ';
                break;
            case 'warning':
                prefix = '⚠️ ';
                break;
            default:
                prefix = 'ℹ️ ';
        }

        alert(prefix + message);
    }

    // ============================================================================
    // CLIPBOARD FUNCTIONS
    // ============================================================================

    /**
     * Copy text to clipboard
     *
     * Uses modern Clipboard API with fallback for older browsers
     *
     * @param {string} text - Text to copy
     * @param {HTMLElement} [button] - Button element for visual feedback
     */
    function copyToClipboard(text, button) {
        if (!text) {
            logError('copyToClipboard: No text provided');
            return;
        }

        log('Copying to clipboard: ' + text.substring(0, 50) + '...');

        // Try modern Clipboard API
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text)
                .then(function() {
                    showCopyFeedback(button, true);
                    log('Clipboard API: Success');
                })
                .catch(function(err) {
                    logError('Clipboard API failed, trying fallback', err);
                    fallbackCopyToClipboard(text, button);
                });
        } else {
            // Use fallback for older browsers
            fallbackCopyToClipboard(text, button);
        }
    }

    /**
     * Fallback copy method using execCommand
     *
     * @param {string} text - Text to copy
     * @param {HTMLElement} [button] - Button element for visual feedback
     */
    function fallbackCopyToClipboard(text, button) {
        var textarea = document.createElement('textarea');
        textarea.value = text;

        // Make it invisible but still functional
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.width = '2em';
        textarea.style.height = '2em';
        textarea.style.padding = '0';
        textarea.style.border = 'none';
        textarea.style.outline = 'none';
        textarea.style.boxShadow = 'none';
        textarea.style.background = 'transparent';
        textarea.style.opacity = '0';

        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        var success = false;

        try {
            success = document.execCommand('copy');
            log('execCommand copy: ' + (success ? 'Success' : 'Failed'));
        } catch (err) {
            logError('execCommand failed', err);
        }

        document.body.removeChild(textarea);
        showCopyFeedback(button, success);
    }

    /**
     * Show visual feedback on copy button
     *
     * @param {HTMLElement} button - Button element
     * @param {boolean} success - Whether copy was successful
     */
    function showCopyFeedback(button, success) {
        if (!button) {
            return;
        }

        var $button = $(button);
        var originalHtml = $button.html();

        if (success) {
            $button.html('<span class="saw-icon">✓</span>');
            $button.addClass('is-copied');
            showNotification('success', __('copied', 'Zkopírováno'));
        } else {
            $button.html('<span class="saw-icon">✗</span>');
            $button.addClass('is-error');
            showNotification('error', __('copyFailed', 'Kopírování selhalo'));
        }

        setTimeout(function() {
            $button.html(originalHtml);
            $button.removeClass('is-copied is-error');
        }, CONFIG.copyFeedbackDuration);
    }

    // ============================================================================
    // TOGGLE FUNCTIONS
    // ============================================================================

    /**
     * Toggle between compact and expanded view
     *
     * @param {number} visitId - Visit ID
     */
    function toggle(visitId) {
        if (!visitId) {
            logError('toggle: Missing visitId');
            return;
        }

        log('Toggle view for visit #' + visitId);

        var $summary = $('#access-summary-' + visitId);
        var $details = $('#access-details-' + visitId);
        var $button = $('#access-toggle-btn-' + visitId);

        if (!$summary.length || !$details.length) {
            logError('toggle: Missing DOM elements for visit #' + visitId);
            return;
        }

        var isExpanded = $details.is(':visible');

        if (isExpanded) {
            // Collapse to summary view
            $details.slideUp(CONFIG.animationDuration, function() {
                $summary.slideDown(CONFIG.animationDuration);
            });

            if ($button.length) {
                $button.html(
                    '<span class="saw-icon">⚙️</span>' +
                    '<span class="saw-btn-text">' + __('manage', 'Spravovat') + '</span>'
                );
                $button.attr('title', __('manage', 'Spravovat'));
            }

            log('Collapsed view for visit #' + visitId);
        } else {
            // Expand to detailed view
            $summary.slideUp(CONFIG.animationDuration, function() {
                $details.slideDown(CONFIG.animationDuration);
            });

            if ($button.length) {
                $button.html(
                    '<span class="saw-icon">✕</span>' +
                    '<span class="saw-btn-text">' + __('close', 'Zavřít') + '</span>'
                );
                $button.attr('title', __('close', 'Zavřít'));
            }

            log('Expanded view for visit #' + visitId);
        }
    }

    // ============================================================================
    // EXTEND EXPIRATION FUNCTIONS
    // ============================================================================

    /**
     * Extend PIN or Token expiration
     *
     * @param {number} visitId - Visit ID
     * @param {string} type - 'pin' or 'token'
     * @param {number} hours - Hours to extend
     */
    function extend(visitId, type, hours) {
        if (!visitId || !type || !hours) {
            logError('extend: Missing required parameters', { visitId: visitId, type: type, hours: hours });
            return;
        }

        log('Extending ' + type + ' for visit #' + visitId + ' by ' + hours + ' hours');

        var action = (type === 'pin') ? 'saw_extend_pin' : 'saw_extend_token';

        // Show loading state
        setButtonsLoading(visitId, type, true);

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: action,
                visit_id: visitId,
                hours: hours,
                nonce: getNonce()
            },
            success: function(response) {
                log('Extend response:', response);

                if (response.success) {
                    var message = (type === 'pin')
                        ? __('pinExtended', 'Platnost PIN prodloužena')
                        : __('tokenExtended', 'Platnost odkazu prodloužena');

                    showNotification('success', message + ': ' + response.data.new_expiry);

                    // Reload page to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, CONFIG.reloadDelay);
                } else {
                    var errorMsg = (response.data && response.data.message)
                        ? response.data.message
                        : __('error', 'Chyba');
                    showNotification('error', errorMsg);
                    setButtonsLoading(visitId, type, false);
                }
            },
            error: function(xhr, status, error) {
                logError('AJAX error', { status: status, error: error });
                showNotification('error', __('serverError', 'Chyba komunikace se serverem'));
                setButtonsLoading(visitId, type, false);
            }
        });
    }

    /**
     * Set loading state on extend buttons
     *
     * @param {number} visitId - Visit ID
     * @param {string} type - 'pin' or 'token'
     * @param {boolean} loading - Loading state
     */
    function setButtonsLoading(visitId, type, loading) {
        var $panel = (type === 'pin')
            ? $('#access-details-' + visitId + ' .saw-access-panel').first()
            : $('#access-details-' + visitId + ' .saw-access-panel').last();

        var $buttons = $panel.find('.saw-btn-group .saw-btn');

        if (loading) {
            $buttons.prop('disabled', true).addClass('is-loading');
        } else {
            $buttons.prop('disabled', false).removeClass('is-loading');
        }
    }

    // ============================================================================
    // CUSTOM DATETIME PICKER
    // ============================================================================

    /**
     * Show custom datetime picker
     *
     * @param {number} visitId - Visit ID
     * @param {string} type - 'pin' or 'token'
     */
    function showCustomPicker(visitId, type) {
        if (!visitId || !type) {
            logError('showCustomPicker: Missing parameters');
            return;
        }

        log('Showing custom picker for ' + type + ', visit #' + visitId);

        var $picker = $('#' + type + '-picker-' + visitId);
        var $input = $('#' + type + '-datetime-' + visitId);

        if (!$picker.length || !$input.length) {
            logError('showCustomPicker: Missing DOM elements');
            return;
        }

        // Set minimum to now
        var now = new Date();
        $input.attr('min', formatToDateTimeLocal(now));

        // Set default to +7 days from now
        var defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 7);
        defaultDate.setHours(23, 59, 0, 0);
        $input.val(formatToDateTimeLocal(defaultDate));

        // Show picker with animation
        $picker.slideDown(CONFIG.animationDuration);
        $input.focus();
    }

    /**
     * Hide custom datetime picker
     *
     * @param {number} visitId - Visit ID
     * @param {string} type - 'pin' or 'token'
     */
    function hideCustomPicker(visitId, type) {
        var $picker = $('#' + type + '-picker-' + visitId);

        if ($picker.length) {
            $picker.slideUp(CONFIG.animationDuration);
        }
    }

    /**
     * Apply custom datetime from picker
     *
     * @param {number} visitId - Visit ID
     * @param {string} type - 'pin' or 'token'
     */
    function applyCustom(visitId, type) {
        if (!visitId || !type) {
            logError('applyCustom: Missing parameters');
            return;
        }

        var $input = $('#' + type + '-datetime-' + visitId);

        if (!$input.length || !$input.val()) {
            showNotification('warning', __('enterDateTime', 'Zadejte datum a čas'));
            return;
        }

        var inputValue = $input.val();
        var date = new Date(inputValue);

        if (isNaN(date.getTime())) {
            showNotification('error', __('invalidDate', 'Neplatné datum'));
            return;
        }

        // Validate that date is in the future
        if (date.getTime() <= Date.now()) {
            showNotification('warning', 'Datum musí být v budoucnosti');
            return;
        }

        log('Applying custom datetime for ' + type + ': ' + inputValue);

        var sqlDate = formatToSQL(date);
        var action = (type === 'pin') ? 'saw_extend_pin' : 'saw_extend_token';

        // Hide picker and show loading
        hideCustomPicker(visitId, type);
        setButtonsLoading(visitId, type, true);

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: action,
                visit_id: visitId,
                exact_expiry: sqlDate,
                nonce: getNonce()
            },
            success: function(response) {
                log('Custom extend response:', response);

                if (response.success) {
                    var message = (type === 'pin')
                        ? __('pinExtended', 'Platnost PIN prodloužena')
                        : __('tokenExtended', 'Platnost odkazu prodloužena');

                    showNotification('success', message + ': ' + response.data.new_expiry);

                    setTimeout(function() {
                        location.reload();
                    }, CONFIG.reloadDelay);
                } else {
                    var errorMsg = (response.data && response.data.message)
                        ? response.data.message
                        : __('error', 'Chyba');
                    showNotification('error', errorMsg);
                    setButtonsLoading(visitId, type, false);
                }
            },
            error: function(xhr, status, error) {
                logError('AJAX error', { status: status, error: error });
                showNotification('error', __('serverError', 'Chyba komunikace se serverem'));
                setButtonsLoading(visitId, type, false);
            }
        });
    }

    // ============================================================================
    // PIN GENERATION
    // ============================================================================

    /**
     * Generate new PIN code for visit
     *
     * @param {number} visitId - Visit ID
     */
    function generatePin(visitId) {
        if (!visitId) {
            logError('generatePin: Missing visitId');
            return;
        }

        // Confirm action
        if (!confirm(__('confirmGeneratePin', 'Vygenerovat PIN kód pro tuto návštěvu?\n\nPIN bude platný do konce plánovaného termínu + 24 hodin.'))) {
            return;
        }

        log('Generating PIN for visit #' + visitId);

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'saw_generate_pin',
                visit_id: visitId,
                nonce: getNonce()
            },
            beforeSend: function() {
                // Could add loading state here
            },
            success: function(response) {
                log('Generate PIN response:', response);

                if (response.success) {
                    var message = __('pinGenerated', 'PIN vygenerován');
                    if (response.data && response.data.pin_code) {
                        message += ': ' + response.data.pin_code;
                    }

                    showNotification('success', message);

                    setTimeout(function() {
                        location.reload();
                    }, CONFIG.reloadDelay + 300); // Slightly longer to see the PIN
                } else {
                    var errorMsg = (response.data && response.data.message)
                        ? response.data.message
                        : __('error', 'Chyba při generování PIN');
                    showNotification('error', errorMsg);
                }
            },
            error: function(xhr, status, error) {
                logError('AJAX error', { status: status, error: error });
                showNotification('error', __('serverError', 'Chyba komunikace se serverem'));
            }
        });
    }

    // ============================================================================
    // INVITATION EMAIL
    // ============================================================================

    /**
     * Send invitation email
     *
     * @param {number} visitId - Visit ID
     */
    function sendInvitation(visitId) {
        if (!visitId) {
            logError('sendInvitation: Missing visitId');
            return;
        }

        log('Sending invitation for visit #' + visitId);

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'saw_send_invitation',
                visit_id: visitId,
                nonce: getNonce()
            },
            success: function(response) {
                log('Send invitation response:', response);

                if (response.success) {
                    showNotification('success', __('invitationSent', 'Pozvánka odeslána'));

                    setTimeout(function() {
                        location.reload();
                    }, CONFIG.reloadDelay);
                } else {
                    var errorMsg = (response.data && response.data.message)
                        ? response.data.message
                        : __('error', 'Chyba při odesílání pozvánky');
                    showNotification('error', errorMsg);
                }
            },
            error: function(xhr, status, error) {
                logError('AJAX error', { status: status, error: error });
                showNotification('error', __('serverError', 'Chyba komunikace se serverem'));
            }
        });
    }

    /**
     * Resend invitation email (with confirmation)
     *
     * @param {number} visitId - Visit ID
     */
    function resendInvitation(visitId) {
        if (!confirm(__('confirmResend', 'Znovu odeslat pozvánku?\n\nEmail s aktuálním odkazem a PIN bude odeslán znovu.'))) {
            return;
        }

        sendInvitation(visitId);
    }

    // ============================================================================
    // EVENT BINDINGS
    // ============================================================================

    /**
     * Initialize event bindings
     *
     * Sets up delegated event handlers for dynamically loaded content
     */
    function initEventBindings() {
        log('Initializing event bindings');

        // Handle Enter key in datetime inputs
        $(document).on('keypress', '[id^="pin-datetime-"], [id^="token-datetime-"]', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();

                var id = $(this).attr('id');
                var parts = id.split('-');
                var type = parts[0]; // 'pin' or 'token'
                var visitId = parts[2]; // Visit ID

                applyCustom(visitId, type);
            }
        });

        // Handle Escape key to close picker
        $(document).on('keyup', '[id^="pin-datetime-"], [id^="token-datetime-"]', function(e) {
            if (e.which === 27) { // Escape key
                var id = $(this).attr('id');
                var parts = id.split('-');
                var type = parts[0];
                var visitId = parts[2];

                hideCustomPicker(visitId, type);
            }
        });

        // Close picker when clicking outside
        $(document).on('click', function(e) {
            var $target = $(e.target);

            // If click is not inside a picker or on a picker trigger button
            if (!$target.closest('.saw-custom-picker').length &&
                !$target.closest('[onclick*="showCustomPicker"]').length) {

                // Close all open pickers
                $('.saw-custom-picker:visible').each(function() {
                    var id = $(this).attr('id');
                    if (id) {
                        var parts = id.split('-');
                        var type = parts[0];
                        var visitId = parts[2];
                        hideCustomPicker(visitId, type);
                    }
                });
            }
        });

        log('Event bindings initialized');
    }

    // ============================================================================
    // INITIALIZATION
    // ============================================================================

    /**
     * Initialize the module
     */
    function init() {
        log('SAW_Access module initializing...');

        initEventBindings();

        log('SAW_Access module initialized');
    }

    // Initialize when DOM is ready
    $(document).ready(init);

    // Also initialize when modal is opened (for dynamically loaded modals)
    $(document).on('saw:modal:opened saw:detail:loaded', function(e, data) {
        log('Modal/detail opened, re-checking bindings');
        // Event bindings are delegated, so no need to rebind
    });

    // ============================================================================
    // PUBLIC API
    // ============================================================================

    /**
     * Public API exposed to window.SAW_Access
     */
    window.SAW_Access = {
        // Core functions
        toggle: toggle,
        copy: copyToClipboard,
        extend: extend,

        // Custom picker
        showCustomPicker: showCustomPicker,
        hideCustomPicker: hideCustomPicker,
        applyCustom: applyCustom,

        // Actions
        generatePin: generatePin,
        sendInvitation: sendInvitation,
        resendInvitation: resendInvitation,

        // Utility (exposed for potential external use)
        showNotification: showNotification,

        // Configuration
        config: CONFIG,

        // Version
        version: '3.6.0'
    };

})(jQuery, window, document);