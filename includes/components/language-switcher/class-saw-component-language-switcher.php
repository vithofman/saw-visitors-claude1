<?php
/**
 * Language Switcher Component
 * 
 * Provides a dropdown interface for users to switch between available
 * languages. Updates language preference in the database and reloads
 * the page to apply changes.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/LanguageSwitcher
 * @version     2.2.0
 * @since       4.7.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Language Switcher Component Class
 * 
 * Handles language selection UI and user language preference retrieval.
 * 
 * @since 4.7.0
 */
class SAW_Component_Language_Switcher {
    
    /**
     * Current language code
     * 
     * @since 4.7.0
     * @var string
     */
    private $current_language;
    
    /**
     * Available languages configuration
     * 
     * @since 4.7.0
     * @var array
     */
    private $available_languages;
    
    /**
     * Constructor
     * 
     * Initializes the language switcher with current language and
     * available language options.
     * 
     * @since 4.7.0
     * @param string $current_language Current language code (default: 'cs')
     */
    public function __construct($current_language = 'cs') {
        $this->current_language = $current_language;
        
        // Available languages
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
     * Get current user language from database
     * 
     * Retrieves the user's language preference from the saw_users table.
     * Falls back to 'cs' if not found or user not logged in.
     * 
     * @since 4.7.0
     * @return string Language code (cs or en)
     */
    public static function get_user_language() {
        if (!is_user_logged_in()) {
            return 'cs';
        }
        
        global $wpdb;
        
        $language = $wpdb->get_var($wpdb->prepare(
            "SELECT language FROM %i WHERE wp_user_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            get_current_user_id()
        ));
        
        if ($language) {
            return $language;
        }
        
        // Default fallback
        return 'cs';
    }
    
    /**
     * Render language switcher
     * 
     * Outputs the complete language switcher HTML with dropdown menu.
     * 
     * @since 4.7.0
     * @return void
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
     * Enqueue component assets
     * 
     * Loads CSS, JavaScript, and localizes script data for AJAX.
     * 
     * @since 4.7.0
     * @return void
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