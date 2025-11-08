/**
 * Admin Table Component JavaScript
 *
 * Handles clickable table rows with AJAX sidebar loading.
 * Supports both modal and sidebar modes with smooth navigation.
 *
 * @package    SAW_Visitors
 * @subpackage Components
 * @version    3.0.0 - AJAX SIDEBAR LOADING
 * @since      1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Open sidebar via AJAX
     *
     * @param {number} id    Item ID
     * @param {string} mode  Mode: 'detail' or 'edit'
     * @param {string} entity Entity name (e.g., 'customers')
     * @return {void}
     */
    window.openSidebarAjax = function(id, mode, entity) {
        if (!id || !mode || !entity) {
            return;
        }
        
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
                hideLoadingOverlay();
                
                if (!response.success) {
                    fallbackToFullReload(buildUrl(entity, id, mode));
                    return;
                }
                
                if (!response.data || !response.data.html || response.data.html.length < 100) {
                    fallbackToFullReload(buildUrl(entity, id, mode));
                    return;
                }
                
                let $wrapper = $('.saw-sidebar-wrapper');
                
                if (!$wrapper.length) {
                    $wrapper = $('<div class="saw-sidebar-wrapper"></div>');
                    $('body').append($wrapper);
                }
                
                $wrapper.html(response.data.html);
                
                setTimeout(function() {
                    $wrapper.addClass('active');
                }, 10);
                
                updateActiveRow(id);
                updateUrl(buildUrl(entity, id, mode), {id: id, mode: mode});
            },
            error: function() {
                hideLoadingOverlay();
                fallbackToFullReload(buildUrl(entity, id, mode));
            }
        });
    };
    
    /**
     * Close sidebar
     *
     * @param {string} listUrl URL to navigate back to list
     * @return {void}
     */
    window.closeSidebar = function(listUrl) {
        const $wrapper = $('.saw-sidebar-wrapper');
        
        if (!$wrapper.length) {
            return;
        }
        
        $wrapper.removeClass('active');
        
        setTimeout(function() {
            $wrapper.html('');
        }, 300);
        
        $('.saw-admin-table tbody tr').removeClass('saw-row-active');
        
        if (listUrl) {
            updateUrl(listUrl, {});
        }
    };
    
    /**
     * Show loading overlay
     *
     * @return {void}
     */
    function showLoadingOverlay() {
        let $overlay = $('#saw-loading-overlay');
        
        if (!$overlay.length) {
            $overlay = $('<div id="saw-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);z-index:9998;display:flex;align-items:center;justify-content:center;"><div class="saw-spinner" style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;"></div></div>');
            $('body').append($overlay);
            
            $('<style>@keyframes spin{to{transform:rotate(360deg);}}</style>').appendTo('head');
        }
        
        $overlay.fadeIn(200);
    }
    
    /**
     * Hide loading overlay
     *
     * @return {void}
     */
    function hideLoadingOverlay() {
        $('#saw-loading-overlay').fadeOut(200);
    }
    
    /**
     * Update active row in table
     *
     * @param {number} id Item ID
     * @return {void}
     */
    function updateActiveRow(id) {
        $('.saw-admin-table tbody tr').removeClass('saw-row-active');
        $('.saw-admin-table tbody tr[data-id="' + id + '"]').addClass('saw-row-active');
    }
    
    /**
     * Update browser URL without reload
     *
     * @param {string} url New URL
     * @param {object} state State object
     * @return {void}
     */
    function updateUrl(url, state) {
        if (window.history && window.history.pushState) {
            window.history.pushState(state, '', url);
        }
    }
    
    /**
     * Build URL from entity, id and mode
     *
     * @param {string} entity Entity name
     * @param {number} id Item ID
     * @param {string} mode Mode: 'detail' or 'edit'
     * @return {string}
     */
    function buildUrl(entity, id, mode) {
        const base = '/admin/settings/' + entity + '/';
        
        if (mode === 'edit') {
            return base + id + '/edit';
        }
        
        return base + id + '/';
    }
    
    /**
     * Fallback to full page reload
     *
     * @param {string} url URL to navigate to
     * @return {void}
     */
    function fallbackToFullReload(url) {
        if (url) {
            window.location.href = url;
        }
    }
    
    /**
     * Parse URL to extract entity, id and mode
     *
     * @param {string} url URL to parse
     * @return {object} Parsed data
     */
    function parseUrl(url) {
        const path = url.split('/').filter(function(p) { return p; });
        
        let entity = null;
        let id = 0;
        let mode = 'detail';
        
        for (let i = 0; i < path.length; i++) {
            if (path[i] === 'settings' && path[i + 1]) {
                entity = path[i + 1];
            }
            
            if (!isNaN(path[i]) && parseInt(path[i]) > 0) {
                id = parseInt(path[i]);
                
                if (path[i + 1] === 'edit') {
                    mode = 'edit';
                }
            }
        }
        
        return { entity: entity, id: id, mode: mode };
    }
    
    /**
     * Initialize admin table component
     *
     * @return {void}
     */
    function initAdminTable() {
        $(document).on('click', '.saw-admin-table tbody tr', function(e) {
            if ($(e.target).closest('.saw-action-buttons, button, a, input, select').length) {
                return;
            }
            
            const $row = $(this);
            const itemId = $row.data('id');
            
            if (!itemId) {
                return;
            }
            
            if ($row.attr('data-clickable-row')) {
                const modalId = $row.data('modal');
                
                if (modalId && typeof SAWModal !== 'undefined') {
                    SAWModal.open(modalId, {
                        id: itemId,
                        nonce: (window.sawGlobal && window.sawGlobal.nonce) || window.sawAjaxNonce
                    });
                }
                return;
            }
            
            const detailUrl = $row.data('detail-url');
            
            if (detailUrl) {
                e.preventDefault();
                
                const parsed = parseUrl(detailUrl);
                
                if (parsed.entity && parsed.id) {
                    openSidebarAjax(parsed.id, parsed.mode, parsed.entity);
                } else {
                    fallbackToFullReload(detailUrl);
                }
            }
        });
        
        $(document).on('click', '.saw-sidebar-close', function(e) {
            e.preventDefault();
            
            const listUrl = $(this).attr('href');
            closeSidebar(listUrl);
        });
    }
    
    /**
     * Handle browser back/forward buttons
     *
     * @return {void}
     */
    function initHistoryNavigation() {
        if (!window.history || !window.history.pushState) {
            return;
        }
        
        $(window).on('popstate', function(e) {
            const state = e.originalEvent.state;
            
            if (state && state.id && state.mode) {
                const parsed = parseUrl(window.location.pathname);
                
                if (parsed.entity) {
                    openSidebarAjax(state.id, state.mode, parsed.entity);
                }
            } else {
                closeSidebar(window.location.pathname);
            }
        });
    }
    
    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        initAdminTable();
        initHistoryNavigation();
    });
    
})(jQuery);