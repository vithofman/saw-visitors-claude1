/**
 * Visits Module Scripts
 * 
 * Handles client-side validation, schedule management, and host selection.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @since       1.0.0
 * @version     3.3.0 - FIXED: Company field toggle now properly clears hidden input for searchable select
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ================================================
        // COMPANY FIELD TOGGLE (FIXED for searchable select)
        // ================================================
        const $companyRow = $('.field-company-row');
        const $companySelect = $('#company_id');

        function toggleCompanyField() {
            const hasCompany = $('input[name="has_company"]:checked').val();

            if (hasCompany === '1') {
                // Show company field
                $companyRow.slideDown();
                
                // Set required on the search input (visible element after select-create init)
                const $searchInput = $('#company_id-search');
                if ($searchInput.length) {
                    $searchInput.prop('required', true);
                } else {
                    // Fallback for non-searchable select
                    $companySelect.prop('required', true);
                }
            } else {
                // Hide company field
                $companyRow.slideUp();
                
                // Remove required
                const $searchInput = $('#company_id-search');
                if ($searchInput.length) {
                    $searchInput.prop('required', false);
                } else {
                    $companySelect.prop('required', false);
                }
                
                // Clear ALL related inputs:
                // 1. Original select (for non-searchable fallback)
                $companySelect.val('');
                
                // 2. Hidden input (stores actual value for searchable select)
                $('#company_id-hidden').val('');
                
                // 3. Search input text (visible search field)
                $('#company_id-search').val('');
                
                // 4. Reset dropdown selection visual state
                $('.saw-select-search-item').removeClass('selected');
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
    function initHostsManager() {
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
        branchSelect.off('change.hosts-manager');
        searchInput.off('input.hosts-manager');
        selectAllCb.off('change.hosts-manager');

        // Bind event handlers with namespace
        branchSelect.on('change.hosts-manager', loadHosts);
        searchInput.on('input.hosts-manager', filterHosts);
        selectAllCb.on('change.hosts-manager', toggleAll);

        // Load hosts if branch is already selected
        const currentBranchId = branchSelect.val();
        
        if (currentBranchId) {
            // Small delay to ensure DOM is fully ready
            setTimeout(function() {
                loadHosts();
            }, 100);
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
    $(document).on('saw:page-loaded', function () {
        // Wait a bit for wp_localize_script to update window.sawVisits
        setTimeout(function() {
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
        init: function() {
            // Kontrola existence kontejneru
            if (!$('#visitors-list-container').length) {
                return;
            }
            
            // Načtení dat z PHP
            if (typeof window.sawVisitorsData !== 'undefined' && window.sawVisitorsData !== null) {
                this.state.mode = window.sawVisitorsData.mode || 'create';
                this.state.visitId = window.sawVisitorsData.visitId || null;
                
                // Načtení existujících návštěvníků (EDIT mode)
                if (Array.isArray(window.sawVisitorsData.existingVisitors)) {
                    console.log('[SAWVisitorsManager] Loading existing visitors:', window.sawVisitorsData.existingVisitors.length);
                    
                    this.state.visitors = window.sawVisitorsData.existingVisitors.map(v => ({
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
                    console.log('[SAWVisitorsManager] No existing visitors array found');
                }
            } else {
                console.log('[SAWVisitorsManager] window.sawVisitorsData not available yet');
            }
            
            // Bind events
            this.bindEvents();
            
            // Initial render
            this.render();
        },
        
        // ========================================
        // EVENT BINDING
        // ========================================
        bindEvents: function() {
            const self = this;
            const namespace = '.saw-visitors-manager';
            
            // Odstranit staré handlery před přidáním nových
            $('#btn-add-visitor').off(namespace);
            $('#btn-visitor-back, #btn-visitor-cancel').off(namespace);
            $('#btn-visitor-save').off(namespace);
            $('#visitors-list').off(namespace);
            $('.saw-visit-form').off(namespace);
            
            // Tlačítko přidat
            $('#btn-add-visitor').on('click' + namespace, function() {
                self.openNestedForm(null);
            });
            
            // Tlačítko zpět
            $('#btn-visitor-back, #btn-visitor-cancel').on('click' + namespace, function() {
                self.closeNestedForm();
            });
            
            // Tlačítko uložit návštěvníka
            $('#btn-visitor-save').on('click' + namespace, function() {
                self.saveVisitor();
            });
            
            // Delegované eventy pro karty
            $('#visitors-list').on('click' + namespace, '.btn-edit', function() {
                const tempId = $(this).closest('.saw-visitor-card').data('temp-id');
                const index = self.findIndexByTempId(tempId);
                if (index !== -1) {
                    self.openNestedForm(index);
                }
            });
            
            $('#visitors-list').on('click' + namespace, '.btn-delete', function() {
                const tempId = $(this).closest('.saw-visitor-card').data('temp-id');
                const index = self.findIndexByTempId(tempId);
                if (index !== -1) {
                    self.removeVisitor(index);
                }
            });
            
            // Před odesláním formuláře - serializace
            $('.saw-visit-form').on('submit' + namespace, function() {
                self.serializeToInput();
            });
        },
        
        // ========================================
        // NESTED FORM OPERATIONS
        // ========================================
        openNestedForm: function(index) {
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
        },
        
        closeNestedForm: function() {
            this.state.editingIndex = null;
            
            // Skrytí nested form, zobrazení hlavního
            $('#visitor-nested-form').hide();
            $('#visit-main-form, .saw-form-section').show();
            
            // Vyčištění
            this.clearNestedForm();
            
            // Scrollování na sekci návštěvníků
            const $visitorsSection = $('.saw-visitors-section');
            if ($visitorsSection.length) {
                setTimeout(function() {
                    const offset = $visitorsSection.offset();
                    if (offset) {
                        $('html, body').animate({
                            scrollTop: offset.top - 20 // 20px offset od horního okraje
                        }, 300);
                    }
                }, 100); // Malé zpoždění pro zajištění, že je DOM aktualizován
            }
        },
        
        clearNestedForm: function() {
            $('#visitor-first-name').val('');
            $('#visitor-last-name').val('');
            $('#visitor-email').val('');
            $('#visitor-phone').val('');
            $('#visitor-position').val('');
            
            // Reset error states
            $('.saw-nested-form-body .saw-input').removeClass('has-error');
            $('.saw-nested-form-body .saw-field-error').remove();
        },
        
        fillNestedForm: function(visitor) {
            $('#visitor-first-name').val(visitor.first_name || '');
            $('#visitor-last-name').val(visitor.last_name || '');
            $('#visitor-email').val(visitor.email || '');
            $('#visitor-phone').val(visitor.phone || '');
            $('#visitor-position').val(visitor.position || '');
        },
        
        getNestedFormData: function() {
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
        validate: function(data) {
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
        
        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
        
        showErrors: function(errors) {
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
        saveVisitor: function() {
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
        
        removeVisitor: function(index) {
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
        
        hasChanges: function(visitor) {
            if (!visitor._dbId) return true;
            
            const original = this.state.originalVisitors.find(v => v._dbId === visitor._dbId);
            if (!original) return true;
            
            const fields = ['first_name', 'last_name', 'email', 'phone', 'position'];
            
            return fields.some(field => visitor[field] !== original[field]);
        },
        
        // ========================================
        // HELPERS
        // ========================================
        findIndexByTempId: function(tempId) {
            return this.state.visitors.findIndex(v => v._tempId === tempId);
        },
        
        getActiveCount: function() {
            return this.state.visitors.filter(v => v._status !== 'deleted').length;
        },
        
        getCountLabel: function(count) {
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
        render: function() {
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
        
        renderCard: function(visitor) {
            const statusClass = visitor._status === 'new' ? 'is-new' : 
                               (visitor._status === 'modified' ? 'is-modified' : '');
            
            const name = visitor.first_name + ' ' + visitor.last_name;
            
            // Detail řádek
            let details = [];
            if (visitor.email) {
                details.push('<span>📧 ' + this.escapeHtml(visitor.email) + '</span>');
            }
            if (visitor.phone) {
                details.push('<span>📞 ' + this.escapeHtml(visitor.phone) + '</span>');
            }
            if (visitor.position) {
                details.push('<span>💼 ' + this.escapeHtml(visitor.position) + '</span>');
            }
            
            const newBadge = visitor._status === 'new' 
                ? '<span class="saw-badge-new">Nový</span>' 
                : '';
            
            return `
                <div class="saw-visitor-card ${statusClass}" data-temp-id="${visitor._tempId}">
                    <div class="saw-visitor-card-info">
                        <div class="saw-visitor-card-name">
                            👤 ${this.escapeHtml(name)}
                            ${newBadge}
                        </div>
                        <div class="saw-visitor-card-details">
                            ${details.join('')}
                        </div>
                    </div>
                    <div class="saw-visitor-card-actions">
                        <button type="button" class="btn-edit" title="Upravit">✏️</button>
                        <button type="button" class="btn-delete" title="Odebrat">🗑️</button>
                    </div>
                </div>
            `;
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // ========================================
        // SERIALIZACE
        // ========================================
        serializeToInput: function() {
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
    
    // Funkce pro čekání na data (podobně jako waitForSawVisits)
    function waitForVisitorsData(callback, maxAttempts = 20, initialDelay = 0) {
        let attempts = 0;
        const baseDelay = 50;
        
        function check() {
            attempts++;
            
            // Kontrola existence kontejneru
            const containerExists = $('#visitors-list-container').length > 0;
            
            // Kontrola existence dat - musí být objekt, ne undefined/null
            const dataExists = typeof window.sawVisitorsData !== 'undefined' && 
                              window.sawVisitorsData !== null &&
                              typeof window.sawVisitorsData === 'object';
            
            console.log('[SAWVisitorsManager] Check attempt', attempts, '/', maxAttempts, {
                containerExists: containerExists,
                dataExists: dataExists,
                sawVisitorsData: typeof window.sawVisitorsData
            });
            
            if (containerExists && dataExists) {
                console.log('[SAWVisitorsManager] Data found, initializing');
                callback();
            } else if (attempts < maxAttempts) {
                const delay = attempts === 1 && initialDelay > 0 
                    ? initialDelay 
                    : baseDelay * Math.pow(2, Math.min(attempts - 1, 5));
                setTimeout(check, delay);
            } else {
                // Inicializace i bez dat (pro CREATE režim)
                console.log('[SAWVisitorsManager] Timeout waiting for data, initializing anyway');
                callback();
            }
        }
        
        if (initialDelay > 0) {
            setTimeout(check, initialDelay);
        } else {
            check();
        }
    }
    
    // Inicializace VisitorsManager
    $(document).ready(function() {
        waitForVisitorsData(function() {
            SAWVisitorsManager.init();
        });
    });
    
    // Re-inicializace při AJAX načtení
    $(document).on('saw:page-loaded saw:content-loaded', function() {
        console.log('[SAWVisitorsManager] AJAX page loaded event received');
        // Větší delay a více pokusů pro AJAX načtení - script tag se může vykonat později
        waitForVisitorsData(function() {
            SAWVisitorsManager.init();
        }, 30, 300); // 30 pokusů, počáteční delay 300ms
    });

})(jQuery);