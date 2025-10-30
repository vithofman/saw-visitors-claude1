/**
 * SAW Customers - Page-Specific JavaScript
 *
 * Customer-specific functionality:
 * - Logo preview before upload
 * - Color picker synchronization
 *
 * @package SAW_Visitors
 * @version 4.6.3
 */

(function($) {
    'use strict';

    const SawCustomers = {

        init: function() {
            this.initLogoPreview();
            this.initColorPicker();
            console.log('SAW Customers: Initialized');
        },

        // Helper: vrátí jQuery input pro logo (podporuje staré i nové ID)
        getLogoInput: function() {
            return $('#customer_logo').length ? $('#customer_logo') : $('#logo');
        },

        // Helper: vrátí jQuery input pro primární barvu (text)
        getPrimaryColorText: function() {
            return $('#customer_primary_color').length ? $('#customer_primary_color') : $('#primary_color');
        },

        // Helper: vrátí jQuery input pro color picker (pokud existuje zvlášť)
        getPrimaryColorPicker: function() {
            return $('#customer_primary_color_picker').length ? $('#customer_primary_color_picker') : $('#primary_color');
        },

        initLogoPreview: function() {
            // Unbind + bind
            $(document).off('change.sawcustomers', '#customer_logo, #logo')
                .on('change.sawcustomers', '#customer_logo, #logo', function(e) {
                    const file = e.target.files && e.target.files[0];
                    if (!file) return;

                    console.log('SAW Customers: Logo selected:', file.name, file.type, file.size);

                    if (!file.type || !file.type.match || !file.type.match('image.*')) {
                        alert('Prosím vyberte obrázek (JPG, PNG, GIF).');
                        $(this).val('');
                        return;
                    }

                    if (file.size > 2 * 1024 * 1024) {
                        alert('Soubor je příliš velký. Maximální velikost je 2 MB.');
                        $(this).val('');
                        return;
                    }

                    $('.saw-file-name').text(file.name);

                    const reader = new FileReader();
                    reader.onload = function(event) {
                        let $previewContainer = $('.saw-logo-preview-current');
                        if ($previewContainer.length === 0) {
                            $previewContainer = $('<div class="saw-logo-preview-current"></div>');
                            $previewContainer.html('<p class="saw-logo-preview-label">Náhled:</p><img src="" alt="Logo náhled">');
                            const $logoInput = SawCustomers.getLogoInput();
                            $logoInput.closest('.saw-form-group, .form-group, .saw-field').prepend($previewContainer);
                        }
                        $('.saw-logo-preview-current img').attr('src', event.target.result);
                        $('.saw-logo-preview-current').show();
                        console.log('SAW Customers: Logo preview updated');
                    };
                    reader.readAsDataURL(file);
                });
        },

        initColorPicker: function() {
            const $picker = this.getPrimaryColorPicker();
            const $text = this.getPrimaryColorText();

            // Synchronizace picker -> text
            $(document).off('input.sawcustomerspicker', '#' + $picker.attr('id'))
                .on('input.sawcustomerspicker', '#' + $picker.attr('id'), function() {
                    const color = $(this).val();
                    if ($text.length) $text.val(color);
                    console.log('SAW Customers: Color changed via picker:', color);
                });

            // Synchronizace text -> picker
            $(document).off('input.sawcustomerstext', '#' + $text.attr('id'))
                .on('input.sawcustomerstext', '#' + $text.attr('id'), function() {
                    const color = $(this).val();
                    if (/^#[0-9A-F]{6}$/i.test(color)) {
                        if ($picker.length) $picker.val(color);
                        console.log('SAW Customers: Color changed via text:', color);
                    }
                });

            // Inicializace pickeru z textu (pokud validní)
            const initialColor = ($text.val() || '').trim();
            if (initialColor && /^#[0-9A-F]{6}$/i.test(initialColor) && $picker.length) {
                $picker.val(initialColor);
            }
        }
    };

    $(document).ready(function() {
        SawCustomers.init();
    });

    $(document).on('saw:scripts-reinitialized', function() {
        console.log('🔄 Customers: Reinitializing after AJAX navigation...');
        SawCustomers.init();
    });

    window.SawCustomers = SawCustomers;

})(jQuery);
