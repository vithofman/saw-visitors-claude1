/**
 * Departments Module Scripts
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @version     3.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        console.log('[Departments Form] Loaded');

        // ================================================
        // FORM VALIDATION
        // ================================================
        $('.saw-department-form').on('submit', function (e) {
            const branchId = $('#branch_id').val();
            const name = $('#name').val().trim();

            if (!branchId) {
                alert('Vyberte prosím pobočku');
                $('#branch_id').focus();
                e.preventDefault();
                return false;
            }

            if (!name) {
                alert('Vyplňte prosím název oddělení');
                $('#name').focus();
                e.preventDefault();
                return false;
            }
        });

    });

})(jQuery);
