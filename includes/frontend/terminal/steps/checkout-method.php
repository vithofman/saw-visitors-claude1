<?php
/**
 * Terminal Step - Checkout Method Selection
 * 
 * Choose between PIN-based checkout or search-based checkout
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$translations = [
    'cs' => [
        'title' => 'Jak se chcete odhlásit?',
        'subtitle' => 'Vyberte způsob odhlášení',
        'pin' => 'Mám PIN kód',
        'pin_desc' => 'Zadám PIN a vyberu všechny odcházející',
        'search' => 'Vyhledat mě',
        'search_desc' => 'Najdu se podle jména',
    ],
    'en' => [
        'title' => 'How would you like to check out?',
        'subtitle' => 'Select checkout method',
        'pin' => 'I have a PIN code',
        'pin_desc' => 'I will enter PIN and select all departing visitors',
        'search' => 'Find me',
        'search_desc' => 'I will search by my name',
    ],
    'uk' => [
        'title' => 'Як ви хочете виписатися?',
        'subtitle' => 'Виберіть спосіб виписки',
        'pin' => 'У мене є PIN-код',
        'pin_desc' => 'Я введу PIN і виберу всіх, хто від\'їжджає',
        'search' => 'Знайдіть мене',
        'search_desc' => 'Я буду шукати за своїм ім\'ям',
    ],
];
$t = $translations[$lang] ?? $translations['cs'];
?>
<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        <div class="saw-terminal-grid-2">
            
            <!-- PIN Method -->
            <a href="<?php echo home_url('/terminal/checkout-pin/'); ?>" 
               class="saw-terminal-btn saw-terminal-btn-icon">
                <span class="icon">🔐</span>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700;">
                        <?php echo esc_html($t['pin']); ?>
                    </div>
                    <div style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                        <?php echo esc_html($t['pin_desc']); ?>
                    </div>
                </div>
            </a>
            
            <!-- Search Method -->
            <a href="<?php echo home_url('/terminal/checkout-search/'); ?>" 
               class="saw-terminal-btn saw-terminal-btn-icon saw-terminal-btn-secondary">
                <span class="icon">🔍</span>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700;">
                        <?php echo esc_html($t['search']); ?>
                    </div>
                    <div style="font-size: 1rem; font-weight: 400; opacity: 0.9;">
                        <?php echo esc_html($t['search_desc']); ?>
                    </div>
                </div>
            </a>
            
        </div>
    </div>
</div>