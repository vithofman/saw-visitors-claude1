/**
 * SAW Calendar Module
 *
 * Initializes and manages FullCalendar for visit scheduling.
 * Branch is taken from SAW_Context via PHP, not from JS filter.
 *
 * @package     SAW_Visitors
 * @subpackage  JS/Modules
 * @version     1.3.0 - FIXED: Removed branch filter, better error handling
 * @since       1.0.0
 */

(function($) {
    'use strict';
    
    // Check if config exists
    if (typeof window.sawCalendar === 'undefined') {
        console.error('SAW Calendar: Configuration not found');
        return;
    }
    
    const config = window.sawCalendar;
    let calendar = null;
    let currentPopup = null;
    
    /**
     * Initialize calendar when DOM is ready
     */
    $(document).ready(function() {
        const calendarEl = document.getElementById('saw-calendar');
        
        if (!calendarEl) {
            console.error('SAW Calendar: Container element not found');
            return;
        }
        
        initCalendar(calendarEl);
        initFilters();
        initPopupHandlers();
        
        console.log('SAW Calendar: Initialized');
    });
    
    /**
     * Initialize FullCalendar
     */
    function initCalendar(el) {
        calendar = new FullCalendar.Calendar(el, {
            // Locale
            locale: 'cs',
            
            // Initial view
            initialView: config.defaultView || 'dayGridMonth',
            
            // Header toolbar
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            
            // Button text (Czech)
            buttonText: {
                today: 'Dnes',
                month: 'Měsíc',
                week: 'Týden',
                day: 'Den',
                list: 'Seznam'
            },
            
            // Time settings
            firstDay: config.firstDay || 1,
            slotMinTime: config.slotMinTime || '06:00:00',
            slotMaxTime: config.slotMaxTime || '22:00:00',
            slotDuration: config.slotDuration || '00:30:00',
            
            // Display settings
            navLinks: true,
            dayMaxEvents: 3,
            weekNumbers: false,
            nowIndicator: true,
            
            // Interaction
            selectable: true,
            selectMirror: true,
            editable: true,
            eventResizableFromStart: true,
            
            // Height
            height: 'auto',
            contentHeight: 650,
            
            // Events source
            events: function(info, successCallback, failureCallback) {
                loadEvents(info.start, info.end, successCallback, failureCallback);
            },
            
            // Event handlers
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                showEventPopup(info.event, info.el);
            },
            
            dateClick: function(info) {
                // Navigate to create visit with pre-filled date and time
                const dateStr = info.dateStr; // Format: YYYY-MM-DD or YYYY-MM-DDTHH:mm:ss
                let url = config.createUrl + '?date=' + dateStr;
                
                // If time is available (week/day view), add time parameter
                if (info.date && info.view.type !== 'dayGridMonth') {
                    const hours = String(info.date.getHours()).padStart(2, '0');
                    const minutes = String(info.date.getMinutes()).padStart(2, '0');
                    url += '&time=' + hours + ':' + minutes;
                }
                
                window.location.href = url;
            },
            
            select: function(info) {
                // Create visit with date range - extract date and time from selection
                const startDate = new Date(info.startStr);
                const dateStr = startDate.toISOString().split('T')[0]; // YYYY-MM-DD
                let url = config.createUrl + '?date=' + dateStr;
                
                // Add time if available (week/day view)
                if (info.view.type !== 'dayGridMonth') {
                    const hours = String(startDate.getHours()).padStart(2, '0');
                    const minutes = String(startDate.getMinutes()).padStart(2, '0');
                    url += '&time=' + hours + ':' + minutes;
                }
                
                window.location.href = url;
            },
            
            eventDrop: function(info) {
                updateEvent(info.event, info.revert);
            },
            
            eventResize: function(info) {
                updateEvent(info.event, info.revert);
            },
            
            // Loading indicator
            loading: function(isLoading) {
                const loader = document.getElementById('saw-calendar-loading');
                if (loader) {
                    loader.style.display = isLoading ? 'flex' : 'none';
                }
            }
        });
        
        calendar.render();
    }
    
    /**
     * Load events from server
     */
    function loadEvents(start, end, successCallback, failureCallback) {
        // Get filter values (no branch - it comes from SAW_Context)
        const status = $('#saw-filter-status').val() || '';
        const type = $('#saw-filter-type').val() || '';
        
        console.log('SAW Calendar: Loading events...', {
            start: start.toISOString(),
            end: end.toISOString(),
            status: status,
            type: type
        });
        
        $.ajax({
            url: config.ajaxurl,
            method: 'GET',
            data: {
                action: 'saw_calendar_events',
                nonce: config.nonce,
                start: start.toISOString(),
                end: end.toISOString(),
                status: status,
                type: type
            },
            success: function(response) {
                console.log('SAW Calendar: Response received', response);
                
                // Handle different response formats
                let events = [];
                
                if (Array.isArray(response)) {
                    // Direct array response (expected)
                    events = response;
                } else if (response && response.success === true && Array.isArray(response.data)) {
                    // WordPress success wrapper
                    events = response.data;
                } else if (response && response.success === false) {
                    // WordPress error
                    console.error('SAW Calendar: Server error', response.data);
                    showToast(response.data?.message || 'Chyba při načítání událostí', 'error');
                    events = [];
                } else {
                    console.warn('SAW Calendar: Unexpected response format', response);
                    events = [];
                }
                
                console.log('SAW Calendar: Loaded ' + events.length + ' events');
                successCallback(events);
            },
            error: function(xhr, status, error) {
                console.error('SAW Calendar: AJAX error', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                // Try to parse error response
                let errorMsg = 'Chyba při načítání událostí';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                } catch (e) {
                    // Ignore parse error
                }
                
                showToast(errorMsg + ' (HTTP ' + xhr.status + ')', 'error');
                
                // Return empty array to prevent further errors
                successCallback([]);
            }
        });
    }
    
    /**
     * Update event after drag/drop or resize
     */
    function updateEvent(event, revertFunc) {
        const startStr = event.start.toISOString();
        const endStr = event.end ? event.end.toISOString() : null;
        
        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'saw_calendar_update_event',
                nonce: config.nonce,
                id: event.id,
                start: startStr,
                end: endStr
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data?.message || 'Návštěva byla přesunuta', 'success');
                } else {
                    showToast(response.data?.message || 'Chyba při ukládání', 'error');
                    revertFunc();
                }
            },
            error: function() {
                showToast('Chyba při ukládání', 'error');
                revertFunc();
            }
        });
    }
    
    /**
     * Show event popup
     */
    function showEventPopup(event, targetEl) {
        // Close existing popup
        closePopup();
        
        const props = event.extendedProps || {};
        
        // Build popup HTML
        const statusClass = 'saw-calendar-popup__status--' + (props.status || 'pending');
        const statusText = getStatusText(props.status);
        
        let html = `
            <div class="saw-calendar-popup" id="saw-event-popup">
                <div class="saw-calendar-popup__header">
                    <span class="saw-calendar-popup__status ${statusClass}">${statusText}</span>
                    <button type="button" class="saw-calendar-popup__close" data-close-popup>&times;</button>
                </div>
                <h4 class="saw-calendar-popup__title">${escapeHtml(event.title)}</h4>
                <div class="saw-calendar-popup__meta">
                    <div class="saw-calendar-popup__meta-item">
                        <span class="saw-calendar-popup__meta-icon">📅</span>
                        <span>${formatDate(event.start)}</span>
                    </div>
                    <div class="saw-calendar-popup__meta-item">
                        <span class="saw-calendar-popup__meta-icon">🕐</span>
                        <span>${formatTime(event.start)} - ${formatTime(event.end)}</span>
                    </div>
        `;
        
        if (props.branch) {
            html += `
                    <div class="saw-calendar-popup__meta-item">
                        <span class="saw-calendar-popup__meta-icon">🏢</span>
                        <span>${escapeHtml(props.branch)}</span>
                    </div>
            `;
        }
        
        if (props.personCount > 1) {
            html += `
                    <div class="saw-calendar-popup__meta-item">
                        <span class="saw-calendar-popup__meta-icon">👥</span>
                        <span>${props.personCount} osob</span>
                    </div>
            `;
        }
        
        html += `
                </div>
        `;
        
        if (props.purpose) {
            html += `
                <div class="saw-calendar-popup__purpose">${escapeHtml(props.purpose)}</div>
            `;
        }
        
        html += `
                <div class="saw-calendar-popup__actions">
                    <a href="${props.detailUrl}" class="saw-btn saw-btn-primary saw-btn-sm">Detail</a>
                    <a href="${props.editUrl}" class="saw-btn saw-btn-secondary saw-btn-sm">Upravit</a>
                </div>
            </div>
        `;
        
        // Create popup element
        const popup = $(html);
        $('body').append(popup);
        currentPopup = popup;
        
        // Position popup
        positionPopup(popup, targetEl);
        
        // Add close handler
        popup.find('[data-close-popup]').on('click', closePopup);
    }
    
    /**
     * Position popup near target element
     */
    function positionPopup(popup, targetEl) {
        const rect = targetEl.getBoundingClientRect();
        const popupEl = popup[0];
        
        // Initial position
        let top = rect.bottom + window.scrollY + 10;
        let left = rect.left + window.scrollX;
        
        // Show to measure
        popup.css({
            position: 'absolute',
            top: top + 'px',
            left: left + 'px',
            zIndex: 10000
        });
        
        // Adjust if off-screen
        const popupRect = popupEl.getBoundingClientRect();
        
        if (popupRect.right > window.innerWidth - 20) {
            left = window.innerWidth - popupRect.width - 20;
        }
        
        if (popupRect.bottom > window.innerHeight - 20) {
            top = rect.top + window.scrollY - popupRect.height - 10;
        }
        
        popup.css({
            top: top + 'px',
            left: Math.max(20, left) + 'px'
        });
    }
    
    /**
     * Close popup
     */
    function closePopup() {
        if (currentPopup) {
            currentPopup.remove();
            currentPopup = null;
        }
    }
    
    /**
     * Initialize filter handlers
     */
    function initFilters() {
        // Status filter
        $('#saw-filter-status').on('change', function() {
            if (calendar) {
                calendar.refetchEvents();
            }
        });
        
        // Type filter
        $('#saw-filter-type').on('change', function() {
            if (calendar) {
                calendar.refetchEvents();
            }
        });
        
        // View switcher
        $('#saw-filter-view').on('change', function() {
            const view = $(this).val();
            if (calendar && view) {
                calendar.changeView(view);
            }
        });
    }
    
    /**
     * Initialize popup handlers
     */
    function initPopupHandlers() {
        // Close popup on outside click
        $(document).on('click', function(e) {
            if (currentPopup && !$(e.target).closest('.saw-calendar-popup, .fc-event').length) {
                closePopup();
            }
        });
        
        // Close popup on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && currentPopup) {
                closePopup();
            }
        });
    }
    
    /**
     * Get status text in Czech
     */
    function getStatusText(status) {
        const texts = {
            'draft': 'Koncept',
            'pending': 'Čekající',
            'confirmed': 'Potvrzená',
            'in_progress': 'Probíhá',
            'completed': 'Dokončená',
            'cancelled': 'Zrušená'
        };
        return texts[status] || status || 'Čekající';
    }
    
    /**
     * Format date for display
     */
    function formatDate(date) {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('cs-CZ', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
    
    /**
     * Format time for display
     */
    function formatTime(date) {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleTimeString('cs-CZ', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Show toast notification
     */
    function showToast(message, type) {
        // Use SAW toast if available
        if (typeof window.sawShowToast === 'function') {
            window.sawShowToast(message, type);
            return;
        }
        
        // Fallback: console
        if (type === 'error') {
            console.error('SAW Calendar:', message);
        } else {
            console.log('SAW Calendar:', message);
        }
    }
    
    /**
     * Public API
     */
    window.SAWCalendarModule = {
        getCalendar: function() {
            return calendar;
        },
        refetch: function() {
            if (calendar) {
                calendar.refetchEvents();
            }
        },
        goToDate: function(date) {
            if (calendar) {
                calendar.gotoDate(date);
            }
        },
        changeView: function(view) {
            if (calendar) {
                calendar.changeView(view);
            }
        }
    };
    
})(jQuery);
