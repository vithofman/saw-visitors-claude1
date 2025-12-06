<?php
/**
 * PIN Section - Detail Sidebar Part
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits/Parts
 * @version     4.2.0
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

// Pre-load labels for JavaScript
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

<script>
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
        });
    }
}
</script>

<style>
:root {
    --saw-primary: #4f46e5;
    --saw-success: #10b981;
    --saw-warning: #f59e0b;
    --saw-danger: #ef4444;
    --saw-text-main: #1e293b;
    --saw-text-muted: #64748b;
    --saw-bg-glass: rgba(255, 255, 255, 0.95);
    --saw-border-light: rgba(226, 232, 240, 0.8);
}

.saw-info-item, 
.saw-pin-modern-card * {
    box-sizing: border-box !important;
}

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

@media (max-width: 420px) {
    .saw-pin-modern-card { padding: 16px 12px; }
    .saw-pin-code-display { font-size: 24px; letter-spacing: 0; }
    .saw-badge-text { display: none; }
    .saw-pin-copy-badge { padding: 8px; border-radius: 8px; }
    .saw-pin-status-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
    .saw-pin-status-right { text-align: left; margin-left: 0; width: 100%; padding-top: 10px; border-top: 1px dashed #cbd5e1; }
    .saw-pin-status-value { white-space: normal; line-height: 1.4; }
    .saw-pin-actions-grid { grid-template-columns: 1fr; gap: 8px; }
    .saw-pin-action-btn { justify-content: flex-start; padding-left: 16px; }
}

@media (max-width: 380px) {
    .saw-pin-modern-card { padding: 12px 8px; border-radius: 16px; }
    .saw-pin-display-box { padding: 12px; gap: 8px; }
    .saw-pin-icon-wrapper { width: 36px; height: 36px; font-size: 16px; }
    .saw-pin-code-display { font-size: 22px; }
    .saw-pin-status-bar { padding: 12px; }
    .saw-pin-status-title { font-size: 9px; }
    .saw-pin-status-value { font-size: 12px; }
    .saw-pin-custom-form { padding: 12px; }
    .saw-pin-action-btn { font-size: 12px; min-height: 40px; padding: 10px 12px; }
}

@media (max-width: 340px) {
    .saw-pin-code-display { font-size: 20px; }
    .saw-pin-action-btn span:first-child { font-size: 14px; }
    .saw-pin-form-title { font-size: 13px; }
}
</style>