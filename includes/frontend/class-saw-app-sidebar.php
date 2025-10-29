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
        $this->customer = $customer;
        $this->active_menu = $active_menu;
    }
    
    /**
     * Render sidebar
     * 
     * @return void
     */
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
                        <a 
                            href="<?php echo esc_url($item['url']); ?>" 
                            class="saw-nav-item <?php echo ($this->active_menu === $item['id']) ? 'active' : ''; ?>"
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
                        'icon' => '🛏️',
                    ),
                    array(
                        'id' => 'content',
                        'label' => 'Školící obsah',
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