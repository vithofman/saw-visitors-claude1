<?php
/**
 * Calendar Module Template
 *
 * Renders the calendar page with filters and FullCalendar container.
 * Branch is taken from SAW_Context (branch switcher), not from filter.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar
 * @version     1.2.0 - FIXED: Removed branch filter, Czech translations
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current branch name for info display
$current_branch_name = '';
$branch_id = SAW_Context::get_branch_id();
if ($branch_id) {
    global $wpdb;
    $current_branch_name = $wpdb->get_var($wpdb->prepare(
        "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
        $branch_id
    ));
}
?>

<div class="saw-calendar-page">
    
    <!-- Header with title, filters and button in one row -->
    <div class="saw-calendar-header">
        <div class="saw-calendar-header__left">
            <h1 class="saw-calendar-title">
                <span class="saw-calendar-title__icon">📅</span>
                Kalendář návštěv
            </h1>
        </div>
        <div class="saw-calendar-header__right">
            <!-- Status filter -->
            <div class="saw-calendar-filter saw-calendar-filter--inline">
                <label class="saw-calendar-filter__label">Stav</label>
                <select id="saw-filter-status" class="saw-calendar-filter__select">
                    <option value="">Všechny stavy</option>
                    <option value="draft">Koncept</option>
                    <option value="pending">Čekající</option>
                    <option value="confirmed">Potvrzená</option>
                    <option value="in_progress">Probíhá</option>
                    <option value="completed">Dokončená</option>
                    <option value="cancelled">Zrušená</option>
                </select>
            </div>
            
            <!-- Type filter -->
            <div class="saw-calendar-filter saw-calendar-filter--inline">
                <label class="saw-calendar-filter__label">Typ návštěvy</label>
                <select id="saw-filter-type" class="saw-calendar-filter__select">
                    <option value="">Všechny typy</option>
                    <option value="planned">Plánovaná</option>
                    <option value="walk_in">Neplánovaná</option>
                </select>
            </div>
            
            <!-- Create button -->
            <a href="<?php echo esc_url(home_url('/admin/visits/create')); ?>" class="saw-btn saw-btn-primary saw-calendar-create-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <span class="saw-calendar-create-btn__text">Nová návštěva</span>
            </a>
        </div>
    </div>
    
    <!-- Calendar Container -->
    <div class="saw-calendar-container">
        <div id="saw-calendar" class="saw-calendar"></div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="saw-calendar-loading" class="saw-calendar-loading" style="display: none;">
        <div class="saw-calendar-loading__spinner"></div>
        <span class="saw-calendar-loading__text">Načítání...</span>
    </div>
    
    <!-- Legend -->
    <div class="saw-calendar-legend">
        <span class="saw-calendar-legend__title">Legenda:</span>
        <div class="saw-calendar-legend__items">
            <div class="saw-calendar-legend__item">
                <span class="saw-calendar-legend__dot" style="background: #f59e0b;"></span>
                <span>Čekající</span>
            </div>
            <div class="saw-calendar-legend__item">
                <span class="saw-calendar-legend__dot" style="background: #3b82f6;"></span>
                <span>Potvrzená</span>
            </div>
            <div class="saw-calendar-legend__item">
                <span class="saw-calendar-legend__dot" style="background: #f97316;"></span>
                <span>Probíhá</span>
            </div>
            <div class="saw-calendar-legend__item">
                <span class="saw-calendar-legend__dot" style="background: #6b7280;"></span>
                <span>Dokončená</span>
            </div>
            <div class="saw-calendar-legend__item">
                <span class="saw-calendar-legend__dot" style="background: #ef4444;"></span>
                <span>Zrušená</span>
            </div>
        </div>
    </div>
    
</div>
