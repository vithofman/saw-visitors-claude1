<?php
/**
 * Terminal Step - Universal Visitor Registration (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$is_planned = ($flow['type'] ?? '') === 'planned';
$existing_visitors = $flow['visitors'] ?? [];

// Get hosts list (should be passed from controller)
$hosts = $hosts ?? [];

$translations = [
    'cs' => [
        'title' => 'Registrace návštěvníků',
        'subtitle' => 'Označte kdo přišel a případně přidejte další osoby',
        'section_already' => 'Již zaregistrováni',
        'section_waiting' => 'Ještě nepřišli - označte kdo přišel',
        'section_existing' => 'Předregistrovaní návštěvníci',
        'section_new' => 'Přidat nové návštěvníky',
        'section_company' => 'Informace o firmě',
        'section_visit' => 'Informace o návštěvě',
        'company' => 'Název firmy',
        'company_placeholder' => 'např. ACME s.r.o.',
        'is_individual' => 'Jsem fyzická osoba (soukromá návštěva)',
        'visitor_number' => 'Návštěvník',
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
    'en' => [
        'title' => 'Visitor Registration',
        'subtitle' => 'Check who arrived and add more people if needed',
        'section_already' => 'Already Registered',
        'section_waiting' => 'Not yet arrived - check who came',
        'section_existing' => 'Pre-registered Visitors',
        'section_new' => 'Add New Visitors',
        'section_company' => 'Company Information',
        'section_visit' => 'Visit Information',
        'company' => 'Company Name',
        'company_placeholder' => 'e.g. ACME Ltd.',
        'is_individual' => 'I am an individual (private visit)',
        'visitor_number' => 'Visitor',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'position' => 'Position',
        'position_placeholder' => 'e.g. Sales Director',
        'email' => 'Email',
        'email_placeholder' => 'your.email@example.com',
        'phone' => 'Phone',
        'phone_placeholder' => '+1 234 567 890',
        'certificates' => 'Professional Certificates',
        'add_certificate' => '+ Add Certificate',
        'cert_name' => 'Certificate Name',
        'cert_name_placeholder' => 'e.g. Forklift Operator',
        'cert_number' => 'Certificate Number',
        'cert_valid_until' => 'Valid Until',
        'hosts' => 'Who are you visiting?',
        'hosts_placeholder' => 'Select one or more people...',
        'hosts_help' => 'You can select multiple people',
        'training_skipped' => 'Completed training within 1 year',
        'add_visitor' => '+ Add Another Visitor',
        'remove_visitor' => 'Remove',
        'submit' => 'Continue',
        'required' => 'Required field',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];

// ✅ Split podle is_currently_inside (kdo je TEĎKA uvnitř)
$already_checked_in = [];
$waiting_for_checkin = [];

if ($is_planned && !empty($existing_visitors)) {
    foreach ($existing_visitors as $visitor) {
        if ($visitor['is_currently_inside']) {
            // Je TEĎKA AKTIVNĚ uvnitř → zelená
            $already_checked_in[] = $visitor;
        } else {
            // Není uvnitř (odešel/nepřišel) → checkbox pro re-entry
            $waiting_for_checkin[] = $visitor;
        }
    }
}
?>
<style>
/* === UNIFIED STYLE === */
:root {
    --theme-color: #667eea;
    --theme-color-hover: #764ba2;
    --bg-dark: #1a202c;
    --bg-dark-medium: #2d3748;
    --bg-glass: rgba(15, 23, 42, 0.6);
    --bg-glass-light: rgba(255, 255, 255, 0.08);
    --border-glass: rgba(148, 163, 184, 0.12);
    --text-primary: #FFFFFF;
    --text-secondary: #e5e7eb;
    --text-muted: #9ca3af;
    --color-success: #10b981;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer {
    display: none !important;
}

.saw-register-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    overflow: hidden;
}

.saw-register-content-wrapper {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 3rem 2rem 10rem;
}

.saw-register-content-wrapper::-webkit-scrollbar {
    width: 8px;
}

.saw-register-content-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.saw-register-content-wrapper::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 999px;
}

.saw-register-layout {
    max-width: 1600px;
    margin: 0 auto;
}

/* Header */
.saw-register-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
    padding: 2rem 2.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.saw-register-icon {
    width: 4rem;
    height: 4rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.25rem;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-radius: 18px;
    box-shadow: 
        0 10px 30px rgba(245, 158, 11, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    position: relative;
}

.saw-register-icon::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.4), transparent);
    z-index: -1;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

.saw-register-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
}

.saw-register-subtitle {
    font-size: 0.9375rem;
    color: rgba(203, 213, 225, 0.7);
    font-weight: 500;
    line-height: 1.5;
}

/* TWO COLUMN LAYOUT */
.saw-register-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 2rem;
    align-items: start;
}

/* Glass card sections */
.saw-register-glass-card {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    padding: 2rem;
    margin-bottom: 2rem;
}

.saw-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Existing visitors - SUCCESS style */
.saw-register-success-card {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
}

.saw-visitor-info-item {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(16, 185, 129, 0.3);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.saw-visitor-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: #10b981;
}

.saw-visitor-position {
    font-size: 0.8125rem;
    color: rgba(203, 213, 225, 0.9);
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.3);
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
    font-weight: 600;
}

/* Waiting visitors - checkbox style */
.saw-visitor-checkbox-card {
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.08);
    transition: all 0.3s ease;
    opacity: 0.6;
    cursor: pointer;  /* ✅ PŘIDEJ */
}

/* ✅ KDYŽ JE ZAŠKRTNUTÝ - ZVÝRAZNI */
.saw-visitor-checkbox-card.checked {
    background: rgba(102, 126, 234, 0.15) !important;
    border-color: rgba(102, 126, 234, 0.5) !important;
    opacity: 1 !important;
    transform: translateX(4px);
}

.saw-visitor-checkbox-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(102, 126, 234, 0.3);
    opacity: 0.8;
}

.saw-visitor-checkbox-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    pointer-events: none;  
}

.saw-visitor-checkbox-header input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
    accent-color: var(--color-success);
    flex-shrink: 0;
    pointer-events: auto;  
}
.saw-visitor-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    pointer-events: none;  /* ✅ PŘIDEJ */
}

.saw-visitor-checkbox-card .saw-visitor-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.6);
    transition: color 0.3s;
    pointer-events: none;  /* ✅ PŘIDEJ */
}

/* ✅ KDYŽ CHECKED - BÍLÉ JMÉNO */
.saw-visitor-checkbox-card.checked .saw-visitor-name {
    color: var(--text-primary);
}

.saw-visitor-checkbox-card .saw-visitor-position {
    font-size: 0.8125rem;
    color: rgba(203, 213, 225, 0.6);
    background: rgba(102, 126, 234, 0.1);
    border: 1px solid rgba(102, 126, 234, 0.2);
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s;
    pointer-events: none;  /* ✅ PŘIDEJ - důležité! */
}
/* ✅ KDYŽ CHECKED - BAREVNÁ POZICE */
.saw-visitor-checkbox-card.checked .saw-visitor-position {
    color: rgba(203, 213, 225, 0.9);
    background: rgba(102, 126, 234, 0.3);
    border-color: rgba(102, 126, 234, 0.5);
}

.saw-visitor-training-skip {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(148, 163, 184, 0.2);
}

/* ✅ TLAČÍTKO VYBRAT VŠE */
.saw-btn-toggle-all {
    padding: 0.75rem 1.5rem;
    background: rgba(102, 126, 234, 0.2);
    border: 1px solid rgba(102, 126, 234, 0.4);
    border-radius: 10px;
    color: #a5b4fc;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-btn-toggle-all:hover {
    background: rgba(102, 126, 234, 0.3);
    border-color: rgba(102, 126, 234, 0.6);
    transform: translateY(-1px);
}
.saw-visitor-training-skip {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(148, 163, 184, 0.2);
}

.saw-training-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: rgba(203, 213, 225, 0.9);
    cursor: pointer;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 8px;
    transition: all 0.2s;
}

.saw-training-checkbox-label:hover {
    background: rgba(255, 255, 255, 0.08);
}

.saw-training-checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--color-success);
    flex-shrink: 0;
}

/* Form fields */
.saw-form-group {
    margin-bottom: 1.25rem;
}

.saw-form-label {
    display: block;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.saw-form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-glass);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.2s;
}

.saw-form-input:focus {
    outline: none;
    border-color: rgba(102, 126, 234, 0.5);
    background: rgba(255, 255, 255, 0.12);
}

.saw-form-input::placeholder {
    color: rgba(203, 213, 225, 0.5);
}

.saw-form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

/* Visitor blocks - collapsible */
.saw-visitor-block {
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    border: 1px solid var(--border-glass);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.saw-visitor-block-header {
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.04);
    border-bottom: 1px solid var(--border-glass);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.saw-visitor-block-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.saw-visitor-toggle {
    font-size: 1.125rem;
    transition: transform 0.3s;
}

.saw-visitor-block-header h4 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.saw-remove-visitor-btn {
    padding: 0.5rem 1rem;
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.4);
    border-radius: 8px;
    color: #fca5a5;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-remove-visitor-btn:hover {
    background: rgba(239, 68, 68, 0.3);
    border-color: rgba(239, 68, 68, 0.6);
}

.saw-visitor-block-content {
    padding: 1.5rem;
}

/* Certificates */
.saw-certificates-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px dashed rgba(148, 163, 184, 0.3);
}

.saw-certificates-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.saw-add-cert-btn {
    padding: 0.5rem 1rem;
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 8px;
    color: #c4b5fd;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-add-cert-btn:hover {
    background: rgba(139, 92, 246, 0.3);
    border-color: rgba(139, 92, 246, 0.6);
}

.saw-cert-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.5fr auto;
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 0.75rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 8px;
}

.saw-remove-cert-btn {
    width: 40px;
    height: 40px;
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.4);
    border-radius: 8px;
    color: #fca5a5;
    font-size: 1.25rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-remove-cert-btn:hover {
    background: rgba(239, 68, 68, 0.3);
}

/* Checkbox style */
.saw-form-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    border: 1px solid var(--border-glass);
    cursor: pointer;
    transition: all 0.2s;
}

.saw-form-checkbox:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(102, 126, 234, 0.5);
}

.saw-form-checkbox input[type="checkbox"] {
    width: 22px;
    height: 22px;
    cursor: pointer;
    accent-color: var(--color-success);
}

.saw-form-checkbox label {
    flex: 1;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-primary);
    cursor: pointer;
}

/* Buttons */
.saw-btn-add-visitor {
    width: 100%;
    padding: 1rem;
    background: rgba(102, 126, 234, 0.2);
    border: 1px solid rgba(102, 126, 234, 0.4);
    border-radius: 12px;
    color: #818cf8;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 1rem;
}

.saw-btn-add-visitor:hover {
    background: rgba(102, 126, 234, 0.3);
    border-color: rgba(102, 126, 234, 0.6);
    transform: translateY(-2px);
}

.saw-submit-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1.125rem;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    z-index: 200;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.saw-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
}

.required {
    color: #fca5a5;
}

.saw-form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: rgba(203, 213, 225, 0.7);
}

/* Select2 override */
.select2-container--default .select2-selection--multiple {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid var(--border-glass) !important;
    border-radius: 12px !important;
    min-height: 48px !important;
    padding: 0.5rem !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    color: white !important;
    border-radius: 6px !important;
    padding: 0.5rem 0.75rem !important;
    font-weight: 600 !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white !important;
}

/* Custom Hosts Multi-Select */
.saw-hosts-dropdown {
    position: relative;
}

.saw-hosts-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
    min-height: 42px;
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 12px;
    border: 1px solid var(--border-glass);
}

.saw-hosts-chips:empty {
    display: none;
}

.saw-host-chip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    animation: chipIn 0.2s ease;
}

@keyframes chipIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-host-chip-name {
    line-height: 1;
}

.saw-host-chip-remove {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    line-height: 1;
}

.saw-host-chip-remove:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.saw-hosts-list {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    max-height: 300px;
    overflow-y: auto;
    background: rgba(30, 41, 59, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-glass);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    z-index: 100;
}

.saw-hosts-list::-webkit-scrollbar {
    width: 8px;
}

.saw-hosts-list::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

.saw-hosts-list::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 999px;
}

.saw-host-item {
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
}

.saw-host-item:last-child {
    border-bottom: none;
}

.saw-host-item:hover {
    background: rgba(102, 126, 234, 0.2);
}

.saw-host-item.selected {
    background: rgba(102, 126, 234, 0.3);
    pointer-events: none;
    opacity: 0.5;
}

.saw-host-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.saw-host-position {
    font-size: 0.8125rem;
    color: rgba(203, 213, 225, 0.7);
}

.saw-hosts-empty {
    padding: 1rem;
    text-align: center;
    color: rgba(203, 213, 225, 0.6);
    font-size: 0.875rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .saw-register-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 768px) {
    .saw-register-content-wrapper {
        padding: 2rem 1rem 12rem;
    }
    
    .saw-register-header {
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .saw-register-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.75rem;
    }
    
    .saw-register-title {
        font-size: 1.5rem;
    }
    
    .saw-form-row {
        grid-template-columns: 1fr;
    }
    
    .saw-cert-row {
        grid-template-columns: 1fr;
    }
    
    .saw-submit-btn {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        width: auto;
    }
}
</style>

<div class="saw-register-aurora">
    <div class="saw-register-content-wrapper">
        <div class="saw-register-layout">
            
            <!-- Header -->
            <header class="saw-register-header">
                <div class="saw-register-icon">📝</div>
                <div class="saw-register-header-text">
                    <h1 class="saw-register-title"><?php echo esc_html($t['title']); ?></h1>
                    <p class="saw-register-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                </div>
            </header>
            
            <form method="POST" id="registration-form">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="submit_unified_registration">
                
                <!-- TWO COLUMN LAYOUT -->
                <div class="saw-register-grid">
                    
                    <!-- LEFT COLUMN -->
                    <div class="saw-register-column-left">
                        
                        <?php if ($is_planned && !empty($existing_visitors)): ?>
                            
                            <!-- Already checked in -->
                            <?php if (!empty($already_checked_in)): ?>
                            <div class="saw-register-glass-card saw-register-success-card">
                                <h3 class="saw-section-title">
                                    <span>✅</span>
                                    <span><?php echo esc_html($t['section_already']); ?> (<?php echo count($already_checked_in); ?>)</span>
                                </h3>
                                
                                <?php foreach ($already_checked_in as $visitor): ?>
                                <div class="saw-visitor-info-item">
                                    <div class="saw-visitor-name">
                                        ✓ <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                                    </div>
                                    <?php if (!empty($visitor['position'])): ?>
                                    <div class="saw-visitor-position"><?php echo esc_html($visitor['position']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Waiting for checkin -->
                            <?php if (!empty($waiting_for_checkin)): ?>
                            <div class="saw-register-glass-card">
                                <h3 class="saw-section-title">
                                    <span>👥</span>
                                    <span><?php echo esc_html($t['section_waiting']); ?> (<?php echo count($waiting_for_checkin); ?>)</span>
                                </h3>

<div style="margin-bottom: 1.5rem;">
    <button type="button" 
            id="toggle-all-visitors" 
            class="saw-btn-toggle-all"
            onclick="toggleAllVisitors()">
        ✓ Vybrat vše
    </button>
</div>
                                
                                <?php foreach ($waiting_for_checkin as $visitor): ?>
                                <div class="saw-visitor-checkbox-card">
                                    <div class="saw-visitor-checkbox-header">
                                        <input type="checkbox" 
                                               name="existing_visitor_ids[]" 
                                               value="<?php echo $visitor['id']; ?>">
                                        <div class="saw-visitor-info">
                                            <div class="saw-visitor-name">
                                                <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                                            </div>
                                            <?php if (!empty($visitor['position'])): ?>
                                            <div class="saw-visitor-position"><?php echo esc_html($visitor['position']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="saw-visitor-training-skip">
                                        <label class="saw-training-checkbox-label">
                                            <input type="checkbox" 
                                                   name="existing_training_skip[<?php echo $visitor['id']; ?>]" 
                                                   value="1">
                                            <span>✅ <?php echo esc_html($t['training_skipped']); ?></span>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                        
                        <?php if (!$is_planned): ?>
                        <!-- Company Section (walk-in only) -->
                        <div class="saw-register-glass-card">
                            <h3 class="saw-section-title">
                                <span>🏢</span>
                                <span><?php echo esc_html($t['section_company']); ?></span>
                            </h3>
                            
                            <div class="saw-form-checkbox">
                                <input type="checkbox" name="is_individual" id="is-individual" value="1">
                                <label for="is-individual"><?php echo esc_html($t['is_individual']); ?></label>
                            </div>
                            
                            <div id="company-section" style="margin-top: 1.25rem;">
                                <div class="saw-form-group">
                                    <label class="saw-form-label">
                                        <?php echo esc_html($t['company']); ?> <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                           name="company_name" 
                                           id="company-input"
                                           class="saw-form-input" 
                                           placeholder="<?php echo esc_attr($t['company_placeholder']); ?>"
                                           required>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                    
                    <!-- RIGHT COLUMN -->
                    <div class="saw-register-column-right">
                        
                        <!-- New Visitors -->
                        <div class="saw-register-glass-card">
                            <h3 class="saw-section-title">
                                <span>➕</span>
                                <span><?php echo esc_html($t['section_new']); ?></span>
                            </h3>
                            
                            <div id="new-visitors-container">
                                <!-- First visitor template -->
                                <div class="saw-visitor-block" data-index="0">
                                    <div class="saw-visitor-block-header" onclick="toggleVisitorBlock(0)">
                                        <div class="saw-visitor-block-header-left">
                                            <span class="saw-visitor-toggle">▼</span>
                                            <h4><?php echo esc_html($t['visitor_number']); ?> 1</h4>
                                        </div>
                                        <button type="button" 
                                                class="saw-remove-visitor-btn" 
                                                style="display: none;" 
                                                onclick="event.stopPropagation(); removeVisitor(0)">
                                            <?php echo esc_html($t['remove_visitor']); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="saw-visitor-block-content">
                                        <div class="saw-form-row">
                                            <div class="saw-form-group">
                                                <label class="saw-form-label">
                                                    <?php echo esc_html($t['first_name']); ?> <span class="required">*</span>
                                                </label>
                                                <input type="text" 
                                                       name="new_visitors[0][first_name]" 
                                                       class="saw-form-input">
                                            </div>
                                            
                                            <div class="saw-form-group">
                                                <label class="saw-form-label">
                                                    <?php echo esc_html($t['last_name']); ?> <span class="required">*</span>
                                                </label>
                                                <input type="text" 
                                                       name="new_visitors[0][last_name]" 
                                                       class="saw-form-input">
                                            </div>
                                        </div>
                                        
                                        <div class="saw-form-row">
                                            <div class="saw-form-group">
                                                <label class="saw-form-label"><?php echo esc_html($t['position']); ?></label>
                                                <input type="text" 
                                                       name="new_visitors[0][position]" 
                                                       class="saw-form-input" 
                                                       placeholder="<?php echo esc_attr($t['position_placeholder']); ?>">
                                            </div>
                                            
                                            <div class="saw-form-group">
                                                <label class="saw-form-label"><?php echo esc_html($t['email']); ?></label>
                                                <input type="email" 
                                                       name="new_visitors[0][email]" 
                                                       class="saw-form-input" 
                                                       placeholder="<?php echo esc_attr($t['email_placeholder']); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="saw-form-group">
                                            <label class="saw-form-label"><?php echo esc_html($t['phone']); ?></label>
                                            <input type="tel" 
                                                   name="new_visitors[0][phone]" 
                                                   class="saw-form-input" 
                                                   placeholder="<?php echo esc_attr($t['phone_placeholder']); ?>">
                                        </div>
                                        
                                        <div class="saw-form-checkbox">
                                            <input type="checkbox" 
                                                   name="new_visitors[0][training_skipped]" 
                                                   value="1"
                                                   id="new-training-skip-0">
                                            <label for="new-training-skip-0">
                                                ✅ <?php echo esc_html($t['training_skipped']); ?>
                                            </label>
                                        </div>
                                        
                                        <!-- Certificates -->
                                        <div class="saw-certificates-section">
                                            <div class="saw-certificates-header">
                                                <label class="saw-form-label">
                                                    🎓 <?php echo esc_html($t['certificates']); ?>
                                                </label>
                                                <button type="button" 
                                                        class="saw-add-cert-btn" 
                                                        data-visitor="0">
                                                    <?php echo esc_html($t['add_certificate']); ?>
                                                </button>
                                            </div>
                                            <div class="certificates-container" data-visitor="0"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" 
                                    id="add-visitor-btn" 
                                    class="saw-btn-add-visitor">
                                <?php echo esc_html($t['add_visitor']); ?>
                            </button>
                        </div>
                        
                        <?php if (!$is_planned): ?>
                        <!-- Hosts Section (walk-in only) -->
                        <div class="saw-register-glass-card">
                            <h3 class="saw-section-title">
                                <span>🎯</span>
                                <span><?php echo esc_html($t['section_visit']); ?></span>
                            </h3>
                            
                            <div class="saw-form-group">
                                <label class="saw-form-label">
                                    <?php echo esc_html($t['hosts']); ?> <span class="required">*</span>
                                </label>
                                
                                <!-- Selected hosts chips -->
                                <div id="selected-hosts-chips" class="saw-hosts-chips"></div>
                                
                                <!-- Searchable dropdown -->
                                <div class="saw-hosts-dropdown">
                                    <input type="text" 
                                           id="hosts-search" 
                                           class="saw-form-input" 
                                           placeholder="<?php echo esc_attr($t['hosts_placeholder']); ?>"
                                           autocomplete="off">
                                    
                                    <div id="hosts-list" class="saw-hosts-list" style="display: none;">
                                        <?php if (empty($hosts)): ?>
                                            <div class="saw-hosts-empty">Žádní hosté k dispozici</div>
                                        <?php else: ?>
                                            <?php foreach ($hosts as $host): ?>
                                            <div class="saw-host-item" 
                                                 data-id="<?php echo esc_attr($host['id']); ?>"
                                                 data-name="<?php echo esc_attr($host['first_name'] . ' ' . $host['last_name']); ?>"
                                                 data-position="<?php echo esc_attr($host['position'] ?? ''); ?>">
                                                <div class="saw-host-name">
                                                    <?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?>
                                                </div>
                                                <?php if (!empty($host['position'])): ?>
                                                <div class="saw-host-position">
                                                    <?php echo esc_html($host['position']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <small class="saw-form-help">
                                    <?php echo esc_html($t['hosts_help']); ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <!-- Submit Button (fixed) -->
                <button type="submit" class="saw-submit-btn">
                    <?php echo esc_html($t['submit']); ?> →
                </button>
                
            </form>
            
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let visitorIndex = 1;
    let selectedHosts = new Set();
    
    // Custom hosts multi-select
    <?php if (!$is_planned): ?>
    const $hostsSearch = $('#hosts-search');
    const $hostsList = $('#hosts-list');
    const $hostsChips = $('#selected-hosts-chips');
    const $selectedHostsInput = $('#selected-hosts');
    
    // Show/hide dropdown
    $hostsSearch.on('focus', function() {
        $hostsList.slideDown(200);
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.saw-hosts-dropdown').length) {
            $hostsList.slideUp(200);
        }
    });
    
    // Search filter
    $hostsSearch.on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.saw-host-item').each(function() {
            const name = $(this).data('name').toLowerCase();
            const position = $(this).data('position').toLowerCase();
            const matches = name.includes(search) || position.includes(search);
            $(this).toggle(matches);
        });
    });
    
    // Select host
    $(document).on('click', '.saw-host-item', function() {
        if ($(this).hasClass('selected')) return;
        
        const id = $(this).data('id');
        const name = $(this).data('name');
        const position = $(this).data('position');
        
        selectedHosts.add(id);
        $(this).addClass('selected');
        
        // Add chip
        let chipHTML = `
            <div class="saw-host-chip" data-id="${id}">
                <span class="saw-host-chip-name">${name}`;
        
        if (position) {
            chipHTML += ` <span style="opacity: 0.8; font-weight: 400;">- ${position}</span>`;
        }
        
        chipHTML += `</span>
                <button type="button" class="saw-host-chip-remove" data-id="${id}">×</button>
            </div>
        `;
        
        $hostsChips.append(chipHTML);
        
        updateSelectedHostsInput();
        $hostsSearch.val('').focus();
    });
    
    // Remove host chip
    $(document).on('click', '.saw-host-chip-remove', function() {
        const id = $(this).data('id');
        selectedHosts.delete(id);
        $(this).closest('.saw-host-chip').remove();
        $(`.saw-host-item[data-id="${id}"]`).removeClass('selected');
        updateSelectedHostsInput();
    });
    
    function updateSelectedHostsInput() {
        // Remove all existing hidden inputs
        $('input[name="host_ids[]"]').remove();
        
        // Add new hidden input for each selected host
        selectedHosts.forEach(id => {
            $('<input>').attr({
                type: 'hidden',
                name: 'host_ids[]',
                value: id
            }).appendTo('#registration-form');
        });
    }
    <?php endif; ?>
    
    // Make visitor header clickable (toggle checkbox)
    $(document).on('click', '.saw-visitor-checkbox-header', function(e) {
        // Don't toggle if clicking directly on checkbox
        if ($(e.target).is('input[type="checkbox"]')) {
            return;
        }
        
        const $checkbox = $(this).find('input[type="checkbox"]');
        $checkbox.prop('checked', !$checkbox.prop('checked'));
    });
    
    // Form validation
    $('#registration-form').on('submit', function(e) {
        const hasExisting = $('input[name="existing_visitor_ids[]"]:checked').length > 0;
        const hasNewFilled = $('input[name="new_visitors[0][first_name]"]').val().trim() !== '';
        
        if (!hasExisting && !hasNewFilled) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Musíte vybrat nebo zadat alespoň jednoho návštěvníka', 'saw-visitors')); ?>');
            return false;
        }
        
        <?php if (!$is_planned): ?>
        // Validate hosts selection
        if (selectedHosts.size === 0) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Musíte vybrat alespoň jednoho hostitele', 'saw-visitors')); ?>');
            $hostsSearch.focus();
            return false;
        }
        <?php endif; ?>
        
        return true;
    });
    
    <?php if (!$is_planned): ?>
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
        
        // Collapse all blocks
        $('.saw-visitor-block').each(function() {
            const $block = $(this);
            const $content = $block.find('.saw-visitor-block-content');
            const $toggle = $block.find('.saw-visitor-toggle');
            
            if ($content.is(':visible')) {
                $content.slideUp(300);
                $toggle.text('▶');
            }
        });
        
        const visitorHTML = `
            <div class="saw-visitor-block" data-index="${newIndex}">
                <div class="saw-visitor-block-header" onclick="toggleVisitorBlock(${newIndex})">
                    <div class="saw-visitor-block-header-left">
                        <span class="saw-visitor-toggle">▼</span>
                        <h4><?php echo esc_js($t['visitor_number']); ?> ${newIndex + 1}</h4>
                    </div>
                    <button type="button" 
                            class="saw-remove-visitor-btn" 
                            onclick="event.stopPropagation(); removeVisitor(${newIndex})">
                        <?php echo esc_js($t['remove_visitor']); ?>
                    </button>
                </div>
                
                <div class="saw-visitor-block-content">
                    <div class="saw-form-row">
                        <div class="saw-form-group">
                            <label class="saw-form-label"><?php echo esc_js($t['first_name']); ?> <span class="required">*</span></label>
                            <input type="text" name="new_visitors[${newIndex}][first_name]" class="saw-form-input" required>
                        </div>
                        <div class="saw-form-group">
                            <label class="saw-form-label"><?php echo esc_js($t['last_name']); ?> <span class="required">*</span></label>
                            <input type="text" name="new_visitors[${newIndex}][last_name]" class="saw-form-input" required>
                        </div>
                    </div>
                    
                    <div class="saw-form-row">
                        <div class="saw-form-group">
                            <label class="saw-form-label"><?php echo esc_js($t['position']); ?></label>
                            <input type="text" name="new_visitors[${newIndex}][position]" class="saw-form-input">
                        </div>
                        <div class="saw-form-group">
                            <label class="saw-form-label"><?php echo esc_js($t['email']); ?></label>
                            <input type="email" name="new_visitors[${newIndex}][email]" class="saw-form-input">
                        </div>
                    </div>
                    
                    <div class="saw-form-group">
                        <label class="saw-form-label"><?php echo esc_js($t['phone']); ?></label>
                        <input type="tel" name="new_visitors[${newIndex}][phone]" class="saw-form-input">
                    </div>
                    
                    <div class="saw-form-checkbox">
                        <input type="checkbox" name="new_visitors[${newIndex}][training_skipped]" value="1" id="new-training-skip-${newIndex}">
                        <label for="new-training-skip-${newIndex}">✅ <?php echo esc_js($t['training_skipped']); ?></label>
                    </div>
                    
                    <div class="saw-certificates-section">
                        <div class="saw-certificates-header">
                            <label class="saw-form-label">🎓 <?php echo esc_js($t['certificates']); ?></label>
                            <button type="button" class="saw-add-cert-btn" data-visitor="${newIndex}">
                                <?php echo esc_js($t['add_certificate']); ?>
                            </button>
                        </div>
                        <div class="certificates-container" data-visitor="${newIndex}"></div>
                    </div>
                </div>
            </div>
        `;
        
        $container.append(visitorHTML);
        $('.saw-visitor-block[data-index="0"] .saw-remove-visitor-btn').show();
    });
    
// ✅ TOGGLE CHECKBOXU - přidej/odebere třídu "checked"
$(document).on('change', 'input[name="existing_visitor_ids[]"]', function() {
    const $card = $(this).closest('.saw-visitor-checkbox-card');
    if ($(this).is(':checked')) {
        $card.addClass('checked');
    } else {
        $card.removeClass('checked');
    }
});

// ✅ KLIKNUTÍ NA CELOU KARTU - toggle (ne jen header)
$(document).on('click', '.saw-visitor-checkbox-card', function(e) {
    // Ignoruj kliknutí na checkbox samotný (už to handluje)
    if ($(e.target).is('input[type="checkbox"]')) {
        return;
    }
    
    // Ignoruj kliknutí na training skip checkbox (ten je uvnitř karty)
    if ($(e.target).closest('.saw-visitor-training-skip').length > 0) {
        return;
    }
    
    // Toggle hlavní checkbox
    const $checkbox = $(this).find('input[name="existing_visitor_ids[]"]');
    $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
});    

// Add certificate
    $(document).on('click', '.saw-add-cert-btn', function() {
        const visitorIdx = $(this).data('visitor');
        const $container = $(`.certificates-container[data-visitor="${visitorIdx}"]`);
        const certIdx = $container.children().length;
        
        const certHTML = `
            <div class="saw-cert-row">
                <div class="saw-form-group" style="margin: 0;">
                    <input type="text" 
                           name="new_visitors[${visitorIdx}][certificates][${certIdx}][name]" 
                           class="saw-form-input" 
                           placeholder="<?php echo esc_js($t['cert_name_placeholder']); ?>">
                </div>
                <div class="saw-form-group" style="margin: 0;">
                    <input type="text" 
                           name="new_visitors[${visitorIdx}][certificates][${certIdx}][number]" 
                           class="saw-form-input" 
                           placeholder="<?php echo esc_js($t['cert_number']); ?>">
                </div>
                <div class="saw-form-group" style="margin: 0;">
                    <input type="date" 
                           name="new_visitors[${visitorIdx}][certificates][${certIdx}][valid_until]" 
                           class="saw-form-input">
                </div>
                <button type="button" class="saw-remove-cert-btn" onclick="jQuery(this).closest('.saw-cert-row').remove()">×</button>
            </div>
        `;
        
        $container.append(certHTML);
    });
});

// Global functions
function removeVisitor(index) {
    jQuery(`.saw-visitor-block[data-index="${index}"]`).remove();
    
    if (jQuery('.saw-visitor-block').length === 1) {
        jQuery('.saw-visitor-block .saw-remove-visitor-btn').hide();
    }
    
    jQuery('.saw-visitor-block').each(function(i) {
        jQuery(this).find('.saw-visitor-block-header h4').text(`<?php echo esc_js($t['visitor_number']); ?> ${i + 1}`);
    });
}

// ✅ TOGGLE ALL VISITORS
function toggleAllVisitors() {
    const $checkboxes = jQuery('input[name="existing_visitor_ids[]"]');
    const $btn = jQuery('#toggle-all-visitors');
    
    // Zjisti kolik je zaškrtnutých
    const checkedCount = $checkboxes.filter(':checked').length;
    const totalCount = $checkboxes.length;
    
    if (checkedCount === totalCount) {
        // VŠECHNY ZAŠKRTNUTÉ → odškrtni vše
        $checkboxes.prop('checked', false).trigger('change');
        $btn.html('✓ Vybrat vše');
    } else {
        // NĚJAKÉ NEZAŠKRTNUTÉ → zaškrtni vše
        $checkboxes.prop('checked', true).trigger('change');
        $btn.html('✕ Zrušit vše');
    }
}

function toggleVisitorBlock(index) {
    const $block = jQuery(`.saw-visitor-block[data-index="${index}"]`);
    const $content = $block.find('.saw-visitor-block-content');
    const $toggle = $block.find('.saw-visitor-toggle');
    
    if ($content.is(':visible')) {
        $content.slideUp(300);
        $toggle.text('▶');
    } else {
        $content.slideDown(300);
        $toggle.text('▼');
    }
}
</script>

<?php
error_log("[REGISTER.PHP] Unified design loaded (v3.3.0)");
?>