/**
 * Users Module Scripts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // FORM VALIDATION
        // ================================================
        $('.saw-user-form').on('submit', function(e) {
            const role = $('#role').val();
            const email = $('#email').val().trim();
            const firstName = $('#first_name').val().trim();
            const lastName = $('#last_name').val().trim();
            
            if (!role) {
                e.preventDefault();
                alert('Vyberte roli!');
                $('#role').focus();
                return false;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Vyplňte email!');
                $('#email').focus();
                return false;
            }
            
            if (!firstName || !lastName) {
                e.preventDefault();
                alert('Vyplňte jméno a příjmení!');
                return false;
            }
            
            if ((role === 'super_manager' || role === 'manager' || role === 'terminal') && !$('#branch_id').val()) {
                e.preventDefault();
                alert('Pro tuto roli musíte vybrat pobočku!');
                $('#branch_id').focus();
                return false;
            }
            
            if (role === 'manager') {
                const selectedDepts = $('input[name="department_ids[]"]:checked').length;
                if (selectedDepts === 0) {
                    e.preventDefault();
                    alert('Manager musí mít přiřazeno alespoň jedno oddělení!');
                    return false;
                }
            }
            
            if (role === 'terminal') {
                const pin = $('#pin').val();
                if (pin && !/^\d{4}$/.test(pin)) {
                    e.preventDefault();
                    alert('PIN musí být 4 čísla!');
                    $('#pin').focus();
                    return false;
                }
            }
            
            return true;
        });
        
        // ================================================
        // PIN INPUT FORMATTING
        // ================================================
        $('#pin').on('input', function() {
            this.value = this.value.replace(/[^\d]/g, '');
        });
        
    });
    
})(jQuery);
