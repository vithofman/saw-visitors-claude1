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
        'visit_time' => 'Čas',
        'location' => 'Místo',
        'contact' => 'Kontaktní osoba',
        'visitors_title' => 'Návštěvníci',
        'risks_title' => 'Informace o rizicích',
        'risks_text' => 'Popis',
        'risks_docs' => 'Dokumenty',
        'confirm_btn' => 'Dokončit registraci',
        'no_risks' => 'Žádné informace o rizicích',
        'no_visitors' => 'Žádní návštěvníci',
        'not_specified' => 'Nespecifikováno',
    ],
    'en' => [
        'title' => 'Your Visit Summary',
        'pin_label' => 'Your PIN Code',
        'visit_date' => 'Visit Date',
        'visit_time' => 'Time',
        'location' => 'Location',
        'contact' => 'Contact Person',
        'visitors_title' => 'Visitors',
        'risks_title' => 'Risk Information',
        'risks_text' => 'Description',
        'risks_docs' => 'Documents',
        'confirm_btn' => 'Complete Registration',
        'no_risks' => 'No risk information',
        'no_visitors' => 'No visitors',
        'not_specified' => 'Not specified',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// Formátování data a času
$visit_date_formatted = $t['not_specified'];
$visit_time_formatted = '';

// ✅ Priorita 1: saw_visit_schedules tabulka
if (!empty($schedule['date'])) {
    try {
        $date = new DateTime($schedule['date']);
        if ($lang === 'en') {
            $visit_date_formatted = $date->format('F j, Y'); // "November 30, 2025"
        } else {
            $visit_date_formatted = $date->format('j. n. Y'); // "30. 11. 2025"
        }
    } catch (Exception $e) {
        $visit_date_formatted = $schedule['date'];
    }
    
    // Čas ze schedule
    if (!empty($schedule['time_from'])) {
        $visit_time_formatted = substr($schedule['time_from'], 0, 5); // "07:21"
        if (!empty($schedule['time_to'])) {
            $visit_time_formatted .= ' - ' . substr($schedule['time_to'], 0, 5); // "07:21 - 08:21"
        }
    }
}
// ✅ Priorita 2: Fallback na planned_date_from z saw_visits
elseif (!empty($visit['planned_date_from'])) {
    try {
        $datetime = new DateTime($visit['planned_date_from']);
        if ($lang === 'en') {
            $visit_date_formatted = $datetime->format('F j, Y');
        } else {
            $visit_date_formatted = $datetime->format('j. n. Y');
        }
        
        // Extrahuj čas pokud není 00:00
        $time_part = $datetime->format('H:i');
        if ($time_part !== '00:00') {
            $visit_time_formatted = $time_part;
            
            if (!empty($visit['planned_date_to'])) {
                $datetime_to = new DateTime($visit['planned_date_to']);
                $time_to = $datetime_to->format('H:i');
                if ($time_to !== '00:00' && $time_to !== $time_part) {
                    $visit_time_formatted .= ' - ' . $time_to;
                }
            }
        }
    } catch (Exception $e) {
        $visit_date_formatted = $visit['planned_date_from'];
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
    text-align: right;
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
                <span class="value"><?php echo esc_html($visit_date_formatted); ?></span>
            </div>
            <?php if (!empty($visit_time_formatted)): ?>
            <div class="saw-summary-row">
                <span class="label"><?php echo esc_html($t['visit_time']); ?></span>
                <span class="value"><?php echo esc_html($visit_time_formatted); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($address): ?>
            <div class="saw-summary-row">
                <span class="label"><?php echo esc_html($t['location']); ?></span>
                <span class="value">
                    <?php echo esc_html($branch['name'] ?? ''); ?>
                    <?php if ($address): ?><br><small style="color: rgba(255,255,255,0.6);"><?php echo esc_html($address); ?></small><?php endif; ?>
                </span>
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
    
    <!-- Risks Section (only if there are risks) -->
    <?php if (!empty($risks_text) || !empty($risks_docs)): ?>
    <div class="saw-summary-section">
        <div class="saw-summary-section-header">
            <span class="icon">⚠️</span>
            <h3><?php echo esc_html($t['risks_title']); ?></h3>
        </div>
        <div class="saw-summary-section-content">
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