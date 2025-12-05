<?php
/**
 * Terminal Step - Check-in Type Selection (Unified Design)
 * 
 * OPRAVENO: Inline styly podle vzoru checkout-method.php
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Typ návštěvy',
        'subtitle' => 'Máte registrovanou návštěvu?',
        'planned' => 'Plánovaná návštěva',
        'planned_desc' => 'Mám PIN kód z emailu',
        'walkin' => 'Jednorázová návštěva',
        'walkin_desc' => 'Nemám PIN, chci se zaregistrovat',
    ],
    'en' => [
        'title' => 'Visit Type',
        'subtitle' => 'Do you have a registered visit?',
        'planned' => 'Planned Visit',
        'planned_desc' => 'I have a PIN code from email',
        'walkin' => 'Walk-in Visit',
        'walkin_desc' => 'I don\'t have a PIN, I want to register',
    ],
    'sk' => [
        'title' => 'Typ návštevy',
        'subtitle' => 'Máte registrovanú návštevu?',
        'planned' => 'Plánovaná návšteva',
        'planned_desc' => 'Mám PIN kód z emailu',
        'walkin' => 'Jednorazová návšteva',
        'walkin_desc' => 'Nemám PIN, chcem sa zaregistrovať',
    ],
    'uk' => [
        'title' => 'Тип візиту',
        'subtitle' => 'У вас є зареєстрований візит?',
        'planned' => 'Заплановий візит',
        'planned_desc' => 'У мене є PIN-код з електронної пошти',
        'walkin' => 'Разовий візит',
        'walkin_desc' => 'У мене немає PIN, я хочу зареєструватися',
    ],
    'de' => [
        'title' => 'Besuchstyp',
        'subtitle' => 'Haben Sie einen registrierten Besuch?',
        'planned' => 'Geplanter Besuch',
        'planned_desc' => 'Ich habe einen PIN-Code aus der E-Mail',
        'walkin' => 'Spontaner Besuch',
        'walkin_desc' => 'Ich habe keinen PIN, ich möchte mich registrieren',
    ],
    'pl' => [
        'title' => 'Typ wizyty',
        'subtitle' => 'Czy masz zarejestrowaną wizytę?',
        'planned' => 'Zaplanowana wizyta',
        'planned_desc' => 'Mam kod PIN z e-maila',
        'walkin' => 'Wizyta bez rejestracji',
        'walkin_desc' => 'Nie mam PIN-u, chcę się zarejestrować',
    ],
    'vi' => [
        'title' => 'Loại chuyến thăm',
        'subtitle' => 'Bạn có chuyến thăm đã đăng ký không?',
        'planned' => 'Chuyến thăm đã lên kế hoạch',
        'planned_desc' => 'Tôi có mã PIN từ email',
        'walkin' => 'Chuyến thăm không đăng ký',
        'walkin_desc' => 'Tôi không có PIN, tôi muốn đăng ký',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<style>
/* === TYPE SELECTION - UNIFIED STYLE === */
:root {
    --theme-color: #667eea;
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

.saw-type-aurora {
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

.saw-type-content {
    max-width: 900px;
    width: 100%;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-type-header {
    text-align: center;
    margin-bottom: 3rem;
}

.saw-type-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-type-title {
    font-size: 2.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
}

.saw-type-subtitle {
    font-size: 1.125rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* Type Grid */
.saw-type-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 3rem;
    animation: fadeIn 0.6s ease 0.3s both;
    max-width: 800px;
    margin: 0 auto;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Type Button */
.saw-type-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    padding: 3rem 2.5rem;
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border: 2px solid var(--border-glass);
    border-radius: 20px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    text-decoration: none;
    min-height: 360px;
    width: 100%;
}

.saw-type-btn:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

.saw-type-btn:active {
    transform: translateY(-3px);
}

/* Planned (Purple/Blue) */
.saw-type-btn.is-planned {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
    border-color: rgba(102, 126, 234, 0.4);
}

.saw-type-btn.is-planned:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.25), rgba(118, 75, 162, 0.25));
    border-color: rgba(102, 126, 234, 0.6);
}

/* Walk-in (Cyan/Teal) */
.saw-type-btn.is-walkin {
    background: linear-gradient(135deg, rgba(34, 211, 238, 0.15), rgba(6, 182, 212, 0.15));
    border-color: rgba(34, 211, 238, 0.4);
}

.saw-type-btn.is-walkin:hover {
    background: linear-gradient(135deg, rgba(34, 211, 238, 0.25), rgba(6, 182, 212, 0.25));
    border-color: rgba(34, 211, 238, 0.6);
}

.saw-type-icon-large {
    font-size: 6rem;
    line-height: 1;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
    margin-bottom: 0.5rem;
}

.saw-type-text {
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    width: 100%;
}

.saw-type-name {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.saw-type-desc {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.85);
    font-weight: 500;
    line-height: 1.5;
    padding: 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .saw-type-aurora {
        padding: 1.5rem;
    }
    
    .saw-type-icon {
        font-size: 3rem;
    }
    
    .saw-type-title {
        font-size: 1.75rem;
    }
    
    .saw-type-subtitle {
        font-size: 1rem;
    }
    
    .saw-type-header {
        margin-bottom: 2rem;
    }
    
    .saw-type-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .saw-type-btn {
        padding: 2rem 1.5rem;
        min-height: 280px;
    }
    
    .saw-type-icon-large {
        font-size: 4.5rem;
    }
    
    .saw-type-name {
        font-size: 1.75rem;
    }
    
    .saw-type-desc {
        font-size: 0.875rem;
    }
}
</style>

<div class="saw-type-aurora">
    <div class="saw-type-content">
        
        <!-- Header -->
        <div class="saw-type-header">
            <div class="saw-type-icon">📋</div>
            <h1 class="saw-type-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-type-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Type Grid -->
        <form method="POST" class="saw-type-grid">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="set_checkin_type">
            
            <!-- Planned Visit Button -->
            <button type="submit" 
                    name="checkin_type" 
                    value="planned" 
                    class="saw-type-btn is-planned">
                <span class="saw-type-icon-large">📧</span>
                <div class="saw-type-text">
                    <div class="saw-type-name"><?php echo esc_html($t['planned']); ?></div>
                    <div class="saw-type-desc"><?php echo esc_html($t['planned_desc']); ?></div>
                </div>
            </button>
            
            <!-- Walk-in Visit Button -->
            <button type="submit" 
                    name="checkin_type" 
                    value="walkin" 
                    class="saw-type-btn is-walkin">
                <span class="saw-type-icon-large">🚶</span>
                <div class="saw-type-text">
                    <div class="saw-type-name"><?php echo esc_html($t['walkin']); ?></div>
                    <div class="saw-type-desc"><?php echo esc_html($t['walkin_desc']); ?></div>
                </div>
            </button>
            
        </form>
        
    </div>
</div>

<?php
error_log("[TYPE.PHP] Unified design loaded (v4.0.0)");
?>