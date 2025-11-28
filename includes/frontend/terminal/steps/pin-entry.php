<?php
/**
 * Terminal Step - PIN Entry (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Zadejte PIN kód',
        'subtitle' => 'PIN kód jste obdrželi v emailu',
        'clear' => 'Smazat',
        'backspace' => '⌫',
        'submit' => 'Potvrdit',
    ],
    'en' => [
        'title' => 'Enter PIN Code',
        'subtitle' => 'You received the PIN code via email',
        'clear' => 'Clear',
        'backspace' => '⌫',
        'submit' => 'Submit',
    ],
    'sk' => [
        'title' => 'Zadajte PIN kód',
        'subtitle' => 'PIN kód ste obdržali v emaili',
        'clear' => 'Zmazať',
        'backspace' => '⌫',
        'submit' => 'Potvrdiť',
    ],
    'uk' => [
        'title' => 'Введіть PIN-код',
        'subtitle' => 'Ви отримали PIN-код електронною поштою',
        'clear' => 'Очистити',
        'backspace' => '⌫',
        'submit' => 'Підтвердити',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-pin">
    <div class="saw-page-content saw-page-content-centered">
        
        <!-- Header -->
        <div class="saw-page-header saw-page-header-centered">
            <div class="saw-header-icon">🔐</div>
            <h1 class="saw-header-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-header-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- PIN Display -->
        <div class="saw-pin-display">
            <div class="saw-pin-dots">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="saw-pin-dot" data-index="<?php echo $i; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Numpad -->
        <div class="saw-pin-numpad">
            <?php for ($i = 1; $i <= 9; $i++): ?>
            <button type="button" 
                    class="saw-pin-numpad-btn" 
                    data-value="<?php echo $i; ?>">
                <?php echo $i; ?>
            </button>
            <?php endfor; ?>
            
            <button type="button" class="saw-pin-numpad-btn clear">
                <?php echo esc_html($t['clear']); ?>
            </button>
            
            <button type="button" 
                    class="saw-pin-numpad-btn" 
                    data-value="0">
                0
            </button>
            
            <button type="button" class="saw-pin-numpad-btn backspace">
                <?php echo $t['backspace']; ?>
            </button>
        </div>
        
        <!-- Submit Form -->
        <form method="POST" id="pin-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="verify_pin">
            <input type="hidden" name="pin" id="pin-input" value="">
            
            <button type="submit" class="saw-btn saw-btn-primary" id="pin-submit" disabled>
                <?php echo esc_html($t['submit']); ?> →
            </button>
        </form>
        
    </div>
</div>

<!-- CSS je v pages.css - žádný inline <style> -->

<script>
(function() {
    'use strict';
    
    let pinValue = '';
    const maxLength = 6;
    
    const pinInput = document.getElementById('pin-input');
    const pinDots = document.querySelectorAll('.saw-pin-dot');
    const submitBtn = document.getElementById('pin-submit');
    const form = document.getElementById('pin-form');
    
    // Update display
    function updateDisplay() {
        pinDots.forEach((dot, index) => {
            if (index < pinValue.length) {
                dot.classList.add('filled');
            } else {
                dot.classList.remove('filled');
            }
        });
        
        pinInput.value = pinValue;
        submitBtn.disabled = pinValue.length !== maxLength;
        
        // Auto-submit when complete
        if (pinValue.length === maxLength) {
            setTimeout(() => {
                form.submit();
            }, 300);
        }
    }
    
    // Number buttons
    document.querySelectorAll('.saw-pin-numpad-btn[data-value]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (pinValue.length < maxLength) {
                pinValue += this.dataset.value;
                updateDisplay();
            }
        });
    });
    
    // Clear button
    document.querySelector('.saw-pin-numpad-btn.clear').addEventListener('click', function() {
        pinValue = '';
        updateDisplay();
    });
    
    // Backspace button
    document.querySelector('.saw-pin-numpad-btn.backspace').addEventListener('click', function() {
        pinValue = pinValue.slice(0, -1);
        updateDisplay();
    });
    
    // IMPROVED Keyboard support - autofocus on page load
    document.addEventListener('keydown', function(e) {
        // Only accept numbers
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            if (pinValue.length < maxLength) {
                pinValue += e.key;
                updateDisplay();
            }
        } 
        // Backspace
        else if (e.key === 'Backspace') {
            e.preventDefault();
            pinValue = pinValue.slice(0, -1);
            updateDisplay();
        } 
        // Escape = clear all
        else if (e.key === 'Escape') {
            e.preventDefault();
            pinValue = '';
            updateDisplay();
        } 
        // Enter = submit (if complete)
        else if (e.key === 'Enter' && pinValue.length === maxLength) {
            e.preventDefault();
            form.submit();
        }
    });
    
    // Auto-focus - make page ready for immediate typing
    window.addEventListener('load', function() {
        document.body.focus();
        console.log('[PIN] Page ready - you can start typing numbers');
    });
    
    // Initial state
    updateDisplay();
})();
</script>

<?php
error_log("[PIN-ENTRY.PHP] Unified design loaded (v3.3.0)");
?>

<!-- CSS blok byl odstraněn - všechny styly jsou v pages.css -->
    --theme-color-hover: #764ba2;
    --bg-dark: #1a202c;
    --bg-dark-medium: #2d3748;
    --bg-glass: rgba(15, 23, 42, 0.6);
    --bg-glass-light: rgba(255, 255, 255, 0.08);
    --border-glass: rgba(148, 163, 184, 0.12);
    --text-primary: #FFFFFF;
    --text-secondary: #e5e7eb;
    --text-muted: #9ca3af;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer {
    display: none !important;
}

/* Main container */
.saw-pin-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.saw-pin-content {
    max-width: 420px;
    width: 100%;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-pin-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.saw-pin-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-pin-title {
    font-size: 1.75rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
}

.saw-pin-subtitle {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* PIN Display */
.saw-pin-display {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}

.saw-pin-dots {
    display: flex;
    justify-content: center;
    gap: 0.875rem;
}

.saw-pin-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(148, 163, 184, 0.2);
    border: 2px solid rgba(148, 163, 184, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.saw-pin-dot.filled {
    background: linear-gradient(135deg, var(--theme-color), var(--theme-color-hover));
    border-color: var(--theme-color);
    box-shadow: 0 0 20px rgba(102, 126, 234, 0.6);
    transform: scale(1.1);
}

/* Numpad */
.saw-pin-numpad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.625rem;
    margin-bottom: 1.25rem;
}

.saw-pin-numpad-btn {
    height: 60px;
    border: none;
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 14px;
    border: 1px solid var(--border-glass);
    color: var(--text-primary);
    font-size: 1.375rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.saw-pin-numpad-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(102, 126, 234, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.3);
}

.saw-pin-numpad-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.saw-pin-numpad-btn.clear,
.saw-pin-numpad-btn.backspace {
    font-size: 0.9375rem;
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.saw-pin-numpad-btn.clear:hover,
.saw-pin-numpad-btn.backspace:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Submit Button */
.saw-pin-submit {
    width: 100%;
    padding: 0.875rem 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.saw-pin-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
}

.saw-pin-submit:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .saw-pin-aurora {
        padding: 1rem;
    }
    
    .saw-pin-icon {
        font-size: 3rem;
    }
    
    .saw-pin-title {
        font-size: 1.75rem;
    }
    
    .saw-pin-subtitle {
        font-size: 0.9375rem;
    }
    
    .saw-pin-display {
        padding: 1.5rem;
    }
    
    .saw-pin-dots {
        gap: 0.75rem;
    }
    
    .saw-pin-dot {
        width: 16px;
        height: 16px;
    }
    
    .saw-pin-numpad {
        gap: 0.5rem;
    }
    
    .saw-pin-numpad-btn {
        font-size: 1.25rem;
    }
    
    .saw-pin-submit {
        padding: 0.875rem 1.5rem;
        font-size: 1rem;
    }
}

/* Tablet */
@media (min-width: 769px) and (max-width: 1024px) {
    .saw-pin-content {
        max-width: 450px;
    }
}
</style>

<div class="saw-pin-aurora">
    <div class="saw-pin-content">
        
        <!-- Header -->
        <div class="saw-pin-header">
            <div class="saw-pin-icon">🔐</div>
            <h1 class="saw-pin-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-pin-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- PIN Display -->
        <div class="saw-pin-display">
            <div class="saw-pin-dots">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="saw-pin-dot" data-index="<?php echo $i; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Numpad -->
        <div class="saw-pin-numpad">
            <?php for ($i = 1; $i <= 9; $i++): ?>
            <button type="button" 
                    class="saw-pin-numpad-btn" 
                    data-value="<?php echo $i; ?>">
                <?php echo $i; ?>
            </button>
            <?php endfor; ?>
            
            <button type="button" class="saw-pin-numpad-btn clear">
                <?php echo esc_html($t['clear']); ?>
            </button>
            
            <button type="button" 
                    class="saw-pin-numpad-btn" 
                    data-value="0">
                0
            </button>
            
            <button type="button" class="saw-pin-numpad-btn backspace">
                <?php echo $t['backspace']; ?>
            </button>
        </div>
        
        <!-- Submit Form -->
        <form method="POST" id="pin-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="verify_pin">
            <input type="hidden" name="pin" id="pin-input" value="">
            
            <button type="submit" class="saw-pin-submit" id="pin-submit" disabled>
                <?php echo esc_html($t['submit']); ?> →
            </button>
        </form>
        
    </div>
</div>

<script>
(function() {
    'use strict';
    
    let pinValue = '';
    const maxLength = 6;
    
    const pinInput = document.getElementById('pin-input');
    const pinDots = document.querySelectorAll('.saw-pin-dot');
    const submitBtn = document.getElementById('pin-submit');
    const form = document.getElementById('pin-form');
    
    // Update display
    function updateDisplay() {
        pinDots.forEach((dot, index) => {
            if (index < pinValue.length) {
                dot.classList.add('filled');
            } else {
                dot.classList.remove('filled');
            }
        });
        
        pinInput.value = pinValue;
        submitBtn.disabled = pinValue.length !== maxLength;
        
        // Auto-submit when complete
        if (pinValue.length === maxLength) {
            setTimeout(() => {
                form.submit();
            }, 300);
        }
    }
    
    // Number buttons
    document.querySelectorAll('.saw-pin-numpad-btn[data-value]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (pinValue.length < maxLength) {
                pinValue += this.dataset.value;
                updateDisplay();
            }
        });
    });
    
    // Clear button
    document.querySelector('.saw-pin-numpad-btn.clear').addEventListener('click', function() {
        pinValue = '';
        updateDisplay();
    });
    
    // Backspace button
    document.querySelector('.saw-pin-numpad-btn.backspace').addEventListener('click', function() {
        pinValue = pinValue.slice(0, -1);
        updateDisplay();
    });
    
    // IMPROVED Keyboard support - autofocus on page load
    document.addEventListener('keydown', function(e) {
        // Only accept numbers
        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            if (pinValue.length < maxLength) {
                pinValue += e.key;
                updateDisplay();
            }
        } 
        // Backspace
        else if (e.key === 'Backspace') {
            e.preventDefault();
            pinValue = pinValue.slice(0, -1);
            updateDisplay();
        } 
        // Escape = clear all
        else if (e.key === 'Escape') {
            e.preventDefault();
            pinValue = '';
            updateDisplay();
        } 
        // Enter = submit (if complete)
        else if (e.key === 'Enter' && pinValue.length === maxLength) {
            e.preventDefault();
            form.submit();
        }
    });
    
    // Auto-focus - make page ready for immediate typing
    window.addEventListener('load', function() {
        document.body.focus();
        console.log('[PIN] Page ready - you can start typing numbers');
    });
    
    // Initial state
    updateDisplay();
})();
</script>

<?php
error_log("[PIN-ENTRY.PHP] Unified design loaded (v3.3.0)");
?>