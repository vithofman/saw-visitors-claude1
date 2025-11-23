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
            
            <?php
            // PIN SEKCE
            include(dirname(__FILE__) . '/parts/pin-section.php');
            ?>
            
            <script>
            // PIN Management Functions
            function extendPinQuick(visitId, hours) {
                // Získat kartu s data-current-expiry
                const card = document.getElementById('pin-card-' + visitId);
                if (!card) {
                    alert('Chyba: Nenalezen PIN element');
                    return;
                }
                
                // Získat aktuální expiraci (timestamp v ms)
                let currentExpiry = parseInt(card.getAttribute('data-current-expiry'));
                let now = Date.now();
                let baseTime;
                
                // KLÍČOVÁ LOGIKA: Počítat OD EXPIRACE, ne od TEĎ
                if (currentExpiry && currentExpiry > now) {
                    // PIN je platný -> přičti k expiraci
                    baseTime = currentExpiry;
                } else {
                    // PIN vypršel -> přičti k TEĎ
                    baseTime = now;
                }
                
                // Vypočítat nový čas
                let newTimeMs = baseTime + (hours * 3600 * 1000);
                let newDate = new Date(newTimeMs);
                newDate.setSeconds(0, 0); // Zaokrouhlit na celé minuty
                
                // Formátovat pro zobrazení
                const displayDate = formatDateTime(newDate);
                
                if (!confirm(`Prodloužit platnost PIN o ${hours} hodin?\n\nNová expirace: ${displayDate}`)) {
                    return;
                }
                
                // Poslat PŘESNÝ čas na backend
                const sqlDate = formatSQLDateTime(newDate);
                extendPinToExactTime(visitId, sqlDate, newDate);
            }
            
            function formatDateTime(date) {
                const d = date.getDate().toString().padStart(2, '0');
                const m = (date.getMonth() + 1).toString().padStart(2, '0');
                const y = date.getFullYear();
                const h = date.getHours().toString().padStart(2, '0');
                const min = date.getMinutes().toString().padStart(2, '0');
                return `${d}.${m}.${y} ${h}:${min}`;
            }
            
            function formatSQLDateTime(date) {
                // ✅ OPRAVA: Převést na Prahu timezone
                // JavaScript Date je v lokálním čase, ale potřebujeme explicitní Prague čas pro backend
                
                const year = date.getFullYear();
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const day = date.getDate().toString().padStart(2, '0');
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                const seconds = date.getSeconds().toString().padStart(2, '0');
                
                // Vrátit přesný lokální čas (browser timezone = Prague)
                return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            }
            
            function extendPinToExactTime(visitId, sqlDate, dateObj) {
                // Poslat přesný čas v parametru exact_expiry
                // a hours=-1 jako flag pro backend že má použít exact_expiry
                jQuery.post(sawGlobal.ajaxurl, {
                    action: 'saw_extend_pin',
                    visit_id: visitId,
                    hours: 999, // Flag pro backend: použij exact_expiry
                    exact_expiry: sqlDate, // Přesný čas: "2025-11-24 10:37:00"
                    nonce: sawGlobal.nonce
                }, function(response) {
                    console.log('Server response:', response);
                    if (response.success) {
                        // Aktualizovat data atribut
                        const card = document.getElementById('pin-card-' + visitId);
                        if (card) {
                            card.setAttribute('data-current-expiry', dateObj.getTime());
                        }
                        
                        alert('✅ PIN prodloužen do: ' + formatDateTime(dateObj));
                        location.reload();
                    } else {
                        alert('❌ Chyba: ' + (response.data?.message || 'Neznámá chyba'));
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    alert('❌ Chyba komunikace se serverem\n\n' + error);
                });
            }

            function showExtendPinForm(visitId) {
                document.getElementById('pin-extend-buttons-' + visitId).style.display = 'none';
                const form = document.getElementById('pin-extend-form-' + visitId);
                form.style.display = 'block';
                
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
                
                // Parsovat datetime
                const newDate = new Date(datetimeValue);
                
                if (isNaN(newDate.getTime())) {
                    alert('Neplatné datum');
                    return;
                }
                
                const displayDate = formatDateTime(newDate);
                
                if (!confirm(`Nastavit expiraci PIN na:\n${displayDate}?`)) {
                    return;
                }
                
                // Poslat PŘESNÝ čas
                const sqlDate = formatSQLDateTime(newDate);
                extendPinToExactTime(visitId, sqlDate, newDate);
            }

            function generatePin(visitId) {
                if (!confirm('Vygenerovat PIN kód pro tuto návštěvu?\n\nPIN bude platný do posledního plánovaného dne návštěvy + 24 hodin.')) {
                    return;
                }
                
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
                            location.reload();
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
                }
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