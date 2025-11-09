/**
 * Admin Table Component JavaScript
 *
 * @package    SAW_Visitors
 * @version    4.3.0 - FIXED: Scroll position on correct element
 * @since      1.0.0
 */

(function($) {
    'use strict';
    
    window.openSidebarAjax = function(id, mode, entity) {
        if (!id || !mode || !entity) {
            console.error('openSidebarAjax: Missing parameters');
            return;
        }
        
        console.log('📊 Opening sidebar:', {id, mode, entity});
        
        // CRITICAL: Save scroll position on .saw-table-panel (the scrolling container)
        const $tablePanel = $('.saw-table-panel');
        const scrollPosition = $tablePanel.length ? $tablePanel.scrollTop() : 0;
        console.log('💾 Saved scroll:', scrollPosition);
        
        showLoadingOverlay();
        
        $.ajax({
            url: window.ajaxurl || '/wp-admin/admin-ajax.php',
            method: 'POST',
            timeout: 10000,
            data: {
                action: 'saw_load_sidebar_' + entity,
                nonce: (window.sawGlobal && window.sawGlobal.nonce) || window.sawAjaxNonce,
                id: id,
                mode: mode
            },
            success: function(response) {
                console.log('✅ AJAX Success');
                hideLoadingOverlay();
                
                if (!response.success || !response.data || !response.data.html) {
                    console.error('❌ Invalid response');
                    fallbackToFullReload(buildUrl(entity, id, mode));
                    return;
                }
                
                let $wrapper = $('.saw-sidebar-wrapper');
                
                if (!$wrapper.length) {
                    $wrapper = $('<div class="saw-sidebar-wrapper"></div>');
                    $('body').append($wrapper);
                }
                
                $wrapper.html(response.data.html);
                $('.saw-admin-table-split').addClass('has-sidebar');
                $wrapper.addClass('active');
                
                // CRITICAL: Restore scroll AFTER DOM update
                if ($tablePanel.length) {
                    setTimeout(function() {
                        $tablePanel.scrollTop(scrollPosition);
                        console.log('📍 Restored scroll:', scrollPosition);
                    }, 10);
                }
                
                // Update URL
                window.history.pushState(
                    { id: id, mode: mode, entity: entity },
                    '',
                    buildUrl(entity, id, mode)
                );
                
                updateActiveRow(id);
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', error);
                hideLoadingOverlay();
                fallbackToFullReload(buildUrl(entity, id, mode));
            }
        });
    };
    
    window.closeSidebar = function() {
        const $wrapper = $('.saw-sidebar-wrapper');
        if (!$wrapper.length) return;
        
        $wrapper.removeClass('active');
        $('.saw-admin-table-split').removeClass('has-sidebar');
        $wrapper.html('');
        $('.saw-admin-table tbody tr').removeClass('saw-row-active');
    };
    
    function showLoadingOverlay() {
        let $overlay = $('#saw-loading-overlay');
        if (!$overlay.length) {
            $overlay = $('<div id="saw-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);z-index:99998;display:flex;align-items:center;justify-content:center;"><div class="saw-spinner" style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;"></div></div>');
            $('body').append($overlay);
            $('<style>@keyframes spin{to{transform:rotate(360deg);}}</style>').appendTo('head');
        }
        $overlay.fadeIn(200);
    }
    
    function hideLoadingOverlay() {
        $('#saw-loading-overlay').fadeOut(200);
    }
    
    function updateActiveRow(id) {
        $('.saw-admin-table tbody tr').removeClass('saw-row-active');
        $('.saw-admin-table tbody tr[data-id="' + id + '"]').addClass('saw-row-active');
    }
    
    function buildUrl(entity, id, mode) {
        const base = '/admin/settings/' + entity + '/';
        return mode === 'edit' ? base + id + '/edit' : base + id + '/';
    }
    
    function fallbackToFullReload(url) {
        if (url) window.location.href = url;
    }
    
    function parseUrl(url) {
        const path = url.split('/').filter(p => p);
        let entity = null, id = 0, mode = 'detail';
        
        for (let i = 0; i < path.length; i++) {
            if (path[i] === 'settings' && path[i + 1]) entity = path[i + 1];
            if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                id = parseInt(path[i]);
                if (path[i + 1] === 'edit') mode = 'edit';
            }
        }
        return { entity, id, mode };
    }
    
    function initAdminTable() {
        $(document).on('click', '.saw-admin-table tbody tr', function(e) {
            if ($(e.target).closest('.saw-action-buttons, button, a, input, select').length) return;
            
            const $row = $(this);
            const itemId = $row.data('id');
            if (!itemId) return;
            
            e.stopPropagation();
            e.preventDefault();
            
            const detailUrl = $row.data('detail-url');
            if (detailUrl) {
                const parsed = parseUrl(detailUrl);
                if (parsed.entity && parsed.id) {
                    openSidebarAjax(parsed.id, parsed.mode, parsed.entity);
                }
            }
        });
        
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.id && e.state.entity) {
                openSidebarAjax(e.state.id, e.state.mode, e.state.entity);
            } else {
                const $wrapper = $('.saw-sidebar-wrapper');
                if ($wrapper.length && $wrapper.hasClass('active')) {
                    $wrapper.removeClass('active');
                    $('.saw-admin-table-split').removeClass('has-sidebar');
                    $wrapper.html('');
                    $('.saw-admin-table tbody tr').removeClass('saw-row-active');
                }
            }
        });
    }
    
    $(document).ready(function() {
        initAdminTable();
    });
    
})(jQuery);