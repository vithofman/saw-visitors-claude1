<?php
/**
 * Terminal Step - Checkout via Search
 * 
 * Search for visitor by name and check them out
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
        'title' => 'Vyhledat návštěvníka',
        'subtitle' => 'Zadejte své jméno nebo příjmení',
        'search_placeholder' => 'Začněte psát vaše jméno...',
        'search_btn' => 'Hledat',
        'results_title' => 'Nalezené návštěvy',
        'no_results' => 'Nebyla nalezena žádná aktivní návštěva',
        'checkout_btn' => 'Odhlásit se',
    ],
    'en' => [
        'title' => 'Find Visitor',
        'subtitle' => 'Enter your first or last name',
        'search_placeholder' => 'Start typing your name...',
        'search_btn' => 'Search',
        'results_title' => 'Found Visits',
        'no_results' => 'No active visit found',
        'checkout_btn' => 'Check Out',
    ],
    'uk' => [
        'title' => 'Знайти відвідувача',
        'subtitle' => 'Введіть своє ім\'я або прізвище',
        'search_placeholder' => 'Почніть вводити своє ім\'я...',
        'search_btn' => 'Шукати',
        'results_title' => 'Знайдені візити',
        'no_results' => 'Активний візит не знайдено',
        'checkout_btn' => 'Виписатися',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// TODO: Implement actual search
$search_results = [];
$searched = false;

if (isset($_POST['search_query']) && !empty($_POST['search_query'])) {
    $searched = true;
    // Mock results
    $search_results = [
        [
            'visitor_id' => 1,
            'visit_id' => 10,
            'first_name' => 'Jan',
            'last_name' => 'Novák',
            'company_name' => 'ACME s.r.o.',
            'position' => 'Obchodní ředitel',
            'checked_in_at' => '2024-11-16 08:30:00',
        ],
    ];
}
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            🔍 <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <!-- Search Form -->
        <form method="POST" class="saw-terminal-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            
            <div class="saw-terminal-form-group">
                <input type="text" 
                       name="search_query" 
                       class="saw-terminal-form-input" 
                       placeholder="<?php echo esc_attr($t['search_placeholder']); ?>"
                       autofocus
                       required>
            </div>
            
            <button type="submit" class="saw-terminal-btn">
                🔍 <?php echo esc_html($t['search_btn']); ?>
            </button>
        </form>
        
        <?php if ($searched): ?>
        
        <hr style="margin: 2.5rem 0; border: 0; border-top: 2px solid #e2e8f0;">
        
        <?php if (empty($search_results)): ?>
        
        <!-- No Results -->
        <div style="text-align: center; padding: 3rem 0;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">😕</div>
            <h3 style="font-size: 1.5rem; color: #718096; margin: 0;">
                <?php echo esc_html($t['no_results']); ?>
            </h3>
        </div>
        
        <?php else: ?>
        
        <!-- Results -->
        <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 1.5rem 0;">
            <?php echo esc_html($t['results_title']); ?>:
        </h3>
        
        <div class="saw-terminal-visitor-list">
            <?php foreach ($search_results as $result): ?>
            
            <div class="saw-terminal-visitor-item" style="cursor: default;">
                <div class="saw-terminal-visitor-avatar">
                    <?php echo strtoupper(substr($result['first_name'], 0, 1)); ?>
                </div>
                
                <div class="saw-terminal-visitor-info" style="flex: 1;">
                    <h3 class="saw-terminal-visitor-name">
                        <?php echo esc_html($result['first_name'] . ' ' . $result['last_name']); ?>
                    </h3>
                    <?php if (!empty($result['position'])): ?>
                    <p class="saw-terminal-visitor-position">
                        <?php echo esc_html($result['position']); ?>
                        <?php if (!empty($result['company_name'])): ?>
                        - <?php echo esc_html($result['company_name']); ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <p class="saw-terminal-visitor-position">
                        Příchod: <?php echo date('H:i', strtotime($result['checked_in_at'])); ?>
                    </p>
                </div>
                
                <form method="POST" style="margin: 0;">
                    <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                    <input type="hidden" name="terminal_action" value="checkout_search">
                    <input type="hidden" name="visitor_id" value="<?php echo $result['visitor_id']; ?>">
                    <input type="hidden" name="visit_id" value="<?php echo $result['visit_id']; ?>">
                    
                    <button type="submit" class="saw-terminal-btn saw-terminal-btn-danger" style="margin: 0; width: auto; padding: 1rem 2rem;">
                        <?php echo esc_html($t['checkout_btn']); ?>
                    </button>
                </form>
            </div>
            
            <?php endforeach; ?>
        </div>
        
        <?php endif; ?>
        
        <?php endif; ?>
        
    </div>
</div>
