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
         
            <!-- ================================================
     VISITOR INFO CARD - Full Width
     ================================================ -->
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
                        <span>Návštěvník</span>
                        <span class="saw-visitor-type-badge <?php echo $is_physical_person ? 'saw-type-person' : 'saw-type-company'; ?>">
                            <?php echo $is_physical_person ? 'Fyzická osoba' : 'Firma'; ?>
                        </span>
                    </div>
                    <div class="saw-visitor-card-name">
                        <?php if ($is_physical_person): ?>
                            <?php echo esc_html($item['first_visitor_name'] ?? 'Neuvedeno'); ?>
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
                    <span class="saw-visitor-count-label"><?php echo $visitors_count === 1 ? 'osoba' : ($visitors_count < 5 ? 'osoby' : 'osob'); ?></span>
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
                        <span>Emailová pozvánka</span>
                        <?php if ($invitation_confirmed): ?>
                            <span class="saw-invitation-status saw-invitation-status-confirmed">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                Potvrzeno
                            </span>
                        <?php elseif ($invitation_sent): ?>
                            <span class="saw-invitation-status saw-invitation-status-sent">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                                Odesláno
                            </span>
                        <?php else: ?>
                            <span class="saw-invitation-status saw-invitation-status-pending">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                Čeká
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="mailto:<?php echo esc_attr($item['invitation_email']); ?>" class="saw-invitation-card-email">
                        <?php echo esc_html($item['invitation_email']); ?>
                    </a>
                    <?php if ($invitation_sent): ?>
                    <div class="saw-invitation-sent-info">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        Odesláno: <?php echo date_i18n('d.m.Y H:i', strtotime($item['invitation_sent_at'])); ?>
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
                        <?php echo $invitation_sent ? 'Odeslat znovu' : 'Odeslat pozvánku'; ?>
                    </button>
                <?php else: ?>
                    <div class="saw-invitation-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                        </svg>
                        Pouze u čekajících
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
                <div class="saw-purpose-label">Účel návštěvy</div>
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
                <div class="saw-notes-label">Poznámky</div>
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
                    <span class="saw-hosts-label">Koho navštěvují</span>
                    <span class="saw-hosts-count"><?php echo count($item['hosts']); ?> <?php echo count($item['hosts']) === 1 ? 'osoba' : (count($item['hosts']) < 5 ? 'osoby' : 'osob'); ?></span>
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

</div>
        
        
    </div>
</div>

<!-- Visitors section will be rendered by related_data via detail-sidebar.php -->

<style>

/* ================================================
   FIX: Cards stack full width
   ================================================ */
.saw-detail-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 16px 0;
    width: 100%;
    max-width: 100%;
}

/* Zajistit že parent container neomezuje šířku */
.saw-info-grid .saw-detail-cards-stack {
    grid-column: 1 / -1;
    width: 100%;
}

/* Každá karta na 100% */
.saw-visitor-card,
.saw-invitation-card,
.saw-purpose-card,
.saw-notes-card,
.saw-hosts-card {
    width: 100%;
    box-sizing: border-box;
}

/* ================================================
   DETAIL CARDS STACK - Vertical Layout
   ================================================ */
.saw-detail-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 16px 0;
}

/* ================================================
   VISITOR CARD - Horizontal Layout
   ================================================ */
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
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 10px;
    border: 1px solid #bae6fd;
}

.saw-visitor-count-box svg {
    color: #0284c7;
    flex-shrink: 0;
}

.saw-visitor-count-number {
    font-size: 24px;
    font-weight: 800;
    color: #0369a1;
    line-height: 1;
}

.saw-visitor-count-label {
    font-size: 13px;
    font-weight: 600;
    color: #0284c7;
}

/* ================================================
   INVITATION CARD - Horizontal Layout
   ================================================ */
.saw-invitation-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
}

.saw-invitation-icon {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
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
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
}

.saw-invitation-status svg {
    flex-shrink: 0;
}

.saw-invitation-status-confirmed {
    background: #dcfce7;
    color: #15803d;
}

.saw-invitation-status-sent {
    background: #dbeafe;
    color: #1d4ed8;
}

.saw-invitation-status-pending {
    background: #fef3c7;
    color: #b45309;
}

.saw-invitation-card-email {
    font-size: 15px;
    font-weight: 600;
    color: #667eea;
    text-decoration: none;
    transition: color 0.2s;
    overflow: hidden;
    text-overflow: ellipsis;
}

.saw-invitation-card-email:hover {
    color: #764ba2;
    text-decoration: underline;
}

.saw-invitation-sent-info {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

.saw-invitation-sent-info svg {
    color: #94a3b8;
    flex-shrink: 0;
}

.saw-invitation-card-right {
    flex-shrink: 0;
}

.saw-invitation-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.saw-invitation-btn svg {
    flex-shrink: 0;
}

.saw-invitation-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
}

.saw-invitation-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.45);
}

.saw-invitation-btn-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 2px solid #e2e8f0;
}

.saw-invitation-btn-secondary:hover {
    background: #e2e8f0;
    border-color: #cbd5e1;
}

.saw-invitation-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.saw-invitation-note {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
}

.saw-invitation-note svg {
    flex-shrink: 0;
    color: #94a3b8;
}

/* ================================================
   PURPOSE CARD
   ================================================ */
.saw-purpose-card {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 14px;
    padding: 2px;
}

.saw-purpose-card-inner {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.saw-purpose-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
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
    font-size: 15px;
    color: #1e293b;
    line-height: 1.6;
}

/* ================================================
   NOTES CARD
   ================================================ */
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
    align-items: flex-start;
    gap: 14px;
}

.saw-notes-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
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
    font-size: 15px;
    color: #1e293b;
    line-height: 1.6;
}

/* ================================================
   HOSTS CARD
   ================================================ */
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
    gap: 14px;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-hosts-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
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
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
}

.saw-hosts-card-body {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.saw-host-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.saw-host-item:hover {
    background: #f1f5f9;
}

.saw-host-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
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

/* ================================================
   RESPONSIVE - Mobile
   ================================================ */
@media (max-width: 600px) {
    .saw-visitor-card-inner,
    .saw-invitation-card-inner {
        flex-direction: column;
        align-items: stretch;
    }
    
    .saw-visitor-card-left,
    .saw-invitation-card-left {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    
    .saw-visitor-card-right,
    .saw-invitation-card-right {
        width: 100%;
    }
    
    .saw-visitor-count-box {
        justify-content: center;
        width: 100%;
    }
    
    .saw-invitation-btn {
        width: 100%;
        justify-content: center;
    }
    
    .saw-invitation-note {
        justify-content: center;
        width: 100%;
    }
}

/* ================================================
   TRACKING TIMELINE (keep existing)
   ================================================ */
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

/* ================================================
   SCHEDULE DAY CARDS (keep existing)
   ================================================ */
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

/* ================================================
   HOSTS LIST (keep existing)
   ================================================ */
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

/* ================================================
   RISKS / MATERIALS SECTION (keep existing)
   ================================================ */
.saw-risks-section {
    margin-top: 2rem;
}

.saw-empty-state {
    color: #6b7280;
    font-style: italic;
    padding: 1rem;
    text-align: center;
    background: #f9fafb;
    border-radius: 8px;
}

.saw-risk-text-block {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.saw-risk-text-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.saw-risk-text-header h4 {
    margin: 0;
    font-size: 1rem;
    color: #111827;
}

.saw-timestamp {
    font-size: 0.875rem;
    color: #6b7280;
}

.saw-risk-text-content {
    max-height: 300px;
    overflow-y: auto;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 6px;
    line-height: 1.6;
    color: #374151;
}

.saw-btn-fullscreen {
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.saw-btn-fullscreen:hover {
    background: #5568d3;
}

.saw-risk-documents {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
}

.saw-risk-documents h4 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: #111827;
}

.saw-documents-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.saw-document-item {
    border-bottom: 1px solid #e5e7eb;
}

.saw-document-item:last-child {
    border-bottom: none;
}

.saw-doc-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    text-decoration: none;
    color: inherit;
    transition: background 0.2s;
}

.saw-doc-link:hover {
    background: #f9fafb;
}

.saw-doc-icon {
    font-size: 1.5rem;
}

.saw-doc-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.saw-doc-info strong {
    color: #111827;
    font-weight: 600;
}

.saw-doc-info small {
    color: #6b7280;
    font-size: 0.8125rem;
}

/* Spinner animation */
@keyframes saw-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.saw-invitation-btn .saw-spinner {
    animation: saw-spin 1s linear infinite;
}

/* ================================================
   RISKS CARD
   ================================================ */
.saw-risks-card {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-radius: 14px;
    padding: 2px;
    margin: 20px;
}

.saw-risks-card-inner {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
}

.saw-risks-card-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
}

.saw-risks-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-risks-card-title {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.saw-risks-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-risks-subtitle {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-risks-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 20px;
    background: #dcfce7;
    color: #15803d;
    flex-shrink: 0;
}

.saw-risks-badge svg {
    flex-shrink: 0;
}

.saw-risks-badge-empty {
    background: #fef3c7;
    color: #b45309;
}

.saw-risks-card-body {
    padding: 16px;
}

.saw-risks-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 32px 16px;
    text-align: center;
    color: #94a3b8;
    font-size: 14px;
}

/* Text Items */
.saw-risks-text-item {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 12px;
}

.saw-risks-text-item:last-child {
    margin-bottom: 0;
}

.saw-risks-text-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #fecaca;
}

.saw-risks-text-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
    border-radius: 8px;
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
    min-width: 0;
}

.saw-risks-text-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-risks-text-date {
    font-size: 12px;
    color: #64748b;
}

.saw-risks-fullscreen-btn {
    width: 36px;
    height: 36px;
    background: #f1f5f9;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.saw-risks-fullscreen-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.saw-risks-text-content {
    padding: 16px;
    font-size: 14px;
    line-height: 1.7;
    color: #374151;
    max-height: 200px;
    overflow-y: auto;
}

/* Documents Section */
.saw-risks-documents-section {
    margin-top: 12px;
}

.saw-risks-documents-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-risks-documents-header svg {
    color: #94a3b8;
}

.saw-risks-documents-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-risks-document-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.saw-risks-document-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateX(4px);
}

.saw-risks-document-icon {
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

.saw-risks-document-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.saw-risks-document-name {
    font-size: 14px;
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
    font-size: 12px;
    color: #64748b;
}

.saw-risks-document-ext {
    background: #ef4444;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
}

.saw-risks-document-download {
    width: 36px;
    height: 36px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.saw-risks-document-item:hover .saw-risks-document-download {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

/* Mobile */
@media (max-width: 600px) {
    .saw-risks-card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .saw-risks-badge {
        margin-top: 8px;
    }
    
    .saw-risks-document-item {
        flex-wrap: wrap;
    }
    
    .saw-risks-document-download {
        margin-left: auto;
    }
}


</style>
<?php
// Load invitation materials
$materials = [];
if (!empty($item['id'])) {
    global $wpdb;
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

<!-- RISKS CARD -->
<?php if (!empty($materials) || !empty($item['invitation_token'])): ?>
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
                <span class="saw-risks-label">Informace o rizicích</span>
                <span class="saw-risks-subtitle">Nahrál pozvaný návštěvník</span>
            </div>
            <?php if (!empty($materials)): ?>
            <div class="saw-risks-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Nahráno
            </div>
            <?php else: ?>
            <div class="saw-risks-badge saw-risks-badge-empty">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
                Čeká na nahrání
            </div>
            <?php endif; ?>
        </div>
        
        <div class="saw-risks-card-body">
            <?php if (empty($materials)): ?>
                <div class="saw-risks-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="12" y1="18" x2="12" y2="12"/>
                        <line x1="9" y1="15" x2="15" y2="15"/>
                    </svg>
                    <span>Pozvaný zatím nenahrál žádné materiály o rizicích</span>
                </div>
            <?php else: ?>
                
                <?php if (!empty($texts)): ?>
                    <?php foreach ($texts as $text): ?>
                    <div class="saw-risks-text-item">
                        <div class="saw-risks-text-header">
                            <div class="saw-risks-text-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <div class="saw-risks-text-meta">
                                <span class="saw-risks-text-title">Textové informace</span>
                                <span class="saw-risks-text-date"><?php echo esc_html(date('d.m.Y H:i', strtotime($text['uploaded_at']))); ?></span>
                            </div>
                            <button class="saw-risks-fullscreen-btn" onclick="openFullscreen(<?php echo $text['id']; ?>)" title="Zobrazit na celou obrazovku">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                        <span>Dokumenty (<?php echo count($documents); ?>)</span>
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
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <path d="M9 15h6"/>
                                </svg>
                                <?php elseif (in_array($file_ext, ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'])): ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <?php else: ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                                    <span><?php echo esc_html(date('d.m.Y', strtotime($doc['uploaded_at']))); ?></span>
                                </span>
                            </div>
                            <div class="saw-risks-document-download">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
<script>
function openFullscreen(materialId) {
    // TODO: Implementovat fullscreen modal
    alert('Fullscreen zobrazení pro material #' + materialId);
}

// Send invitation email
function sendInvitation(visitId) {
    const btn = document.getElementById('send-invitation-btn-' + visitId);
    if (!btn) return;
    
    // Disable button and show loading
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Odesílám...';
    
    jQuery.post(sawGlobal.ajaxurl, {
        action: 'saw_send_invitation',
        visit_id: visitId,
        nonce: sawGlobal.nonce
    }, function(response) {
        if (response.success) {
            alert('✅ Pozvánka byla úspěšně odeslána na email: ' + (response.data?.email || ''));
            location.reload(); // Reload to show updated invitation_sent_at
        } else {
            alert('❌ Chyba: ' + (response.data?.message || 'Nepodařilo se odeslat pozvánku'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX Error:', xhr.responseText);
        alert('❌ Chyba komunikace se serverem\n\n' + error);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// CSS for spinner animation
if (!document.getElementById('saw-invitation-spinner-style')) {
    const style = document.createElement('style');
    style.id = 'saw-invitation-spinner-style';
    style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
}
</script>