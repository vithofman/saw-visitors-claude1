<?php
/**
 * Calendar Module Template
 *
 * Renders either desktop (FullCalendar) or mobile view based on device.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar
 * @version     2.0.0 - Added mobile view support
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine if mobile view
$is_mobile = wp_is_mobile();

// Allow override via query param (for testing)
if (isset($_GET['view'])) {
    $is_mobile = ($_GET['view'] === 'mobile');
}

// Get selected date for mobile view
$selected_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}
?>

<?php if ($is_mobile): ?>
<!-- ============================================
     MOBILE VIEW
     ============================================ -->
<div class="saw-calendar-mobile-view">
    <?php include __DIR__ . '/partials/mobile-header.php'; ?>
    
    <div class="saw-calendar-mobile-content">
        <?php 
        // Events will be loaded via AJAX for better performance
        $events = [];
        
        include __DIR__ . '/partials/mini-calendar.php'; 
        include __DIR__ . '/partials/agenda-list.php'; 
        ?>
    </div>
    
    <?php include __DIR__ . '/partials/bottom-sheet.php'; ?>
</div>

<?php else: ?>
<!-- ============================================
     DESKTOP VIEW
     ============================================ -->
<div class="saw-calendar-desktop-view">
    <div class="saw-calendar-page">
        <?php 
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
                    <?php if (class_exists('SAW_Icons')): ?>
                        <?php echo SAW_Icons::get('plus'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-plus-alt2"></span>
                    <?php endif; ?>
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
            <span class="saw-calendar-loading__text">Načítám kalendář...</span>
        </div>
        
        <!-- Legend -->
        <div class="saw-calendar-legend">
            <span class="saw-calendar-legend__title">Legenda:</span>
            <div class="saw-calendar-legend__items">
                <span class="saw-calendar-legend__item">
                    <span class="saw-calendar-legend__dot" style="background: #94a3b8;"></span>
                    Koncept
                </span>
                <span class="saw-calendar-legend__item">
                    <span class="saw-calendar-legend__dot" style="background: #f59e0b;"></span>
                    Čekající
                </span>
                <span class="saw-calendar-legend__item">
                    <span class="saw-calendar-legend__dot" style="background: #3b82f6;"></span>
                    Potvrzená
                </span>
                <span class="saw-calendar-legend__item">
                    <span class="saw-calendar-legend__dot" style="background: #f97316;"></span>
                    Probíhá
                </span>
                <span class="saw-calendar-legend__item">
                    <span class="saw-calendar-legend__dot" style="background: #6b7280;"></span>
                    Dokončená
                </span>
                <span class="saw-calendar-legend__item">
                    <span class="saw-calendar-legend__dot" style="background: #ef4444;"></span>
                    Zrušená
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
