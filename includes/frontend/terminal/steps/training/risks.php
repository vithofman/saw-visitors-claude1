<?php
/**
 * Terminal Training Step - Risks (Unified Design)
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

error_log("[RISKS.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");

// Get risks content
$risks_text = isset($risks_text) ? $risks_text : '';
$has_content = !empty($risks_text);

// Get documents
$documents = isset($documents) ? $documents : array();
$has_documents = !empty($documents);

error_log("[RISKS.PHP] Has content: " . ($has_content ? 'yes' : 'no') . ", Documents: " . count($documents));

// Check if completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_risks FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_risks);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'title' => 'Informace o rizicích',
        'subtitle' => 'Seznámení zaměstnance s riziky dle § 103 odst. 1 písm. b) zákona č. 262/2006 Sb., zákoník práce',
        'confirm' => 'Potvrzuji seznámení s riziky',
        'continue' => 'Pokračovat',
        'documents_title' => 'Související dokumenty',
        'no_content' => 'Obsah není k dispozici.',
        'download' => 'Stáhnout',
    ),
    'en' => array(
        'title' => 'Risk Information',
        'subtitle' => 'Employee familiarization with risks pursuant to § 103(1)(b) of Act No. 262/2006 Coll., Labour Code',
        'confirm' => 'I confirm risk acknowledgment',
        'continue' => 'Continue',
        'documents_title' => 'Related Documents',
        'no_content' => 'Content not available.',
        'download' => 'Download',
    ),
    'sk' => array(
        'title' => 'Informácie o rizikách',
        'subtitle' => 'Oboznámenie zamestnanca s rizikami podľa § 103 ods. 1 písm. b) zákona č. 262/2006 Z.z., zákonník práce',
        'confirm' => 'Potvrdzujem oboznámenie s rizikami',
        'continue' => 'Pokračovať',
        'documents_title' => 'Súvisiace dokumenty',
        'no_content' => 'Obsah nie je k dispozícii.',
        'download' => 'Stiahnuť',
    ),
    'uk' => array(
        'title' => 'Інформація про ризики',
        'subtitle' => 'Ознайомлення працівника з ризиками відповідно до § 103(1)(b) Закону № 262/2006, Трудовий кодекс',
        'confirm' => 'Підтверджую ознайомлення з ризиками',
        'continue' => 'Продовжити',
        'documents_title' => 'Супровідні документи',
        'no_content' => 'Вміст недоступний.',
        'download' => 'Завантажити',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>
<style>
/* === UNIFIED COLORS (PDF/Video) === */
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

/* Main container - STEJNÝ GRADIENT jako PDF/Video */
.saw-risks-aurora {
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
.saw-risks-content-wrapper {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 3rem 2rem 10rem;
}

.saw-risks-content-wrapper::-webkit-scrollbar {
    width: 8px;
}

.saw-risks-content-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.saw-risks-content-wrapper::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 999px;
}

.saw-risks-content-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.5);
}

/* Layout */
.saw-risks-layout {
    max-width: 1600px;
    margin: 0 auto;
}

/* Header */
.saw-risks-header {
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

.saw-risks-icon {
    width: 4rem;
    height: 4rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.25rem;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    border-radius: 18px;
    color: #111827;
    box-shadow: 
        0 10px 30px rgba(251, 191, 36, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    position: relative;
}

.saw-risks-icon::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.4), transparent);
    z-index: -1;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

.saw-risks-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
}

.saw-risks-subtitle {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.7);
    font-weight: 500;
    line-height: 1.5;
}

/* Grid - UŽŠÍ sloupeček pro dokumenty */
.saw-risks-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 0.75fr);
    gap: 2rem;
    align-items: start;
}

/* Glass card */
.saw-risks-glass-card {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    padding: 2rem;
}

/* Text content */
.saw-text-content {
    font-size: 1rem;
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
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.saw-text-content h1 { font-size: 2rem; margin-top: 0; }
.saw-text-content h2 { font-size: 1.75rem; }
.saw-text-content h3 { font-size: 1.5rem; }
.saw-text-content h4 { font-size: 1.25rem; }
.saw-text-content p { margin-bottom: 1.25rem; }

.saw-text-content ul,
.saw-text-content ol {
    margin: 1.25rem 0 1.25rem 1.5rem;
}

.saw-text-content li {
    margin-bottom: 0.5rem;
}

.saw-text-content strong {
    color: var(--text-primary);
    font-weight: 600;
}

.saw-text-content a {
    color: #818cf8;
    text-decoration: none;
    border-bottom: 1px solid rgba(129, 140, 248, 0.3);
    transition: all 0.2s;
}

.saw-text-content a:hover {
    color: #a5b4fc;
    border-bottom-color: rgba(165, 180, 252, 0.5);
}

/* Empty state */
.saw-risks-empty-text {
    font-size: 1.2rem;
    color: var(--text-muted);
    text-align: center;
    padding: 3rem 0;
}

/* Sidebar - sticky */
.saw-risks-sidebar {
    position: sticky;
    top: 2rem;
}

/* Documents */
.saw-documents-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.saw-documents-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.saw-documents-count {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.saw-documents-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Document card */
.saw-document-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    text-decoration: none;
    background: var(--bg-glass-light);
    border-radius: 12px;
    border: 1px solid var(--border-glass);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.saw-document-card:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(102, 126, 234, 0.4);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.saw-document-icon {
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--theme-color), var(--theme-color-hover));
    color: var(--text-primary);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.saw-document-info {
    flex: 1;
    min-width: 0;
}

.saw-document-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.375rem;
    word-break: break-word;
    line-height: 1.3;
}

.saw-document-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.saw-doc-badge {
    padding: 0.125rem 0.5rem;
    background: rgba(102, 126, 234, 0.15);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 6px;
    color: #818cf8;
    font-weight: 600;
    text-transform: uppercase;
}

.saw-doc-action {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--text-muted);
}

.saw-doc-action svg {
    opacity: 0.7;
}

/* === UNIFIED FLOATING ACTION BAR (jako PDF/Video) === */
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
@media (max-width: 1200px) {
    .saw-risks-grid {
        grid-template-columns: minmax(0, 1fr);
    }
    
    .saw-risks-sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .saw-risks-content-wrapper {
        padding: 2rem 1rem 12rem;
    }
    
    .saw-risks-header {
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .saw-risks-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.5rem;
    }
    
    .saw-risks-title {
        font-size: 1.75rem;
    }
    
    .saw-risks-subtitle {
        font-size: 0.95rem;
    }
    
    .saw-risks-glass-card {
        padding: 1.25rem;
    }
    
    .saw-text-content {
        font-size: 0.95rem;
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

<div class="saw-risks-aurora">
    
    <div class="saw-risks-content-wrapper">
        <div class="saw-risks-layout">

            <header class="saw-risks-header">
                <div class="saw-risks-icon">⚠️</div>
                <div class="saw-risks-header-text">
                    <h1 class="saw-risks-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-risks-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </header>

            <main class="saw-risks-grid">
                
                <section class="saw-risks-glass-card saw-risks-text-card">
                    <?php if (!$has_content): ?>
                        <p class="saw-risks-empty-text">
                            <?php echo esc_html($t['no_content']); ?>
                        </p>
                    <?php else: ?>
                        <div class="saw-text-content">
                            <?php echo wp_kses_post($risks_text); ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($has_documents): ?>
                    <aside class="saw-risks-sidebar">
                        <div class="saw-risks-glass-card saw-documents-card">
                            
                            <div class="saw-documents-header">
                                <h2 class="saw-documents-title">
                                    <span>📎</span>
                                    <span><?php echo esc_html($t['documents_title']); ?></span>
                                </h2>
                                <span class="saw-documents-count">
                                    <?php echo count($documents); ?>
                                </span>
                            </div>

                            <div class="saw-documents-list">
                                <?php foreach ($documents as $doc): ?>
                                    <?php
                                    // Get proper file URL
                                    $file_url = isset($doc['file_url']) ? $doc['file_url'] : $doc['url'];
                                    
                                    // Get filename from path
                                    $filename = isset($doc['file_name']) ? $doc['file_name'] : basename($file_url);
                                    
                                    // Get extension
                                    $file_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                    ?>
                                    <a href="<?php echo esc_url($file_url); ?>"
                                       class="saw-document-card"
                                       download="<?php echo esc_attr($filename); ?>">
                                        <div class="saw-document-icon">📄</div>
                                        <div class="saw-document-info">
                                            <div class="saw-document-name">
                                                <?php echo esc_html($doc['name']); ?>
                                            </div>
                                            <div class="saw-document-meta">
                                                <?php if ($file_ext): ?>
                                                <span class="saw-doc-badge"><?php echo esc_html($file_ext); ?></span>
                                                <?php endif; ?>
                                                <span class="saw-doc-action">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                                    </svg>
                                                    <?php echo esc_html($t['download']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </aside>
                <?php endif; ?>

            </main>
        </div>
    </div>
    
    <!-- UNIFIED Floating Panel (jako PDF/Video) -->
    <form method="POST" id="risks-form" class="saw-confirm-panel">
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        <input type="hidden" name="terminal_action" value="complete_training_risks">

        <?php if (!$completed): ?>
        <label class="saw-confirm-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="risks_confirmed"
                   id="risks-confirmed"
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

    const checkbox = document.getElementById('risks-confirmed');
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
error_log("[RISKS.PHP] Unified design loaded (v3.3.0)");
?>