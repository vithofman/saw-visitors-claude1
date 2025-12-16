<?php
/**
 * SAW App Header - WITH NOTIFICATIONS (FIXED v2)
 *
 * Main application header component with integrated notifications bell.
 * 
 * FIXED v2: Added inline JavaScript directly in PHP for guaranteed functionality.
 *
 * @package    SAW_Visitors
 * @subpackage Frontend
 * @version    2.6.0
 * @since      4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW App Header Class
 */
class SAW_App_Header {
    
    private $user;
    private $customer;
    
    public function __construct($user = null, $customer = null) {
        $this->user = $user ?: $this->get_default_user();
        $this->customer = $customer ?: $this->get_default_customer();
    }
    
    private function get_default_user() {
        $wp_user = wp_get_current_user();
        
        return [
            'id' => $wp_user->ID,
            'name' => $wp_user->display_name ?: 'User',
            'email' => $wp_user->user_email,
            'role' => 'admin',
        ];
    }
    
    private function get_default_customer() {
        $customer = null;
        
        if (class_exists('SAW_Context') && method_exists('SAW_Context', 'get_customer')) {
            $full_customer = SAW_Context::get_customer();
            if ($full_customer) {
                $customer = $full_customer;
                if (!empty($full_customer['logo_url'])) {
                    $customer['logo_url_full'] = wp_get_upload_dir()['baseurl'] . '/' . ltrim($full_customer['logo_url'], '/');
                }
            }
        }
        
        if (!$customer) {
            $customer_id = null;
            
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }
            
            if ($customer_id) {
                global $wpdb;
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name, ico, logo_url FROM %i WHERE id = %d",
                    $wpdb->prefix . 'saw_customers',
                    $customer_id
                ), ARRAY_A);
                
                if ($customer && !empty($customer['logo_url'])) {
                    $customer['logo_url_full'] = wp_get_upload_dir()['baseurl'] . '/' . ltrim($customer['logo_url'], '/');
                }
            }
        }
        
        return $customer ?: [
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'logo_url' => '',
        ];
    }
    
    public function render() {
        ?>
        <header class="sa-app-header" id="sawAppHeader">
            <div class="sa-header-left">
                <button class="sa-mobile-menu-toggle" id="sawMobileMenuToggle" aria-label="Menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                
                <?php $this->render_customer_switcher(); ?>
            </div>
            
            <div class="sa-header-right">
                <?php $this->render_language_switcher(); ?>
                
                <?php $this->render_notifications(); ?>
                
                <div class="sa-user-menu">
                    <button class="sa-user-menu-toggle" id="sawUserMenuToggle">
                        <div class="sa-user-avatar">
                            <?php echo esc_html(strtoupper(substr($this->user['name'], 0, 1))); ?>
                        </div>
                        <div class="sa-user-info">
                            <div class="sa-user-name"><?php echo esc_html($this->user['name']); ?></div>
                            <div class="sa-user-role"><?php echo esc_html($this->get_role_label()); ?></div>
                        </div>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" class="sa-user-arrow">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    
                    <?php $this->render_user_dropdown(); ?>
                </div>
            </div>
        </header>
        <?php
    }
    
    /**
     * Render notifications bell with INLINE JavaScript
     */
    private function render_notifications() {
        $saw_user_id = $this->get_saw_user_id();
        
        if (!$saw_user_id) {
            return;
        }
        
        $unread_count = 0;
        if (class_exists('SAW_Notifications')) {
            $customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : null;
            $branch_id = class_exists('SAW_Context') ? SAW_Context::get_branch_id() : null;
            $unread_count = SAW_Notifications::get_unread_count($saw_user_id, $customer_id, $branch_id);
        }
        
        $nonce = wp_create_nonce('saw_notifications_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <!-- Notifications Component -->
        <div class="saw-notifications" id="sawNotifications" style="position: relative; display: flex; align-items: center;">
            <!-- Bell Button -->
            <button type="button"
                    class="saw-notifications-toggle" 
                    id="sawNotificationsToggle"
                    onclick="sawToggleNotifications()"
                    aria-label="<?php esc_attr_e('Notifikace', 'saw-visitors'); ?>"
                    style="position: relative; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; padding: 0; background: transparent; border: none; border-radius: 8px; cursor: pointer; color: #6b7280;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($unread_count > 0): ?>
                <span class="saw-notifications-badge" id="sawNotificationsBadge" style="position: absolute; top: 2px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; background: #ef4444; color: white; font-size: 11px; font-weight: 600; line-height: 18px; text-align: center; border-radius: 9px;">
                    <?php echo $unread_count > 99 ? '99+' : esc_html($unread_count); ?>
                </span>
                <?php endif; ?>
            </button>
            
            <!-- Dropdown Panel - FIXED positioning to avoid parent overflow issues -->
            <div class="saw-notifications-dropdown" 
                 id="sawNotificationsDropdown" 
                 style="display: none; position: fixed; top: 60px; right: 80px; width: 380px; max-height: 520px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 9999; overflow: hidden;">
                
                <!-- Header -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; background: #f8fafc;">
                    <h3 style="display: flex; align-items: center; gap: 8px; margin: 0; font-size: 15px; font-weight: 600; color: #111827;">
                        <?php esc_html_e('Notifikace', 'saw-visitors'); ?>
                        <?php if ($unread_count > 0): ?>
                        <span id="sawNotificationsHeaderCount" style="display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; background: #2563eb; color: white; font-size: 12px; font-weight: 600; border-radius: 11px;">
                            <?php echo esc_html($unread_count); ?>
                        </span>
                        <?php endif; ?>
                    </h3>
                    <button type="button" 
                            onclick="sawMarkAllRead()" 
                            id="sawMarkAllBtn"
                            <?php echo $unread_count === 0 ? 'disabled' : ''; ?>
                            style="display: flex; align-items: center; gap: 4px; padding: 6px 10px; background: transparent; border: none; border-radius: 6px; font-size: 12px; font-weight: 500; color: #6b7280; cursor: pointer; <?php echo $unread_count === 0 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>">
                        <svg viewBox="0 0 16 16" fill="currentColor" style="width: 14px; height: 14px;">
                            <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0z"/>
                        </svg>
                        <?php esc_html_e('Označit vše', 'saw-visitors'); ?>
                    </button>
                </div>
                
                <!-- Notifications List -->
                <div id="sawNotificationsList" style="overflow-y: auto; max-height: 400px; padding: 0 20px;">
                    <!-- Initial loading or empty state -->
                    <div id="sawNotificationsContent">
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">🔔</div>
                            <h4 style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #374151;">Žádné notifikace</h4>
                            <p style="margin: 0; font-size: 14px; color: #9ca3af;">Zatím nemáte žádné oznámení</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- INLINE JavaScript - guaranteed to work -->
        <script>
        (function() {
            // Configuration
            var config = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>',
                isOpen: false,
                notifications: [],
                loaded: false
            };
            
            // Toggle dropdown
            window.sawToggleNotifications = function() {
                var dropdown = document.getElementById('sawNotificationsDropdown');
                var toggle = document.getElementById('sawNotificationsToggle');
                if (!dropdown || !toggle) {
                    console.error('[SAW] Elements not found');
                    return;
                }
                
                config.isOpen = !config.isOpen;
                
                if (config.isOpen) {
                    // Calculate position based on button location
                    var rect = toggle.getBoundingClientRect();
                    dropdown.style.top = (rect.bottom + 8) + 'px';
                    dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
                
                console.log('[SAW] Toggle notifications, isOpen:', config.isOpen);
                
                // Load notifications on first open
                if (config.isOpen && !config.loaded) {
                    sawLoadNotifications();
                }
            };
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                var wrapper = document.getElementById('sawNotifications');
                var dropdown = document.getElementById('sawNotificationsDropdown');
                
                if (config.isOpen && wrapper && !wrapper.contains(e.target)) {
                    config.isOpen = false;
                    dropdown.style.display = 'none';
                }
            });
            
            // Close on Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && config.isOpen) {
                    config.isOpen = false;
                    document.getElementById('sawNotificationsDropdown').style.display = 'none';
                }
            });
            
            // Reposition on window resize
            window.addEventListener('resize', function() {
                if (config.isOpen) {
                    var dropdown = document.getElementById('sawNotificationsDropdown');
                    var toggle = document.getElementById('sawNotificationsToggle');
                    if (dropdown && toggle) {
                        var rect = toggle.getBoundingClientRect();
                        dropdown.style.top = (rect.bottom + 8) + 'px';
                        dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                    }
                }
            });
            
            // Load notifications
            window.sawLoadNotifications = function() {
                var content = document.getElementById('sawNotificationsContent');
                
                // Show loading
                content.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">Načítání...</div>';
                
                var formData = new FormData();
                formData.append('action', 'saw_get_notifications');
                formData.append('nonce', config.nonce);
                formData.append('offset', 0);
                formData.append('limit', 15);
                
                fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    config.loaded = true;
                    
                    if (data.success && data.data.notifications && data.data.notifications.length > 0) {
                        config.notifications = data.data.notifications;
                        sawRenderNotifications();
                    } else {
                        sawShowEmpty();
                    }
                })
                .catch(function(error) {
                    console.error('[SAW] Error loading notifications:', error);
                    content.innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">Chyba při načítání</div>';
                });
            };
            
            // Render notifications
            window.sawRenderNotifications = function() {
                var content = document.getElementById('sawNotificationsContent');
                
                if (config.notifications.length === 0) {
                    sawShowEmpty();
                    return;
                }
                
                var html = config.notifications.map(function(n) {
                    var isUnread = !parseInt(n.is_read);
                    return '<div onclick="sawNotificationClick(' + n.id + ', \'' + (n.action_url || '') + '\', ' + isUnread + ')" style="display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #f3f4f6; cursor: pointer; ' + (isUnread ? 'background: #f0f9ff; margin: 0 -20px; padding-left: 20px; padding-right: 20px;' : '') + '">' +
                        '<div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #f3f4f6; border-radius: 10px; font-size: 18px; flex-shrink: 0;">' + (n.icon || '🔔') + '</div>' +
                        '<div style="flex: 1; min-width: 0;">' +
                            '<h4 style="margin: 0 0 4px; font-size: 14px; font-weight: ' + (isUnread ? '600' : '500') + '; color: #111827;">' + sawEscape(n.title) + '</h4>' +
                            '<p style="margin: 0 0 6px; font-size: 13px; color: #6b7280;">' + sawEscape(n.message) + '</p>' +
                            '<span style="font-size: 12px; color: #9ca3af;">' + (n.time_ago || '') + '</span>' +
                        '</div>' +
                    '</div>';
                }).join('');
                
                content.innerHTML = html;
            };
            
            // Show empty state
            window.sawShowEmpty = function() {
                var content = document.getElementById('sawNotificationsContent');
                content.innerHTML = '<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center;">' +
                    '<div style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">🔔</div>' +
                    '<h4 style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #374151;">Žádné notifikace</h4>' +
                    '<p style="margin: 0; font-size: 14px; color: #9ca3af;">Zatím nemáte žádné oznámení</p>' +
                '</div>';
            };
            
            // Click on notification
            window.sawNotificationClick = function(id, url, isUnread) {
                if (isUnread) {
                    sawMarkAsRead(id);
                }
                if (url) {
                    window.location.href = url;
                }
            };
            
            // Mark as read
            window.sawMarkAsRead = function(id) {
                var formData = new FormData();
                formData.append('action', 'saw_mark_notification_read');
                formData.append('nonce', config.nonce);
                formData.append('notification_id', id);
                
                fetch(config.ajaxUrl, { method: 'POST', body: formData });
            };
            
            // Mark all as read
            window.sawMarkAllRead = function() {
                var formData = new FormData();
                formData.append('action', 'saw_mark_all_notifications_read');
                formData.append('nonce', config.nonce);
                
                fetch(config.ajaxUrl, { method: 'POST', body: formData })
                .then(function() {
                    // Update UI
                    var badge = document.getElementById('sawNotificationsBadge');
                    var headerCount = document.getElementById('sawNotificationsHeaderCount');
                    if (badge) badge.style.display = 'none';
                    if (headerCount) headerCount.style.display = 'none';
                    
                    // Reload notifications
                    sawLoadNotifications();
                });
            };
            
            // Escape HTML
            window.sawEscape = function(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };
            
            console.log('[SAW Notifications] Inline script loaded');
        })();
        </script>
        <?php
    }
    
    private function get_saw_user_id() {
        $wp_user_id = get_current_user_id();
        
        if (!$wp_user_id) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
            $wp_user_id
        ));
    }
    
    private function render_customer_switcher() {
        if (!class_exists('SAW_Component_Customer_Switcher')) {
            $file = SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        if (class_exists('SAW_Component_Customer_Switcher')) {
            $switcher = new SAW_Component_Customer_Switcher($this->customer);
            $switcher->render();
        }
    }
    
    private function render_language_switcher() {
        if (!class_exists('SAW_Component_Language_Switcher')) {
            $file = SAW_VISITORS_PLUGIN_DIR . 'includes/components/language-switcher/class-saw-component-language-switcher.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        if (class_exists('SAW_Component_Language_Switcher')) {
            // Získat aktuální jazyk uživatele z databáze
            $current_lang = SAW_Component_Language_Switcher::get_user_language();
            $switcher = new SAW_Component_Language_Switcher($current_lang);
            $switcher->render();
        }
    }
    
    private function render_user_dropdown() {
        $logout_url = wp_logout_url(home_url('/login/'));
        ?>
        <div class="sa-user-dropdown saw-user-dropdown" id="sawUserDropdown">
            <div class="sa-user-dropdown-header saw-user-dropdown-header">
                <div class="sa-user-dropdown-avatar saw-user-dropdown-avatar">
                    <?php echo esc_html(strtoupper(substr($this->user['name'], 0, 1))); ?>
                </div>
                <div class="sa-user-dropdown-info saw-user-dropdown-info">
                    <div class="sa-user-dropdown-name saw-user-dropdown-name"><?php echo esc_html($this->user['name']); ?></div>
                    <div class="sa-user-dropdown-email saw-user-dropdown-email"><?php echo esc_html($this->user['email']); ?></div>
                </div>
            </div>
            
            <div class="sa-user-dropdown-divider saw-user-dropdown-divider"></div>
            
            <a href="<?php echo esc_url(home_url('/admin/profile/')); ?>" class="sa-dropdown-item saw-dropdown-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <?php esc_html_e('Můj profil', 'saw-visitors'); ?>
            </a>
            
            <div class="sa-user-dropdown-divider saw-user-dropdown-divider"></div>
            
            <a href="<?php echo esc_url($logout_url); ?>" class="sa-dropdown-item sa-dropdown-item--danger saw-dropdown-item saw-dropdown-item-danger">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <?php esc_html_e('Odhlásit se', 'saw-visitors'); ?>
            </a>
        </div>
        <?php
    }
    
    private function get_role_label() {
        $roles = [
            'super_admin' => __('Super Admin', 'saw-visitors'),
            'admin' => __('Admin', 'saw-visitors'),
            'super_manager' => __('Super Manager', 'saw-visitors'),
            'manager' => __('Manager', 'saw-visitors'),
            'terminal' => __('Terminal', 'saw-visitors'),
        ];
        
        $role = $this->user['role'] ?? 'admin';
        
        return $roles[$role] ?? ucfirst($role);
    }
}