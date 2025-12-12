<?php
/**
 * Mini Calendar Component
 * 
 * Compact month view for mobile calendar.
 * Shows day numbers with dot indicators for days with events.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar/Partials
 * @version     1.0.0
 * @since       3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current month/year (from URL or default to today)
$current_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$current_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selected_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

// Validate selected_date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Calculate month data
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = intval(date('t', $first_day_of_month));
$first_weekday = intval(date('N', $first_day_of_month)); // 1=Monday, 7=Sunday

// Czech month names
$month_names = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
    5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
    9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec'
];
$month_name = $month_names[$current_month] . ' ' . $current_year;

// Previous/Next month calculation
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Today's date for comparison
$today = date('Y-m-d');
?>

<div class="saw-mini-calendar" 
     id="saw-mini-calendar"
     data-year="<?php echo esc_attr($current_year); ?>"
     data-month="<?php echo esc_attr($current_month); ?>"
     data-selected="<?php echo esc_attr($selected_date); ?>">
    
    <!-- Month Navigation -->
    <div class="saw-mini-calendar__header">
        <button type="button" 
                class="saw-mini-calendar__nav saw-mini-calendar__nav--prev"
                data-year="<?php echo esc_attr($prev_year); ?>"
                data-month="<?php echo esc_attr($prev_month); ?>"
                aria-label="Předchozí měsíc">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
        </button>
        
        <span class="saw-mini-calendar__month-name"><?php echo esc_html($month_name); ?></span>
        
        <button type="button"
                class="saw-mini-calendar__nav saw-mini-calendar__nav--next"
                data-year="<?php echo esc_attr($next_year); ?>"
                data-month="<?php echo esc_attr($next_month); ?>"
                aria-label="Další měsíc">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
    
    <!-- Weekday Headers -->
    <div class="saw-mini-calendar__weekdays">
        <span class="saw-mini-calendar__weekday">Po</span>
        <span class="saw-mini-calendar__weekday">Út</span>
        <span class="saw-mini-calendar__weekday">St</span>
        <span class="saw-mini-calendar__weekday">Čt</span>
        <span class="saw-mini-calendar__weekday">Pá</span>
        <span class="saw-mini-calendar__weekday saw-mini-calendar__weekday--weekend">So</span>
        <span class="saw-mini-calendar__weekday saw-mini-calendar__weekday--weekend">Ne</span>
    </div>
    
    <!-- Days Grid -->
    <div class="saw-mini-calendar__days" id="saw-mini-calendar-days">
        <?php
        // Empty cells before first day
        for ($i = 1; $i < $first_weekday; $i++):
        ?>
            <div class="saw-mini-calendar__day saw-mini-calendar__day--empty"></div>
        <?php endfor; ?>
        
        <?php
        // Days of month
        for ($day = 1; $day <= $days_in_month; $day++):
            $date_str = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
            $is_today = ($date_str === $today);
            $is_selected = ($date_str === $selected_date);
            $weekday = intval(date('N', strtotime($date_str)));
            $is_weekend = ($weekday >= 6);
            
            $day_classes = ['saw-mini-calendar__day'];
            if ($is_today) $day_classes[] = 'saw-mini-calendar__day--today';
            if ($is_selected) $day_classes[] = 'saw-mini-calendar__day--selected';
            if ($is_weekend) $day_classes[] = 'saw-mini-calendar__day--weekend';
        ?>
            <button type="button"
                    class="<?php echo esc_attr(implode(' ', $day_classes)); ?>"
                    data-date="<?php echo esc_attr($date_str); ?>">
                <span class="saw-mini-calendar__day-number"><?php echo $day; ?></span>
                <span class="saw-mini-calendar__day-dot"></span>
            </button>
        <?php endfor; ?>
        
        <?php
        // Empty cells after last day (fill to complete grid)
        $last_weekday = intval(date('N', mktime(0, 0, 0, $current_month, $days_in_month, $current_year)));
        for ($i = $last_weekday; $i < 7; $i++):
        ?>
            <div class="saw-mini-calendar__day saw-mini-calendar__day--empty"></div>
        <?php endfor; ?>
    </div>
    
    <!-- Today Button -->
    <div class="saw-mini-calendar__footer">
        <button type="button" class="saw-mini-calendar__today-btn" data-date="<?php echo esc_attr($today); ?>">
            Dnes
        </button>
    </div>
</div>
