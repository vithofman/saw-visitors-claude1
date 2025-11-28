<?php
/**
 * Invitation Step - Visitors Registration
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - Added visitor status display
 */

if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$lang = $flow['language'] ?? 'cs';
$existing_visitors = $existing_visitors ?? [];

// Get visit status for context
global $wpdb;
$visit_status = $wpdb->get_var($wpdb->prepare(
    "SELECT status FROM {$wpdb->prefix}saw_visits WHERE id = %d",
    $flow['visit_id'] ?? 0
));

$translations = [
    'cs' => [
        'title' => 'Registrace návštěvníků',
        'subtitle' => 'Označte kdo přijde a přidejte další osoby',
        'existing' => 'Předregistrovaní návštěvníci',
        'new' => 'Přidat nové návštěvníky',
        'first_name' => 'Jméno',
        'last_name' => 'Příjmení',
        'position' => 'Pozice',
        'email' => 'Email',
        'phone' => 'Telefon',
        'training_skip' => 'Školení absolvoval do 1 roku',
        'add_visitor' => '+ Přidat dalšího návštěvníka',
        'submit' => 'Pokračovat →',
        // Status labels
        'status_planned' => 'Plánovaný',
        'status_confirmed' => 'Potvrzený',
        'status_present' => 'Přítomen',
        'status_checked_out' => 'Odhlášen',
        'status_no_show' => 'Nedostavil se',
        // Visit status banner
        'visit_in_progress' => '🟢 Návštěva právě probíhá',
        'visit_pending' => '⏳ Návštěva čeká na příchod',
        'visit_confirmed' => '✅ Návštěva je potvrzena',
    ],
    'en' => [
        'title' => 'Visitor Registration',
        'subtitle' => 'Check who will come and add more people',
        'existing' => 'Pre-registered Visitors',
        'new' => 'Add New Visitors',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'position' => 'Position',
        'email' => 'Email',
        'phone' => 'Phone',
        'training_skip' => 'Completed training within 1 year',
        'add_visitor' => '+ Add Another Visitor',
        'submit' => 'Continue →',
        // Status labels
        'status_planned' => 'Planned',
        'status_confirmed' => 'Confirmed',
        'status_present' => 'Present',
        'status_checked_out' => 'Checked Out',
        'status_no_show' => 'No Show',
        // Visit status banner
        'visit_in_progress' => '🟢 Visit is in progress',
        'visit_pending' => '⏳ Waiting for arrival',
        'visit_confirmed' => '✅ Visit is confirmed',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// Status badge configuration
$status_config = [
    'planned' => ['label' => $t['status_planned'], 'color' => '#94a3b8', 'bg' => 'rgba(148, 163, 184, 0.2)'],
    'confirmed' => ['label' => $t['status_confirmed'], 'color' => '#fbbf24', 'bg' => 'rgba(251, 191, 36, 0.2)'],
    'present' => ['label' => $t['status_present'], 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.2)'],
    'checked_out' => ['label' => $t['status_checked_out'], 'color' => '#6366f1', 'bg' => 'rgba(99, 102, 241, 0.2)'],
    'no_show' => ['label' => $t['status_no_show'], 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.2)'],
];
?>
<style>
.saw-invitation-visitors-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.saw-visitors-header {
    text-align: center;
    margin-bottom: 2rem;
}

.saw-visitors-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.5rem;
}

.saw-visitors-header p {
    color: rgba(255, 255, 255, 0.8);
}

/* Visit Status Banner */
.saw-visit-status-banner {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    text-align: center;
    font-weight: 600;
    font-size: 1.1rem;
}

.saw-visit-status-banner.in_progress {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.saw-visit-status-banner.pending {
    background: rgba(251, 191, 36, 0.15);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fbbf24;
}

.saw-visit-status-banner.confirmed {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
    color: #818cf8;
}

.saw-visitors-form {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(148, 163, 184, 0.12);
}

.saw-visitors-existing,
.saw-visitors-new {
    margin-bottom: 2rem;
}

.saw-visitors-existing h3,
.saw-visitors-new h3 {
    color: #fff;
    margin-bottom: 1rem;
}

.saw-visitor-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

/* Status-based card styling */
.saw-visitor-card[data-status="present"] {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.saw-visitor-card[data-status="checked_out"] {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

.saw-visitor-select {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    flex: 1;
    min-width: 200px;
}

.saw-visitor-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.saw-visitor-name {
    color: #fff;
    font-weight: 600;
}

.saw-visitor-position-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
}

/* Status Badge */
.saw-visitor-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
}

.saw-visitor-status-badge .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.saw-visitor-status-badge[data-status="present"] .status-dot {
    background: #10b981;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.saw-visitor-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.saw-training-skip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    font-size: 0.875rem;
}

.saw-visitor-form {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.saw-visitor-form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.saw-visitor-form-header h4 {
    color: #fff;
    margin: 0;
}

.saw-btn-remove {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.25rem;
    line-height: 1;
}

.saw-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 600px) {
    .saw-form-grid {
        grid-template-columns: 1fr;
    }
}

.saw-input {
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    width: 100%;
}

.saw-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.saw-btn-add-visitor,
.saw-btn-submit {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.saw-btn-add-visitor {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    width: 100%;
}

.saw-btn-add-visitor:hover {
    background: rgba(255, 255, 255, 0.15);
}

.saw-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.saw-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-top: 2rem;
    flex-wrap: wrap;
}

/* Disabled state for checked-in visitors */
.saw-visitor-card.is-checked-in .saw-visitor-select input[type="checkbox"] {
    pointer-events: none;
}

.saw-visitor-card.is-checked-in .saw-training-skip {
    opacity: 0.5;
    pointer-events: none;
}
</style>

<div class="saw-invitation-visitors-page">
    <div class="saw-visitors-header">
        <h1><?= esc_html($t['title']) ?></h1>
        <p><?= esc_html($t['subtitle']) ?></p>
    </div>
    
    <?php 
    // Show visit status banner
    if ($visit_status === 'in_progress'): ?>
        <div class="saw-visit-status-banner in_progress">
            <?= esc_html($t['visit_in_progress']) ?>
        </div>
    <?php elseif ($visit_status === 'confirmed'): ?>
        <div class="saw-visit-status-banner confirmed">
            <?= esc_html($t['visit_confirmed']) ?>
        </div>
    <?php elseif ($visit_status === 'pending'): ?>
        <div class="saw-visit-status-banner pending">
            <?= esc_html($t['visit_pending']) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="visitors-form" class="saw-visitors-form">
        <?php wp_nonce_field('saw_invitation_step', 'invitation_nonce'); ?>
        <input type="hidden" name="invitation_action" value="save_visitors">
        
        <?php if (!empty($existing_visitors)): ?>
            <div class="saw-visitors-existing">
                <h3><?= esc_html($t['existing']) ?></h3>
                
                <?php foreach ($existing_visitors as $visitor): 
                    $current_status = $visitor['current_status'] ?? 'planned';
                    $status_info = $status_config[$current_status] ?? $status_config['planned'];
                    $is_checked_in = in_array($current_status, ['present', 'checked_out']);
                ?>
                    <div class="saw-visitor-card <?= $is_checked_in ? 'is-checked-in' : '' ?>" 
                         data-status="<?= esc_attr($current_status) ?>">
                        
                        <label class="saw-visitor-select">
                            <input type="checkbox" 
                                   name="existing_visitor_ids[]" 
                                   value="<?= $visitor['id'] ?>"
                                   <?= $is_checked_in ? 'checked disabled' : 'checked' ?>>
                            <?php if ($is_checked_in): ?>
                                <!-- Hidden field to ensure checked-in visitors are always included -->
                                <input type="hidden" name="existing_visitor_ids[]" value="<?= $visitor['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="saw-visitor-info">
                                <span class="saw-visitor-name">
                                    <?= esc_html($visitor['first_name'] . ' ' . $visitor['last_name']) ?>
                                </span>
                                <?php if (!empty($visitor['position'])): ?>
                                    <span class="saw-visitor-position-text">
                                        <?= esc_html($visitor['position']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </label>
                        
                        <div class="saw-visitor-meta">
                            <!-- Status Badge -->
                            <span class="saw-visitor-status-badge" 
                                  data-status="<?= esc_attr($current_status) ?>"
                                  style="background: <?= $status_info['bg'] ?>; color: <?= $status_info['color'] ?>; border: 1px solid <?= $status_info['color'] ?>33;">
                                <?php if ($current_status === 'present'): ?>
                                    <span class="status-dot"></span>
                                <?php endif; ?>
                                <?= esc_html($status_info['label']) ?>
                            </span>
                            
                            <?php if (!$is_checked_in): ?>
                                <label class="saw-training-skip">
                                    <input type="checkbox" 
                                           name="training_skip[<?= $visitor['id'] ?>]" 
                                           value="1"
                                           <?= !empty($visitor['training_skipped']) ? 'checked' : '' ?>>
                                    <span><?= esc_html($t['training_skip']) ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="saw-visitors-new">
            <h3><?= esc_html($t['new']) ?></h3>
            <div id="new-visitors-container"></div>
            
            <button type="button" class="saw-btn-add-visitor" onclick="addNewVisitor()">
                <?= esc_html($t['add_visitor']) ?>
            </button>
        </div>
        
        <div class="form-actions">
            <?php if ($this->can_go_back()): ?>
            <button type="submit" 
                    name="invitation_action" 
                    value="go_back" 
                    class="btn btn-secondary">
                <svg style="width:1.25rem;height:1.25rem;margin-right:0.5rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                <?php echo $lang === 'en' ? 'Back' : 'Zpět'; ?>
            </button>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
            
            <button type="submit" class="saw-btn-primary saw-btn-submit">
                <?= esc_html($t['submit']) ?>
            </button>
        </div>
    </form>
</div>

<script>
let visitorCount = 0;

function addNewVisitor() {
    visitorCount++;
    const html = `
        <div class="saw-visitor-form" id="visitor-${visitorCount}">
            <div class="saw-visitor-form-header">
                <h4><?= $lang === 'en' ? 'Visitor' : 'Návštěvník' ?> #${visitorCount}</h4>
                <button type="button" class="saw-btn-remove" onclick="removeVisitor(${visitorCount})">×</button>
            </div>
            
            <div class="saw-form-grid">
                <input type="text" 
                       name="new_visitors[${visitorCount}][first_name]" 
                       placeholder="<?= esc_attr($t['first_name']) ?>" 
                       required 
                       class="saw-input">
                
                <input type="text" 
                       name="new_visitors[${visitorCount}][last_name]" 
                       placeholder="<?= esc_attr($t['last_name']) ?>" 
                       required 
                       class="saw-input">
                
                <input type="text" 
                       name="new_visitors[${visitorCount}][position]" 
                       placeholder="<?= esc_attr($t['position']) ?>" 
                       class="saw-input">
                
                <input type="email" 
                       name="new_visitors[${visitorCount}][email]" 
                       placeholder="<?= esc_attr($t['email']) ?>" 
                       class="saw-input">
                
                <input type="tel" 
                       name="new_visitors[${visitorCount}][phone]" 
                       placeholder="<?= esc_attr($t['phone']) ?>" 
                       class="saw-input">
            </div>
            
            <label class="saw-training-skip">
                <input type="checkbox" name="new_visitors[${visitorCount}][training_skip]" value="1">
                <span><?= esc_html($t['training_skip']) ?></span>
            </label>
        </div>
    `;
    
    document.getElementById('new-visitors-container').insertAdjacentHTML('beforeend', html);
}

function removeVisitor(id) {
    document.getElementById(`visitor-${id}`).remove();
}

// Auto-add first visitor form if no existing visitors
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelectorAll('.saw-visitor-card').length === 0) {
        addNewVisitor();
    }
    
    // Form validation
    const form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const existingChecked = document.querySelectorAll('input[name="existing_visitor_ids[]"]:checked').length;
            const existingHidden = document.querySelectorAll('input[type="hidden"][name="existing_visitor_ids[]"]').length;
            
            const newVisitorForms = document.querySelectorAll('.saw-visitor-form');
            let hasValidNewVisitor = false;
            
            newVisitorForms.forEach(function(formEl) {
                const firstName = formEl.querySelector('input[name*="[first_name]"]');
                const lastName = formEl.querySelector('input[name*="[last_name]"]');
                
                if (firstName && lastName && 
                    firstName.value.trim() !== '' && 
                    lastName.value.trim() !== '') {
                    hasValidNewVisitor = true;
                }
            });
            
            const totalVisitors = existingChecked + existingHidden + (hasValidNewVisitor ? 1 : 0);
            
            if (totalVisitors === 0) {
                e.preventDefault();
                alert('<?= $lang === 'en' ? 'Please select or add at least one visitor' : 'Prosím vyberte nebo přidejte alespoň jednoho návštěvníka' ?>');
            }
        });
    }
});
</script>