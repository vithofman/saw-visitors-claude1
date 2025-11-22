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
            
            <!-- PIN Section -->
            <?php if (!empty($item['pin_code'])): ?>
            <!-- PIN EXISTS -->
            <div class="saw-info-item" style="grid-column: 1 / -1;">
                <label>PIN kód</label>
                <span>
                    <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 8px;">
                        <span class="dashicons dashicons-lock" style="color: #0ea5e9;"></span>
                        <span style="font-size: 24px; font-weight: 700; color: #0369a1; letter-spacing: 3px; font-family: monospace;">
                            <?php echo esc_html($item['pin_code']); ?>
                        </span>
                    </div>
                </span>
            </div>
            
            <?php 
            // Determine expiry - either from DB or calculate from schedules
            $pin_expiry_display = null;
            $pin_expiry_timestamp = null;
            
            if (!empty($item['pin_expires_at'])) {
                $pin_expiry_timestamp = strtotime($item['pin_expires_at']);
            } else {
                // If not in DB, calculate from last schedule date
                global $wpdb;
                $last_schedule_date = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(date) FROM {$wpdb->prefix}saw_visit_schedules WHERE visit_id = %d",
                    $item['id']
                ));
                
                if ($last_schedule_date) {
                    $pin_expiry_timestamp = strtotime($last_schedule_date . ' +1 day 23:59:59');
                }
            }
            ?>
            
            <?php if ($pin_expiry_timestamp): ?>
            <div class="saw-info-item" style="grid-column: 1 / -1;">
                <label>Platnost PIN</label>
                <span>
                    <?php 
                    $expires = $pin_expiry_timestamp;
                    $now = time();
                    
                    if ($expires < $now) {
                        // Expired
                        $hours_ago = ceil(($now - $expires) / 3600);
                        echo '<span style="color: #dc2626; font-weight: 600;">
                            ❌ Vypršel před ' . $hours_ago . 'h
                        </span><br>';
                        echo '<small style="color: #6b7280;">' . 
                             date('d.m.Y H:i', $expires) . 
                             '</small>';
                    } else {
                        // Valid
                        $hours_left = ceil(($expires - $now) / 3600);
                        $color = $hours_left < 6 ? '#f59e0b' : '#16a34a';
                        echo '<span style="color: ' . $color . '; font-weight: 600;">
                            ✅ Platný ještě ' . $hours_left . 'h
                        </span><br>';
                        echo '<small style="color: #6b7280;">' . 
                             date('d.m.Y H:i', $expires) . 
                             '</small>';
                    }
                    ?>
                </span>
            </div>
            <?php else: ?>
            <div class="saw-info-item" style="grid-column: 1 / -1;">
                <label>Platnost PIN</label>
                <span style="color: #9ca3af;">Bez omezení</span>
            </div>
            <?php endif; ?>
            
            <?php if ($item['status'] !== 'cancelled'): ?>
            <div class="saw-info-item" style="grid-column: 1 / -1; margin-top: 8px;">
                <div id="pin-extend-buttons-<?php echo $item['id']; ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button type="button" 
                            class="saw-button saw-button-secondary"
                            onclick="extendPinQuick(<?php echo $item['id']; ?>, 24)">
                        🔄 Prodloužit o 24h
                    </button>
                    
                    <button type="button" 
                            class="saw-button saw-button-secondary"
                            onclick="extendPinQuick(<?php echo $item['id']; ?>, 48)">
                        ⏱️ Prodloužit o 48h
                    </button>
                    
                    <button type="button" 
                            class="saw-button saw-button-secondary"
                            onclick="showExtendPinForm(<?php echo $item['id']; ?>)">
                        ⚙️ Vlastní datum...
                    </button>
                </div>
                
                <!-- Custom date form (hidden by default) -->
                <div id="pin-extend-form-<?php echo $item['id']; ?>" style="display: none; margin-top: 12px; padding: 16px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">Nová platnost PIN</label>
                    <input type="datetime-local" 
                           id="pin-expiry-datetime-<?php echo $item['id']; ?>" 
                           class="saw-input"
                           style="width: 100%; margin-bottom: 8px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"
                           min="<?php echo date('Y-m-d\TH:i'); ?>"
                           value="<?php echo $pin_expiry_timestamp ? date('Y-m-d\TH:i', $pin_expiry_timestamp) : date('Y-m-d\TH:i', strtotime('+24 hours')); ?>">
                    <div style="display: flex; gap: 8px;">
                        <button type="button" 
                                class="saw-button saw-button-primary"
                                onclick="extendPinCustom(<?php echo $item['id']; ?>)">
                            ✅ Uložit
                        </button>
                        <button type="button" 
                                class="saw-button saw-button-secondary"
                                onclick="hideExtendPinForm(<?php echo $item['id']; ?>)">
                            ❌ Zrušit
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>  <!-- konec if ($item['status'] !== 'cancelled') -->
            
            <?php else: ?>
            <!-- PIN DOES NOT EXIST -->
            <?php if ($item['status'] !== 'cancelled' && ($item['visit_type'] ?? '') === 'planned'): ?>
            <div class="saw-info-item" style="grid-column: 1 / -1;">
                <label>PIN kód</label>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <span style="color: #9ca3af;">PIN nebyl vygenerován</span>
                    <button type="button" 
                            class="saw-button saw-button-primary"
                            style="align-self: flex-start;"
                            onclick="generatePin(<?php echo $item['id']; ?>)">
                        🔐 Vygenerovat PIN
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>  <!-- konec if (!empty($item['pin_code'])) -->
            
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
            if (response.success) {
                alert('✅ PIN úspěšně vygenerován: ' + response.data.pin_code + '\n\nPlatnost: ' + response.data.pin_expires_at);
                location.reload(); // Reload to show PIN
            } else {
                alert('❌ Chyba: ' + response.data.message);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }).fail(function() {
            alert('❌ Chyba komunikace se serverem');
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
            if (response.success) {
                alert('✅ PIN úspěšně vygenerován: ' + response.data.pin_code + '\n\nPlatnost: ' + response.data.pin_expires_at);
                location.reload();
            } else {
                alert('❌ Chyba: ' + response.data.message);
            }
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
