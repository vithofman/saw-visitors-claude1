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
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-risks saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container saw-page-container-wide">
            
            <!-- Header -->
            <div class="saw-page-header saw-page-header-left">
                <div class="saw-header-icon">⚠️</div>
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
                            <div class="saw-empty-state-icon">⚠️</div>
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
                                <?php echo wp_kses_post($risks_text); ?>
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
    
    <?php if ($is_invitation): ?>
    <!-- Skip button for invitation mode -->
    <div class="saw-panel-skip">
        <p class="saw-panel-skip-info">
            💡 Toto školení je volitelné. Můžete ho přeskočit a projít si později.
        </p>
        <form method="POST" style="display: inline-block;">
            <?php 
            $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
            $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
            $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
            wp_nonce_field($nonce_name, $nonce_field); 
            ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="skip_training">
            <button type="submit" class="saw-panel-skip-btn">
                ⏭️ Přeskočit školení
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Floating Confirm Panel -->
    <form method="POST" id="risks-form" class="saw-panel-confirm">
        <?php 
        $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
        $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
        $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
        $complete_action = $is_invitation ? 'complete_training' : 'complete_training_risks';
        wp_nonce_field($nonce_name, $nonce_field); 
        ?>
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
error_log("[RISKS.PHP] Unified design matching department.php (v3.4.0)");
?>