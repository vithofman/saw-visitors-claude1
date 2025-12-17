<?php
/**
 * SAW App Sidebar Component
 *
 * Renders application sidebar navigation with hierarchical menu,
 * permission checks, and branch switcher.
 *
 * @package SAW_Visitors
 * @since   4.6.2
 * @version 5.4.0 - REDESIGN: Modern gradient sidebar with glassmorphism switchers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW App Sidebar Class
 *
 * Main sidebar navigation component.
 * Displays menu items based on user permissions and role.
 * Includes branch switcher for admins and super admins.
 *
 * @since 4.6.1
 */
class SAW_App_Sidebar {
    
    /**
     * Current user data
     *
     * @since 4.6.1
     * @var array
     */
    private $user;
    
    /**
     * Current customer data
     *
     * @since 4.6.1
     * @var array
     */
    private $customer;
    
    /**
     * Active menu item ID
     *
     * @since 4.6.1
     * @var string
     */
    private $active_menu;
    
    /**
     * Current branch data
     *
     * @since 4.6.1
     * @var array|null
     */
    private $current_branch;
    
    /**
     * Current SAW user role
     *
     * @since 4.6.1
     * @var string
     */
    private $saw_role;
    
    /**
     * Current UI language
     *
     * @since 5.1.0
     * @var string
     */
    private $lang;
    
    /**
     * Constructor
     *
     * Initializes sidebar with user, customer, and menu state.
     *
     * @since 4.6.1
     * @param array|null  $user           Optional user data
     * @param array|null  $customer       Optional customer data
     * @param string      $active_menu    Active menu item ID
     * @param array|null  $current_branch Optional branch data
     */
    public function __construct($user = null, $customer = null, $active_menu = '', $current_branch = null) {
        $this->user = $user ?: array('role' => 'admin');
        $this->customer = $customer ?: array(
            'id' => 0,
            'name' => 'Demo zákazník',
            'logo_url' => '',
        );
        $this->active_menu = $active_menu;
        $this->current_branch = $current_branch ?: $this->load_current_branch();
        $this->saw_role = $this->get_current_saw_role();
        $this->lang = $this->get_user_ui_language();
    }
    
    /**
     * Get user's UI language
     *
     * Retrieves language preference from Language Switcher component.
     *
     * @since 5.1.0
     * @return string Language code (cs, en)
     */
    private function get_user_ui_language() {
        // Try Language Switcher component first
        if (class_exists('SAW_Component_Language_Switcher')) {
            return SAW_Component_Language_Switcher::get_user_language();
        }
        
        // Fallback: check user meta
        if (is_user_logged_in()) {
            $lang = get_user_meta(get_current_user_id(), 'saw_current_language', true);
            if ($lang) {
                return $lang;
            }
        }
        
        // Default
        return 'cs';
    }
    
    /**
     * Get current SAW user role
     *
     * Determines user role from SAW_Context, WordPress capabilities,
     * or database lookup.
     *
     * @since 4.6.1
     * @return string User role (super_admin, admin, manager, etc.)
     */
    private function get_current_saw_role() {
        // Check WordPress super admin first
        if (current_user_can('manage_options')) {
            if (class_exists('SAW_Context')) {
                $role = SAW_Context::get_role();
                if ($role) {
                    return $role;
                }
            }
            return 'super_admin';
        }
        
        // Check SAW Context
        if (class_exists('SAW_Context')) {
            $role = SAW_Context::get_role();
            if ($role) {
                return $role;
            }
        }
        
        // Fallback: database lookup
        global $wpdb;
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM %i 
             WHERE wp_user_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            get_current_user_id()
        ));
        
        return $saw_user->role ?? 'admin';
    }
    
    /**
     * Check if user can access module
     *
     * Uses SAW_Permissions class to verify access rights.
     * Super admins have access to everything.
     *
     * @since 4.6.1
     * @param string $module_slug Module identifier
     * @return bool True if user has access
     */
    private function can_access_module($module_slug) {
        // Terminal is accessible to admin roles only (not permission-based)
        if ($module_slug === 'terminal') {
            $admin_roles = array('super_admin', 'admin', 'super_manager', 'manager');
            return in_array($this->saw_role, $admin_roles, true);
        }
        
        // Calendar uses visits permissions (if user can see visits, can see calendar)
        if ($module_slug === 'calendar') {
            $module_slug = 'visits';
        }
        
        // Load permissions class if needed
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        // Fallback if permissions not available
        if (!class_exists('SAW_Permissions')) {
            return true;
        }
        
        // Translations module - only super admins
        if ($module_slug === 'translations') {
            return $this->saw_role === 'super_admin';
        }
        
        // Super admins have access to everything
        if ($this->saw_role === 'super_admin') {
            return true;
        }
        
        // Check permission
        return SAW_Permissions::check($this->saw_role, $module_slug, 'list');
    }
    
    /**
     * Load current branch data
     *
     * Retrieves branch information from database using SAW_Context.
     *
     * @since 4.6.1
     * @return array|null Branch data or null if not found
     */
    private function load_current_branch() {
        if (!class_exists('SAW_Context')) {
            return null;
        }
        
        $branch_id = SAW_Context::get_branch_id();
        
        if (!$branch_id || !$this->customer['id']) {
            return null;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, code, city 
             FROM %i 
             WHERE id = %d 
             AND customer_id = %d 
             AND is_active = 1",
            $table,
            $branch_id,
            $this->customer['id']
        ), ARRAY_A);
        
        return $branch ?: null;
    }
    
    /**
     * Check if section has active item
     *
     * Determines if any menu item in section matches active menu.
     * Used for highlighting active sections.
     *
     * @since 4.6.1
     * @param array $section Menu section data
     * @return bool True if section contains active item
     */
    private function section_has_active_item($section) {
        if (!isset($section['items'])) {
            return false;
        }
        
        foreach ($section['items'] as $item) {
            if ($this->active_menu === $item['id']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Map emoji/Dashicons to Lucide icon name
     *
     * @since 2.0.0
     * @param string $icon Emoji or Dashicons class
     * @return string Lucide icon name
     */
    private function map_icon($icon) {
        $map = [
            '📊' => 'bar-chart-3',
            '📅' => 'calendar',
            '🖥️' => 'settings', // Terminal - using settings as fallback
            '🏭' => 'building-2',
            '👥' => 'users',
            '🏢' => 'building-2',
            '📂' => 'folder',
            '👤' => 'user',
            '🌍' => 'globe',
            '📚' => 'file-text', // Content - using file-text
            '🦺' => 'shield',
            '🔒' => 'lock',
            '🏬' => 'building-2',
            '💳' => 'badge-check', // Account types
            '⚙️' => 'settings',
            '🌐' => 'globe',
            'ℹ️' => 'info',
        ];
        
        return $map[$icon] ?? 'settings';
    }
    
    /**
     * Get translated text
     *
     * Helper method to get translation with fallback.
     *
     * @since 5.1.0
     * @since 5.3.0 - ADDED: calendar translation
     * @param string $key Translation key
     * @return string Translated text or key as fallback
     */
    private function t($key) {
        if (function_exists('saw_t')) {
            return saw_t($key, $this->lang, 'admin', 'sidebar');
        }
        
        // Fallback translations
        $fallback = array(
            'terminal' => $this->lang === 'en' ? 'Terminal' : 'Terminál',
            'calendar' => $this->lang === 'en' ? 'Calendar' : 'Kalendář',
        );
        
        return $fallback[$key] ?? $key;
    }
    
    /**
     * Render sidebar
     *
     * Outputs complete sidebar HTML including branch switcher
     * and navigation menu with permission filtering.
     *
     * @since 4.6.1
     * @since 5.4.0 - REDESIGN: Wrapped switchers in glassmorphism container
     * @return void
     */
    public function render() {
        $menu = $this->get_menu_items();
        $show_switchers = ($this->saw_role === 'super_admin' || $this->saw_role === 'admin');
        ?>
        <div class="sa-sidebar-overlay" id="sawSidebarOverlay"></div>
        <aside class="sa-app-sidebar" id="sawAppSidebar">
            
            <?php if ($show_switchers): ?>
                <!-- Switchers Section - Glassmorphism Container -->
                <div class="sa-sidebar-switchers">
                    <?php $this->render_customer_switcher(); ?>
                    <?php $this->render_branch_switcher(); ?>
                </div>
            <?php endif; ?>
            
            <nav class="sa-sidebar-nav">
                <?php 
                $section_index = 0;
                
                // Section IDs for tracking (used by JavaScript)
                $section_ids = array(
                    'section_visits' => 'visits',
                    'section_organization' => 'organization',
                    'section_training' => 'training',
                    'section_system' => 'system',
                );
                
                // Sections that are open by default (first section without heading is always open)
                // "NÁVŠTĚVY" (visits) is open by default
                $default_open_sections = array('visits');
                
                foreach ($menu as $index => $section): 
                    $has_active = $this->section_has_active_item($section);
                    
                    // Filter items by permissions
                    $visible_items = array();
                    foreach ($section['items'] as $item) {
                        if ($this->can_access_module($item['id'])) {
                            $visible_items[] = $item;
                        }
                    }
                    
                    // Skip empty sections
                    if (empty($visible_items)) {
                        continue;
                    }
                ?>
                    <?php if (isset($section['heading'])): ?>
                        <?php
                        // Determine section ID from heading translation key
                        $section_id = '';
                        foreach ($section_ids as $key => $id) {
                            if ($section['heading'] === $this->t($key)) {
                                $section_id = $id;
                                break;
                            }
                        }
                        
                        // Determine collapsed state:
                        // 1. If section contains active item → OPEN
                        // 2. If section is in default_open_sections → OPEN
                        // 3. Otherwise → COLLAPSED
                        $is_collapsed = true;
                        if ($has_active) {
                            $is_collapsed = false; // Active section is always open
                        } elseif (in_array($section_id, $default_open_sections)) {
                            $is_collapsed = false; // Default open sections
                        }
                        ?>
                        <div class="sa-nav-section <?php echo $is_collapsed ? 'sa-nav-section--collapsed' : ''; ?>" 
                             data-section-id="<?php echo esc_attr($section_id); ?>"
                             <?php if ($has_active): ?>data-has-active="1"<?php endif; ?>>
                            <div class="sa-nav-heading" data-section-toggle>
                                <?php echo esc_html($section['heading']); ?>
                                <span class="sa-nav-heading-toggle">
                                    <svg viewBox="0 0 24 24">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </span>
                            </div>
                            <div class="sa-nav-items">
                                <?php foreach ($visible_items as $item): ?>
                                    <a 
                                        href="<?php echo esc_url($item['url']); ?>" 
                                        class="sa-nav-item <?php echo ($this->active_menu === $item['id']) ? 'sa-nav-item--active' : ''; ?>"
                                        data-menu="<?php echo esc_attr($item['id']); ?>"
                                        data-section="<?php echo esc_attr($section_id); ?>"
                                        <?php if (!empty($item['target'])): ?>target="<?php echo esc_attr($item['target']); ?>"<?php endif; ?>
                                    >
                                        <span class="sa-nav-icon"><?php 
                                            // Convert emoji/Dashicons to SAW_Icons
                                            if (class_exists('SAW_Icons')) {
                                                $icon_name = $this->map_icon($item['icon']);
                                                echo SAW_Icons::get($icon_name, 'sa-icon--md');
                                            } else {
                                                echo esc_html($item['icon']);
                                            }
                                        ?></span>
                                        <span class="sa-nav-label"><?php echo esc_html($item['label']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- First section without heading (Dashboard, Calendar, Terminal) - always visible -->
                        <?php foreach ($visible_items as $item): ?>
                            <a 
                                href="<?php echo esc_url($item['url']); ?>" 
                                class="sa-nav-item <?php echo ($this->active_menu === $item['id']) ? 'sa-nav-item--active' : ''; ?>"
                                data-menu="<?php echo esc_attr($item['id']); ?>"
                                data-section="main"
                                <?php if (!empty($item['target'])): ?>target="<?php echo esc_attr($item['target']); ?>"<?php endif; ?>
                            >
                                <span class="sa-nav-icon"><?php 
                                    // Convert emoji/Dashicons to SAW_Icons
                                    if (class_exists('SAW_Icons')) {
                                        $icon_name = $this->map_icon($item['icon']);
                                        echo SAW_Icons::get($icon_name, 'sa-icon--md');
                                    } else {
                                        echo esc_html($item['icon']);
                                    }
                                ?></span>
                                <span class="sa-nav-label"><?php echo esc_html($item['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php $section_index++; ?>
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php
    }
    
    /**
     * Render customer switcher component
     *
     * Loads and renders customer switcher for super admins.
     *
     * @since 6.0.0
     * @return void
     */
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
    
    /**
     * Render branch switcher component
     *
     * Loads and renders branch switcher for admins/super admins.
     *
     * @since 4.6.1
     * @return void
     */
    private function render_branch_switcher() {
        if (!class_exists('SAW_Component_Branch_Switcher')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/branch-switcher/class-saw-component-branch-switcher.php';
        }
        
        $switcher = new SAW_Component_Branch_Switcher($this->customer['id'], $this->current_branch);
        $switcher->render();
    }
    
    /**
     * Get menu items
     *
     * Returns complete menu structure with sections and items.
     * All labels are translated using SAW_Translations.
     *
     * @since 4.6.1
     * @version 5.3.0 - ADDED: Calendar menu item
     * @return array Menu structure
     */
    private function get_menu_items() {
        return array(
            // ===============================================
            // HLAVNÍ NAVIGACE
            // ===============================================
            array(
                'items' => array(
                    array(
                        'id' => 'dashboard', 
                        'label' => $this->t('dashboard'), 
                        'url' => '/admin/dashboard', 
                        'icon' => '📊'
                    ),
                    array(
                        'id' => 'calendar', 
                        'label' => 'Kalendář', 
                        'url' => '/admin/calendar', 
                        'icon' => '📅'
                    ),
                    array(
                        'id' => 'terminal', 
                        'label' => 'Terminal', 
                        'url' => '/terminal/', 
                        'icon' => '🖥️'
                    ),
                ),
            ),
            
            // ===============================================
            // NÁVŠTĚVY
            // ===============================================
            array(
                'heading' => $this->t('section_visits'),
                'items' => array(
                    array(
                        'id' => 'companies', 
                        'label' => $this->t('companies'), 
                        'url' => '/admin/companies', 
                        'icon' => '🏭'
                    ),
                    array(
                        'id' => 'visits', 
                        'label' => $this->t('visits'), 
                        'url' => '/admin/visits', 
                        'icon' => '👥'
                    ),
                    array(
                        'id' => 'visitors', 
                        'label' => $this->t('visitors'), 
                        'url' => '/admin/visitors', 
                        'icon' => '👥'
                    ),
                ),
            ),

            // ===============================================
            // ORGANIZACE
            // ===============================================
            array(
                'heading' => $this->t('section_organization'),
                'items' => array(
                    array(
                        'id' => 'branches', 
                        'label' => $this->t('branches'), 
                        'url' => '/admin/branches', 
                        'icon' => '🏢'
                    ),                    
                    array(
                        'id' => 'departments', 
                        'label' => $this->t('departments'), 
                        'url' => '/admin/departments', 
                        'icon' => '📂'
                    ),
                    array(
                        'id' => 'users', 
                        'label' => $this->t('users'), 
                        'url' => '/admin/users', 
                        'icon' => '👤'
                    ),
                ),
            ),
            
            // ===============================================
            // ŠKOLENÍ
            // ===============================================
            array(
                'heading' => $this->t('section_training'),
                'items' => array(
                    array(
                        'id' => 'training-languages', 
                        'label' => $this->t('training_languages'), 
                        'url' => '/admin/training-languages', 
                        'icon' => '🌍'
                    ),
                    array(
                        'id' => 'content', 
                        'label' => $this->t('content'), 
                        'url' => '/admin/content', 
                        'icon' => '📚'
                    ),
                    array(
                        'id' => 'oopp', 
                        'label' => $this->t('oopp'), 
                        'url' => '/admin/oopp', 
                        'icon' => '🦺'
                    ),
                ),
            ),
            
            // ===============================================
            // SYSTÉM
            // ===============================================
            array(
                'heading' => $this->t('section_system'),
                'items' => array(
                    array(
                        'id' => 'permissions', 
                        'label' => $this->t('permissions'), 
                        'url' => '/admin/permissions', 
                        'icon' => '🔒'
                    ),
                    array(
                        'id' => 'customers', 
                        'label' => $this->t('customers'), 
                        'url' => '/admin/customers', 
                        'icon' => '🏬'
                    ),
                    array(
                        'id' => 'account-types', 
                        'label' => $this->t('account_types'), 
                        'url' => '/admin/account-types', 
                        'icon' => '💳'
                    ),
                    array(
                        'id' => 'settings', 
                        'label' => $this->t('settings'), 
                        'url' => '/admin/settings', 
                        'icon' => '⚙️'
                    ),
                    array(
                        'id' => 'translations', 
                        'label' => 'Překlady', 
                        'url' => '/admin/translations', 
                        'icon' => '🌐'
                    ),
                    array(
                        'id' => 'about', 
                        'label' => $this->t('about'), 
                        'url' => '/admin/about', 
                        'icon' => 'ℹ️'
                    ),
                ),
            ),
        );
    }
}