<?php
/**
 * Visits Detail Sidebar Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     4.5.1
 */

if (!defined('ABSPATH')) exit;

// Load translations
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visits') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// Day names translations
$day_names = array(
    'Mon' => $tr('day_mon', 'Pondělí'),
    'Tue' => $tr('day_tue', 'Úterý'),
    'Wed' => $tr('day_wed', 'Středa'),
    'Thu' => $tr('day_thu', 'Čtvrtek'),
    'Fri' => $tr('day_fri', 'Pátek'),
    'Sat' => $tr('day_sat', 'Sobota'),
    'Sun' => $tr('day_sun', 'Neděle'),
);

// Person count helper (Czech grammar)
$person_count_label = function($count) use ($tr) {
    if ($count === 1) {
        return $tr('person_singular', 'osoba');
    } elseif ($count >= 2 && $count <= 4) {
        return $tr('person_few', 'osoby');
    } else {
        return $tr('person_many', 'osob');
    }
};

// Permission check for risks editing
$can_edit_risks = false;
if (current_user_can('manage_options')) {
    $can_edit_risks = true;
} elseif (function_exists('saw_get_current_role')) {
    $user_role = saw_get_current_role();
    $can_edit_risks = in_array($user_role, ['super_admin', 'admin', 'super_manager', 'manager']);
} elseif (current_user_can('edit_posts')) {
    $can_edit_risks = true;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html($tr('error_not_found', 'Návštěva nebyla nalezena')) . '</div>';
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

// Get visitor count
$visitors_count = intval($item['visitor_count'] ?? 0);

// Determine visitor type
$is_physical_person = empty($item['company_id']);

// Current status
$current_status = $item['status'] ?? 'draft';

// Status configuration
$all_statuses = array(
    'draft' => array(
        'label' => $tr('status_draft', 'Koncept'),
        'icon' => '📝',
    ),
    'pending' => array(
        'label' => $tr('status_pending', 'Čekající'),
        'icon' => '⏳',
    ),
    'confirmed' => array(
        'label' => $tr('status_confirmed', 'Potvrzená'),
        'icon' => '✅',
    ),
    'in_progress' => array(
        'label' => $tr('status_in_progress', 'Probíhající'),
        'icon' => '🔄',
    ),
    'completed' => array(
        'label' => $tr('status_completed', 'Dokončená'),
        'icon' => '🏁',
    ),
    'cancelled' => array(
        'label' => $tr('status_cancelled', 'Zrušená'),
        'icon' => '❌',
    ),
);

// Load invitation materials for risks section
$materials = [];
if (!empty($item['id'])) {
    $materials = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials 
         WHERE visit_id = %d 
         ORDER BY material_type, uploaded_at ASC",
        $item['id']
    ), ARRAY_A);
}

$texts = array_filter($materials, fn($m) => $m['material_type'] === 'text');
$documents = array_filter($materials, fn($m) => $m['material_type'] === 'document');
?>

<!-- ============================================ -->
<!-- STATUS QUICK CHANGE BUTTONS                  -->
<!-- ============================================ -->
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">🔄 <?php echo esc_html($tr('change_status', 'Změnit stav')); ?></h4>
    </div>
    <div class="saw-section-body">
        <div class="saw-status-buttons-grid">
            <?php foreach ($all_statuses as $status_key => $status_config): 
                $is_current = ($status_key === $current_status);
            ?>
            <button 
                type="button" 
                class="saw-status-btn <?php echo $is_current ? 'saw-status-btn-current' : ''; ?>"
                data-status="<?php echo esc_attr($status_key); ?>"
                data-visit-id="<?php echo esc_attr($item['id']); ?>"
                data-status-label="<?php echo esc_attr($status_config['label']); ?>"
                <?php echo $is_current ? 'disabled' : ''; ?>
            >
                <span class="saw-status-btn-icon"><?php echo $status_config['icon']; ?></span>
                <span class="saw-status-btn-label"><?php echo esc_html($status_config['label']); ?></span>
                <?php if ($is_current): ?>
                <span class="saw-status-btn-check">✓</span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- VISIT PROGRESS TIMELINE                      -->
<!-- ============================================ -->
<?php if (!empty($item['started_at']) || !empty($item['completed_at'])): ?>
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">⏱️ <?php echo esc_html($tr('section_timeline', 'Průběh návštěvy')); ?></h4>
    </div>
    <div class="saw-section-body">
        <div class="saw-visit-timeline">
            <?php if (!empty($item['started_at'])): ?>
            <div class="saw-timeline-row">
                <div class="saw-timeline-dot saw-timeline-dot-start">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </div>
                <div class="saw-timeline-info">
                    <span class="saw-timeline-label"><?php echo esc_html($tr('field_started', 'Zahájeno')); ?></span>
                    <span class="saw-timeline-value"><?php echo date_i18n('d.m.Y', strtotime($item['started_at'])); ?></span>
                    <span class="saw-timeline-time"><?php echo date_i18n('H:i', strtotime($item['started_at'])); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['started_at']) && !empty($item['completed_at'])): ?>
            <?php
            $start = new DateTime($item['started_at']);
            $end = new DateTime($item['completed_at']);
            $diff = $start->diff($end);
            
            $duration_parts = [];
            if ($diff->d > 0) $duration_parts[] = $diff->d . 'd';
            if ($diff->h > 0) $duration_parts[] = $diff->h . 'h';
            if ($diff->i > 0 && $diff->d == 0) $duration_parts[] = $diff->i . 'm';
            $duration_str = implode(' ', $duration_parts) ?: '< 1m';
            ?>
            <div class="saw-timeline-connector">
                <div class="saw-timeline-line"></div>
                <span class="saw-timeline-duration"><?php echo esc_html($duration_str); ?></span>
            </div>
            <?php elseif (!empty($item['started_at']) && empty($item['completed_at'])): ?>
            <div class="saw-timeline-connector saw-timeline-active">
                <div class="saw-timeline-line"></div>
                <span class="saw-timeline-duration saw-timeline-now"><?php echo esc_html($tr('now', 'Probíhá')); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['completed_at'])): ?>
            <div class="saw-timeline-row">
                <div class="saw-timeline-dot saw-timeline-dot-end">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="18 15 12 9 6 15"/>
                    </svg>
                </div>
                <div class="saw-timeline-info">
                    <span class="saw-timeline-label"><?php echo esc_html($tr('field_completed', 'Dokončeno')); ?></span>
                    <span class="saw-timeline-value"><?php echo date_i18n('d.m.Y', strtotime($item['completed_at'])); ?></span>
                    <span class="saw-timeline-time"><?php echo date_i18n('H:i', strtotime($item['completed_at'])); ?></span>
                </div>
            </div>
            <?php elseif (!empty($item['started_at'])): ?>
            <div class="saw-timeline-row">
                <div class="saw-timeline-dot saw-timeline-dot-active">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="saw-timeline-info">
                    <span class="saw-timeline-label"><?php echo esc_html($tr('in_progress', 'Probíhá')); ?></span>
                    <span class="saw-timeline-value saw-timeline-value-active"><?php echo esc_html($tr('now', 'Nyní')); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SCHEDULED DAYS                               -->
<!-- ============================================ -->
<?php if (!empty($schedules)): ?>
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">📅 <?php echo esc_html($tr('section_schedule', 'Naplánované dny návštěvy')); ?></h4>
    </div>
    <div class="saw-section-body">
        <div class="saw-visit-schedule-detail">
            <?php 
            foreach ($schedules as $schedule): 
                $date = new DateTime($schedule['date']);
                $day_key = date('D', strtotime($schedule['date']));
                $day_name = $day_names[$day_key] ?? '';
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
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- VISIT INFORMATION                            -->
<!-- ============================================ -->
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">ℹ️ <?php echo esc_html($tr('section_info', 'Informace o návštěvě')); ?></h4>
    </div>
    <div class="saw-section-body">
        <div class="saw-info-grid">
            <?php if (!empty($item['branch_name'])): ?>
            <div class="saw-info-item">
                <label><?php echo esc_html($tr('field_branch', 'Pobočka')); ?></label>
                <span><?php echo esc_html($item['branch_name']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php
            // PIN Section
            include(dirname(__FILE__) . '/parts/pin-section.php');
            ?>
            
<?php 
$can_send_invitation = in_array($item['status'] ?? '', ['pending', 'draft']);
$invitation_sent = !empty($item['invitation_sent_at']);
$invitation_confirmed = !empty($item['invitation_confirmed_at']);
$has_invitation_email = !empty($item['invitation_email']);
?>

<div class="saw-detail-cards-stack">
    <!-- VISITOR CARD -->
    <div class="saw-visitor-card">
        <div class="saw-visitor-card-inner">
            <div class="saw-visitor-card-left">
                <div class="saw-visitor-icon <?php echo $is_physical_person ? 'saw-visitor-icon-person' : 'saw-visitor-icon-company'; ?>">
                    <?php if ($is_physical_person): ?>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <?php else: ?>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/>
                        <path d="M5 21V7l8-4v18"/>
                        <path d="M19 21V11l-6-4"/>
                        <path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>
                    </svg>
                    <?php endif; ?>
                </div>
                <div class="saw-visitor-card-info">
                    <div class="saw-visitor-card-label">
                        <span><?php echo esc_html($tr('section_visitor', 'Návštěvník')); ?></span>
                        <span class="saw-visitor-type-badge <?php echo $is_physical_person ? 'saw-type-person' : 'saw-type-company'; ?>">
                            <?php echo $is_physical_person ? esc_html($tr('visitor_physical', 'Fyzická osoba')) : esc_html($tr('visitor_company', 'Firma')); ?>
                        </span>
                    </div>
                    <div class="saw-visitor-card-name">
                        <?php if ($is_physical_person): ?>
                            <?php echo esc_html($item['first_visitor_name'] ?? $tr('not_specified', 'Neuvedeno')); ?>
                        <?php else: ?>
                            <?php echo esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="saw-visitor-card-right">
                <div class="saw-visitor-count-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="saw-visitor-count-number"><?php echo $visitors_count; ?></span>
                    <span class="saw-visitor-count-label"><?php echo $person_count_label($visitors_count); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- INVITATION CARD -->
    <?php if ($has_invitation_email): ?>
    <div class="saw-invitation-card">
        <div class="saw-invitation-card-inner">
            <div class="saw-invitation-card-left">
                <div class="saw-invitation-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="M22 7l-10 7L2 7"/>
                    </svg>
                </div>
                <div class="saw-invitation-card-info">
                    <div class="saw-invitation-card-label">
                        <span><?php echo esc_html($tr('email_invitation', 'Emailová pozvánka')); ?></span>
                        <?php if ($invitation_confirmed): ?>
                            <span class="saw-invitation-status saw-invitation-status-confirmed">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                <?php echo esc_html($tr('invitation_confirmed', 'Potvrzeno')); ?>
                            </span>
                        <?php elseif ($invitation_sent): ?>
                            <span class="saw-invitation-status saw-invitation-status-sent">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                                <?php echo esc_html($tr('invitation_sent', 'Odesláno')); ?>
                            </span>
                        <?php else: ?>
                            <span class="saw-invitation-status saw-invitation-status-pending">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                <?php echo esc_html($tr('invitation_pending', 'Čeká')); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="mailto:<?php echo esc_attr($item['invitation_email']); ?>" class="saw-invitation-card-email">
                        <?php echo esc_html($item['invitation_email']); ?>
                    </a>
                    <?php if ($invitation_sent): ?>
                    <div class="saw-invitation-sent-info">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <?php echo esc_html($tr('invitation_sent_at', 'Odesláno')); ?>: <?php echo date_i18n('d.m.Y H:i', strtotime($item['invitation_sent_at'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="saw-invitation-card-right">
                <?php if ($can_send_invitation): ?>
                    <button 
                        type="button" 
                        class="saw-invitation-btn <?php echo $invitation_sent ? 'saw-invitation-btn-secondary' : 'saw-invitation-btn-primary'; ?>"
                        id="send-invitation-btn-<?php echo $item['id']; ?>"
                        onclick="sendInvitation(<?php echo $item['id']; ?>)"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                        <?php echo $invitation_sent ? esc_html($tr('btn_send_again', 'Odeslat znovu')) : esc_html($tr('btn_send_invitation', 'Odeslat pozvánku')); ?>
                    </button>
                <?php else: ?>
                    <div class="saw-invitation-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                        </svg>
                        <?php echo esc_html($tr('invitation_only_pending', 'Pouze u čekajících')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PURPOSE CARD -->
    <?php if (!empty($item['purpose'])): ?>
    <div class="saw-purpose-card">
        <div class="saw-purpose-card-inner">
            <div class="saw-purpose-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <line x1="10" y1="9" x2="8" y2="9"/>
                </svg>
            </div>
            <div class="saw-purpose-content">
                <div class="saw-purpose-label"><?php echo esc_html($tr('purpose_label', 'Účel návštěvy')); ?></div>
                <div class="saw-purpose-text"><?php echo nl2br(esc_html($item['purpose'])); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- NOTES CARD -->
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-notes-card">
        <div class="saw-notes-card-inner">
            <div class="saw-notes-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <div class="saw-notes-content">
                <div class="saw-notes-label"><?php echo esc_html($tr('notes_label', 'Poznámky')); ?></div>
                <div class="saw-notes-text"><?php echo nl2br(esc_html($item['notes'])); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- HOSTS CARD -->
    <?php if (!empty($item['hosts'])): ?>
    <div class="saw-hosts-card">
        <div class="saw-hosts-card-inner">
            <div class="saw-hosts-card-header">
                <div class="saw-hosts-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="saw-hosts-card-title">
                    <span class="saw-hosts-label"><?php echo esc_html($tr('hosts_label', 'Koho navštěvují')); ?></span>
                    <span class="saw-hosts-count"><?php echo count($item['hosts']); ?> <?php echo $person_count_label(count($item['hosts'])); ?></span>
                </div>
            </div>
            <div class="saw-hosts-card-body">
                <?php foreach ($item['hosts'] as $host): ?>
                <div class="saw-host-item">
                    <div class="saw-host-avatar">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="saw-host-details">
                        <span class="saw-host-name"><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></span>
                        <?php if (!empty($host['position'])): ?>
                            <span class="saw-host-role"><?php echo esc_html($host['position']); ?></span>
                        <?php elseif (!empty($host['role'])): ?>
                            <span class="saw-host-role"><?php echo esc_html($host['role']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- RISKS CARD (RED BORDER) -->
    <?php if (!empty($materials) || !empty($item['invitation_token']) || $can_edit_risks): ?>
    <div class="saw-risks-card">
        <div class="saw-risks-card-inner">
            <div class="saw-risks-card-header">
                <div class="saw-risks-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="saw-risks-card-title">
                    <span class="saw-risks-label"><?php echo esc_html($tr('section_risks', 'Informace o rizicích')); ?></span>
                    <?php if (!empty($materials)): ?>
                    <span class="saw-risks-status saw-risks-status-uploaded">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo esc_html($tr('risks_uploaded', 'Nahráno')); ?>
                    </span>
                    <?php else: ?>
                    <span class="saw-risks-status saw-risks-status-waiting">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <?php echo esc_html($tr('risks_waiting', 'Čeká na nahrání')); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($can_edit_risks && !empty($item['id'])): ?>
                <a href="<?php echo esc_url(home_url('/admin/visits/' . $item['id'] . '/edit-risks/')); ?>" class="saw-risks-edit-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    <?php echo esc_html($tr('btn_edit', 'Upravit')); ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="saw-risks-card-body">
                <?php if (empty($materials)): ?>
                    <div class="saw-risks-empty">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="12" y1="18" x2="12" y2="12"/>
                            <line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        <span><?php echo esc_html($tr('risks_empty', 'Zatím nebyly nahrány žádné materiály o rizicích')); ?></span>
                        <?php if ($can_edit_risks && !empty($item['id'])): ?>
                        <a href="<?php echo esc_url(home_url('/admin/visits/' . $item['id'] . '/edit-risks/')); ?>" class="saw-risks-add-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            <?php echo esc_html($tr('btn_add_risks', 'Zadat informace o rizicích')); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    
                    <?php if (!empty($texts)): ?>
                        <?php foreach ($texts as $text): ?>
                        <div class="saw-risks-text-item">
                            <div class="saw-risks-text-header">
                                <div class="saw-risks-text-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                </div>
                                <div class="saw-risks-text-meta">
                                    <span class="saw-risks-text-title"><?php echo esc_html($tr('risks_text_info', 'Textové informace')); ?></span>
                                    <span class="saw-risks-text-date"><?php echo esc_html(date('d.m.Y H:i', strtotime($text['uploaded_at']))); ?></span>
                                </div>
                                <button class="saw-risks-fullscreen-btn" onclick="openRisksFullscreen(<?php echo $text['id']; ?>)" title="<?php echo esc_attr($tr('risks_fullscreen', 'Zobrazit na celou obrazovku')); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="15 3 21 3 21 9"/>
                                        <polyline points="9 21 3 21 3 15"/>
                                        <line x1="21" y1="3" x2="14" y2="10"/>
                                        <line x1="3" y1="21" x2="10" y2="14"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="saw-risks-text-content">
                                <?php echo wp_kses_post($text['text_content']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($documents)): ?>
                    <div class="saw-risks-documents-section">
                        <div class="saw-risks-documents-header">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                            <span><?php echo esc_html($tr('risks_documents', 'Dokumenty')); ?> (<?php echo count($documents); ?>)</span>
                        </div>
                        <div class="saw-risks-documents-list">
                            <?php 
                            $upload_dir = wp_upload_dir();
                            foreach ($documents as $doc): 
                                $file_url = $upload_dir['baseurl'] . $doc['file_path'];
                                $file_ext = strtoupper(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                            ?>
                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="saw-risks-document-item">
                                <div class="saw-risks-document-icon">
                                    <?php if (in_array($file_ext, ['PDF'])): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <path d="M9 15h6"/>
                                    </svg>
                                    <?php elseif (in_array($file_ext, ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'])): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="saw-risks-document-info">
                                    <span class="saw-risks-document-name"><?php echo esc_html($doc['file_name']); ?></span>
                                    <span class="saw-risks-document-meta">
                                        <span class="saw-risks-document-ext"><?php echo $file_ext; ?></span>
                                        <span><?php echo size_format($doc['file_size']); ?></span>
                                    </span>
                                </div>
                                <div class="saw-risks-document-download">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
        
    </div>
</div>

<!-- ============================================ -->
<!-- STYLES                                       -->
<!-- ============================================ -->
<style>
/* ============================================
   STATUS BUTTONS
   ============================================ */
.saw-status-buttons-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.saw-status-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 10px 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    min-height: 56px;
    font-family: inherit;
}

.saw-status-btn:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.saw-status-btn:disabled {
    cursor: default;
}

.saw-status-btn-current {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 2px solid #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.saw-status-btn-icon {
    font-size: 18px;
    line-height: 1;
}

.saw-status-btn-label {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    text-align: center;
    line-height: 1.2;
}

.saw-status-btn-current .saw-status-btn-label {
    color: #047857;
}

.saw-status-btn-check {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 16px;
    height: 16px;
    background: #10b981;
    color: white;
    border-radius: 50%;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

@media (max-width: 400px) {
    .saw-status-buttons-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ============================================
   TIMELINE
   ============================================ */
.saw-visit-timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.saw-timeline-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
}

.saw-timeline-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
}

.saw-timeline-dot svg {
    flex-shrink: 0;
}

.saw-timeline-dot-start {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.saw-timeline-dot-end {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.saw-timeline-dot-active {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(139, 92, 246, 0); }
}

.saw-timeline-info {
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex: 1;
}

.saw-timeline-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    min-width: 70px;
}

.saw-timeline-value {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-timeline-value-active {
    color: #7c3aed;
}

.saw-timeline-time {
    font-size: 13px;
    color: #64748b;
}

.saw-timeline-connector {
    display: flex;
    align-items: center;
    padding: 4px 0 4px 15px;
    gap: 8px;
}

.saw-timeline-line {
    width: 2px;
    height: 20px;
    background: #e2e8f0;
    border-radius: 1px;
}

.saw-timeline-active .saw-timeline-line {
    background: linear-gradient(to bottom, #10b981, #8b5cf6);
}

.saw-timeline-duration {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 10px;
}

.saw-timeline-now {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
}

/* ============================================
   SCHEDULE DAY CARDS
   ============================================ */
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
    font-family: dashicons !important;
    color: #2271b1 !important;
    font-size: 18px !important;
    width: 18px !important;
    height: 18px !important;
    line-height: 18px !important;
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
    font-family: dashicons !important;
    color: #ca8a04 !important;
    font-size: 18px !important;
    width: 18px !important;
    height: 18px !important;
    line-height: 18px !important;
    flex-shrink: 0;
    margin-top: 2px;
}

/* ============================================
   DETAIL CARDS STACK
   ============================================ */
.saw-detail-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 16px 0;
    width: 100%;
    max-width: 100%;
}

.saw-info-grid .saw-detail-cards-stack {
    grid-column: 1 / -1;
    width: 100%;
}

.saw-visitor-card,
.saw-invitation-card,
.saw-purpose-card,
.saw-notes-card,
.saw-hosts-card,
.saw-risks-card {
    width: 100%;
    box-sizing: border-box;
}

/* Visitor Card */
.saw-visitor-card {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-visitor-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.saw-visitor-card-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
}

.saw-visitor-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-visitor-icon-person {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.saw-visitor-icon-company {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}

.saw-visitor-card-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.saw-visitor-card-label {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.saw-visitor-card-label > span:first-child {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-visitor-type-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
}

.saw-type-person {
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    color: #7c3aed;
}

.saw-type-company {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #0284c7;
}

.saw-visitor-card-name {
    font-size: 17px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
}

.saw-visitor-card-right {
    flex-shrink: 0;
}

.saw-visitor-count-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 2px solid #bae6fd;
    border-radius: 12px;
    padding: 12px 16px;
    min-width: 80px;
    color: #0284c7;
}

.saw-visitor-count-box svg {
    margin-bottom: 4px;
    opacity: 0.8;
}

.saw-visitor-count-number {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
}

.saw-visitor-count-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

/* Invitation Card */
.saw-invitation-card {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-invitation-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.saw-invitation-card-left {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    flex: 1;
    min-width: 0;
}

.saw-invitation-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #059669;
    flex-shrink: 0;
}

.saw-invitation-card-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.saw-invitation-card-label {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.saw-invitation-card-label > span:first-child {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-invitation-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
}

.saw-invitation-status-confirmed {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

.saw-invitation-status-sent {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #0284c7;
}

.saw-invitation-status-pending {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #d97706;
}

.saw-invitation-card-email {
    font-size: 15px;
    font-weight: 600;
    color: #059669;
    text-decoration: none;
    word-break: break-all;
}

.saw-invitation-card-email:hover {
    text-decoration: underline;
}

.saw-invitation-sent-info {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}

.saw-invitation-card-right {
    flex-shrink: 0;
}

.saw-invitation-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    font-family: inherit;
}

.saw-invitation-btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.saw-invitation-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.saw-invitation-btn-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.saw-invitation-btn-secondary:hover {
    background: #e2e8f0;
}

.saw-invitation-note {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #94a3b8;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
}

/* Purpose Card */
.saw-purpose-card {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-purpose-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
}

.saw-purpose-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #7c3aed;
    flex-shrink: 0;
}

.saw-purpose-content {
    flex: 1;
    min-width: 0;
}

.saw-purpose-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.saw-purpose-text {
    font-size: 14px;
    color: #334155;
    line-height: 1.5;
}

/* Notes Card */
.saw-notes-card {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-notes-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
}

.saw-notes-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d97706;
    flex-shrink: 0;
}

.saw-notes-content {
    flex: 1;
    min-width: 0;
}

.saw-notes-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.saw-notes-text {
    font-size: 14px;
    color: #334155;
    line-height: 1.5;
}

/* Hosts Card */
.saw-hosts-card {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-hosts-card-inner {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
}

.saw-hosts-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-hosts-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4f46e5;
    flex-shrink: 0;
}

.saw-hosts-card-title {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.saw-hosts-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-hosts-count {
    font-size: 14px;
    font-weight: 700;
    color: #4f46e5;
}

.saw-hosts-card-body {
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 200px;
    overflow-y: auto;
}

.saw-host-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #f8fafc;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.saw-host-item:hover {
    background: #f1f5f9;
}

.saw-host-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4f46e5;
    flex-shrink: 0;
}

.saw-host-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.saw-host-name {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-host-role {
    font-size: 12px;
    color: #64748b;
}

/* ============================================
   RISKS CARD (RED BORDER)
   ============================================ */
.saw-risks-card {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-risks-card-inner {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
}

.saw-risks-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-risks-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    flex-shrink: 0;
}

.saw-risks-card-title {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.saw-risks-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-risks-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    width: fit-content;
}

.saw-risks-status-uploaded {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

.saw-risks-status-waiting {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #d97706;
}

.saw-risks-edit-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.saw-risks-edit-btn:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.saw-risks-card-body {
    padding: 16px;
}

.saw-risks-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    text-align: center;
    color: #64748b;
    gap: 12px;
}

.saw-risks-empty span {
    font-size: 13px;
    max-width: 260px;
    line-height: 1.5;
}

.saw-risks-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding: 10px 18px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.saw-risks-add-btn:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
}

/* Risks Text Item */
.saw-risks-text-item {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 12px;
}

.saw-risks-text-item:last-child {
    margin-bottom: 0;
}

.saw-risks-text-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #fee2e2;
    border-bottom: 1px solid #fecaca;
}

.saw-risks-text-icon {
    width: 32px;
    height: 32px;
    background: #fff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    flex-shrink: 0;
}

.saw-risks-text-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}

.saw-risks-text-title {
    font-size: 12px;
    font-weight: 600;
    color: #991b1b;
}

.saw-risks-text-date {
    font-size: 11px;
    color: #b91c1c;
}

.saw-risks-fullscreen-btn {
    width: 32px;
    height: 32px;
    background: #fff;
    border: 1px solid #fecaca;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.saw-risks-fullscreen-btn:hover {
    background: #dc2626;
    border-color: #dc2626;
    color: white;
}

.saw-risks-text-content {
    padding: 14px;
    font-size: 13px;
    color: #334155;
    line-height: 1.6;
    max-height: 150px;
    overflow-y: auto;
}

/* Risks Documents */
.saw-risks-documents-section {
    margin-top: 12px;
}

.saw-risks-documents-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #991b1b;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #fecaca;
}

.saw-risks-documents-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-risks-document-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.saw-risks-document-item:hover {
    background: #fee2e2;
    border-color: #f87171;
    transform: translateX(4px);
}

.saw-risks-document-icon {
    width: 36px;
    height: 36px;
    background: #fff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    flex-shrink: 0;
}

.saw-risks-document-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.saw-risks-document-name {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.saw-risks-document-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: #64748b;
}

.saw-risks-document-ext {
    background: #dc2626;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
}

.saw-risks-document-download {
    width: 32px;
    height: 32px;
    background: #fff;
    border: 1px solid #fecaca;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.saw-risks-document-item:hover .saw-risks-document-download {
    background: #dc2626;
    border-color: #dc2626;
    color: white;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 600px) {
    .saw-visitor-card-inner,
    .saw-invitation-card-inner {
        flex-direction: column;
        align-items: stretch;
    }
    
    .saw-visitor-card-right,
    .saw-invitation-card-right {
        margin-top: 12px;
    }
    
    .saw-visitor-count-box {
        flex-direction: row;
        gap: 12px;
        justify-content: center;
    }
    
    .saw-visitor-count-box svg {
        margin-bottom: 0;
    }
    
    .saw-risks-card-header {
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .saw-risks-edit-btn {
        width: 100%;
        justify-content: center;
    }
    
    .saw-risks-document-item {
        flex-wrap: wrap;
    }
    
    .saw-risks-document-download {
        margin-left: auto;
    }
}
</style>

<!-- ============================================ -->
<!-- JAVASCRIPT                                   -->
<!-- ============================================ -->
<script>
(function() {
    document.querySelectorAll('.saw-status-btn:not(:disabled)').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var visitId = this.dataset.visitId;
            var newStatus = this.dataset.status;
            var statusLabel = this.dataset.statusLabel;
            
            var confirmMsg = '<?php echo esc_js($tr('confirm_status_change', 'Opravdu chcete změnit stav návštěvy na')); ?>:\n\n' + statusLabel + '?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            document.querySelectorAll('.saw-status-btn').forEach(function(b) {
                b.disabled = true;
                b.style.opacity = '0.5';
            });
            
            this.innerHTML = '<span class="saw-status-btn-icon">⏳</span><span class="saw-status-btn-label"><?php echo esc_js($tr('saving', 'Ukládám...')); ?></span>';
            
            jQuery.post(sawGlobal.ajaxurl, {
                action: 'saw_change_visit_status',
                visit_id: visitId,
                new_status: newStatus,
                nonce: sawGlobal.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + (response.data?.message || '<?php echo esc_js($tr('unknown_error', 'Neznámá chyba')); ?>'));
                    location.reload();
                }
            }).fail(function(xhr, status, error) {
                alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem: ' + error);
                location.reload();
            });
        });
    });
})();

function extendPinQuick(visitId, hours) {
    var card = document.getElementById('pin-card-' + visitId);
    if (!card) {
        alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: Nenalezen PIN element');
        return;
    }
    
    var currentExpiry = parseInt(card.getAttribute('data-current-expiry'));
    var now = Date.now();
    var baseTime;
    
    if (currentExpiry && currentExpiry > now) {
        baseTime = currentExpiry;
    } else {
        baseTime = now;
    }
    
    var newTimeMs = baseTime + (hours * 3600 * 1000);
    var newDate = new Date(newTimeMs);
    newDate.setSeconds(0, 0);
    
    var displayDate = formatDateTime(newDate);
    
    if (!confirm('Prodloužit platnost PIN o ' + hours + ' hodin?\n\nNová expirace: ' + displayDate)) {
        return;
    }
    
    var sqlDate = formatSQLDateTime(newDate);
    extendPinToExactTime(visitId, sqlDate, newDate);
}

function formatDateTime(date) {
    var d = date.getDate().toString().padStart(2, '0');
    var m = (date.getMonth() + 1).toString().padStart(2, '0');
    var y = date.getFullYear();
    var h = date.getHours().toString().padStart(2, '0');
    var min = date.getMinutes().toString().padStart(2, '0');
    return d + '.' + m + '.' + y + ' ' + h + ':' + min;
}

function formatSQLDateTime(date) {
    var year = date.getFullYear();
    var month = (date.getMonth() + 1).toString().padStart(2, '0');
    var day = date.getDate().toString().padStart(2, '0');
    var hours = date.getHours().toString().padStart(2, '0');
    var minutes = date.getMinutes().toString().padStart(2, '0');
    var seconds = date.getSeconds().toString().padStart(2, '0');
    return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
}

function extendPinToExactTime(visitId, sqlDate, dateObj) {
    jQuery.post(sawGlobal.ajaxurl, {
        action: 'saw_extend_pin',
        visit_id: visitId,
        hours: 999,
        exact_expiry: sqlDate,
        nonce: sawGlobal.nonce
    }, function(response) {
        if (response.success) {
            var card = document.getElementById('pin-card-' + visitId);
            if (card) {
                card.setAttribute('data-current-expiry', dateObj.getTime());
            }
            alert('✅ <?php echo esc_js($tr('alert_pin_extended', 'PIN prodloužen do')); ?>: ' + formatDateTime(dateObj));
            location.reload();
        } else {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + (response.data?.message || 'Neznámá chyba'));
        }
    }).fail(function(xhr, status, error) {
        alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem\n\n' + error);
    });
}

function showExtendPinForm(visitId) {
    document.getElementById('pin-extend-buttons-' + visitId).style.display = 'none';
    var form = document.getElementById('pin-extend-form-' + visitId);
    form.style.display = 'block';
    var datetimeInput = document.getElementById('pin-expiry-datetime-' + visitId);
    datetimeInput.min = new Date().toISOString().slice(0, 16);
}

function hideExtendPinForm(visitId) {
    document.getElementById('pin-extend-form-' + visitId).style.display = 'none';
    document.getElementById('pin-extend-buttons-' + visitId).style.display = 'flex';
}

function extendPinCustom(visitId) {
    var datetimeInput = document.getElementById('pin-expiry-datetime-' + visitId);
    var datetimeValue = datetimeInput.value;
    
    if (!datetimeValue) {
        alert('Prosím zadejte datum a čas.');
        return;
    }
    
    var newDate = new Date(datetimeValue);
    
    if (isNaN(newDate.getTime())) {
        alert('Neplatné datum');
        return;
    }
    
    var displayDate = formatDateTime(newDate);
    
    if (!confirm('Nastavit expiraci PIN na:\n' + displayDate + '?')) {
        return;
    }
    
    var sqlDate = formatSQLDateTime(newDate);
    extendPinToExactTime(visitId, sqlDate, newDate);
}

function generatePin(visitId) {
    if (!confirm('Vygenerovat PIN kód pro tuto návštěvu?\n\nPIN bude platný do posledního plánovaného dne návštěvy + 24 hodin.')) {
        return;
    }
    
    var buttons = document.querySelectorAll('button[onclick*="generatePin(' + visitId + ')"]');
    var button = buttons.length > 0 ? buttons[0] : (event ? event.target : null);
    
    if (button) {
        var originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳ <?php echo esc_js($tr('generating', 'Generování...')); ?>';
        
        jQuery.post(sawGlobal.ajaxurl, {
            action: 'saw_generate_pin',
            visit_id: visitId,
            nonce: sawGlobal.nonce
        }, function(response) {
            if (response && response.success) {
                alert('✅ <?php echo esc_js($tr('alert_pin_generated', 'PIN úspěšně vygenerován')); ?>: ' + (response.data.pin_code || 'N/A') + '\n\nPlatnost: ' + (response.data.pin_expires_at || 'N/A'));
                location.reload();
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : 'Neznámá chyba';
                alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + msg);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }).fail(function(xhr, status, error) {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem: ' + error);
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }
}

function sendInvitation(visitId) {
    var btn = document.getElementById('send-invitation-btn-' + visitId);
    if (!btn) return;
    
    var originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> <?php echo esc_js($tr('sending', 'Odesílám...')); ?>';
    
    jQuery.post(sawGlobal.ajaxurl, {
        action: 'saw_send_invitation',
        visit_id: visitId,
        nonce: sawGlobal.nonce
    }, function(response) {
        if (response.success) {
            alert('✅ <?php echo esc_js($tr('alert_invitation_sent', 'Pozvánka byla úspěšně odeslána na email')); ?>: ' + (response.data?.email || ''));
            location.reload();
        } else {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + (response.data?.message || 'Nepodařilo se odeslat pozvánku'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }).fail(function(xhr, status, error) {
        alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem\n\n' + error);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function openRisksFullscreen(materialId) {
    alert('Fullscreen zobrazení pro material #' + materialId);
}

if (!document.getElementById('saw-spinner-style')) {
    var style = document.createElement('style');
    style.id = 'saw-spinner-style';
    style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
}
</script>