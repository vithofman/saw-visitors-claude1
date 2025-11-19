jQuery(document).ready(function ($) {
    const roleSelect = $('#role');
    const branchSelect = $('#branch_id');
    const deptList = $('#departments-list');
    const deptControls = $('.saw-dept-controls');
    const searchInput = $('#dept-search');
    const selectAllCb = $('#select-all-dept');
    const selectedSpan = $('#dept-selected');
    const totalSpan = $('#dept-total');
    const counterDiv = $('#dept-counter');

    let allDepts = [];

    // Data passed from Controller via wp_localize_script
    // sawUsers.existingIds is already an array of integers
    let existingIds = (window.sawUsers && window.sawUsers.existingIds) || [];

    const ajaxUrl = (window.sawGlobal && window.sawGlobal.ajaxurl) || '/wp-admin/admin-ajax.php';
    const ajaxNonce = (window.sawGlobal && window.sawGlobal.nonce) || '';

    roleSelect.on('change', updateFields);
    branchSelect.on('change', loadDepts);
    searchInput.on('input', filterDepts);
    selectAllCb.on('change', toggleAll);

    function updateFields() {
        const role = roleSelect.val();

        $('.field-customer').toggle(role === 'super_admin');
        $('.field-branch-departments').toggle(['super_manager', 'manager', 'terminal'].includes(role));
        $('.field-pin').toggle(role === 'terminal');
        $('.field-departments-row').toggle(role === 'manager');
        $('.field-branch-required').toggle(['manager', 'super_manager', 'terminal'].includes(role));

        if (role === 'manager' && branchSelect.val()) {
            // If we already have departments loaded (e.g. from previous load), don't reload unnecessarily
            // unless list is empty
            if (deptList.children().length <= 1) {
                loadDepts();
            }
        } else if (role !== 'manager') {
            deptList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Nejprve vyberte pobočku výše</p>');
            deptControls.hide();
        }
    }

    function loadDepts() {
        const branchId = branchSelect.val();
        const role = roleSelect.val();

        if (role !== 'manager' || !branchId) {
            deptList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Nejprve vyberte pobočku výše</p>');
            deptControls.hide();
            return;
        }

        deptList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;"><span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> Načítám...</p>');
        deptControls.hide();

        // Add spin animation if not exists
        if ($('#saw-spin-style').length === 0) {
            $('<style id="saw-spin-style">@keyframes spin { to { transform: rotate(360deg); }}</style>').appendTo('head');
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'saw_get_departments_by_branch',
                branch_id: branchId,
                nonce: ajaxNonce
            },
            success: function (response) {
                if (response.success) {
                    allDepts = response.data.departments;
                    renderDepts(allDepts);
                    if (allDepts.length > 0) {
                        deptControls.show();
                        updateCounter();
                    }
                } else {
                    deptList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">' + (response.data.message || 'Chyba') + '</p>');
                }
            },
            error: function () {
                deptList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">Chyba při načítání</p>');
            }
        });
    }

    function renderDepts(depts) {
        if (depts.length === 0) {
            deptList.html('<p class="saw-text-muted" style="padding: 40px 20px; margin: 0; text-align: center;">Pobočka nemá žádná oddělení</p>');
            deptControls.hide();
            return;
        }

        let html = '';
        depts.forEach(d => {
            // Convert d.id to integer for proper comparison
            const deptId = parseInt(d.id, 10);
            const checked = existingIds.includes(deptId);

            // Format: "111 | Name" or just "Name"
            const label = d.department_number
                ? `<span class="saw-dept-number">${d.department_number}</span><span class="saw-dept-separator">|</span><span class="saw-dept-name">${d.name}</span>`
                : `<span class="saw-dept-name">${d.name}</span>`;

            html += `<div class="saw-dept-item ${checked ? 'selected' : ''}" data-id="${deptId}" data-name="${d.name.toLowerCase()}" data-number="${(d.department_number || '').toLowerCase()}">
                <input type="checkbox" name="department_ids[]" value="${deptId}" ${checked ? 'checked' : ''} id="dept-${deptId}">
                <label for="dept-${deptId}">${label}</label>
            </div>`;
        });

        deptList.html(html);

        // Click on row toggles checkbox
        $('.saw-dept-item').on('click', function (e) {
            // Don't trigger if clicking directly on checkbox
            if (e.target.type !== 'checkbox') {
                const cb = $(this).find('input[type="checkbox"]');
                cb.prop('checked', !cb.prop('checked')).trigger('change');
            }
        });

        deptList.on('change', 'input[type="checkbox"]', function () {
            $(this).closest('.saw-dept-item').toggleClass('selected', this.checked);
            updateCounter();
            updateSelectAllState();
        });
    }

    function filterDepts() {
        const term = searchInput.val().toLowerCase().trim();

        $('.saw-dept-item').each(function () {
            const $item = $(this);
            const name = $item.data('name');
            const number = $item.data('number');

            // Safe check if number exists
            const numberStr = number ? String(number) : '';

            const matches = name.includes(term) || numberStr.includes(term);
            $item.toggle(matches);
        });

        updateCounter();
    }

    function toggleAll() {
        const checked = selectAllCb.prop('checked');
        $('.saw-dept-item:visible input[type="checkbox"]').prop('checked', checked).trigger('change');
    }

    function updateCounter() {
        const visible = $('.saw-dept-item:visible').length;
        const selected = $('.saw-dept-item:visible input[type="checkbox"]:checked').length;

        selectedSpan.text(selected);
        totalSpan.text(visible);

        // Change color based on selection
        if (selected === 0) {
            counterDiv.css('background', '#d63638'); // red
        } else if (selected === visible) {
            counterDiv.css('background', '#00a32a'); // green
        } else {
            counterDiv.css('background', '#0073aa'); // blue
        }
    }

    function updateSelectAllState() {
        const visible = $('.saw-dept-item:visible').length;
        const selected = $('.saw-dept-item:visible input[type="checkbox"]:checked').length;

        selectAllCb.prop('checked', visible > 0 && selected === visible);
    }

    // Initialize on load
    updateFields();

    // If we have existing IDs and branch is selected, trigger load to show them
    if (existingIds.length > 0 && branchSelect.val() && roleSelect.val() === 'manager') {
        loadDepts();
    }
});
