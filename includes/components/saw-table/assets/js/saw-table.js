/**
 * SAW Table Component JavaScript
 * 
 * Uses sawt- CSS class prefix.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

(function() {
    'use strict';
    
    const SAWTable = {
        // State
        state: {
            entity: null,
            config: null,
            currentId: null,
            sidebarMode: null,
            isLoading: false,
            infiniteScrollPage: 1,
            infiniteScrollLoading: false,
            infiniteScrollHasMore: true,
            allRowIds: [],
        },
        
        // DOM Elements
        elements: {
            page: null,
            table: null,
            tbody: null,
            sidebarWrapper: null,
            sidebar: null,
            backdrop: null,
            loader: null,
        },
        
        // ============================================
        // INITIALIZATION
        // ============================================
        
        init: function() {
            this.elements.page = document.querySelector('.sawt-page');
            if (!this.elements.page) return;
            
            this.state.entity = this.elements.page.dataset.entity;
            if (!this.state.entity) return;
            
            this.state.config = window.sawtConfig?.[this.state.entity] || {};
            
            this.cacheElements();
            this.buildRowIdList();
            this.bindEvents();
            this.initInfiniteScroll();
            this.initFromState();
            
            console.log('SAW Table initialized:', this.state.entity);
        },
        
        cacheElements: function() {
            this.elements.table = document.querySelector('.sawt-table');
            this.elements.tbody = document.querySelector('.sawt-table-body');
            this.elements.sidebarWrapper = document.querySelector('.sawt-sidebar-wrapper');
            this.elements.sidebar = document.querySelector('.sawt-sidebar');
            this.elements.backdrop = document.querySelector('.sawt-sidebar-backdrop');
            this.elements.loader = document.querySelector('.sawt-infinite-scroll-loader');
        },
        
        buildRowIdList: function() {
            const rows = document.querySelectorAll('.sawt-table-row[data-id]');
            this.state.allRowIds = Array.from(rows).map(row => parseInt(row.dataset.id));
        },
        
        // ============================================
        // EVENT BINDING
        // ============================================
        
        bindEvents: function() {
            // Row clicks
            document.addEventListener('click', this.handleRowClick.bind(this));
            
            // Action menu
            document.addEventListener('click', this.handleActionClick.bind(this));
            
            // Backdrop click
            if (this.elements.backdrop) {
                this.elements.backdrop.addEventListener('click', this.closeSidebar.bind(this));
            }
            
            // Keyboard
            document.addEventListener('keydown', this.handleKeydown.bind(this));
            
            // Navigation buttons
            document.addEventListener('click', this.handleNavClick.bind(this));
            
            // Close button
            document.addEventListener('click', this.handleCloseClick.bind(this));
            
            // New button
            const newBtn = document.querySelector('.sawt-btn-primary[data-action="create"]');
            if (newBtn) {
                newBtn.addEventListener('click', this.handleNewClick.bind(this));
            }
            
            // Browser back/forward
            window.addEventListener('popstate', this.handlePopState.bind(this));
        },
        
        // ============================================
        // ROW CLICK
        // ============================================
        
        handleRowClick: function(e) {
            const row = e.target.closest('.sawt-table-row');
            if (!row) return;
            
            // Ignore action buttons
            if (e.target.closest('.sawt-table-actions, .sawt-table-action-menu, button, a, input')) {
                return;
            }
            
            const id = row.dataset.id;
            if (!id) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            this.openDetail(parseInt(id));
        },
        
        // ============================================
        // DETAIL SIDEBAR
        // ============================================
        
        openDetail: function(id) {
            if (this.state.isLoading) return;
            if (this.state.currentId === id && this.state.sidebarMode === 'detail') return;
            
            this.state.currentId = id;
            this.state.sidebarMode = 'detail';
            this.state.isLoading = true;
            
            this.setActiveRow(id);
            this.showSidebar();
            this.showSidebarLoading();
            this.updateUrl(id, 'detail');
            this.loadDetailContent(id);
        },
        
        loadDetailContent: function(id) {
            const config = this.state.config;
            const ajaxAction = `saw_get_${this.state.entity.replace(/-/g, '_')}_detail`;
            
            const formData = new FormData();
            formData.append('action', ajaxAction);
            formData.append('id', id);
            formData.append('nonce', config.nonce);
            
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                this.state.isLoading = false;
                
                if (data.success && data.data.html) {
                    this.setSidebarContent(data.data.html);
                    this.elements.sidebar.dataset.currentId = id;
                } else {
                    this.showSidebarError(data.data?.message || config.i18n?.error || 'Error');
                }
            })
            .catch(error => {
                this.state.isLoading = false;
                console.error('Detail load error:', error);
                this.showSidebarError(config.i18n?.error || 'Error');
            });
        },
        
        // ============================================
        // FORM SIDEBAR
        // ============================================
        
        openForm: function(id = null) {
            if (this.state.isLoading) return;
            
            this.state.currentId = id;
            this.state.sidebarMode = 'form';
            this.state.isLoading = true;
            
            if (id) {
                this.setActiveRow(id);
            } else {
                this.clearActiveRow();
            }
            
            this.showSidebar();
            this.showSidebarLoading();
            this.updateUrl(id, 'form');
            this.loadFormContent(id);
        },
        
        loadFormContent: function(id) {
            const config = this.state.config;
            const ajaxAction = `saw_load_sidebar_${this.state.entity.replace(/-/g, '_')}`;
            
            const formData = new FormData();
            formData.append('action', ajaxAction);
            formData.append('mode', id ? 'edit' : 'create');
            if (id) formData.append('id', id);
            formData.append('nonce', config.nonce);
            
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                this.state.isLoading = false;
                
                if (data.success && data.data.html) {
                    this.setSidebarContent(data.data.html);
                    if (id) this.elements.sidebar.dataset.currentId = id;
                    this.initFormHandlers();
                } else {
                    this.showSidebarError(data.data?.message || 'Error');
                }
            })
            .catch(error => {
                this.state.isLoading = false;
                console.error('Form load error:', error);
                this.showSidebarError('Error');
            });
        },
        
        initFormHandlers: function() {
            const form = this.elements.sidebar.querySelector('form');
            if (!form) return;
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        },
        
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const config = this.state.config;
            
            const isEdit = !!this.state.currentId;
            const ajaxAction = isEdit 
                ? `saw_update_${this.state.entity.replace(/-/g, '_')}`
                : `saw_create_${this.state.entity.replace(/-/g, '_')}`;
            
            formData.append('action', ajaxAction);
            formData.append('nonce', config.nonce);
            
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.textContent;
                submitBtn.textContent = config.i18n?.loading || 'Ukládání...';
            }
            
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }
                
                if (data.success) {
                    this.showToast(config.i18n?.saved || 'Uloženo', 'success');
                    const newId = data.data?.id || this.state.currentId;
                    if (newId) {
                        window.location.href = config.baseUrl + '/' + newId + '/';
                    } else {
                        window.location.reload();
                    }
                } else {
                    this.showFormErrors(data.data?.errors || {});
                    this.showToast(data.data?.message || 'Chyba', 'error');
                }
            })
            .catch(error => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }
                console.error('Form submit error:', error);
                this.showToast('Chyba', 'error');
            });
        },
        
        showFormErrors: function(errors) {
            // Clear previous
            this.elements.sidebar.querySelectorAll('.sawt-field-error').forEach(el => el.remove());
            this.elements.sidebar.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            // Show new
            for (const [field, message] of Object.entries(errors)) {
                const input = this.elements.sidebar.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    const errorEl = document.createElement('div');
                    errorEl.className = 'sawt-field-error';
                    errorEl.textContent = message;
                    input.parentNode.appendChild(errorEl);
                }
            }
        },
        
        // ============================================
        // SIDEBAR MANAGEMENT
        // ============================================
        
        showSidebar: function() {
            if (this.elements.sidebarWrapper) {
                this.elements.sidebarWrapper.classList.add('is-open');
            }
            if (this.elements.page) {
                this.elements.page.classList.add('has-sidebar');
            }
            document.body.classList.add('sawt-sidebar-open');
        },
        
        closeSidebar: function() {
            if (this.elements.sidebarWrapper) {
                this.elements.sidebarWrapper.classList.remove('is-open');
            }
            if (this.elements.page) {
                this.elements.page.classList.remove('has-sidebar');
            }
            document.body.classList.remove('sawt-sidebar-open');
            
            this.state.currentId = null;
            this.state.sidebarMode = null;
            this.clearActiveRow();
            this.updateUrl(null, null);
        },
        
        showSidebarLoading: function() {
            if (this.elements.sidebar) {
                this.elements.sidebar.innerHTML = `
                    <div class="sawt-sidebar-loading is-active">
                        <div class="sawt-spinner sawt-spinner-lg"></div>
                        <span class="sawt-sidebar-loading-text">${this.state.config.i18n?.loading || 'Načítání...'}</span>
                    </div>
                `;
            }
        },
        
        showSidebarError: function(message) {
            if (this.elements.sidebar) {
                this.elements.sidebar.innerHTML = `
                    <div class="sawt-sidebar-content sawt-p-4">
                        <div class="sawt-alert sawt-alert-danger">${message}</div>
                        <button type="button" class="sawt-btn sawt-btn-secondary sawt-mt-4" data-close-sidebar>Zavřít</button>
                    </div>
                `;
            }
        },
        
        setSidebarContent: function(html) {
            if (this.elements.sidebar) {
                this.elements.sidebar.innerHTML = html;
            }
        },
        
        // ============================================
        // NAVIGATION
        // ============================================
        
        handleNavClick: function(e) {
            const btn = e.target.closest('.sawt-sidebar-nav-btn, [data-nav]');
            if (!btn) return;
            
            e.preventDefault();
            const direction = btn.classList.contains('sawt-sidebar-nav-btn') 
                ? (btn.dataset.nav || (btn.textContent.includes('‹') ? 'prev' : 'next'))
                : btn.dataset.nav;
            
            this.navigate(direction);
        },
        
        navigate: function(direction) {
            if (!this.state.currentId) return;
            
            const currentIndex = this.state.allRowIds.indexOf(this.state.currentId);
            if (currentIndex === -1) return;
            
            const newIndex = direction === 'prev' ? currentIndex - 1 : currentIndex + 1;
            if (newIndex < 0 || newIndex >= this.state.allRowIds.length) return;
            
            const newId = this.state.allRowIds[newIndex];
            
            if (this.state.sidebarMode === 'detail') {
                this.openDetail(newId);
            } else if (this.state.sidebarMode === 'form') {
                this.openForm(newId);
            }
        },
        
        // ============================================
        // CLOSE HANDLING
        // ============================================
        
        handleCloseClick: function(e) {
            const btn = e.target.closest('.sawt-sidebar-close, [data-close-sidebar]');
            if (!btn) return;
            
            e.preventDefault();
            this.closeSidebar();
        },
        
        // ============================================
        // ACTION MENU
        // ============================================
        
        handleActionClick: function(e) {
            // Toggle menu
            const toggle = e.target.closest('.sawt-table-action-toggle');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                this.toggleActionMenu(toggle);
                return;
            }
            
            // Close menus outside
            if (!e.target.closest('.sawt-table-actions')) {
                this.closeAllActionMenus();
            }
            
            // Handle action items
            const actionItem = e.target.closest('.sawt-table-action-item');
            if (actionItem) {
                this.handleActionItem(e, actionItem);
            }
        },
        
        toggleActionMenu: function(toggle) {
            const menu = toggle.parentNode.querySelector('.sawt-table-action-menu');
            if (!menu) return;
            
            const isOpen = menu.classList.contains('is-open');
            this.closeAllActionMenus();
            
            if (!isOpen) {
                menu.classList.add('is-open');
            }
        },
        
        closeAllActionMenus: function() {
            document.querySelectorAll('.sawt-table-action-menu.is-open').forEach(menu => {
                menu.classList.remove('is-open');
            });
        },
        
        handleActionItem: function(e, actionItem) {
            const action = actionItem.dataset.action;
            const id = actionItem.dataset.id;
            
            switch (action) {
                case 'view':
                    e.preventDefault();
                    this.openDetail(parseInt(id));
                    break;
                case 'delete':
                    e.preventDefault();
                    this.confirmDelete(actionItem);
                    break;
            }
            
            this.closeAllActionMenus();
        },
        
        confirmDelete: function(actionItem) {
            const id = actionItem.dataset.id;
            const entity = actionItem.dataset.entity;
            const confirmMsg = actionItem.dataset.confirm || 'Opravdu smazat?';
            
            if (!confirm(confirmMsg)) return;
            this.deleteItem(id, entity);
        },
        
        deleteItem: function(id, entity) {
            const config = this.state.config;
            const ajaxAction = `saw_delete_${entity.replace(/-/g, '_')}`;
            
            const formData = new FormData();
            formData.append('action', ajaxAction);
            formData.append('id', id);
            formData.append('nonce', config.nonce);
            
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showToast(config.i18n?.deleted || 'Smazáno', 'success');
                    
                    const row = document.querySelector(`.sawt-table-row[data-id="${id}"]`);
                    if (row) row.remove();
                    
                    if (this.state.currentId === parseInt(id)) {
                        this.closeSidebar();
                    }
                    
                    this.buildRowIdList();
                } else {
                    this.showToast(data.data?.message || 'Chyba', 'error');
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                this.showToast('Chyba', 'error');
            });
        },
        
        // ============================================
        // NEW ITEM
        // ============================================
        
        handleNewClick: function(e) {
            e.preventDefault();
            this.openForm(null);
        },
        
        // ============================================
        // KEYBOARD
        // ============================================
        
        handleKeydown: function(e) {
            if (e.key === 'Escape' && this.state.sidebarMode) {
                e.preventDefault();
                this.closeSidebar();
                return;
            }
            
            if (e.target.matches('input, textarea, select')) return;
            
            if (this.state.sidebarMode === 'detail') {
                if (e.key === 'ArrowUp' || e.key === 'k') {
                    e.preventDefault();
                    this.navigate('prev');
                } else if (e.key === 'ArrowDown' || e.key === 'j') {
                    e.preventDefault();
                    this.navigate('next');
                }
            }
        },
        
        // ============================================
        // ACTIVE ROW
        // ============================================
        
        setActiveRow: function(id) {
            this.clearActiveRow();
            const row = document.querySelector(`.sawt-table-row[data-id="${id}"]`);
            if (row) {
                row.classList.add('is-active');
                this.scrollRowIntoView(row);
            }
        },
        
        clearActiveRow: function() {
            document.querySelectorAll('.sawt-table-row.is-active').forEach(row => {
                row.classList.remove('is-active');
            });
        },
        
        scrollRowIntoView: function(row) {
            const scrollArea = document.querySelector('.sawt-table-scroll');
            if (!scrollArea) return;
            
            const rowRect = row.getBoundingClientRect();
            const areaRect = scrollArea.getBoundingClientRect();
            const isVisible = rowRect.top >= areaRect.top && rowRect.bottom <= areaRect.bottom;
            
            if (!isVisible) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },
        
        // ============================================
        // URL MANAGEMENT
        // ============================================
        
        updateUrl: function(id, mode) {
            const config = this.state.config;
            let url;
            
            if (id && mode === 'detail') {
                url = config.baseUrl + '/' + id + '/';
            } else if (id && mode === 'form') {
                url = config.baseUrl + '/' + id + '/edit';
            } else if (mode === 'form') {
                url = config.baseUrl + '/create';
            } else {
                url = config.baseUrl;
            }
            
            const currentParams = new URLSearchParams(window.location.search);
            if (currentParams.toString()) {
                url += '?' + currentParams.toString();
            }
            
            history.pushState({ id, mode }, '', url);
        },
        
        handlePopState: function(e) {
            const state = e.state;
            
            if (state && state.id && state.mode === 'detail') {
                this.openDetail(state.id);
            } else if (state && state.mode === 'form') {
                this.openForm(state.id);
            } else {
                this.closeSidebar();
            }
        },
        
        initFromState: function() {
            if (this.elements.sidebar && this.elements.sidebar.dataset.currentId) {
                const id = parseInt(this.elements.sidebar.dataset.currentId);
                const mode = this.elements.sidebar.dataset.mode || 'detail';
                
                this.state.currentId = id;
                this.state.sidebarMode = mode;
                this.setActiveRow(id);
            }
        },
        
        // ============================================
        // INFINITE SCROLL
        // ============================================
        
        initInfiniteScroll: function() {
            const config = this.state.config.infiniteScroll;
            if (!config || !config.enabled) return;
            
            const scrollArea = document.querySelector('.sawt-table-scroll');
            const trigger = document.querySelector('.sawt-infinite-scroll-trigger');
            
            if (!scrollArea || !trigger) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.state.infiniteScrollLoading && this.state.infiniteScrollHasMore) {
                        this.loadMoreRows();
                    }
                });
            }, {
                root: scrollArea,
                threshold: config.threshold || 0.6,
            });
            
            observer.observe(trigger);
        },
        
        loadMoreRows: function() {
            if (this.state.infiniteScrollLoading || !this.state.infiniteScrollHasMore) return;
            
            this.state.infiniteScrollLoading = true;
            this.state.infiniteScrollPage++;
            
            const config = this.state.config;
            const ajaxAction = `saw_load_more_${this.state.entity.replace(/-/g, '_')}`;
            
            if (this.elements.loader) {
                this.elements.loader.classList.remove('sawt-hidden');
            }
            
            const params = new URLSearchParams(window.location.search);
            const formData = new FormData();
            formData.append('action', ajaxAction);
            formData.append('page', this.state.infiniteScrollPage);
            formData.append('nonce', config.nonce);
            formData.append('columns', JSON.stringify(config.columns));
            params.forEach((value, key) => formData.append(key, value));
            
            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                this.state.infiniteScrollLoading = false;
                
                if (this.elements.loader) {
                    this.elements.loader.classList.add('sawt-hidden');
                }
                
                if (data.success && data.data.html) {
                    if (this.elements.tbody) {
                        this.elements.tbody.insertAdjacentHTML('beforeend', data.data.html);
                    }
                    this.state.infiniteScrollHasMore = data.data.has_more;
                    this.buildRowIdList();
                } else {
                    this.state.infiniteScrollHasMore = false;
                }
            })
            .catch(error => {
                this.state.infiniteScrollLoading = false;
                console.error('Infinite scroll error:', error);
                if (this.elements.loader) {
                    this.elements.loader.classList.add('sawt-hidden');
                }
            });
        },
        
        // ============================================
        // TOAST
        // ============================================
        
        showToast: function(message, type = 'info') {
            if (typeof window.sawToast === 'function') {
                window.sawToast(message, type);
                return;
            }
            
            const toast = document.createElement('div');
            toast.className = `sawt-toast sawt-toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            requestAnimationFrame(() => toast.classList.add('is-visible'));
            
            setTimeout(() => {
                toast.classList.remove('is-visible');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },
    };
    
    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SAWTable.init());
    } else {
        SAWTable.init();
    }
    
    window.SAWTable = SAWTable;
})();
