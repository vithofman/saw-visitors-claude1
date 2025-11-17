<?php
if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěvník nebyl nalezen</div>';
    return;
}
?>

<style>
/* FORCE INLINE CSS */
.saw-detail-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 20px !important;
    padding: 20px !important;
}

/* Full-width cards */
.saw-detail-card-full {
    grid-column: 1 / -1 !important;
}
.saw-detail-card {
    background: #fff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    overflow: hidden !important;
}

.saw-detail-card-header {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 16px !important;
    background: #f8fafc !important;
    border-bottom: 1px solid #e5e7eb !important;
}

.saw-detail-card-icon {
    font-size: 24px !important;
}

.saw-detail-card-title {
    margin: 0 !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    color: #1e293b !important;
}

.saw-detail-card-body {
    padding: 16px !important;
}

.saw-detail-list {
    display: grid !important;
    gap: 12px !important;
    margin: 0 !important;
}

.saw-detail-label {
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    margin: 0 !important;
}

.saw-detail-value {
    font-size: 14px !important;
    color: #1e293b !important;
    margin: 0 0 12px 0 !important;
}

.saw-certificates-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
}

.saw-certificate-item {
    display: flex !important;
    gap: 12px !important;
    padding: 12px !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 6px !important;
}

.saw-cert-icon {
    font-size: 20px !important;
}

.saw-cert-name {
    font-weight: 600 !important;
    color: #1e293b !important;
    margin-bottom: 4px !important;
}

.saw-cert-meta {
    font-size: 13px !important;
    color: #64748b !important;
}

.saw-hosts-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
}

.saw-host-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 8px 12px !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 6px !important;
}

.saw-host-item .dashicons {
    color: #2271b1 !important;
    font-size: 20px !important;
    width: 20px !important;
    height: 20px !important;
}

.saw-training-progress {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
}

.saw-training-step {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 8px !important;
    background: #f8fafc !important;
    border-radius: 6px !important;
}

.saw-training-step.completed {
    background: #f0fdf4 !important;
    border: 1px solid #bbf7d0 !important;
}

.saw-training-step.incomplete {
    background: #fef2f2 !important;
    border: 1px solid #fecaca !important;
}
</style>

<div class="saw-detail-grid">
    
    <!-- 1. ZÁKLADNÍ INFORMACE -->
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">👤</span>
            <h3 class="saw-detail-card-title">Základní informace</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Jméno a příjmení</dt>
                <dd class="saw-detail-value"><strong><?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?></strong></dd>
                
                <?php if (!empty($item['position'])): ?>
                <dt class="saw-detail-label">Pozice/profese</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['position']); ?></dd>
                <?php endif; ?>
                
                <dt class="saw-detail-label">Email</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['email'])): ?>
                        <a href="mailto:<?php echo esc_attr($item['email']); ?>"><?php echo esc_html($item['email']); ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                
                <dt class="saw-detail-label">Telefon</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['phone'])): ?>
                        <a href="tel:<?php echo esc_attr($item['phone']); ?>"><?php echo esc_html($item['phone']); ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <!-- 2. NÁVŠTĚVA -->
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">🏢</span>
            <h3 class="saw-detail-card-title">Návštěva</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Firma</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['visit_data']['company_name'] ?? '—'); ?></dd>
                
                <dt class="saw-detail-label">Pobočka</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['visit_data']['branch_name'] ?? '—'); ?></dd>
                
                <dt class="saw-detail-label">Koho navštěvuje</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['visit_data']['hosts'])): ?>
                        <div class="saw-hosts-list">
                            <?php foreach ($item['visit_data']['hosts'] as $host): ?>
                            <div class="saw-host-item">
                                <span class="dashicons dashicons-businessman"></span>
                                <div>
                                    <strong><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></strong>
                                    <?php if (!empty($host['email'])): ?>
                                    <div style="font-size:12px;color:#64748b;"><?php echo esc_html($host['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                
                <dt class="saw-detail-label">Detail návštěvy</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['visit_id'])): ?>
                        <a href="<?php echo esc_url(home_url('/admin/visits/' . $item['visit_id'] . '/')); ?>">
                            Zobrazit návštěvu #<?php echo $item['visit_id']; ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <!-- 3. STAV ÚČASTI -->
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">✓</span>
            <h3 class="saw-detail-card-title">Stav účasti</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Stav</dt>
                <dd class="saw-detail-value">
                    <?php
                    $status = $item['participation_status'] ?? 'planned';
                    $badges = array(
                        'planned' => '<span class="saw-badge saw-badge-info">Plánovaný</span>',
                        'confirmed' => '<span class="saw-badge saw-badge-success">Potvrzený</span>',
                        'no_show' => '<span class="saw-badge saw-badge-danger">Nedorazil</span>',
                    );
                    echo $badges[$status] ?? $status;
                    ?>
                </dd>
                
                <dt class="saw-detail-label">První check-in</dt>
                <dd class="saw-detail-value">
                    <?php echo !empty($item['first_checkin_at']) ? date('d.m.Y H:i', strtotime($item['first_checkin_at'])) : '—'; ?>
                </dd>
                
                <dt class="saw-detail-label">Poslední check-out</dt>
                <dd class="saw-detail-value">
                    <?php echo !empty($item['last_checkout_at']) ? date('d.m.Y H:i', strtotime($item['last_checkout_at'])) : '—'; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <!-- 4. ŠKOLENÍ -->
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">🎓</span>
            <h3 class="saw-detail-card-title">Školení BOZP</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Status</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['training_skipped'])): ?>
                        <span class="saw-badge saw-badge-warning">⏭️ Přeskočeno (do 1 roku)</span>
                    <?php elseif (!empty($item['training_completed_at'])): ?>
                        <span class="saw-badge saw-badge-success">✅ Dokončeno</span>
                    <?php elseif (!empty($item['training_started_at'])): ?>
                        <span class="saw-badge saw-badge-info">🔄 Probíhá</span>
                    <?php else: ?>
                        <span class="saw-badge saw-badge-secondary">⚪ Nespuštěno</span>
                    <?php endif; ?>
                </dd>
                
                <?php if (!empty($item['training_started_at'])): ?>
                <dt class="saw-detail-label">Zahájeno</dt>
                <dd class="saw-detail-value"><?php echo date('d.m.Y H:i', strtotime($item['training_started_at'])); ?></dd>
                <?php endif; ?>
                
                <?php if (!empty($item['training_completed_at'])): ?>
                <dt class="saw-detail-label">Dokončeno</dt>
                <dd class="saw-detail-value"><?php echo date('d.m.Y H:i', strtotime($item['training_completed_at'])); ?></dd>
                <?php endif; ?>

 <?php 
                // ✅ VÝPOČET DOBY ŠKOLENÍ
                if (!empty($item['training_started_at']) && !empty($item['training_completed_at'])): 
                    $start = strtotime($item['training_started_at']);
                    $end = strtotime($item['training_completed_at']);
                    $duration_seconds = $end - $start;
                    
                    if ($duration_seconds < 60) {
                        // Méně než minuta → sekundy
                        $duration_text = $duration_seconds . ' sekund';
                    } elseif ($duration_seconds < 3600) {
                        // Méně než hodina → minuty a sekundy
                        $minutes = floor($duration_seconds / 60);
                        $seconds = $duration_seconds % 60;
                        $duration_text = $minutes . ' min ' . $seconds . ' s';
                    } else {
                        // Více než hodina → hodiny, minuty, sekundy
                        $hours = floor($duration_seconds / 3600);
                        $minutes = floor(($duration_seconds % 3600) / 60);
                        $seconds = $duration_seconds % 60;
                        $duration_text = $hours . ' h ' . $minutes . ' min ' . $seconds . ' s';
                    }
                ?>
                <dt class="saw-detail-label">⏱️ Doba školení</dt>
                <dd class="saw-detail-value">
                    <strong><?php echo $duration_text; ?></strong>
                </dd>
                <?php endif; ?>
                
                <?php if (!$item['training_skipped'] && !empty($item['training_started_at'])): ?>
                <dt class="saw-detail-label">Progress</dt>
                <dd class="saw-detail-value">
                    <div class="saw-training-progress">
                        <div class="saw-training-step <?php echo $item['training_step_video'] ? 'completed' : 'incomplete'; ?>">
                            <?php echo $item['training_step_video'] ? '✅' : '⬜'; ?> Video školení
                        </div>
                        <div class="saw-training-step <?php echo $item['training_step_map'] ? 'completed' : 'incomplete'; ?>">
                            <?php echo $item['training_step_map'] ? '✅' : '⬜'; ?> Mapa objektu
                        </div>
                        <div class="saw-training-step <?php echo $item['training_step_risks'] ? 'completed' : 'incomplete'; ?>">
                            <?php echo $item['training_step_risks'] ? '✅' : '⬜'; ?> Informace o rizicích
                        </div>
                        <div class="saw-training-step <?php echo $item['training_step_additional'] ? 'completed' : 'incomplete'; ?>">
                            <?php echo $item['training_step_additional'] ? '✅' : '⬜'; ?> Další informace
                        </div>
                        <div class="saw-training-step <?php echo $item['training_step_department'] ? 'completed' : 'incomplete'; ?>">
                            <?php echo $item['training_step_department'] ? '✅' : '⬜'; ?> Specifika oddělení
                        </div>
                    </div>
                </dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>
    
    <!-- 5. HISTORIE CHECK-IN/OUT -->
    <div class="saw-detail-card saw-detail-card-full">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">📊</span>
            <h3 class="saw-detail-card-title">Historie check-in/out</h3>
        </div>
        <div class="saw-detail-card-body">
            <?php if (!empty($item['daily_logs'])): ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 8px; text-align: left; font-size: 13px; color: #64748b;">Datum</th>
                            <th style="padding: 8px; text-align: left; font-size: 13px; color: #64748b;">Check-in</th>
                            <th style="padding: 8px; text-align: left; font-size: 13px; color: #64748b;">Check-out</th>
                            <th style="padding: 8px; text-align: left; font-size: 13px; color: #64748b;">Doba</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($item['daily_logs'] as $log): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 8px; font-size: 14px;">
                                <?php echo date('d.m.Y', strtotime($log['log_date'])); ?>
                            </td>
                            <td style="padding: 8px; font-size: 14px;">
                                <?php echo $log['checked_in_at'] ? date('H:i', strtotime($log['checked_in_at'])) : '—'; ?>
                            </td>
                            <td style="padding: 8px; font-size: 14px;">
                                <?php 
                                if ($log['checked_out_at']) {
                                    echo date('H:i', strtotime($log['checked_out_at']));
                                } else {
                                    echo '<span class="saw-badge saw-badge-success">Přítomen</span>';
                                }
                                ?>
                            </td>
                            <td style="padding: 8px; font-size: 14px; color: #64748b;">
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
                <p style="color: #64748b; font-size: 14px;">Žádná historie check-in/out.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 6. PROFESNÍ PRŮKAZY -->
    <div class="saw-detail-card saw-detail-card-full">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">📜</span>
            <h3 class="saw-detail-card-title">Profesní průkazy</h3>
        </div>
        <div class="saw-detail-card-body">
            <?php if (!empty($item['certificates'])): ?>
                <div class="saw-certificates-list">
                    <?php foreach ($item['certificates'] as $cert): ?>
                    <div class="saw-certificate-item">
                        <div class="saw-cert-icon">📄</div>
                        <div class="saw-cert-content">
                            <div class="saw-cert-name"><?php echo esc_html($cert['certificate_name']); ?></div>
                            <?php if (!empty($cert['certificate_number'])): ?>
                            <div class="saw-cert-meta">Číslo: <?php echo esc_html($cert['certificate_number']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($cert['valid_until'])): ?>
                            <div class="saw-cert-meta">Platný do: <?php echo esc_html($cert['valid_until']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #64748b; font-size: 14px;">Žádné průkazy.</p>
            <?php endif; ?>
        </div>
    </div>
    
</div>