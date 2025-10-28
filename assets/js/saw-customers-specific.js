/**
 * SAW Customers - Specific JavaScript
 * 
 * POUZE customer-specific logika (logo preview, color picker)
 * Vše ostatní (search, sort, delete, AJAX) je v saw-admin-table.js
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

(function($) {
    'use strict';
    
    const SawCustomersSpecific = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initLogoPreview();
            this.initColorPicker();
            
            console.log('SAW Customers Specific: Initialized');
        },
        
        /**
         * Logo preview před uploadem
         */
        initLogoPreview: function() {
            $(document).on('change', '#customer_logo', function(e) {
                const file = e.target.files[0];
                
                if (!file) {
                    return;
                }
                
                console.log('SAW Customers: Logo selected:', file.name, file.type, file.size);
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    alert('Prosím vyberte obrázek (JPG, PNG, GIF).');
                    $(this).val('');
                    return;
                }
                
                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Soubor je příliš velký. Maximální velikost je 2 MB.');
                    $(this).val('');
                    return;
                }
                
                // Update file name display
                $('.saw-file-name').text(file.name);
                
                // Create preview
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const $previewContainer = $('.saw-logo-preview-current');
                    
                    if ($previewContainer.length === 0) {
                        // Create preview container if doesn't exist
                        const $newPreview = $('<div class="saw-logo-preview-current"></div>');
                        $newPreview.html('<p class="saw-logo-preview-label">Náhled:</p><img src="" alt="Logo náhled">');
                        $('#customer_logo').closest('.saw-form-group').prepend($newPreview);
                    }
                    
                    // Update preview image
                    $('.saw-logo-preview-current img').attr('src', event.target.result);
                    $('.saw-logo-preview-current').show();
                    
                    console.log('SAW Customers: Logo preview updated');
                };
                
                reader.readAsDataURL(file);
            });
        },
        
        /**
         * Color picker synchronization
         */
        initColorPicker: function() {
            // Update text input when color picker changes
            $(document).on('input', '#customer_primary_color_picker', function() {
                const color = $(this).val();
                $('#customer_primary_color').val(color);
                
                console.log('SAW Customers: Color changed via picker:', color);
            });
            
            // Update color picker when text input changes
            $(document).on('input', '#customer_primary_color', function() {
                const color = $(this).val();
                
                // Validate hex color
                if (/^#[0-9A-F]{6}$/i.test(color)) {
                    $('#customer_primary_color_picker').val(color);
                    console.log('SAW Customers: Color changed via text:', color);
                }
            });
            
            // Initialize picker value from text input on load
            const initialColor = $('#customer_primary_color').val();
            if (initialColor && /^#[0-9A-F]{6}$/i.test(initialColor)) {
                $('#customer_primary_color_picker').val(initialColor);
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SawCustomersSpecific.init();
    });
    
    // Export to global scope
    window.SawCustomersSpecific = SawCustomersSpecific;
    
})(jQuery);