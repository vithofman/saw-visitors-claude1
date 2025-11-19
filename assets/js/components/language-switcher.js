/**
 * SAW Language Switcher - JavaScript
 * 
 * Handles language switching with AJAX requests, dropdown interactions,
 * and keyboard navigation support.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/LanguageSwitcher
 * @version     2.0.1
 * @since       4.7.0
 * @author      SAW Visitors Team
 */

(function($) {
    'use strict';
    
    /**
     * Language Switcher Class
     * 
     * Manages language selection dropdown and AJAX-based language switching.
     * 
     * @class
     * @since 4.7.0
     */
    class LanguageSwitcher {
        /**
         * Constructor
         * 
         * Initializes the language switcher with DOM elements and state.
         * 
         * @since 4.7.0
         */
        constructor() {
            this.button = $('#sawLanguageSwitcherButton');
            this.dropdown = $('#sawLanguageSwitcherDropdown');
            this.currentLanguage = null;
            this.isOpen = false;
            
            this.init();
        }
        
        /**
         * Initialize component
         * 
         * Sets up event listeners and validates required elements.
         * 
         * @since 4.7.0
         * @return {void}
         */
        init() {
            if (!this.button.length || !this.dropdown.length) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // Elements not found
                }
                return;
            }
            
            if (typeof sawLanguageSwitcher === 'undefined') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // sawLanguageSwitcher object not found
                }
                return;
            }
            
            this.currentLanguage = this.button.data('current-language');
            
            // Button click
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            // Language item click
            this.dropdown.find('.saw-language-item').on('click', (e) => {
                const language = $(e.currentTarget).data('language');
                this.switchLanguage(language);
            });
            
            // Outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#sawLanguageSwitcher').length) {
                    this.close();
                }
            });
            
            // ESC key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }
        
        /**
         * Toggle dropdown
         * 
         * Opens dropdown if closed, closes if open.
         * 
         * @since 4.7.0
         * @return {void}
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        /**
         * Open dropdown
         * 
         * Displays the language selection dropdown.
         * 
         * @since 4.7.0
         * @return {void}
         */
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
        }
        
        /**
         * Close dropdown
         * 
         * Hides the language selection dropdown.
         * 
         * @since 4.7.0
         * @return {void}
         */
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        /**
         * Switch language
         * 
         * Sends AJAX request to switch the current language and reloads
         * the page on success.
         * 
         * @since 4.7.0
         * @param {string} language - Language code to switch to
         * @return {void}
         */
        switchLanguage(language) {
            if (language === this.currentLanguage) {
                this.close();
                return;
            }
            
            this.button.prop('disabled', true);
            
            $.ajax({
                url: sawLanguageSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_language',
                    language: language,
                    nonce: sawLanguageSwitcher.nonce
                },
                success: (response) => {
                    if (response && response.success) {
                        window.location.reload();
                    } else {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba při přepínání jazyka';
                        alert(message);
                        this.button.prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    let message = 'Chyba serveru (status: ' + xhr.status + ')';
                    
                    if (xhr.status === 0) {
                        message = 'Síťová chyba - zkontrolujte připojení';
                    } else if (xhr.status === 400) {
                        message = 'Chybný požadavek (400) - problém s daty nebo nonce';
                    } else if (xhr.status === 403) {
                        message = 'Nedostatečná oprávnění (403)';
                    } else if (xhr.status === 404) {
                        message = 'AJAX endpoint nenalezen (404)';
                    }
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                    } catch (e) {
                        // Could not parse error response
                    }
                    
                    alert(message);
                    this.button.prop('disabled', false);
                }
            });
        }
    }
    
    /**
     * Initialize language switcher on document ready
     * 
     * @since 4.7.0
     */
    $(document).ready(function() {
        if ($('#sawLanguageSwitcher').length) {
            window.languageSwitcher = new LanguageSwitcher();
        }
    });
    
})(jQuery);