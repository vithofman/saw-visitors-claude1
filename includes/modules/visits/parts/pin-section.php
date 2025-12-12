<?php
/**
 * PIN & Token Section - Detail Sidebar Part
 * 
 * Displays PIN code and Invitation Token with expiration management.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits/Parts
 * @version     5.0.0 - Added Token section with extend functionality
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
// PIN LABELS
// ============================================
$pin_label = $tr('pin_label', 'PIN kód pro vstup');
$pin_copy = $tr('pin_copy', 'Kopírovat');
$pin_copied = $tr('pin_copied', 'Zkopírováno');
$pin_status = $tr('pin_status', 'Stav');
$pin_expiration = $tr('pin_expiration', 'Expirace');
$pin_unlimited = $tr('pin_unlimited', 'Bez omezení');
$pin_permanent = $tr('pin_permanent', 'Trvalý přístup');
$pin_expired = $tr('pin_expired', 'Vypršelo');
$pin_expired_ago = $tr('pin_expired_ago', 'Před');
$pin_remaining = $tr('pin_remaining', 'Zbývá');
$pin_extend_24h = $tr('pin_extend_24h', '+24h');
$pin_extend_48h = $tr('pin_extend_48h', '+48h');
$pin_extend_7d = $tr('pin_extend_7d', '+7 dní');
$pin_extend_manual = $tr('pin_extend_manual', 'Ručně');
$pin_set_expiry = $tr('pin_set_expiry', 'Nastavit novou expiraci');
$pin_save = $tr('pin_save', 'Uložit');
$pin_back = $tr('pin_back', 'Zpět');
$pin_not_generated = $tr('pin_not_generated', 'PIN kód nebyl vygenerován.');
$pin_generate = $tr('pin_generate', 'Vygenerovat PIN');

// ============================================
// TOKEN LABELS
// ============================================
$token_label = $tr('token_label', 'Registrační odkaz');
$token_copy = $tr('token_copy', 'Kopírovat odkaz');
$token_copied = $tr('token_copied', 'Zkopírováno');
$token_status = $tr('token_status', 'Stav');
$token_expiration = $tr('token_expiration', 'Expirace');
$token_unlimited = $tr('token_unlimited', 'Bez omezení');
$token_permanent = $tr('token_permanent', 'Trvalý odkaz');
$token_expired = $tr('token_expired', 'Vypršelo');
$token_expired_ago = $tr('token_expired_ago', 'Před');
$token_remaining = $tr('token_remaining', 'Zbývá');
$token_extend_24h = $tr('token_extend_24h', '+24h');
$token_extend_3d = $tr('token_extend_3d', '+3 dny');
$token_extend_7d = $tr('token_extend_7d', '+7 dní');
$token_extend_manual = $tr('token_extend_manual', 'Ručně');
$token_set_expiry = $tr('token_set_expiry', 'Nastavit novou expiraci');
$token_not_generated = $tr('token_not_generated', 'Registrační odkaz nebyl vygenerován.');
$token_open = $tr('token_open', 'Otevřít');

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
// BUILD INVITATION URL
// ============================================
$invitation_url = '';
$invitation_url_short = '';
if (!empty($item['invitation_token'])) {
    $invitation_url = home_url('/visitor-invitation/' . $item['invitation_token'] . '/');
    // Shorten URL for display
    $invitation_url_short = str_replace(['https://', 'http://'], '', $invitation_url);
    if (strlen($invitation_url_short) > 40) {
        $invitation_url_short = substr($invitation_url_short, 0, 37) . '...';
    }
}

// ============================================
// PIN SECTION
// ============================================
if (!empty($item['pin_code'])): 
    $js_expiry_ts = 0;
    if (!empty($item['pin_expires_at'])) {
        $exp_dt = new DateTime($item['pin_expires_at'], $prague_tz);
        $js_expiry_ts = $exp_dt->getTimestamp() * 1000;
    }
?>

<div class="saw-info-item" style="grid-column: 1 / -1; width: 100%;">
    <label style="margin-bottom: 10px; display:block; font-weight:600; color:#475569;">
        🔐 <?php echo esc_html($pin_label); ?>
    </label>
    
    <div class="saw-pin-modern-card" 
         id="pin-card-<?php echo $item['id']; ?>" 
         data-current-expiry="<?php echo $js_expiry_ts; ?>">
        
        <div class="saw-pin-display-box" 
             onclick="copyPinToClipboard('<?php echo esc_js($item['pin_code']); ?>', <?php echo $item['id']; ?>)">
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
                <span class="saw-badge-text"><?php echo esc_html($pin_copy); ?></span>
            </div>
        </div>
        
        <?php 
        $expiry_status_class = '';
        $expiry_text_main = $pin_unlimited;
        $expiry_text_sub = $pin_permanent;
        $dot_class = 'valid';
        
        $calendar_val = (clone $now_dt)->modify('+24 hours')->format('Y-m-d\TH:i');

        if (!empty($item['pin_expires_at'])) {
            $expiry_dt = new DateTime($item['pin_expires_at'], $prague_tz);
            $current_now = new DateTime('now', $prague_tz);
            
            if ($expiry_dt < $current_now) {
                $diff = $current_now->diff($expiry_dt);
                $expiry_text_main = $pin_expired;
                $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                $expiry_text_sub = $pin_expired_ago . " " . $duration;
                $dot_class = 'expired';
                $expiry_status_class = 'expired';
                $calendar_val = (clone $current_now)->modify('+24 hours')->format('Y-m-d\TH:i');
            } else {
                $diff = $expiry_dt->diff($current_now);
                $total_hours = ($diff->days * 24) + $diff->h;
                
                $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                $expiry_text_main = $pin_remaining . " " . $duration;
                $expiry_text_sub = $expiry_dt->format('d.m.Y H:i');
                
                $dot_class = ($total_hours < 6) ? 'warning' : 'valid';
                $expiry_status_class = ($total_hours < 6) ? 'warning' : 'valid';
                
                $calendar_val = (clone $expiry_dt)->modify('+24 hours')->format('Y-m-d\TH:i');
            }
        }
        ?>
        
        <div class="saw-pin-status-bar">
            <div class="saw-pin-status-info">
                <div class="saw-status-dot <?php echo $dot_class; ?>"></div>
                <div class="saw-pin-status-text">
                    <span class="saw-pin-status-title"><?php echo esc_html($pin_status); ?></span>
                    <span class="saw-pin-status-value <?php echo $expiry_status_class; ?>" 
                          id="status-val-<?php echo $item['id']; ?>">
                        <?php echo esc_html($expiry_text_main); ?>
                    </span>
                </div>
            </div>
            <div class="saw-pin-status-text saw-pin-status-right">
                <span class="saw-pin-status-title"><?php echo esc_html($pin_expiration); ?></span>
                <span style="font-size: 13px; color: #64748b; font-weight: 500;" 
                      id="expiry-val-<?php echo $item['id']; ?>">
                    <?php echo esc_html($expiry_text_sub); ?>
                </span>
            </div>
        </div>

        <?php if ($item['status'] !== 'cancelled'): ?>
        
        <div id="pin-extend-buttons-<?php echo $item['id']; ?>" class="saw-pin-buttons-wrapper">
            <div class="saw-pin-actions-grid">
                <button type="button" 
                        class="saw-pin-action-btn" 
                        onclick="extendPinQuick(<?php echo $item['id']; ?>, 24)">
                    <span>🔄</span> <?php echo esc_html($pin_extend_24h); ?>
                </button>
                <button type="button" 
                        class="saw-pin-action-btn" 
                        onclick="extendPinQuick(<?php echo $item['id']; ?>, 48)">
                    <span>⏱️</span> <?php echo esc_html($pin_extend_48h); ?>
                </button>
                <button type="button" 
                        class="saw-pin-action-btn" 
                        onclick="extendPinQuick(<?php echo $item['id']; ?>, 168)">
                    <span>📅</span> <?php echo esc_html($pin_extend_7d); ?>
                </button>
                <button type="button" 
                        class="saw-pin-action-btn primary" 
                        onclick="showExtendPinForm(<?php echo $item['id']; ?>)">
                    <span>⚙️</span> <?php echo esc_html($pin_extend_manual); ?>
                </button>
            </div>
        </div>

        <div id="pin-extend-form-<?php echo $item['id']; ?>" 
             style="display: none; width: 100%;">
            <div class="saw-pin-custom-form">
                <div class="saw-pin-form-title">
                    <span>📆</span> <?php echo esc_html($pin_set_expiry); ?>
                </div>
                
                <input type="datetime-local" 
                       id="pin-expiry-datetime-<?php echo $item['id']; ?>" 
                       class="saw-pin-datetime-input"
                       value="<?php echo $calendar_val; ?>">
                
                <div class="saw-pin-actions-grid">
                    <button type="button" 
                            class="saw-pin-action-btn" 
                            style="background:#10b981; color:white; border:none; justify-content: center;" 
                            onclick="extendPinCustom(<?php echo $item['id']; ?>)">
                        <span>✅</span> <?php echo esc_html($pin_save); ?>
                    </button>
                    <button type="button" 
                            class="saw-pin-action-btn" 
                            style="justify-content: center;" 
                            onclick="hideExtendPinForm(<?php echo $item['id']; ?>)">
                        <span>❌</span> <?php echo esc_html($pin_back); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php 
// Show generate PIN button if no PIN and visit is planned
if (empty($item['pin_code']) && 
    $item['status'] !== 'cancelled' && 
    ($item['visit_type'] ?? '') === 'planned'): 
?>
<div class="saw-info-item" style="grid-column: 1 / -1; width: 100%;">
    <div class="saw-pin-modern-card">
        <div class="saw-pin-empty-state">
            <div class="saw-pin-empty-icon">🔓</div>
            <p style="margin:0 0 20px 0; color:#94a3b8; font-size:14px; line-height: 1.5;">
                <?php echo esc_html($pin_not_generated); ?>
            </p>
            <button type="button" 
                    class="saw-pin-generate-btn" 
                    onclick="generatePin(<?php echo $item['id']; ?>)">
                <span>✨</span> <?php echo esc_html($pin_generate); ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>


<?php
// ============================================
// TOKEN SECTION
// ============================================
if (!empty($item['invitation_token'])): 
    $js_token_expiry_ts = 0;
    if (!empty($item['invitation_token_expires_at'])) {
        $token_exp_dt = new DateTime($item['invitation_token_expires_at'], $prague_tz);
        $js_token_expiry_ts = $token_exp_dt->getTimestamp() * 1000;
    }
?>

<div class="saw-info-item" style="grid-column: 1 / -1; width: 100%;">
    <label style="margin-bottom: 10px; display:block; font-weight:600; color:#475569;">
        🔗 <?php echo esc_html($token_label); ?>
    </label>
    
    <div class="saw-token-modern-card" 
         id="token-card-<?php echo $item['id']; ?>" 
         data-current-expiry="<?php echo $js_token_expiry_ts; ?>">
        
        <!-- Token Display Box -->
        <div class="saw-token-display-box">
            <div class="saw-token-left-section">
                <div class="saw-token-icon-wrapper">
                    <span>🌐</span>
                </div>
                <div class="saw-token-url-display" id="token-url-<?php echo $item['id']; ?>" title="<?php echo esc_attr($invitation_url); ?>">
                    <?php echo esc_html($invitation_url_short); ?>
                </div>
            </div>
            <div class="saw-token-actions">
                <a href="<?php echo esc_url($invitation_url); ?>" 
                   target="_blank" 
                   class="saw-token-open-btn"
                   title="<?php echo esc_attr($token_open); ?>">
                    <span>↗️</span>
                </a>
                <button type="button" 
                        class="saw-token-copy-badge" 
                        id="token-badge-<?php echo $item['id']; ?>"
                        onclick="copyUrlToClipboard('<?php echo esc_js($invitation_url); ?>', <?php echo $item['id']; ?>)">
                    <span>📋</span> 
                    <span class="saw-badge-text"><?php echo esc_html($token_copy); ?></span>
                </button>
            </div>
        </div>
        
        <?php 
        // Token expiry status
        $token_expiry_status_class = '';
        $token_expiry_text_main = $token_unlimited;
        $token_expiry_text_sub = $token_permanent;
        $token_dot_class = 'valid';
        
        $token_calendar_val = (clone $now_dt)->modify('+24 hours')->format('Y-m-d\TH:i');

        if (!empty($item['invitation_token_expires_at'])) {
            $token_expiry_dt = new DateTime($item['invitation_token_expires_at'], $prague_tz);
            $current_now = new DateTime('now', $prague_tz);
            
            if ($token_expiry_dt < $current_now) {
                $diff = $current_now->diff($token_expiry_dt);
                $token_expiry_text_main = $token_expired;
                $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                $token_expiry_text_sub = $token_expired_ago . " " . $duration;
                $token_dot_class = 'expired';
                $token_expiry_status_class = 'expired';
                $token_calendar_val = (clone $current_now)->modify('+24 hours')->format('Y-m-d\TH:i');
            } else {
                $diff = $token_expiry_dt->diff($current_now);
                $total_hours = ($diff->days * 24) + $diff->h;
                
                $duration = saw_format_czech_duration_tr($diff, $day_singular, $day_few, $day_many, $hour_singular, $hour_few, $hour_many, $min_label);
                $token_expiry_text_main = $token_remaining . " " . $duration;
                $token_expiry_text_sub = $token_expiry_dt->format('d.m.Y H:i');
                
                $token_dot_class = ($total_hours < 24) ? 'warning' : 'valid';
                $token_expiry_status_class = ($total_hours < 24) ? 'warning' : 'valid';
                
                $token_calendar_val = (clone $token_expiry_dt)->modify('+24 hours')->format('Y-m-d\TH:i');
            }
        }
        ?>
        
        <!-- Token Status Bar -->
        <div class="saw-token-status-bar">
            <div class="saw-token-status-info">
                <div class="saw-status-dot <?php echo $token_dot_class; ?>"></div>
                <div class="saw-token-status-text">
                    <span class="saw-token-status-title"><?php echo esc_html($token_status); ?></span>
                    <span class="saw-token-status-value <?php echo $token_expiry_status_class; ?>" 
                          id="token-status-val-<?php echo $item['id']; ?>">
                        <?php echo esc_html($token_expiry_text_main); ?>
                    </span>
                </div>
            </div>
            <div class="saw-token-status-text saw-token-status-right">
                <span class="saw-token-status-title"><?php echo esc_html($token_expiration); ?></span>
                <span style="font-size: 13px; color: #64748b; font-weight: 500;" 
                      id="token-expiry-val-<?php echo $item['id']; ?>">
                    <?php echo esc_html($token_expiry_text_sub); ?>
                </span>
            </div>
        </div>

        <?php if ($item['status'] !== 'cancelled'): ?>
        
        <!-- Token Extend Buttons -->
        <div id="token-extend-buttons-<?php echo $item['id']; ?>" class="saw-token-buttons-wrapper">
            <div class="saw-token-actions-grid">
                <button type="button" 
                        class="saw-token-action-btn" 
                        onclick="extendTokenQuick(<?php echo $item['id']; ?>, 24)">
                    <span>🔄</span> <?php echo esc_html($token_extend_24h); ?>
                </button>
                <button type="button" 
                        class="saw-token-action-btn" 
                        onclick="extendTokenQuick(<?php echo $item['id']; ?>, 72)">
                    <span>⏱️</span> <?php echo esc_html($token_extend_3d); ?>
                </button>
                <button type="button" 
                        class="saw-token-action-btn" 
                        onclick="extendTokenQuick(<?php echo $item['id']; ?>, 168)">
                    <span>📅</span> <?php echo esc_html($token_extend_7d); ?>
                </button>
                <button type="button" 
                        class="saw-token-action-btn primary" 
                        onclick="showExtendTokenForm(<?php echo $item['id']; ?>)">
                    <span>⚙️</span> <?php echo esc_html($token_extend_manual); ?>
                </button>
            </div>
        </div>

        <!-- Token Custom Expiry Form -->
        <div id="token-extend-form-<?php echo $item['id']; ?>" 
             style="display: none; width: 100%;">
            <div class="saw-token-custom-form">
                <div class="saw-token-form-title">
                    <span>📆</span> <?php echo esc_html($token_set_expiry); ?>
                </div>
                
                <input type="datetime-local" 
                       id="token-expiry-datetime-<?php echo $item['id']; ?>" 
                       class="saw-token-datetime-input"
                       value="<?php echo $token_calendar_val; ?>">
                
                <div class="saw-token-actions-grid">
                    <button type="button" 
                            class="saw-token-action-btn" 
                            style="background:#10b981; color:white; border:none; justify-content: center;" 
                            onclick="extendTokenCustom(<?php echo $item['id']; ?>)">
                        <span>✅</span> <?php echo esc_html($pin_save); ?>
                    </button>
                    <button type="button" 
                            class="saw-token-action-btn" 
                            style="justify-content: center;" 
                            onclick="hideExtendTokenForm(<?php echo $item['id']; ?>)">
                        <span>❌</span> <?php echo esc_html($pin_back); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php 
// Show message if no token and visit is planned
if (empty($item['invitation_token']) && 
    $item['status'] !== 'cancelled' && 
    ($item['visit_type'] ?? '') === 'planned'): 
?>
<div class="saw-info-item" style="grid-column: 1 / -1; width: 100%;">
    <div class="saw-token-modern-card saw-token-empty">
        <div class="saw-token-empty-state">
            <div class="saw-token-empty-icon">🔗</div>
            <p style="margin:0; color:#94a3b8; font-size:14px; line-height: 1.5;">
                <?php echo esc_html($token_not_generated); ?>
            </p>
            <p style="margin:8px 0 0 0; color:#64748b; font-size:12px;">
                <?php echo esc_html($tr('token_generate_hint', 'Token se vygeneruje automaticky při odeslání pozvánky.')); ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ============================================ -->
<!-- JAVASCRIPT                                   -->
<!-- ============================================ -->
<script>
// ============================================
// PIN FUNCTIONS
// ============================================
if (typeof copyPinToClipboard === 'undefined') {
    function copyPinToClipboard(pin, visitId) {
        if (!pin) return;
        
        const displayEl = document.getElementById('pin-code-' + visitId);
        const badgeEl = document.getElementById('pin-badge-' + visitId);
        
        if (!displayEl || !badgeEl) return;
        
        const originalText = displayEl.innerText;
        const originalBadge = badgeEl.innerHTML;
        
        navigator.clipboard.writeText(pin).then(() => {
            displayEl.innerText = "✓";
            displayEl.classList.add('copied');
            badgeEl.classList.add('copied');
            
            const badgeText = badgeEl.querySelector('.saw-badge-text');
            if (badgeText && getComputedStyle(badgeText).display !== 'none') {
                badgeText.innerText = "<?php echo esc_js($pin_copied); ?>";
            }
            
            if (window.navigator && window.navigator.vibrate) {
                window.navigator.vibrate([50]);
            }
            
            setTimeout(() => {
                displayEl.classList.remove('copied');
                displayEl.innerText = originalText;
                badgeEl.classList.remove('copied');
                badgeEl.innerHTML = originalBadge;
            }, 1500);
        }).catch(err => {
            console.error('Copy error:', err);
            // Fallback for older browsers
            fallbackCopyToClipboard(pin);
        });
    }
}

// ============================================
// TOKEN FUNCTIONS
// ============================================
if (typeof copyUrlToClipboard === 'undefined') {
    function copyUrlToClipboard(url, visitId) {
        if (!url) return;
        
        const badgeEl = document.getElementById('token-badge-' + visitId);
        if (!badgeEl) return;
        
        const originalBadge = badgeEl.innerHTML;
        
        navigator.clipboard.writeText(url).then(() => {
            badgeEl.classList.add('copied');
            
            const badgeText = badgeEl.querySelector('.saw-badge-text');
            if (badgeText) {
                badgeText.innerText = "<?php echo esc_js($token_copied); ?>";
            }
            
            if (window.navigator && window.navigator.vibrate) {
                window.navigator.vibrate([50]);
            }
            
            setTimeout(() => {
                badgeEl.classList.remove('copied');
                badgeEl.innerHTML = originalBadge;
            }, 1500);
        }).catch(err => {
            console.error('Copy error:', err);
            // Fallback for older browsers
            fallbackCopyToClipboard(url);
        });
    }
}

// Fallback copy function for older browsers
if (typeof fallbackCopyToClipboard === 'undefined') {
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('<?php echo esc_js($tr('copied_fallback', 'Zkopírováno do schránky')); ?>');
        } catch (err) {
            console.error('Fallback copy failed:', err);
            alert('<?php echo esc_js($tr('copy_failed', 'Kopírování selhalo. Zkopírujte ručně.')); ?>');
        }
        
        document.body.removeChild(textArea);
    }
}

// ============================================
// TOKEN EXTEND FUNCTIONS
// ============================================
if (typeof extendTokenQuick === 'undefined') {
    function extendTokenQuick(visitId, hours) {
        var card = document.getElementById('token-card-' + visitId);
        if (!card) {
            alert('<?php echo esc_js($tr('alert_error', 'Chyba')); ?>: Nenalezen Token element');
            return;
        }
        
        var currentExpiry = parseInt(card.getAttribute('data-current-expiry'));
        var now = Date.now();
        var baseTime;
        
        if (currentExpiry && currentExpiry > now) {
            baseTime = currentExpiry;
        } else {
            baseTime = now;
        }
        
        var newTimeMs = baseTime + (hours * 3600 * 1000);
        var newDate = new Date(newTimeMs);
        newDate.setSeconds(0, 0);
        
        var displayDate = formatDateTime(newDate);
        
        if (!confirm('<?php echo esc_js($tr('confirm_extend_token', 'Prodloužit platnost odkazu o')); ?> ' + hours + ' <?php echo esc_js($tr('hours', 'hodin')); ?>?\n\n<?php echo esc_js($tr('new_expiration', 'Nová expirace')); ?>: ' + displayDate)) {
            return;
        }
        
        var sqlDate = formatSQLDateTime(newDate);
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
                var card = document.getElementById('token-card-' + visitId);
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
        var form = document.getElementById('token-extend-form-' + visitId);
        form.style.display = 'block';
        var datetimeInput = document.getElementById('token-expiry-datetime-' + visitId);
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
        var datetimeInput = document.getElementById('token-expiry-datetime-' + visitId);
        var datetimeValue = datetimeInput.value;
        
        if (!datetimeValue) {
            alert('<?php echo esc_js($tr('enter_datetime', 'Prosím zadejte datum a čas.')); ?>');
            return;
        }
        
        var newDate = new Date(datetimeValue);
        
        if (isNaN(newDate.getTime())) {
            alert('<?php echo esc_js($tr('invalid_date', 'Neplatné datum')); ?>');
            return;
        }
        
        var displayDate = formatDateTime(newDate);
        
        if (!confirm('<?php echo esc_js($tr('confirm_set_expiry', 'Nastavit expiraci odkazu na')); ?>:\n' + displayDate + '?')) {
            return;
        }
        
        var sqlDate = formatSQLDateTime(newDate);
        extendTokenToExactTime(visitId, sqlDate, newDate);
    }
}

// ============================================
// HELPER FUNCTIONS (shared)
// ============================================
if (typeof formatDateTime === 'undefined') {
    function formatDateTime(date) {
        var d = date.getDate().toString().padStart(2, '0');
        var m = (date.getMonth() + 1).toString().padStart(2, '0');
        var y = date.getFullYear();
        var h = date.getHours().toString().padStart(2, '0');
        var min = date.getMinutes().toString().padStart(2, '0');
        return d + '.' + m + '.' + y + ' ' + h + ':' + min;
    }
}

if (typeof formatSQLDateTime === 'undefined') {
    function formatSQLDateTime(date) {
        var year = date.getFullYear();
        var month = (date.getMonth() + 1).toString().padStart(2, '0');
        var day = date.getDate().toString().padStart(2, '0');
        var hours = date.getHours().toString().padStart(2, '0');
        var minutes = date.getMinutes().toString().padStart(2, '0');
        var seconds = date.getSeconds().toString().padStart(2, '0');
        return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
    }
}
</script>


<!-- ============================================ -->
<!-- STYLES                                       -->
<!-- ============================================ -->
<style>
:root {
    --saw-primary: #4f46e5;
    --saw-success: #10b981;
    --saw-warning: #f59e0b;
    --saw-danger: #ef4444;
    --saw-link: #0ea5e9;
    --saw-text-main: #1e293b;
    --saw-text-muted: #64748b;
    --saw-bg-glass: rgba(255, 255, 255, 0.95);
    --saw-border-light: rgba(226, 232, 240, 0.8);
}

.saw-info-item, 
.saw-pin-modern-card *,
.saw-token-modern-card * {
    box-sizing: border-box !important;
}

/* ============================================
   PIN CARD STYLES
   ============================================ */
.saw-pin-modern-card {
    background: var(--saw-bg-glass);
    border: 1px solid var(--saw-border-light);
    border-radius: 20px;
    padding: 24px;
    margin-top: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    position: relative;
    overflow: hidden;
    width: 100%;
}

.saw-pin-modern-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
}

.saw-pin-display-box {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    width: 100%;
    transition: transform 0.2s ease;
}

.saw-pin-display-box:active {
    transform: scale(0.98);
}

.saw-pin-left-section {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.saw-pin-icon-wrapper {
    width: 40px;
    height: 40px;
    background: #e0e7ff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--saw-primary);
    flex-shrink: 0;
}

.saw-pin-code-display {
    font-family: 'SF Mono', 'Menlo', 'Monaco', monospace;
    font-size: 32px;
    font-weight: 800;
    color: var(--saw-text-main);
    letter-spacing: 0.05em;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.saw-pin-copy-badge {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 99px;
    color: var(--saw-text-muted);
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.saw-pin-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
}

.saw-pin-status-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.saw-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.saw-status-dot.valid {
    background: var(--saw-success);
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.saw-status-dot.expired {
    background: var(--saw-danger);
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
}

.saw-status-dot.warning {
    background: var(--saw-warning);
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

.saw-pin-status-text {
    display: flex;
    flex-direction: column;
}

.saw-pin-status-title {
    font-size: 10px;
    text-transform: uppercase;
    color: var(--saw-text-muted);
    font-weight: 700;
    margin-bottom: 2px;
}

.saw-pin-status-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--saw-text-main);
}

.saw-pin-status-value.expired {
    color: var(--saw-danger);
}

.saw-pin-status-value.warning {
    color: var(--saw-warning);
}

.saw-pin-status-right {
    text-align: right;
    margin-left: auto;
}

.saw-pin-buttons-wrapper {
    width: 100%;
    display: block;
}

.saw-pin-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    width: 100%;
}

.saw-pin-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 4px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
    color: var(--saw-text-muted);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    width: 100%;
    min-width: 0;
    min-height: 44px;
    white-space: nowrap;
    transition: all 0.2s ease;
    font-family: inherit;
}

.saw-pin-action-btn:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.saw-pin-action-btn:active {
    transform: translateY(0);
}

.saw-pin-action-btn.primary {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    border: none;
    color: white;
}

.saw-pin-action-btn.primary:hover {
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.saw-pin-custom-form {
    margin-top: 16px;
    padding: 16px;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 14px;
    width: 100%;
}

.saw-pin-form-title {
    font-weight: 700;
    color: #92400e;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.saw-pin-datetime-input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #fbbf24;
    border-radius: 10px;
    margin-bottom: 12px;
    background: white;
    font-size: 16px;
    color: #333;
    font-family: inherit;
}

.saw-pin-datetime-input:focus {
    outline: none;
    border-color: var(--saw-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.saw-pin-empty-state {
    text-align: center;
    padding: 20px 10px;
}

.saw-pin-empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.saw-pin-generate-btn {
    background: var(--saw-primary);
    color: white;
    padding: 12px 20px;
    border-radius: 99px;
    border: none;
    font-weight: 700;
    width: 100%;
    max-width: 250px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
}

.saw-pin-generate-btn:hover {
    background: #4338ca;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.saw-pin-code-display.copied {
    color: var(--saw-success);
    font-family: sans-serif;
    animation: sawPop 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.saw-pin-copy-badge.copied {
    background: var(--saw-success) !important;
    color: white !important;
    border-color: var(--saw-success) !important;
}

@keyframes sawPop {
    0% { transform: scale(0.5); opacity: 0.5; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

/* ============================================
   TOKEN CARD STYLES
   ============================================ */
.saw-token-modern-card {
    background: var(--saw-bg-glass);
    border: 1px solid var(--saw-border-light);
    border-radius: 20px;
    padding: 24px;
    margin-top: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    position: relative;
    overflow: hidden;
    width: 100%;
}

.saw-token-modern-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #0ea5e9, #06b6d4, #14b8a6);
}

.saw-token-display-box {
    background: linear-gradient(145deg, #ffffff 0%, #f0f9ff 100%);
    border: 1px solid #bae6fd;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    width: 100%;
}

.saw-token-left-section {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.saw-token-icon-wrapper {
    width: 40px;
    height: 40px;
    background: #e0f2fe;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--saw-link);
    flex-shrink: 0;
}

.saw-token-url-display {
    font-family: 'SF Mono', 'Menlo', 'Monaco', monospace;
    font-size: 13px;
    font-weight: 500;
    color: var(--saw-link);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.saw-token-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.saw-token-open-btn {
    width: 36px;
    height: 36px;
    background: white;
    border: 1px solid #bae6fd;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: var(--saw-link);
    text-decoration: none;
    transition: all 0.2s ease;
}

.saw-token-open-btn:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
    transform: translateY(-1px);
}

.saw-token-copy-badge {
    padding: 8px 12px;
    background: white;
    border: 1px solid #bae6fd;
    border-radius: 99px;
    color: var(--saw-link);
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
}

.saw-token-copy-badge:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
}

.saw-token-copy-badge.copied {
    background: var(--saw-success) !important;
    color: white !important;
    border-color: var(--saw-success) !important;
}

.saw-token-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px;
    background: #f0f9ff;
    border: 1px solid #e0f2fe;
    border-radius: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
}

.saw-token-status-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.saw-token-status-text {
    display: flex;
    flex-direction: column;
}

.saw-token-status-title {
    font-size: 10px;
    text-transform: uppercase;
    color: var(--saw-text-muted);
    font-weight: 700;
    margin-bottom: 2px;
}

.saw-token-status-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--saw-text-main);
}

.saw-token-status-value.expired {
    color: var(--saw-danger);
}

.saw-token-status-value.warning {
    color: var(--saw-warning);
}

.saw-token-status-right {
    text-align: right;
    margin-left: auto;
}

.saw-token-buttons-wrapper {
    width: 100%;
    display: flex;
}

.saw-token-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    width: 100%;
}

.saw-token-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 4px;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    background: white;
    color: var(--saw-link);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    width: 100%;
    min-width: 0;
    min-height: 44px;
    white-space: nowrap;
    transition: all 0.2s ease;
    font-family: inherit;
}

.saw-token-action-btn:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
    transform: translateY(-1px);
}

.saw-token-action-btn:active {
    transform: translateY(0);
}

.saw-token-action-btn.primary {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border: none;
    color: white;
}

.saw-token-action-btn.primary:hover {
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.saw-token-custom-form {
    margin-top: 16px;
    padding: 16px;
    background: #ecfeff;
    border: 1px solid #67e8f9;
    border-radius: 14px;
    width: 100%;
}

.saw-token-form-title {
    font-weight: 700;
    color: #0e7490;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.saw-token-datetime-input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #22d3ee;
    border-radius: 10px;
    margin-bottom: 12px;
    background: white;
    font-size: 16px;
    color: #333;
    font-family: inherit;
}

.saw-token-datetime-input:focus {
    outline: none;
    border-color: var(--saw-link);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.saw-token-empty-state {
    text-align: center;
    padding: 20px 10px;
}

.saw-token-empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.saw-token-modern-card.saw-token-empty {
    background: #f8fafc;
    border-style: dashed;
}

.saw-token-modern-card.saw-token-empty::before {
    background: linear-gradient(90deg, #cbd5e1, #94a3b8, #cbd5e1);
}

/* ============================================
   RESPONSIVE STYLES
   ============================================ */
@media (max-width: 420px) {
    .saw-pin-modern-card,
    .saw-token-modern-card { 
        padding: 16px 12px; 
    }
    
    .saw-pin-code-display { 
        font-size: 24px; 
        letter-spacing: 0; 
    }
    
    .saw-badge-text { 
        display: none; 
    }
    
    .saw-pin-copy-badge,
    .saw-token-copy-badge { 
        padding: 8px; 
        border-radius: 8px; 
    }
    
    .saw-pin-status-bar,
    .saw-token-status-bar { 
        flex-direction: column; 
        align-items: flex-start; 
        gap: 12px; 
    }
    
    .saw-pin-status-right,
    .saw-token-status-right { 
        text-align: left; 
        margin-left: 0; 
        width: 100%; 
        padding-top: 10px; 
        border-top: 1px dashed #cbd5e1; 
    }
    
    .saw-pin-status-value,
    .saw-token-status-value { 
        white-space: normal; 
        line-height: 1.4; 
    }
    
    .saw-pin-actions-grid,
    .saw-token-actions-grid { 
        grid-template-columns: 1fr; 
        gap: 8px; 
    }
    
    .saw-pin-action-btn,
    .saw-token-action-btn { 
        justify-content: flex-start; 
        padding-left: 16px; 
    }
    
    .saw-token-display-box {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .saw-token-actions {
        justify-content: flex-end;
    }
    
    .saw-token-url-display {
        font-size: 11px;
    }
}

@media (max-width: 380px) {
    .saw-pin-modern-card,
    .saw-token-modern-card { 
        padding: 12px 8px; 
        border-radius: 16px; 
    }
    
    .saw-pin-display-box,
    .saw-token-display-box { 
        padding: 12px; 
        gap: 8px; 
    }
    
    .saw-pin-icon-wrapper,
    .saw-token-icon-wrapper { 
        width: 36px; 
        height: 36px; 
        font-size: 16px; 
    }
    
    .saw-pin-code-display { 
        font-size: 22px; 
    }
    
    .saw-pin-status-bar,
    .saw-token-status-bar { 
        padding: 12px; 
    }
    
    .saw-pin-status-title,
    .saw-token-status-title { 
        font-size: 9px; 
    }
    
    .saw-pin-status-value,
    .saw-token-status-value { 
        font-size: 12px; 
    }
    
    .saw-pin-custom-form,
    .saw-token-custom-form { 
        padding: 12px; 
    }
    
    .saw-pin-action-btn,
    .saw-token-action-btn { 
        font-size: 12px; 
        min-height: 40px; 
        padding: 10px 12px; 
    }
}

@media (max-width: 340px) {
    .saw-pin-code-display { 
        font-size: 20px; 
    }
    
    .saw-pin-action-btn span:first-child,
    .saw-token-action-btn span:first-child { 
        font-size: 14px; 
    }
    
    .saw-pin-form-title,
    .saw-token-form-title { 
        font-size: 13px; 
    }
}
</style>