<?php
/**
 * Visits Detail Sidebar Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     4.0.0 - REFACTORED: Uses saw-industrial-section structure like companies
 */

if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěva nebyla nalezena</div>';
    return;
}

// Header is now rendered by admin-table component (detail-sidebar.php)
// Module only provides content

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

// Get visitor count from item (loaded in format_detail_data)
$visitors_count = intval($item['visitor_count'] ?? 0);

// Determine if physical or legal person
$is_physical_person = empty($item['company_id']);
?>

<!-- Tracking Timeline -->
<?php if (!empty($item['started_at']) || !empty($item['completed_at'])): ?>
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">⏱️ Průběh návštěvy</h4>
    </div>
    <div class="saw-section-body">
        <div class="saw-tracking-timeline">
            <?php if (!empty($item['started_at'])): ?>
            <div class="saw-tracking-event">
                <div class="saw-tracking-icon saw-tracking-icon-start">
                    <span class="dashicons dashicons-arrow-down-alt"></span>
                </div>
                <div class="saw-tracking-content">
                    <strong>Zahájeno</strong>
                    <span class="saw-tracking-time">
                        <?php echo date_i18n('d.m.Y H:i', strtotime($item['started_at'])); ?>
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
                        <?php echo date_i18n('d.m.Y H:i', strtotime($item['completed_at'])); ?>
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
</div>
<?php endif; ?>

<!-- Scheduled Days -->
<?php if (!empty($schedules)): ?>
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">📅 Naplánované dny návštěvy</h4>
    </div>
    <div class="saw-section-body">
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
</div>
<?php endif; ?>

<!-- Visit Information -->
<div class="saw-industrial-section">
    <div class="saw-section-head">
        <h4 class="saw-section-title saw-section-title-accent">ℹ️ Informace o návštěvě</h4>
    </div>
    <div class="saw-section-body">
        <div class="saw-info-grid">
            <?php if (!empty($item['branch_name'])): ?>
            <div class="saw-info-item">
                <label>Pobočka</label>
                <span><?php echo esc_html($item['branch_name']); ?></span>
            </div>
            <?php endif; ?>
            
           <style>
/* --- VARIABLES & BASICS --- */
:root {
    --saw-primary: #4f46e5;       /* Indigo 600 */
    --saw-primary-light: #e0e7ff; /* Indigo 100 */
    --saw-success: #10b981;       /* Emerald 500 */
    --saw-warning: #f59e0b;       /* Amber 500 */
    --saw-danger: #ef4444;        /* Red 500 */
    --saw-text-main: #1e293b;     /* Slate 800 */
    --saw-text-muted: #64748b;    /* Slate 500 */
    --saw-bg-glass: rgba(255, 255, 255, 0.95);
}

/* --- MAIN CARD --- */
.saw-pin-modern-card {
    background: var(--saw-bg-glass);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 24px;
    padding: 32px;
    margin-top: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

/* Subtle top gradient line */
.saw-pin-modern-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
    opacity: 0.8;
}

/* --- PIN DISPLAY BOX --- */
.saw-pin-display-box {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    cursor: pointer;
    position: relative;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.01);
}

.saw-pin-display-box:active {
    transform: scale(0.98);
}

.saw-pin-left-section {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
    min-width: 0;
}

.saw-pin-icon-wrapper {
    width: 44px;
    height: 44px;
    background: var(--saw-primary-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--saw-primary);
    flex-shrink: 0;
}

.saw-pin-code-display {
    font-family: 'SF Mono', 'Menlo', 'Monaco', 'Courier New', monospace;
    font-size: 38px;
    font-weight: 800;
    color: var(--saw-text-main);
    letter-spacing: 0.12em;
    line-height: 1;
    white-space: nowrap;
}

/* --- COPY BADGE --- */
.saw-pin-copy-badge {
    padding: 8px 14px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 99px;
    color: var(--saw-text-muted);
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    white-space: nowrap;
    flex-shrink: 0;
}

.saw-pin-display-box:hover .saw-pin-copy-badge {
    background: var(--saw-primary);
    border-color: var(--saw-primary);
    color: white;
}

/* --- ANIMATIONS (COPIED STATE) --- */
.saw-pin-code-display.copied {
    color: var(--saw-success);
    font-family: sans-serif; /* Switch font for the checkmark symbol */
    font-size: 38px;
    letter-spacing: 0;
    animation: sawPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.saw-pin-copy-badge.copied {
    background: var(--saw-success) !important;
    border-color: var(--saw-success) !important;
    color: white !important;
}

@keyframes sawPop {
    0% { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

/* --- STATUS BAR --- */
.saw-pin-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    margin-bottom: 24px;
}

.saw-pin-status-info { display: flex; align-items: center; gap: 12px; }
.saw-status-dot { width: 10px; height: 10px; border-radius: 50%; }
.saw-status-dot.valid { background: var(--saw-success); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
.saw-status-dot.warning { background: var(--saw-warning); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15); }
.saw-status-dot.expired { background: var(--saw-danger); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15); }

.saw-pin-status-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--saw-text-muted); font-weight: 700; display:block; margin-bottom:2px;}
.saw-pin-status-value { font-size: 14px; font-weight: 600; color: var(--saw-text-main); }

/* --- ACTIONS GRID --- */
.saw-pin-buttons-wrapper {
    width: 100%;
    display: block;
}

.saw-pin-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    width: 100%; /* Important fix for shrinking */
    box-sizing: border-box;
}

.saw-pin-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
    color: var(--saw-text-muted);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%; /* Ensure full width in grid cell */
    box-sizing: border-box;
}

.saw-pin-action-btn:hover { border-color: var(--saw-primary); color: var(--saw-primary); transform: translateY(-2px); }
.saw-pin-action-btn.primary { background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); border: none; color: white; }
.saw-pin-action-btn.primary:hover { box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }

/* --- CUSTOM FORM --- */
.saw-pin-custom-form { 
    margin-top: 16px; 
    padding: 20px; 
    background: #fffbeb; 
    border: 1px solid #fcd34d; 
    border-radius: 16px;
    width: 100%;
    box-sizing: border-box;
    animation: sawFadeIn 0.3s ease;
}

@keyframes sawFadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

.saw-pin-datetime-input { width: 100%; padding: 12px; border: 2px solid #fbbf24; border-radius: 10px; margin-bottom: 12px; box-sizing: border-box; font-family: inherit; }

/* --- EMPTY STATE --- */
.saw-pin-empty-state { text-align: center; padding: 30px 20px; }
.saw-pin-empty-icon { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
.saw-pin-generate-btn { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; padding: 12px 24px; border-radius: 99px; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s; }
.saw-pin-generate-btn:hover { transform: scale(1.05); }

/* --- RESPONSIVE --- */
@media (max-width: 480px) {
    .saw-pin-modern-card { padding: 20px; }
    .saw-pin-code-display { font-size: 32px; letter-spacing: 0.1em; }
    .saw-pin-copy-badge span:first-child { display: none; } /* Hide icon in badge on small screens */
    .saw-pin-actions-grid { grid-template-columns: 1fr; } /* Stack buttons on mobile */
}
</style>

<?php if (!empty($item['pin_code'])): ?>
<div class="saw-info-item" style="grid-column: 1 / -1;">
    <label style="margin-bottom: 10px; display:block; font-weight:600; color:#475569;">🔐 PIN kód pro vstup</label>
    
    <div class="saw-pin-modern-card">
        
        <div class="saw-pin-display-box" onclick="copyPinToClipboard('<?php echo esc_js($item['pin_code']); ?>', <?php echo $item['id']; ?>)">
            <div class="saw-pin-left-section">
                <div class="saw-pin-icon-wrapper">
                    <span>🔒</span>
                </div>
                <div class="saw-pin-code-display" id="pin-code-<?php echo $item['id']; ?>">
                    <?php echo esc_html($item['pin_code']); ?>
                </div>
            </div>
            
            <div class="saw-pin-copy-badge" id="pin-badge-<?php echo $item['id']; ?>">
                <span>📋</span> 
                <span class="saw-badge-text">Kopírovat</span>
            </div>
        </div>
        
        <?php 
        // Expiry Calculation
        $pin_expiry_timestamp = null;
        $expiry_status_class = '';
        $expiry_text_main = 'Bez omezení';
        $expiry_text_sub = 'Trvalý přístup';
        $dot_class = 'valid';
        
        if (!empty($item['pin_expires_at'])) {
            $pin_expiry_timestamp = strtotime($item['pin_expires_at']);
            $now = time();
            
            if ($pin_expiry_timestamp < $now) {
                $expiry_text_main = 'Platnost vypršela';
                $hours_past = ceil(($now - $pin_expiry_timestamp) / 3600);
                $expiry_text_sub = "Před $hours_past hod";
                $dot_class = 'expired';
                $expiry_status_class = 'expired';
            } else {
                $hours_left = ceil(($pin_expiry_timestamp - $now) / 3600);
                $expiry_text_main = "Platný ještě {$hours_left}h";
                $expiry_text_sub = date('d.m.Y H:i', $pin_expiry_timestamp);
                $dot_class = ($hours_left < 6) ? 'warning' : 'valid';
                $expiry_status_class = ($hours_left < 6) ? 'warning' : 'valid';
            }
        }
        ?>
        
        <div class="saw-pin-status-bar">
            <div class="saw-pin-status-info">
                <div class="saw-status-dot <?php echo $dot_class; ?>"></div>
                <div class="saw-pin-status-text">
                    <span class="saw-pin-status-title">Stav</span>
                    <span class="saw-pin-status-value <?php echo $expiry_status_class; ?>">
                        <?php echo $expiry_text_main; ?>
                    </span>
                </div>
            </div>
            <div class="saw-pin-status-text" style="text-align: right;">
                <span class="saw-pin-status-title">Expirace</span>
                <span style="font-size: 13px; color: #64748b; font-weight: 500;">
                    <?php echo $expiry_text_sub; ?>
                </span>
            </div>
        </div>

        <?php if ($item['status'] !== 'cancelled'): ?>
        
        <div id="pin-extend-buttons-<?php echo $item['id']; ?>" class="saw-pin-buttons-wrapper">
            <div class="saw-pin-actions-grid">
                <button type="button" class="saw-pin-action-btn" onclick="extendPinQuick(<?php echo $item['id']; ?>, 24)">
                    <span>🔄</span> +24h
                </button>
                <button type="button" class="saw-pin-action-btn" onclick="extendPinQuick(<?php echo $item['id']; ?>, 48)">
                    <span>⏱️</span> +48h
                </button>
                <button type="button" class="saw-pin-action-btn" onclick="extendPinQuick(<?php echo $item['id']; ?>, 168)">
                    <span>📅</span> +7 dní
                </button>
                <button type="button" class="saw-pin-action-btn primary" onclick="showExtendPinForm(<?php echo $item['id']; ?>)">
                    <span>⚙️</span> Ručně
                </button>
            </div>
        </div>

        <div id="pin-extend-form-<?php echo $item['id']; ?>" style="display: none; width: 100%;">
            <div class="saw-pin-custom-form">
                <div style="font-weight:700; color:#92400e; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                    <span>📆</span> Změnit platnost
                </div>
                
                <input type="datetime-local" 
                       id="pin-expiry-datetime-<?php echo $item['id']; ?>" 
                       class="saw-pin-datetime-input"
                       min="<?php echo date('Y-m-d\TH:i'); ?>"
                       value="<?php echo $pin_expiry_timestamp ? date('Y-m-d\TH:i', $pin_expiry_timestamp) : date('Y-m-d\TH:i', strtotime('+24 hours')); ?>">
                
                <div class="saw-pin-actions-grid">
                    <button type="button" class="saw-pin-action-btn" style="background:#10b981; color:white; border:none;" onclick="extendPinCustom(<?php echo $item['id']; ?>)">
                        <span>✅</span> Uložit
                    </button>
                    <button type="button" class="saw-pin-action-btn" onclick="hideExtendPinForm(<?php echo $item['id']; ?>)">
                        <span>❌</span> Zpět
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<?php if ($item['status'] !== 'cancelled' && ($item['visit_type'] ?? '') === 'planned'): ?>
<div class="saw-info-item" style="grid-column: 1 / -1;">
    <div class="saw-pin-modern-card">
        <div class="saw-pin-empty-state">
            <div class="saw-pin-empty-icon">🔓</div>
            <p style="margin:0 0 20px 0; color:#94a3b8; font-size:14px;">
                PIN kód nebyl vygenerován.
            </p>
            <button type="button" class="saw-pin-generate-btn" onclick="generatePin(<?php echo $item['id']; ?>)">
                <span>✨</span> Vygenerovat PIN
            </button>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
/**
 * Smart Copy Function - Layout Safe
 */
function copyPinToClipboard(pin, visitId) {
    if (!pin) return;
    
    const displayEl = document.getElementById('pin-code-' + visitId);
    const badgeEl = document.getElementById('pin-badge-' + visitId);
    const badgeTextEl = badgeEl.querySelector('.saw-badge-text') || badgeEl.querySelector('span:last-child');
    
    const originalText = displayEl.innerText;
    const originalBadgeText = badgeTextEl.innerText;
    
    navigator.clipboard.writeText(pin).then(() => {
        // 1. Change PIN to Checkmark to avoid layout shift
        displayEl.innerText = "✓";
        displayEl.classList.add('copied');
        
        // 2. Change Badge to "Copied"
        badgeEl.classList.add('copied');
        badgeTextEl.innerText = "Zkopírováno";
        
        // Mobile vibration
        if (window.navigator && window.navigator.vibrate) {
            window.navigator.vibrate(50);
        }
        
        // Reset
        setTimeout(() => {
            displayEl.classList.remove('copied');
            displayEl.innerText = originalText;
            
            badgeEl.classList.remove('copied');
            badgeTextEl.innerText = originalBadgeText;
        }, 1500);
    }).catch(err => {
        console.error('Copy failed:', err);
    });
}

/**
 * Form Toggling with Width Fix
 */
function showExtendPinForm(id) {
    document.getElementById('pin-extend-buttons-' + id).style.display = 'none';
    document.getElementById('pin-extend-form-' + id).style.display = 'block';
}

function hideExtendPinForm(id) {
    document.getElementById('pin-extend-form-' + id).style.display = 'none';
    
    var btnContainer = document.getElementById('pin-extend-buttons-' + id);
    btnContainer.style.display = 'block';
    // Force width recalculation for grid
    btnContainer.style.width = '100%'; 
}
</script>
            
            <div class="saw-info-item">
                <label>Návštěvník</label>
                <span>
                    <?php if ($is_physical_person): ?>
                        <?php if (!empty($item['first_visitor_name'])): ?>
                            <strong style="color: #6366f1;"><?php echo esc_html($item['first_visitor_name']); ?></strong>
                            <span class="saw-badge" style="background: #6366f1; color: white; font-size: 11px; margin-left: 8px;">👤 Fyzická</span>
                        <?php else: ?>
                            <span style="color: #6366f1; font-weight: 600;">👤 Fyzická osoba</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <strong><?php echo esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']); ?></strong>
                        <span class="saw-badge saw-badge-info" style="font-size: 11px; margin-left: 8px;">🏢 Firma</span>
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if ($visitors_count > 0): ?>
            <div class="saw-info-item">
                <label>Počet návštěvníků</label>
                <span>
                    <span class="saw-badge saw-badge-info">
                        👥 <?php echo $visitors_count; ?> 
                        <?php echo $visitors_count === 1 ? 'osoba' : ($visitors_count < 5 ? 'osoby' : 'osob'); ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['invitation_email'])): ?>
            <div class="saw-info-item">
                <label>Email pro pozvánku</label>
                <span>
                    <a href="mailto:<?php echo esc_attr($item['invitation_email']); ?>" class="saw-link">
                        <?php echo esc_html($item['invitation_email']); ?>
                    </a>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['purpose'])): ?>
            <div class="saw-info-item">
                <label>Účel návštěvy</label>
                <span><?php echo nl2br(esc_html($item['purpose'])); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['notes'])): ?>
            <div class="saw-info-item">
                <label>Poznámky</label>
                <span><?php echo nl2br(esc_html($item['notes'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['hosts'])): ?>
        <div style="margin-top: 16px;">
            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #334155;">Koho navštěvují</label>
            <div class="saw-hosts-list">
                <?php foreach ($item['hosts'] as $host): ?>
                    <div class="saw-host-card">
                        <span class="dashicons dashicons-businessman"></span>
                        <div class="saw-host-info">
                            <strong><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></strong>
                            <?php if (!empty($host['role'])): ?>
                                <span class="saw-host-email"><?php echo esc_html($host['role']); ?></span>
                            <?php elseif (!empty($host['email'])): ?>
                                <span class="saw-host-email"><?php echo esc_html($host['email']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Visitors section will be rendered by related_data via detail-sidebar.php -->

<style>
/* Tracking Timeline */
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
    font-family: dashicons !important;
    color: white !important;
    font-size: 24px !important;
    width: 24px !important;
    height: 24px !important;
    line-height: 24px !important;
    display: inline-block !important;
    speak: none !important;
    font-style: normal !important;
    font-weight: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
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
    font-family: dashicons !important;
    color: #2271b1 !important;
    font-size: 18px !important;
    width: 18px !important;
    height: 18px !important;
    line-height: 18px !important;
    display: inline-block !important;
    speak: none !important;
    font-style: normal !important;
    font-weight: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
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
    display: inline-block !important;
    flex-shrink: 0;
    margin-top: 2px;
    speak: none !important;
    font-style: normal !important;
    font-weight: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
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
    font-family: dashicons !important;
    color: #2271b1 !important;
    font-size: 24px !important;
    width: 24px !important;
    height: 24px !important;
    line-height: 24px !important;
    display: inline-block !important;
    speak: none !important;
    font-style: normal !important;
    font-weight: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
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

<script>
function generatePin(visitId) {
    if (!confirm('Vygenerovat PIN kód pro tuto návštěvu?\n\nPIN bude platný do posledního plánovaného dne návštěvy + 24 hodin.')) {
        return;
    }
    
    // Find button and show loading state
    const buttons = document.querySelectorAll(`button[onclick*="generatePin(${visitId})"]`);
    let button = buttons.length > 0 ? buttons[0] : event?.target;
    
    if (button) {
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳ Generování...';
        
        jQuery.post(sawGlobal.ajaxurl, {
            action: 'saw_generate_pin',
            visit_id: visitId,
            nonce: sawGlobal.nonce
        }, function(response) {
            console.log('[Generate PIN] Response:', response);
            if (response && response.success) {
                alert('✅ PIN úspěšně vygenerován: ' + (response.data.pin_code || 'N/A') + '\n\nPlatnost: ' + (response.data.pin_expires_at || 'N/A'));
                location.reload(); // Reload to show PIN
            } else {
                const msg = (response && response.data && response.data.message) ? response.data.message : 'Neznámá chyba';
                alert('❌ Chyba: ' + msg);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }).fail(function(xhr, status, error) {
            console.error('[Generate PIN] AJAX Error:', status, error, xhr.responseText);
            alert('❌ Chyba komunikace se serverem: ' + error);
            button.disabled = false;
            button.innerHTML = originalText;
        });
    } else {
        // Fallback if button not found
        jQuery.post(sawGlobal.ajaxurl, {
            action: 'saw_generate_pin',
            visit_id: visitId,
            nonce: sawGlobal.nonce
        }, function(response) {
            console.log('[Generate PIN] Response:', response);
            if (response && response.success) {
                alert('✅ PIN úspěšně vygenerován: ' + (response.data.pin_code || 'N/A') + '\n\nPlatnost: ' + (response.data.pin_expires_at || 'N/A'));
                location.reload();
            } else {
                const msg = (response && response.data && response.data.message) ? response.data.message : 'Neznámá chyba';
                alert('❌ Chyba: ' + msg);
            }
        }).fail(function(xhr, status, error) {
            console.error('[Generate PIN] AJAX Error:', status, error, xhr.responseText);
            alert('❌ Chyba komunikace se serverem: ' + error);
        });
    }
}

function extendPinQuick(visitId, hours) {
    if (!confirm(`Prodloužit platnost PIN o ${hours} hodin?`)) {
        return;
    }
    
    extendPin(visitId, hours);
}

function showExtendPinForm(visitId) {
    document.getElementById('pin-extend-buttons-' + visitId).style.display = 'none';
    const form = document.getElementById('pin-extend-form-' + visitId);
    form.style.display = 'block';
    
    // Set min attribute to current time
    const datetimeInput = document.getElementById('pin-expiry-datetime-' + visitId);
    datetimeInput.min = new Date().toISOString().slice(0, 16);
}

function hideExtendPinForm(visitId) {
    document.getElementById('pin-extend-form-' + visitId).style.display = 'none';
    document.getElementById('pin-extend-buttons-' + visitId).style.display = 'flex';
}

function extendPinCustom(visitId) {
    const datetimeInput = document.getElementById('pin-expiry-datetime-' + visitId);
    const datetimeValue = datetimeInput.value;
    
    if (!datetimeValue) {
        alert('Prosím zadejte datum a čas.');
        return;
    }
    
    // Convert datetime-local to timestamp and calculate hours difference
    const expiryTime = new Date(datetimeValue).getTime();
    const now = new Date().getTime();
    const hoursDiff = Math.ceil((expiryTime - now) / (1000 * 60 * 60));
    
    if (hoursDiff < 1 || hoursDiff > 720) {
        alert('Neplatná hodnota. Platnost musí být 1-720 hodin od nynějška.');
        return;
    }
    
    if (!confirm(`Prodloužit platnost PIN do ${datetimeValue}?\n(${hoursDiff} hodin)`)) {
        return;
    }
    
    extendPin(visitId, hoursDiff);
}

function extendPin(visitId, hours) {
    jQuery.post(sawGlobal.ajaxurl, {
        action: 'saw_extend_pin',
        visit_id: visitId,
        hours: hours,
        nonce: sawGlobal.nonce
    }, function(response) {
        if (response.success) {
            alert('✅ PIN prodloužen do: ' + response.data.new_expiry);
            hideExtendPinForm(visitId);
            location.reload();
        } else {
            alert('❌ Chyba: ' + response.data.message);
        }
    }).fail(function() {
        alert('❌ Chyba komunikace se serverem');
    });
}
</script>
