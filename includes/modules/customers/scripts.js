/**
 * Customers Module Scripts
 * 
 * Color picker sync, IČO validace
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
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