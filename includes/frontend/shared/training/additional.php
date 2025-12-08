<?php
/**
 * Shared Training Step - Additional Information
 * Works for Terminal, Invitation and Visitor Info flows
 * 
 * @package SAW_Visitors
 * @version 3.5.0
 * 
 * ZMĚNA v 3.5.0:
 * - Přidána podpora pro visitor_info kontext (Info Portal)
 * - Context detection pro 3 různé flow typy
 * 
 * ZMĚNA v 3.4.0:
 * - Sjednocený layout s risks.php (text + dokumenty ve stejné kartě)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ===== CONTEXT DETECTION (v3.5.0) =====
// Determine which flow we're in: terminal, invitation, or visitor_info
$context = 'terminal'; // default
if (isset($is_invitation) && $is_invitation === true) {
    $context = 'invitation';
}
if (isset($is_visitor_info) && $is_visitor_info === true) {
    $context = 'visitor_info';
}

// Context-specific form settings
$context_settings = array(
    'terminal' => array(
        'nonce_name' => 'saw_terminal_step',
        'nonce_field' => 'terminal_nonce',
        'action_name' => 'terminal_action',
        'complete_action' => 'complete_training_additional',
    ),
    'invitation' => array(
        'nonce_name' => 'saw_invitation_step',
        'nonce_field' => 'invitation_nonce',
        'action_name' => 'invitation_action',
        'complete_action' => 'complete_training',
    ),
    'visitor_info' => array(
        'nonce_name' => 'saw_visitor_info_step',
        'nonce_field' => 'visitor_info_nonce',
        'action_name' => 'visitor_info_action',
        'complete_action' => 'complete_training_additional',
    ),
);

$ctx = $context_settings[$context];
$nonce_name = $ctx['nonce_name'];
$nonce_field = $ctx['nonce_field'];
$action_name = $ctx['action_name'];
$complete_action = $ctx['complete_action'];
// ===== END CONTEXT DETECTION =====

// Detect flow type (legacy support)
$is_invitation = ($context === 'invitation');

// Get data from appropriate flow
if ($context === 'invitation') {
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
    
    // Get additional content from training content
    $additional_text = '';
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
                "SELECT id, additional_text FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            if ($content) {
                $additional_text = $content->additional_text ?? '';
                if ($content->id) {
                    $documents = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                         WHERE document_type = 'additional' AND reference_id = %d 
                         ORDER BY uploaded_at ASC",
                        $content->id
                    ), ARRAY_A);
                }
            }
        }
    }
} elseif ($context === 'visitor_info') {
    // Visitor Info Portal flow - data passed from controller
    $flow = isset($flow) ? $flow : array();
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;
    $additional_text = isset($additional_text) ? $additional_text : '';
    $documents = isset($documents) ? $documents : array();
} else {
    // Terminal flow
    $flow = isset($flow) ? $flow : array();
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $additional_text = isset($additional_text) ? $additional_text : '';
    $documents = isset($documents) ? $documents : array();
}

$has_content = !empty($additional_text);
$has_documents = !empty($documents);

// Check if completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_additional FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_additional);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'title' => 'Další informace',
        'subtitle' => 'Doplňující informace pro návštěvníky',
        'confirm' => 'Potvrzuji seznámení s dalšími informacemi',
        'continue' => 'Pokračovat',
        'documents_title' => 'Související dokumenty',
        'no_content' => 'Obsah není k dispozici.',
        'download' => 'Stáhnout',
        'no_documents' => 'Žádné dokumenty',
        'skip_info' => 'Toto školení je volitelné. Můžete ho přeskočit a projít si později.',
        'skip_button' => 'Přeskočit školení',
    ),
    'en' => array(
        'title' => 'Additional Information',
        'subtitle' => 'Supplementary information for visitors',
        'confirm' => 'I confirm familiarization with additional information',
        'continue' => 'Continue',
        'documents_title' => 'Related Documents',
        'no_content' => 'Content not available.',
        'download' => 'Download',
        'no_documents' => 'No documents',
        'skip_info' => 'This training is optional. You can skip it and complete it later.',
        'skip_button' => 'Skip training',
    ),
    'sk' => array(
        'title' => 'Ďalšie informácie',
        'subtitle' => 'Doplňujúce informácie pre návštevníkov',
        'confirm' => 'Potvrdzujem oboznámenie s ďalšími informáciami',
        'continue' => 'Pokračovať',
        'documents_title' => 'Súvisiace dokumenty',
        'no_content' => 'Obsah nie je k dispozícii.',
        'download' => 'Stiahnuť',
        'no_documents' => 'Žiadne dokumenty',
        'skip_info' => 'Toto školenie je voliteľné. Môžete ho preskočiť a prejsť si neskôr.',
        'skip_button' => 'Preskočiť školenie',
    ),
    'uk' => array(
        'title' => 'Додаткова інформація',
        'subtitle' => 'Додаткова інформація для відвідувачів',
        'confirm' => 'Підтверджую ознайомлення з додатковою інформацією',
        'continue' => 'Продовжити',
        'documents_title' => 'Супровідні документи',
        'no_content' => 'Вміст недоступний.',
        'download' => 'Завантажити',
        'no_documents' => 'Немає документів',
        'skip_info' => 'Це навчання є необов\'язковим. Ви можете пропустити його і пройти пізніше.',
        'skip_button' => 'Пропустити навчання',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<div class="saw-page-aurora saw-step-additional saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container">
            
            <div class="saw-page-header saw-page-header-left">
                <div class="saw-header-icon">ℹ️</div>
                <div class="saw-header-text">
                    <h1 class="saw-header-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-header-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </div>

            <!-- Content Card -->
            <?php if (!$has_content && !$has_documents): ?>
                <div class="saw-card-content">
                    <div class="saw-card-body">
                        <div class="saw-empty-state">
                            <div class="saw-empty-state-icon">ℹ️</div>
                            <p class="saw-empty-state-text"><?php echo esc_html($t['no_content']); ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="saw-card-content">
                    <div class="saw-card-body saw-card-body-grid">
                        
                        <!-- Text content -->
                        <div class="saw-text-content">
                            <?php if ($has_content): ?>
                                <?php echo wp_kses_post($additional_text); ?>
                            <?php else: ?>
                                <p><?php echo esc_html($t['no_content']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Documents sidebar -->
                        <div class="saw-docs-sidebar">
                            <h4 class="saw-docs-title">
                                <span>📎</span>
                                <span><?php echo esc_html($t['documents_title']); ?></span>
                            </h4>
                            
                            <?php if ($has_documents): ?>
                            <div class="saw-docs-list">
                                <?php foreach ($documents as $doc): ?>
                                <?php
                                $file_url = content_url() . '/uploads' . $doc['file_path'];
                                $filename = $doc['file_name'];
                                $file_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                $file_size = isset($doc['file_size']) ? size_format($doc['file_size']) : '';
                                ?>
                                <a href="<?php echo esc_url($file_url); ?>"
                                   class="saw-doc-card"
                                   download="<?php echo esc_attr($filename); ?>">
                                    <div class="saw-doc-icon">📄</div>
                                    <div class="saw-doc-info">
                                        <div class="saw-doc-name">
                                            <?php echo esc_html($filename); ?>
                                        </div>
                                        <div class="saw-doc-meta">
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
                            <p><?php echo esc_html($t['no_documents']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <?php if ($context === 'invitation' || $context === 'visitor_info'): ?>
    <!-- Skip button for invitation/visitor_info mode -->
    <div class="saw-panel-skip">
        <p class="saw-panel-skip-info">
            💡 <?php echo esc_html($t['skip_info']); ?>
        </p>
        <form method="POST" style="display: inline-block;">
            <?php wp_nonce_field($nonce_name, $nonce_field); ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="skip_training">
            <button type="submit" class="saw-panel-skip-btn">
                ⏭️ <?php echo esc_html($t['skip_button']); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- UNIFIED Floating Panel -->
    <form method="POST" id="additional-form" class="saw-panel-confirm">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">

        <?php if (!$completed): ?>
        <label class="saw-panel-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="additional_confirmed"
                   id="additional-confirmed"
                   value="1"
                   required>
            <span><?php echo esc_html($t['confirm']); ?></span>
        </label>
        <?php endif; ?>

        <button type="submit"
                class="saw-panel-btn"
                id="continue-btn"
                <?php echo !$completed ? 'disabled' : ''; ?>>
            <?php echo esc_html($t['continue']); ?> →
        </button>
    </form>
</div>

<script>
(function() {
    'use strict';

    var checkbox = document.getElementById('additional-confirmed');
    var continueBtn = document.getElementById('continue-btn');
    var wrapper = document.getElementById('checkbox-wrapper');

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