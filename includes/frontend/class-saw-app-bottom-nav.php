<?php
/**
 * SAW App Bottom Navigation Component
 *
 * Renders bottom navigation bar for mobile and tablet devices.
 * Replaces footer on smaller screens with app-like navigation.
 * Provides quick access to main sections: Dashboard, Visits, Visitors, Calendar.
 *
 * @package    SAW_Visitors
 * @subpackage Frontend
 * @since      5.4.0
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW App Bottom Nav Class
 *
 * Mobile-first bottom navigation component.
 * Displays 4 main navigation items with SVG icons.
 * Only visible on tablet and mobile devices (≤1024px).
 *
 * Features:
 * - Responsive design with iOS safe area support
 * - SVG icons for crisp rendering on retina displays
 * - Active state indication with animated top bar
 * - Touch-friendly tap targets (minimum 44x44px)
 * - Full accessibility support (ARIA labels, focus states)
 * - Multi-language support via SAW translation system
 *
 * @since 5.4.0
 */
class SAW_App_Bottom_Nav {
    
    /**
     * Active menu item ID
     *
     * Matches the ID of currently active page for highlighting.
     *
     * @since 5.4.0
     * @var string
     */
    private $active_menu;
    
    /**
     * Current UI language
     *
     * Language code for translations (cs, en, etc.).
     *
     * @since 5.4.0
     * @var string
     */
    private $lang;
    
    /**
     * SAW user role
     *
     * Used for permission checks if needed in future.
     *
     * @since 5.4.0
     * @var string
     */
    private $saw_role;
    
    /**
     * Navigation items cache
     *
     * Cached array of navigation items to avoid repeated builds.
     *
     * @since 5.4.0
     * @var array|null
     */
    private $nav_items = null;
    
    /**
     * Constructor
     *
     * Initializes bottom navigation with current context.
     *
     * @since 5.4.0
     * @param string $active_menu Active menu item ID (dashboard, visits, visitors, calendar)
     * @param string $lang        UI language code (default: cs)
     * @param string $saw_role    SAW user role for permission checks (default: user)
     */
    public function __construct($active_menu = '', $lang = 'cs', $saw_role = 'user') {
        $this->active_menu = $active_menu;
        $this->lang = $lang;
        $this->saw_role = $saw_role;
    }
    
    /**
     * Get navigation items
     *
     * Returns array of navigation items with labels, URLs, and icons.
     * Items are cached after first build.
     *
     * @since 5.4.0
     * @return array Navigation items array with keys: id, label, url, icon
     */
    private function get_nav_items() {
        // Return cached items if available
        if ($this->nav_items !== null) {
            return $this->nav_items;
        }
        
        $this->nav_items = array(
            array(
                'id'    => 'dashboard',
                'label' => $this->t('nav_home', 'Domů', 'Home'),
                'url'   => '/admin/dashboard',
                'icon'  => 'home',
            ),
            array(
                'id'    => 'visits',
                'label' => $this->t('nav_visits', 'Návštěvy', 'Visits'),
                'url'   => '/admin/visits',
                'icon'  => 'users',
            ),
            array(
                'id'    => 'visitors',
                'label' => $this->t('nav_visitors', 'Návštěvníci', 'Visitors'),
                'url'   => '/admin/visitors',
                'icon'  => 'user',
            ),
            array(
                'id'    => 'calendar',
                'label' => $this->t('nav_calendar', 'Kalendář', 'Calendar'),
                'url'   => '/admin/calendar',
                'icon'  => 'calendar',
            ),
        );
        
        /**
         * Filter bottom navigation items
         *
         * Allows modification of navigation items by other components.
         *
         * @since 5.4.0
         * @param array  $nav_items  Navigation items array
         * @param string $saw_role   Current user role
         * @param string $lang       Current language code
         */
        $this->nav_items = apply_filters('saw_bottom_nav_items', $this->nav_items, $this->saw_role, $this->lang);
        
        return $this->nav_items;
    }
    
    /**
     * Get SVG icon markup
     *
     * Returns optimized SVG icon based on icon name.
     * Uses Lucide-style icons for visual consistency with rest of app.
     * All icons are 24x24 with stroke-based design.
     *
     * @since 5.4.0
     * @param string $icon Icon name (home, users, user, calendar)
     * @return string SVG markup or empty string if icon not found
     */
    private function get_icon_svg($icon) {
        $icons = array(
            'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>',
            
            'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>',
            
            'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>',
            
            'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>',
            
            // Additional icons for future use
            'settings' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>',
            
            'more' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="19" cy="12" r="1"></circle>
                <circle cx="5" cy="12" r="1"></circle>
            </svg>',
        );
        
        return $icons[$icon] ?? '';
    }
    
    /**
     * Get translated text
     *
     * Retrieves translation from SAW translation system with fallback.
     * Supports both Czech and English with proper fallback chain.
     *
     * @since 5.4.0
     * @param string $key Translation key
     * @param string $cs  Czech fallback text
     * @param string $en  English fallback text
     * @return string Translated text
     */
    private function t($key, $cs, $en) {
        // Try SAW translation system first
        if (function_exists('saw_t')) {
            $translated = saw_t($key, $this->lang, 'admin', 'bottom_nav');
            // If translation found (not same as key), return it
            if ($translated !== $key) {
                return $translated;
            }
        }
        
        // Fallback based on language
        return ($this->lang === 'en') ? $en : $cs;
    }
    
    /**
     * Check if navigation item is active
     *
     * Determines if a navigation item matches the current page.
     * Supports both exact match and prefix match for sub-pages.
     *
     * @since 5.4.0
     * @param string $item_id Navigation item ID to check
     * @return bool True if item is active, false otherwise
     */
    private function is_active($item_id) {
        // Exact match
        if ($this->active_menu === $item_id) {
            return true;
        }
        
        // Prefix match for sub-pages (e.g., 'visits' matches 'visits_detail')
        if (strpos($this->active_menu, $item_id . '_') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get CSS classes for navigation item
     *
     * Builds CSS class string for a navigation item.
     *
     * @since 5.4.0
     * @param array $item Navigation item data
     * @return string CSS classes separated by space
     */
    private function get_item_classes($item) {
        $classes = array('saw-bottom-nav-item');
        
        if ($this->is_active($item['id'])) {
            $classes[] = 'active';
        }
        
        /**
         * Filter navigation item CSS classes
         *
         * @since 5.4.0
         * @param array $classes CSS classes array
         * @param array $item    Navigation item data
         */
        $classes = apply_filters('saw_bottom_nav_item_classes', $classes, $item);
        
        return implode(' ', $classes);
    }
    
    /**
     * Render bottom navigation
     *
     * Outputs complete bottom navigation HTML.
     * Hidden on desktop via CSS (>1024px).
     * Includes full accessibility support.
     *
     * @since 5.4.0
     * @return void
     */
    public function render() {
        $items = $this->get_nav_items();
        
        // Don't render if no items
        if (empty($items)) {
            return;
        }
        
        /**
         * Action before bottom navigation renders
         *
         * @since 5.4.0
         * @param array $items Navigation items
         */
        do_action('saw_before_bottom_nav', $items);
        ?>
        <nav 
            class="saw-bottom-nav" 
            id="sawBottomNav" 
            role="navigation" 
            aria-label="<?php echo esc_attr($this->t('aria_mobile_nav', 'Mobilní navigace', 'Mobile navigation')); ?>"
        >
            <div class="saw-bottom-nav-inner">
                <?php foreach ($items as $item): ?>
                    <?php $this->render_item($item); ?>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php
        
        /**
         * Action after bottom navigation renders
         *
         * @since 5.4.0
         * @param array $items Navigation items
         */
        do_action('saw_after_bottom_nav', $items);
    }
    
    /**
     * Render single navigation item
     *
     * Outputs HTML for one navigation item with icon and label.
     *
     * @since 5.4.0
     * @param array $item Navigation item data
     * @return void
     */
    private function render_item($item) {
        $is_active = $this->is_active($item['id']);
        $classes = $this->get_item_classes($item);
        ?>
        <a 
            href="<?php echo esc_url($item['url']); ?>" 
            class="<?php echo esc_attr($classes); ?>"
            data-menu-id="<?php echo esc_attr($item['id']); ?>"
            <?php if ($is_active): ?>
                aria-current="page"
            <?php endif; ?>
        >
            <span class="saw-bottom-nav-icon">
                <?php echo $this->get_icon_svg($item['icon']); ?>
            </span>
            <span class="saw-bottom-nav-label">
                <?php echo esc_html($item['label']); ?>
            </span>
        </a>
        <?php
    }
    
    /**
     * Get navigation item by ID
     *
     * Retrieves a single navigation item by its ID.
     * Useful for external components needing item data.
     *
     * @since 5.4.0
     * @param string $item_id Item ID to find
     * @return array|null Item data or null if not found
     */
    public function get_item($item_id) {
        $items = $this->get_nav_items();
        
        foreach ($items as $item) {
            if ($item['id'] === $item_id) {
                return $item;
            }
        }
        
        return null;
    }
    
    /**
     * Check if bottom navigation should be rendered
     *
     * Determines if bottom nav should be displayed based on context.
     * Can be overridden via filter.
     *
     * @since 5.4.0
     * @return bool True if should render, false otherwise
     */
    public function should_render() {
        $should_render = true;
        
        /**
         * Filter whether to render bottom navigation
         *
         * @since 5.4.0
         * @param bool   $should_render Whether to render
         * @param string $active_menu   Current active menu
         * @param string $saw_role      Current user role
         */
        return apply_filters('saw_should_render_bottom_nav', $should_render, $this->active_menu, $this->saw_role);
    }
    
    /**
     * Static factory method
     *
     * Creates and returns a new instance with automatic context detection.
     * Useful for quick instantiation in templates.
     *
     * @since 5.4.0
     * @param string $active_menu Active menu item ID
     * @return SAW_App_Bottom_Nav New instance
     */
    public static function create($active_menu = '') {
        // Try to get language from various sources
        $lang = 'cs';
        if (function_exists('saw_get_user_language')) {
            $lang = saw_get_user_language();
        } elseif (isset($_COOKIE['saw_ui_lang'])) {
            $lang = sanitize_text_field($_COOKIE['saw_ui_lang']);
        }
        
        // Try to get role
        $role = 'user';
        if (function_exists('saw_get_current_user_role')) {
            $role = saw_get_current_user_role();
        }
        
        return new self($active_menu, $lang, $role);
    }
}

/**
 * Helper function to render bottom navigation
 *
 * Convenience function for use in templates.
 * Creates instance and renders in one call.
 *
 * @since 5.4.0
 * @param string $active_menu Active menu item ID
 * @param string $lang        Language code (optional)
 * @param string $saw_role    User role (optional)
 * @return void
 */
function saw_render_bottom_nav($active_menu = '', $lang = 'cs', $saw_role = 'user') {
    $bottom_nav = new SAW_App_Bottom_Nav($active_menu, $lang, $saw_role);
    
    if ($bottom_nav->should_render()) {
        $bottom_nav->render();
    }
}