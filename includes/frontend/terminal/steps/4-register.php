<?php
/**
 * Terminal Step 4 - Walk-in Registration Form
 * 
 * Registration form for one-time visitors without pre-registration
 * 
 * CHANGES v2.0:
 * - ✅ Two-column layout for better space usage
 * - ✅ Multi-select for hosts with search (Select2)
 * - ✅ Loads real hosts from database
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Registrace návštěvníka',
        'subtitle' => 'Vyplňte prosím své údaje',
        'section_company' => 'Informace o firmě',
        'section_personal' => 'Osobní údaje',
        'section_visit' => 'Informace o návštěvě',
        'company' => 'Název firmy',
        'company_placeholder' => 'např. ACME s.r.o.',
        'is_individual' => 'Jsem fyzická osoba (soukromá návštěva)',
        'first_name' => 'Jméno',
        'last_name' => 'Příjmení',
        'position' => 'Funkce / Pozice',
        'position_placeholder' => 'např. Obchodní ředitel',
        'email' => 'Email',
        'email_placeholder' => 'vas.email@example.com',
        'phone' => 'Telefon',
        'phone_placeholder' => '+420 123 456 789',
        'hosts' => 'Koho navštěvujete?',
        'hosts_placeholder' => 'Vyberte jednu nebo více osob...',
        'hosts_help' => 'Můžete vybrat více osob, které budete navštěvovat',
        'training_skipped' => 'Absolvoval/a jsem školení BOZP do 1 roku',
        'submit' => 'Pokračovat',
        'required' => 'Povinné pole',
    ],
    'en' => [
        'title' => 'Visitor Registration',
        'subtitle' => 'Please fill in your details',
        'section_company' => 'Company Information',
        'section_personal' => 'Personal Details',
        'section_visit' => 'Visit Information',
        'company' => 'Company Name',
        'company_placeholder' => 'e.g. ACME Ltd.',
        'is_individual' => 'I am an individual (private visit)',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'position' => 'Position / Role',
        'position_placeholder' => 'e.g. Sales Director',
        'email' => 'Email',
        'email_placeholder' => 'your.email@example.com',
        'phone' => 'Phone',
        'phone_placeholder' => '+420 123 456 789',
        'hosts' => 'Who are you visiting?',
        'hosts_placeholder' => 'Select one or more people...',
        'hosts_help' => 'You can select multiple people you will be visiting',
        'training_skipped' => 'I completed safety training within the last year',
        'submit' => 'Continue',
        'required' => 'Required field',
    ],
    'uk' => [
        'title' => 'Реєстрація відвідувача',
        'subtitle' => 'Будь ласка, заповніть свої дані',
        'section_company' => 'Інформація про компанію',
        'section_personal' => 'Особисті дані',
        'section_visit' => 'Інформація про візит',
        'company' => 'Назва компанії',
        'company_placeholder' => 'наприклад ACME Ltd.',
        'is_individual' => 'Я фізична особа (приватний візит)',
        'first_name' => 'Ім\'я',
        'last_name' => 'Прізвище',
        'position' => 'Посада / Роль',
        'position_placeholder' => 'наприклад Директор з продажу',
        'email' => 'Email',
        'email_placeholder' => 'vas.email@example.com',
        'phone' => 'Телефон',
        'phone_placeholder' => '+420 123 456 789',
        'hosts' => 'Кого ви відвідуєте?',
        'hosts_placeholder' => 'Виберіть одну або кілька осіб...',
        'hosts_help' => 'Ви можете вибрати кілька осіб, яких відвідаєте',
        'training_skipped' => 'Я пройшов навчання з охорони праці протягом року',
        'submit' => 'Продовжити',
        'required' => 'Обов\'язкове поле',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// $hosts is passed from controller - array of user objects
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
        <form method="POST" class="saw-terminal-form saw-terminal-form-columns" id="registration-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="submit_registration">
            
            <!-- ============================================
                 LEFT COLUMN - Company & Personal Info
                 ============================================ -->
            <div class="saw-terminal-form-column">
                
                <!-- Individual Checkbox - MIMO company section aby byl vždy viditelný -->
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
                
                <!-- Company Section - Skrývá se/zobrazuje -->
                <div class="saw-terminal-form-section" id="company-section">
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
                
                <!-- Personal Info Section -->
                <div class="saw-terminal-form-section">
                    <h3 class="saw-terminal-form-section-title">
                        👤 <?php echo esc_html($t['section_personal']); ?>
                    </h3>
                    
                    <div class="saw-terminal-form-row">
                        <div class="saw-terminal-form-group">
                            <label class="saw-terminal-form-label">
                                <?php echo esc_html($t['first_name']); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   name="first_name" 
                                   class="saw-terminal-form-input" 
                                   required>
                        </div>
                        
                        <div class="saw-terminal-form-group">
                            <label class="saw-terminal-form-label">
                                <?php echo esc_html($t['last_name']); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   name="last_name" 
                                   class="saw-terminal-form-input" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="saw-terminal-form-group">
                        <label class="saw-terminal-form-label">
                            <?php echo esc_html($t['position']); ?>
                        </label>
                        <input type="text" 
                               name="position" 
                               class="saw-terminal-form-input" 
                               placeholder="<?php echo esc_attr($t['position_placeholder']); ?>">
                    </div>
                </div>
                
            </div>
            
            <!-- ============================================
                 RIGHT COLUMN - Contact & Visit Info
                 ============================================ -->
            <div class="saw-terminal-form-column">
                
                <!-- Contact Info (continued) -->
                <div class="saw-terminal-form-section">
                    <h3 class="saw-terminal-form-section-title">
                        📧 Kontaktní údaje
                    </h3>
                    
                    <div class="saw-terminal-form-group">
                        <label class="saw-terminal-form-label">
                            <?php echo esc_html($t['email']); ?>
                        </label>
                        <input type="email" 
                               name="email" 
                               class="saw-terminal-form-input" 
                               placeholder="<?php echo esc_attr($t['email_placeholder']); ?>">
                    </div>
                    
                    <div class="saw-terminal-form-group">
                        <label class="saw-terminal-form-label">
                            <?php echo esc_html($t['phone']); ?>
                        </label>
                        <input type="tel" 
                               name="phone" 
                               class="saw-terminal-form-input" 
                               placeholder="<?php echo esc_attr($t['phone_placeholder']); ?>">
                    </div>
                </div>
                
                <!-- Visit Info Section -->
                <div class="saw-terminal-form-section">
                    <h3 class="saw-terminal-form-section-title">
                        🎯 <?php echo esc_html($t['section_visit']); ?>
                    </h3>
                    
                    <!-- Multi-select Hosts -->
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
                    
                    <!-- Training Skip -->
                    <div class="saw-terminal-form-group">
                        <div class="saw-terminal-form-checkbox">
                            <input type="checkbox" 
                                   name="training_skipped" 
                                   id="training-skipped" 
                                   value="1">
                            <label for="training-skipped">
                                ✅ <?php echo esc_html($t['training_skipped']); ?>
                            </label>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Submit Button (full width) -->
            <div class="saw-terminal-form-submit">
                <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                    <?php echo esc_html($t['submit']); ?> →
                </button>
            </div>
            
        </form>
    </div>
</div>

<!-- Select2 Library -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Two-column layout - COMPACT VERSION */
.saw-terminal-card-wide {
    max-width: 1200px;
}

.saw-terminal-form-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem; /* Menší mezera mezi sloupci */
}

.saw-terminal-form-column {
    display: flex;
    flex-direction: column;
    gap: 1rem; /* Menší mezera mezi sekcemi */
}

.saw-terminal-form-section {
    background: #f8f9fa;
    padding: 1rem; /* Menší padding */
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.saw-terminal-form-section-title {
    font-size: 1rem; /* Menší nadpis */
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 0.75rem 0; /* Menší mezera */
}

.saw-terminal-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem; /* Menší mezera */
}

/* Kompaktnější form groups */
.saw-terminal-form-columns .saw-terminal-form-group {
    margin-bottom: 0.75rem; /* Menší mezera mezi poli */
}

.saw-terminal-form-columns .saw-terminal-form-label {
    font-size: 0.9375rem; /* Menší font */
    margin-bottom: 0.5rem; /* Menší mezera */
}

.saw-terminal-form-columns .saw-terminal-form-input {
    padding: 0.875rem 1rem; /* Menší padding */
    font-size: 1rem; /* Menší font */
}

.saw-terminal-form-submit {
    grid-column: 1 / -1;
    margin-top: 0.75rem; /* Menší mezera */
}

.required {
    color: #e53e3e;
}

.saw-terminal-form-help {
    display: block;
    margin-top: 0.375rem; /* Menší mezera */
    color: #718096;
    font-size: 0.875rem; /* Menší font */
    line-height: 1.4;
}

/* Kompaktnější checkbox */
.saw-terminal-form-columns .saw-terminal-form-checkbox {
    padding: 0.875rem; /* Menší padding */
    margin-bottom: 0.5rem;
}

.saw-terminal-form-columns .saw-terminal-form-checkbox label {
    font-size: 0.9375rem; /* Menší font */
}

/* Select2 styling for terminal - FIXED */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--multiple {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    min-height: 3rem; /* Menší výška */
    padding: 0.375rem 0.5rem; /* Menší padding */
    font-size: 1rem;
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #667eea;
    background: white;
}

/* ✅ OPRAVA: Placeholder viditelný */
.select2-container--default .select2-selection--multiple .select2-selection__placeholder {
    color: #a0aec0;
    font-size: 1rem;
    line-height: 1.5;
    padding: 0.25rem 0;
}

/* ✅ OPRAVA: Štítky bez divných linek */
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    border-radius: 6px;
    padding: 0.375rem 0.625rem;
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0.25rem 0.25rem 0.25rem 0;
    line-height: 1.4;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

/* ✅ OPRAVA: Křížek bez linky */
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    font-size: 1.125rem;
    font-weight: 700;
    border: none !important;
    background: none !important;
    margin: 0;
    padding: 0;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #ffd700;
    background: none !important;
}

/* Search field */
.select2-search--inline .select2-search__field {
    font-size: 1rem;
    line-height: 1.5;
    margin: 0;
    padding: 0.25rem 0;
}

/* Dropdown */
.select2-dropdown {
    border: 2px solid #667eea;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
}

.select2-results__option {
    padding: 0.75rem 1rem; /* Kompaktnější */
    font-size: 1rem;
}

.select2-results__option--highlighted {
    background: #667eea !important;
}

.select2-search__field {
    padding: 0.625rem 0.75rem; /* Kompaktnější */
    font-size: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
}

.select2-search__field:focus {
    border-color: #667eea;
    outline: none;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .saw-terminal-form-columns {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .saw-terminal-form-row {
        grid-template-columns: 1fr;
    }
    
    .saw-terminal-form-section {
        padding: 0.875rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize Select2 for hosts
    $('#host-select').select2({
        placeholder: '<?php echo esc_js($t['hosts_placeholder']); ?>',
        allowClear: false,
        width: '100%',
        language: {
            noResults: function() {
                return 'Nenalezeno';
            },
            searching: function() {
                return 'Vyhledávání...';
            }
        }
    });
    
    // Toggle company field based on individual checkbox
    $('#is-individual').on('change', function() {
        const isIndividual = $(this).is(':checked');
        const $companySection = $('#company-section');
        const $companyInput = $('#company-input');
        
        if (isIndividual) {
            $companyInput.val('').prop('required', false).prop('disabled', true);
            $companySection.slideUp(200);
        } else {
            $companyInput.prop('required', true).prop('disabled', false);
            $companySection.slideDown(200);
        }
    });
    
    // Form validation
    $('#registration-form').on('submit', function(e) {
        const selectedHosts = $('#host-select').val();
        
        if (!selectedHosts || selectedHosts.length === 0) {
            e.preventDefault();
            alert('Prosím vyberte alespoň jednoho hostitele');
            $('#host-select').next('.select2-container').addClass('error-highlight');
            setTimeout(function() {
                $('#host-select').next('.select2-container').removeClass('error-highlight');
            }, 2000);
            return false;
        }
    });
});
</script>