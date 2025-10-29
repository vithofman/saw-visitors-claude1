/**
 * SAW Notifications System
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

(function() {
    'use strict';

    const SAWNotifications = {
        
        container: null,
        
        init: function() {
            this.createContainer();
            this.checkURLParams();
        },
        
        createContainer: function() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'saw-notifications-container';
                document.body.appendChild(this.container);
            }
        },
        
        checkURLParams: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('created') === '1') {
                this.show('Zákazník byl úspěšně vytvořen', 'success');
                this.cleanURL();
            }
            
            if (urlParams.get('updated') === '1') {
                this.show('Zákazník byl úspěšně aktualizován', 'success');
                this.cleanURL();
            }
            
            if (urlParams.get('deleted') === '1') {
                this.show('Zákazník byl úspěšně smazán', 'success');
                this.cleanURL();
            }
            
            if (urlParams.get('error')) {
                const errorMsg = urlParams.get('error');
                this.show(this.getErrorMessage(errorMsg), 'error');
                this.cleanURL();
            }
        },
        
        cleanURL: function() {
            const url = new URL(window.location);
            url.searchParams.delete('created');
            url.searchParams.delete('updated');
            url.searchParams.delete('deleted');
            url.searchParams.delete('error');
            window.history.replaceState({}, '', url);
        },
        
        getErrorMessage: function(error) {
            const messages = {
                'save_failed': 'Nepodařilo se uložit změny',
                'delete_failed': 'Nepodařilo se smazat zákazníka',
                'not_found': 'Zákazník nebyl nalezen'
            };
            return messages[error] || 'Došlo k chybě';
        },
        
        show: function(message, type = 'info', duration = 4000) {
            this.createContainer();
            
            const notification = document.createElement('div');
            notification.className = `saw-notification saw-notification-${type}`;
            
            const icon = this.getIcon(type);
            
            notification.innerHTML = `
                <div class="saw-notification-icon">
                    ${icon}
                </div>
                <div class="saw-notification-content">
                    <p class="saw-notification-message">${this.escapeHtml(message)}</p>
                </div>
                <button type="button" class="saw-notification-close" aria-label="Zavřít">
                    <svg viewBox="0 0 20 20" fill="none">
                        <path d="M6 6L14 14M6 14L14 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <div class="saw-notification-progress">
                    <div class="saw-notification-progress-bar"></div>
                </div>
            `;
            
            this.container.appendChild(notification);
            
            // Close button
            const closeBtn = notification.querySelector('.saw-notification-close');
            closeBtn.addEventListener('click', () => {
                this.dismiss(notification);
            });
            
            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss(notification);
                }, duration);
            }
            
            return notification;
        },
        
        dismiss: function(notification) {
            notification.classList.add('saw-notification-dismissing');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        },
        
        getIcon: function(type) {
            const icons = {
                success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
                error: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
            };
            return icons[type] || icons.info;
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        success: function(message, duration) {
            return this.show(message, 'success', duration);
        },
        
        error: function(message, duration) {
            return this.show(message, 'error', duration);
        },
        
        warning: function(message, duration) {
            return this.show(message, 'warning', duration);
        },
        
        info: function(message, duration) {
            return this.show(message, 'info', duration);
        }
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            SAWNotifications.init();
        });
    } else {
        SAWNotifications.init();
    }
    
    // Export to global scope
    window.SAWNotifications = SAWNotifications;
    
})();