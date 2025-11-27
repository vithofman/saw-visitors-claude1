<?php
if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$lang = $flow['language'] ?? 'cs';

// Překlady
$translations = [
    'cs' => [
        'title' => 'Přehled vaší návštěvy',
        'pin_label' => 'Váš PIN kód',
        'visit_date' => 'Termín návštěvy',
        'location' => 'Místo',
        'contact' => 'Kontaktní osoba',
        'visitors_title' => 'Návštěvníci',
        'risks_title' => 'Informace o rizicích',
        'risks_text' => 'Popis',
        'risks_docs' => 'Dokumenty',
        'training_title' => 'Školení',
        'training_completed' => 'Dokončeno',
        'training_skipped' => 'Přeskočeno',
        'training_pending' => 'Čeká',
        'confirm_btn' => 'Dokončit registraci',
        'no_risks' => 'Žádné informace o rizicích',
        'no_visitors' => 'Žádní návštěvníci',
    ],
    'en' => [
        'title' => 'Your Visit Summary',
        'pin_label' => 'Your PIN Code',
        'visit_date' => 'Visit Date',
        'location' => 'Location',
        'contact' => 'Contact Person',
        'visitors_title' => 'Visitors',
        'risks_title' => 'Risk Information',
        'risks_text' => 'Description',
        'risks_docs' => 'Documents',
        'training_title' => 'Training',
        'training_completed' => 'Completed',
        'training_skipped' => 'Skipped',
        'training_pending' => 'Pending',
        'confirm_btn' => 'Complete Registration',
        'no_risks' => 'No risk information',
        'no_visitors' => 'No visitors',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// Formátování data
$visit_date = '';
if (!empty($visit['scheduled_date'])) {
    $date = new DateTime($visit['scheduled_date']);
    $visit_date = $date->format('j. n. Y');
    
    if (!empty($visit['scheduled_time_from'])) {
        $visit_date .= ', ' . substr($visit['scheduled_time_from'], 0, 5);
        if (!empty($visit['scheduled_time_to'])) {
            $visit_date .= ' - ' . substr($visit['scheduled_time_to'], 0, 5);
        }
    }
}

// Adresa
$address = '';
if ($branch) {
    $parts = array_filter([
        $branch['street'] ?? '',
        $branch['city'] ?? '',
        $branch['zip'] ?? ''
    ]);
    $address = implode(', ', $parts);
}
?>

<style>
/* Summary page styles */
.saw-summary-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.saw-summary-header {
    text-align: center;
    margin-bottom: 2rem;
}

.saw-summary-header h1 {
    color: #fff;
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
}

.saw-pin-highlight {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 2rem;
}

.saw-pin-highlight .pin-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.saw-pin-highlight .pin-code {
    font-size: 2.5rem;
    font-weight: bold;
    color: #fff;
    letter-spacing: 0.5rem;
    font-family: monospace;
}

.saw-summary-section {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.saw-summary-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.saw-summary-section-header .icon {
    font-size: 1.25rem;
}

.saw-summary-section-header h3 {
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.saw-summary-section-content {
    padding: 1rem 1.25rem;
    color: rgba(255, 255, 255, 0.85);
}

.saw-summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.saw-summary-row:last-child {
    border-bottom: none;
}

.saw-summary-row .label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
}

.saw-summary-row .value {
    color: #fff;
    font-weight: 500;
}

.saw-visitor-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.saw-visitor-item:last-child {
    margin-bottom: 0;
}

.saw-visitor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
}

.saw-visitor-info {
    flex: 1;
}

.saw-visitor-name {
    color: #fff;
    font-weight: 500;
}

.saw-visitor-email {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8125rem;
}

.saw-doc-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
}

.saw-doc-icon {
    color: #ef4444;
}

.saw-training-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.saw-training-status.completed {
    color: #10b981;
}

.saw-training-status.skipped {
    color: #f59e0b;
}

.saw-training-status.pending {
    color: rgba(255, 255, 255, 0.5);
}

.saw-summary-actions {
    margin-top: 2rem;
    text-align: center;
}

.saw-btn-confirm {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 1.125rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
    transition: all 0.3s ease;
}

.saw-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 30px rgba(16, 185, 129, 0.5);
}

.saw-empty-state {
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
    font-style: italic;
    padding: 1rem 0;
}
</style>

<div class="saw-summary-page">
    <div class="saw-summary-header">
        <h1><?php echo esc_html($t['title']); ?></h1>
    </div>
    
    <!-- PIN Code Highlight -->
    <div class="saw-pin-highlight">
        <div class="pin-label"><?php echo esc_html($t['pin_label']); ?></div>
        <div class="pin-code"><?php echo esc_html($pin); ?></div>
    </div>
    
    <!-- Visit Info Section -->
    <div class="saw-summary-section">
        <div class="saw-summary-section-header">
            <span class="icon">📅</span>
            <h3><?php echo esc_html($t['visit_date']); ?></h3>
        </div>
        <div class="saw-summary-section-content">
            <div class="saw-summary-row">
                <span class="label"><?php echo esc_html($t['visit_date']); ?></span>
                <span class="value"><?php echo esc_html($visit_date); ?></span>
            </div>
            <?php if ($address): ?>
            <div class="saw-summary-row">
                <span class="label"><?php echo esc_html($t['location']); ?></span>
                <span class="value"><?php echo esc_html($branch['name'] ?? ''); ?><br><small><?php echo esc_html($address); ?></small></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Contact Person Section -->
    <?php if (!empty($hosts)): ?>
    <div class="saw-summary-section">
        <div class="saw-summary-section-header">
            <span class="icon">👤</span>
            <h3><?php echo esc_html($t['contact']); ?></h3>
        </div>
        <div class="saw-summary-section-content">
            <?php foreach ($hosts as $host): ?>
            <div class="saw-summary-row">
                <span class="value"><?php echo esc_html($host['display_name']); ?></span>
                <span class="label">
                    <?php echo esc_html($host['user_email']); ?>
                    <?php if (!empty($host['phone'])): ?>
                        | <?php echo esc_html($host['phone']); ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Visitors Section -->
    <div class="saw-summary-section">
        <div class="saw-summary-section-header">
            <span class="icon">👥</span>
            <h3><?php echo esc_html($t['visitors_title']); ?> (<?php echo count($visitors); ?>)</h3>
        </div>
        <div class="saw-summary-section-content">
            <?php if (!empty($visitors)): ?>
                <?php foreach ($visitors as $visitor): ?>
                <div class="saw-visitor-item">
                    <div class="saw-visitor-avatar">
                        <?php echo strtoupper(substr($visitor['first_name'] ?? '?', 0, 1)); ?>
                    </div>
                    <div class="saw-visitor-info">
                        <div class="saw-visitor-name">
                            <?php echo esc_html(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? '')); ?>
                        </div>
                        <div class="saw-visitor-email">
                            <?php echo esc_html($visitor['email'] ?? ''); ?>
                            <?php if (!empty($visitor['company_name'])): ?>
                                • <?php echo esc_html($visitor['company_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="saw-empty-state"><?php echo esc_html($t['no_visitors']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Risks Section -->
    <div class="saw-summary-section">
        <div class="saw-summary-section-header">
            <span class="icon">⚠️</span>
            <h3><?php echo esc_html($t['risks_title']); ?></h3>
        </div>
        <div class="saw-summary-section-content">
            <?php if (!empty($risks_text) || !empty($risks_docs)): ?>
                <?php if (!empty($risks_text)): ?>
                <div style="margin-bottom: 1rem;">
                    <div class="label" style="margin-bottom: 0.5rem;"><?php echo esc_html($t['risks_text']); ?>:</div>
                    <div style="color: rgba(255,255,255,0.9);"><?php echo wp_kses_post($risks_text); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($risks_docs)): ?>
                <div>
                    <div class="label" style="margin-bottom: 0.5rem;"><?php echo esc_html($t['risks_docs']); ?>:</div>
                    <?php foreach ($risks_docs as $doc): ?>
                    <div class="saw-doc-item">
                        <span class="saw-doc-icon">📄</span>
                        <span><?php echo esc_html($doc['file_name']); ?></span>
                        <small style="color: rgba(255,255,255,0.5);">
                            (<?php echo size_format($doc['file_size']); ?>)
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="saw-empty-state"><?php echo esc_html($t['no_risks']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Training Section (only if training exists) -->
    <?php if ($has_training): ?>
    <div class="saw-summary-section">
        <div class="saw-summary-section-header">
            <span class="icon">📚</span>
            <h3><?php echo esc_html($t['training_title']); ?></h3>
        </div>
        <div class="saw-summary-section-content">
            <?php
            $training_steps = ['training-video', 'training-map', 'training-risks', 'training-department', 'training-additional'];
            $training_labels = [
                'training-video' => 'Video',
                'training-map' => $lang === 'en' ? 'Map' : 'Mapa',
                'training-risks' => $lang === 'en' ? 'Risks' : 'Rizika',
                'training-department' => $lang === 'en' ? 'Department' : 'Oddělení',
                'training-additional' => $lang === 'en' ? 'Additional' : 'Další',
            ];
            
            foreach ($training_steps as $ts):
                $is_completed = in_array($ts, $completed_steps);
                $status_class = $is_completed ? 'completed' : 'pending';
                $status_text = $is_completed ? $t['training_completed'] : $t['training_pending'];
                $status_icon = $is_completed ? '✅' : '⏳';
            ?>
            <div class="saw-summary-row">
                <span class="label"><?php echo esc_html($training_labels[$ts]); ?></span>
                <span class="saw-training-status <?php echo $status_class; ?>">
                    <?php echo $status_icon; ?> <?php echo esc_html($status_text); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Confirm Button -->
    <div class="saw-summary-actions">
        <form method="POST">
            <?php wp_nonce_field('saw_invitation_step', 'invitation_nonce'); ?>
            <input type="hidden" name="invitation_action" value="confirm_summary">
            <button type="submit" class="saw-btn-confirm">
                ✅ <?php echo esc_html($t['confirm_btn']); ?>
            </button>
        </form>
    </div>
</div>

