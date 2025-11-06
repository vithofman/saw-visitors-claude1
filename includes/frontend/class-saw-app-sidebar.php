<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Sidebar {
    
    private $user;
    private $customer;
    private $active_menu;
    private $current_branch;
    private $saw_role;
    
    public function __construct($user = null, $customer = null, $active_menu = '', $current_branch = null) {
        $this->user = $user ?: ['role' => 'admin'];
        $this->customer = $customer ?: [
            'id' => 0,
            'name' => 'Demo zákazník',
            'logo_url' => '',
        ];
        $this->active_menu = $active_menu;
        $this->current_branch = $current_branch ?: $this->load_current_branch();
        $this->saw_role = $this->get_current_saw_role();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Sidebar] Role: %s, Active menu: %s', $this->saw_role ?? 'NULL', $active_menu));
        }
    }
    
    private function get_current_saw_role() {
        if (current_user_can('manage_options')) {
            if (class_exists('SAW_Context')) {
                $role = SAW_Context::get_role();
                if ($role) {
                    return $role;
                }
            }
            return 'super_admin';
        }
        
        if (class_exists('SAW_Context')) {
            $role = SAW_Context::get_role();
            if ($role) {
                return $role;
            }
        }
        
        global $wpdb;
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
        
        return $saw_user->role ?? 'admin';
    }
    
    private function can_access_module($module_slug) {
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        if (!class_exists('SAW_Permissions')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Sidebar] SAW_Permissions class not found');
            }
            return true;
        }
        
        if ($this->saw_role === 'super_admin') {
            return true;
        }
        
        $has_access = SAW_Permissions::check($this->saw_role, $module_slug, 'list');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Sidebar] Permission check - Role: %s, Module: %s, Access: %s',
                $this->saw_role,
                $module_slug,
                $has_access ? 'YES' : 'NO'
            ));
        }
        
        return $has_access;
    }
    
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
             FROM {$table} 
             WHERE id = %d 
             AND customer_id = %d 
             AND is_active = 1",
            $branch_id,
            $this->customer['id']
        ), ARRAY_A);
        
        return $branch ?: null;
    }
    
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
                    
                    $visible_items = [];
                    foreach ($section['items'] as $item) {
                        if ($this->can_access_module($item['id'])) {
                            $visible_items[] = $item;
                        }
                    }
                    
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
    
    private function render_branch_switcher() {
        if (!class_exists('SAW_Component_Branch_Switcher')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/branch-switcher/class-saw-component-branch-switcher.php';
        }
        
        $switcher = new SAW_Component_Branch_Switcher($this->customer['id'], $this->current_branch);
        $switcher->render();
    }
    
    private function get_menu_items() {
        return [
            [
                'items' => [
                    ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => '/admin/', 'icon' => '📊'],
                    ['id' => 'invitations', 'label' => 'Pozvánky', 'url' => '/admin/invitations', 'icon' => '📧'],
                    ['id' => 'visits', 'label' => 'Přehled návštěv', 'url' => '/admin/visits', 'icon' => '👥'],
                    ['id' => 'statistics', 'label' => 'Statistiky', 'url' => '/admin/statistics', 'icon' => '📈'],
                ],
            ],
            [
                'heading' => 'Organizace',
                'items' => [
                    ['id' => 'branches', 'label' => 'Pobočky', 'url' => '/admin/branches', 'icon' => '🏢'],
                    ['id' => 'departments', 'label' => 'Oddělení', 'url' => '/admin/departments', 'icon' => '📂'],
                    ['id' => 'users', 'label' => 'Uživatelé', 'url' => '/admin/users', 'icon' => '👤'],
                ],
            ],
            [
                'heading' => 'Školení',
                'items' => [
                    ['id' => 'training-languages', 'label' => 'Jazyky', 'url' => '/admin/training-languages', 'icon' => '🌍'],
                    ['id' => 'content', 'label' => 'Obsah', 'url' => '/admin/settings/content', 'icon' => '📚'],
                    ['id' => 'training', 'label' => 'Verze', 'url' => '/admin/settings/training', 'icon' => '🎓'],
                ],
            ],
            [
                'heading' => 'Systém',
                'items' => [
                    ['id' => 'permissions', 'label' => 'Oprávnění', 'url' => '/admin/permissions', 'icon' => '🔐'],
                    ['id' => 'customers', 'label' => 'Zákazníci', 'url' => '/admin/settings/customers', 'icon' => '🏬'],
                    ['id' => 'account-types', 'label' => 'Typy účtů', 'url' => '/admin/settings/account-types', 'icon' => '💳'],
                    ['id' => 'company', 'label' => 'Firma', 'url' => '/admin/settings/company', 'icon' => '⚙️'],
                    ['id' => 'about', 'label' => 'O aplikaci', 'url' => '/admin/settings/about', 'icon' => 'ℹ️'],
                ],
            ],
        ];
    }
}