<?php
/**
 * PIN & Token Section - Compact Collapsible Design
 * 
 * Compact collapsible section for access credentials (PIN code and invitation link).
 * Shows aggregated status in collapsed state, full details when expanded.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits/Parts
 * @version     6.0.0 - Compact collapsible design
 */

if (!defined('ABSPATH')) exit;

// Load translations (inherited from parent template, but ensure available)
if (!isset($tr) || !is_callable($tr)) {
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
}

// ============================================
// LABELS & TRANSLATIONS
// ============================================
$section_title = $tr('access_credentials_title', 'Přístupové údaje');
$expand_label = $tr('expand', 'Rozbalit');
$collapse_label = $tr('collapse', 'Sbalit');

$pin_label = $tr('pin_label', 'PIN kód pro vstup');
$pin_copy = $tr('pin_copy', 'Kopírovat');
$pin_copied = $tr('pin_copied', 'Zkopírováno');
$pin_expired = $tr('pin_expired', 'Vypršelo');
$pin_expired_ago = $tr('pin_expired_ago', 'Před');
$pin_remaining = $tr('pin_remaining', 'Zbývá');
$pin_active = $tr('pin_active', 'Aktivní');
$pin_extend_24h = $tr('pin_extend_24h', '+24h');
$pin_extend_48h = $tr('pin_extend_48h', '+48h');
$pin_extend_7d = $tr('pin_extend_7d', '+7 dní');
$pin_extend_manual = $tr('pin_extend_manual', 'Ručně');
$pin_set_expiry = $tr('pin_set_expiry', 'Nastavit novou expiraci');
$pin_save = $tr('pin_save', 'Uložit');
$pin_back = $tr('pin_back', 'Zpět');
$pin_not_generated = $tr('pin_not_generated', 'PIN kód nebyl vygenerován.');
$pin_generate = $tr('pin_generate', 'Vygenerovat PIN');

$token_label = $tr('token_label', 'Registrační odkaz');
$token_copy = $tr('token_copy', 'Kopírovat odkaz');
$token_copied = $tr('token_copied', 'Zkopírováno');
$token_expired = $tr('token_expired', 'Vypršelo');
$token_expired_ago = $tr('token_expired_ago', 'Před');
$token_remaining = $tr('token_remaining', 'Zbývá');
$token_active = $tr('token_active', 'Aktivní');
$token_extend_24h = $tr('token_extend_24h', '+24h');
$token_extend_3d = $tr('token_extend_3d', '+3 dny');
$token_extend_7d = $tr('token_extend_7d', '+7 dní');
$token_extend_manual = $tr('token_extend_manual', 'Ručně');
$token_set_expiry = $tr('token_set_expiry', 'Nastavit novou expiraci');
$token_not_generated = $tr('token_not_generated', 'Registrační odkaz nebyl vygenerován.');
$token_open = $tr('token_open', 'Otevřít');

// Status badge labels
$status_all_active = $tr('status_all_active', 'Aktivní');
$status_pin_expired = $tr('status_pin_expired', 'PIN vypršel');
$status_token_expired = $tr('status_token_expired', 'Odkaz vypršel');
$status_both_expired = $tr('status_both_expired', 'Vše vypršelo');
$status_none_generated = $tr('status_none_generated', 'Žádné údaje');

// Duration labels
$day_singular = $tr('duration_day', 'den');
$day_few = $tr('duration_days_few', 'dny');
$day_many = $tr('duration_days_many', 'dní');
$hour_singular = $tr('duration_hour', 'hodina');
$hour_few = $tr('duration_hours_few', 'hodiny');
$hour_many = $tr('duration_hours_many', 'hodin');
$min_label = $tr('duration_min', 'min');

$prague_tz = new DateTimeZone('Europe/Prague');
$now_dt = new DateTime('now', $prague_tz);

/**
 * Format duration in Czech
 */
if (!function_exists('saw_format_czech_duration_tr')) {
    function saw_format_czech_duration_tr($diff, $day_s, $day_f, $day_m, $hour_s, $hour_f, $hour_m, $min_l) {
        $parts = [];
        
        if ($diff->days > 0) {
            $d = $diff->days;
            if ($d == 1) $word = $day_s;
            elseif ($d >= 2 && $d <= 4) $word = $day_f;
            else $word = $day_m;
            $parts[] = "$d $word";
        }
        
        $h = $diff->h;
        if ($diff->days == 0 || $h > 0) {
            if ($h == 1) $word = $hour_s;
            elseif ($h >= 2 && $h <= 4) $word = $hour_f;
            else $word = $hour_m;
            $parts[] = "$h $word";
        }
        
        if ($diff->days == 0) {
            $m = $diff->i;
            $parts[] = "$m $min_l";
        }

        return implode(", ", $parts);
    }
}

// ============================================
// CALCULATE AGGREGATED STATUS
// ============================================
$has_pin = !empty($item['pin_code']);
$has_token = !empty($item['invitation_token']);

$pin_expired = false;
$token_expired = false;

if ($has_pin && !empty($item['pin_expires_at'])) {
    $pin_exp_dt = new DateTime($item['pin_expires_at'], $prague_tz);
    $pin_expired = $pin_exp_dt < $now_dt;
}

if ($has_token && !empty($item['invitation_token_expires_at'])) {
    $token_exp_dt = new DateTime($item['invitation_token_expires_at'], $prague_tz);
    $token_expired = $token_exp_dt < $now_dt;
}

// Determine aggregated status
$aggregated_status = 'none'; // none, all_active, pin_expired, token_expired, both_expired
$status_badge_text = $status_none_generated;
$status_badge_icon = '⚪';
$status_badge_class = 'status-none';

if ($has_pin || $has_token) {
    if ($pin_expired && $token_expired) {
        $aggregated_status = 'both_expired';
        $status_badge_text = $status_both_expired;
        $status_badge_icon = '🔴';
        $status_badge_class = 'status-error';
    } elseif ($pin_expired) {
        $aggregated_status = 'pin_expired';
        $status_badge_text = $status_pin_expired;
        $status_badge_icon = '⚠️';
        $status_badge_class = 'status-warning';
    } elseif ($token_expired) {
        $aggregated_status = 'token_expired';
        $status_badge_text = $status_token_expired;
        $status_badge_icon = '⚠️';
        $status_badge_class = 'status-warning';
    } else {
        $aggregated_status = 'all_active';
        $status_badge_text = $status_all_active;
        $status_badge_icon = '✅';
        $status_badge_class = 'status-success';
    }
}

// ============================================
// BUILD INVITATION URL
// ============================================
$invitation_url = '';
$invitation_url_short = '';
if (!empty($item['invitation_token'])) {
    $invitation_url = home_url('/visitor-invitation/' . $item['invitation_token'] . '/');
    // Shorten URL for display
    $invitation_url_short = str_replace(['https://', 'http://'], '', $invitation_url);
    if (strlen($invitation_url_short) > 35) {
        $half = (35 - 3) / 2;
        $invitation_url_short = substr($invitation_url_short, 0, floor($half)) . '...' . substr($invitation_url_short, -ceil($half));
    }
}

$visit_id = intval($item['id'] ?? 0);
?>

<!-- ============================================ -->
<!-- ACCESS CREDENTIALS SECTION (COMPACT)        -->
<!-- ============================================ -->
<div class="saw-info-item saw-access-credentials-wrapper" style="grid-column: 1 / -1; width: 100%;">
<div class="saw-access-credentials-section" id="access-credentials-<?php echo $visit_id; ?>">
    <!-- HEADER (Always visible) -->
    <div class="saw-access-header" onclick="sawToggleAccessCredentials(<?php echo $visit_id; ?>)">
        <div class="saw-access-header-left">
            <span class="saw-access-icon">🔐</span>
            <span class="saw-access-title"><?php echo esc_html($section_title); ?></span>
        </div>
        <div class="saw-access-header-right">
            <span class="saw-access-status-badge <?php echo esc_attr($status_badge_class); ?>">
                <span class="status-icon"><?php echo $status_badge_icon; ?></span>
                <span class="status-text"><?php echo esc_html($status_badge_text); ?></span>
            </span>
            <span class="saw-access-toggle-icon" id="toggle-icon-<?php echo $visit_id; ?>">▼</span>
        </div>
    </div>

    <!-- COLLAPSIBLE CONTENT -->
    <div class="saw-access-content" id="access-content-<?php echo $visit_id; ?>" style="display: none;">
        <div class="saw-access-content-inner">
            
            <!-- PIN CARD -->
            <?php if ($has_pin): 
                $js_pin_expiry_ts = 0;
                if (!empty($item['pin_expires_at'])) {
                    $exp_dt = new DateTime($item['pin_expires_at'], $prague_tz);
                    $js_pin_expiry_ts = $exp_dt->getTimestamp() * 1000;
                }
                $calendar_val = (clone $now_dt)->modify('+24 hours')->format('Y-m-d\TH:i');
                if (!empty($item['pin_expires_at'])) {
                    $exp_dt = new DateTime($item['pin_expires_at'], $prague_tz);
                    $current_now = new DateTime('now', $prague_tz);
                    if ($exp_dt > $current_now) {
                        $calendar_val = (clone $exp_dt)->modify('+24 hours')->format('Y-m-d\TH:i');
                    }
                }
            ?>
            <div class="saw-credential-card saw-pin-card <?php echo $pin_expired ? 'expired' : ''; ?>" 
                 id="pin-card-<?php echo $visit_id; ?>" 
                 data-current-expiry="<?php echo $js_pin_expiry_ts; ?>">
                
                <div class="saw-credential-title"><?php echo esc_html($pin_label); ?></div>
                
                <div class="saw-credential-display">
                    <div class="saw-credential-value-box" onclick="copyPinToClipboard('<?php echo esc_js($item['pin_code']); ?>', <?php echo $visit_id; ?>)">
                        <span class="credential-icon">🔑</span>
                        <span class="credential-value pin-value" id="pin-code-<?php echo $visit_id; ?>">
                            <?php echo esc_html($item['pin_code']); ?>
                        </span>
                        <span class="credential-copy-icon" id="pin-copy-icon-<?php echo $visit_id; ?>">👁️</span>
                        <span class="credential-copy-text" id="pin-copy-text-<?php echo $visit_id; ?>"><?php echo esc_html($pin_copy); ?></span>
                    </div>
                </div>
                
                <?php 
                $pin_status_text = $pin_active;
                $pin_status_class = 'active';
                $pin_expiry_text = '';
                
                if (!empty($item['pin_expires_at'])) {
                    $exp_dt = new DateTime($item['pin_expires_at'], $prague_tz);
                    $current_now = new DateTime('now', $prague_tz);
                    
                    if ($exp_dt < $current_now) {
                        $diff = $current_now->diff($exp_dt);
                        $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                        $pin_status_text = $pin_expired_ago . ' ' . $duration;
                        $pin_status_class = 'expired';
                    } else {
                        $diff = $exp_dt->diff($current_now);
                        $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                        $pin_status_text = $pin_active . ' - ' . $pin_remaining . ' ' . $duration;
                        $pin_expiry_text = $exp_dt->format('d.m.Y H:i');
                    }
                }
                ?>
                
                <div class="saw-credential-status">
                    <span class="status-indicator <?php echo esc_attr($pin_status_class); ?>">
                        <?php echo $pin_expired ? '⚠️' : '✅'; ?>
                    </span>
                    <span class="status-text <?php echo esc_attr($pin_status_class); ?>">
                        <?php echo esc_html($pin_status_text); ?>
                    </span>
                </div>
                
                <?php if ($item['status'] !== 'cancelled'): ?>
                <div class="saw-credential-actions">
                    <div class="saw-extend-buttons" id="pin-extend-buttons-<?php echo $visit_id; ?>">
                        <button type="button" class="saw-extend-btn" onclick="extendPinQuick(<?php echo $visit_id; ?>, 24)">
                            <span>🔄</span> <?php echo esc_html($pin_extend_24h); ?>
                        </button>
                        <button type="button" class="saw-extend-btn" onclick="extendPinQuick(<?php echo $visit_id; ?>, 48)">
                            <span>⏱️</span> <?php echo esc_html($pin_extend_48h); ?>
                        </button>
                        <button type="button" class="saw-extend-btn" onclick="extendPinQuick(<?php echo $visit_id; ?>, 168)">
                            <span>📅</span> <?php echo esc_html($pin_extend_7d); ?>
                        </button>
                        <button type="button" class="saw-extend-btn primary" onclick="showExtendPinForm(<?php echo $visit_id; ?>)">
                            <span>⚙️</span> <?php echo esc_html($pin_extend_manual); ?>
                        </button>
                    </div>
                    
                    <div class="saw-extend-form" id="pin-extend-form-<?php echo $visit_id; ?>" style="display: none;">
                        <div class="saw-form-title">📆 <?php echo esc_html($pin_set_expiry); ?></div>
                        <input type="datetime-local" 
                               id="pin-expiry-datetime-<?php echo $visit_id; ?>" 
                               class="saw-datetime-input"
                               value="<?php echo esc_attr($calendar_val); ?>">
                        <div class="saw-form-buttons">
                            <button type="button" class="saw-form-btn save" onclick="extendPinCustom(<?php echo $visit_id; ?>)">
                                <span>✅</span> <?php echo esc_html($pin_save); ?>
                            </button>
                            <button type="button" class="saw-form-btn cancel" onclick="hideExtendPinForm(<?php echo $visit_id; ?>)">
                                <span>❌</span> <?php echo esc_html($pin_back); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($item['status'] !== 'cancelled' && ($item['visit_type'] ?? '') === 'planned'): ?>
            <div class="saw-credential-card saw-pin-card empty">
                <div class="saw-credential-title"><?php echo esc_html($pin_label); ?></div>
                <div class="saw-empty-state">
                    <span class="empty-icon">🔓</span>
                    <p><?php echo esc_html($pin_not_generated); ?></p>
                    <button type="button" class="saw-generate-btn" onclick="generatePin(<?php echo $visit_id; ?>)">
                        <span>✨</span> <?php echo esc_html($pin_generate); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- TOKEN CARD -->
            <?php if ($has_token): 
                $js_token_expiry_ts = 0;
                if (!empty($item['invitation_token_expires_at'])) {
                    $token_exp_dt = new DateTime($item['invitation_token_expires_at'], $prague_tz);
                    $js_token_expiry_ts = $token_exp_dt->getTimestamp() * 1000;
                }
                $token_calendar_val = (clone $now_dt)->modify('+24 hours')->format('Y-m-d\TH:i');
                if (!empty($item['invitation_token_expires_at'])) {
                    $token_exp_dt = new DateTime($item['invitation_token_expires_at'], $prague_tz);
                    $current_now = new DateTime('now', $prague_tz);
                    if ($token_exp_dt > $current_now) {
                        $token_calendar_val = (clone $token_exp_dt)->modify('+24 hours')->format('Y-m-d\TH:i');
                    }
                }
            ?>
            <div class="saw-credential-card saw-token-card <?php echo $token_expired ? 'expired' : ''; ?>" 
                 id="token-card-<?php echo $visit_id; ?>" 
                 data-current-expiry="<?php echo $js_token_expiry_ts; ?>">
                
                <div class="saw-credential-title"><?php echo esc_html($token_label); ?></div>
                
                <div class="saw-credential-display">
                    <div class="saw-credential-value-box">
                        <span class="credential-icon">🔗</span>
                        <span class="credential-value url-value" id="token-url-<?php echo $visit_id; ?>" title="<?php echo esc_attr($invitation_url); ?>">
                            <?php echo esc_html($invitation_url_short); ?>
                        </span>
                        <a href="<?php echo esc_url($invitation_url); ?>" 
                           target="_blank" 
                           class="credential-open-link" 
                           title="<?php echo esc_attr($token_open); ?>">↗️</a>
                        <button type="button" 
                                class="credential-copy-btn" 
                                id="token-copy-btn-<?php echo $visit_id; ?>"
                                onclick="copyUrlToClipboard('<?php echo esc_js($invitation_url); ?>', <?php echo $visit_id; ?>)">
                            <span class="copy-icon">📋</span>
                            <span class="copy-text"><?php echo esc_html($token_copy); ?></span>
                        </button>
                    </div>
                </div>
                
                <?php 
                $token_status_text = $token_active;
                $token_status_class = 'active';
                $token_expiry_text = '';
                
                if (!empty($item['invitation_token_expires_at'])) {
                    $token_exp_dt = new DateTime($item['invitation_token_expires_at'], $prague_tz);
                    $current_now = new DateTime('now', $prague_tz);
                    
                    if ($token_exp_dt < $current_now) {
                        $diff = $current_now->diff($token_exp_dt);
                        $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                        $token_status_text = $token_expired_ago . ' ' . $duration;
                        $token_status_class = 'expired';
                    } else {
                        $diff = $token_exp_dt->diff($current_now);
                        $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                        $token_status_text = $token_active . ' - ' . $token_remaining . ' ' . $duration;
                        $token_expiry_text = $token_exp_dt->format('d.m.Y H:i');
                    }
                }
                ?>
                
                <div class="saw-credential-status">
                    <span class="status-indicator <?php echo esc_attr($token_status_class); ?>">
                        <?php echo $token_expired ? '⚠️' : '✅'; ?>
                    </span>
                    <span class="status-text <?php echo esc_attr($token_status_class); ?>">
                        <?php echo esc_html($token_status_text); ?>
                    </span>
                </div>
                
                <?php if ($item['status'] !== 'cancelled'): ?>
                <div class="saw-credential-actions">
                    <div class="saw-extend-buttons" id="token-extend-buttons-<?php echo $visit_id; ?>">
                        <button type="button" class="saw-extend-btn" onclick="extendTokenQuick(<?php echo $visit_id; ?>, 24)">
                            <span>🔄</span> <?php echo esc_html($token_extend_24h); ?>
                        </button>
                        <button type="button" class="saw-extend-btn" onclick="extendTokenQuick(<?php echo $visit_id; ?>, 72)">
                            <span>⏱️</span> <?php echo esc_html($token_extend_3d); ?>
                        </button>
                        <button type="button" class="saw-extend-btn" onclick="extendTokenQuick(<?php echo $visit_id; ?>, 168)">
                            <span>📅</span> <?php echo esc_html($token_extend_7d); ?>
                        </button>
                        <button type="button" class="saw-extend-btn primary" onclick="showExtendTokenForm(<?php echo $visit_id; ?>)">
                            <span>⚙️</span> <?php echo esc_html($token_extend_manual); ?>
                        </button>
                    </div>
                    
                    <div class="saw-extend-form" id="token-extend-form-<?php echo $visit_id; ?>" style="display: none;">
                        <div class="saw-form-title">📆 <?php echo esc_html($token_set_expiry); ?></div>
                        <input type="datetime-local" 
                               id="token-expiry-datetime-<?php echo $visit_id; ?>" 
                               class="saw-datetime-input"
                               value="<?php echo esc_attr($token_calendar_val); ?>">
                        <div class="saw-form-buttons">
                            <button type="button" class="saw-form-btn save" onclick="extendTokenCustom(<?php echo $visit_id; ?>)">
                                <span>✅</span> <?php echo esc_html($pin_save); ?>
                            </button>
                            <button type="button" class="saw-form-btn cancel" onclick="hideExtendTokenForm(<?php echo $visit_id; ?>)">
                                <span>❌</span> <?php echo esc_html($pin_back); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($item['status'] !== 'cancelled' && ($item['visit_type'] ?? '') === 'planned'): ?>
            <div class="saw-credential-card saw-token-card empty">
                <div class="saw-credential-title"><?php echo esc_html($token_label); ?></div>
                <div class="saw-empty-state">
                    <span class="empty-icon">🔗</span>
                    <p><?php echo esc_html($token_not_generated); ?></p>
                    <p class="empty-hint"><?php echo esc_html($tr('token_generate_hint', 'Token se vygeneruje automaticky při odeslání pozvánky.')); ?></p>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</div>

<!-- ============================================ -->
<!-- JAVASCRIPT                                  -->
<!-- ============================================ -->
<script>
// Toggle expand/collapse
if (typeof sawToggleAccessCredentials === 'undefined') {
    function sawToggleAccessCredentials(visitId) {
        const content = document.getElementById('access-content-' + visitId);
        const icon = document.getElementById('toggle-icon-' + visitId);
        
        if (!content || !icon) return;
        
        const isExpanded = content.style.display !== 'none';
        
        if (isExpanded) {
            content.style.display = 'none';
            icon.textContent = '▼';
            icon.style.transform = 'rotate(0deg)';
        } else {
            content.style.display = 'block';
            icon.textContent = '▲';
            icon.style.transform = 'rotate(180deg)';
        }
    }
}

// PIN copy function
if (typeof copyPinToClipboard === 'undefined') {
    function copyPinToClipboard(pin, visitId) {
        if (!pin) return;
        
        const displayEl = document.getElementById('pin-code-' + visitId);
        const copyIcon = document.getElementById('pin-copy-icon-' + visitId);
        const copyText = document.getElementById('pin-copy-text-' + visitId);
        
        if (!displayEl) return;
        
        const originalText = displayEl.innerText;
        const originalIcon = copyIcon ? copyIcon.textContent : '';
        const originalCopyText = copyText ? copyText.textContent : '';
        
        navigator.clipboard.writeText(pin).then(() => {
            if (copyIcon) copyIcon.textContent = '✓';
            if (copyText) copyText.textContent = '<?php echo esc_js($pin_copied); ?>';
            
            if (window.navigator && window.navigator.vibrate) {
                window.navigator.vibrate([50]);
            }
            
            setTimeout(() => {
                if (copyIcon) copyIcon.textContent = originalIcon;
                if (copyText) copyText.textContent = originalCopyText;
            }, 2000);
        }).catch(err => {
            console.error('Copy error:', err);
            fallbackCopyToClipboard(pin);
        });
    }
}

// URL copy function
if (typeof copyUrlToClipboard === 'undefined') {
    function copyUrlToClipboard(url, visitId) {
        if (!url) return;
        
        const badgeEl = document.getElementById('token-copy-btn-' + visitId);
        if (!badgeEl) return;
        
        const copyIcon = badgeEl.querySelector('.copy-icon');
        const copyText = badgeEl.querySelector('.copy-text');
        const originalIcon = copyIcon ? copyIcon.textContent : '';
        const originalText = copyText ? copyText.textContent : '';
        
        navigator.clipboard.writeText(url).then(() => {
            if (copyIcon) copyIcon.textContent = '✓';
            if (copyText) copyText.textContent = '<?php echo esc_js($token_copied); ?>';
            
            if (window.navigator && window.navigator.vibrate) {
                window.navigator.vibrate([50]);
            }
            
            setTimeout(() => {
                if (copyIcon) copyIcon.textContent = originalIcon;
                if (copyText) copyText.textContent = originalText;
            }, 2000);
        }).catch(err => {
            console.error('Copy error:', err);
            fallbackCopyToClipboard(url);
        });
    }
}

// Fallback copy
if (typeof fallbackCopyToClipboard === 'undefined') {
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('<?php echo esc_js($tr('copied_fallback', 'Zkopírováno do schránky')); ?>');
        } catch (err) {
            console.error('Fallback copy failed:', err);
        }
        
        document.body.removeChild(textArea);
    }
}

// PIN extend functions (from existing implementation)
if (typeof extendPinQuick === 'undefined') {
    function extendPinQuick(visitId, hours) {
        const card = document.getElementById('pin-card-' + visitId);
        if (!card) {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: Nenalezen PIN element');
            return;
        }
        
        const currentExpiry = parseInt(card.getAttribute('data-current-expiry')) || 0;
        const now = Date.now();
        const baseTime = (currentExpiry && currentExpiry > now) ? currentExpiry : now;
        const newTimeMs = baseTime + (hours * 3600 * 1000);
        const newDate = new Date(newTimeMs);
        newDate.setSeconds(0, 0);
        
        const displayDate = formatDateTime(newDate);
        if (!confirm('<?php echo esc_js($tr('confirm_extend_pin', 'Prodloužit platnost PIN o')); ?> ' + hours + ' <?php echo esc_js($tr('hours', 'hodin')); ?>?\n\n<?php echo esc_js($tr('new_expiration', 'Nová expirace')); ?>: ' + displayDate)) {
            return;
        }
        
        const sqlDate = formatSQLDateTime(newDate);
        extendPinToExactTime(visitId, sqlDate, newDate);
    }
}

if (typeof extendPinToExactTime === 'undefined') {
    function extendPinToExactTime(visitId, sqlDate, dateObj) {
        jQuery.post(sawGlobal.ajaxurl, {
            action: 'saw_extend_pin',
            visit_id: visitId,
            exact_expiry: sqlDate,
            nonce: sawGlobal.nonce
        }, function(response) {
            if (response.success) {
                const card = document.getElementById('pin-card-' + visitId);
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
}

if (typeof showExtendPinForm === 'undefined') {
    function showExtendPinForm(visitId) {
        document.getElementById('pin-extend-buttons-' + visitId).style.display = 'none';
        const form = document.getElementById('pin-extend-form-' + visitId);
        form.style.display = 'block';
        const datetimeInput = document.getElementById('pin-expiry-datetime-' + visitId);
        datetimeInput.min = new Date().toISOString().slice(0, 16);
    }
}

if (typeof hideExtendPinForm === 'undefined') {
    function hideExtendPinForm(visitId) {
        document.getElementById('pin-extend-form-' + visitId).style.display = 'none';
        document.getElementById('pin-extend-buttons-' + visitId).style.display = 'flex';
    }
}

if (typeof extendPinCustom === 'undefined') {
    function extendPinCustom(visitId) {
        const datetimeInput = document.getElementById('pin-expiry-datetime-' + visitId);
        const datetimeValue = datetimeInput.value;
        
        if (!datetimeValue) {
            alert('<?php echo esc_js($tr('enter_datetime', 'Prosím zadejte datum a čas.')); ?>');
            return;
        }
        
        const newDate = new Date(datetimeValue);
        if (isNaN(newDate.getTime())) {
            alert('<?php echo esc_js($tr('invalid_date', 'Neplatné datum')); ?>');
            return;
        }
        
        const displayDate = formatDateTime(newDate);
        if (!confirm('<?php echo esc_js($tr('confirm_set_expiry', 'Nastavit expiraci PIN na')); ?>:\n' + displayDate + '?')) {
            return;
        }
        
        const sqlDate = formatSQLDateTime(newDate);
        extendPinToExactTime(visitId, sqlDate, newDate);
    }
}

// TOKEN extend functions
if (typeof extendTokenQuick === 'undefined') {
    function extendTokenQuick(visitId, hours) {
        const card = document.getElementById('token-card-' + visitId);
        if (!card) {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: Nenalezen Token element');
            return;
        }
        
        const currentExpiry = parseInt(card.getAttribute('data-current-expiry')) || 0;
        const now = Date.now();
        const baseTime = (currentExpiry && currentExpiry > now) ? currentExpiry : now;
        const newTimeMs = baseTime + (hours * 3600 * 1000);
        const newDate = new Date(newTimeMs);
        newDate.setSeconds(0, 0);
        
        const displayDate = formatDateTime(newDate);
        if (!confirm('<?php echo esc_js($tr('confirm_extend_token', 'Prodloužit platnost odkazu o')); ?> ' + hours + ' <?php echo esc_js($tr('hours', 'hodin')); ?>?\n\n<?php echo esc_js($tr('new_expiration', 'Nová expirace')); ?>: ' + displayDate)) {
            return;
        }
        
        const sqlDate = formatSQLDateTime(newDate);
        extendTokenToExactTime(visitId, sqlDate, newDate);
    }
}

if (typeof extendTokenToExactTime === 'undefined') {
    function extendTokenToExactTime(visitId, sqlDate, dateObj) {
        jQuery.post(sawGlobal.ajaxurl, {
            action: 'saw_extend_token',
            visit_id: visitId,
            exact_expiry: sqlDate,
            nonce: sawGlobal.nonce
        }, function(response) {
            if (response.success) {
                const card = document.getElementById('token-card-' + visitId);
                if (card) {
                    card.setAttribute('data-current-expiry', dateObj.getTime());
                }
                alert('✅ <?php echo esc_js($tr('alert_token_extended', 'Odkaz prodloužen do')); ?>: ' + formatDateTime(dateObj));
                location.reload();
            } else {
                alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + (response.data?.message || 'Neznámá chyba'));
            }
        }).fail(function(xhr, status, error) {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem\n\n' + error);
        });
    }
}

if (typeof showExtendTokenForm === 'undefined') {
    function showExtendTokenForm(visitId) {
        document.getElementById('token-extend-buttons-' + visitId).style.display = 'none';
        const form = document.getElementById('token-extend-form-' + visitId);
        form.style.display = 'block';
        const datetimeInput = document.getElementById('token-expiry-datetime-' + visitId);
        datetimeInput.min = new Date().toISOString().slice(0, 16);
    }
}

if (typeof hideExtendTokenForm === 'undefined') {
    function hideExtendTokenForm(visitId) {
        document.getElementById('token-extend-form-' + visitId).style.display = 'none';
        document.getElementById('token-extend-buttons-' + visitId).style.display = 'flex';
    }
}

if (typeof extendTokenCustom === 'undefined') {
    function extendTokenCustom(visitId) {
        const datetimeInput = document.getElementById('token-expiry-datetime-' + visitId);
        const datetimeValue = datetimeInput.value;
        
        if (!datetimeValue) {
            alert('<?php echo esc_js($tr('enter_datetime', 'Prosím zadejte datum a čas.')); ?>');
            return;
        }
        
        const newDate = new Date(datetimeValue);
        if (isNaN(newDate.getTime())) {
            alert('<?php echo esc_js($tr('invalid_date', 'Neplatné datum')); ?>');
            return;
        }
        
        const displayDate = formatDateTime(newDate);
        if (!confirm('<?php echo esc_js($tr('confirm_set_expiry', 'Nastavit expiraci odkazu na')); ?>:\n' + displayDate + '?')) {
            return;
        }
        
        const sqlDate = formatSQLDateTime(newDate);
        extendTokenToExactTime(visitId, sqlDate, newDate);
    }
}

// Helper functions
if (typeof formatDateTime === 'undefined') {
    function formatDateTime(date) {
        const d = date.getDate().toString().padStart(2, '0');
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const y = date.getFullYear();
        const h = date.getHours().toString().padStart(2, '0');
        const min = date.getMinutes().toString().padStart(2, '0');
        return d + '.' + m + '.' + y + ' ' + h + ':' + min;
    }
}

if (typeof formatSQLDateTime === 'undefined') {
    function formatSQLDateTime(date) {
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        const seconds = date.getSeconds().toString().padStart(2, '0');
        return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
    }
}

// Generate PIN function (if not exists)
if (typeof generatePin === 'undefined') {
    function generatePin(visitId) {
        if (!confirm('<?php echo esc_js($tr('confirm_generate_pin', 'Vygenerovat PIN kód pro tuto návštěvu?\n\nPIN bude platný do posledního plánovaného dne návštěvy + 24 hodin.')); ?>')) {
            return;
        }
        
        jQuery.post(sawGlobal.ajaxurl, {
            action: 'saw_generate_pin',
            visit_id: visitId,
            nonce: sawGlobal.nonce
        }, function(response) {
            if (response.success) {
                alert('✅ <?php echo esc_js($tr('alert_pin_generated', 'PIN úspěšně vygenerován')); ?>: ' + (response.data.pin_code || 'N/A') + '\n\nPlatnost: ' + (response.data.pin_expires_at || 'N/A'));
                location.reload();
            } else {
                alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: ' + (response.data?.message || 'Neznámá chyba'));
            }
        }).fail(function(xhr, status, error) {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?> komunikace se serverem\n\n' + error);
        });
    }
}
</script>

<!-- ============================================ -->
<!-- STYLES                                       -->
<!-- ============================================ -->
<style>
/* ============================================
   WRAPPER (ensures full width)
   ============================================ */
.saw-access-credentials-wrapper {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
}

/* Ensure parent grid doesn't constrain width */
.saw-info-grid .saw-access-credentials-wrapper {
    grid-column: 1 / -1;
    width: 100%;
    max-width: 100%;
}

/* ============================================
   MAIN SECTION CONTAINER
   ============================================ */
.saw-access-credentials-section {
    width: 100%;
    margin: 0;
    background: white;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    overflow-x: hidden;
    max-width: 100%;
    box-sizing: border-box;
}

/* ============================================
   HEADER (Always visible)
   ============================================ */
.saw-access-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s ease;
    max-width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
}

.saw-access-header:hover {
    background-color: #F9FAFB;
}

.saw-access-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.saw-access-icon {
    font-size: 18px;
    line-height: 1;
}

.saw-access-title {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.saw-access-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    min-width: 0;
}

/* Status Badge */
.saw-access-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
    min-width: 150px;
    width: auto;
    white-space: nowrap;
    justify-content: center;
}

.saw-access-status-badge .status-icon {
    font-size: 14px;
    line-height: 1;
}

.saw-access-status-badge.status-success {
    background-color: #DCFCE7;
    border: 1px solid #86EFAC;
    color: #166534;
}

.saw-access-status-badge.status-warning {
    background-color: #FEF3C7;
    border: 1px solid #FCD34D;
    color: #92400E;
    min-width: 150px;
    width: auto;
}

.saw-access-status-badge.status-error {
    background-color: #FEE2E2;
    border: 1px solid #FCA5A5;
    color: #DC2626;
}

.saw-access-status-badge.status-none {
    background-color: #F3F4F6;
    border: 1px solid #D1D5DB;
    color: #6B7280;
}

.saw-access-toggle-icon {
    font-size: 14px;
    color: #6B7280;
    transition: transform 0.3s ease;
    line-height: 1;
}

/* ============================================
   COLLAPSIBLE CONTENT
   ============================================ */
.saw-access-content {
    border-top: 1px solid #E5E7EB;
    background-color: #FAFBFC;
    overflow-x: hidden;
    max-width: 100%;
    box-sizing: border-box;
}

.saw-access-content-inner {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow-x: hidden;
    max-width: 100%;
    box-sizing: border-box;
}

/* ============================================
   CREDENTIAL CARD
   ============================================ */
.saw-credential-card {
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 16px;
}

.saw-credential-card.expired {
    border-color: #FCA5A5;
    border-width: 2px;
}

.saw-credential-title {
    font-size: 11px;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

/* Credential Display */
.saw-credential-display {
    margin-bottom: 12px;
}

.saw-credential-value-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    max-width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
}

.saw-credential-value-box:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
}

.credential-icon {
    font-size: 18px;
    flex-shrink: 0;
}

.credential-value {
    flex: 1;
    min-width: 0;
}

.credential-value.pin-value {
    font-family: 'SF Mono', 'Menlo', 'Monaco', monospace;
    font-size: 36px !important;
    font-weight: 800;
    color: #111827;
    letter-spacing: 4px;
    line-height: 1.2;
    word-break: break-all;
    overflow-wrap: break-word;
    max-width: 100%;
}

.credential-value.url-value {
    font-size: 13px;
    color: #2563EB;
    text-decoration: underline;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.credential-copy-icon,
.credential-copy-text {
    font-size: 12px;
    color: #64748B;
    flex-shrink: 0;
}

.credential-open-link {
    font-size: 14px;
    text-decoration: none;
    color: #2563EB;
    flex-shrink: 0;
    padding: 4px;
}

.credential-open-link:hover {
    opacity: 0.7;
}

.credential-copy-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    font-size: 12px;
    color: #64748B;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s ease;
    font-family: inherit;
}

.credential-copy-btn:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
}

/* Status */
.saw-credential-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 8px 0;
}

.status-indicator {
    font-size: 14px;
    line-height: 1;
}

.status-text {
    font-size: 13px;
    font-weight: 500;
}

.status-text.active {
    color: #059669;
}

.status-text.expired {
    color: #DC2626;
}

/* Actions */
.saw-credential-actions {
    margin-top: 8px;
}

.saw-extend-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.saw-extend-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: white;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
    white-space: nowrap;
}

.saw-extend-btn:hover {
    border-color: #CBD5E1;
    background: #F9FAFB;
    transform: translateY(-1px);
}

.saw-extend-btn.primary {
    background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
    border: none;
    color: white;
}

.saw-extend-btn.primary:hover {
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.saw-extend-btn span {
    font-size: 14px;
}

/* Custom Form */
.saw-extend-form {
    margin-top: 12px;
    padding: 12px;
    background: #FFFBEB;
    border: 1px solid #FCD34D;
    border-radius: 8px;
}

.saw-form-title {
    font-weight: 600;
    color: #92400E;
    margin-bottom: 8px;
    font-size: 13px;
}

.saw-datetime-input {
    width: 100%;
    padding: 8px 10px;
    border: 2px solid #FBBF24;
    border-radius: 6px;
    margin-bottom: 10px;
    background: white;
    font-size: 14px;
    color: #333;
    font-family: inherit;
}

.saw-datetime-input:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.saw-form-buttons {
    display: flex;
    gap: 8px;
}

.saw-form-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
    flex: 1;
    justify-content: center;
}

.saw-form-btn.save {
    background: #10B981;
    border: none;
    color: white;
}

.saw-form-btn.save:hover {
    background: #059669;
}

.saw-form-btn.cancel {
    background: white;
    border: 1px solid #E5E7EB;
    color: #374151;
}

.saw-form-btn.cancel:hover {
    background: #F9FAFB;
}

/* Empty State */
.saw-empty-state {
    text-align: center;
    padding: 24px 12px;
}

.saw-empty-state .empty-icon {
    font-size: 32px;
    display: block;
    margin-bottom: 8px;
    opacity: 0.5;
}

.saw-empty-state p {
    margin: 0 0 16px 0;
    color: #9CA3AF;
    font-size: 13px;
}

.saw-empty-state .empty-hint {
    font-size: 11px;
    color: #D1D5DB;
    margin-top: 4px;
}

.saw-generate-btn {
    background: #2563EB;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.saw-generate-btn:hover {
    background: #1D4ED8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 420px) {
    .saw-access-header {
        padding: 10px 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .saw-access-header-left {
        flex: 1 1 100%;
        min-width: 0;
    }
    
    .saw-access-header-right {
        flex: 1 1 100%;
        justify-content: space-between;
        min-width: 0;
    }
    
    .saw-access-title {
        font-size: 14px;
    }
    
    .saw-access-status-badge {
        font-size: 11px;
        padding: 3px 8px;
        min-width: auto !important;
        max-width: 100%;
        flex: 1 1 auto;
    }
    
    .saw-access-status-badge.status-warning {
        min-width: auto !important;
    }
    
    .saw-access-toggle-icon {
        flex-shrink: 0;
    }
    
    .saw-access-content-inner {
        padding: 12px;
    }
    
    .saw-credential-card {
        padding: 12px;
    }
    
    .saw-credential-value-box {
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px;
    }
    
    .credential-value.pin-value {
        font-size: 24px !important;
        letter-spacing: 2px;
        word-break: break-all;
    }
    
    .credential-value.url-value {
        font-size: 11px;
        max-width: 100%;
    }
    
    .saw-extend-buttons {
        flex-direction: column;
    }
    
    .saw-extend-btn {
        width: 100%;
        justify-content: center;
        white-space: normal;
        padding: 10px 12px;
    }
}

/* ============================================
   VERY SMALL SCREENS
   ============================================ */
@media (max-width: 360px) {
    .saw-access-status-badge {
        font-size: 10px;
        padding: 2px 6px;
    }
    
    .saw-access-title {
        font-size: 13px;
    }
    
    .credential-value.pin-value {
        font-size: 20px !important;
        letter-spacing: 1px;
    }
    
    .saw-extend-btn {
        font-size: 11px;
        padding: 8px 10px;
    }
}
</style>
