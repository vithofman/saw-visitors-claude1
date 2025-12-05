<?php
/**
 * Invitation Step - Language Selection
 * 
 * SJEDNOCENO s terminal/steps/1-language.php
 * Používá stejné CSS třídy: saw-card-grid, saw-card-selection
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$languages = $languages ?? [];
$has_languages = !empty($languages);

// Počet jazyků pro grid (max 4 sloupce)
$lang_count = min(count($languages), 4);

// Mapování vlajek pro jazyky
$flags = [
    'cs' => '🇨🇿', 
    'en' => '🇬🇧', 
    'uk' => '🇺🇦', 
    'sk' => '🇸🇰', 
    'de' => '🇩🇪', 
    'pl' => '🇵🇱',
    'vi' => '🇻🇳',
    'ru' => '🇷🇺',
    'hu' => '🇭🇺',
    'ro' => '🇷🇴',
];
?>
<!-- CSS je v pages.css - SJEDNOCENO s terminal -->

<div class="saw-page-aurora saw-step-language">
    <div class="saw-page-content">
        
        <!-- Header - stejná struktura jako terminal -->
        <div class="saw-page-header">
            <div class="saw-header-icon">🌍</div>
            <h1 class="saw-header-title">
                Vyberte jazyk / Choose Language / Оберіть мову
            </h1>
            <p class="saw-header-subtitle">
                Select your preferred language for this session
            </p>
        </div>
        
        <?php if (!$has_languages): ?>
            
            <!-- Error State -->
            <div class="saw-empty-state">
                <div class="saw-empty-state-icon">⚠️</div>
                <p class="saw-empty-state-text">
                    Pro tuto pobočku nejsou nastaveny žádné jazyky.<br>
                    Kontaktujte správce.
                </p>
            </div>
            
        <?php else: ?>
            
            <!-- Language Grid - SJEDNOCENÉ TŘÍDY -->
            <form method="POST">
                <?php wp_nonce_field('saw_invitation_step', 'invitation_nonce'); ?>
                <input type="hidden" name="invitation_action" value="select_language">
                
                <!-- saw-card-grid + saw-card-grid-X místo saw-selection-grid -->
                <div class="saw-card-grid saw-card-grid-<?php echo esc_attr($lang_count); ?>">
                    <?php foreach ($languages as $code => $name): ?>
                    <!-- saw-card-selection místo saw-selection-card -->
                    <button type="submit" 
                            name="language" 
                            value="<?php echo esc_attr($code); ?>"
                            class="saw-card-selection">
                        <!-- saw-card-selection-icon místo saw-card-icon -->
                        <span class="saw-card-selection-icon">
                            <?php echo $flags[$code] ?? '🌍'; ?>
                        </span>
                        <!-- saw-card-selection-title místo saw-card-title -->
                        <span class="saw-card-selection-title">
                            <?php echo esc_html($name); ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>
            
        <?php endif; ?>
        
    </div>
</div>