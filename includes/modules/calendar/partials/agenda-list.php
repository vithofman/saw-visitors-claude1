<?php
/**
 * Agenda List Component
 * 
 * List of events for selected day.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar/Partials
 * @version     1.0.0
 * @since       3.1.0
 * 
 * @param string $selected_date Selected date (Y-m-d)
 * @param array $events Array of events (optional, loaded via AJAX if empty)
 */

if (!defined('ABSPATH')) {
    exit;
}

$selected_date = $selected_date ?? date('Y-m-d');
$events = $events ?? [];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Format selected date for display
$date_obj = new DateTime($selected_date);
$day_names = [
    'Monday' => 'Pondělí',
    'Tuesday' => 'Úterý',
    'Wednesday' => 'Středa',
    'Thursday' => 'Čtvrtek',
    'Friday' => 'Pátek',
    'Saturday' => 'Sobota',
    'Sunday' => 'Neděle',
];
$day_name = $day_names[$date_obj->format('l')] ?? '';
$formatted_date = $day_name . ' ' . $date_obj->format('j. n. Y');

// Check if today
$is_today = ($selected_date === date('Y-m-d'));

// Event count text
$event_count = count($events);
if ($event_count === 1) {
    $count_word = 'návštěva';
} elseif ($event_count >= 2 && $event_count <= 4) {
    $count_word = 'návštěvy';
} else {
    $count_word = 'návštěv';
}
?>

<div class="saw-agenda" id="saw-agenda" data-date="<?php echo esc_attr($selected_date); ?>">
    
    <!-- Selected Day Header -->
    <div class="saw-agenda__header">
        <div class="saw-agenda__date">
            <span class="saw-agenda__date-icon">📅</span>
            <span class="saw-agenda__date-text" id="saw-agenda-date-text">
                <?php if ($is_today): ?>
                    <strong>Dnes</strong>, <?php echo esc_html(mb_strtolower($formatted_date)); ?>
                <?php else: ?>
                    <?php echo esc_html($formatted_date); ?>
                <?php endif; ?>
            </span>
        </div>
        <span class="saw-agenda__count" id="saw-agenda-count">
            <?php echo esc_html($event_count . ' ' . $count_word); ?>
        </span>
    </div>
    
    <!-- Events List -->
    <div class="saw-agenda__list" id="saw-agenda-list">
        <?php if (!empty($events)): ?>
            <?php foreach ($events as $event): ?>
                <?php include __DIR__ . '/event-card.php'; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Empty State -->
            <div class="saw-agenda__empty" id="saw-agenda-empty">
                <div class="saw-agenda__empty-icon">📭</div>
                <p class="saw-agenda__empty-text">Žádné návštěvy pro tento den</p>
                <a href="<?php echo esc_url(home_url('/admin/visits/create?date=' . $selected_date)); ?>" 
                   class="saw-agenda__empty-btn">
                    + Naplánovat návštěvu
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Loading State (hidden by default) -->
    <div class="saw-agenda__loading" id="saw-agenda-loading" style="display: none;">
        <div class="saw-agenda__loading-spinner"></div>
        <span>Načítám návštěvy...</span>
    </div>
</div>
