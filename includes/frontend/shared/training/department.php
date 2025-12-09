<?php
/**
 * Shared Training Step - Department Information
 * Works for Terminal, Invitation and Visitor Info flows
 * 
 * @package SAW_Visitors
 * @version 3.9.9
 * 
 * ZMĚNA v 3.9.9:
 * - REMOVED: Skip training sekce úplně odstraněna
 * - FIX: Dokumenty používají správné CSS třídy (saw-doc-card, saw-doc-badge) jako terminal/additional
 * - NEW: Free mode pro invitation - checkbox volitelný, button aktivní
 * - FIX: Přidán fallback pro invitation context když visit nemá hosts
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

// FREE MODE for invitation - no confirmation required
$free_mode = ($context === 'invitation');

// Get data from appropriate flow
if ($context === 'invitation') {
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    $lang = $flow['language'] ?? 'cs';
    
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
    
    // Load departments from DB - FILTERED BY HOSTS
    $departments = [];
    if ($visit) {
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        if ($language_id) {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            if ($content) {
                // Get department IDs filtered by hosts
                $host_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                    $visit->id
                ));
                
                $department_ids = [];
                
                if (!empty($host_ids)) {
                    foreach ($host_ids as $host_id) {
                        $host_dept_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                            $host_id
                        ));
                        
                        if (empty($host_dept_ids)) {
                            // Admin/super_manager - all departments
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
                } else {
                    // FIX v3.9.9: FALLBACK when no hosts - load all active departments
                    $department_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}saw_departments 
                         WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                        $visit->customer_id,
                        $visit->branch_id
                    ));
                }
                
                // Load content only for filtered departments
                $dept_rows = [];
                if (!empty($department_ids)) {
                    $placeholders = implode(',', array_fill(0, count($department_ids), '%d'));
                    $query_params = array_merge([$content->id], $department_ids);
                    
                    $dept_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT tdc.*, d.name as department_name, d.description as department_description
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
                }
                
                foreach ($dept_rows as $dept) {
                    $docs = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                         WHERE document_type = 'department' AND reference_id = %d 
                         ORDER BY uploaded_at ASC",
                        $dept['id']
                    ), ARRAY_A);
                    
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
    $flow = isset($flow) ? $flow : array();
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;
    $departments = isset($departments) ? $departments : array();
} else {
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
                                        $file_url = content_url() . '/uploads' . (strpos($doc['file_path'], '/') === 0 ? '' : '/') . $doc['file_path'];
                                        $filename = $doc['file_name'];
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
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Floating Panel -->
    <form method="POST" id="department-form" class="saw-panel-confirm">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">

        <?php if (!$completed): ?>
        <label class="saw-panel-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="department_confirmed"
                   id="department-confirmed"
                   value="1"
                   <?php if (!$free_mode): ?>required<?php endif; ?>>
            <span><?php echo esc_html($t['confirm']); ?></span>
        </label>
        <?php endif; ?>

        <button type="submit"
                class="saw-panel-btn"
                id="continue-btn"
                <?php echo (!$completed && !$free_mode) ? 'disabled' : ''; ?>>
            <?php echo esc_html($t['continue']); ?> →
        </button>
    </form>
</div>

<script>
(function() {
    'use strict';

    document.querySelectorAll('.saw-accordion-header').forEach(function(header) {
        header.addEventListener('click', function() {
            var item = this.closest('.saw-accordion-item');
            item.classList.toggle('expanded');
        });
    });

    var checkbox = document.getElementById('department-confirmed');
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
