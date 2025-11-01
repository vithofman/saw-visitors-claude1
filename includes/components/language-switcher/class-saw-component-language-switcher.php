<?php
/**
 * Language Switcher Component
 * 
 * Globální komponenta pro přepínání jazyků administrace
 * Zobrazuje se v headeru vpravo (místo současného customer switcheru)
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Language_Switcher {
    
    private $current_language;
    private $available_languages;
    
    public function __construct($current_language = 'cs') {
        $this->current_language = $current_language;
        
        // Placeholder pro jazyky (později rozšířit o překlady)
        $this->available_languages = array(
            'cs' => array(
                'code' => 'cs',
                'name' => 'Čeština',
                'flag' => '🇨🇿',
            ),
            'en' => array(
                'code' => 'en',
                'name' => 'English',
                'flag' => '🇬🇧',
            ),
        );
    }
    
    /**
     * Render language switcher
     */
    public function render() {
        $this->enqueue_assets();
        
        $current = $this->available_languages[$this->current_language] ?? $this->available_languages['cs'];
        ?>
        <div class="saw-language-switcher" id="sawLanguageSwitcher">
            <button class="saw-language-switcher-button" id="sawLanguageSwitcherButton"
                    data-current-language="<?php echo esc_attr($this->current_language); ?>">
                <span class="saw-language-flag"><?php echo esc_html($current['flag']); ?></span>
                <span class="saw-language-code"><?php echo esc_html(strtoupper($current['code'])); ?></span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-language-arrow">
                    <path d="M8 10.5l-4-4h8l-4 4z"/>
                </svg>
            </button>
            
            <div class="saw-language-switcher-dropdown" id="sawLanguageSwitcherDropdown">
                <?php foreach ($this->available_languages as $lang): ?>
                    <?php
                    $isActive = $lang['code'] === $this->current_language;
                    $activeClass = $isActive ? 'active' : '';
                    ?>
                    <div class="saw-language-item <?php echo esc_attr($activeClass); ?>" 
                         data-language="<?php echo esc_attr($lang['code']); ?>">
                        <span class="saw-language-item-flag"><?php echo esc_html($lang['flag']); ?></span>
                        <span class="saw-language-item-name"><?php echo esc_html($lang['name']); ?></span>
                        <?php if ($isActive): ?>
                            <span class="saw-language-item-check">✓</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-language-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/language-switcher/language-switcher.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-language-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/language-switcher/language-switcher.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script(
            'saw-language-switcher',
            'sawLanguageSwitcher',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_language_switcher'),
            )
        );
    }
}
