<?php
/**
 * Terminal Training Step - Department Information (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get data from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;

error_log("[DEPARTMENT.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");

// Get departments
$departments = isset($departments) ? $departments : array();
$has_departments = !empty($departments);

error_log("[DEPARTMENT.PHP] Departments count: " . count($departments));

// Check if completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_department FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_department);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'title' => 'Informace o odděleních',
        'subtitle' => 'Seznámení se specifiky jednotlivých oddělení a pracovních pozic',
        'confirm' => 'Potvrzuji seznámení s informacemi o odděleních',
        'continue' => 'Pokračovat',
        'documents_title' => 'Dokumenty oddělení',
        'no_departments' => 'Žádná oddělení k dispozici.',
        'download' => 'Stáhnout',
        'no_documents' => 'Žádné dokumenty',
    ),
    'en' => array(
        'title' => 'Department Information',
        'subtitle' => 'Familiarization with specifics of individual departments and job positions',
        'confirm' => 'I confirm familiarization with department information',
        'continue' => 'Continue',
        'documents_title' => 'Department Documents',
        'no_departments' => 'No departments available.',
        'download' => 'Download',
        'no_documents' => 'No documents',
    ),
    'sk' => array(
        'title' => 'Informácie o oddeleniach',
        'subtitle' => 'Oboznámenie sa so špecifikami jednotlivých oddelení a pracovných pozícií',
        'confirm' => 'Potvrdzujem oboznámenie s informáciami o oddeleniach',
        'continue' => 'Pokračovať',
        'documents_title' => 'Dokumenty oddelenia',
        'no_departments' => 'Žiadne oddelenia k dispozícii.',
        'download' => 'Stiahnuť',
        'no_documents' => 'Žiadne dokumenty',
    ),
    'uk' => array(
        'title' => 'Інформація про відділи',
        'subtitle' => 'Ознайомлення зі специфікою окремих відділів та посад',
        'confirm' => 'Підтверджую ознайомлення з інформацією про відділи',
        'continue' => 'Продовжити',
        'documents_title' => 'Документи відділу',
        'no_departments' => 'Немає доступних відділів.',
        'download' => 'Завантажити',
        'no_documents' => 'Немає документів',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>
<style>
/* === UNIFIED COLORS === */
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
    --accent-green: #10b981;
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
.saw-department-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    overflow: hidden;
}

/* Scrollable wrapper */
.saw-department-content-wrapper {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 3rem 2rem 10rem;
}

.saw-department-content-wrapper::-webkit-scrollbar {
    width: 8px;
}

.saw-department-content-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.saw-department-content-wrapper::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 999px;
}

.saw-department-content-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.5);
}

/* Layout */
.saw-department-layout {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.saw-department-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
    padding: 2rem 2.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.saw-department-icon {
    width: 4rem;
    height: 4rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.25rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 18px;
    box-shadow: 
        0 10px 30px rgba(16, 185, 129, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    position: relative;
}

.saw-department-icon::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.4), transparent);
    z-index: -1;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

.saw-department-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
}

.saw-department-subtitle {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.7);
    font-weight: 500;
    line-height: 1.5;
}

/* Departments list */
.saw-departments-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Single department card */
.saw-dept-card {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.saw-dept-card.expanded {
    border-color: rgba(102, 126, 234, 0.3);
}

/* Department header (clickable) */
.saw-dept-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2rem;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}

.saw-dept-header:hover {
    background: rgba(255, 255, 255, 0.05);
}

.saw-dept-card.expanded .saw-dept-header {
    background: rgba(102, 126, 234, 0.08);
}

.saw-dept-title-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.saw-dept-icon {
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--text-secondary);
}

.saw-dept-card.expanded .saw-dept-icon {
    transform: rotate(90deg);
}

.saw-dept-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
}

.saw-dept-badge {
    padding: 0.375rem 0.875rem;
    background: rgba(102, 126, 234, 0.15);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 999px;
    color: #818cf8;
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

/* Department content */
.saw-dept-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.saw-dept-card.expanded .saw-dept-content {
    max-height: 5000px;
}

.saw-dept-body {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 2rem;
    padding: 0 2rem 2rem 2rem;
    border-top: 1px solid var(--border-glass);
}

/* Text content */
.saw-dept-text {
    padding-top: 2rem;
}

.saw-text-content {
    font-size: 0.9375rem;
    line-height: 1.75;
    font-weight: 400;
    color: var(--text-secondary);
}

.saw-text-content h1,
.saw-text-content h2,
.saw-text-content h3,
.saw-text-content h4 {
    color: var(--text-primary);
    font-weight: 700;
    letter-spacing: -0.01em;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.saw-text-content h1 { font-size: 1.5rem; margin-top: 0; }
.saw-text-content h2 { font-size: 1.25rem; }
.saw-text-content h3 { font-size: 1.125rem; }
.saw-text-content h4 { font-size: 1rem; }
.saw-text-content p { margin-bottom: 1rem; }

.saw-text-content ul,
.saw-text-content ol {
    margin: 1rem 0 1rem 1.5rem;
}

.saw-text-content li {
    margin-bottom: 0.5rem;
}

.saw-text-content strong {
    color: var(--text-primary);
    font-weight: 600;
}

/* Department documents sidebar */
.saw-dept-docs {
    padding-top: 2rem;
}

.saw-dept-docs-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.saw-dept-docs-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.saw-dept-doc-card {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem;
    text-decoration: none;
    background: var(--bg-glass-light);
    border-radius: 12px;
    border: 1px solid var(--border-glass);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.saw-dept-doc-card:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(102, 126, 234, 0.4);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.saw-dept-doc-icon {
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--theme-color), var(--theme-color-hover));
    color: var(--text-primary);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.saw-dept-doc-info {
    flex: 1;
    min-width: 0;
}

.saw-dept-doc-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    word-break: break-word;
    line-height: 1.3;
}

.saw-dept-doc-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.6875rem;
}

.saw-doc-badge {
    padding: 0.125rem 0.4rem;
    background: rgba(102, 126, 234, 0.15);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 6px;
    color: #818cf8;
    font-weight: 600;
    text-transform: uppercase;
}

.saw-doc-size {
    color: var(--text-muted);
}

/* Empty states */
.saw-empty-text {
    font-size: 1rem;
    color: var(--text-muted);
    text-align: center;
    padding: 3rem 0;
}

.saw-no-docs {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-style: italic;
}

/* === UNIFIED FLOATING ACTION BAR === */
.saw-confirm-panel {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    z-index: 200;
    min-width: 280px;
}

.saw-confirm-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 1rem 1.5rem;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
}

.saw-confirm-checkbox:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(102, 126, 234, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 30px rgba(0, 0, 0, 0.3);
}

.saw-confirm-checkbox.checked {
    background: rgba(72, 187, 120, 0.2);
    border-color: rgba(72, 187, 120, 0.5);
}

.saw-confirm-checkbox input {
    width: 22px;
    height: 22px;
    cursor: pointer;
    accent-color: #48bb78;
    flex-shrink: 0;
}

.saw-confirm-checkbox span {
    font-weight: 600;
    color: white;
    font-size: 0.925rem;
    line-height: 1.4;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.saw-continue-btn {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 4px 24px rgba(102, 126, 234, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.saw-continue-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.6);
}

.saw-continue-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

/* Responsive */
@media (max-width: 1024px) {
    .saw-dept-body {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .saw-department-content-wrapper {
        padding: 2rem 1rem 12rem;
    }
    
    .saw-department-header {
        gap: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .saw-department-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.75rem;
    }
    
    .saw-department-title {
        font-size: 1.5rem;
    }
    
    .saw-department-subtitle {
        font-size: 0.875rem;
    }
    
    .saw-dept-header {
        padding: 1.25rem;
    }
    
    .saw-dept-name {
        font-size: 1.125rem;
    }
    
    .saw-dept-body {
        padding: 0 1.25rem 1.25rem 1.25rem;
    }
    
    .saw-text-content {
        font-size: 0.875rem;
    }
    
    .saw-confirm-panel {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        min-width: 0;
    }
    
    .saw-confirm-checkbox {
        padding: 0.875rem 1.25rem;
    }
    
    .saw-confirm-checkbox span {
        font-size: 0.875rem;
    }
    
    .saw-continue-btn {
        padding: 0.875rem 1.25rem;
    }
}
</style>

<div class="saw-department-aurora">
    
    <div class="saw-department-content-wrapper">
        <div class="saw-department-layout">

            <header class="saw-department-header">
                <div class="saw-department-icon">🏭</div>
                <div class="saw-department-header-text">
                    <h1 class="saw-department-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-department-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </header>

            <?php if (!$has_departments): ?>
                <div class="saw-empty-text">
                    <?php echo esc_html($t['no_departments']); ?>
                </div>
            <?php else: ?>
                <div class="saw-departments-wrapper">
                    <?php foreach ($departments as $index => $dept): ?>
                    <?php 
                    $dept_id = 'dept-' . $index;
                    $has_docs = !empty($dept['documents']);
                    $docs_count = $has_docs ? count($dept['documents']) : 0;
                    ?>
                    
                    <div class="saw-dept-card <?php echo $index === 0 ? 'expanded' : ''; ?>" data-dept="<?php echo $dept_id; ?>">
                        <div class="saw-dept-header">
                            <div class="saw-dept-title-wrapper">
                                <svg class="saw-dept-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                                <h3 class="saw-dept-name"><?php echo esc_html($dept['department_name']); ?></h3>
                            </div>
                            
                            <?php if ($has_docs): ?>
                            <div class="saw-dept-badge">
                                <span>📄</span>
                                <span><?php echo $docs_count; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="saw-dept-content">
                            <div class="saw-dept-body">
                                
                                <!-- Text content -->
                                <div class="saw-dept-text">
                                    <?php if (!empty($dept['text_content'])): ?>
                                        <div class="saw-text-content">
                                            <?php echo wp_kses_post($dept['text_content']); ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="saw-no-docs">Žádný textový obsah</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Documents sidebar -->
                                <div class="saw-dept-docs">
                                    <h4 class="saw-dept-docs-title">
                                        <span>📎</span>
                                        <span><?php echo esc_html($t['documents_title']); ?></span>
                                    </h4>
                                    
                                    <?php if ($has_docs): ?>
                                    <div class="saw-dept-docs-list">
                                        <?php foreach ($dept['documents'] as $doc): ?>
                                        <?php
                                        $file_url = content_url() . '/uploads' . $doc['file_path'];
                                        $filename = $doc['file_name'];
                                        $file_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        $file_size = isset($doc['file_size']) ? size_format($doc['file_size']) : '';
                                        ?>
                                        <a href="<?php echo esc_url($file_url); ?>"
                                           class="saw-dept-doc-card"
                                           download="<?php echo esc_attr($filename); ?>">
                                            <div class="saw-dept-doc-icon">📄</div>
                                            <div class="saw-dept-doc-info">
                                                <div class="saw-dept-doc-name">
                                                    <?php echo esc_html($filename); ?>
                                                </div>
                                                <div class="saw-dept-doc-meta">
                                                    <?php if ($file_ext): ?>
                                                    <span class="saw-doc-badge"><?php echo esc_html($file_ext); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($file_size): ?>
                                                    <span class="saw-doc-size"><?php echo esc_html($file_size); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <p class="saw-no-docs"><?php echo esc_html($t['no_documents']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- UNIFIED Floating Panel -->
    <form method="POST" id="department-form" class="saw-confirm-panel">
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        <input type="hidden" name="terminal_action" value="complete_training_department">

        <?php if (!$completed): ?>
        <label class="saw-confirm-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="department_confirmed"
                   id="department-confirmed"
                   value="1"
                   required>
            <span><?php echo esc_html($t['confirm']); ?></span>
        </label>
        <?php endif; ?>

        <button type="submit"
                class="saw-continue-btn"
                id="continue-btn"
                <?php echo !$completed ? 'disabled' : ''; ?>>
            <?php echo esc_html($t['continue']); ?> →
        </button>
    </form>
</div>

<script>
(function() {
    'use strict';

    // Accordion functionality
    document.querySelectorAll('.saw-dept-header').forEach(header => {
        header.addEventListener('click', function() {
            const card = this.closest('.saw-dept-card');
            card.classList.toggle('expanded');
        });
    });

    // Checkbox listener
    const checkbox = document.getElementById('department-confirmed');
    const continueBtn = document.getElementById('continue-btn');
    const wrapper = document.getElementById('checkbox-wrapper');

    if (checkbox && continueBtn) {
        checkbox.addEventListener('change', function() {
            continueBtn.disabled = !this.checked;
            if (wrapper) {
                if (this.checked) {
                    wrapper.classList.add('checked');
                } else {
                    wrapper.classList.remove('checked');
                }
            }
        });
    }
})();
</script>

<?php
error_log("[DEPARTMENT.PHP] Unified design with departments accordion loaded (v3.3.0)");
?>