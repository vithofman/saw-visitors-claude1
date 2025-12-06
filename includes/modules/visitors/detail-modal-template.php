<?php
/**
 * Visitors Detail Sidebar Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     4.0.0 - Multi-language support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visitors') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html($tr('error_not_found', 'Návštěvník nebyl nalezen')) . '</div>';
    return;
}
?>

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- VISIT INFORMATION -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🏢 <?php echo esc_html($tr('section_visit_info', 'Informace o návštěvě')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_company', 'Firma')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['visit_data']['company_name'] ?? '—'); ?></span>
                </div>
                
                <?php if(!empty($item['visit_data']['branch_name'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_branch', 'Pobočka')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['visit_data']['branch_name']); ?></span>
                </div>
                <?php endif; ?>

                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_hosts', 'Hostitelé')); ?></span>
                    <div class="saw-info-val">
                        <?php if (!empty($item['visit_data']['hosts'])): ?>
                            <?php foreach ($item['visit_data']['hosts'] as $host): ?>
                                <div class="saw-host-mini">
                                    <span class="dashicons dashicons-businessman" style="color:#ccc"></span>
                                    <span><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($item['visit_id'])): ?>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e5e7eb;">
                    <a href="<?php echo esc_url(home_url('/admin/visits/' . $item['visit_id'] . '/')); ?>" 
                       class="saw-button saw-button-primary" 
                       style="display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center;">
                        <span class="dashicons dashicons-visibility" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php echo esc_html($tr('link_view_visit', 'Zobrazit návštěvu')); ?> #<?php echo $item['visit_id']; ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CONTACT INFORMATION -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">👤 <?php echo esc_html($tr('section_contact', 'Kontaktní údaje')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_email', 'Email')); ?></span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['email'])): ?>
                            <a href="mailto:<?php echo esc_attr($item['email']); ?>"><?php echo esc_html($item['email']); ?></a>
                        <?php else: ?>
                            <span style="color:#ccc">—</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_phone', 'Telefon')); ?></span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['phone'])): ?>
                            <a href="tel:<?php echo esc_attr($item['phone']); ?>"><?php echo esc_html($item['phone']); ?></a>
                        <?php else: ?>
                            <span style="color:#ccc">—</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- PARTICIPATION STATUS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">✓ <?php echo esc_html($tr('section_participation', 'Stav účasti')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php
                global $wpdb;
                $today = current_time('Y-m-d');
                
                $log = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND log_date = %s ORDER BY checked_in_at DESC LIMIT 1", 
                    $item['id'], 
                    $today
                ), ARRAY_A);
                
                // Default values
                $st_class = 'status-info';
                $st_icon = '📅';
                $st_text = $tr('status_planned_short', 'Plánovaný');

                if ($item['participation_status'] === 'confirmed') {
                    if ($log && $log['checked_in_at'] && !$log['checked_out_at']) {
                        $st_class = 'status-success';
                        $st_icon = '✅';
                        $st_text = $tr('status_present_short', 'Přítomen');
                    } elseif ($log && $log['checked_out_at']) {
                        $st_class = 'status-neutral';
                        $st_icon = '🚪';
                        $st_text = $tr('status_checked_out_short', 'Odhlášen');
                    } else {
                        $st_class = 'status-warning';
                        $st_icon = '⏳';
                        $st_text = $tr('status_confirmed_short', 'Potvrzený');
                    }
                } elseif ($item['participation_status'] === 'no_show') {
                    $st_class = 'status-danger';
                    $st_icon = '❌';
                    $st_text = $tr('status_no_show_short', 'Nedostavil se');
                }
                ?>

                <div class="saw-status-box <?php echo $st_class; ?>">
                    <div class="st-icon"><?php echo $st_icon; ?></div>
                    <div class="st-content">
                        <span class="st-title"><?php echo esc_html($tr('field_current_status', 'Aktuální stav')); ?></span>
                        <span class="st-value"><?php echo esc_html($st_text); ?></span>
                    </div>
                </div>

                <?php 
                $first_log = $wpdb->get_row($wpdb->prepare(
                    "SELECT checked_in_at FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND checked_in_at IS NOT NULL ORDER BY checked_in_at ASC LIMIT 1", 
                    $item['id']
                ), ARRAY_A);
                
                $last_checkin = $wpdb->get_row($wpdb->prepare(
                    "SELECT checked_in_at FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND checked_in_at IS NOT NULL ORDER BY checked_in_at DESC LIMIT 1", 
                    $item['id']
                ), ARRAY_A);

                $last_checkout = $wpdb->get_row($wpdb->prepare(
                    "SELECT checked_out_at FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND checked_out_at IS NOT NULL ORDER BY checked_out_at DESC LIMIT 1", 
                    $item['id']
                ), ARRAY_A);
                ?>

                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_first_checkin', 'První check-in')); ?></span>
                    <span class="saw-info-val"><?php echo !empty($first_log['checked_in_at']) ? date('d.m.Y H:i', strtotime($first_log['checked_in_at'])) : '—'; ?></span>
                </div>
                
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_last_checkin', 'Poslední check-in')); ?></span>
                    <span class="saw-info-val"><?php echo !empty($last_checkin['checked_in_at']) ? date('d.m.Y H:i', strtotime($last_checkin['checked_in_at'])) : '—'; ?></span>
                </div>

                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_last_checkout', 'Poslední check-out')); ?></span>
                    <span class="saw-info-val"><?php echo !empty($last_checkout['checked_out_at']) ? date('d.m.Y H:i', strtotime($last_checkout['checked_out_at'])) : '—'; ?></span>
                </div>
            </div>
        </div>

        <!-- TRAINING STATUS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🎓 <?php echo esc_html($tr('section_training', 'Školení BOZP')); ?></h4>
            </div>
            <div class="saw-section-body">
                
                <?php 
                $tr_class = 'status-neutral';
                $tr_icon = '⚪';
                $tr_text = $tr('training_not_started_short', 'Nespuštěno');

                if (!empty($item['training_skipped'])) {
                    $tr_class = 'status-warning';
                    $tr_icon = '⏭️';
                    $tr_text = $tr('training_skipped_detail', 'Přeskočeno (do 1 roku)');
                } elseif (!empty($item['training_completed_at'])) {
                    $tr_class = 'status-success';
                    $tr_icon = '✅';
                    $tr_text = $tr('training_completed_short', 'Dokončeno');
                } elseif (!empty($item['training_started_at'])) {
                    $tr_class = 'status-info';
                    $tr_icon = '🔄';
                    $tr_text = $tr('training_in_progress_short', 'Probíhá');
                }
                ?>

                <div class="saw-status-box <?php echo $tr_class; ?>">
                    <div class="st-icon"><?php echo $tr_icon; ?></div>
                    <div class="st-content">
                        <span class="st-title"><?php echo esc_html($tr('field_training_status', 'Status školení')); ?></span>
                        <span class="st-value"><?php echo esc_html($tr_text); ?></span>
                    </div>
                </div>

                <?php if (!empty($item['training_started_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_training_started', 'Zahájeno')); ?></span>
                    <span class="saw-info-val"><?php echo date('d.m.Y H:i', strtotime($item['training_started_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['training_completed_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_training_completed', 'Dokončeno')); ?></span>
                    <span class="saw-info-val"><?php echo date('d.m.Y H:i', strtotime($item['training_completed_at'])); ?></span>
                </div>
                <?php endif; ?>

                <?php 
                if (!empty($item['training_started_at']) && !empty($item['training_completed_at'])):
                    try {
                        $start = new DateTime($item['training_started_at']);
                        $end = new DateTime($item['training_completed_at']);
                        $interval = $start->diff($end);
                        if ($interval->invert) {
                            $d_text = $tr('error_date', 'Chyba dat');
                        } else {
                            $secs = ($interval->days * 24 * 3600) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                            if ($secs < 60) {
                                $d_text = $secs . ' ' . $tr('time_seconds', 'sekund');
                            } elseif ($secs < 3600) {
                                $d_text = $interval->i . ' min ' . $interval->s . ' s';
                            } else {
                                $d_text = $interval->h . ' h ' . $interval->i . ' min ' . $interval->s . ' s';
                            }
                        }
                    } catch(Exception $e) {
                        $d_text = $tr('error_invalid_format', 'Neplatný formát');
                    }
                ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">⏱️ <?php echo esc_html($tr('field_training_duration', 'Doba školení')); ?></span>
                    <span class="saw-info-val"><strong><?php echo esc_html($d_text); ?></strong></span>
                </div>
                <?php endif; ?>

                <?php if (!$item['training_skipped'] && !empty($item['training_started_at'])): ?>
                <div class="saw-progress-title"><?php echo esc_html($tr('field_progress', 'Progress')); ?></div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_video'] ? '✅' : '⬜'; ?></span>
                    <span><?php echo esc_html($tr('step_video', 'Video školení')); ?></span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_map'] ? '✅' : '⬜'; ?></span>
                    <span><?php echo esc_html($tr('step_map', 'Mapa objektu')); ?></span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_risks'] ? '✅' : '⬜'; ?></span>
                    <span><?php echo esc_html($tr('step_risks', 'Informace o rizicích')); ?></span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_additional'] ? '✅' : '⬜'; ?></span>
                    <span><?php echo esc_html($tr('step_additional', 'Další informace')); ?></span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_department'] ? '✅' : '⬜'; ?></span>
                    <span><?php echo esc_html($tr('step_department', 'Specifika oddělení')); ?></span>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- CHECK-IN/OUT HISTORY -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📊 <?php echo esc_html($tr('section_history', 'Historie check-in/out')); ?></h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php if (!empty($item['daily_logs'])): ?>
                    <table class="saw-simple-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html($tr('table_date', 'Datum')); ?></th>
                                <th><?php echo esc_html($tr('table_in', 'IN')); ?></th>
                                <th><?php echo esc_html($tr('table_out', 'OUT')); ?></th>
                                <th><?php echo esc_html($tr('table_duration', 'Doba')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item['daily_logs'] as $log_item): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($log_item['log_date'])); ?></td>
                                <td><?php echo $log_item['checked_in_at'] ? date('H:i', strtotime($log_item['checked_in_at'])) : '—'; ?></td>
                                <td>
                                    <?php 
                                    if ($log_item['checked_out_at']) {
                                        echo date('H:i', strtotime($log_item['checked_out_at']));
                                    } else {
                                        echo '<span class="saw-badge saw-badge-success" style="font-size:10px; background:#ecfdf5; color:#047857; padding:2px 6px; border-radius:4px;">' . esc_html($tr('status_present_short', 'Přítomen')) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($log_item['checked_in_at'] && $log_item['checked_out_at']) {
                                        $diff = strtotime($log_item['checked_out_at']) - strtotime($log_item['checked_in_at']);
                                        $hours = floor($diff / 3600);
                                        $minutes = floor(($diff % 3600) / 60);
                                        echo sprintf('%dh %dm', $hours, $minutes);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 20px; color: #888; font-style: italic; text-align: center;">
                        <?php echo esc_html($tr('empty_history', 'Žádná historie check-in/out.')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CERTIFICATES -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📜 <?php echo esc_html($tr('section_certificates', 'Profesní průkazy')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['certificates'])): ?>
                    <?php foreach ($item['certificates'] as $cert): ?>
                    <div class="saw-cert-row">
                        <div class="saw-cert-icon">📄</div>
                        <div>
                            <div class="saw-cert-title"><?php echo esc_html($cert['certificate_name']); ?></div>
                            <div class="saw-cert-sub">
                                <?php if (!empty($cert['certificate_number'])): ?>
                                    <?php echo esc_html($tr('cert_number', 'Číslo')); ?>: <?php echo esc_html($cert['certificate_number']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($cert['valid_until'])): ?>
                                    <?php echo esc_html($tr('cert_valid_until', 'Platný do')); ?>: <?php echo esc_html($cert['valid_until']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #888; font-style: italic; text-align: center;">
                        <?php echo esc_html($tr('empty_certificates', 'Žádné průkazy.')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>