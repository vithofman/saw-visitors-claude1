<?php
/**
 * Language Switcher Component
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Language_Switcher {
    
    private $current_language;
    private $available_languages;
    private static $ajax_registered = false;
    
    public function __construct($current_language = 'cs') {
        $this->current_language = $current_language;
        
        // Dostupné jazyky (lze rozšířit)
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
        
        // Register AJAX handler (singleton)
        if (!self::$ajax_registered) {
            add_action('wp_ajax_saw_switch_language', [$this, 'ajax_switch_language']);
            self::$ajax_registered = true;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Language Switcher] AJAX handler registered');
            }
        }
    }
    
    /**
     * AJAX: Switch language
     */
    public function ajax_switch_language() {
        check_ajax_referer('saw_language_switcher', 'nonce');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Language Switcher] AJAX request - User: %d',
                get_current_user_id()
            ));
        }
        
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        
        // Validate language
        $valid_languages = array_keys($this->available_languages);
        if (!in_array($language, $valid_languages)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Language Switcher] Invalid language: %s',
                    $language
                ));
            }
            wp_send_json_error(['message' => 'Neplatný jazyk']);
            return;
        }
        
        global $wpdb;
        
        // Update language in saw_users table
        $updated = $wpdb->update(
            $wpdb->prefix . 'saw_users',
            ['language' => $language],
            ['wp_user_id' => get_current_user_id()],
            ['%s'],
            ['%d']
        );
        
        if ($updated === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Language Switcher] DB update failed: %s',
                    $wpdb->last_error
                ));
            }
            wp_send_json_error(['message' => 'Chyba při ukládání jazyka']);
            return;
        }
        
        // Backup to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_current_language'] = $language;
        
        // Backup to WordPress user meta
        update_user_meta(get_current_user_id(), 'saw_current_language', $language);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Language Switcher] Language switched to: %s for user %d',
                $language,
                get_current_user_id()
            ));
        }
        
        wp_send_json_success([
            'language' => $language,
            'language_name' => $this->available_languages[$language]['name'],
            'message' => 'Jazyk byl změněn'
        ]);
    }
    
    /**
     * Get current user language from database
     */
    public static function get_user_language() {
        if (!is_user_logged_in()) {
            return 'cs';
        }
        
        global $wpdb;
        
        $language = $wpdb->get_var($wpdb->prepare(
            "SELECT language FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
            get_current_user_id()
        ));
        
        if ($language) {
            return $language;
        }
        
        // Fallback to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_language'])) {
            return $_SESSION['saw_current_language'];
        }
        
        // Fallback to user meta
        $meta_language = get_user_meta(get_current_user_id(), 'saw_current_language', true);
        if ($meta_language) {
            return $meta_language;
        }
        
        return 'cs';
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