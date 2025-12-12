/**
 * SAW Calendar Mobile Module
 *
 * Mobile-optimized calendar functionality.
 * Components: MiniCalendar, AgendaList, BottomSheet
 *
 * @package     SAW_Visitors
 * @subpackage  JS/Modules
 * @version     1.1.0 - Fixed: Added robust error handling
 * @since       3.1.0
 */

(function($) {
    'use strict';
    
    // Wait for config to be available (with timeout)
    var configCheckAttempts = 0;
    var maxAttempts = 10;
    
    function waitForConfig() {
        configCheckAttempts++;
        
        if (typeof window.sawCalendarMobile !== 'undefined') {
            initMobileCalendar();
        } else if (configCheckAttempts < maxAttempts) {
            setTimeout(waitForConfig, 100);
        } else {
            console.warn('SAW Calendar Mobile: Configuration not found after ' + maxAttempts + ' attempts');
        }
    }
    
    function initMobileCalendar() {
        try {
            console.log('SAW Calendar Mobile: Initializing...');
            
            // Initialize components with error handling
            if (document.getElementById('saw-mini-calendar')) {
                MiniCalendar.init();
            }
            
            if (document.getElementById('saw-agenda')) {
                AgendaList.init();
            }
            
            if (document.getElementById('saw-bottom-sheet')) {
                BottomSheet.init();
            }
            
            console.log('SAW Calendar Mobile: Initialized');
        } catch (e) {
            console.error('SAW Calendar Mobile: Initialization error', e);
        }
    }
    
    // Safe config access
    function getConfig() {
        return window.sawCalendarMobile || {
            ajaxurl: '/wp-admin/admin-ajax.php',
            nonce: '',
            homeUrl: '',
            createUrl: '/admin/visits/create',
            detailUrl: '/admin/visits/{id}/',
            editUrl: '/admin/visits/{id}/edit'
        };
    }
    
    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        waitForConfig();
    });
    
    // ==========================================
    // MINI CALENDAR COMPONENT
    // ==========================================
    const MiniCalendar = {
        container: null,
        currentYear: null,
        currentMonth: null,
        selectedDate: null,
        daysWithEvents: [],
        touchStartX: 0,
        touchEndX: 0,
        
        /**
         * Initialize mini calendar
         */
        init: function() {
            this.container = document.getElementById('saw-mini-calendar');
            if (!this.container) return;
            
            this.currentYear = parseInt(this.container.dataset.year);
            this.currentMonth = parseInt(this.container.dataset.month);
            this.selectedDate = this.container.dataset.selected || this.getTodayString();
            
            this.bindEvents();
            this.loadDaysWithEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Navigation buttons
            $(this.container).on('click', '.saw-mini-calendar__nav', function(e) {
                e.preventDefault();
                const year = parseInt($(this).data('year'));
                const month = parseInt($(this).data('month'));
                self.navigateToMonth(year, month);
            });
            
            // Day buttons
            $(this.container).on('click', '.saw-mini-calendar__day:not(.saw-mini-calendar__day--empty)', function(e) {
                e.preventDefault();
                const date = $(this).data('date');
                self.selectDate(date);
            });
            
            // Today button
            $(this.container).on('click', '.saw-mini-calendar__today-btn', function(e) {
                e.preventDefault();
                const today = self.getTodayString();
                const todayDate = new Date(today);
                self.navigateToMonth(todayDate.getFullYear(), todayDate.getMonth() + 1);
                self.selectDate(today);
            });
            
            // Swipe gesture for month navigation
            this.initSwipeGesture();
        },
        
        /**
         * Initialize swipe gesture for month navigation
         */
        initSwipeGesture: function() {
            const self = this;
            const minSwipeDistance = 50;
            
            this.container.addEventListener('touchstart', function(e) {
                self.touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            this.container.addEventListener('touchend', function(e) {
                self.touchEndX = e.changedTouches[0].screenX;
                const diff = self.touchStartX - self.touchEndX;
                
                if (Math.abs(diff) > minSwipeDistance) {
                    if (diff > 0) {
                        // Swipe left - next month
                        self.navigateMonth(1);
                    } else {
                        // Swipe right - prev month
                        self.navigateMonth(-1);
                    }
                }
            }, { passive: true });
        },
        
        /**
         * Navigate to specific month
         */
        navigateToMonth: function(year, month) {
            const url = new URL(window.location.href);
            url.searchParams.set('year', year);
            url.searchParams.set('month', month);
            
            // Keep selected date if in same month, otherwise select first day
            if (this.selectedDate) {
                const selectedDateObj = new Date(this.selectedDate);
                if (selectedDateObj.getFullYear() !== year || (selectedDateObj.getMonth() + 1) !== month) {
                    const newDate = year + '-' + String(month).padStart(2, '0') + '-01';
                    url.searchParams.set('date', newDate);
                }
            }
            
            window.location.href = url.toString();
        },
        
        /**
         * Navigate relative month (+1 or -1)
         */
        navigateMonth: function(direction) {
            let newMonth = this.currentMonth + direction;
            let newYear = this.currentYear;
            
            if (newMonth > 12) {
                newMonth = 1;
                newYear++;
            } else if (newMonth < 1) {
                newMonth = 12;
                newYear--;
            }
            
            this.navigateToMonth(newYear, newMonth);
        },
        
        /**
         * Select a date
         */
        selectDate: function(dateStr) {
            this.selectedDate = dateStr;
            
            // Update URL without reload
            const url = new URL(window.location.href);
            url.searchParams.set('date', dateStr);
            window.history.replaceState({}, '', url.toString());
            
            // Update UI
            $(this.container).find('.saw-mini-calendar__day--selected').removeClass('saw-mini-calendar__day--selected');
            $(this.container).find('[data-date="' + dateStr + '"]').addClass('saw-mini-calendar__day--selected');
            
            // Trigger agenda update
            AgendaList.loadEvents(dateStr);
        },
        
        /**
         * Load days that have events (for dot indicators)
         */
        loadDaysWithEvents: function() {
            var self = this;
            var cfg = getConfig();
            var startDate = this.currentYear + '-' + String(this.currentMonth).padStart(2, '0') + '-01';
            var endDate = this.currentYear + '-' + String(this.currentMonth).padStart(2, '0') + '-31';
            
            if (!cfg.nonce) {
                console.warn('SAW Calendar Mobile: No nonce available');
                return;
            }
            
            $.ajax({
                url: cfg.ajaxurl,
                method: 'GET',
                data: {
                    action: 'saw_calendar_days_with_events',
                    nonce: cfg.nonce,
                    start: startDate,
                    end: endDate
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.daysWithEvents = response.data;
                        self.renderDots();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load days with events:', error);
                }
            });
        },
        
        /**
         * Render dot indicators
         */
        renderDots: function() {
            const self = this;
            this.daysWithEvents.forEach(function(date) {
                const dayBtn = $(self.container).find('[data-date="' + date + '"]');
                if (dayBtn.length) {
                    dayBtn.addClass('saw-mini-calendar__day--has-events');
                }
            });
        },
        
        /**
         * Get today's date as string
         */
        getTodayString: function() {
            const today = new Date();
            return today.getFullYear() + '-' + 
                   String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(today.getDate()).padStart(2, '0');
        }
    };
    
    // ==========================================
    // AGENDA LIST COMPONENT
    // ==========================================
    const AgendaList = {
        container: null,
        listEl: null,
        loadingEl: null,
        countEl: null,
        dateTextEl: null,
        currentDate: null,
        
        /**
         * Initialize agenda list
         */
        init: function() {
            this.container = document.getElementById('saw-agenda');
            if (!this.container) return;
            
            this.listEl = document.getElementById('saw-agenda-list');
            this.loadingEl = document.getElementById('saw-agenda-loading');
            this.countEl = document.getElementById('saw-agenda-count');
            this.dateTextEl = document.getElementById('saw-agenda-date-text');
            
            this.currentDate = $(this.container).data('date') || this.getTodayString();
            
            this.bindEvents();
            
            // Load events for initial date
            this.loadEvents(this.currentDate);
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Event card click - open bottom sheet
            $(this.container).on('click', '.saw-event-card', function(e) {
                e.preventDefault();
                const eventId = $(this).data('event-id');
                BottomSheet.openEventDetail(eventId);
            });
        },
        
        /**
         * Load events for specific date
         */
        loadEvents: function(dateStr) {
            var self = this;
            var cfg = getConfig();
            this.currentDate = dateStr;
            this.showLoading();
            
            if (!cfg.nonce) {
                this.hideLoading();
                this.showError('Konfigurace není dostupná');
                return;
            }
            
            $.ajax({
                url: cfg.ajaxurl,
                method: 'GET',
                data: {
                    action: 'saw_calendar_day_events',
                    nonce: cfg.nonce,
                    date: dateStr
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.renderEvents(response.data.events || []);
                        self.updateHeader(dateStr, response.data.events ? response.data.events.length : 0);
                    } else {
                        self.showError(response.data && response.data.message ? response.data.message : 'Chyba při načítání');
                    }
                },
                error: function(xhr, status, error) {
                    self.hideLoading();
                    self.showError('Chyba připojení');
                    console.error('Failed to load events:', error);
                }
            });
        },
        
        /**
         * Render events list
         */
        renderEvents: function(events) {
            const self = this;
            
            if (events.length === 0) {
                $(this.listEl).html(this.getEmptyStateHTML());
                return;
            }
            
            const html = events.map(function(event) {
                return self.getEventCardHTML(event);
            }).join('');
            
            $(this.listEl).html(html);
        },
        
        /**
         * Get event card HTML
         */
        getEventCardHTML: function(event) {
            const statusColors = {
                'draft': '#94a3b8',
                'pending': '#f59e0b',
                'confirmed': '#3b82f6',
                'in_progress': '#f97316',
                'completed': '#6b7280',
                'cancelled': '#ef4444'
            };
            
            const status = event.status || 'pending';
            const color = statusColors[status] || '#94a3b8';
            const timeFrom = event.time_from ? event.time_from.substring(0, 5) : '—';
            const timeTo = event.time_to ? event.time_to.substring(0, 5) : '—';
            const personCount = parseInt(event.person_count || event.visitor_count || 1);
            const personWord = personCount === 1 ? 'osoba' : (personCount >= 2 && personCount <= 4 ? 'osoby' : 'osob');
            
            let html = '<article class="saw-event-card" data-event-id="' + event.id + '" data-status="' + this.escapeHtml(status) + '">';
            html += '<div class="saw-event-card__indicator" style="background-color: ' + color + '"></div>';
            html += '<div class="saw-event-card__content">';
            html += '<div class="saw-event-card__time">' + this.escapeHtml(timeFrom) + ' - ' + this.escapeHtml(timeTo) + '</div>';
            html += '<h3 class="saw-event-card__title">' + this.escapeHtml(event.company_name || 'Návštěva #' + event.id) + '</h3>';
            html += '<div class="saw-event-card__meta">';
            html += '<span class="saw-event-card__meta-item">';
            html += '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>';
            html += personCount + ' ' + personWord;
            html += '</span>';
            
            if (event.branch_name) {
                html += '<span class="saw-event-card__meta-item">';
                html += '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>';
                html += this.escapeHtml(event.branch_name);
                html += '</span>';
            }
            
            html += '</div></div>';
            html += '<div class="saw-event-card__arrow">';
            html += '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>';
            html += '</div></article>';
            
            return html;
        },
        
        /**
         * Get empty state HTML
         */
        getEmptyStateHTML: function() {
            var cfg = getConfig();
            var createUrl = (cfg.createUrl || '/admin/visits/create') + '?date=' + this.currentDate;
            
            return '<div class="saw-agenda__empty">' +
                   '<div class="saw-agenda__empty-icon">📭</div>' +
                   '<p class="saw-agenda__empty-text">Žádné návštěvy pro tento den</p>' +
                   '<a href="' + createUrl + '" class="saw-agenda__empty-btn">+ Naplánovat návštěvu</a>' +
                   '</div>';
        },
        
        /**
         * Update header with date and count
         */
        updateHeader: function(dateStr, count) {
            // Update count badge
            if (this.countEl) {
                const countWord = count === 1 ? 'návštěva' : (count >= 2 && count <= 4 ? 'návštěvy' : 'návštěv');
                $(this.countEl).text(count + ' ' + countWord);
            }
            
            // Update date text
            if (this.dateTextEl) {
                const dateObj = new Date(dateStr);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                dateObj.setHours(0, 0, 0, 0);
                
                const isToday = dateObj.getTime() === today.getTime();
                const dayNames = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
                const dayName = dayNames[dateObj.getDay()];
                const formatted = dayName + ' ' + dateObj.getDate() + '. ' + (dateObj.getMonth() + 1) + '. ' + dateObj.getFullYear();
                
                if (isToday) {
                    $(this.dateTextEl).html('<strong>Dnes</strong>, ' + formatted.toLowerCase());
                } else {
                    $(this.dateTextEl).text(formatted);
                }
            }
        },
        
        /**
         * Show loading state
         */
        showLoading: function() {
            $(this.listEl).hide();
            $(this.loadingEl).show();
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function() {
            $(this.listEl).show();
            $(this.loadingEl).hide();
        },
        
        /**
         * Show error state
         */
        showError: function(message) {
            $(this.listEl).html(
                '<div class="saw-agenda__empty">' +
                '<div class="saw-agenda__empty-icon">⚠️</div>' +
                '<p class="saw-agenda__empty-text">' + this.escapeHtml(message) + '</p>' +
                '</div>'
            ).show();
            $(this.loadingEl).hide();
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },
        
        /**
         * Get today as string
         */
        getTodayString: function() {
            const today = new Date();
            return today.getFullYear() + '-' + 
                   String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(today.getDate()).padStart(2, '0');
        }
    };
    
    // ==========================================
    // BOTTOM SHEET COMPONENT
    // ==========================================
    const BottomSheet = {
        sheet: null,
        backdrop: null,
        content: null,
        isOpen: false,
        startY: 0,
        currentY: 0,
        isDragging: false,
        
        /**
         * Initialize bottom sheet
         */
        init: function() {
            this.sheet = document.getElementById('saw-bottom-sheet');
            this.backdrop = document.getElementById('saw-bottom-sheet-backdrop');
            this.content = document.getElementById('saw-bottom-sheet-content');
            
            if (!this.sheet || !this.backdrop) return;
            
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Close on backdrop click
            $(this.backdrop).on('click', function() {
                self.close();
            });
            
            // Close on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });
            
            // Drag to dismiss
            this.initDragToDismiss();
        },
        
        /**
         * Initialize drag to dismiss gesture
         */
        initDragToDismiss: function() {
            const self = this;
            const handle = this.sheet.querySelector('.saw-bottom-sheet__handle');
            if (!handle) return;
            
            handle.addEventListener('touchstart', function(e) {
                self.startY = e.touches[0].clientY;
                self.isDragging = true;
                self.sheet.style.transition = 'none';
            }, { passive: true });
            
            handle.addEventListener('touchmove', function(e) {
                if (!self.isDragging) return;
                
                self.currentY = e.touches[0].clientY;
                const diff = self.currentY - self.startY;
                
                // Only allow dragging down
                if (diff > 0) {
                    self.sheet.style.transform = 'translateY(' + diff + 'px)';
                }
            }, { passive: true });
            
            handle.addEventListener('touchend', function() {
                if (!self.isDragging) return;
                self.isDragging = false;
                
                const diff = self.currentY - self.startY;
                self.sheet.style.transition = '';
                
                // If dragged more than 100px, close the sheet
                if (diff > 100) {
                    self.close();
                } else {
                    self.sheet.style.transform = '';
                }
            }, { passive: true });
        },
        
        /**
         * Open bottom sheet with event detail
         */
        openEventDetail: function(eventId) {
            this.open();
            this.loadEventDetail(eventId);
        },
        
        /**
         * Open the sheet
         */
        open: function() {
            this.isOpen = true;
            $(this.sheet).addClass('is-open');
            this.sheet.setAttribute('aria-hidden', 'false');
            $(this.backdrop).addClass('is-visible');
            $('body').css('overflow', 'hidden');
            
            // Show loading
            $(this.content).html(
                '<div class="saw-bottom-sheet__loading">' +
                '<div class="saw-bottom-sheet__loading-spinner"></div>' +
                '<span>Načítám detail...</span>' +
                '</div>'
            );
        },
        
        /**
         * Close the sheet
         */
        close: function() {
            this.isOpen = false;
            $(this.sheet).removeClass('is-open');
            this.sheet.setAttribute('aria-hidden', 'true');
            this.sheet.style.transform = '';
            $(this.backdrop).removeClass('is-visible');
            $('body').css('overflow', '');
        },
        
        /**
         * Load event detail via AJAX
         */
        loadEventDetail: function(eventId) {
            var self = this;
            var cfg = getConfig();
            
            if (!cfg.nonce) {
                self.renderError('Konfigurace není dostupná');
                return;
            }
            
            $.ajax({
                url: cfg.ajaxurl,
                method: 'GET',
                data: {
                    action: 'saw_calendar_event_details',
                    nonce: cfg.nonce,
                    id: eventId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEventDetail(response.data);
                    } else {
                        self.renderError(response.data && response.data.message ? response.data.message : 'Chyba při načítání');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load event detail:', error);
                    self.renderError('Chyba připojení');
                }
            });
        },
        
        /**
         * Render event detail content
         */
        renderEventDetail: function(data) {
            const visit = data.visit;
            const visitors = data.visitors || [];
            const calendarLinks = data.calendar_links || {};
            
            const statusLabels = {
                'draft': 'Koncept',
                'pending': 'Čekající',
                'confirmed': 'Potvrzeno',
                'in_progress': 'Probíhá',
                'completed': 'Dokončeno',
                'cancelled': 'Zrušeno'
            };
            
            const status = visit.status || 'pending';
            const statusLabel = statusLabels[status] || status;
            
            // Format date
            const visitDate = visit.visit_date || visit.schedule_date || visit.planned_date_from;
            const dateObj = new Date(visitDate);
            const dayNames = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
            const formattedDate = dayNames[dateObj.getDay()] + ' ' + dateObj.getDate() + '. ' + (dateObj.getMonth() + 1) + '. ' + dateObj.getFullYear();
            
            // Format times
            const timeFrom = visit.time_from ? visit.time_from.substring(0, 5) : null;
            const timeTo = visit.time_to ? visit.time_to.substring(0, 5) : null;
            const timeStr = timeFrom && timeTo ? timeFrom + ' - ' + timeTo : (timeFrom || '—');
            
            // Person count
            const personCount = visitors.length || parseInt(visit.visitor_count || 1);
            const personWord = personCount === 1 ? 'osoba' : (personCount >= 2 && personCount <= 4 ? 'osoby' : 'osob');
            
            // Build location string
            var locationParts = [];
            if (visit.branch_name) locationParts.push(visit.branch_name);
            if (visit.department_name) locationParts.push(visit.department_name);
            var locationStr = locationParts.join(', ') || '—';
            
            // URLs
            var cfg = getConfig();
            var detailUrl = (cfg.homeUrl || '') + '/admin/visits/' + visit.id + '/';
            var editUrl = (cfg.homeUrl || '') + '/admin/visits/' + visit.id + '/edit';
            
            // Build HTML
            let html = '<div class="saw-sheet-event">';
            html += '<span class="saw-sheet-event__status saw-sheet-event__status--' + this.escapeHtml(status) + '">' + this.escapeHtml(statusLabel) + '</span>';
            html += '<h2 class="saw-sheet-event__title">' + this.escapeHtml(visit.company_name || 'Návštěva #' + visit.id) + '</h2>';
            
            // Meta info
            html += '<div class="saw-sheet-event__meta">';
            html += '<div class="saw-sheet-event__meta-row"><span class="saw-sheet-event__meta-icon">📅</span><div><span class="saw-sheet-event__meta-text">' + this.escapeHtml(formattedDate) + '</span><span class="saw-sheet-event__meta-label">Datum návštěvy</span></div></div>';
            html += '<div class="saw-sheet-event__meta-row"><span class="saw-sheet-event__meta-icon">🕐</span><div><span class="saw-sheet-event__meta-text">' + this.escapeHtml(timeStr) + '</span><span class="saw-sheet-event__meta-label">Čas</span></div></div>';
            html += '<div class="saw-sheet-event__meta-row"><span class="saw-sheet-event__meta-icon">📍</span><div><span class="saw-sheet-event__meta-text">' + this.escapeHtml(locationStr) + '</span><span class="saw-sheet-event__meta-label">Místo</span></div></div>';
            html += '<div class="saw-sheet-event__meta-row"><span class="saw-sheet-event__meta-icon">👥</span><div><span class="saw-sheet-event__meta-text">' + personCount + ' ' + personWord + '</span><span class="saw-sheet-event__meta-label">Počet návštěvníků</span></div></div>';
            html += '</div>';
            
            // Purpose
            if (visit.purpose) {
                html += '<div class="saw-sheet-event__purpose">';
                html += '<div class="saw-sheet-event__purpose-label">Účel návštěvy</div>';
                html += '<p class="saw-sheet-event__purpose-text">' + this.escapeHtml(visit.purpose) + '</p>';
                html += '</div>';
            }
            
            html += '<div class="saw-sheet-event__divider"></div>';
            
            // Action buttons
            html += '<div class="saw-sheet-event__actions">';
            html += '<a href="' + detailUrl + '" class="saw-sheet-event__action-btn saw-sheet-event__action-btn--primary">';
            html += '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>';
            html += 'Detail</a>';
            html += '<a href="' + editUrl + '" class="saw-sheet-event__action-btn saw-sheet-event__action-btn--secondary">';
            html += '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>';
            html += 'Upravit</a>';
            html += '</div>';
            
            // Export links
            if (calendarLinks && Object.keys(calendarLinks).length > 0) {
                html += '<div class="saw-sheet-event__export">';
                html += '<div class="saw-sheet-event__export-label">Přidat do kalendáře</div>';
                html += '<div class="saw-sheet-event__export-links">';
                
                if (calendarLinks.google) {
                    html += '<a href="' + calendarLinks.google + '" target="_blank" class="saw-sheet-event__export-link saw-sheet-event__export-link--google">Google</a>';
                }
                if (calendarLinks.outlook) {
                    html += '<a href="' + calendarLinks.outlook + '" target="_blank" class="saw-sheet-event__export-link saw-sheet-event__export-link--outlook">Outlook</a>';
                }
                if (calendarLinks.office365) {
                    html += '<a href="' + calendarLinks.office365 + '" target="_blank" class="saw-sheet-event__export-link">Office 365</a>';
                }
                if (calendarLinks.ics) {
                    html += '<a href="' + calendarLinks.ics + '" download class="saw-sheet-event__export-link">ICS soubor</a>';
                }
                
                html += '</div></div>';
            }
            
            html += '</div>';
            
            $(this.content).html(html);
        },
        
        /**
         * Render error state
         */
        renderError: function(message) {
            $(this.content).html(
                '<div class="saw-bottom-sheet__loading">' +
                '<span style="font-size: 32px;">⚠️</span>' +
                '<span>' + this.escapeHtml(message) + '</span>' +
                '</div>'
            );
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };
    
    // Export to global scope for debugging
    window.SAWCalendarMobile = {
        MiniCalendar: MiniCalendar,
        AgendaList: AgendaList,
        BottomSheet: BottomSheet
    };
    
})(jQuery);