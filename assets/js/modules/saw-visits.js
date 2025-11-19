/**
 * Visits Module Scripts
 * 
 * Handles client-side validation, schedule management, and host selection.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @since       1.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ================================================
        // COMPANY FIELD TOGGLE
        // ================================================
        const $companyRow = $('.field-company-row');
        const $companySelect = $('#company_id');

        function toggleCompanyField() {
            const hasCompany = $('input[name="has_company"]:checked').val();

            if (hasCompany === '1') {
                $companyRow.slideDown();
                $companySelect.prop('required', true);
            } else {
                $companyRow.slideUp();
                $companySelect.prop('required', false);
                $companySelect.val(''); // Clear value
            }
        }

        // Toggle on radio change
        $('input[name="has_company"]').on('change', toggleCompanyField);

        // Initial state
        toggleCompanyField();

        // ================================================
        // EMAIL VALIDATION
        // ================================================
        $('#email').on('blur', function () {
            const $input = $(this);
            const value = $input.val().trim();

            if (!value) return;

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                alert('Zadejte prosím platný email ve formátu: email@domena.cz');
                $input.focus();
            }
        });

        // ================================================
        // WEBSITE URL VALIDATION
        // ================================================
        $('#website').on('blur', function () {
            const $input = $(this);
            let value = $input.val().trim();

            if (!value) return;

            if (!value.match(/^https?:\/\//i)) {
                value = 'https://' + value;
                $input.val(value);
            }

            try {
                new URL(value);
            } catch (e) {
                alert('Zadejte prosím platnou webovou adresu (např. https://www.firma.cz)');
                $input.focus();
            }
        });

        // ================================================
        // IČO VALIDATION
        // ================================================
        $('#ico').on('blur', function () {
            const $input = $(this);
            const value = $input.val().trim();

            if (!value) return;

            const cleanedValue = value.replace(/\s/g, '');

            if (!/^\d+$/.test(cleanedValue)) {
                alert('IČO musí obsahovat pouze číslice');
                $input.focus();
                return;
            }

            if (cleanedValue.length !== 8) {
                if (!confirm('IČO v ČR má obvykle 8 číslic. Chcete pokračovat s tímto IČO?')) {
                    $input.focus();
                }
            }

            $input.val(cleanedValue);
        });

        $('#ico').on('keypress', function (e) {
            if ($.inArray(e.keyCode, [46, 8, 9, 27, 13]) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }

            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) &&
                (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        // ================================================
        // ZIP CODE FORMATTING
        // ================================================
        $('#zip').on('blur', function () {
            const $input = $(this);
            let value = $input.val().trim();

            if (!value) return;

            value = value.replace(/\s/g, '');

            if (/^\d{5}$/.test(value)) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
                $input.val(value);
            }
        });

        // ================================================
        // PHONE NUMBER FORMATTING
        // ================================================
        $('#phone').on('blur', function () {
            const $input = $(this);
            let value = $input.val().trim();

            if (!value) return;

            value = value.replace(/[^\d+]/g, '');

            if (/^\d{9}$/.test(value)) {
                value = '+420' + value;
            }

            if (value.startsWith('+420') && value.length === 13) {
                value = '+420 ' + value.substring(4, 7) + ' ' + value.substring(7, 10) + ' ' + value.substring(10);
            }

            $input.val(value);
        });
    });

    // ========================================
    // SCHEDULES MANAGER
    // ========================================
    const SAWVisitSchedules = {

        init: function () {
            this.bindEvents();
            this.updateRemoveButtons();
        },

        bindEvents: function () {
            $(document).on('click', '.saw-add-schedule-day-inline', this.addScheduleRow.bind(this));
            $(document).on('click', '.saw-remove-schedule-day', this.removeScheduleRow.bind(this));
            $(document).on('change', '.saw-schedule-date-input', this.validateDates.bind(this));
        },

        addScheduleRow: function (e) {
            e.preventDefault();

            const $container = $('#visit-schedule-container');
            const $lastRow = $container.find('.saw-schedule-row').last();
            const newIndex = $container.find('.saw-schedule-row').length;

            // Získej datum z předchozího řádku
            const lastDate = $lastRow.find('.saw-schedule-date-input').val();

            const $newRow = $lastRow.clone();

            // Vymaž všechny hodnoty
            $newRow.find('input').val('');
            $newRow.attr('data-index', newIndex);

            // Pokud existuje datum, přidej +1 den
            if (lastDate) {
                const date = new Date(lastDate);
                date.setDate(date.getDate() + 1);

                // Formátuj zpět do YYYY-MM-DD
                const nextDate = date.toISOString().split('T')[0];
                $newRow.find('.saw-schedule-date-input').val(nextDate);

                // Zkopíruj časy z předchozího dne
                const lastTimeFrom = $lastRow.find('.saw-schedule-time-input').eq(0).val();
                const lastTimeTo = $lastRow.find('.saw-schedule-time-input').eq(1).val();

                if (lastTimeFrom) {
                    $newRow.find('.saw-schedule-time-input').eq(0).val(lastTimeFrom);
                }
                if (lastTimeTo) {
                    $newRow.find('.saw-schedule-time-input').eq(1).val(lastTimeTo);
                }
            }

            $container.append($newRow);

            this.updateRemoveButtons();

            // Focus na poznámku místo data (datum už je vyplněné)
            $newRow.find('.saw-schedule-notes-input').focus();
        },

        removeScheduleRow: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $row = $button.closest('.saw-schedule-row');
            const $container = $('#visit-schedule-container');

            if ($container.find('.saw-schedule-row').length <= 1) {
                alert('Musí zůstat alespoň jeden den návštěvy.');
                return;
            }

            const hasData = $row.find('input').filter(function () {
                return $(this).val() !== '';
            }).length > 0;

            if (hasData) {
                if (!confirm('Opravdu chcete odstranit tento den?')) {
                    return;
                }
            }

            $row.fadeOut(300, function () {
                $(this).remove();
                SAWVisitSchedules.updateRemoveButtons();
                SAWVisitSchedules.reindexRows();
            });
        },

        updateRemoveButtons: function () {
            const $container = $('#visit-schedule-container');
            const rowCount = $container.find('.saw-schedule-row').length;

            $container.find('.saw-remove-schedule-day').prop('disabled', rowCount === 1);
        },

        reindexRows: function () {
            $('#visit-schedule-container .saw-schedule-row').each(function (index) {
                $(this).attr('data-index', index);
            });
        },

        validateDates: function () {
            const dates = [];
            let hasDuplicates = false;

            $('.saw-schedule-date-input').each(function () {
                const date = $(this).val();

                if (date) {
                    if (dates.includes(date)) {
                        hasDuplicates = true;
                        $(this).addClass('saw-input-error');
                    } else {
                        dates.push(date);
                        $(this).removeClass('saw-input-error');
                    }
                }
            });

            if (hasDuplicates) {
                this.showValidationMessage('Některá data se opakují. Každý den může být zadán pouze jednou.');
            } else {
                this.hideValidationMessage();
            }
        },

        showValidationMessage: function (message) {
            let $msg = $('#schedule-validation-message');

            if (!$msg.length) {
                $msg = $('<div id="schedule-validation-message" class="saw-validation-error"></div>');
                $('#visit-schedule-container').before($msg);
            }

            $msg.text(message).show();
        },

        hideValidationMessage: function () {
            $('#schedule-validation-message').hide();
        }
    };

    // ========================================
    // HOSTS MANAGER
    // ========================================
    $(document).ready(function () {
        const branchSelect = $('#branch_id');
        const hostList = $('#hosts-list');
        const hostControls = $('.saw-host-controls');
        const searchInput = $('#host-search');
        const selectAllCb = $('#select-all-host');
        const selectedSpan = $('#host-selected');
        const totalSpan = $('#host-total');
        const counterDiv = $('#host-counter');

        let allHosts = [];
        // Use localized data instead of global window variable
        let existingIds = (window.sawVisits && window.sawVisits.existing_hosts) ? window.sawVisits.existing_hosts : [];

        const ajaxUrl = (window.sawVisits && window.sawVisits.ajaxurl) || '/wp-admin/admin-ajax.php';
        const ajaxNonce = (window.sawVisits && window.sawVisits.nonce) || '';

        branchSelect.on('change', loadHosts);
        searchInput.on('input', filterHosts);
        selectAllCb.on('change', toggleAll);

        if (branchSelect.val()) {
            loadHosts();
        }

        function loadHosts() {
            const branchId = branchSelect.val();

            if (!branchId) {
                hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Nejprve vyberte pobočku výše</p>');
                hostControls.hide();
                return;
            }

            hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;"><span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span> Načítám uživatele...</p>');

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saw_get_hosts_by_branch',
                    nonce: ajaxNonce,
                    branch_id: branchId
                },
                success: function (response) {
                    if (response.success && response.data.hosts) {
                        allHosts = response.data.hosts;
                        renderHosts();
                        hostControls.show();
                    } else {
                        hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Žádní uživatelé nenalezeni</p>');
                        hostControls.hide();
                    }
                },
                error: function () {
                    hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">❌ Chyba při načítání uživatelů</p>');
                    hostControls.hide();
                }
            });
        }

        function renderHosts() {
            let html = '';

            $.each(allHosts, function (index, h) {
                const hostId = parseInt(h.id);
                const checked = existingIds.includes(hostId);

                const label = `<span class="saw-host-name">${h.first_name} ${h.last_name}</span><span class="saw-host-role">(${h.role})</span>`;

                html += `<div class="saw-host-item ${checked ? 'selected' : ''}" data-id="${hostId}" data-name="${(h.first_name + ' ' + h.last_name).toLowerCase()}" data-role="${h.role.toLowerCase()}">
                    <input type="checkbox" name="hosts[]" value="${hostId}" ${checked ? 'checked' : ''} id="host-${hostId}">
                    <label for="host-${hostId}">${label}</label>
                </div>`;
            });

            hostList.html(html);

            $('.saw-host-item').on('click', function (e) {
                if (e.target.type !== 'checkbox') {
                    const cb = $(this).find('input[type="checkbox"]');
                    cb.prop('checked', !cb.prop('checked')).trigger('change');
                }
            });

            hostList.on('change', 'input[type="checkbox"]', function () {
                $(this).closest('.saw-host-item').toggleClass('selected', this.checked);
                updateCounter();
                updateSelectAllState();
            });

            updateCounter();
            updateSelectAllState();
        }

        function filterHosts() {
            const term = searchInput.val().toLowerCase().trim();

            $('.saw-host-item').each(function () {
                const $item = $(this);
                const name = $item.data('name');
                const role = $item.data('role');

                const matches = name.includes(term) || role.includes(term);
                $item.toggle(matches);
            });

            updateCounter();
        }

        function toggleAll() {
            const checked = selectAllCb.prop('checked');
            $('.saw-host-item:visible input[type="checkbox"]').prop('checked', checked).trigger('change');
        }

        function updateCounter() {
            const visible = $('.saw-host-item:visible').length;
            const selected = $('.saw-host-item:visible input[type="checkbox"]:checked').length;

            selectedSpan.text(selected);
            totalSpan.text(visible);

            if (selected === 0) {
                counterDiv.css('background', '#d63638');
            } else if (selected === visible) {
                counterDiv.css('background', '#00a32a');
            } else {
                counterDiv.css('background', '#0073aa');
            }
        }

        function updateSelectAllState() {
            const visible = $('.saw-host-item:visible').length;
            const selected = $('.saw-host-item:visible input[type="checkbox"]:checked').length;

            selectAllCb.prop('checked', visible > 0 && selected === visible);
        }

        // Initialize schedules
        SAWVisitSchedules.init();
    });

})(jQuery);
