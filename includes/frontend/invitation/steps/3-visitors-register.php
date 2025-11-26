<?php
/**
 * Invitation Step - Visitors Registration
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$lang = $flow['language'] ?? 'cs';
$existing_visitors = $existing_visitors ?? [];

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
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<style>
.saw-invitation-visitors-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.saw-visitors-header {
    text-align: center;
    margin-bottom: 3rem;
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
}

.saw-visitor-select {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.saw-visitor-name {
    color: #fff;
    font-weight: 600;
}

.saw-training-skip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
}

.saw-visitor-form {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.saw-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.saw-input {
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    width: 100%;
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
}

.saw-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    width: 100%;
    margin-top: 2rem;
}
</style>

<div class="saw-invitation-visitors-page">
    <div class="saw-visitors-header">
        <h1><?= esc_html($t['title']) ?></h1>
        <p><?= esc_html($t['subtitle']) ?></p>
    </div>
    
    <form method="POST" id="visitors-form" class="saw-visitors-form">
        <?php wp_nonce_field('saw_invitation_step', 'invitation_nonce'); ?>
        <input type="hidden" name="invitation_action" value="save_visitors">
        
        <?php if (!empty($existing_visitors)): ?>
            <div class="saw-visitors-existing">
                <h3><?= esc_html($t['existing']) ?></h3>
                
                <?php foreach ($existing_visitors as $visitor): ?>
                    <div class="saw-visitor-card">
                        <label class="saw-visitor-select">
                            <input type="checkbox" 
                                   name="existing_visitor_ids[]" 
                                   value="<?= $visitor['id'] ?>"
                                   checked>
                            <span class="saw-visitor-name">
                                <?= esc_html($visitor['first_name'] . ' ' . $visitor['last_name']) ?>
                            </span>
                        </label>
                        
                        <label class="saw-training-skip">
                            <input type="checkbox" 
                                   name="training_skip[<?= $visitor['id'] ?>]" 
                                   value="1">
                            <span><?= esc_html($t['training_skip']) ?></span>
                        </label>
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
        
        <!-- ✅ NOVÝ form-actions s back button -->
        <div class="form-actions" style="display: flex; gap: 1rem; justify-content: space-between; margin-top: 2rem; flex-wrap: wrap;">
            
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
            <div></div><!-- Spacer pro layout -->
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
                <h4>Návštěvník #${visitorCount}</h4>
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
// Auto-add first visitor if no existing visitors
if (document.querySelectorAll('.saw-visitor-card').length === 0) {
    addNewVisitor();
}

// ===================================
// FORM VALIDATION
// ===================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    
    if (!form) {
        console.error('[Invitation] Form not found');
        return;
    }
    
    form.addEventListener('submit', function(e) {
        // 1. Zkontroluj existing visitors (checkboxy)
        const existingChecked = document.querySelectorAll('input[name="existing_visitor_ids[]"]:checked').length;
        
        // 2. Zkontroluj nové visitors (formuláře)
        const newVisitorForms = document.querySelectorAll('.saw-visitor-form');
        let hasValidNewVisitor = false;
        
        newVisitorForms.forEach(function(form) {
            const firstName = form.querySelector('input[name*="[first_name]"]');
            const lastName = form.querySelector('input[name*="[last_name]"]');
            
            // Pokud má OBOJÍ jméno i příjmení vyplněné, je to validní visitor
            if (firstName && lastName && 
                firstName.value.trim() !== '' && 
                lastName.value.trim() !== '') {
                hasValidNewVisitor = true;
            }
        });
        
        // 3. Validace: Musí být alespoň JEDEN visitor
        if (existingChecked === 0 && !hasValidNewVisitor) {
            e.preventDefault();
            
            // Zobraz chybu v jazyce uživatele
            const lang = '<?php echo $lang; ?>';
            const errorMsg = lang === 'en' 
                ? 'Please add at least one visitor with first name and last name filled.'
                : 'Přidejte alespoň jednoho návštěvníka s vyplněným jménem a příjmením.';
            
            alert(errorMsg);
            
            // Focus na první nový visitor formulář pokud existuje
            if (newVisitorForms.length > 0) {
                const firstForm = newVisitorForms[0];
                const firstNameInput = firstForm.querySelector('input[name*="[first_name]"]');
                if (firstNameInput) {
                    firstNameInput.focus();
                }
            }
            
            return false;
        }
        
        // Vše OK, formulář může být odeslán
        return true;
    });
});
</script>

