/**
 * SAW Language Switcher - JavaScript (DEBUG VERSION)
 * 
 * @package SAW_Visitors
 * @version 2.0.1
 */

(function($) {
    'use strict';
    
    class LanguageSwitcher {
        constructor() {
            this.button = $('#sawLanguageSwitcherButton');
            this.dropdown = $('#sawLanguageSwitcherDropdown');
            this.currentLanguage = null;
            this.isOpen = false;
            
            this.init();
        }
        
        init() {
            console.log('[Language Switcher] Starting init...');
            
            if (!this.button.length || !this.dropdown.length) {
                console.error('[Language Switcher] Elements not found!', {
                    button: this.button.length,
                    dropdown: this.dropdown.length
                });
                return;
            }
            
            if (typeof sawLanguageSwitcher === 'undefined') {
                console.error('[Language Switcher] sawLanguageSwitcher object not found!');
                console.log('Available global objects:', Object.keys(window));
                return;
            }
            
            this.currentLanguage = this.button.data('current-language');
            
            console.log('[Language Switcher] Initialized successfully', {
                currentLanguage: this.currentLanguage,
                ajaxurl: sawLanguageSwitcher.ajaxurl,
                hasNonce: !!sawLanguageSwitcher.nonce,
                nonce: sawLanguageSwitcher.nonce
            });
            
            // Button click
            this.button.on('click', (e) => {
                e.stopPropagation();
                console.log('[Language Switcher] Button clicked');
                this.toggle();
            });
            
            // Language item click
            this.dropdown.find('.saw-language-item').on('click', (e) => {
                const language = $(e.currentTarget).data('language');
                console.log('[Language Switcher] Language item clicked:', language);
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
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            console.log('[Language Switcher] Opening dropdown');
            this.isOpen = true;
            this.dropdown.addClass('active');
        }
        
        close() {
            console.log('[Language Switcher] Closing dropdown');
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
        switchLanguage(language) {
            if (language === this.currentLanguage) {
                console.log('[Language Switcher] Already on this language, closing');
                this.close();
                return;
            }
            
            console.log('[Language Switcher] Starting switch to:', language);
            console.log('[Language Switcher] AJAX data:', {
                url: sawLanguageSwitcher.ajaxurl,
                action: 'saw_switch_language',
                language: language,
                nonce: sawLanguageSwitcher.nonce
            });
            
            this.button.prop('disabled', true);
            
            $.ajax({
                url: sawLanguageSwitcher.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_language',
                    language: language,
                    nonce: sawLanguageSwitcher.nonce
                },
                beforeSend: function(xhr) {
                    console.log('[Language Switcher] Sending AJAX request...');
                },
                success: (response) => {
                    console.log('[Language Switcher] AJAX Success!', response);
                    
                    if (response && response.success) {
                        console.log('[Language Switcher] Language switched successfully, reloading...');
                        window.location.reload();
                    } else {
                        const message = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Chyba při přepínání jazyka';
                        console.error('[Language Switcher] Server returned error:', message);
                        alert(message);
                        this.button.prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[Language Switcher] AJAX ERROR!', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error,
                        responseText: xhr.responseText,
                        readyState: xhr.readyState
                    });
                    
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
                        console.log('[Language Switcher] Parsed error response:', response);
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                    } catch (e) {
                        console.log('[Language Switcher] Could not parse error response');
                    }
                    
                    alert(message);
                    this.button.prop('disabled', false);
                }
            });
        }
    }
    
    // Initialize
    $(document).ready(function() {
        console.log('[Language Switcher] Document ready, searching for component...');
        
        if ($('#sawLanguageSwitcher').length) {
            console.log('[Language Switcher] Component found, initializing...');
            window.languageSwitcher = new LanguageSwitcher();
        } else {
            console.error('[Language Switcher] Component #sawLanguageSwitcher not found in DOM!');
        }
    });
    
})(jQuery);