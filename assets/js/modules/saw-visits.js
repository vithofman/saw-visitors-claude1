/**
 * Visits Module Scripts
 * 
 * Handles client-side validation, schedule management, and host selection.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @since       1.0.0
 * @version     3.6.0 - FIXED: company_id selectors for searchable select component
 * 
 * CHANGELOG v3.6.0:
 * - Fixed: company_id hidden input selector (was #company_id-hidden, now uses [name="company_id"])
 * - Fixed: company_id search input selector (was #company_id-search, now #saw-select-company_id-search)
 * - The select-create component uses 'saw-select-' prefix for generated elements
 * 
 * CHANGELOG v3.5.0:
 * - Fixed: Visitors not displaying when navigating from detail to edit via AJAX
 * - Root cause: jQuery .data() caches values, returns stale data after DOM updates
 * - Solution: Replace .data() with .attr() + JSON.parse() in SAWVisitorsManager
 * - Added: Support for window.sawVisitorsFormData (inline script data injection)
 * - Added: Event handler for saw:init-visitors-manager custom event
 */

(function ($) {
    'use strict';

    // ================================================
    // COMPANY FIELD TOGGLE (FIXED v3.7.0)
    // ================================================

    /**
     * Get all company field elements (select-create component)
     * ⭐ FIX v3.8.0: Field name changed to 'visit_company_selection' to prevent browser autocomplete
     */
    function getCompanyElements() {
        return {
            $hidden: $('input[type="hidden"][name="visit_company_selection"].saw-select-create-value'),
            $select: $('#saw-select-visit_company_selection'),
            $search: $('#saw-select-visit_company_selection-search'),
            $dropdown: $('#saw-select-visit_company_selection-dropdown')
        };
    }

    /**
     * Toggle company field visibility based on has_company radio
     */
    function toggleCompanyField() {
        const hasCompany = $('input[name="has_company"]:checked').val();
        const $companyRow = $('.field-company-row');

        if (!$companyRow.length) return;

        const elements = getCompanyElements();

        if (hasCompany === '1') {
            // Show company field - Legal person
            $companyRow.slideDown(200);

            // Set required on the search input (visible element after select-create init)
            if (elements.$search.length) {
                elements.$search.prop('required', true);
            } else if (elements.$select.length) {
                elements.$select.prop('required', true);
            }
        } else {
            // Hide company field - Physical person
            $companyRow.slideUp(200);

            // Remove required
            if (elements.$search.length) {
                elements.$search.prop('required', false);
            } else if (elements.$select.length) {
                elements.$select.prop('required', false);
            }

            // Clear ALL related inputs:
            // 1. Hidden input (stores actual value for searchable select)
            if (elements.$hidden.length) {
                elements.$hidden.val('');
            }

            // 2. Original select (for non-searchable fallback)
            if (elements.$select.length) {
                elements.$select.val('');
            }

            // 3. Search input text (visible search field)
            if (elements.$search.length) {
                elements.$search.val('');
            }

            // 4. Reset dropdown selection visual state
            if (elements.$dropdown.length) {
                elements.$dropdown.find('.saw-select-search-item').removeClass('selected');
            }
        }
    }

    /**
     * Initialize company field toggle
     */
    function initCompanyToggle() {
        const $companyRow = $('.field-company-row');
        if (!$companyRow.length) return;

        // Wait for select-create to initialize
        setTimeout(function () {
            // Remove old handlers
            $('input[name="has_company"]').off('change.companyToggle');

            // Bind new handler
            $('input[name="has_company"]').on('change.companyToggle', toggleCompanyField);

            // Set initial state
            toggleCompanyField();
        }, 200);
    }

    // Ensure company_id is properly included in form submission
    function ensureCompanyIdOnSubmit() {
        $('.saw-visit-form').off('submit.ensureCompanyId').on('submit.ensureCompanyId', function (e) {
            const hasCompany = $('input[name="has_company"]:checked').val();

            if (hasCompany === '1') {
                // Legal person - ensure company_id is set
                // ⭐ FIX v3.8.0: Use new field name 'visit_company_selection'
                const $hiddenInput = $('input[type="hidden"][name="visit_company_selection"].saw-select-create-value');
                const $searchInput = $('#saw-select-visit_company_selection-search');

                if ($hiddenInput.length) {
                    const companyId = $hiddenInput.val();

                    // If hidden input is empty but search input has text, try to find the value
                    if (!companyId && $searchInput.length && $searchInput.val()) {
                        console.warn('[VISITS] Company search has text but hidden input is empty - this may cause validation issues');
                    }

                    console.log('[VISITS] Form submit - has_company:', hasCompany, 'company_id:', companyId);
                } else {
                    // This might happen if select-create component didn't initialize
                    // Check if original select has value
                    const $originalSelect = $('#saw-select-visit_company_selection');
                    if ($originalSelect.length && $originalSelect.val()) {
                        console.log('[VISITS] Form submit - using original select value:', $originalSelect.val());
                    } else {
                        console.warn('[VISITS] Legal person selected but no company_id input found');
                    }
                }
            } else {
                console.log('[VISITS] Form submit - Physical person selected');
            }
        });
    }

    // ================================================
    // PREVENT AUTOCOMPLETE FROM OVERWRITING COMPANY
    // ================================================
    function preventAutocompleteCompanyOverwrite() {
        const $emailInput = $('#invitation_email');
        // ⭐ FIX v3.8.0: Use new field name 'visit_company_selection' to prevent browser autocomplete
        const $companyHidden = $('input[type="hidden"][name="visit_company_selection"].saw-select-create-value');
        const $companySearch = $('#saw-select-visit_company_selection-search');

        if (!$emailInput.length || !$companyHidden.length) return;

        // Store original company value when user manually selects it
        let userSelectedCompany = null;
        let companyValueBeforeEmailChange = null;

        // Mark company as user-selected when manually changed
        $companyHidden.on('change', function () {
            const currentVal = $(this).val();
            if (currentVal && currentVal !== '') {
                userSelectedCompany = currentVal;
                console.log('[VISITS] Company manually selected:', currentVal);
            }
        });

        // Also track when user selects from dropdown
        $(document).on('click', '#saw-select-visit_company_selection-dropdown .saw-select-search-item', function () {
            setTimeout(function () {
                const currentVal = $companyHidden.val();
                if (currentVal && currentVal !== '') {
                    userSelectedCompany = currentVal;
                    console.log('[VISITS] Company selected from dropdown:', currentVal);
                }
            }, 100);
        });

        // Before email input changes, save current company value
        $emailInput.on('focus', function () {
            companyValueBeforeEmailChange = $companyHidden.val();
        });

        // After email input changes, check if company was overwritten by autocomplete
        $emailInput.on('input change', function () {
            const currentCompanyVal = $companyHidden.val();

            // If company was manually selected by user, don't let autocomplete override it
            if (userSelectedCompany && currentCompanyVal !== userSelectedCompany) {
                console.warn('[VISITS] Autocomplete tried to overwrite company, restoring user selection');
                $companyHidden.val(userSelectedCompany);

                // Also restore search input if it exists
                if ($companySearch.length) {
                    const selectedOption = $('#saw-select-visit_company_selection option[value="' + userSelectedCompany + '"]');
                    if (selectedOption.length) {
                        $companySearch.val(selectedOption.text());
                    }
                }
            }
            // If company was set before email change and now is different, restore it
            else if (companyValueBeforeEmailChange &&
                companyValueBeforeEmailChange !== '' &&
                currentCompanyVal !== companyValueBeforeEmailChange &&
                !userSelectedCompany) {
                console.warn('[VISITS] Autocomplete changed company, restoring previous value');
                $companyHidden.val(companyValueBeforeEmailChange);

                if ($companySearch.length) {
                    const selectedOption = $('#saw-select-visit_company_selection option[value="' + companyValueBeforeEmailChange + '"]');
                    if (selectedOption.length) {
                        $companySearch.val(selectedOption.text());
                    }
                }
            }
        });
    }

    $(document).ready(function () {
        // Initialize company toggle
        initCompanyToggle();

        // Ensure company_id on form submit
        ensureCompanyIdOnSubmit();

        // Prevent autocomplete from overwriting company
        preventAutocompleteCompanyOverwrite();

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
    // HOSTS MANAGER - FIXED: Používá pobočku z kontextu/hidden inputu
    // ========================================
    function initHostsManager() {
        // ⭐ FIX: Použít hidden input místo disabled selectu
        const branchHiddenInput = $('#branch_id_hidden');
        const branchSelect = $('#branch_id'); // Disabled select pro zobrazení
        const hostList = $('#hosts-list');
        const hostControls = $('.saw-host-controls');
        const searchInput = $('#host-search');
        const selectAllCb = $('#select-all-host');
        const selectedSpan = $('#host-selected');
        const totalSpan = $('#host-total');
        const counterDiv = $('#host-counter');

        // Skip if hosts section doesn't exist (not on visits form)
        if (!hostList.length) {
            return;
        }

        let allHosts = [];

        // Get existing hosts from window.sawVisitsData
        let existingIds = [];

        if (typeof window.sawVisitsData !== 'undefined' && window.sawVisitsData !== null) {
            if (Array.isArray(window.sawVisitsData.existing_hosts)) {
                existingIds = window.sawVisitsData.existing_hosts.map(id => parseInt(id)).filter(id => !isNaN(id));
            }
        }

        // Get AJAX URL and nonce from sawGlobal (always available)
        const ajaxUrl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl)
            ? window.sawGlobal.ajaxurl
            : '/wp-admin/admin-ajax.php';

        const ajaxNonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce)
            ? window.sawGlobal.nonce
            : '';

        // Validate nonce is available
        if (!ajaxNonce) {
            hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">❌ Chyba: Nelze ověřit požadavek. Zkuste obnovit stránku.</p>');
            return;
        }

        // Remove existing handlers to prevent duplicates
        searchInput.off('input.hosts-manager');
        selectAllCb.off('change.hosts-manager');

        // Bind event handlers with namespace (branch select je disabled, takže change event není potřeba)
        searchInput.on('input.hosts-manager', filterHosts);
        selectAllCb.on('change.hosts-manager', toggleAll);

        /**
         * ⭐ FIX v3.7.0: Získat pobočku z více zdrojů (priorita: hidden input > disabled select)
         */
        function getBranchId() {
            // 1. Zkusit hidden input (nejspolehlivější)
            if (branchHiddenInput.length) {
                const val = branchHiddenInput.val();
                if (val && val !== '' && val !== '0') {
                    const branchId = parseInt(val);
                    if (!isNaN(branchId) && branchId > 0) {
                        console.log('[Hosts Manager] Branch ID from hidden input:', branchId);
                        return branchId;
                    }
                }
            }

            // 2. Zkusit disabled select (pro zobrazení)
            if (branchSelect.length) {
                const val = branchSelect.val();
                if (val && val !== '' && val !== '0') {
                    const branchId = parseInt(val);
                    if (!isNaN(branchId) && branchId > 0) {
                        console.log('[Hosts Manager] Branch ID from select:', branchId);
                        // Aktualizovat hidden input pro příští použití
                        if (branchHiddenInput.length) {
                            branchHiddenInput.val(branchId);
                        }
                        return branchId;
                    }
                }
            }

            console.warn('[Hosts Manager] No branch ID found');
            return null;
        }

        // Load hosts if branch is available
        const currentBranchId = getBranchId();

        if (currentBranchId) {
            console.log('[Hosts Manager] Initializing with branch ID:', currentBranchId);
            // Small delay to ensure DOM is fully ready
            setTimeout(function () {
                loadHosts();
            }, 150);
        } else {
            console.warn('[Hosts Manager] No branch ID available on init');
            // Pokud není pobočka, zobrazit informativní zprávu
            hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">⚠️ Pobočka není nastavena. Zkontrolujte branchswitcher.</p>');
            hostControls.hide();
        }

        function loadHosts() {
            const branchId = getBranchId();

            if (!branchId) {
                console.warn('[Hosts Manager] loadHosts() called but no branch ID');
                hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">⚠️ Pobočka není nastavena. Zkontrolujte branchswitcher.</p>');
                hostControls.hide();
                return;
            }

            console.log('[Hosts Manager] Loading hosts for branch ID:', branchId);
            hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;"><span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span> Načítám uživatele...</p>');

            // ⭐ FIX: Aktualizovat hidden input, pokud ještě není nastaven
            if (branchHiddenInput.length && !branchHiddenInput.val()) {
                branchHiddenInput.val(branchId);
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saw_get_hosts_by_branch',
                    nonce: ajaxNonce,
                    branch_id: branchId
                },
                success: function (response) {
                    if (response.success && response.data && response.data.hosts) {
                        allHosts = response.data.hosts;
                        renderHosts();
                        hostControls.show();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Žádní uživatelé nenalezeni';
                        hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">' + message + '</p>');
                        hostControls.hide();
                    }
                },
                error: function (xhr, status, error) {
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
            let html = '';

            // Use existingIds from closure
            const currentExistingIds = existingIds;

            $.each(allHosts, function (index, h) {
                const hostId = parseInt(h.id);
                const checked = currentExistingIds.includes(hostId);

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
                callback();
            } else if (attempts < maxAttempts) {
                // Exponential backoff: 50ms, 100ms, 200ms, 400ms, ...
                const delay = baseDelay * Math.pow(2, Math.min(attempts - 1, 5));
                setTimeout(check, delay);
            } else {
                // Initialize anyway, will handle gracefully
                callback();
            }
        }

        check();
    }

    // Initialize on document ready
    $(document).ready(function () {
        waitForSawVisits(initHostsManager);
    });

    // Re-initialize when new content is loaded via AJAX (e.g., sidebar form)
    $(document).on('saw:page-loaded saw:sidebar-loaded', function () {
        // Re-initialize company toggle
        setTimeout(initCompanyToggle, 50);

        // Re-ensure company_id on form submit
        setTimeout(ensureCompanyIdOnSubmit, 50);

        // Re-initialize autocomplete protection
        setTimeout(preventAutocompleteCompanyOverwrite, 100);

        // Wait a bit for wp_localize_script to update window.sawVisits
        setTimeout(function () {
            waitForSawVisits(initHostsManager, 10); // Fewer attempts for re-init
        }, 50);
    });

    // ========================================
    // VISITORS MANAGER
    // ========================================
    const SAWVisitorsManager = {

        // ========================================
        // KONFIGURACE
        // ========================================
        config: {
            maxVisitors: 50,
            requiredFields: ['first_name', 'last_name'],
        },

        // ========================================
        // STAV
        // ========================================
        state: {
            visitors: [],
            originalVisitors: [],
            mode: 'create',
            visitId: null,
            editingIndex: null,
        },

        // ========================================
        // INICIALIZACE
        // ========================================
        /**
         * Initialize VisitorsManager
         * 
         * v3.5.0 FIX: jQuery .data() cache bug
         * - Priority 1: Use window.sawVisitorsFormData (injected by inline script)
         * - Priority 2: Use .attr() instead of .data() to read form attributes
         * 
         * WHY: jQuery .data() caches values on first access. When navigating
         * from CREATE to EDIT form via AJAX, .data() returns cached empty array
         * instead of fresh data from DOM. Using .attr() always reads from DOM.
         */
        init: function () {
            console.log('[SAWVisitorsManager] init() called - v3.6.0');

            // VŽDY resetovat state před načtením nových dat (důležité při AJAX načtení)
            this.state = {
                visitors: [],
                originalVisitors: [],
                mode: 'create',
                visitId: null,
                editingIndex: null,
            };

            // Kontrola existence kontejneru
            if (!$('#visitors-list-container').length) {
                console.log('[SAWVisitorsManager] Container not found, skipping init');
                return;
            }

            const $form = $('.saw-visit-form');
            let visitorsData = [];
            let translations = {};

            // ================================================
            // ⭐ PRIORITA 1: Data z window objektu (inline script)
            // Toto obchází jQuery .data() cache problém úplně
            // ================================================
            if (window.sawVisitorsFormData && typeof window.sawVisitorsFormData === 'object') {
                console.log('[SAWVisitorsManager] Using data from window.sawVisitorsFormData (preferred)');

                this.state.mode = window.sawVisitorsFormData.mode || 'create';
                this.state.visitId = window.sawVisitorsFormData.visitId || null;
                visitorsData = window.sawVisitorsFormData.existingVisitors || [];
                translations = window.sawVisitorsFormData.translations || {};

                console.log('[SAWVisitorsManager] Loaded from window object:', {
                    mode: this.state.mode,
                    visitId: this.state.visitId,
                    visitorsCount: visitorsData.length
                });

                // Vyčistit po použití (prevence opakovaného použití starých dat)
                delete window.sawVisitorsFormData;
            }
            // ================================================
            // ⭐ PRIORITA 2: Fallback na data atributy
            // KRITICKÉ: Použít .attr() místo .data()
            // .data() cachuje hodnoty a vrací staré data při AJAX navigaci!
            // ================================================
            else if ($form.length) {
                console.log('[SAWVisitorsManager] Using data from form attributes (with .attr() fix)');

                // ✅ OPRAVA v3.5.0: .attr() místo .data() - vždy čte aktuální hodnotu z DOM
                const mode = $form.attr('data-visitors-mode') || 'create';
                const visitIdRaw = $form.attr('data-visit-id');
                const visitId = visitIdRaw ? parseInt(visitIdRaw) : null;

                const visitorsDataRaw = $form.attr('data-visitors-data');
                const translationsRaw = $form.attr('data-visitors-translations');

                this.state.mode = mode;
                this.state.visitId = visitId;

                // Bezpečné parsování JSON (.attr() vrací string, .data() auto-parsuje)
                if (visitorsDataRaw) {
                    try {
                        const parsed = JSON.parse(visitorsDataRaw);
                        if (Array.isArray(parsed)) {
                            visitorsData = parsed;
                        }
                    } catch (e) {
                        console.error('[SAWVisitorsManager] Failed to parse visitors data:', e);
                        visitorsData = [];
                    }
                }

                if (translationsRaw) {
                    try {
                        const parsedTranslations = JSON.parse(translationsRaw);
                        if (typeof parsedTranslations === 'object') {
                            translations = parsedTranslations;
                        }
                    } catch (e) {
                        console.error('[SAWVisitorsManager] Failed to parse translations:', e);
                        translations = {};
                    }
                }

                console.log('[SAWVisitorsManager] Loaded from data attributes:', {
                    mode: mode,
                    visitId: this.state.visitId,
                    visitorsCount: visitorsData.length
                });
            }

            // Uložení překladů do window pro kompatibilitu s ostatním kódem
            window.sawVisitorsData = window.sawVisitorsData || {};
            window.sawVisitorsData.translations = translations;

            // Načtení existujících návštěvníků (EDIT mode)
            if (Array.isArray(visitorsData) && visitorsData.length > 0) {
                console.log('[SAWVisitorsManager] Loading existing visitors:', visitorsData.length);

                this.state.visitors = visitorsData.map(v => ({
                    _tempId: 'existing_' + v.id,
                    _dbId: parseInt(v.id),
                    _status: 'existing',
                    first_name: v.first_name || '',
                    last_name: v.last_name || '',
                    email: v.email || '',
                    phone: v.phone || '',
                    position: v.position || '',
                }));

                // Uložení originálu pro detekci změn
                this.state.originalVisitors = JSON.parse(JSON.stringify(this.state.visitors));

                console.log('[SAWVisitorsManager] Loaded', this.state.visitors.length, 'visitors into state');
            } else {
                console.log('[SAWVisitorsManager] No existing visitors found');
            }

            // Bind events
            this.bindEvents();

            // Initial render
            this.render();
        },

        // ========================================
        // EVENT BINDING
        // ========================================
        bindEvents: function () {
            const self = this;
            const namespace = '.saw-visitors-manager';

            // Odstranit staré handlery před přidáním nových
            $('#btn-add-visitor').off(namespace);
            $('#btn-visitor-back, #btn-visitor-cancel').off(namespace);
            $('#btn-visitor-save').off(namespace);
            $('#visitors-list').off(namespace);
            $('.saw-visit-form').off(namespace);

            // Tlačítko přidat
            $('#btn-add-visitor').on('click' + namespace, function () {
                self.openNestedForm(null);
            });

            // Tlačítko zpět
            $('#btn-visitor-back, #btn-visitor-cancel').on('click' + namespace, function () {
                self.closeNestedForm();
            });

            // Tlačítko uložit návštěvníka
            $('#btn-visitor-save').on('click' + namespace, function () {
                self.saveVisitor();
            });

            // Delegované eventy pro karty
            $('#visitors-list').on('click' + namespace, '.btn-edit', function () {
                const tempId = $(this).closest('.saw-visitor-card').data('temp-id');
                const index = self.findIndexByTempId(tempId);
                if (index !== -1) {
                    self.openNestedForm(index);
                }
            });

            $('#visitors-list').on('click' + namespace, '.btn-delete', function () {
                const tempId = $(this).closest('.saw-visitor-card').data('temp-id');
                const index = self.findIndexByTempId(tempId);
                if (index !== -1) {
                    self.removeVisitor(index);
                }
            });

            // Před odesláním formuláře - serializace
            $('.saw-visit-form').on('submit' + namespace, function () {
                self.serializeToInput();
            });
        },

        // ========================================
        // NESTED FORM OPERATIONS
        // ========================================
        openNestedForm: function (index) {
            const t = window.sawVisitorsData?.translations || {};

            this.state.editingIndex = index;

            // Nastavení titulku
            if (index === null) {
                $('#visitor-form-title').text('👤 ' + (t.title_add || 'Přidat návštěvníka'));
                $('#btn-visitor-save').text('✓ ' + (t.btn_add || 'Přidat návštěvníka'));
                this.clearNestedForm();
            } else {
                $('#visitor-form-title').text('👤 ' + (t.title_edit || 'Upravit návštěvníka'));
                $('#btn-visitor-save').text('✓ ' + (t.btn_save || 'Uložit návštěvníka'));
                this.fillNestedForm(this.state.visitors[index]);
            }

            // Zobrazení
            $('#visit-main-form, .saw-form-section').hide();
            $('#visitor-nested-form').show();
            $('#visitor-first-name').focus();

            // ⭐ Hide FAB when nested form is open
            $('.sa-sidebar-fab-container, .sa-sidebar-fab-container--form, .sa-sidebar-fab').hide();
        },


        closeNestedForm: function () {
            this.state.editingIndex = null;

            // Skrytí nested form, zobrazení hlavního
            $('#visitor-nested-form').hide();
            $('#visit-main-form, .saw-form-section').show();

            // ⭐ Show FAB when nested form is closed
            $('.sa-sidebar-fab-container, .sa-sidebar-fab-container--form, .sa-sidebar-fab').show();

            // Vyčištění
            this.clearNestedForm();


            // ========================================
            // OPRAVENO: Scrollování na sekci návštěvníků
            // Scrolluje sidebar content (ne html/body)
            // ========================================
            const $visitorsSection = $('.saw-visitors-section');
            if ($visitorsSection.length) {
                setTimeout(function () {
                    // Najít scroll container (sidebar content)
                    const $scrollContainer = $visitorsSection.closest('.saw-sidebar-content');

                    if ($scrollContainer.length) {
                        // Jsme v sidebaru - scrolluj sidebar content
                        const containerScrollTop = $scrollContainer.scrollTop();
                        const containerOffset = $scrollContainer.offset().top;
                        const sectionOffset = $visitorsSection.offset().top;

                        // Vypočítat pozici sekce relativně k aktuálnímu scrollu
                        const scrollTo = containerScrollTop + (sectionOffset - containerOffset) - 20;

                        $scrollContainer.animate({
                            scrollTop: Math.max(0, scrollTo)
                        }, 300);
                    } else {
                        // Fallback: jsme na samostatné stránce
                        const offset = $visitorsSection.offset();
                        if (offset) {
                            $('html, body').animate({
                                scrollTop: offset.top - 20
                            }, 300);
                        }
                    }
                }, 150); // Mírně delší zpoždění pro DOM update
            }
        },

        clearNestedForm: function () {
            $('#visitor-first-name').val('');
            $('#visitor-last-name').val('');
            $('#visitor-email').val('');
            $('#visitor-phone').val('');
            $('#visitor-position').val('');

            // Reset error states
            $('.saw-nested-form-body .saw-input').removeClass('has-error');
            $('.saw-nested-form-body .saw-field-error').remove();
        },

        fillNestedForm: function (visitor) {
            $('#visitor-first-name').val(visitor.first_name || '');
            $('#visitor-last-name').val(visitor.last_name || '');
            $('#visitor-email').val(visitor.email || '');
            $('#visitor-phone').val(visitor.phone || '');
            $('#visitor-position').val(visitor.position || '');
        },

        getNestedFormData: function () {
            return {
                first_name: $('#visitor-first-name').val().trim(),
                last_name: $('#visitor-last-name').val().trim(),
                email: $('#visitor-email').val().trim(),
                phone: $('#visitor-phone').val().trim(),
                position: $('#visitor-position').val().trim(),
            };
        },

        // ========================================
        // VALIDACE
        // ========================================
        validate: function (data) {
            const t = window.sawVisitorsData?.translations || {};
            const errors = [];

            // Povinná pole
            if (!data.first_name) {
                errors.push({ field: 'first_name', message: t.error_required || 'Jméno je povinné' });
            }
            if (!data.last_name) {
                errors.push({ field: 'last_name', message: t.error_required || 'Příjmení je povinné' });
            }

            // Email formát
            if (data.email && !this.isValidEmail(data.email)) {
                errors.push({ field: 'email', message: t.error_email || 'Neplatný formát emailu' });
            }

            // Duplicita emailu
            if (data.email) {
                const duplicate = this.state.visitors.find((v, i) =>
                    v._status !== 'deleted' &&
                    i !== this.state.editingIndex &&
                    v.email &&
                    v.email.toLowerCase() === data.email.toLowerCase()
                );

                if (duplicate) {
                    errors.push({ field: 'email', message: t.error_duplicate || 'Návštěvník s tímto emailem již je v seznamu' });
                }
            }

            return errors;
        },

        isValidEmail: function (email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        showErrors: function (errors) {
            // Reset
            $('.saw-nested-form-body .saw-input').removeClass('has-error');
            $('.saw-nested-form-body .saw-field-error').remove();

            // Zobrazení chyb
            errors.forEach(err => {
                const fieldMap = {
                    'first_name': '#visitor-first-name',
                    'last_name': '#visitor-last-name',
                    'email': '#visitor-email',
                };

                const $field = $(fieldMap[err.field]);
                if ($field.length) {
                    $field.addClass('has-error');
                    $field.after('<div class="saw-field-error">' + err.message + '</div>');
                }
            });
        },

        // ========================================
        // CRUD OPERACE
        // ========================================
        saveVisitor: function () {
            const data = this.getNestedFormData();
            const errors = this.validate(data);

            if (errors.length > 0) {
                this.showErrors(errors);
                return;
            }

            if (this.state.editingIndex === null) {
                // Nový návštěvník
                if (this.getActiveCount() >= this.config.maxVisitors) {
                    alert('Maximální počet návštěvníků je ' + this.config.maxVisitors);
                    return;
                }

                this.state.visitors.push({
                    _tempId: 'temp_' + Date.now(),
                    _dbId: null,
                    _status: 'new',
                    ...data
                });
            } else {
                // Editace existujícího
                const visitor = this.state.visitors[this.state.editingIndex];

                // Aktualizace dat
                Object.assign(visitor, data);

                // Změna statusu (pokud byl existing a změněn)
                if (visitor._status === 'existing') {
                    if (this.hasChanges(visitor)) {
                        visitor._status = 'modified';
                    }
                }
            }

            this.closeNestedForm();
            this.render();
        },

        removeVisitor: function (index) {
            const t = window.sawVisitorsData?.translations || {};

            if (!confirm(t.confirm_delete || 'Opravdu chcete odebrat tohoto návštěvníka?')) {
                return;
            }

            const visitor = this.state.visitors[index];

            if (visitor._status === 'new') {
                // Nový - úplně smazat z pole
                this.state.visitors.splice(index, 1);
            } else {
                // Existující z DB - označit jako deleted
                visitor._status = 'deleted';
            }

            this.render();
        },

        hasChanges: function (visitor) {
            if (!visitor._dbId) return true;

            const original = this.state.originalVisitors.find(v => v._dbId === visitor._dbId);
            if (!original) return true;

            const fields = ['first_name', 'last_name', 'email', 'phone', 'position'];

            return fields.some(field => visitor[field] !== original[field]);
        },

        // ========================================
        // HELPERS
        // ========================================
        findIndexByTempId: function (tempId) {
            return this.state.visitors.findIndex(v => v._tempId === tempId);
        },

        getActiveCount: function () {
            return this.state.visitors.filter(v => v._status !== 'deleted').length;
        },

        getCountLabel: function (count) {
            const t = window.sawVisitorsData?.translations || {};

            if (count === 1) {
                return t.person_singular || 'návštěvník';
            } else if (count >= 2 && count <= 4) {
                return t.person_few || 'návštěvníci';
            } else {
                return t.person_many || 'návštěvníků';
            }
        },

        // ========================================
        // RENDERING
        // ========================================
        render: function () {
            const activeVisitors = this.state.visitors.filter(v => v._status !== 'deleted');
            const count = activeVisitors.length;

            console.log('[SAWVisitorsManager] Rendering:', {
                total: this.state.visitors.length,
                active: count,
                visitors: this.state.visitors
            });

            // Empty state vs list
            if (count === 0) {
                $('#visitors-empty-state').show();
                $('#visitors-list').hide();
                $('#visitors-counter').hide();
            } else {
                $('#visitors-empty-state').hide();
                $('#visitors-list').show();
                $('#visitors-counter').show();

                // Render cards
                let html = '';
                activeVisitors.forEach(visitor => {
                    html += this.renderCard(visitor);
                });
                $('#visitors-list').html(html);

                // Update counter
                $('#visitors-count').text(count);
                $('#visitors-count-label').text(this.getCountLabel(count));
            }

            // Aktualizace hidden inputu
            this.serializeToInput();
        },

        renderCard: function (visitor) {
            const statusClass = visitor._status === 'new' ? 'is-new' :
                (visitor._status === 'modified' ? 'is-modified' : '');

            const name = visitor.first_name + ' ' + visitor.last_name;

            // Detail badges s SVG ikonami
            let details = [];
            if (visitor.email) {
                details.push(`
                    <span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        ${this.escapeHtml(visitor.email)}
                    </span>
                `);
            }
            if (visitor.phone) {
                details.push(`
                    <span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        ${this.escapeHtml(visitor.phone)}
                    </span>
                `);
            }
            if (visitor.position) {
                details.push(`
                    <span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                        ${this.escapeHtml(visitor.position)}
                    </span>
                `);
            }

            // Badge pro nové návštěvníky
            const newBadge = visitor._status === 'new'
                ? '<span class="saw-badge-new">NOVÝ</span>'
                : '';

            return `
                <div class="saw-visitor-card ${statusClass}" data-temp-id="${visitor._tempId}">
                    <div class="saw-visitor-card-info">
                        <div class="saw-visitor-card-name">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            ${this.escapeHtml(name)}
                            ${newBadge}
                        </div>
                        ${details.length > 0 ? `<div class="saw-visitor-card-details">${details.join('')}</div>` : ''}
                    </div>
                    <div class="saw-visitor-card-actions">
                        <button type="button" class="btn-edit" title="Upravit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button type="button" class="btn-delete" title="Odebrat">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // ========================================
        // SERIALIZACE
        // ========================================
        serializeToInput: function () {
            // Připravit data pro backend
            const dataForBackend = this.state.visitors.map(v => {
                if (v._status === 'deleted') {
                    // Pro smazané stačí ID a status
                    return {
                        _dbId: v._dbId,
                        _status: v._status
                    };
                }

                return {
                    _dbId: v._dbId,
                    _status: v._status,
                    first_name: v.first_name,
                    last_name: v.last_name,
                    email: v.email,
                    phone: v.phone,
                    position: v.position,
                };
            });

            $('#visitors-json-input').val(JSON.stringify(dataForBackend));
        },
    };

    // ========================================
    // WAIT FOR VISITORS DATA
    // ========================================
    /**
     * Wait for visitors data to be available
     * 
     * v3.5.0 FIX: Uses .attr() instead of .data() to check form attributes
     * because .data() caches values and returns stale data after AJAX updates.
     * 
     * @param {Function} callback - Function to call when ready
     * @param {number} maxAttempts - Maximum polling attempts
     * @param {number} initialDelay - Initial delay before first check
     */
    function waitForVisitorsData(callback, maxAttempts = 20, initialDelay = 0) {
        let attempts = 0;
        const baseDelay = 50;

        function check() {
            attempts++;

            // Kontrola existence kontejneru
            const containerExists = $('#visitors-list-container').length > 0;

            // ✅ OPRAVA v3.5.0: Použít .attr() místo .data()
            // .data() cachuje hodnoty a vrací staré data při AJAX navigaci
            const $form = $('.saw-visit-form');
            const formDataExists = $form.length > 0 && $form.attr('data-visitors-mode') !== undefined;

            // Pro EDIT režim: ověřit, že visit ID odpovídá (pokud je v URL nebo sidebaru)
            let visitIdMatches = true;
            if (formDataExists) {
                // ✅ OPRAVA: .attr() místo .data()
                const formVisitIdRaw = $form.attr('data-visit-id');
                const formVisitId = formVisitIdRaw ? parseInt(formVisitIdRaw) : null;

                // Zkontrolovat URL pro očekávané visit ID
                const urlMatch = window.location.pathname.match(/\/visits\/(\d+)\//);
                const urlVisitId = urlMatch ? parseInt(urlMatch[1]) : null;

                // Zkontrolovat sidebar
                const $sidebar = $('.saw-sidebar[data-current-id]');
                // ✅ OPRAVA: .attr() místo .data()
                const sidebarVisitIdRaw = $sidebar.length ? $sidebar.attr('data-current-id') : null;
                const sidebarVisitId = sidebarVisitIdRaw ? parseInt(sidebarVisitIdRaw) : null;

                // Očekávané visit ID (priorita: sidebar > URL)
                const expectedVisitId = sidebarVisitId || urlVisitId;

                // Pokud máme očekávané ID a form ID, musí se shodovat
                if (expectedVisitId && formVisitId && formVisitId !== expectedVisitId) {
                    visitIdMatches = false;
                    console.log('[SAWVisitorsManager] Visit ID mismatch:', {
                        formVisitId: formVisitId,
                        expectedVisitId: expectedVisitId,
                        sidebarVisitId: sidebarVisitId,
                        urlVisitId: urlVisitId
                    });
                }
            }

            const dataExists = formDataExists && visitIdMatches;

            console.log('[SAWVisitorsManager] Check attempt', attempts, '/', maxAttempts, {
                containerExists: containerExists,
                formDataExists: formDataExists,
                visitIdMatches: visitIdMatches,
                dataExists: dataExists
            });

            if (containerExists && dataExists) {
                console.log('[SAWVisitorsManager] Data ready, initializing');
                callback();
            } else if (attempts < maxAttempts) {
                const delay = attempts === 1 && initialDelay > 0
                    ? initialDelay
                    : baseDelay * Math.pow(2, Math.min(attempts - 1, 5));
                setTimeout(check, delay);
            } else {
                // Inicializace i bez dat (pro CREATE režim nebo pokud data nejsou kritická)
                console.warn('[SAWVisitorsManager] Timeout waiting for data, initializing anyway');
                callback();
            }
        }

        if (initialDelay > 0) {
            setTimeout(check, initialDelay);
        } else {
            check();
        }
    }

    // ========================================
    // INITIALIZATION TRIGGERS
    // ========================================

    // Inicializace VisitorsManager na document ready
    $(document).ready(function () {
        waitForVisitorsData(function () {
            SAWVisitorsManager.init();
        });
    });

    // Re-inicializace při AJAX načtení (obecné eventy)
    $(document).on('saw:page-loaded saw:content-loaded', function () {
        console.log('[SAWVisitorsManager] AJAX page loaded event received');
        // Data atributy jsou dostupné okamžitě po vložení HTML
        waitForVisitorsData(function () {
            SAWVisitorsManager.init();
        }, 20, 100); // 20 pokusů, počáteční delay 100ms
    });

    // ⭐ NEW v3.5.0: Přímá inicializace z inline scriptu
    // Toto umožňuje form-template.php přímo triggerovat inicializaci
    // s daty předanými přes window.sawVisitorsFormData
    $(document).on('saw:init-visitors-manager', function () {
        console.log('[SAWVisitorsManager] saw:init-visitors-manager event received');

        // Malý delay pro jistotu, že DOM je kompletně připraven
        setTimeout(function () {
            SAWVisitorsManager.init();
        }, 10);
    });
    // ========================================
    // EXPORT TO GLOBAL SCOPE
    // ========================================
    // Umožňuje přímou inicializaci z inline scriptů
    window.SAWVisitorsManager = SAWVisitorsManager;

})(jQuery);