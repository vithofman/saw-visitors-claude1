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
        // TRAINING VERSION INCREMENT
        // ================================================
        const $versionField = $('#training_version');
        
        if ($versionField.length) {
            const $incrementBtn = $('<button type="button" class="saw-button saw-button-secondary saw-increment-version-btn" title="Zvýšit verzi školení o 1"><span class="dashicons dashicons-arrow-up-alt"></span> Zvýšit verzi</button>');
            
            $versionField.closest('.saw-form-group').append($incrementBtn);
            
            $incrementBtn.on('click', function(e) {
                e.preventDefault();
                
                const currentValue = parseInt($versionField.val()) || 1;
                $versionField.val(currentValue + 1);
                
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                $btn.html('<span class="dashicons dashicons-yes"></span> Verze zvýšena!');
                
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 2000);
            });
        }
        
        // ================================================
        // FORM VALIDATION
        // ================================================
        $('.saw-department-form').on('submit', function(e) {
            const name = $('#name').val().trim();
            
            if (!name) {
                e.preventDefault();
                alert('Vyplňte název oddělení!');
                $('#name').focus();
                return false;
            }
            
            const version = parseInt($('#training_version').val());
            
            if (!version || version < 1) {
                e.preventDefault();
                alert('Verze školení musí být alespoň 1!');
                $('#training_version').focus();
                return false;
            }
            
            return true;
        });
        
    });
    
})(jQuery);
