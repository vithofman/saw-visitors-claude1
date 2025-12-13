<?php
/**
 * SAW App Sidebar Component
 *
 * Renders application sidebar navigation with hierarchical menu,
 * permission checks, and branch switcher.
 *
 * @package SAW_Visitors
 * @since   4.6.2
 * @version 5.3.0 - ADDED: Calendar menu item
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
     * @return void
     */
    public function render() {
        $menu = $this->get_menu_items();
        ?>
        <div class="saw-sidebar-overlay" id="sawSidebarOverlay"></div>
        <aside class="saw-app-sidebar" id="sawAppSidebar">
            <?php if ($this->saw_role === 'super_admin' || $this->saw_role === 'admin'): ?>
                <?php $this->render_branch_switcher(); ?>
            <?php endif; ?>
            
            <nav class="saw-sidebar-nav">
                <?php 
                $first_section = true;
                foreach ($menu as $index => $section): 
                    $has_active = $this->section_has_active_item($section);
                    $is_collapsed = false;
                    
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
                        <div class="saw-nav-section <?php echo $is_collapsed ? 'collapsed' : ''; ?>">
                            <div class="saw-nav-heading">
                                <?php echo esc_html($section['heading']); ?>
                                <span class="saw-nav-heading-toggle">
                                    <svg viewBox="0 0 24 24">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </span>
                            </div>
                            <div class="saw-nav-items">
                                <?php foreach ($visible_items as $item): ?>
                                    <a 
                                        href="<?php echo esc_url($item['url']); ?>" 
                                        class="saw-nav-item <?php echo ($this->active_menu === $item['id']) ? 'active' : ''; ?>"
                                        data-menu="<?php echo esc_attr($item['id']); ?>"
                                        <?php if (!empty($item['target'])): ?>target="<?php echo esc_attr($item['target']); ?>"<?php endif; ?>
                                    >
                                        <span class="saw-nav-icon"><?php echo $item['icon']; ?></span>
                                        <span class="saw-nav-label"><?php echo esc_html($item['label']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($visible_items as $item): ?>
                            <a 
                                href="<?php echo esc_url($item['url']); ?>" 
                                class="saw-nav-item <?php echo ($this->active_menu === $item['id']) ? 'active' : ''; ?>"
                                data-menu="<?php echo esc_attr($item['id']); ?>"
                                <?php if (!empty($item['target'])): ?>target="<?php echo esc_attr($item['target']); ?>"<?php endif; ?>
                            >
                                <span class="saw-nav-icon"><?php echo $item['icon']; ?></span>
                                <span class="saw-nav-label"><?php echo esc_html($item['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php $first_section = false; ?>
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php
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