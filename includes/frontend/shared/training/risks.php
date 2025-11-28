<?php
/**
 * Shared Training Step - Risks
 * Works for both Terminal and Invitation flows
 * 
 * UNIFIED DESIGN matching department.php
 * 
 * @package SAW_Visitors
 * @version 3.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Detect flow type
$is_invitation = isset($is_invitation) ? $is_invitation : false;

// Get data from appropriate flow
if ($is_invitation) {
    // Invitation flow
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    $lang = $flow['language'] ?? 'cs';
    
    // Get visitor ID from invitation flow
    global $wpdb;
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $flow['visit_id'] ?? 0
    ));
    
    $visitor_id = null;
    if ($visit) {
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d AND training_skipped = 0 
             ORDER BY created_at ASC LIMIT 1",
            $visit->id
        ));
        if ($visitor) {
            $visitor_id = $visitor->id;
        }
    }
    
    // Get risks content from training content
    $risks_text = '';
    $documents = [];
    if ($visit) {
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        if ($language_id) {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT id, risks_text FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            if ($content) {
                $risks_text = $content->risks_text ?? '';
                if ($content->id) {
                    $documents = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                         WHERE document_type = 'risks' AND reference_id = %d 
                         ORDER BY uploaded_at ASC",
                        $content->id
                    ), ARRAY_A);
                }
            }
        }
    }
} else {
    // Terminal flow
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $risks_text = isset($risks_text) ? $risks_text : '';
    $documents = isset($documents) ? $documents : array();
}

$has_content = !empty($risks_text);
$has_documents = !empty($documents);
$docs_count = count($documents);

error_log("[SHARED RISKS.PHP] Is Invitation: " . ($is_invitation ? 'yes' : 'no') . ", Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[SHARED RISKS.PHP] Has content: " . ($has_content ? 'yes' : 'no') . ", Documents: " . $docs_count);

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
        'no_documents' => 'Žádné dokumenty',
    ),
    'en' => array(
        'title' => 'Risk Information',
        'subtitle' => 'Employee familiarization with risks pursuant to § 103(1)(b) of Act No. 262/2006 Coll., Labour Code',
        'confirm' => 'I confirm risk acknowledgment',
        'continue' => 'Continue',
        'documents_title' => 'Related Documents',
        'no_content' => 'Content not available.',
        'download' => 'Download',
        'no_documents' => 'No documents',
    ),
    'sk' => array(
        'title' => 'Informácie o rizikách',
        'subtitle' => 'Oboznámenie zamestnanca s rizikami podľa § 103 ods. 1 písm. b) zákona č. 262/2006 Z.z., zákonník práce',
        'confirm' => 'Potvrdzujem oboznámenie s rizikami',
        'continue' => 'Pokračovať',
        'documents_title' => 'Súvisiace dokumenty',
        'no_content' => 'Obsah nie je k dispozícii.',
        'download' => 'Stiahnuť',
        'no_documents' => 'Žiadne dokumenty',
    ),
    'uk' => array(
        'title' => 'Інформація про ризики',
        'subtitle' => 'Ознайомлення працівника з ризиками відповідно до § 103(1)(b) Закону № 262/2006, Трудовий кодекс',
        'confirm' => 'Підтверджую ознайомлення з ризиками',
        'continue' => 'Продовжити',
        'documents_title' => 'Супровідні документи',
        'no_content' => 'Вміст недоступний.',
        'download' => 'Завантажити',
        'no_documents' => 'Немає документів',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>
<style>
/* === EXACT COPY FROM DEPARTMENT.PHP === */
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
    --accent-warning: #fbbf24;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer,
.saw-invitation-footer {
    display: none !important;
}

/* Main container */
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
    max-width: 1400px;
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

/* Content card */
.saw-risks-card {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

/* Card body with grid */
.saw-risks-body {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 2rem;
    padding: 2rem;
}

/* Text content */
.saw-risks-text {
    /* No extra padding needed */
}

.saw-risks-text-content {
    font-size: 0.9375rem;
    line-height: 1.75;
    font-weight: 400;
    color: var(--text-secondary);
}

.saw-risks-text-content h1,
.saw-risks-text-content h2,
.saw-risks-text-content h3,
.saw-risks-text-content h4 {
    color: var(--text-primary);
    font-weight: 700;
    letter-spacing: -0.01em;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.saw-risks-text-content h1 { font-size: 1.5rem; margin-top: 0; }
.saw-risks-text-content h2 { font-size: 1.25rem; }
.saw-risks-text-content h3 { font-size: 1.125rem; }
.saw-risks-text-content h4 { font-size: 1rem; }
.saw-risks-text-content p { margin-bottom: 1rem; }

.saw-risks-text-content ul,
.saw-risks-text-content ol {
    margin: 1rem 0 1rem 1.5rem;
}

.saw-risks-text-content li {
    margin-bottom: 0.5rem;
}

.saw-risks-text-content strong {
    color: var(--text-primary);
    font-weight: 600;
}

.saw-risks-text-content a {
    color: #818cf8;
    text-decoration: none;
    border-bottom: 1px solid rgba(129, 140, 248, 0.3);
    transition: all 0.2s;
}

.saw-risks-text-content a:hover {
    color: #a5b4fc;
    border-bottom-color: rgba(165, 180, 252, 0.5);
}

/* Documents sidebar */
.saw-risks-docs {
    border-left: 1px solid var(--border-glass);
    padding-left: 2rem;
}

.saw-risks-docs-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.saw-risks-docs-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Document card */
.saw-risks-doc-card {
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

.saw-risks-doc-card:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(102, 126, 234, 0.4);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.saw-risks-doc-icon {
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

.saw-risks-doc-info {
    flex: 1;
    min-width: 0;
}

.saw-risks-doc-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    word-break: break-word;
    line-height: 1.3;
}

.saw-risks-doc-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.6875rem;
}

.saw-risks-doc-badge {
    padding: 0.125rem 0.4rem;
    background: rgba(102, 126, 234, 0.15);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 6px;
    color: #818cf8;
    font-weight: 600;
    text-transform: uppercase;
}

.saw-risks-doc-size {
    color: var(--text-muted);
}

/* Empty states */
.saw-risks-empty-text {
    font-size: 1rem;
    color: var(--text-muted);
    text-align: center;
    padding: 3rem 0;
}

.saw-risks-no-docs {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-style: italic;
}

/* === FLOATING ACTION BAR === */
.saw-risks-confirm-panel {
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

.saw-risks-confirm-checkbox {
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

.saw-risks-confirm-checkbox:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(102, 126, 234, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 30px rgba(0, 0, 0, 0.3);
}

.saw-risks-confirm-checkbox.checked {
    background: rgba(72, 187, 120, 0.2);
    border-color: rgba(72, 187, 120, 0.5);
}

.saw-risks-confirm-checkbox input {
    width: 22px;
    height: 22px;
    cursor: pointer;
    accent-color: #48bb78;
    flex-shrink: 0;
}

.saw-risks-confirm-checkbox span {
    font-weight: 600;
    color: white;
    font-size: 0.925rem;
    line-height: 1.4;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.saw-risks-continue-btn {
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

.saw-risks-continue-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.6);
}

.saw-risks-continue-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

/* Responsive */
@media (max-width: 1024px) {
    .saw-risks-body {
        grid-template-columns: 1fr;
    }
    
    .saw-risks-docs {
        border-left: none;
        border-top: 1px solid var(--border-glass);
        padding-left: 0;
        padding-top: 2rem;
    }
}

@media (max-width: 768px) {
    .saw-risks-content-wrapper {
        padding: 2rem 1rem 12rem;
    }
    
    .saw-risks-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .saw-risks-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.75rem;
    }
    
    .saw-risks-title {
        font-size: 1.5rem;
    }
    
    .saw-risks-subtitle {
        font-size: 0.875rem;
    }
    
    .saw-risks-body {
        padding: 1.25rem;
    }
    
    .saw-risks-text-content {
        font-size: 0.875rem;
    }
    
    .saw-risks-confirm-panel {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        min-width: 0;
    }
    
    .saw-risks-confirm-checkbox {
        padding: 0.875rem 1.25rem;
    }
    
    .saw-risks-confirm-checkbox span {
        font-size: 0.875rem;
    }
    
    .saw-risks-continue-btn {
        padding: 0.875rem 1.25rem;
    }
}

/* Skip button styles */
.saw-risks-skip-wrapper {
    margin-top: 2rem;
    padding: 1.5rem;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 12px;
    text-align: center;
}

.saw-risks-skip-info {
    color: #c4b5fd;
    margin-bottom: 1rem;
}

.saw-risks-btn-skip {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: #f9fafb;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.saw-risks-btn-skip:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>

<div class="saw-risks-aurora">
    
    <div class="saw-risks-content-wrapper">
        <div class="saw-risks-layout">

            <!-- Header -->
            <header class="saw-risks-header">
                <div class="saw-risks-icon">⚠️</div>
                <div class="saw-risks-header-text">
                    <h1 class="saw-risks-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-risks-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </header>

            <!-- Content Card -->
            <?php if (!$has_content && !$has_documents): ?>
                <div class="saw-risks-card">
                    <div class="saw-risks-empty-text">
                        <?php echo esc_html($t['no_content']); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="saw-risks-card">
                    <div class="saw-risks-body">
                        
                        <!-- Text content -->
                        <div class="saw-risks-text">
                            <?php if ($has_content): ?>
                                <div class="saw-risks-text-content">
                                    <?php echo wp_kses_post($risks_text); ?>
                                </div>
                            <?php else: ?>
                                <p class="saw-risks-no-docs"><?php echo esc_html($t['no_content']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Documents sidebar -->
                        <div class="saw-risks-docs">
                            <h4 class="saw-risks-docs-title">
                                <span>📎</span>
                                <span><?php echo esc_html($t['documents_title']); ?></span>
                            </h4>
                            
                            <?php if ($has_documents): ?>
                            <div class="saw-risks-docs-list">
                                <?php foreach ($documents as $doc): ?>
                                <?php
                                $file_url = content_url() . '/uploads' . $doc['file_path'];
                                $filename = $doc['file_name'];
                                $file_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                $file_size = isset($doc['file_size']) ? size_format($doc['file_size']) : '';
                                ?>
                                <a href="<?php echo esc_url($file_url); ?>"
                                   class="saw-risks-doc-card"
                                   download="<?php echo esc_attr($filename); ?>">
                                    <div class="saw-risks-doc-icon">📄</div>
                                    <div class="saw-risks-doc-info">
                                        <div class="saw-risks-doc-name">
                                            <?php echo esc_html($filename); ?>
                                        </div>
                                        <div class="saw-risks-doc-meta">
                                            <?php if ($file_ext): ?>
                                            <span class="saw-risks-doc-badge"><?php echo esc_html($file_ext); ?></span>
                                            <?php endif; ?>
                                            <?php if ($file_size): ?>
                                            <span class="saw-risks-doc-size"><?php echo esc_html($file_size); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="saw-risks-no-docs"><?php echo esc_html($t['no_documents']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- Floating Confirm Panel -->
    <form method="POST" id="risks-form" class="saw-risks-confirm-panel">
        <?php 
        $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
        $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
        $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
        $complete_action = $is_invitation ? 'complete_training' : 'complete_training_risks';
        wp_nonce_field($nonce_name, $nonce_field); 
        ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">

        <?php if (!$completed): ?>
        <label class="saw-risks-confirm-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="risks_confirmed"
                   id="risks-confirmed"
                   value="1"
                   required>
            <span><?php echo esc_html($t['confirm']); ?></span>
        </label>
        <?php endif; ?>

        <button type="submit"
                class="saw-risks-continue-btn"
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

<?php if ($is_invitation): ?>
<div class="saw-risks-skip-wrapper">
    <p class="saw-risks-skip-info">
        💡 Toto školení je volitelné. Můžete ho přeskočit a projít si později.
    </p>
    <form method="POST" style="display: inline-block;">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="skip_training">
        <button type="submit" class="saw-risks-btn-skip">
            ⏭️ Přeskočit školení
        </button>
    </form>
</div>
<?php endif; ?>

<?php
error_log("[RISKS.PHP] Unified design matching department.php (v3.4.0)");
?>