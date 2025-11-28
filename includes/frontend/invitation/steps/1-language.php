<?php
/**
 * Invitation Step - Language Selection
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

$languages = $languages ?? [];
$has_languages = !empty($languages);
?>
<!-- Žádný <style> blok! CSS je v pages.css -->

<div class="saw-page-aurora saw-step-language">
    <div class="saw-page-content saw-page-content-centered">
        
        <!-- Header -->
        <div class="saw-page-header saw-page-header-centered">
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
            
            <!-- Language Grid -->
            <form method="POST">
                <?php wp_nonce_field('saw_invitation_step', 'invitation_nonce'); ?>
                <input type="hidden" name="invitation_action" value="select_language">
                
                <div class="saw-selection-grid saw-lang-<?php echo min(count($languages), 6); ?>">
                    <?php 
                    $flags = ['cs' => '🇨🇿', 'en' => '🇬🇧', 'uk' => '🇺🇦', 'sk' => '🇸🇰', 'de' => '🇩🇪', 'pl' => '🇵🇱'];
                    foreach ($languages as $code => $name): 
                    ?>
                    <button type="submit" 
                            name="language" 
                            value="<?php echo esc_attr($code); ?>"
                            class="saw-selection-card">
                        <span class="saw-card-icon">
                            <?php echo $flags[$code] ?? '🌍'; ?>
                        </span>
                        <span class="saw-card-title">
                            <?php echo esc_html($name); ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>
            
        <?php endif; ?>
        
    </div>
</div>

