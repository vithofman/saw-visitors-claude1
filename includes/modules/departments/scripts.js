/**
 * Departments Module Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // TRAINING VERSION VALIDATION
        // ================================================
        $('#training_version').on('blur', function() {
            const value = parseInt($(this).val());
            
            if (isNaN(value) || value < 1) {
                alert('Verze školení musí být alespoň 1');
                $(this).val(1);
                $(this).focus();
            }
        });
        
    });
    
})(jQuery);
