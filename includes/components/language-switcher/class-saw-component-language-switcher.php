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
     * Register AJAX handlers globally
     *
     * CRITICAL: This must be called early (via Component Manager), NOT in constructor.
     * Ensures AJAX handlers work even if component doesn't render.
     *
     * @since 8.0.0
     * @return void
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_saw_switch_language', array(__CLASS__, 'ajax_switch_language'));
    }
    
    /**
     * AJAX: Switch language
     *
     * Static method called by WordPress AJAX system.
     * Updates user's language preference in saw_users table with
     * session and user meta fallbacks for reliability.
     *
     * @since 8.0.0
     * @return void Outputs JSON response
     */
    public static function ajax_switch_language() {
        // Verify nonce
        check_ajax_referer('saw_language_switcher', 'nonce');
        
        // Get and validate language
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        
        $valid_languages = array('cs', 'en');
        if (!in_array($language, $valid_languages, true)) {
            wp_send_json_error(array('message' => 'Neplatný jazyk'));
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Update language in saw_users table
        $updated = $wpdb->update(
            $wpdb->prefix . 'saw_users',
            array('language' => $language),
            array('wp_user_id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Language Switcher] DB update failed for user %d: %s',
                    $user_id,
                    $wpdb->last_error
                ));
            }
            wp_send_json_error(array('message' => 'Chyba při ukládání jazyka'));
            return;
        }
        
        // Backup to session for reliability
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_current_language'] = $language;
        
        // Backup to WordPress user meta
        update_user_meta($user_id, 'saw_current_language', $language);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Language Switcher] Success - Language: %s, User: %d, DB rows: %d',
                $language,
                $user_id,
                $updated
            ));
        }
        
        wp_send_json_success(array(
            'language' => $language,
            'message' => 'Jazyk byl úspěšně změněn'
        ));
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
        
        // Oprava: %i není podporováno v $wpdb->prepare(), použijeme string concatenation pro table name
        $table_name = $wpdb->prefix . 'saw_users';
        $language = $wpdb->get_var($wpdb->prepare(
            "SELECT language FROM {$table_name} WHERE wp_user_id = %d AND is_active = 1",
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
     * DEPRECATED: Assets are now loaded globally via SAW_Asset_Loader.
     * This method is kept for backwards compatibility but does nothing.
     * 
     * @since 4.7.0
     * @deprecated 8.0.0 Use SAW_Asset_Loader instead
     * @return void
     */
    private function enqueue_assets() {
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
    }
}