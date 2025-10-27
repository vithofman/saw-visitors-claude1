<?php
/**
 * SAW App Sidebar Component
 * 
 * @package SAW_Visitors
 * @subpackage Frontend
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Sidebar {
    
    private $user;
    private $customer;
    private $active_menu;
    
    public function __construct($user, $customer, $active_menu = '') {
        $this->user = $user;
        $this->customer = $customer;
        $this->active_menu = $active_menu;
    }
    
    public function render() {
        $menu = $this->get_menu_items();
        ?>
        <aside class="saw-app-sidebar">
            <nav class="saw-sidebar-nav">
                <?php foreach ($menu as $section): ?>
                    <?php if (isset($section['heading'])): ?>
                        <div class="saw-nav-heading"><?php echo esc_html($section['heading']); ?></div>
                    <?php endif; ?>
                    
                    <?php foreach ($section['items'] as $item): ?>
                        <?php if ($this->can_access($item)): ?>
                            <a 
                                href="<?php echo esc_url($item['url']); ?>" 
                                class="saw-nav-item <?php echo ($this->active_menu === $item['id']) ? 'active' : ''; ?>"
                            >
                                <span class="saw-nav-icon"><?php echo $item['icon']; ?></span>
                                <span class="saw-nav-label"><?php echo esc_html($item['label']); ?></span>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="saw-nav-badge"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php
    }
    
    private function get_menu_items() {
        $base_url = $this->get_base_url();
        
        return array(
            array(
                'items' => array(
                    array(
                        'id' => 'dashboard',
                        'label' => 'Dashboard',
                        'url' => $base_url . '/',
                        'icon' => '📊',
                        'roles' => array('admin', 'manager'),
                    ),
                    array(
                        'id' => 'invitations',
                        'label' => 'Pozvánky',
                        'url' => $base_url . '/invitations',
                        'icon' => '📧',
                        'roles' => array('admin', 'manager'),
                    ),
                    array(
                        'id' => 'visits',
                        'label' => 'Přehled návštěv',
                        'url' => $base_url . '/visits',
                        'icon' => '👥',
                        'roles' => array('admin', 'manager'),
                    ),
                    array(
                        'id' => 'statistics',
                        'label' => 'Statistiky',
                        'url' => $base_url . '/statistics',
                        'icon' => '📈',
                        'roles' => array('admin'),
                    ),
                ),
            ),
            array(
                'heading' => 'Nastavení',
                'items' => array(
                    array(
                        'id' => 'customers',
                        'label' => 'Správa zákazníků',
                        'url' => $base_url . '/settings/customers',
                        'icon' => '🏢',
                        'roles' => array('super_admin'),
                    ),
                    array(
                        'id' => 'company',
                        'label' => 'Nastavení firmy',
                        'url' => $base_url . '/settings/company',
                        'icon' => '⚙️',
                        'roles' => array('admin'),
                    ),
                    array(
                        'id' => 'users',
                        'label' => 'Uživatelé',
                        'url' => $base_url . '/settings/users',
                        'icon' => '👤',
                        'roles' => array('super_admin', 'admin'),
                    ),
                    array(
                        'id' => 'departments',
                        'label' => 'Oddělení',
                        'url' => $base_url . '/settings/departments',
                        'icon' => '🏛️',
                        'roles' => array('admin'),
                    ),
                    array(
                        'id' => 'content',
                        'label' => 'Školící obsah',
                        'url' => $base_url . '/settings/content',
                        'icon' => '📚',
                        'roles' => array('admin'),
                    ),
                    array(
                        'id' => 'training',
                        'label' => 'Verze školení',
                        'url' => $base_url . '/settings/training',
                        'icon' => '🎓',
                        'roles' => array('admin'),
                    ),
                    array(
                        'id' => 'audit',
                        'label' => 'Audit Log',
                        'url' => $base_url . '/settings/audit',
                        'icon' => '📋',
                        'roles' => array('super_admin', 'admin'),
                    ),
                    array(
                        'id' => 'email-queue',
                        'label' => 'Email Queue',
                        'url' => $base_url . '/settings/email-queue',
                        'icon' => '✉️',
                        'roles' => array('super_admin', 'admin'),
                    ),
                    array(
                        'id' => 'about',
                        'label' => 'O aplikaci',
                        'url' => $base_url . '/settings/about',
                        'icon' => 'ℹ️',
                        'roles' => array('admin', 'manager'),
                    ),
                ),
            ),
        );
    }
    
    private function get_base_url() {
        if ($this->is_super_admin() || $this->user['role'] === 'admin') {
            return '/admin';
        }
        
        if ($this->user['role'] === 'manager') {
            return '/manager';
        }
        
        return '/terminal';
    }
    
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
    
    private function can_access($item) {
        if (empty($item['roles'])) {
            return true;
        }
        
        if ($this->is_super_admin() && in_array('super_admin', $item['roles'])) {
            return true;
        }
        
        $user_role = $this->user['role'] ?? 'manager';
        return in_array($user_role, $item['roles']);
    }
}
