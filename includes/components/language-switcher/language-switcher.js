/**
 * SAW Language Switcher - JavaScript
 * 
 * @package SAW_Visitors
 * @since 4.7.0
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
            if (!this.button.length || !this.dropdown.length) {
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
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            this.isOpen = true;
            this.dropdown.addClass('active');
        }
        
        close() {
            this.isOpen = false;
            this.dropdown.removeClass('active');
        }
        
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
                    if (response.success) {
                        // Reload stránky pro aplikování nového jazyka
                        window.location.reload();
                    } else {
                        alert(response.data?.message || 'Chyba při přepínání jazyka');
                        this.button.prop('disabled', false);
                    }
                },
                error: () => {
                    alert('Chyba serveru');
                    this.button.prop('disabled', false);
                }
            });
        }
    }
    
    // Initialize
    $(document).ready(function() {
        new LanguageSwitcher();
    });
    
})(jQuery);
