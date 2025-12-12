<?php
/**
 * Email History Panel - Collapsible
 * 
 * Zobrazuje historii odeslaných emailů k návštěvě
 * a tlačítko pro odeslání výzvy k doplnění rizik.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits/Partials
 * @version     1.1.0
 * 
 * Očekávané proměnné:
 * - $item (array) - data návštěvy (včetně invitation_email)
 * - $tr (callable) - překladová funkce
 */

if (!defined('ABSPATH')) exit;

// Validace
if (empty($item['id'])) {
    return;
}

$visit_id = intval($item['id']);
$invitation_email = $item['invitation_email'] ?? '';

// Načtení historie emailů
$email_logs = array();
if (class_exists('SAW_Email_Logger')) {
    $logger = new SAW_Email_Logger();
    $email_logs = $logger->get_by_visit($visit_id);
}

// Počet emailů
$email_count = count($email_logs);

// Typy emailů - labely
$email_type_labels = array(
    'info_portal'       => $tr('email_type_info_portal', 'Info portál'),
    'invitation'        => $tr('email_type_invitation', 'Pozvánka'),
    'pin_reminder'      => $tr('email_type_pin_reminder', 'PIN připomenutí'),
    'host_notification' => $tr('email_type_host_notification', 'Notifikace hostiteli'),
    'risks_request'     => $tr('email_type_risks_request', 'Výzva k rizikům'),
    'password_reset'    => $tr('email_type_password_reset', 'Reset hesla'),
    'password_changed'  => $tr('email_type_password_changed', 'Heslo změněno'),
    'welcome'           => $tr('email_type_welcome', 'Uvítací email'),
);

// Status ikony
$status_icons = array(
    'sent'   => '✅',
    'failed' => '❌',
    'queued' => '⏳',
);

// Unique ID pro collapse
$collapse_id = 'email-history-' . $visit_id;
?>

<!-- ============================================ -->
<!-- EMAIL HISTORY - COLLAPSIBLE                  -->
<!-- ============================================ -->
<div class="saw-industrial-section saw-email-history-section">
    <div class="saw-section-head saw-section-head-collapsible" 
         onclick="toggleEmailHistory('<?php echo esc_js($collapse_id); ?>')"
         style="cursor: pointer;">
        <h4 class="saw-section-title saw-section-title-accent">
            📧 <?php echo esc_html($tr('section_email_history', 'Historie emailů')); ?>
            <?php if ($email_count > 0): ?>
            <span class="saw-badge saw-badge-info" style="margin-left: 8px; font-size: 11px;">
                <?php echo $email_count; ?>
            </span>
            <?php endif; ?>
        </h4>
        <span class="saw-collapse-icon" id="<?php echo esc_attr($collapse_id); ?>-icon">▼</span>
    </div>
    
    <div class="saw-section-body saw-collapsible-body" id="<?php echo esc_attr($collapse_id); ?>" style="display: none;">
        
        <!-- Akční tlačítka -->
        <?php if (!empty($invitation_email)): ?>
        <div class="saw-email-actions" style="margin-bottom: 16px;">
            <div class="saw-email-action-row" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div class="saw-email-recipient" style="display: flex; align-items: center; gap: 8px; flex: 1;">
                    <span style="font-size: 13px; color: #6b7280;">
                        <?php echo esc_html($tr('send_to', 'Odeslat na')); ?>:
                    </span>
                    <a href="mailto:<?php echo esc_attr($invitation_email); ?>" 
                       style="font-weight: 600; color: #2563eb; text-decoration: none;">
                        <?php echo esc_html($invitation_email); ?>
                    </a>
                </div>
                <button type="button" 
                        class="saw-btn saw-btn-warning saw-btn-sm"
                        onclick="sendRisksRequest(<?php echo $visit_id; ?>)"
                        id="send-risks-btn-<?php echo $visit_id; ?>"
                        title="<?php echo esc_attr($tr('send_risks_request_title', 'Odeslat email s výzvou k doplnění informací o rizicích')); ?>">
                    <span class="saw-btn-icon">⚠️</span>
                    <?php echo esc_html($tr('send_risks_request', 'Vyzvat k rizikům')); ?>
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="saw-email-actions" style="margin-bottom: 16px;">
            <p class="saw-text-muted" style="font-size: 13px; margin: 0; padding: 12px; background: #f9fafb; border-radius: 8px; color: #9ca3af;">
                ⚠️ <?php echo esc_html($tr('no_invitation_email', 'Návštěva nemá vyplněný email pro pozvánku. Nelze odeslat výzvu k rizikům.')); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Tabulka historie -->
        <?php if (empty($email_logs)): ?>
        <div class="saw-empty-state" style="padding: 24px; text-align: center; color: #9ca3af;">
            <span style="font-size: 32px; display: block; margin-bottom: 8px;">📭</span>
            <p style="margin: 0; font-size: 14px;">
                <?php echo esc_html($tr('no_emails_sent', 'Zatím nebyly odeslány žádné emaily.')); ?>
            </p>
        </div>
        <?php else: ?>
        <div class="saw-table-responsive" style="overflow-x: auto;">
            <table class="saw-table saw-table-compact saw-table-striped" style="width: 100%; font-size: 13px;">
                <thead>
                    <tr>
                        <th style="width: 130px;"><?php echo esc_html($tr('col_date', 'Datum')); ?></th>
                        <th style="width: 130px;"><?php echo esc_html($tr('col_type', 'Typ')); ?></th>
                        <th><?php echo esc_html($tr('col_recipient', 'Příjemce')); ?></th>
                        <th style="width: 80px; text-align: center;"><?php echo esc_html($tr('col_status', 'Stav')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($email_logs as $log): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <span style="color: #374151;">
                                <?php echo esc_html(date_i18n('d.m.Y', strtotime($log['created_at']))); ?>
                            </span>
                            <span style="color: #9ca3af; font-size: 12px;">
                                <?php echo esc_html(date_i18n('H:i', strtotime($log['created_at']))); ?>
                            </span>
                        </td>
                        <td>
                            <span class="saw-badge saw-badge-secondary" style="font-size: 11px;">
                                <?php echo esc_html($email_type_labels[$log['email_type']] ?? $log['email_type']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="mailto:<?php echo esc_attr($log['recipient_email']); ?>" 
                               style="color: #2563eb; text-decoration: none;">
                                <?php echo esc_html($log['recipient_email']); ?>
                            </a>
                            <?php if (!empty($log['recipient_name'])): ?>
                            <span style="color: #9ca3af; font-size: 12px;">
                                (<?php echo esc_html($log['recipient_name']); ?>)
                            </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php 
                            $status = $log['status'] ?? 'sent';
                            $icon = $status_icons[$status] ?? '❓';
                            $title = '';
                            if ($status === 'failed' && !empty($log['error_message'])) {
                                $title = $log['error_message'];
                            }
                            ?>
                            <span title="<?php echo esc_attr($title); ?>" style="cursor: <?php echo $title ? 'help' : 'default'; ?>;">
                                <?php echo $icon; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
.saw-email-history-section .saw-section-head-collapsible {
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    transition: background-color 0.2s;
}

.saw-email-history-section .saw-section-head-collapsible:hover {
    background-color: #f3f4f6;
}

.saw-collapse-icon {
    font-size: 12px;
    color: #6b7280;
    transition: transform 0.2s;
}

.saw-collapse-icon.expanded {
    transform: rotate(180deg);
}

.saw-email-actions {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.saw-table-compact th,
.saw-table-compact td {
    padding: 8px 12px;
}

.saw-table-compact th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.saw-table-compact td {
    border-bottom: 1px solid #f3f4f6;
}

.saw-table-striped tbody tr:nth-child(even) {
    background-color: #fafafa;
}

.saw-btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    font-family: inherit;
}

.saw-btn-warning:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.saw-btn-warning:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
</style>

<script>
function toggleEmailHistory(collapseId) {
    var body = document.getElementById(collapseId);
    var icon = document.getElementById(collapseId + '-icon');
    
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.classList.add('expanded');
    } else {
        body.style.display = 'none';
        icon.classList.remove('expanded');
    }
}

function sendRisksRequest(visitId) {
    var btn = document.getElementById('send-risks-btn-' + visitId);
    
    if (!btn) {
        console.error('Missing button element');
        return;
    }
    
    if (!confirm('<?php echo esc_js($tr('confirm_send_risks', 'Odeslat email s výzvou k doplnění rizik?')); ?>')) {
        return;
    }
    
    var originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="saw-btn-icon">⏳</span> <?php echo esc_js($tr('sending', 'Odesílám...')); ?>';
    
    jQuery.post(sawGlobal.ajaxurl, {
        action: 'saw_send_risks_request',
        visit_id: visitId,
        nonce: sawGlobal.nonce
    }, function(response) {
        if (response.success) {
            alert('✅ <?php echo esc_js($tr('alert_risks_sent', 'Email byl úspěšně odeslán')); ?>');
            location.reload();
        } else {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + (response.data?.message || '<?php echo esc_js($tr('unknown_error', 'Neznámá chyba')); ?>'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }).fail(function(xhr, status, error) {
        alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem: ' + error);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>