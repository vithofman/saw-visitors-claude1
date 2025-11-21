<?php
if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěvník nebyl nalezen</div>';
    return;
}
?>

<!-- Fonts are now loaded globally in admin-table-detail.css -->



<div class="saw-detail-wrapper">
    <!-- Header is now rendered by detail-sidebar.php via saw-detail-header-universal -->
    <!-- Removed duplicate saw-industrial-header -->
    
    <div class="saw-detail-stack">
        
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🏢 Informace o návštěvě</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">Firma</span>
                    <span class="saw-info-val"><?php echo esc_html($item['visit_data']['company_name'] ?? '—'); ?></span>
                </div>
                
                <?php if(!empty($item['visit_data']['branch_name'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Pobočka</span>
                    <span class="saw-info-val"><?php echo esc_html($item['visit_data']['branch_name']); ?></span>
                </div>
                <?php endif; ?>

                <div class="saw-info-row">
                    <span class="saw-info-label">Hostitelé</span>
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
                <div class="saw-info-row" style="border-top: 1px dashed #eee; margin-top: 8px; padding-top: 8px;">
                    <span class="saw-info-label">Akce</span>
                    <span class="saw-info-val">
                        <a href="<?php echo esc_url(home_url('/admin/visits/' . $item['visit_id'] . '/')); ?>">
                            Zobrazit návštěvu #<?php echo $item['visit_id']; ?> →
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">👤 Kontaktní údaje</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">Email</span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['email'])): ?>
                            <a href="mailto:<?php echo esc_attr($item['email']); ?>"><?php echo esc_html($item['email']); ?></a>
                        <?php else: ?>
                            <span style="color:#ccc">—</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="saw-info-row">
                    <span class="saw-info-label">Telefon</span>
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

        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">✓ Stav účasti</h4>
            </div>
            <div class="saw-section-body">
                <?php
                // ========================================
                // ✅ PŮVODNÍ LOGIKA - BEZE ZMĚN
                // ========================================
                global $wpdb;
                $today = current_time('Y-m-d');
                
                $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND log_date = %s ORDER BY checked_in_at DESC LIMIT 1", $item['id'], $today), ARRAY_A);
                
                // Výchozí hodnoty (podle "else" v původním kódu = Plánovaný)
                $st_class = 'status-info';
                $st_icon = '📅';
                $st_text = 'Plánovaný';

                if ($item['participation_status'] === 'confirmed') {
                    if ($log && $log['checked_in_at'] && !$log['checked_out_at']) {
                        // Přítomen
                        $st_class = 'status-success'; $st_icon = '✅'; $st_text = 'Přítomen';
                    } elseif ($log && $log['checked_out_at']) {
                        // Odhlášen
                        $st_class = 'status-neutral'; $st_icon = '🚪'; $st_text = 'Odhlášen';
                    } else {
                        // Potvrzený
                        $st_class = 'status-warning'; $st_icon = '⏳'; $st_text = 'Potvrzený';
                    }
                } elseif ($item['participation_status'] === 'no_show') {
                    // Nedostavil se
                    $st_class = 'status-danger'; $st_icon = '❌'; $st_text = 'Nedostavil se';
                }
                ?>

                <div class="saw-status-box <?php echo $st_class; ?>">
                    <div class="st-icon"><?php echo $st_icon; ?></div>
                    <div class="st-content">
                        <span class="st-title">Aktuální stav</span>
                        <span class="st-value"><?php echo $st_text; ?></span>
                    </div>
                </div>

                <?php 
                // ✅ 1. První check-in
                $first_log = $wpdb->get_row($wpdb->prepare("SELECT checked_in_at FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND checked_in_at IS NOT NULL ORDER BY checked_in_at ASC LIMIT 1", $item['id']), ARRAY_A);
                
                // ✅ 2. Poslední check-in (RESTORED)
                $last_checkin = $wpdb->get_row($wpdb->prepare("SELECT checked_in_at FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND checked_in_at IS NOT NULL ORDER BY checked_in_at DESC LIMIT 1", $item['id']), ARRAY_A);

                // ✅ 3. Poslední check-out
                $last_checkout = $wpdb->get_row($wpdb->prepare("SELECT checked_out_at FROM {$wpdb->prefix}saw_visit_daily_logs WHERE visitor_id = %d AND checked_out_at IS NOT NULL ORDER BY checked_out_at DESC LIMIT 1", $item['id']), ARRAY_A);
                ?>

                <div class="saw-info-row">
                    <span class="saw-info-label">První check-in</span>
                    <span class="saw-info-val"><?php echo !empty($first_log['checked_in_at']) ? date('d.m.Y H:i', strtotime($first_log['checked_in_at'])) : '—'; ?></span>
                </div>
                
                <div class="saw-info-row">
                    <span class="saw-info-label">Poslední check-in</span>
                    <span class="saw-info-val"><?php echo !empty($last_checkin['checked_in_at']) ? date('d.m.Y H:i', strtotime($last_checkin['checked_in_at'])) : '—'; ?></span>
                </div>

                <div class="saw-info-row">
                    <span class="saw-info-label">Poslední check-out</span>
                    <span class="saw-info-val"><?php echo !empty($last_checkout['checked_out_at']) ? date('d.m.Y H:i', strtotime($last_checkout['checked_out_at'])) : '—'; ?></span>
                </div>
            </div>
        </div>

        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🎓 Školení BOZP</h4>
            </div>
            <div class="saw-section-body">
                
                <?php 
                // Status logik
                $tr_class = 'status-neutral'; $tr_icon = '⚪'; $tr_text = 'Nespuštěno';

                if (!empty($item['training_skipped'])) {
                    $tr_class = 'status-warning'; $tr_icon = '⏭️'; $tr_text = 'Přeskočeno (do 1 roku)';
                } elseif (!empty($item['training_completed_at'])) {
                    $tr_class = 'status-success'; $tr_icon = '✅'; $tr_text = 'Dokončeno';
                } elseif (!empty($item['training_started_at'])) {
                    $tr_class = 'status-info'; $tr_icon = '🔄'; $tr_text = 'Probíhá';
                }
                ?>

                <div class="saw-status-box <?php echo $tr_class; ?>">
                    <div class="st-icon"><?php echo $tr_icon; ?></div>
                    <div class="st-content">
                        <span class="st-title">Status školení</span>
                        <span class="st-value"><?php echo $tr_text; ?></span>
                    </div>
                </div>

                <?php if (!empty($item['training_started_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Zahájeno</span>
                    <span class="saw-info-val"><?php echo date('d.m.Y H:i', strtotime($item['training_started_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['training_completed_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Dokončeno</span>
                    <span class="saw-info-val"><?php echo date('d.m.Y H:i', strtotime($item['training_completed_at'])); ?></span>
                </div>
                <?php endif; ?>

                <?php 
                // Calculation logic
                if (!empty($item['training_started_at']) && !empty($item['training_completed_at'])):
                    try {
                        $start = new DateTime($item['training_started_at']);
                        $end = new DateTime($item['training_completed_at']);
                        $interval = $start->diff($end);
                        if ($interval->invert) {
                            $d_text = 'Chyba dat';
                        } else {
                            $secs = ($interval->days * 24 * 3600) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                            if ($secs < 60) $d_text = $secs . ' sekund';
                            elseif ($secs < 3600) $d_text = $interval->i . ' min ' . $interval->s . ' s';
                            else $d_text = $interval->h . ' h ' . $interval->i . ' min ' . $interval->s . ' s';
                        }
                    } catch(Exception $e) { $d_text = 'Neplatný formát'; }
                ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">⏱️ Doba školení</span>
                    <span class="saw-info-val"><strong><?php echo $d_text; ?></strong></span>
                </div>
                <?php endif; ?>

                <?php if (!$item['training_skipped'] && !empty($item['training_started_at'])): ?>
                <div class="saw-progress-title">Progress</div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_video'] ? '✅' : '⬜'; ?></span>
                    <span>Video školení</span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_map'] ? '✅' : '⬜'; ?></span>
                    <span>Mapa objektu</span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_risks'] ? '✅' : '⬜'; ?></span>
                    <span>Informace o rizicích</span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_additional'] ? '✅' : '⬜'; ?></span>
                    <span>Další informace</span>
                </div>
                <div class="saw-step-item">
                    <span style="width:20px; text-align:center;"><?php echo $item['training_step_department'] ? '✅' : '⬜'; ?></span>
                    <span>Specifika oddělení</span>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📊 Historie check-in/out</h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php if (!empty($item['daily_logs'])): ?>
                    <table class="saw-simple-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>IN</th>
                                <th>OUT</th>
                                <th>Doba</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item['daily_logs'] as $log): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($log['log_date'])); ?></td>
                                <td><?php echo $log['checked_in_at'] ? date('H:i', strtotime($log['checked_in_at'])) : '—'; ?></td>
                                <td>
                                    <?php 
                                    if ($log['checked_out_at']) {
                                        echo date('H:i', strtotime($log['checked_out_at']));
                                    } else {
                                        echo '<span class="saw-badge saw-badge-success" style="font-size:10px; background:#ecfdf5; color:#047857; padding:2px 6px; border-radius:4px;">Přítomen</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($log['checked_in_at'] && $log['checked_out_at']) {
                                        $diff = strtotime($log['checked_out_at']) - strtotime($log['checked_in_at']);
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
                    <div style="padding: 20px; color: #888; font-style: italic; text-align: center;">Žádná historie check-in/out.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📜 Profesní průkazy</h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['certificates'])): ?>
                    <?php foreach ($item['certificates'] as $cert): ?>
                    <div class="saw-cert-row">
                        <div class="saw-cert-icon">📄</div>
                        <div>
                            <div class="saw-cert-title"><?php echo esc_html($cert['certificate_name']); ?></div>
                            <div class="saw-cert-sub">
                                <?php if (!empty($cert['certificate_number'])) echo 'Číslo: ' . esc_html($cert['certificate_number']) . '<br>'; ?>
                                <?php if (!empty($cert['valid_until'])) echo 'Platný do: ' . esc_html($cert['valid_until']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #888; font-style: italic; text-align: center;">Žádné průkazy.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>