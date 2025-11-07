<?php
/**
 * Language Switcher AJAX Handler
 * 
 * Standalone AJAX handler for language switching. Loaded globally during
 * plugin initialization, not just when the component is rendered.
 * Updates user language preference in the saw_users database table with
 * session and user meta fallbacks for reliability.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/LanguageSwitcher
 * @version     1.0.0
 * @since       4.7.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handler for language switching
 * 
 * @since 4.7.0
 */
add_action('wp_ajax_saw_switch_language', 'saw_language_switcher_ajax_handler');

/**
 * AJAX handler for language switching
 * 
 * Handles language change requests from authenticated users.
 * Updates the language preference in the saw_users table with
 * session and user meta fallbacks.
 * 
 * @since 4.7.0
 * @return void Sends JSON response and exits
 */
function saw_language_switcher_ajax_handler() {
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
    
    $table = $wpdb->prefix . 'saw_users';
    $user_id = get_current_user_id();
    
    // Update language in saw_users table
    $updated = $wpdb->update(
        $table,
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