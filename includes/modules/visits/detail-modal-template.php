<?php
/**
 * Visits Detail Modal Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.0.0 - REFACTORED: Shows started_at, completed_at, physical/legal person
 */

if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěva nebyla nalezena</div>';
    return;
}

// Load schedules
global $wpdb;
$schedules = array();
if (!empty($item['id'])) {
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM %i WHERE visit_id = %d ORDER BY date ASC",
        $wpdb->prefix . 'saw_visit_schedules',
        $item['id']
    ), ARRAY_A);
}

// Load visitors count
$visitors_count = 0;
if (!empty($item['id'])) {
    $visitors_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM %i WHERE visit_id = %d",
        $wpdb->prefix . 'saw_visitors',
        $item['id']
    ));
}

// Determine if physical or legal person
$is_physical_person = empty($item['company_id']);
?>

<div class="saw-detail-header">
    <div class="saw-detail-header-info">
        <h2 class="saw-detail-title">
            #<?php echo esc_html($item['id']); ?> 
            <?php if ($is_physical_person): ?>
                <span style="color: #6366f1;">Fyzická osoba</span>
            <?php else: ?>
                <?php echo esc_html($item['company_name']); ?>
            <?php endif; ?>
        </h2>
        <div class="saw-detail-badges">
            <?php
            $status_labels = array(
                'draft' => 'Koncept',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzená',
                'in_progress' => 'Probíhající',
                'completed' => 'Dokončená',
                'cancelled' => 'Zrušená',
            );
            $status_classes = array(
                'draft' => 'saw-badge-secondary',
                'pending' => 'saw-badge-warning',
                'confirmed' => 'saw-badge-info',
                'in_progress' => 'saw-badge-primary',
                'completed' => 'saw-badge-success',
                'cancelled' => 'saw-badge-danger',
            );
            $type_labels = array(
                'planned' => 'Plánovaná',
                'walk_in' => 'Walk-in',
            );
            ?>
            <?php if (!empty($item['visit_type'])): ?>
            <span class="saw-badge saw-badge-info">
                <?php echo esc_html($type_labels[$item['visit_type']] ?? $item['visit_type']); ?>
            </span>
            <?php endif; ?>
            
            <?php if ($is_physical_person): ?>
            <span class="saw-badge" style="background: #6366f1; color: white;">
                👤 Fyzická osoba
            </span>
            <?php endif; ?>
            
            <?php if (!empty($item['status'])): ?>
            <span class="saw-badge <?php echo esc_attr($status_classes[$item['status']] ?? 'saw-badge-secondary'); ?>">
                <?php echo esc_html($status_labels[$item['status']] ?? $item['status']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="saw-detail-sections">
    
    <!-- ⭐ NEW: Tracking Timeline -->
    <?php if (!empty($item['started_at']) || !empty($item['completed_at'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-clock"></span>
            Průběh návštěvy
        </h3>
        
        <div class="saw-tracking-timeline">
            <?php if (!empty($item['started_at'])): ?>
            <div class="saw-tracking-event">
                <div class="saw-tracking-icon saw-tracking-icon-start">
                    <span class="dashicons dashicons-arrow-down-alt"></span>
                </div>
                <div class="saw-tracking-content">
                    <strong>Zahájeno</strong>
                    <span class="saw-tracking-time">
                        <?php echo date('d.m.Y H:i', strtotime($item['started_at'])); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['completed_at'])): ?>
            <div class="saw-tracking-event">
                <div class="saw-tracking-icon saw-tracking-icon-end">
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                </div>
                <div class="saw-tracking-content">
                    <strong>Dokončeno</strong>
                    <span class="saw-tracking-time">
                        <?php echo date('d.m.Y H:i', strtotime($item['completed_at'])); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['started_at']) && !empty($item['completed_at'])): ?>
            <div class="saw-tracking-duration">
                <?php
                $start = new DateTime($item['started_at']);
                $end = new DateTime($item['completed_at']);
                $diff = $start->diff($end);
                
                $duration_parts = array();
                if ($diff->d > 0) $duration_parts[] = $diff->d . ' dní';
                if ($diff->h > 0) $duration_parts[] = $diff->h . ' h';
                if ($diff->i > 0) $duration_parts[] = $diff->i . ' min';
                
                echo '⏱️ Celková doba: ' . implode(', ', $duration_parts);
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Scheduled Days -->
    <?php if (!empty($schedules)): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-calendar-alt"></span>
            Naplánované dny návštěvy
        </h3>
        
        <div class="saw-visit-schedule-detail">
            <?php 
            $day_names = array(
                'Mon' => 'Pondělí',
                'Tue' => 'Úterý',
                'Wed' => 'Středa',
                'Thu' => 'Čtvrtek',
                'Fri' => 'Pátek',
                'Sat' => 'Sobota',
                'Sun' => 'Neděle',
            );
            
            foreach ($schedules as $schedule): 
                $date = new DateTime($schedule['date']);
                $day_name = $day_names[date('D', strtotime($schedule['date']))] ?? '';
                $formatted_date = $date->format('d.m.Y');
            ?>
                <div class="saw-schedule-day-card">
                    <div class="saw-schedule-day-header">
                        <div class="saw-schedule-day-date">
                            <span class="saw-schedule-day-name"><?php echo esc_html($day_name); ?></span>
                            <span class="saw-schedule-day-number"><?php echo esc_html($formatted_date); ?></span>
                        </div>
                        <?php if (!empty($schedule['time_from']) || !empty($schedule['time_to'])): ?>
                        <div class="saw-schedule-day-time">
                            <span class="dashicons dashicons-clock"></span>
                            <span>
                                <?php 
                                echo !empty($schedule['time_from']) ? esc_html(substr($schedule['time_from'], 0, 5)) : '—';
                                echo ' - ';
                                echo !empty($schedule['time_to']) ? esc_html(substr($schedule['time_to'], 0, 5)) : '—';
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($schedule['notes'])): ?>
                    <div class="saw-schedule-day-notes">
                        <span class="dashicons dashicons-admin-comments"></span>
                        <?php echo esc_html($schedule['notes']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Visit Information -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-info"></span>
            Informace o návštěvě
        </h3>
        <dl class="saw-detail-list">
            <dt class="saw-detail-label">Pobočka</dt>
            <dd class="saw-detail-value"><?php echo esc_html($item['branch_name'] ?? '—'); ?></dd>
            
            <dt class="saw-detail-label">Návštěvník</dt>
            <dd class="saw-detail-value">
                <?php if ($is_physical_person): ?>
                    <span style="color: #6366f1; font-weight: 600;">👤 Fyzická osoba</span>
                <?php else: ?>
                    <strong><?php echo esc_html($item['company_name']); ?></strong>
                <?php endif; ?>
            </dd>
            
            <dt class="saw-detail-label">Počet návštěvníků</dt>
            <dd class="saw-detail-value">
                <span class="saw-badge saw-badge-info">
                    <?php echo $visitors_count; ?> 
                    <?php echo $visitors_count === 1 ? 'osoba' : ($visitors_count < 5 ? 'osoby' : 'osob'); ?>
                </span>
            </dd>
            
            <?php if (!empty($item['invitation_email'])): ?>
            <dt class="saw-detail-label">Email pro pozvánku</dt>
            <dd class="saw-detail-value">
                <a href="mailto:<?php echo esc_attr($item['invitation_email']); ?>">
                    <?php echo esc_html($item['invitation_email']); ?>
                </a>
            </dd>
            <?php endif; ?>
            
            <dt class="saw-detail-label">Účel návštěvy</dt>
            <dd class="saw-detail-value"><?php echo !empty($item['purpose']) ? nl2br(esc_html($item['purpose'])) : '—'; ?></dd>
            
            <?php if (!empty($item['hosts'])): ?>
            <dt class="saw-detail-label">Koho navštěvují</dt>
            <dd class="saw-detail-value">
                <div class="saw-hosts-list">
                    <?php foreach ($item['hosts'] as $host): ?>
                        <div class="saw-host-card">
                            <span class="dashicons dashicons-businessman"></span>
                            <div class="saw-host-info">
                                <strong><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></strong>
                                <?php if (!empty($host['position'])): ?>
                                    <span class="saw-host-email"><?php echo esc_html($host['position']); ?></span>
                                <?php elseif (!empty($host['email'])): ?>
                                    <span class="saw-host-email"><?php echo esc_html($host['email']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<style>
/* ⭐ NEW: Tracking Timeline */
.saw-tracking-timeline {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 8px;
}

.saw-tracking-event {
    display: flex;
    align-items: center;
    gap: 16px;
}

.saw-tracking-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.saw-tracking-icon-start {
    background: #10b981;
}

.saw-tracking-icon-end {
    background: #3b82f6;
}

.saw-tracking-icon .dashicons {
    color: white;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.saw-tracking-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.saw-tracking-content strong {
    font-size: 16px;
    color: #1e293b;
}

.saw-tracking-time {
    font-size: 14px;
    color: #64748b;
}

.saw-tracking-duration {
    padding: 12px;
    background: white;
    border: 2px dashed #cbd5e1;
    border-radius: 6px;
    text-align: center;
    font-weight: 600;
    color: #475569;
}

/* Schedule Day Cards */
.saw-visit-schedule-detail {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saw-schedule-day-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-left: 4px solid #2271b1;
    border-radius: 8px;
    padding: 16px;
    transition: all 0.2s ease;
}

.saw-schedule-day-card:hover {
    box-shadow: 0 2px 8px rgba(34, 113, 177, 0.15);
    border-left-color: #135e96;
}

.saw-schedule-day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.saw-schedule-day-date {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.saw-schedule-day-name {
    font-size: 13px;
    font-weight: 600;
    color: #2271b1;
    text-transform: uppercase;
}

.saw-schedule-day-number {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.saw-schedule-day-time {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    padding: 8px 12px;
    border-radius: 6px;
    font-weight: 600;
    color: #475569;
}

.saw-schedule-day-time .dashicons {
    color: #2271b1;
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.saw-schedule-day-notes {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
    background: #fef9e7;
    border-radius: 6px;
    font-size: 14px;
    color: #854d0e;
    margin-top: 8px;
}

.saw-schedule-day-notes .dashicons {
    color: #ca8a04;
    font-size: 18px;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 2px;
}

/* Hosts List */
.saw-hosts-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-host-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.saw-host-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.saw-host-card .dashicons {
    color: #2271b1;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.saw-host-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.saw-host-info strong {
    color: #1e293b;
    font-size: 15px;
}

.saw-host-email {
    color: #64748b;
    font-size: 13px;
}
</style>