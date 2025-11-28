<?php
/**
 * Terminal Step - Check-in Type Selection (Unified Design)
 * 
 * @package SAW_Visitors
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';

$translations = [
    'cs' => [
        'title' => 'Typ návštěvy',
        'subtitle' => 'Máte registrovanou návštěvu?',
        'planned' => 'Plánovaná návštěva',
        'planned_desc' => 'Mám PIN kód z emailu',
        'walkin' => 'Jednorázová návštěva',
        'walkin_desc' => 'Nemám PIN, chci se zaregistrovat',
    ],
    'en' => [
        'title' => 'Visit Type',
        'subtitle' => 'Do you have a registered visit?',
        'planned' => 'Planned Visit',
        'planned_desc' => 'I have a PIN code from email',
        'walkin' => 'Walk-in Visit',
        'walkin_desc' => 'I don\'t have a PIN, I want to register',
    ],
    'sk' => [
        'title' => 'Typ návštevy',
        'subtitle' => 'Máte registrovanú návštevu?',
        'planned' => 'Plánovaná návšteva',
        'planned_desc' => 'Mám PIN kód z emailu',
        'walkin' => 'Jednorazová návšteva',
        'walkin_desc' => 'Nemám PIN, chcem sa zaregistrovať',
    ],
    'uk' => [
        'title' => 'Тип візиту',
        'subtitle' => 'У вас є зареєстрований візит?',
        'planned' => 'Заплановий візит',
        'planned_desc' => 'У мене є PIN-код з електронної пошти',
        'walkin' => 'Разовий візит',
        'walkin_desc' => 'У мене немає PIN, я хочу зареєструватися',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-type">
    <div class="saw-page-content saw-page-content-centered">
        
        <!-- Header -->
        <div class="saw-page-header saw-page-header-centered">
            <div class="saw-header-icon">📋</div>
            <h1 class="saw-header-title"><?php echo esc_html($t['title']); ?></h1>
            <p class="saw-header-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Type Grid -->
        <form method="POST">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="set_checkin_type">
            
            <div class="saw-selection-grid saw-selection-grid-2">
                
                <!-- Planned Visit Button -->
                <button type="submit" 
                        name="checkin_type" 
                        value="planned" 
                        class="saw-selection-card is-planned">
                    <span class="saw-card-icon">📧</span>
                    <div class="saw-card-content">
                        <div class="saw-card-title"><?php echo esc_html($t['planned']); ?></div>
                        <div class="saw-card-description"><?php echo esc_html($t['planned_desc']); ?></div>
                    </div>
                </button>
                
                <!-- Walk-in Visit Button -->
                <button type="submit" 
                        name="checkin_type" 
                        value="walkin" 
                        class="saw-selection-card is-walkin">
                    <span class="saw-card-icon">🚶</span>
                    <div class="saw-card-content">
                        <div class="saw-card-title"><?php echo esc_html($t['walkin']); ?></div>
                        <div class="saw-card-description"><?php echo esc_html($t['walkin_desc']); ?></div>
                    </div>
                </button>
                
            </div>
        </form>
        
    </div>
</div>

<?php
error_log("[TYPE.PHP] Unified design loaded (v3.3.0)");
?>