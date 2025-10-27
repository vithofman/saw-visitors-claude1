/**
 * SAW Visitors - Admin JavaScript
 * 
 * Základní JavaScriptové funkce pro admin rozhraní
 */

(function($) {
    'use strict';

    /**
     * Inicializace při načtení stránky
     */
    $(document).ready(function() {
        console.log('SAW Visitors Admin JS loaded');
        
        // Inicializovat komponenty
        initConfirmDialogs();
        initAjaxForms();
    });

    /**
     * Potvrzovací dialogy pro destruktivní akce
     */
    function initConfirmDialogs() {
        // Přidat potvrzení pro všechny tlačítka s class "saw-confirm"
        $('.saw-confirm').on('click', function(e) {
            var message = $(this).data('confirm-message') || 'Opravdu chcete provést tuto akci?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * AJAX formuláře
     */
    function initAjaxForms() {
        $('.saw-ajax-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Disable tlačítko během odeslání
            $submitBtn.prop('disabled', true).text('Odesílám...');
            
            // Získat data z formuláře
            var formData = new FormData(this);
            formData.append('action', $form.data('action'));
            formData.append('nonce', sawVisitorsAdmin.nonce);
            
            // Odeslat AJAX request
            $.ajax({
                url: sawVisitorsAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message || 'Operace úspěšná');
                        
                        // Pokud je definován callback, zavolat ho
                        if (typeof $form.data('success-callback') === 'function') {
                            $form.data('success-callback')(response.data);
                        }
                        
                        // Reload stránky pokud je to požadováno
                        if (response.data.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        showNotice('error', response.data.message || 'Došlo k chybě');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotice('error', 'Došlo k chybě při komunikaci se serverem');
                },
                complete: function() {
                    // Enable tlačítko zpět
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * Zobrazení notifikace
     * 
     * @param {string} type - Typ notifikace (success, error, warning, info)
     * @param {string} message - Text zprávy
     */
    function showNotice(type, message) {
        var $notice = $('<div>')
            .addClass('notice notice-' + type + ' is-dismissible')
            .append($('<p>').text(message));
        
        // Přidat dismiss button
        $notice.append(
            $('<button>')
                .addClass('notice-dismiss')
                .attr('type', 'button')
                .on('click', function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                })
        );
        
        // Vložit na začátek wrap elementu
        $('.wrap').prepend($notice);
        
        // Auto-hide po 5 sekundách
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * AJAX helper funkce
     * 
     * @param {string} action - WordPress AJAX action
     * @param {object} data - Data k odeslání
     * @param {function} callback - Success callback
     * @param {function} errorCallback - Error callback
     */
    window.sawAjax = function(action, data, callback, errorCallback) {
        data = data || {};
        data.action = action;
        data.nonce = sawVisitorsAdmin.nonce;
        
        $.ajax({
            url: sawVisitorsAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (typeof callback === 'function') {
                        callback(response.data);
                    }
                } else {
                    if (typeof errorCallback === 'function') {
                        errorCallback(response.data);
                    } else {
                        showNotice('error', response.data.message || 'Došlo k chybě');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                if (typeof errorCallback === 'function') {
                    errorCallback({ message: 'Chyba komunikace se serverem' });
                } else {
                    showNotice('error', 'Došlo k chybě při komunikaci se serverem');
                }
            }
        });
    };

    /**
     * Export do globálního scope pro použití v jiných scriptech
     */
    window.sawVisitorsAdminHelper = {
        showNotice: showNotice,
        ajax: window.sawAjax
    };

})(jQuery);