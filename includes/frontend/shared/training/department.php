<?php
/**
 * Shared Training Step - Department Information
 * Works for both Terminal and Invitation flows
 * 
 * @package SAW_Visitors
 * @version 3.4.0
 * 
 * ZMĚNA v 3.4.0:
 * - Invitation flow nyní filtruje departments podle navštěvovaných hostů
 * - Stejná logika jako v Terminal (saw_visit_hosts → saw_user_departments)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Detect flow type
$is_invitation = isset($is_invitation) ? $is_invitation : false;

// Get data from appropriate flow
if ($is_invitation) {
    // Invitation flow
    $session = SAW_Session_Manager::instance();  // ✅ OPRAVENO
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
    
    // ✅ NOVÉ: Načíst departments z databáze - FILTROVANÉ PODLE HOSTŮ
    $departments = [];
    if ($visit) {
        // Najdi language_id
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $visit->customer_id,
            $lang
        ));
        
        error_log("[SHARED DEPARTMENT.PHP] Looking for language_id, customer: {$visit->customer_id}, lang: {$lang}");
        error_log("[SHARED DEPARTMENT.PHP] Found language_id: " . ($language_id ? $language_id : 'NOT FOUND'));
        
        if ($language_id) {
            // Najdi training_content
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $visit->customer_id,
                $visit->branch_id,
                $language_id
            ));
            
            error_log("[SHARED DEPARTMENT.PHP] Found content_id: " . ($content ? $content->id : 'NOT FOUND'));
            
            if ($content) {
                error_log("[DEPT] Content ID: " . $content->id);
                
                // ✅ NOVÉ: Získej department IDs filtrované podle hostů (stejně jako Terminal)
                $host_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                    $visit->id
                ));
                
                error_log("[SHARED DEPARTMENT.PHP Invitation] Host IDs for visit #{$visit->id}: " . implode(', ', $host_ids));
                
                $department_ids = [];
                
                if (!empty($host_ids)) {
                    foreach ($host_ids as $host_id) {
                        // Získej departments přiřazené tomuto hostovi
                        $host_dept_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                            $host_id
                        ));
                        
                        error_log("[SHARED DEPARTMENT.PHP Invitation] Host #{$host_id} departments: " . implode(', ', $host_dept_ids));
                        
                        // Pokud host nemá přiřazená oddělení (admin/super_manager) → všechna oddělení pobočky
                        if (empty($host_dept_ids)) {
                            $all_dept_ids = $wpdb->get_col($wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}saw_departments 
                                 WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                                $visit->customer_id,
                                $visit->branch_id
                            ));
                            $department_ids = array_merge($department_ids, $all_dept_ids);
                            error_log("[SHARED DEPARTMENT.PHP Invitation] Host #{$host_id} is admin - using ALL branch departments: " . implode(', ', $all_dept_ids));
                        } else {
                            $department_ids = array_merge($department_ids, $host_dept_ids);
                        }
                    }
                    
                    $department_ids = array_unique($department_ids);
                    error_log("[SHARED DEPARTMENT.PHP Invitation] Final filtered department IDs: " . implode(', ', $department_ids));
                }
                
                // ✅ Načti content JEN pro filtrovaná oddělení
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
                    error_log("[SHARED DEPARTMENT.PHP Invitation] No hosts found - showing no departments");
                    $dept_rows = [];
                }
                
                error_log("[DEPT] SQL: " . $wpdb->last_query);
                error_log("[DEPT] Error: " . $wpdb->last_error);
                error_log("[DEPT] Rows found: " . count($dept_rows));
                error_log("[DEPT] Raw result: " . json_encode($dept_rows));
                
                foreach ($dept_rows as $dept) {
                    error_log("[SHARED DEPARTMENT.PHP] Processing dept: " . ($dept['department_name'] ?? 'NO NAME') . ", text_content: " . (!empty($dept['text_content']) ? 'YES' : 'NO'));
                    
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
                
                error_log("[SHARED DEPARTMENT.PHP Invitation] Loaded " . count($departments) . " departments with content (filtered by hosts)");
            }
        }
    }
} else {
    // Terminal flow
    $flow = isset($flow) ? $flow : [];
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
    $visitor_id = isset($flow['visitor_ids'][0]) ? $flow['visitor_ids'][0] : null;
    $departments = isset($departments) ? $departments : [];
}

$has_departments = !empty($departments);

error_log("[SHARED DEPARTMENT.PHP] Is Invitation: " . ($is_invitation ? 'yes' : 'no') . ", Language: {$lang}, Visitor ID: " . ($visitor_id ?? 'NULL'));
error_log("[SHARED DEPARTMENT.PHP] Final departments count: " . count($departments));

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
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-department saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container saw-page-container-wide">
            
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
                                        <p>Žádný textový obsah</p>
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
    
    <!-- UNIFIED Floating Panel -->
    <form method="POST" id="department-form" class="saw-panel-confirm">
        <?php 
        $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
        $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
        $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
        $complete_action = $is_invitation ? 'complete_training' : 'complete_training_department';
        wp_nonce_field($nonce_name, $nonce_field); 
        ?>
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
    document.querySelectorAll('.saw-accordion-header').forEach(header => {
        header.addEventListener('click', function() {
            const item = this.closest('.saw-accordion-item');
            item.classList.toggle('expanded');
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
error_log("[DEPARTMENT.PHP] Unified design with departments accordion loaded (v3.4.0 - filtered by hosts)");
?>