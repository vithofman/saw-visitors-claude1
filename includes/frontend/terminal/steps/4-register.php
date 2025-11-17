<?php
/**
 * Terminal Step - Universal Visitor Registration
 * 
 * Unified form for:
 * - Selecting pre-registered visitors (planned visits)
 * - Adding new visitors (both planned and walk-in)
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$is_planned = ($flow['type'] ?? '') === 'planned';
$existing_visitors = $flow['visitors'] ?? [];

$translations = [
    'cs' => [
        'title' => 'Registrace návštěvníků',
        'subtitle' => 'Označte kdo přišel a případně přidejte další osoby',
        'section_existing' => 'Předregistrovaní návštěvníci',
        'section_new' => 'Přidat nové návštěvníky',
        'section_company' => 'Informace o firmě',
        'section_visit' => 'Informace o návštěvě',
        'company' => 'Název firmy',
        'company_placeholder' => 'např. ACME s.r.o.',
        'is_individual' => 'Jsem fyzická osoba (soukromá návštěva)',
        'visitor_number' => 'Nový návštěvník',
        'first_name' => 'Jméno',
        'last_name' => 'Příjmení',
        'position' => 'Funkce / Pozice',
        'position_placeholder' => 'např. Obchodní ředitel',
        'email' => 'Email',
        'email_placeholder' => 'vas.email@example.com',
        'phone' => 'Telefon',
        'phone_placeholder' => '+420 123 456 789',
        'certificates' => 'Profesní průkazy',
        'add_certificate' => '+ Přidat průkaz',
        'cert_name' => 'Název průkazu',
        'cert_name_placeholder' => 'např. Vazač břemen',
        'cert_number' => 'Číslo průkazu',
        'cert_valid_until' => 'Platnost do',
        'hosts' => 'Koho navštěvujete?',
        'hosts_placeholder' => 'Vyberte jednu nebo více osob...',
        'hosts_help' => 'Můžete vybrat více osob',
        'training_skipped' => 'Absolvoval/a školení do 1 roku',
        'add_visitor' => '+ Přidat dalšího návštěvníka',
        'remove_visitor' => 'Odstranit',
        'submit' => 'Pokračovat',
        'required' => 'Povinné pole',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>

<div class="saw-terminal-card saw-terminal-card-wide">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            📝 <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        <form method="POST" class="saw-terminal-form" id="registration-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="submit_unified_registration">
            
            <!-- ============================================
                 EXISTING VISITORS (only if planned + has visitors)
                 ============================================ -->
            <?php if ($is_planned && !empty($existing_visitors)): ?>
                <?php 
                // ✅ Rozděl visitors podle toho, zda jsou dnes checked in
                $already_checked_in = [];
                $waiting_for_checkin = [];
                
                foreach ($existing_visitors as $visitor) {
                    // Pokud má dnes checkout = je odhlášený, ale zobraz ho jako čeká
                    if (!empty($visitor['today_checkout'])) {
                        $waiting_for_checkin[] = $visitor;
                    } 
                    // Pokud je confirmed a NEMÁ dnes checkout = je přihlášený
                    elseif ($visitor['participation_status'] === 'confirmed') {
                        $already_checked_in[] = $visitor;
                    } 
                    // Ostatní (planned, no_show) = čeká
                    else {
                        $waiting_for_checkin[] = $visitor;
                    }
                }
                ?>
                
                <!-- ✅ UŽ ZAREGISTROVANÍ (jen info) -->
                <?php if (!empty($already_checked_in)): ?>
                <div class="saw-terminal-form-section saw-section-success">
                    <h3 class="saw-terminal-form-section-title">
                        ✅ Již zaregistrováni (<?php echo count($already_checked_in); ?>)
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($already_checked_in as $visitor): ?>
                        <div class="saw-visitor-info-card">
                            <div style="font-size: 1.125rem; font-weight: 700; color: #10b981;">
                                ✓ <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                            </div>
                            <?php if (!empty($visitor['position'])): ?>
                                <div style="color: #64748b; font-size: 0.875rem;">
                                    <?php echo esc_html($visitor['position']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ✅ JEŠTĚ NEPŘIŠLI (checkboxy) -->
                <?php if (!empty($waiting_for_checkin)): ?>
                <div class="saw-terminal-form-section">
                    <h3 class="saw-terminal-form-section-title">
                        👥 Ještě nepřišli - označte kdo přišel (<?php echo count($waiting_for_checkin); ?>)
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($waiting_for_checkin as $visitor): ?>
                        <label class="saw-terminal-visitor-card">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <input type="checkbox" 
                                       name="existing_visitor_ids[]" 
                                       value="<?php echo $visitor['id']; ?>" 
                                       checked 
                                       style="width: 24px; height: 24px; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-size: 1.125rem; font-weight: 700; color: #1e293b;">
                                        <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                                    </div>
                                    <?php if (!empty($visitor['position'])): ?>
                                        <div style="color: #64748b; font-size: 0.875rem;">
                                            <?php echo esc_html($visitor['position']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #475569; cursor: pointer;">
                                    <input type="checkbox" 
                                           name="existing_training_skip[<?php echo $visitor['id']; ?>]" 
                                           value="1"
                                           style="cursor: pointer;">
                                    <span><?php echo esc_html($t['training_skipped']); ?></span>
                                </label>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- ============================================
                 COMPANY SECTION (only for walk-in)
                 ============================================ -->
            <?php if (!$is_planned): ?>
            <div class="saw-terminal-form-section">
                
                <!-- Individual Checkbox -->
                <div class="saw-terminal-form-group">
                    <div class="saw-terminal-form-checkbox">
                        <input type="checkbox" 
                               name="is_individual" 
                               id="is-individual" 
                               value="1">
                        <label for="is-individual">
                            <?php echo esc_html($t['is_individual']); ?>
                        </label>
                    </div>
                </div>
                
                <!-- Company Input -->
                <div id="company-section">
                    <h3 class="saw-terminal-form-section-title">
                        🏢 <?php echo esc_html($t['section_company']); ?>
                    </h3>
                    
                    <div class="saw-terminal-form-group">
                        <label class="saw-terminal-form-label">
                            <?php echo esc_html($t['company']); ?> <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="company_name" 
                               class="saw-terminal-form-input" 
                               placeholder="<?php echo esc_attr($t['company_placeholder']); ?>"
                               id="company-input"
                               required>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ============================================
                 NEW VISITORS SECTION (dynamic)
                 ============================================ -->
            <div class="saw-terminal-form-section">
                <h3 class="saw-terminal-form-section-title">
                    ➕ <?php echo esc_html($t['section_new']); ?>
                </h3>
                
                <div id="new-visitors-container">
                    <!-- First visitor (template) -->
                    <div class="visitor-block" data-index="0">
                        <div class="visitor-header" onclick="toggleVisitorBlock(0)">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span class="visitor-toggle">▼</span>
                                <h4><?php echo esc_html($t['visitor_number']); ?> 1</h4>
                            </div>
                            <button type="button" class="remove-visitor-btn" style="display: none;" onclick="event.stopPropagation(); removeVisitor(0)">
                                <?php echo esc_html($t['remove_visitor']); ?>
                            </button>
                        </div>
                        
                        <div class="visitor-content">
                            <div class="saw-terminal-form-row">
                                <div class="saw-terminal-form-group">
                                    <label class="saw-terminal-form-label">
                                        <?php echo esc_html($t['first_name']); ?> <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                   name="new_visitors[0][first_name]" 
                                   class="saw-terminal-form-input">
                                </div>
                                
                                <div class="saw-terminal-form-group">
                                    <label class="saw-terminal-form-label">
                                        <?php echo esc_html($t['last_name']); ?> <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                   name="new_visitors[0][last_name]" 
                                   class="saw-terminal-form-input">
                                </div>
                            </div>
                            
                            <div class="saw-terminal-form-row">
                                <div class="saw-terminal-form-group">
                                    <label class="saw-terminal-form-label">
                                        <?php echo esc_html($t['position']); ?>
                                    </label>
                                    <input type="text" 
                                           name="new_visitors[0][position]" 
                                           class="saw-terminal-form-input" 
                                           placeholder="<?php echo esc_attr($t['position_placeholder']); ?>">
                                </div>
                                
                                <div class="saw-terminal-form-group">
                                    <label class="saw-terminal-form-label">
                                        <?php echo esc_html($t['email']); ?>
                                    </label>
                                    <input type="email" 
                                           name="new_visitors[0][email]" 
                                           class="saw-terminal-form-input" 
                                           placeholder="<?php echo esc_attr($t['email_placeholder']); ?>">
                                </div>
                            </div>
                            
                            <div class="saw-terminal-form-group">
                                <label class="saw-terminal-form-label">
                                    <?php echo esc_html($t['phone']); ?>
                                </label>
                                <input type="tel" 
                                       name="new_visitors[0][phone]" 
                                       class="saw-terminal-form-input" 
                                       placeholder="<?php echo esc_attr($t['phone_placeholder']); ?>">
                            </div>
                            
                            <!-- Training Skip Checkbox -->
                            <div class="saw-terminal-form-group">
                                <div class="saw-terminal-form-checkbox">
                                    <input type="checkbox" 
                                           name="new_visitors[0][training_skipped]" 
                                           value="1"
                                           id="new-training-skip-0">
                                    <label for="new-training-skip-0">
                                        ✅ <?php echo esc_html($t['training_skipped']); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Certificates -->
                            <div class="certificates-section">
                                <div class="certificates-header">
                                    <label class="saw-terminal-form-label">
                                        🎓 <?php echo esc_html($t['certificates']); ?>
                                    </label>
                                    <button type="button" class="add-certificate-btn" data-visitor="0">
                                        <?php echo esc_html($t['add_certificate']); ?>
                                    </button>
                                </div>
                                <div class="certificates-container" data-visitor="0"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" id="add-visitor-btn" class="saw-terminal-btn saw-terminal-btn-secondary">
                    <?php echo esc_html($t['add_visitor']); ?>
                </button>
            </div>
            
            <!-- ============================================
                 HOSTS SECTION (only for walk-in)
                 ============================================ -->
            <?php if (!$is_planned): ?>
            <div class="saw-terminal-form-section">
                <h3 class="saw-terminal-form-section-title">
                    🎯 <?php echo esc_html($t['section_visit']); ?>
                </h3>
                
                <div class="saw-terminal-form-group">
                    <label class="saw-terminal-form-label">
                        <?php echo esc_html($t['hosts']); ?> <span class="required">*</span>
                    </label>
                    <select name="host_ids[]" 
                            id="host-select"
                            class="saw-terminal-form-input saw-terminal-select2" 
                            multiple
                            required>
                        <?php if (empty($hosts)): ?>
                            <option value="" disabled>Žádní hosté k dispozici</option>
                        <?php else: ?>
                            <?php foreach ($hosts as $host): ?>
                            <option value="<?php echo esc_attr($host['id']); ?>">
                                <?php 
                                echo esc_html($host['first_name'] . ' ' . $host['last_name']);
                                if (!empty($host['position'])) {
                                    echo ' - ' . esc_html($host['position']);
                                }
                                ?>
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small class="saw-terminal-form-help">
                        <?php echo esc_html($t['hosts_help']); ?>
                    </small>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Submit Button -->
            <div class="saw-terminal-form-submit">
                <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                    <?php echo esc_html($t['submit']); ?> →
                </button>
            </div>
            
        </form>
    </div>
</div>

<!-- Select2 Library (only for walk-in) -->
<?php if (!$is_planned): ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php endif; ?>

<style>
/* Wide card */
.saw-terminal-card-wide {
    max-width: 1000px;
}

/* Form sections */
.saw-terminal-form-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    margin-bottom: 1.5rem;
}

.saw-terminal-form-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 1rem 0;
}

/* Existing visitor cards */
.saw-terminal-visitor-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-terminal-visitor-card:hover {
    border-color: #10b981;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}

/* New visitor blocks */
.visitor-block {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid #cbd5e1;
    margin-bottom: 1.5rem;
    position: relative;
}

.visitor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
}

.visitor-header h4 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #2d3748;
}

.remove-visitor-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.remove-visitor-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Form rows */
.saw-terminal-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

/* Certificates */
.certificates-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px dashed #cbd5e1;
}

.certificates-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.add-certificate-btn {
    background: #8b5cf6;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.add-certificate-btn:hover {
    background: #7c3aed;
}

.certificate-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.5fr auto;
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 0.75rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
}

.remove-cert-btn {
    background: #ef4444;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    font-size: 1.25rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.remove-cert-btn:hover {
    background: #dc2626;
}

/* Add visitor button */
#add-visitor-btn {
    width: 100%;
    margin-top: 1rem;
}

.required {
    color: #e53e3e;
}

.saw-terminal-form-help {
    display: block;
    margin-top: 0.5rem;
    color: #718096;
    font-size: 0.875rem;
}

/* Select2 styling */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--multiple {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    min-height: 3.5rem;
    padding: 0.5rem;
    font-size: 1rem;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-weight: 600;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .saw-terminal-form-row {
        grid-template-columns: 1fr;
    }
    
    .certificate-row {
        grid-template-columns: 1fr;
    }
}

/* Success section */
.saw-section-success {
    background: #f0fdf4;
    border-color: #86efac;
}

.saw-visitor-info-card {
    background: white;
    border: 2px solid #86efac;
    border-radius: 12px;
    padding: 1rem;
}

.visitor-header {
    cursor: pointer;
    user-select: none;
}

.visitor-toggle {
    font-size: 1.25rem;
    transition: transform 0.3s;
}

.visitor-content {
    padding-top: 1rem;
}

</style>

<script>
jQuery(document).ready(function($) {
    let visitorIndex = 1;

// Custom validation - vyžaduj new_visitors JEN pokud nejsou žádní existing
    $('#registration-form').on('submit', function(e) {
        const $form = $(this);
        const hasExisting = $('input[name="existing_visitor_ids[]"]:checked').length > 0;
        const hasNewFilled = $('input[name="new_visitors[0][first_name]"]').val().trim() !== '';
        
        if (!hasExisting && !hasNewFilled) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Musíte vybrat nebo zadat alespoň jednoho návštěvníka', 'saw-visitors')); ?>');
            return false;
        }
        
        return true;
    });
    
    <?php if (!$is_planned): ?>
    // Initialize Select2 for hosts (walk-in only)
    $('#host-select').select2({
        placeholder: '<?php echo esc_js($t['hosts_placeholder']); ?>',
        allowClear: false,
        width: '100%'
    });
    
    // Toggle company field
    $('#is-individual').on('change', function() {
        const isIndividual = $(this).is(':checked');
        const $companyInput = $('#company-input');
        
        if (isIndividual) {
            $companyInput.val('').prop('required', false).prop('disabled', true);
            $('#company-section').slideUp(200);
        } else {
            $companyInput.prop('required', true).prop('disabled', false);
            $('#company-section').slideDown(200);
        }
    });
    <?php endif; ?>
    
    // Add visitor
    $('#add-visitor-btn').on('click', function() {
        const $container = $('#new-visitors-container');
        const newIndex = visitorIndex++;

	// ✅ Sbal všechny otevřené visitor bloky
        $('.visitor-block').each(function() {
            const $block = $(this);
            const $content = $block.find('.visitor-content');
            const $toggle = $block.find('.visitor-toggle');
            
            if ($content.is(':visible')) {
                $content.slideUp(300);
                $toggle.text('▶');
            }
        });
        
        const visitorHTML = `
            <div class="visitor-block" data-index="${newIndex}">
                <div class="visitor-header" onclick="toggleVisitorBlock(${newIndex})">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span class="visitor-toggle">▼</span>
                        <h4><?php echo esc_js($t['visitor_number']); ?> ${newIndex + 1}</h4>
                    </div>
                    <button type="button" class="remove-visitor-btn" onclick="event.stopPropagation(); removeVisitor(${newIndex})">
                        <?php echo esc_js($t['remove_visitor']); ?>
                    </button>
                </div>
                
                <div class="visitor-content">
                    <div class="saw-terminal-form-row">
                        <div class="saw-terminal-form-group">
                            <label class="saw-terminal-form-label">
                                <?php echo esc_js($t['first_name']); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="new_visitors[${newIndex}][first_name]" class="saw-terminal-form-input" required>
                        </div>
                        <div class="saw-terminal-form-group">
                            <label class="saw-terminal-form-label">
                                <?php echo esc_js($t['last_name']); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="new_visitors[${newIndex}][last_name]" class="saw-terminal-form-input" required>
                        </div>
                    </div>
                    
                    <div class="saw-terminal-form-row">
                        <div class="saw-terminal-form-group">
                            <label class="saw-terminal-form-label"><?php echo esc_js($t['position']); ?></label>
                            <input type="text" name="new_visitors[${newIndex}][position]" class="saw-terminal-form-input">
                        </div>
                        <div class="saw-terminal-form-group">
                            <label class="saw-terminal-form-label"><?php echo esc_js($t['email']); ?></label>
                            <input type="email" name="new_visitors[${newIndex}][email]" class="saw-terminal-form-input">
                        </div>
                    </div>
                    
                    <div class="saw-terminal-form-group">
                        <label class="saw-terminal-form-label"><?php echo esc_js($t['phone']); ?></label>
                        <input type="tel" name="new_visitors[${newIndex}][phone]" class="saw-terminal-form-input">
                    </div>
                    
                    <div class="saw-terminal-form-group">
                        <div class="saw-terminal-form-checkbox">
                            <input type="checkbox" name="new_visitors[${newIndex}][training_skipped]" value="1" id="new-training-skip-${newIndex}">
                            <label for="new-training-skip-${newIndex}">✅ <?php echo esc_js($t['training_skipped']); ?></label>
                        </div>
                    </div>
                    
                    <div class="certificates-section">
                        <div class="certificates-header">
                            <label class="saw-terminal-form-label">🎓 <?php echo esc_js($t['certificates']); ?></label>
                            <button type="button" class="add-certificate-btn" data-visitor="${newIndex}">
                                <?php echo esc_js($t['add_certificate']); ?>
                            </button>
                        </div>
                        <div class="certificates-container" data-visitor="${newIndex}"></div>
                    </div>
                </div>
            </div>
        `;
        
        $container.append(visitorHTML);
        
        // Show remove button for first visitor
        $('.visitor-block[data-index="0"] .remove-visitor-btn').show();
    });
    
    // Add certificate (delegated event)
    $(document).on('click', '.add-certificate-btn', function() {
        const visitorIdx = $(this).data('visitor');
        const $container = $(`.certificates-container[data-visitor="${visitorIdx}"]`);
        const certIdx = $container.children().length;
        
        const certHTML = `
            <div class="certificate-row">
                <div class="saw-terminal-form-group" style="margin: 0;">
                    <input type="text" 
                           name="new_visitors[${visitorIdx}][certificates][${certIdx}][name]" 
                           class="saw-terminal-form-input" 
                           placeholder="<?php echo esc_js($t['cert_name_placeholder']); ?>">
                </div>
                <div class="saw-terminal-form-group" style="margin: 0;">
                    <input type="text" 
                           name="new_visitors[${visitorIdx}][certificates][${certIdx}][number]" 
                           class="saw-terminal-form-input" 
                           placeholder="<?php echo esc_js($t['cert_number']); ?>">
                </div>
                <div class="saw-terminal-form-group" style="margin: 0;">
                    <input type="date" 
                           name="new_visitors[${visitorIdx}][certificates][${certIdx}][valid_until]" 
                           class="saw-terminal-form-input">
                </div>
                <button type="button" class="remove-cert-btn" onclick="jQuery(this).closest('.certificate-row').remove()">×</button>
            </div>
        `;
        
        $container.append(certHTML);
    });
});

// Remove visitor (global function)
function removeVisitor(index) {
    jQuery(`.visitor-block[data-index="${index}"]`).remove();
    
    // Hide remove button if only one visitor left
    if (jQuery('.visitor-block').length === 1) {
        jQuery('.visitor-block .remove-visitor-btn').hide();
    }
    
    // Renumber visitors
    jQuery('.visitor-block').each(function(i) {
        jQuery(this).find('.visitor-header h4').text(`<?php echo esc_js($t['visitor_number']); ?> ${i + 1}`);
    });
}

// Toggle visitor block (global function)
function toggleVisitorBlock(index) {
    const $block = jQuery(`.visitor-block[data-index="${index}"]`);
    const $content = $block.find('.visitor-content');
    const $toggle = $block.find('.visitor-toggle');
    
    if ($content.is(':visible')) {
        $content.slideUp(300);
        $toggle.text('▶');
    } else {
        $content.slideDown(300);
        $toggle.text('▼');
    }
}
</script>