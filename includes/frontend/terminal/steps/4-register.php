<?php
/**
 * Terminal Step 4 - Walk-in Registration Form
 * 
 * Registration form for one-time visitors without pre-registration
 * 
 * @package SAW_Visitors
 * @version 1.0.0
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
        'host' => 'Koho navštěvujete?',
        'host_placeholder' => 'Vyberte osobu...',
        'training_skipped' => 'Absolvoval/a jsem školení BOZP do 1 roku',
        'submit' => 'Pokračovat',
        'required' => 'Povinné pole',
    ],
    'en' => [
        'title' => 'Visitor Registration',
        'subtitle' => 'Please fill in your details',
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
        'host' => 'Who are you visiting?',
        'host_placeholder' => 'Select person...',
        'training_skipped' => 'I completed safety training within the last year',
        'submit' => 'Continue',
        'required' => 'Required field',
    ],
    'uk' => [
        'title' => 'Реєстрація відвідувача',
        'subtitle' => 'Будь ласка, заповніть свої дані',
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
        'host' => 'Кого ви відвідуєте?',
        'host_placeholder' => 'Виберіть особу...',
        'training_skipped' => 'Я пройшов навчання з охорони праці протягом року',
        'submit' => 'Продовжити',
        'required' => 'Обов\'язкове поле',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// TODO: Get hosts from database based on current branch
// For now, mock data
$hosts = [
    1 => 'Jan Novák - Výroba',
    2 => 'Marie Svobodová - Administrativa',
    3 => 'Petr Dvořák - IT',
];
?>

<div class="saw-terminal-card">
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
            <input type="hidden" name="terminal_action" value="submit_registration">
            
            <!-- Company Information -->
            <div class="saw-terminal-form-group">
                <label class="saw-terminal-form-label">
                    <?php echo esc_html($t['company']); ?> <span style="color: #e53e3e;">*</span>
                </label>
                <input type="text" 
                       name="company_name" 
                       class="saw-terminal-form-input" 
                       placeholder="<?php echo esc_attr($t['company_placeholder']); ?>"
                       id="company-input"
                       required>
            </div>
            
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
            
            <hr style="margin: 2rem 0; border: 0; border-top: 2px solid #e2e8f0;">
            
            <!-- Personal Information -->
            <div class="saw-terminal-form-group">
                <label class="saw-terminal-form-label">
                    <?php echo esc_html($t['first_name']); ?> <span style="color: #e53e3e;">*</span>
                </label>
                <input type="text" 
                       name="first_name" 
                       class="saw-terminal-form-input" 
                       required>
            </div>
            
            <div class="saw-terminal-form-group">
                <label class="saw-terminal-form-label">
                    <?php echo esc_html($t['last_name']); ?> <span style="color: #e53e3e;">*</span>
                </label>
                <input type="text" 
                       name="last_name" 
                       class="saw-terminal-form-input" 
                       required>
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
            
            <hr style="margin: 2rem 0; border: 0; border-top: 2px solid #e2e8f0;">
            
            <!-- Host Selection -->
            <div class="saw-terminal-form-group">
                <label class="saw-terminal-form-label">
                    <?php echo esc_html($t['host']); ?> <span style="color: #e53e3e;">*</span>
                </label>
                <select name="host_id" 
                        class="saw-terminal-form-input" 
                        required>
                    <option value=""><?php echo esc_html($t['host_placeholder']); ?></option>
                    <?php foreach ($hosts as $id => $name): ?>
                    <option value="<?php echo esc_attr($id); ?>">
                        <?php echo esc_html($name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Training Skip Checkbox -->
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
            
            <!-- Submit Button -->
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                <?php echo esc_html($t['submit']); ?> →
            </button>
            
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle company field based on individual checkbox
    $('#is-individual').on('change', function() {
        const isIndividual = $(this).is(':checked');
        const $companyInput = $('#company-input');
        
        if (isIndividual) {
            $companyInput.val('').prop('required', false).prop('disabled', true);
            $companyInput.closest('.saw-terminal-form-group').fadeOut(200);
        } else {
            $companyInput.prop('required', true).prop('disabled', false);
            $companyInput.closest('.saw-terminal-form-group').fadeIn(200);
        }
    });
});
</script>
