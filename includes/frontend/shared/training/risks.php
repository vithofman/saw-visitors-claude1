<?php
/**
 * Shared Training Step - Risks
 * Works for Terminal, Invitation and Visitor Info flows
 * 
 * @package SAW_Visitors
 * @version 3.9.9
 * 
 * ZMĚNA v 3.9.9:
 * - REMOVED: Skip training sekce úplně odstraněna
 */

if (!defined('ABSPATH')) {
    exit;
}

// ===== CONTEXT DETECTION =====
$context = 'terminal';
if (isset($is_invitation) && $is_invitation === true) {
    $context = 'invitation';
}
if (isset($is_visitor_info) && $is_visitor_info === true) {
    $context = 'visitor_info';
}

$context_settings = array(
    'terminal' => array(
        'nonce_name' => 'saw_terminal_step',
        'nonce_field' => 'terminal_nonce',
        'action_name' => 'terminal_action',
        'complete_action' => 'complete_training_risks',
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
        'complete_action' => 'complete_training_risks',
    ),
);

$ctx = $context_settings[$context];
$nonce_name = $ctx['nonce_name'];
$nonce_field = $ctx['nonce_field'];
$action_name = $ctx['action_name'];
$complete_action = $ctx['complete_action'];

// Initialize variables
$risks_text = '';
$documents = [];
$visitor_id = null;
$lang = 'cs';

// Get data based on flow
if ($context === 'invitation') {
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    $lang = $flow['language'] ?? 'cs';
    
    global $wpdb;
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $flow['visit_id'] ?? 0
    ));
    
    if ($visit) {
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d AND training_skipped = 0 
             ORDER BY created_at ASC LIMIT 1",
            $visit->id
        ));
        $visitor_id = $visitor ? $visitor->id : null;
        
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
} elseif ($context === 'visitor_info') {
    $flow = isset($flow) ? $flow : array();
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;
    $risks_text = isset($risks_text) ? $risks_text : '';
    $documents = isset($documents) ? $documents : array();
} else {
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $risks_text = isset($risks_text) ? $risks_text : '';
    $documents = isset($documents) ? $documents : array();
}

$has_content = !empty($risks_text);
$has_documents = !empty($documents);

// Check completion status
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

<div class="saw-page-aurora saw-step-risks saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container">
            
            <!-- Header -->
            <header class="saw-page-header saw-page-header-left">
                <div class="saw-header-icon saw-header-icon-warning">⚠️</div>
                <div class="saw-header-text">
                    <h1 class="saw-header-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-header-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </header>
            
            <!-- Content Card -->
            <div class="saw-card-content">
                <div class="saw-card-body saw-card-body-grid">
                    
                    <!-- Text Content -->
                    <div class="saw-text-content">
                        <?php if ($has_content): ?>
                            <?php echo wp_kses_post($risks_text); ?>
                        <?php else: ?>
                            <p class="saw-empty-text"><?php echo esc_html($t['no_content']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Documents Sidebar -->
                    <div class="saw-docs-sidebar">
                        <h3 class="saw-docs-title">
                            📎 <?php echo esc_html($t['documents_title']); ?>
                        </h3>
                        
                        <?php if ($has_documents): ?>
                        <div class="saw-docs-list">
                            <?php foreach ($documents as $doc): ?>
                            <?php
                                $file_url = '';
                                if (isset($doc['file_url'])) {
                                    $file_url = $doc['file_url'];
                                } elseif (isset($doc['url'])) {
                                    $file_url = $doc['url'];
                                } elseif (isset($doc['file_path'])) {
                                    $file_url = content_url() . '/uploads' . (strpos($doc['file_path'], '/') === 0 ? '' : '/') . $doc['file_path'];
                                }
                                
                                $filename = '';
                                if (isset($doc['file_name'])) {
                                    $filename = $doc['file_name'];
                                } elseif (isset($doc['name'])) {
                                    $filename = $doc['name'];
                                } elseif (isset($doc['original_name'])) {
                                    $filename = $doc['original_name'];
                                } else {
                                    $filename = basename($file_url);
                                }
                                
                                $file_ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                            ?>
                            <a href="<?php echo esc_url($file_url); ?>" 
                               target="_blank" 
                               class="saw-doc-card"
                               download>
                                <div class="saw-doc-icon">📄</div>
                                <div class="saw-doc-info">
                                    <div class="saw-doc-name"><?php echo esc_html($filename); ?></div>
                                    <div class="saw-doc-meta">
                                        <span class="saw-doc-badge"><?php echo esc_html($file_ext); ?></span>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="saw-docs-empty"><?php echo esc_html($t['no_documents']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Floating Confirm Panel -->
    <form method="POST" id="risks-form" class="saw-panel-confirm">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">
        
        <?php if (!$completed): ?>
        <label class="saw-panel-checkbox" id="checkbox-wrapper">
            <input type="checkbox" 
                   name="risks_confirmed" 
                   id="risks-confirmed" 
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
    var checkbox = document.getElementById('risks-confirmed');
    var continueBtn = document.getElementById('continue-btn');
    var wrapper = document.getElementById('checkbox-wrapper');

    if (checkbox && continueBtn) {
        checkbox.addEventListener('change', function() {
            continueBtn.disabled = !this.checked;
            if (wrapper) {
                wrapper.classList.toggle('checked', this.checked);
            }
        });
    }
})();
</script>