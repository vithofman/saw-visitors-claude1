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
    
    <!-- UNIFIED Floating Panel -->
    <form method="POST" id="department-form" class="saw-panel-confirm">
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        <input type="hidden" name="terminal_action" value="complete_training_department">

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
// Skip button for invitation mode
$is_invitation = ($flow['mode'] ?? '') === 'invitation';
if ($is_invitation): 
?>
    <div class="saw-training-skip-wrapper">
        <p class="saw-skip-info">
            💡 Toto školení je volitelné. Můžete ho přeskočit a projít si později.
        </p>
        <form method="POST" style="display: inline-block;">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="skip_training">
            <button type="submit" class="saw-btn-skip">
                ⏭️ Přeskočit školení
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if ($is_invitation): ?>
<style>
.saw-training-skip-wrapper {
    margin-top: 2rem;
    padding: 1.5rem;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 12px;
    text-align: center;
}

.saw-skip-info {
    color: #c4b5fd;
    margin-bottom: 1rem;
}

.saw-btn-skip {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: #f9fafb;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.saw-btn-skip:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>
<?php endif; ?>

<?php
error_log("[DEPARTMENT.PHP] Unified design with departments accordion loaded (v3.3.0)");
?>