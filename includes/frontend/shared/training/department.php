<?php
/**
 * Shared Training Step - Department Information
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
 * - Invitation flow nyní filtruje departments podle navštěvovaných hostů
 * - Stejná logika jako v Terminal (saw_visit_hosts → saw_user_departments)
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
        'complete_action' => 'complete_training_department',
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
        'complete_action' => 'complete_training_department',
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
    
    // Načíst departments z databáze - FILTROVANÉ PODLE HOSTŮ
    $departments = [];
    if ($visit) {
        // Najdi language_id
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        if ($language_id) {
            // Najdi training_content
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            if ($content) {
                // Získej department IDs filtrované podle hostů (stejně jako Terminal)
                $host_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                    $visit->id
                ));
                
                $department_ids = [];
                
                if (!empty($host_ids)) {
                    foreach ($host_ids as $host_id) {
                        // Získej departments přiřazené tomuto hostovi
                        $host_dept_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                            $host_id
                        ));
                        
                        // Pokud host nemá přiřazená oddělení (admin/super_manager) → všechna oddělení pobočky
                        if (empty($host_dept_ids)) {
                            $all_dept_ids = $wpdb->get_col($wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}saw_departments 
                                 WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                                $visit->customer_id,
                                $visit->branch_id
                            ));
                            $department_ids = array_merge($department_ids, $all_dept_ids);
                        } else {
                            $department_ids = array_merge($department_ids, $host_dept_ids);
                        }
                    }
                    
                    $department_ids = array_unique($department_ids);
                }
                
                // Načti content JEN pro filtrovaná oddělení
                if (!empty($department_ids)) {
                    $placeholders = implode(',', array_fill(0, count($department_ids), '%d'));
                    $query_params = array_merge([$content->id], $department_ids);
                    
                    $dept_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT tdc.*, d.name as department_name, d.description as department_description,
                                (SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents td 
                                 WHERE td.document_type = 'department' AND td.reference_id = tdc.id) as docs_count
                         FROM {$wpdb->prefix}saw_training_department_content tdc
                         LEFT JOIN {$wpdb->prefix}saw_departments d ON tdc.department_id = d.id
                         WHERE tdc.training_content_id = %d 
                           AND tdc.department_id IN ({$placeholders})
                           AND (
                               (tdc.text_content IS NOT NULL AND tdc.text_content != '')
                               OR EXISTS (
                                   SELECT 1 FROM {$wpdb->prefix}saw_training_documents td 
                                   WHERE td.document_type = 'department' AND td.reference_id = tdc.id
                               )
                           )
                         ORDER BY tdc.id ASC",
                        ...$query_params
                    ), ARRAY_A);
                } else {
                    // Žádní hosts = žádná oddělení
                    $dept_rows = [];
                }
                
                foreach ($dept_rows as $dept) {
                    // Načti dokumenty pro toto oddělení
                    $docs = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                         WHERE document_type = 'department' AND reference_id = %d 
                         ORDER BY uploaded_at ASC",
                        $dept['id']
                    ), ARRAY_A);
                    
                    // Použij text_content z training_department_content, nebo fallback na description z departments
                    $text = $dept['text_content'];
                    if (empty($text)) {
                        $text = $dept['department_description'] ?? '';
                    }
                    
                    $departments[] = [
                        'department_name' => $dept['department_name'] ?? 'Oddělení #' . $dept['department_id'],
                        'department_id' => $dept['department_id'],
                        'text_content' => $text,
                        'documents' => $docs
                    ];
                }
            }
        }
    }
} elseif ($context === 'visitor_info') {
    // Visitor Info Portal flow - data passed from controller
    $flow = isset($flow) ? $flow : array();
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;
    $departments = isset($departments) ? $departments : array();
} else {
    // Terminal flow
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $departments = isset($departments) ? $departments : [];
}

$has_departments = !empty($departments);

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
        'no_text_content' => 'Žádný textový obsah',
        'skip_info' => 'Toto školení je volitelné. Můžete ho přeskočit a projít si později.',
        'skip_button' => 'Přeskočit školení',
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
        'no_text_content' => 'No text content',
        'skip_info' => 'This training is optional. You can skip it and complete it later.',
        'skip_button' => 'Skip training',
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
        'no_text_content' => 'Žiadny textový obsah',
        'skip_info' => 'Toto školenie je voliteľné. Môžete ho preskočiť a prejsť si neskôr.',
        'skip_button' => 'Preskočiť školenie',
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
        'no_text_content' => 'Немає текстового вмісту',
        'skip_info' => 'Це навчання є необов\'язковим. Ви можете пропустити його і пройти пізніше.',
        'skip_button' => 'Пропустити навчання',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<div class="saw-page-aurora saw-step-department saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container">
            
            <div class="saw-page-header saw-page-header-left">
                <div class="saw-header-icon">🏭</div>
                <div class="saw-header-text">
                    <h1 class="saw-header-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-header-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </div>

            <?php if (!$has_departments): ?>
                <div class="saw-card-content">
                    <div class="saw-card-body">
                        <div class="saw-empty-state">
                            <div class="saw-empty-state-icon">🏭</div>
                            <p class="saw-empty-state-text"><?php echo esc_html($t['no_departments']); ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="saw-accordion">
                    <?php foreach ($departments as $index => $dept): ?>
                    <?php 
                    $dept_id = 'dept-' . $index;
                    $has_docs = !empty($dept['documents']);
                    $docs_count = $has_docs ? count($dept['documents']) : 0;
                    ?>
                    
                    <div class="saw-accordion-item <?php echo $index === 0 ? 'expanded' : ''; ?>" data-dept="<?php echo $dept_id; ?>">
                        <button type="button" class="saw-accordion-header">
                            <div class="saw-accordion-title-wrapper">
                                <svg class="saw-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 2rem; height: 2rem;">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                                <h3 class="saw-accordion-title"><?php echo esc_html($dept['department_name']); ?></h3>
                            </div>
                            
                            <?php if ($has_docs): ?>
                            <div class="saw-accordion-badge">
                                <span>📄</span>
                                <span><?php echo $docs_count; ?></span>
                            </div>
                            <?php endif; ?>
                        </button>
                        
                        <div class="saw-accordion-content">
                            <div class="saw-accordion-body saw-accordion-body-grid">
                                
                                <!-- Text content -->
                                <div class="saw-text-content">
                                    <?php if (!empty($dept['text_content'])): ?>
                                        <?php echo wp_kses_post($dept['text_content']); ?>
                                    <?php else: ?>
                                        <p><?php echo esc_html($t['no_text_content']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Documents sidebar -->
                                <div class="saw-docs-sidebar">
                                    <h4 class="saw-docs-title">
                                        <span>📎</span>
                                        <span><?php echo esc_html($t['documents_title']); ?></span>
                                    </h4>
                                    
                                    <?php if ($has_docs): ?>
                                    <div class="saw-docs-list">
                                        <?php foreach ($dept['documents'] as $doc): ?>
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
                    </div>
                    <?php endforeach; ?>
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
    <form method="POST" id="department-form" class="saw-panel-confirm">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">

        <?php if (!$completed): ?>
        <label class="saw-panel-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="department_confirmed"
                   id="department-confirmed"
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

    // Accordion functionality
    document.querySelectorAll('.saw-accordion-header').forEach(function(header) {
        header.addEventListener('click', function() {
            var item = this.closest('.saw-accordion-item');
            item.classList.toggle('expanded');
        });
    });

    // Checkbox listener
    var checkbox = document.getElementById('department-confirmed');
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