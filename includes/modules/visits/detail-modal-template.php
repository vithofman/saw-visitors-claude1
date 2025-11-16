<?php
if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěva nebyla nalezena</div>';
    return;
}

// Načti schedules
global $wpdb;
$schedules = array();
if (!empty($item['id'])) {
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM %i WHERE visit_id = %d ORDER BY date ASC",
        $wpdb->prefix . 'saw_visit_schedules',
        $item['id']
    ), ARRAY_A);
}
?>

<div class="saw-detail-header">
    <div class="saw-detail-header-info">
        <h2 class="saw-detail-title">
            #<?php echo esc_html($item['id']); ?> 
            <?php if (!empty($item['company_name'])): ?>
                <?php echo esc_html($item['company_name']); ?>
            <?php else: ?>
                Návštěva
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
            <?php if (!empty($item['status'])): ?>
            <span class="saw-badge <?php echo esc_attr($status_classes[$item['status']] ?? 'saw-badge-secondary'); ?>">
                <?php echo esc_html($status_labels[$item['status']] ?? $item['status']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="saw-detail-sections">
    
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
    
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-info"></span>
            Informace o návštěvě
        </h3>
        <dl class="saw-detail-list">
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