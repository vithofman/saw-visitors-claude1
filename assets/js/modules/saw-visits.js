/**
 * Visits Module Scripts
 * 
 * Handles client-side validation, schedule management, and host selection.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @since       1.0.0
 * @version     3.2.0 - FIXED: Improved hosts loading with robust window.sawVisits detection
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
    // HOSTS MANAGER - NUCLEAR DEBUGGING
    // ========================================
    function initHostsManager() {
        console.log('='.repeat(80));
        console.log('INIT HOSTS MANAGER - START');
        console.log('='.repeat(80));
        console.log('window.sawVisits:', JSON.stringify(window.sawVisits, null, 2));
        console.log('typeof window.sawVisits:', typeof window.sawVisits);
        console.log('window.sawVisits === null:', window.sawVisits === null);
        console.log('window.sawVisits === undefined:', window.sawVisits === undefined);
        
        const branchSelect = $('#branch_id');
        const hostList = $('#hosts-list');
        const hostControls = $('.saw-host-controls');
        const searchInput = $('#host-search');
        const selectAllCb = $('#select-all-host');
        const selectedSpan = $('#host-selected');
        const totalSpan = $('#host-total');
        const counterDiv = $('#host-counter');

        // Skip if hosts section doesn't exist (not on visits form)
        if (!branchSelect.length || !hostList.length) {
            console.log('❌ Hosts section not found - branchSelect:', branchSelect.length, 'hostList:', hostList.length);
            return;
        }
        
        console.log('✅ Hosts section found');
        console.log('Branch select value:', branchSelect.val());

        let allHosts = [];
        
        // CRITICAL: Get existing hosts from window.sawVisits with EXTREME validation
        let existingIds = [];
        
        console.log('-'.repeat(80));
        console.log('EXTRACTING EXISTING HOSTS');
        console.log('-'.repeat(80));
        
        // CRITICAL FIX: Use sawVisitsData for existing_hosts (separate from Asset Loader's sawVisits)
        console.log('window.sawVisitsData:', window.sawVisitsData);
        console.log('window.sawVisits:', window.sawVisits);
        
        if (typeof window.sawVisitsData !== 'undefined' && window.sawVisitsData !== null) {
            console.log('✅ window.sawVisitsData EXISTS');
            console.log('window.sawVisitsData.existing_hosts:', window.sawVisitsData.existing_hosts);
            console.log('Type:', typeof window.sawVisitsData.existing_hosts);
            console.log('Is Array:', Array.isArray(window.sawVisitsData.existing_hosts));
            console.log('Length:', window.sawVisitsData.existing_hosts ? window.sawVisitsData.existing_hosts.length : 'N/A');
            
            if (Array.isArray(window.sawVisitsData.existing_hosts)) {
                existingIds = window.sawVisitsData.existing_hosts.map(id => parseInt(id)).filter(id => !isNaN(id));
                console.log('✅ PARSED existing_hosts:', existingIds);
                console.log('✅ COUNT:', existingIds.length);
            } else {
                console.error('❌ window.sawVisitsData.existing_hosts is NOT an array!');
            }
        } else {
            console.error('❌ window.sawVisitsData does NOT exist or is null!');
            console.log('Will proceed with empty existingIds array');
        }
        
        console.log('-'.repeat(80));
        console.log('FINAL existingIds:', existingIds);
        console.log('-'.repeat(80));

        // Get AJAX URL and nonce from sawGlobal (always available)
        const ajaxUrl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
            ? window.sawGlobal.ajaxurl 
            : '/wp-admin/admin-ajax.php';
        
        const ajaxNonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
            ? window.sawGlobal.nonce 
            : '';
        
        // Validate nonce is available
        if (!ajaxNonce) {
            console.error('[Visits] No AJAX nonce available from sawGlobal');
            hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">❌ Chyba: Nelze ověřit požadavek. Zkuste obnovit stránku.</p>');
            return;
        }

        // Remove existing handlers to prevent duplicates
        branchSelect.off('change.hosts-manager');
        searchInput.off('input.hosts-manager');
        selectAllCb.off('change.hosts-manager');

        // Bind event handlers with namespace
        branchSelect.on('change.hosts-manager', loadHosts);
        searchInput.on('input.hosts-manager', filterHosts);
        selectAllCb.on('change.hosts-manager', toggleAll);

        // CRITICAL: Load hosts if branch is already selected
        const currentBranchId = branchSelect.val();
        console.log('Current branch ID:', currentBranchId);
        
        if (currentBranchId) {
            console.log('✅ Branch already selected, triggering loadHosts()');
            // Small delay to ensure DOM is fully ready
            setTimeout(function() {
                loadHosts();
            }, 100);
        } else {
            console.log('⚠️ No branch selected yet');
        }

        function loadHosts() {
            const branchId = branchSelect.val();
            console.log('[Visits] loadHosts called with branchId:', branchId);

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
                    console.log('[Visits] Hosts AJAX response:', response);
                    
                    if (response.success && response.data && response.data.hosts) {
                        allHosts = response.data.hosts;
                        console.log('[Visits] Loaded', allHosts.length, 'hosts');
                        renderHosts();
                        hostControls.show();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Žádní uživatelé nenalezeni';
                        hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">' + message + '</p>');
                        hostControls.hide();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[Visits] Hosts AJAX error:', error, 'Status:', xhr.status, 'Response:', xhr.responseText);
                    
                    let errorMessage = '❌ Chyba při načítání uživatelů';
                    
                    if (xhr.status === 403) {
                        errorMessage = '❌ Chyba: Oprávnění zamítnuto. Možná problém s nonce.';
                    } else if (xhr.status === 0) {
                        errorMessage = '❌ Chyba: Nelze se připojit k serveru.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = '❌ ' + xhr.responseJSON.data.message;
                    }
                    
                    hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">' + errorMessage + '</p>');
                    hostControls.hide();
                }
            });
        }

        function renderHosts() {
            console.log('='.repeat(80));
            console.log('RENDER HOSTS - START');
            console.log('='.repeat(80));
            console.log('Total hosts to render:', allHosts.length);
            console.log('existingIds from closure:', existingIds);
            console.log('Type:', typeof existingIds);
            console.log('Is Array:', Array.isArray(existingIds));
            console.log('Length:', existingIds.length);
            
            if (existingIds.length === 0) {
                console.error('⚠️⚠️⚠️ existingIds is EMPTY! ⚠️⚠️⚠️');
                console.log('Checking window.sawVisitsData again...');
                console.log('window.sawVisitsData:', window.sawVisitsData);
                console.log('window.sawVisitsData.existing_hosts:', window.sawVisitsData ? window.sawVisitsData.existing_hosts : 'N/A');
            }
            
            let html = '';
            
            // Use existingIds from closure
            const currentExistingIds = existingIds;
            
            console.log('-'.repeat(80));
            console.log('RENDERING HOSTS WITH THESE CHECKED IDs:', currentExistingIds);
            console.log('-'.repeat(80));

            $.each(allHosts, function (index, h) {
                const hostId = parseInt(h.id);
                const checked = currentExistingIds.includes(hostId);
                
                if (index < 3 || checked) {
                    console.log(`Host #${index}: ID=${hostId}, Name=${h.first_name} ${h.last_name}, CHECKED=${checked}`);
                }

                // Build label with name, position (if available), and role
                const namePart = `${h.first_name} ${h.last_name}`;
                const positionPart = h.position && h.position.trim() ? ` - ${h.position}` : '';
                const rolePart = `(${h.role})`;
                const label = `<span class="saw-host-name">${namePart}${positionPart}</span><span class="saw-host-role">${rolePart}</span>`;

                // Store searchable data in data attributes for filtering
                const nameLower = namePart.toLowerCase();
                const positionLower = (h.position || '').toLowerCase();
                const roleLower = (h.role || '').toLowerCase();

                html += `<div class="saw-host-item ${checked ? 'selected' : ''}" data-id="${hostId}" data-name="${nameLower}" data-role="${roleLower}" data-position="${positionLower}">
                    <input type="checkbox" name="hosts[]" value="${hostId}" ${checked ? 'checked' : ''} id="host-${hostId}">
                    <label for="host-${hostId}">${label}</label>
                </div>`;
            });

            hostList.html(html);
            
            console.log('✅ HTML GENERATED');
            console.log('Checking rendered checkboxes in DOM...');
            
            setTimeout(function() {
                const checkedCount = $('#hosts-list input[type="checkbox"]:checked').length;
                console.log('Checkboxes in DOM - Total:', $('#hosts-list input[type="checkbox"]').length, 'Checked:', checkedCount);
                
                if (checkedCount === 0 && currentExistingIds.length > 0) {
                    console.error('❌❌❌ CHECKBOXES NOT CHECKED IN DOM! ❌❌❌');
                } else if (checkedCount > 0) {
                    console.log('✅✅✅ CHECKBOXES SUCCESSFULLY CHECKED! ✅✅✅');
                }
            }, 100);
            
            console.log('='.repeat(80));
            console.log('RENDER HOSTS - END');
            console.log('='.repeat(80));

            // Bind click handler for host items
            $('.saw-host-item').on('click', function (e) {
                if (e.target.type !== 'checkbox') {
                    const cb = $(this).find('input[type="checkbox"]');
                    cb.prop('checked', !cb.prop('checked')).trigger('change');
                }
            });

            // Bind change handler for checkboxes
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
                const name = $item.data('name') || '';
                const role = $item.data('role') || '';
                const position = $item.data('position') || '';

                // Search in name, role, and position
                const matches = name.includes(term) || role.includes(term) || position.includes(term);
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
    }

    // ========================================
    // INITIALIZATION
    // ========================================
    
    /**
     * Wait for window.sawVisitsData to be available before initializing
     * Uses polling with exponential backoff for reliability
     */
    function waitForSawVisits(callback, maxAttempts = 20) {
        let attempts = 0;
        const baseDelay = 50; // Start with 50ms
        
        function check() {
            attempts++;
            
            // Check if sawVisitsData exists (contains existing_hosts)
            if (typeof window.sawVisitsData !== 'undefined' && window.sawVisitsData !== null) {
                console.log('[Visits] window.sawVisitsData available after', attempts, 'attempts');
                callback();
            } else if (attempts < maxAttempts) {
                // Exponential backoff: 50ms, 100ms, 200ms, 400ms, ...
                const delay = baseDelay * Math.pow(2, Math.min(attempts - 1, 5));
                console.log('[Visits] window.sawVisitsData not available, retry', attempts, 'of', maxAttempts, 'in', delay, 'ms');
                setTimeout(check, delay);
            } else {
                console.warn('[Visits] window.sawVisitsData not available after', maxAttempts, 'attempts, initializing anyway');
                callback(); // Initialize anyway, will handle gracefully
            }
        }
        
        check();
    }

    // Initialize on document ready
    $(document).ready(function () {
        console.log('[Visits] Document ready, waiting for window.sawVisitsData...');
        waitForSawVisits(initHostsManager);
    });

    // Re-initialize when new content is loaded via AJAX (e.g., sidebar form)
    $(document).on('saw:page-loaded', function () {
        console.log('[Visits] saw:page-loaded event triggered, re-initializing...');
        // Wait a bit for wp_localize_script to update window.sawVisits
        setTimeout(function() {
            waitForSawVisits(initHostsManager, 10); // Fewer attempts for re-init
        }, 50);
    });

})(jQuery);