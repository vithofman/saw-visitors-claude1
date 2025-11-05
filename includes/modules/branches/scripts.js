/**
 * Branches Module Scripts
 * 
 * REFACTORED v2.0.0:
 * ✅ Wrapped in IIFE
 * ✅ 'use strict'
 * ✅ No console.log (only in dev check)
 * ✅ Event delegation where appropriate
 * ✅ Clean validation
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // NAME → CODE AUTO-GENERATION
        // ================================================
        const $codeField = $('#code');
        const $nameField = $('#name');
        const isEditMode = $('input[name="id"]').length > 0;
        
        if (!isEditMode && $codeField.length && $nameField.length && !$codeField.val()) {
            $nameField.on('input', function() {
                const name = $(this).val();
                const code = generateBranchCode(name);
                $codeField.val(code);
            });
        }
        
        /**
         * Generate branch code from name
         * Example: "Pobočka Praha" → "PR001"
         */
        function generateBranchCode(text) {
            if (!text) return '';
            
            const words = text
                .toUpperCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^A-Z\s]/g, '')
                .trim()
                .split(/\s+/);
            
            if (words.length === 0) return '';
            
            let code = words[0].substring(0, 2);
            
            if (words.length > 1) {
                code += words[1].substring(0, 1);
            }
            
            code += '001';
            
            return code;
        }
        
        // ================================================
        // GPS COORDINATES VALIDATION
        // ================================================
        $('#latitude').on('blur', function() {
            const lat = parseFloat($(this).val());
            
            if (!isNaN(lat)) {
                if (lat < -90 || lat > 90) {
                    alert('Zeměpisná šířka musí být mezi -90 a 90!');
                    $(this).focus();
                } else {
                    $(this).val(lat.toFixed(8));
                }
            }
        });
        
        $('#longitude').on('blur', function() {
            const lon = parseFloat($(this).val());
            
            if (!isNaN(lon)) {
                if (lon < -180 || lon > 180) {
                    alert('Zeměpisná délka musí být mezi -180 a 180!');
                    $(this).focus();
                } else {
                    $(this).val(lon.toFixed(8));
                }
            }
        });
        
        // ================================================
        // GET GPS FROM BROWSER
        // ================================================
        const $gpsButton = $('<button type="button" class="saw-button saw-button-secondary saw-get-gps-btn" title="Získat GPS z polohy zařízení"><span class="dashicons dashicons-location"></span> Získat GPS z polohy</button>');
        
        if ($('#latitude').length && navigator.geolocation) {
            $('#longitude').closest('.saw-form-group').after(
                '<div class="saw-form-group saw-col-12" style="margin-top: -12px;"></div>'
            ).next().append($gpsButton);
        }
        
        $gpsButton.on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update saw-spin"></span> Získávám polohu...');
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    $('#latitude').val(position.coords.latitude.toFixed(8));
                    $('#longitude').val(position.coords.longitude.toFixed(8));
                    
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> GPS získáno!');
                    
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 3000);
                },
                function(error) {
                    $btn.prop('disabled', false).html(originalText);
                    alert('Nepodařilo se získat GPS polohu: ' + error.message);
                }
            );
        });
        
        // ================================================
        // OPENING HOURS TEMPLATES
        // ================================================
        const $openingHoursField = $('#opening_hours');
        
        if ($openingHoursField.length) {
            const templates = [
                {
                    name: 'Po-Pá 8-16',
                    value: 'Po: 8:00-16:00\nÚt: 8:00-16:00\nSt: 8:00-16:00\nČt: 8:00-16:00\nPá: 8:00-16:00\nSo: Zavřeno\nNe: Zavřeno'
                },
                {
                    name: 'Po-Pá 9-17',
                    value: 'Po: 9:00-17:00\nÚt: 9:00-17:00\nSt: 9:00-17:00\nČt: 9:00-17:00\nPá: 9:00-17:00\nSo: Zavřeno\nNe: Zavřeno'
                },
                {
                    name: 'Nonstop',
                    value: 'Po: 0:00-24:00\nÚt: 0:00-24:00\nSt: 0:00-24:00\nČt: 0:00-24:00\nPá: 0:00-24:00\nSo: 0:00-24:00\nNe: 0:00-24:00'
                },
                {
                    name: 'Každý den 8-20',
                    value: 'Po: 8:00-20:00\nÚt: 8:00-20:00\nSt: 8:00-20:00\nČt: 8:00-20:00\nPá: 8:00-20:00\nSo: 8:00-20:00\nNe: 8:00-20:00'
                }
            ];
            
            const $templateButtons = $('<div class="saw-opening-hours-templates"></div>');
            
            templates.forEach(function(template) {
                const $btn = $('<button type="button" class="saw-template-btn">' + template.name + '</button>');
                
                $btn.on('click', function(e) {
                    e.preventDefault();
                    $openingHoursField.val(template.value);
                });
                
                $templateButtons.append($btn);
            });
            
            $openingHoursField.before($templateButtons);
        }
        
        // ================================================
        // POSTAL CODE FORMATTING
        // ================================================
        $('#postal_code').on('blur', function() {
            let value = $(this).val().replace(/\s+/g, '');
            
            if (value.length === 5) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
                $(this).val(value);
            }
        });
        
        // ================================================
        // PHONE FORMATTING
        // ================================================
        $('#phone').on('blur', function() {
            let value = $(this).val().replace(/\s+/g, '');
            
            if (value.length === 9 && !value.startsWith('+')) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
                $(this).val(value);
            }
        });
        
        // ================================================
        // HEADQUARTERS WARNING
        // ================================================
        $('#is_headquarters').on('change', function() {
            if ($(this).is(':checked')) {
                if (!confirm('Označením této pobočky jako hlavní sídlo bude zrušeno označení u všech ostatních poboček. Pokračovat?')) {
                    $(this).prop('checked', false);
                }
            }
        });
        
        // ================================================
        // FORM VALIDATION
        // ================================================
        $('.saw-branch-form').on('submit', function(e) {
            const name = $('#name').val().trim();
            
            if (!name) {
                e.preventDefault();
                alert('Vyplňte název pobočky!');
                $('#name').focus();
                return false;
            }
            
            const email = $('#email').val().trim();
            if (email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Neplatná emailová adresa!');
                    $('#email').focus();
                    return false;
                }
            }
            
            return true;
        });
        
    });
    
})(jQuery);
