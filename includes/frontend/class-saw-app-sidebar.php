<?php
/**
 * SAW App Sidebar Component
 * 
 * @package SAW_Visitors
 * @version 4.7.0
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Sidebar {
    
    private $user;
    private $customer;
    private $active_menu;
    private $current_branch;
    
    public function __construct($user = null, $customer = null, $active_menu = '', $current_branch = null) {
        $this->user = $user ?: array('role' => 'admin');
        $this->customer = $customer ?: array(
            'id' => 0,
            'name' => 'Demo zákazník',
            'logo_url' => '',
        );
        $this->active_menu = $active_menu;
        $this->current_branch = $current_branch ?: $this->load_current_branch();
    }
    
    private function get_logo_url() {
        if (!empty($this->customer['logo_url_full'])) {
            return $this->customer['logo_url_full'];
        }
        
        if (!empty($this->customer['logo_url'])) {
            return $this->customer['logo_url'];
        }
        
        return '';
    }
    
    private function load_current_branch() {
        $branch_id = $this->get_current_branch_id();
        
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
        
        if (!$branch) {
            $this->clear_invalid_branch();
            return null;
        }
        
        return $branch;
    }
    
    private function clear_invalid_branch() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['saw_current_branch_id']);
        
        if (is_user_logged_in()) {
            delete_user_meta(get_current_user_id(), 'saw_current_branch_id');
        }
    }
    
    private function get_current_branch_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_branch_id'])) {
            return intval($_SESSION['saw_current_branch_id']);
        }
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            if (isset($_SESSION['saw_current_customer_id'])) {
                $customer_id = intval($_SESSION['saw_current_customer_id']);
                $branch_id = get_user_meta($user_id, 'saw_branch_customer_' . $customer_id, true);
                
                if ($branch_id) {
                    $_SESSION['saw_current_branch_id'] = intval($branch_id);
                    return intval($branch_id);
                }
            }
            
            $branch_id = get_user_meta($user_id, 'saw_current_branch_id', true);
            if ($branch_id) {
                $_SESSION['saw_current_branch_id'] = intval($branch_id);
                return intval($branch_id);
            }
        }
        
        return null;
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
        $logo_url = $this->get_logo_url();
        ?>
        <div class="saw-sidebar-overlay" id="sawSidebarOverlay"></div>
        <aside class="saw-app-sidebar" id="sawAppSidebar">
            <div class="saw-sidebar-header">
                <div class="saw-sidebar-mobile-customer">
                    <div class="saw-sidebar-mobile-logo">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($this->customer['name']); ?>" class="saw-sidebar-logo-image">
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 40 40" fill="none" class="saw-sidebar-logo-fallback">
                                <rect width="40" height="40" rx="8" fill="#2563eb"/>
                                <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="saw-sidebar-mobile-customer-name">
                        <?php echo esc_html($this->customer['name']); ?>
                    </div>
                </div>
                <button class="saw-sidebar-close" id="sawSidebarClose" aria-label="Zavřít menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <?php $this->render_branch_switcher(); ?>
            
            <nav class="saw-sidebar-nav">
                <?php 
                $first_section = true;
                foreach ($menu as $index => $section): 
                    $has_active = $this->section_has_active_item($section);
                    $is_collapsed = !$first_section && !$has_active;
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
                                <?php foreach ($section['items'] as $item): ?>
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
                        <?php $first_section = false; ?>
                    <?php else: ?>
                        <?php foreach ($section['items'] as $item): ?>
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
        $menu = array(
            array(
                'items' => array(
                    array(
                        'id' => 'dashboard',
                        'label' => 'Dashboard',
                        'url' => '/admin/',
                        'icon' => '📊',
                    ),
                    array(
                        'id' => 'invitations',
                        'label' => 'Pozvánky',
                        'url' => '/admin/invitations',
                        'icon' => '📧',
                    ),
                    array(
                        'id' => 'visits',
                        'label' => 'Přehled návštěv',
                        'url' => '/admin/visits',
                        'icon' => '👥',
                    ),
                    array(
                        'id' => 'statistics',
                        'label' => 'Statistiky',
                        'url' => '/admin/statistics',
                        'icon' => '📈',
                    ),
                ),
            ),
            array(
                'heading' => 'Organizace',
                'items' => array(
                    array(
                        'id' => 'branches',
                        'label' => 'Pobočky',
                        'url' => '/admin/branches',
                        'icon' => '🏢',
                    ),
                    array(
                        'id' => 'departments',
                        'label' => 'Oddělení',
                        'url' => '/admin/departments',
                        'icon' => '📂',
                    ),
                    array(
                        'id' => 'users',
                        'label' => 'Uživatelé',
                        'url' => '/admin/settings/users',
                        'icon' => '👤',
                    ),
                ),
            ),
            array(
                'heading' => 'Školení',
                'items' => array(
                    array(
                        'id' => 'training-languages',
                        'label' => 'Jazyky',
                        'url' => '/admin/training-languages',
                        'icon' => '🌐',
                    ),
                    array(
                        'id' => 'content',
                        'label' => 'Obsah',
                        'url' => '/admin/settings/content',
                        'icon' => '📚',
                    ),
                    array(
                        'id' => 'training',
                        'label' => 'Verze',
                        'url' => '/admin/settings/training',
                        'icon' => '🎓',
                    ),
                ),
            ),
            array(
                'heading' => 'Systém',
                'items' => array(
                    array(
                        'id' => 'customers',
                        'label' => 'Zákazníci',
                        'url' => '/admin/settings/customers',
                        'icon' => '🏬',
                    ),
                    array(
                        'id' => 'account-types',
                        'label' => 'Typy účtů',
                        'url' => '/admin/settings/account-types',
                        'icon' => '💳',
                    ),
                    array(
                        'id' => 'company',
                        'label' => 'Firma',
                        'url' => '/admin/settings/company',
                        'icon' => '⚙️',
                    ),
                    array(
                        'id' => 'about',
                        'label' => 'O aplikaci',
                        'url' => '/admin/settings/about',
                        'icon' => 'ℹ️',
                    ),
                ),
            ),
        );
        
        return $menu;
    }
}