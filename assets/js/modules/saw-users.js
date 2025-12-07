/**
 * SAW Users Module - Departments Management
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     14.1.0 - Search by name AND department_number
 */
(function($) {
    'use strict';
    
    function initDepartmentsManager() {
        var $role = $('#role');
        var $branch = $('#branch_id');
        var $deptList = $('#departments-list');
        var $deptControls = $('.saw-dept-controls');
        var $search = $('#dept-search');
        var $selectAll = $('#select-all-dept');
        var $selectedSpan = $('#dept-selected');
        var $totalSpan = $('#dept-total');
        var $counter = $('#dept-counter');
        
        if (!$role.length) return;
        if ($role.data('saw-v13')) return;
        $role.data('saw-v13', true);
        
        // Read from DOM data-existing attribute
        var existingDeptIds = [];
        var raw = $deptList.attr('data-existing');
        if (raw) {
            try {
                var arr = JSON.parse(raw);
                if (Array.isArray(arr)) {
                    existingDeptIds = arr.map(function(x) { return parseInt(x, 10); }).filter(function(x) { return x > 0; });
                }
            } catch(e) {}
        }
        console.log('[SAW Users] existingDeptIds from DOM:', existingDeptIds);
        
        var ajaxUrl = window.sawGlobal ? window.sawGlobal.ajaxurl : '/wp-admin/admin-ajax.php';
        var ajaxNonce = window.sawGlobal ? window.sawGlobal.nonce : '';
        var allDepts = [];
        
        function updateFields() {
            var role = $role.val();
            $('.field-customer').toggle(role === 'super_admin');
            $('.field-branch-departments').toggle(['super_manager', 'manager', 'terminal'].indexOf(role) !== -1);
            $('.field-pin').toggle(role === 'terminal');
            $('.field-departments-row').toggle(role === 'manager');
            $('.field-branch-required').toggle(['manager', 'super_manager', 'terminal'].indexOf(role) !== -1);
            
            if (role === 'manager' && $branch.val() && allDepts.length === 0) {
                loadDepts();
            } else if (role !== 'manager') {
                $deptList.html('<p style="padding:20px;text-align:center;">Nejprve vyberte pobočku</p>');
                $deptControls.hide();
            }
        }
        
        function loadDepts() {
            var branchId = $branch.val();
            if (!branchId || $role.val() !== 'manager') return;
            
            $deptList.html('<p style="padding:20px;text-align:center;">Načítám...</p>');
            $deptControls.hide();
            
            $.post(ajaxUrl, {
                action: 'saw_get_departments_by_branch',
                branch_id: branchId,
                nonce: ajaxNonce
            }).done(function(res) {
                if (res.success && res.data.departments) {
                    allDepts = res.data.departments;
                    renderDepts();
                } else {
                    $deptList.html('<p style="padding:20px;text-align:center;color:#d63638;">Chyba</p>');
                }
            });
        }
        
        function renderDepts() {
            if (!allDepts.length) {
                $deptList.html('<p style="padding:20px;text-align:center;">Žádná oddělení</p>');
                $deptControls.hide();
                return;
            }
            
            var html = '';
            for (var i = 0; i < allDepts.length; i++) {
                var d = allDepts[i];
                var id = parseInt(d.id, 10);
                var checked = existingDeptIds.indexOf(id) !== -1;
                if (checked) console.log('[SAW Users] PRE-CHECK:', id, d.name);
                
                html += '<div class="saw-dept-item' + (checked ? ' selected' : '') + '" data-id="' + id + '" data-name="' + (d.name||'').toLowerCase() + '" data-number="' + (d.department_number||'').toLowerCase() + '">' +
                    '<input type="checkbox" name="department_ids[]" value="' + id + '"' + (checked ? ' checked' : '') + ' id="dept-' + id + '">' +
                    '<label for="dept-' + id + '">' + (d.department_number ? d.department_number + ' | ' : '') + d.name + '</label>' +
                '</div>';
            }
            
            $deptList.html(html);
            $deptControls.show();
            updateCounter();
        }
        
        function updateCounter() {
            var vis = $deptList.find('.saw-dept-item:visible').length;
            var sel = $deptList.find('.saw-dept-item:visible input:checked').length;
            $selectedSpan.text(sel);
            $totalSpan.text(vis);
            $counter.css('background', sel === 0 ? '#d63638' : (sel === vis ? '#00a32a' : '#0073aa'));
            $selectAll.prop('checked', vis > 0 && sel === vis);
        }
        
        $role.off('.saw').on('change.saw', updateFields);
        $branch.off('.saw').on('change.saw', function() { allDepts = []; loadDepts(); });
        $search.off('.saw').on('input.saw', function() {
            var t = $(this).val().toLowerCase();
            $deptList.find('.saw-dept-item').each(function() {
                var name = ($(this).data('name') || '').toString().toLowerCase();
                var num = ($(this).data('number') || '').toString().toLowerCase();
                $(this).toggle(name.indexOf(t) !== -1 || num.indexOf(t) !== -1);
            });
            updateCounter();
        });
        $selectAll.off('.saw').on('change.saw', function() {
            $deptList.find('.saw-dept-item:visible input').prop('checked', this.checked).trigger('change');
        });
        $deptList.off('.saw').on('click.saw', '.saw-dept-item', function(e) {
            if (e.target.tagName !== 'INPUT') {
                $(this).find('input').click();
            }
        }).on('change.saw', 'input', function() {
            $(this).closest('.saw-dept-item').toggleClass('selected', this.checked);
            updateCounter();
        });
        
        updateFields();
        if (existingDeptIds.length && $branch.val() && $role.val() === 'manager') {
            loadDepts();
        }
    }
    
    $(document).ready(function() {
        if ($('#role').length && window.sawGlobal) initDepartmentsManager();
    });
    
    $(document).on('saw:page-loaded', function() {
        setTimeout(function() {
            $('#role').removeData('saw-v13');
            if ($('#role').length && window.sawGlobal) initDepartmentsManager();
        }, 100);
    });
    
})(jQuery);