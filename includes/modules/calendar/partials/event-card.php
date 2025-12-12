<?php
/**
 * Event Card Component
 * 
 * Single event card for agenda list.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar/Partials
 * @version     1.0.0
 * @since       3.1.0
 * 
 * @param array $event Event data from database
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure $event is set
if (!isset($event) || empty($event)) {
    return;
}

// Status colors
$status_colors = [
    'draft' => '#94a3b8',
    'pending' => '#f59e0b',
    'confirmed' => '#3b82f6',
    'in_progress' => '#f97316',
    'completed' => '#6b7280',
    'cancelled' => '#ef4444',
];

$status_labels = [
    'draft' => 'Koncept',
    'pending' => 'Čekající',
    'confirmed' => 'Potvrzeno',
    'in_progress' => 'Probíhá',
    'completed' => 'Dokončeno',
    'cancelled' => 'Zrušeno',
];

$status = $event['status'] ?? 'pending';
$status_color = $status_colors[$status] ?? '#94a3b8';
$status_label = $status_labels[$status] ?? $status;

// Format times
$time_from = !empty($event['time_from']) ? substr($event['time_from'], 0, 5) : '—';
$time_to = !empty($event['time_to']) ? substr($event['time_to'], 0, 5) : '—';

// Person count
$person_count = intval($event['person_count'] ?? $event['visitor_count'] ?? 1);

// Czech plural for "osoba"
if ($person_count === 1) {
    $person_word = 'osoba';
} elseif ($person_count >= 2 && $person_count <= 4) {
    $person_word = 'osoby';
} else {
    $person_word = 'osob';
}
?>

<article class="saw-event-card" 
         data-event-id="<?php echo esc_attr($event['id']); ?>"
         data-status="<?php echo esc_attr($status); ?>">
    
    <div class="saw-event-card__indicator" style="background-color: <?php echo esc_attr($status_color); ?>"></div>
    
    <div class="saw-event-card__content">
        <div class="saw-event-card__time">
            <?php echo esc_html($time_from); ?> - <?php echo esc_html($time_to); ?>
        </div>
        
        <h3 class="saw-event-card__title">
            <?php echo esc_html($event['company_name'] ?? 'Návštěva #' . $event['id']); ?>
        </h3>
        
        <div class="saw-event-card__meta">
            <span class="saw-event-card__meta-item">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                </svg>
                <?php echo esc_html($person_count . ' ' . $person_word); ?>
            </span>
            
            <?php if (!empty($event['branch_name'])): ?>
            <span class="saw-event-card__meta-item">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                </svg>
                <?php echo esc_html($event['branch_name']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="saw-event-card__arrow">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
        </svg>
    </div>
</article>
