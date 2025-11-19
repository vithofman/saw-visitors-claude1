<?php
if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěvník nebyl nalezen</div>';
    return;
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
/* 🎨 307P DESIGN SYSTEM */
:root {
    --p307-primary: #005A8C;
    --p307-accent: #0077B5;
    --p307-dark: #1a1a1a;
    --p307-gray: #f4f6f8;
    --p307-border: #dce1e5;
    
    /* Status Colors */
    --st-success-bg: #ecfdf5; --st-success-text: #047857; --st-success-border: #a7f3d0;
    --st-warning-bg: #fffbeb; --st-warning-text: #b45309; --st-warning-border: #fde68a;
    --st-danger-bg: #fef2f2;  --st-danger-text: #b91c1c;  --st-danger-border: #fecaca;
    --st-info-bg: #eff6ff;    --st-info-text: #1d4ed8;    --st-info-border: #bfdbfe;
    --st-neutral-bg: #f3f4f6; --st-neutral-text: #4b5563; --st-neutral-border: #e5e7eb;
}

.saw-detail-wrapper {
    font-family: 'Roboto', sans-serif;
    color: var(--p307-dark);
    box-sizing: border-box;
    max-width: 100%;
}

.saw-detail-wrapper * { box-sizing: border-box; }

/* LAYOUT - JEDEN SLOUPEC */
.saw-detail-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 16px;
}

/* HEADER */
.saw-industrial-header {
    background: var(--p307-primary);
    color: #fff;
    border-radius: 4px 4px 0 0;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,90,140,.2);
}

.saw-header-inner { padding: 24px 24px 16px 24px; }

.saw-industrial-header h3 {
    font-family: 'Oswald', sans-serif;
    font-weight: 700;
    font-size: 28px;
    text-transform: uppercase;
    margin: 0 0 8px 0;
    color: #fff !important;
    letter-spacing: 1px;
    line-height: 1.1;
}

.saw-header-meta { display: flex; gap: 10px; align-items: center; font-size: 13px; opacity: 0.9; }

.saw-industrial-stripe {
    height: 8px;
    width: 100%;
    background-image: repeating-linear-gradient(-45deg, var(--p307-primary), var(--p307-primary) 10px, #fff 10px, #fff 20px);
    border-bottom: 1px solid rgba(0,0,0,.1);
}

/* SECTION */
.saw-industrial-section {
    background: #fff;
    border: 1px solid var(--p307-border);
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
}

.saw-section-head {
    background: #fff;
    padding: 14px 20px;
    border-bottom: 2px solid var(--p307-gray);
}

.saw-section-title {
    font-family: 'Oswald', sans-serif;
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--p307-primary);
    margin: 0;
    display: flex; align-items: center; gap: 8px;
}

.saw-section-body { padding: 20px; }

/* INFO ROWS - LEFT ALIGNED */
.saw-info-row {
    display: grid;
    grid-template-columns: 140px 1fr; /* Pevná šířka pro label, zbytek pro hodnotu */
    gap: 12px;
    align-items: baseline; /* Zarovnání na řádek textu */
    border-bottom: 1px dotted #e5e7eb;
    padding-bottom: 8px;
    margin-bottom: 8px;
}
.saw-info-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

.saw-info-label { 
    font-size: 12px; 
    color: #888; 
    font-weight: 700; 
    text-transform: uppercase; 
}

.saw-info-val { 
    font-size: 14px; 
    color: var(--p307-dark); 
    font-weight: 500; 
    text-align: left; /* ✅ ZAROVNÁNÍ DOLEVA */
}

.saw-info-val a { color: var(--p307-accent); text-decoration: none; font-weight: 600; }
.saw-info-val a:hover { text-decoration: underline; }

/* STATUS BOX */
.saw-status-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 6px;
    border: 1px solid transparent;
    margin-bottom: 12px;
}
.st-icon { font-size: 18px; }
.st-content { flex: 1; }
.st-title { font-size: 11px; text-transform: uppercase; font-weight: 700; opacity: 0.8; margin-bottom: 2px; display: block; }
.st-value { font-size: 15px; font-weight: 700; display: block; }

.status-success { background: var(--st-success-bg); color: var(--st-success-text); border-color: var(--st-success-border); }
.status-warning { background: var(--st-warning-bg); color: var(--st-warning-text); border-color: var(--st-warning-border); }
.status-danger  { background: var(--st-danger-bg);  color: var(--st-danger-text);  border-color: var(--st-danger-border); }
.status-info    { background: var(--st-info-bg);    color: var(--st-info-text);    border-color: var(--st-info-border); }
.status-neutral { background: var(--st-neutral-bg); color: var(--st-neutral-text); border-color: var(--st-neutral-border); }

/* TABLES */
.saw-simple-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.saw-simple-table th { text-align: left; color: #888; font-weight: 700; padding: 8px; border-bottom: 2px solid #eee; font-size: 11px; text-transform: uppercase; }
.saw-simple-table td { padding: 8px; border-bottom: 1px solid #eee; color: #333; }

/* OTHERS */
.saw-host-mini { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.saw-progress-title { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #888; margin-bottom: 8px; margin-top: 16px; }
.saw-step-item { display: flex; align-items: center; gap: 10px; font-size: 13px; padding: 6px 0; border-bottom: 1px dashed #eee; }
.saw-cert-row { display: flex; gap: 12px; padding: 10px; background: #fafafa; border: 1px solid #eee; border-radius: 4px; margin-bottom: 8px; }
.saw-cert-title { font-weight: 700; font-size: 14px; color: var(--p307-primary); }
.saw-cert-sub { font-size: 12px; color: #666; }

</style>

<div class="saw-detail-wrapper">

    <div class="saw-industrial-header">
        <div class="saw-header-inner">
            <h3><?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?></h3>
            <div class="saw-header-meta">
                <?php if (!empty($item['position'])): ?>
                    <span>💼 <?php echo esc_html($item['position']); ?></span> • 
                <?php endif; ?>
                <span>🆔 <?php echo esc_html($item['id']); ?></span>
            </div>
        </div>
        <div class="saw-industrial-stripe"></div>
    </div>

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