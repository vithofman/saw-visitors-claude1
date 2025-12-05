<?php
/**
 * Terminal Training Step - Risks
 * 
 * @package SAW_Visitors
 * @version 3.4.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Data extraction (Terminal Flow)
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;

// Risks content
$risks_text = isset($risks_text) ? $risks_text : '';
$has_content = !empty($risks_text);

// Documents
$documents = isset($documents) ? $documents : array();
$has_documents = !empty($documents);

// Check completed status
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

<div class="saw-page-aurora saw-step-risks saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container"> <!-- max-width: 900px -->
            
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
                                // Normalize file URL/Path logic for Terminal
                                $file_url = isset($doc['file_url']) ? $doc['file_url'] : (isset($doc['url']) ? $doc['url'] : '');
                                if (!$file_url && isset($doc['file_path'])) {
                                    $file_url = content_url() . '/uploads' . $doc['file_path'];
                                }
                                
                                $filename = isset($doc['file_name']) ? $doc['file_name'] : (isset($doc['name']) ? $doc['name'] : basename($file_url));
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
        <?php 
        wp_nonce_field('saw_terminal_step', 'terminal_nonce'); 
        ?>
        <input type="hidden" name="terminal_action" value="complete_training_risks">
        
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
                if (this.checked) wrapper.classList.add('checked');
                else wrapper.classList.remove('checked');
            }
        });
    }
})();
</script>