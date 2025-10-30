/**
 * Customers Module Scripts
 * 
 * Logo preview, color picker sync, IČO validace
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Logo preview při výběru souboru
        $('#logo').on('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Validace velikosti (2MB)
                if (file.size > 2097152) {
                    alert('Soubor je příliš velký! Maximální velikost je 2MB.');
                    $(this).val('');
                    return;
                }
                
                // Preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('.saw-logo-preview-current').html('<img src="' + e.target.result + '" alt="Preview">');
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Color picker sync s text inputem
        $('#primary_color').on('input', function() {
            $('#primary_color_value').val($(this).val().toUpperCase());
        });
        
        // IČO validace
        $('#ico').on('blur', function() {
            const ico = $(this).val();
            
            if (ico && !/^\d{8}$/.test(ico)) {
                alert('IČO musí být 8 číslic!');
                $(this).focus();
            }
        });
        
        // PSČ formátování (přidá mezeru)
        $('input[name="address_zip"], input[name="billing_address_zip"]').on('blur', function() {
            let zip = $(this).val().replace(/\s/g, '');
            if (zip.length === 5) {
                $(this).val(zip.slice(0, 3) + ' ' + zip.slice(3));
            }
        });
    });
    
})(jQuery);
