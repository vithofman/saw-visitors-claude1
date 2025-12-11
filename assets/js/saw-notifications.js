/**
 * SAW Notifications JavaScript (FIXED)
 *
 * Handles all notification functionality:
 * - Toggle dropdown
 * - Load notifications via AJAX
 * - Mark as read (single and all)
 * - Delete notifications
 * - Real-time polling for new notifications
 * - Click to navigate to related content
 *
 * FIXED: Uses inline styles instead of CSS classes for dropdown toggle
 *
 * @package SAW_Visitors
 * @version 1.1.0
 */

(function() {
    'use strict';
    
    // ================================================================
    // CONFIGURATION
    // ================================================================
    
    const CONFIG = {
        // Polling interval for new notifications (30 seconds)
        pollInterval: 30000,
        
        // Number of notifications to load per page
        pageSize: 15,
        
        // Animation duration
        animationDuration: 200,
        
        // AJAX URL (set by WordPress)
        ajaxUrl: window.sawNotificationsConfig?.ajaxUrl || '/wp-admin/admin-ajax.php',
        
        // Nonce for security
        nonce: window.sawNotificationsConfig?.nonce || '',
    };
    
    // ================================================================
    // STATE
    // ================================================================
    
    let state = {
        isOpen: false,
        isLoading: false,
        notifications: [],
        unreadCount: 0,
        offset: 0,
        hasMore: true,
        pollTimer: null,
    };
    
    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    
    let elements = {
        wrapper: null,
        toggle: null,
        badge: null,
        dropdown: null,
        list: null,
        headerCount: null,
        markAllBtn: null,
        loadMoreBtn: null,
    };
    
    // ================================================================
    // INITIALIZATION
    // ================================================================
    
    /**
     * Initialize notifications system
     */
    function init() {
        // Find DOM elements
        elements.wrapper = document.getElementById('sawNotifications');
        
        if (!elements.wrapper) {
            console.log('[SAW Notifications] Wrapper element not found');
            return;
        }
        
        elements.toggle = document.getElementById('sawNotificationsToggle');
        elements.badge = elements.wrapper.querySelector('.saw-notifications-badge');
        elements.dropdown = document.getElementById('sawNotificationsDropdown');
        elements.list = elements.wrapper.querySelector('.saw-notifications-list');
        elements.headerCount = elements.wrapper.querySelector('.saw-notifications-title-count');
        elements.markAllBtn = elements.wrapper.querySelector('[data-action="mark-all-read"]');
        elements.loadMoreBtn = elements.wrapper.querySelector('.saw-notifications-load-more-btn');
        
        // Bind events
        bindEvents();
        
        // Initial load of unread count
        loadUnreadCount();
        
        // Start polling
        startPolling();
        
        console.log('[SAW Notifications] Initialized successfully');
    }
    
    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Toggle dropdown
        if (elements.toggle) {
            elements.toggle.addEventListener('click', handleToggle);
        }
        
        // Close on outside click
        document.addEventListener('click', handleOutsideClick);
        
        // Close on Escape key
        document.addEventListener('keydown', handleKeydown);
        
        // Mark all as read
        if (elements.markAllBtn) {
            elements.markAllBtn.addEventListener('click', handleMarkAllRead);
        }
        
        // Load more
        if (elements.loadMoreBtn) {
            elements.loadMoreBtn.addEventListener('click', handleLoadMore);
        }
        
        // Delegate click events on notification items
        if (elements.list) {
            elements.list.addEventListener('click', handleItemClick);
        }
    }
    
    // ================================================================
    // EVENT HANDLERS
    // ================================================================
    
    /**
     * Handle toggle button click
     */
    function handleToggle(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('[SAW Notifications] Toggle clicked, isOpen:', state.isOpen);
        
        if (state.isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }
    
    /**
     * Handle click outside dropdown
     */
    function handleOutsideClick(e) {
        if (state.isOpen && elements.wrapper && !elements.wrapper.contains(e.target)) {
            closeDropdown();
        }
    }
    
    /**
     * Handle keyboard events
     */
    function handleKeydown(e) {
        if (e.key === 'Escape' && state.isOpen) {
            closeDropdown();
            elements.toggle?.focus();
        }
    }
    
    /**
     * Handle click on notification item
     */
    function handleItemClick(e) {
        const item = e.target.closest('.saw-notification-item');
        const deleteBtn = e.target.closest('.saw-notification-delete');
        
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const notificationId = deleteBtn.dataset.id;
            deleteNotification(notificationId, item);
            return;
        }
        
        if (item) {
            const notificationId = item.dataset.id;
            const actionUrl = item.dataset.url;
            const isUnread = item.classList.contains('unread');
            
            // Mark as read if unread
            if (isUnread) {
                markAsRead(notificationId, item);
            }
            
            // Navigate to action URL
            if (actionUrl) {
                // Small delay to allow marking as read
                setTimeout(() => {
                    window.location.href = actionUrl;
                }, 100);
            }
        }
    }
    
    /**
     * Handle mark all as read
     */
    function handleMarkAllRead(e) {
        e.preventDefault();
        markAllAsRead();
    }
    
    /**
     * Handle load more
     */
    function handleLoadMore(e) {
        e.preventDefault();
        loadNotifications(true);
    }
    
    // ================================================================
    // DROPDOWN CONTROL - FIXED: Uses inline styles
    // ================================================================
    
    /**
     * Open dropdown
     */
    function openDropdown() {
        if (!elements.dropdown) {
            console.log('[SAW Notifications] Dropdown element not found');
            return;
        }
        
        state.isOpen = true;
        
        // FIXED: Use inline style instead of CSS class
        elements.dropdown.style.display = 'flex';
        
        // Add classes for additional styling (optional)
        elements.toggle?.classList.add('active');
        elements.dropdown.classList.add('open');
        
        // Update aria
        elements.toggle?.setAttribute('aria-expanded', 'true');
        
        console.log('[SAW Notifications] Dropdown opened');
        
        // Load notifications if empty
        if (state.notifications.length === 0) {
            loadNotifications();
        }
        
        // Stop polling while open (user is viewing)
        stopPolling();
    }
    
    /**
     * Close dropdown
     */
    function closeDropdown() {
        if (!elements.dropdown) return;
        
        state.isOpen = false;
        
        // FIXED: Use inline style instead of CSS class
        elements.dropdown.style.display = 'none';
        
        // Remove classes
        elements.toggle?.classList.remove('active');
        elements.dropdown.classList.remove('open');
        
        // Update aria
        elements.toggle?.setAttribute('aria-expanded', 'false');
        
        console.log('[SAW Notifications] Dropdown closed');
        
        // Resume polling
        startPolling();
    }
    
    // ================================================================
    // AJAX OPERATIONS
    // ================================================================
    
    /**
     * Load unread count
     */
    async function loadUnreadCount() {
        try {
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'saw_get_unread_count',
                    nonce: CONFIG.nonce,
                }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                const oldCount = state.unreadCount;
                state.unreadCount = data.data.count;
                updateBadge();
                
                // Animate if count increased
                if (state.unreadCount > oldCount) {
                    animateBadge();
                }
            }
        } catch (error) {
            console.error('[SAW Notifications] Error loading unread count:', error);
        }
    }
    
    /**
     * Load notifications
     */
    async function loadNotifications(append = false) {
        if (state.isLoading) return;
        
        state.isLoading = true;
        
        if (!append) {
            state.offset = 0;
            state.notifications = [];
            showLoadingState();
        }
        
        try {
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'saw_get_notifications',
                    nonce: CONFIG.nonce,
                    offset: state.offset,
                    limit: CONFIG.pageSize,
                }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (append) {
                    state.notifications = [...state.notifications, ...data.data.notifications];
                } else {
                    state.notifications = data.data.notifications;
                }
                
                state.hasMore = data.data.has_more;
                state.offset += data.data.notifications.length;
                state.unreadCount = data.data.unread_count;
                
                renderNotifications();
                updateBadge();
            } else {
                showError(data.data?.message || 'Nepodařilo se načíst notifikace');
            }
        } catch (error) {
            console.error('[SAW Notifications] Error loading notifications:', error);
            showError('Chyba při načítání notifikací');
        } finally {
            state.isLoading = false;
        }
    }
    
    /**
     * Mark single notification as read
     */
    async function markAsRead(notificationId, element) {
        try {
            // Optimistic UI update
            element?.classList.remove('unread');
            state.unreadCount = Math.max(0, state.unreadCount - 1);
            updateBadge();
            
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'saw_mark_notification_read',
                    nonce: CONFIG.nonce,
                    notification_id: notificationId,
                }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                state.unreadCount = data.data.unread_count;
                updateBadge();
            }
        } catch (error) {
            console.error('[SAW Notifications] Error marking as read:', error);
            // Revert UI on error
            element?.classList.add('unread');
        }
    }
    
    /**
     * Mark all notifications as read
     */
    async function markAllAsRead() {
        try {
            // Optimistic UI update
            document.querySelectorAll('.saw-notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            state.unreadCount = 0;
            updateBadge();
            
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'saw_mark_all_notifications_read',
                    nonce: CONFIG.nonce,
                }),
            });
            
            const data = await response.json();
            
            if (!data.success) {
                // Reload to revert
                loadNotifications();
            }
        } catch (error) {
            console.error('[SAW Notifications] Error marking all as read:', error);
            loadNotifications();
        }
    }
    
    /**
     * Delete notification
     */
    async function deleteNotification(notificationId, element) {
        try {
            // Optimistic UI - animate removal
            element?.classList.add('deleting');
            element.style.maxHeight = element.offsetHeight + 'px';
            
            setTimeout(() => {
                element.style.maxHeight = '0';
                element.style.opacity = '0';
                element.style.padding = '0';
                element.style.margin = '0';
            }, 10);
            
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'saw_delete_notification',
                    nonce: CONFIG.nonce,
                    notification_id: notificationId,
                }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove from DOM after animation
                setTimeout(() => {
                    element?.remove();
                    
                    // Remove from state
                    state.notifications = state.notifications.filter(n => n.id != notificationId);
                    state.unreadCount = data.data.unread_count;
                    updateBadge();
                    
                    // Show empty state if no more notifications
                    if (state.notifications.length === 0) {
                        showEmptyState();
                    }
                }, CONFIG.animationDuration);
            } else {
                // Revert UI on error
                element?.classList.remove('deleting');
                element?.style.removeProperty('max-height');
                element?.style.removeProperty('opacity');
                element?.style.removeProperty('padding');
                element?.style.removeProperty('margin');
            }
        } catch (error) {
            console.error('[SAW Notifications] Delete error:', error);
            element?.classList.remove('deleting');
        }
    }
    
    // ================================================================
    // RENDERING
    // ================================================================
    
    /**
     * Render notifications list
     */
    function renderNotifications() {
        if (!elements.list) return;
        
        if (state.notifications.length === 0) {
            showEmptyState();
            return;
        }
        
        const html = state.notifications.map(notification => {
            const isUnread = !parseInt(notification.is_read);
            const priorityBadge = notification.priority === 'high' 
                ? '<span class="saw-notification-priority" style="display: inline-block; padding: 2px 6px; background: #fef2f2; color: #dc2626; font-size: 10px; font-weight: 600; border-radius: 4px; margin-left: 8px;">Důležité</span>'
                : '';
            
            return `
                <div class="saw-notification-item ${isUnread ? 'unread' : ''}" 
                     data-id="${notification.id}"
                     data-url="${notification.action_url || ''}"
                     style="display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #f3f4f6; cursor: pointer; position: relative; ${isUnread ? 'background: #f0f9ff;' : ''}">
                    ${isUnread ? '<div class="saw-notification-unread-dot" style="position: absolute; left: -8px; top: 50%; transform: translateY(-50%); width: 8px; height: 8px; background: #2563eb; border-radius: 50%;"></div>' : ''}
                    <div class="saw-notification-icon" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #f3f4f6; border-radius: 10px; font-size: 18px; flex-shrink: 0;">
                        ${notification.icon || '🔔'}
                    </div>
                    <div class="saw-notification-content" style="flex: 1; min-width: 0;">
                        <h4 class="saw-notification-title" style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #111827; line-height: 1.3;">${escapeHtml(notification.title)}</h4>
                        <p class="saw-notification-message" style="margin: 0 0 6px; font-size: 13px; color: #6b7280; line-height: 1.4;">${escapeHtml(notification.message)}</p>
                        <div class="saw-notification-meta" style="display: flex; align-items: center; gap: 8px;">
                            <span class="saw-notification-time" style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #9ca3af;">
                                <svg viewBox="0 0 16 16" fill="currentColor" style="width: 12px; height: 12px;">
                                    <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0zM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0zm7-3.25v2.992l2.028.812a.75.75 0 0 1-.557 1.392l-2.5-1A.75.75 0 0 1 7 8.25v-3.5a.75.75 0 0 1 1.5 0z"/>
                                </svg>
                                ${notification.time_ago || ''}
                            </span>
                            ${priorityBadge}
                        </div>
                    </div>
                    <button class="saw-notification-delete" data-id="${notification.id}" title="Smazat" style="position: absolute; top: 8px; right: 0; display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: transparent; border: none; border-radius: 6px; cursor: pointer; color: #9ca3af; opacity: 0; transition: opacity 0.2s;">
                        <svg viewBox="0 0 16 16" fill="currentColor" style="width: 14px; height: 14px;">
                            <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.749.749 0 0 1 1.275.326.749.749 0 0 1-.215.734L9.06 8l3.22 3.22a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215L8 9.06l-3.22 3.22a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06z"/>
                        </svg>
                    </button>
                </div>
            `;
        }).join('');
        
        elements.list.innerHTML = html;
        
        // Add hover effect for delete buttons (since we can't use CSS :hover with inline styles)
        elements.list.querySelectorAll('.saw-notification-item').forEach(item => {
            const deleteBtn = item.querySelector('.saw-notification-delete');
            if (deleteBtn) {
                item.addEventListener('mouseenter', () => deleteBtn.style.opacity = '1');
                item.addEventListener('mouseleave', () => deleteBtn.style.opacity = '0');
            }
        });
        
        // Show/hide load more
        const loadMoreContainer = elements.wrapper.querySelector('.saw-notifications-load-more');
        if (loadMoreContainer) {
            loadMoreContainer.style.display = state.hasMore ? 'block' : 'none';
        }
    }
    
    /**
     * Show loading state
     */
    function showLoadingState() {
        if (!elements.list) return;
        
        elements.list.innerHTML = `
            <div class="saw-notifications-loading" style="padding: 10px 0;">
                ${Array(3).fill().map(() => `
                    <div class="saw-notification-skeleton" style="display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #f3f4f6;">
                        <div class="skeleton-icon" style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite;"></div>
                        <div class="skeleton-content" style="flex: 1; display: flex; flex-direction: column; gap: 8px;">
                            <div class="skeleton-line" style="height: 12px; width: 60%; border-radius: 4px; background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite;"></div>
                            <div class="skeleton-line" style="height: 12px; width: 90%; border-radius: 4px; background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite;"></div>
                            <div class="skeleton-line" style="height: 10px; width: 40%; border-radius: 4px; background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite;"></div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    /**
     * Show empty state
     */
    function showEmptyState() {
        if (!elements.list) return;
        
        elements.list.innerHTML = `
            <div class="saw-notifications-empty" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center;">
                <div class="saw-notifications-empty-icon" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">🔔</div>
                <h4 class="saw-notifications-empty-title" style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #374151;">Žádné notifikace</h4>
                <p class="saw-notifications-empty-text" style="margin: 0; font-size: 14px; color: #9ca3af;">Zatím nemáte žádné oznámení</p>
            </div>
        `;
        
        // Hide load more
        const loadMoreContainer = elements.wrapper.querySelector('.saw-notifications-load-more');
        if (loadMoreContainer) {
            loadMoreContainer.style.display = 'none';
        }
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        if (!elements.list) return;
        
        elements.list.innerHTML = `
            <div class="saw-notifications-empty" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center;">
                <div class="saw-notifications-empty-icon" style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
                <h4 class="saw-notifications-empty-title" style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #374151;">Chyba</h4>
                <p class="saw-notifications-empty-text" style="margin: 0; font-size: 14px; color: #9ca3af;">${escapeHtml(message)}</p>
            </div>
        `;
    }
    
    /**
     * Update badge count
     */
    function updateBadge() {
        // Update or create badge
        if (state.unreadCount > 0) {
            if (elements.badge) {
                elements.badge.textContent = state.unreadCount > 99 ? '99+' : state.unreadCount;
                elements.badge.dataset.count = state.unreadCount;
                elements.badge.style.display = 'block';
            } else {
                // Create badge if it doesn't exist
                const badge = document.createElement('span');
                badge.className = 'saw-notifications-badge';
                badge.style.cssText = 'position: absolute; top: 2px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; background: #ef4444; color: white; font-size: 11px; font-weight: 600; line-height: 18px; text-align: center; border-radius: 9px; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4);';
                badge.textContent = state.unreadCount > 99 ? '99+' : state.unreadCount;
                badge.dataset.count = state.unreadCount;
                elements.toggle?.appendChild(badge);
                elements.badge = badge;
            }
        } else if (elements.badge) {
            elements.badge.style.display = 'none';
        }
        
        // Update header count
        if (elements.headerCount) {
            if (state.unreadCount > 0) {
                elements.headerCount.textContent = state.unreadCount;
                elements.headerCount.dataset.count = state.unreadCount;
                elements.headerCount.style.display = 'inline-flex';
            } else {
                elements.headerCount.style.display = 'none';
            }
        }
        
        // Update mark all button state
        if (elements.markAllBtn) {
            elements.markAllBtn.disabled = state.unreadCount === 0;
            elements.markAllBtn.style.opacity = state.unreadCount === 0 ? '0.5' : '1';
            elements.markAllBtn.style.cursor = state.unreadCount === 0 ? 'not-allowed' : 'pointer';
        }
    }
    
    /**
     * Animate badge (pulse effect)
     */
    function animateBadge() {
        if (!elements.badge) return;
        
        elements.badge.style.transform = 'scale(1.3)';
        setTimeout(() => {
            elements.badge.style.transform = 'scale(1)';
        }, 200);
    }
    
    // ================================================================
    // POLLING
    // ================================================================
    
    /**
     * Start polling for new notifications
     */
    function startPolling() {
        if (state.pollTimer) return;
        
        state.pollTimer = setInterval(() => {
            if (!state.isOpen) {
                loadUnreadCount();
            }
        }, CONFIG.pollInterval);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }
    
    // ================================================================
    // UTILITIES
    // ================================================================
    
    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ================================================================
    // PUBLIC API
    // ================================================================
    
    window.SAWNotifications = {
        init,
        refresh: loadNotifications,
        getUnreadCount: () => state.unreadCount,
        open: openDropdown,
        close: closeDropdown,
    };
    
    // ================================================================
    // AUTO-INIT
    // ================================================================
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();