<?php
/**
 * SAW App Sidebar Component
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Sidebar {
    
    /**
     * @var array
     */
    private $user;
    
    /**
     * @var array
     */
    private $customer;
    
    /**
     * @var string
     */
    private $active_menu;
    
    /**
     * Konstruktor
     * 
     * @param array|null  $user        User data
     * @param array|null  $customer    Customer data
     * @param string      $active_menu Active menu ID
     */
    public function __construct($user = null, $customer = null, $active_menu = '') {
        $this->user = $user ?: array('role' => 'admin');
        $this->customer = $customer ?: array(
            'id' => 0,
            'name' => 'Demo zákazník',
            'logo_url' => '',
        );
        $this->active_menu = $active_menu;
    }
    
    /**
     * Get logo URL with fallback logic
     */
    private function get_logo_url() {
        if (!empty($this->customer['logo_url_full'])) {
            return $this->customer['logo_url_full'];
        }
        
        if (!empty($this->customer['logo_url'])) {
            return $this->customer['logo_url'];
        }
        
        return '';
    }
    
    /**
     * Render sidebar
     * 
     * @return void
     */
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
            <nav class="saw-sidebar-nav">
                <?php foreach ($menu as $section): ?>
                    <?php if (isset($section['heading'])): ?>
                        <div class="saw-nav-heading"><?php echo esc_html($section['heading']); ?></div>
                    <?php endif; ?>
                    
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
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php
    }
    
    /**
     * Získání menu položek
     * 
     * @return array
     */
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
                'heading' => 'Nastavení',
                'items' => array(
                    array(
                        'id' => 'customers',
                        'label' => 'Zákazníci',
                        'url' => '/admin/settings/customers',
                        'icon' => '🏢',
                    ),
                    array(
                        'id' => 'account-types',
                        'label' => 'Account Types',
                        'url' => '/admin/settings/account-types',
                        'icon' => '💳',
                    ),
                    array(
                        'id' => 'company',
                        'label' => 'Nastavení firmy',
                        'url' => '/admin/settings/company',
                        'icon' => '⚙️',
                    ),
                    array(
                        'id' => 'users',
                        'label' => 'Uživatelé',
                        'url' => '/admin/settings/users',
                        'icon' => '👤',
                    ),
                    array(
                        'id' => 'departments',
                        'label' => 'Oddělení',
                        'url' => '/admin/settings/departments',
                        'icon' => '🏛️',
                    ),
                    array(
                        'id' => 'content',
                        'label' => 'Školicí obsah',
                        'url' => '/admin/settings/content',
                        'icon' => '📚',
                    ),
                    array(
                        'id' => 'training',
                        'label' => 'Verze školení',
                        'url' => '/admin/settings/training',
                        'icon' => '🎓',
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