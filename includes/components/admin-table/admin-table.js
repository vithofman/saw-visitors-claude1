/**
 * Admin Table Component JavaScript
 *
 * Handles clickable table rows with AJAX sidebar loading.
 * ZERO ANIMATIONS - Everything instant.
 * FIXED: Removed close button handler - now handled by sidebar.js
 *
 * @package    SAW_Visitors
 * @subpackage Components
 * @version    4.1.0 - REMOVED close button handler conflict
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
            console.error('openSidebarAjax: Missing required parameters', {id, mode, entity});
            return;
        }
        
        console.log('📊 Opening sidebar via AJAX:', {id, mode, entity});
        
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
                console.log('✅ AJAX Success:', response);
                hideLoadingOverlay();
                
                if (!response.success) {
                    console.error('❌ AJAX returned success: false');
                    fallbackToFullReload(buildUrl(entity, id, mode));
                    return;
                }
                
                if (!response.data || !response.data.html || response.data.html.length < 100) {
                    console.error('❌ Invalid HTML response', {
                        hasData: !!response.data,
                        hasHtml: !!(response.data && response.data.html),
                        htmlLength: response.data && response.data.html ? response.data.html.length : 0
                    });
                    fallbackToFullReload(buildUrl(entity, id, mode));
                    return;
                }
                
                console.log('📦 Received HTML length:', response.data.html.length);
                
                let $wrapper = $('.saw-sidebar-wrapper');
                
                if (!$wrapper.length) {
                    console.log('🆕 Creating new sidebar wrapper');
                    $wrapper = $('<div class="saw-sidebar-wrapper"></div>');
                    $('body').append($wrapper);
                }
                
                console.log('📝 Inserting HTML into wrapper');
                $wrapper.html(response.data.html);
                
                // INSTANT: Add both classes at same time
                $('.saw-admin-table-split').addClass('has-sidebar');
                $wrapper.addClass('active');
                
                // Update URL
                const newUrl = buildUrl(entity, id, mode);
                console.log('🔗 Updating URL to:', newUrl);
                window.history.pushState(
                    { id: id, mode: mode, entity: entity },
                    '',
                    newUrl
                );
                
                updateActiveRow(id);
                console.log('✅ Sidebar opened successfully');
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', {xhr, status, error});
                hideLoadingOverlay();
                fallbackToFullReload(buildUrl(entity, id, mode));
            }
        });
    };
    
    /**
     * Close sidebar
     *
     * INSTANT: No timeout, immediate cleanup
     * NOTE: This is called from sidebar.js after handling navigation logic
     *
     * @param {string} listUrl URL to navigate back to list
     * @return {void}
     */
    window.closeSidebar = function(listUrl) {
        console.log('🚪 closeSidebar() called from external');
        
        const $wrapper = $('.saw-sidebar-wrapper');
        
        if (!$wrapper.length) {
            console.log('⚠️ No wrapper found');
            return;
        }
        
        // INSTANT: Remove classes and HTML at same time
        $wrapper.removeClass('active');
        $('.saw-admin-table-split').removeClass('has-sidebar');
        $wrapper.html(''); // NO setTimeout - instant cleanup
        
        $('.saw-admin-table tbody tr').removeClass('saw-row-active');
        
        console.log('✅ Sidebar closed instantly');
    };
    
    /**
     * Show loading overlay
     *
     * @return {void}
     */
    function showLoadingOverlay() {
        let $overlay = $('#saw-loading-overlay');
        
        if (!$overlay.length) {
            $overlay = $('<div id="saw-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);z-index:99998;display:flex;align-items:center;justify-content:center;"><div class="saw-spinner" style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;"></div></div>');
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
            console.log('🔄 Fallback to full reload:', url);
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
            // Ignore clicks on action buttons
            if ($(e.target).closest('.saw-action-buttons, button, a, input, select').length) {
                return;
            }
            
            const $row = $(this);
            const itemId = $row.data('id');
            
            if (!itemId) {
                return;
            }
            
            e.stopPropagation();
            e.preventDefault();
            
            console.log('📊 Admin Table: Row clicked', {id: itemId});
            
            // Modal mode (backward compatible)
            if ($row.attr('data-clickable-row')) {
                const modalId = $row.data('modal');
                
                if (modalId && typeof SAWModal !== 'undefined') {
                    console.log('📲 Opening modal:', modalId);
                    SAWModal.open(modalId, {
                        id: itemId,
                        nonce: (window.sawGlobal && window.sawGlobal.nonce) || window.sawAjaxNonce
                    });
                }
                return;
            }
            
            // Sidebar mode (AJAX)
            const detailUrl = $row.data('detail-url');
            
            if (detailUrl) {
                console.log('📊 Detail URL found:', detailUrl);
                
                const parsed = parseUrl(detailUrl);
                console.log('📊 Parsed URL:', parsed);
                
                if (parsed.entity && parsed.id) {
                    console.log('📊 Calling openSidebarAjax');
                    openSidebarAjax(parsed.id, parsed.mode, parsed.entity);
                } else {
                    console.warn('⚠️ Could not parse URL, falling back to full reload');
                    fallbackToFullReload(detailUrl);
                }
            }
        });
        
        // REMOVED: Close sidebar button handler - now in sidebar.js
        // This prevents double event handlers and conflicts
        
        // Browser back/forward button support
        window.addEventListener('popstate', function(e) {
            console.log('🔙 Browser back/forward detected', e.state);
            
            if (e.state && e.state.id && e.state.entity) {
                console.log('📊 Reopening sidebar from history');
                openSidebarAjax(e.state.id, e.state.mode, e.state.entity);
            } else {
                console.log('🚪 Closing sidebar from history');
                const $wrapper = $('.saw-sidebar-wrapper');
                if ($wrapper.length && $wrapper.hasClass('active')) {
                    $wrapper.removeClass('active');
                    $('.saw-admin-table-split').removeClass('has-sidebar');
                    $wrapper.html(''); // INSTANT cleanup
                    $('.saw-admin-table tbody tr').removeClass('saw-row-active');
                }
            }
        });
        
        console.log('✅ Admin Table initialized');
    }
    
    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        initAdminTable();
    });
    
})(jQuery);