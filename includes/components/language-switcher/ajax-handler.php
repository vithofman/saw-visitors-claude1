<?php
/**
 * Language Switcher AJAX Handler
 * 
 * Samostatný AJAX handler pro přepínání jazyků.
 * Tento soubor se načítá GLOBÁLNĚ při inicializaci pluginu,
 * ne jen při renderu komponenty.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handler
 */
add_action('wp_ajax_saw_switch_language', 'saw_language_switcher_ajax_handler');

/**
 * AJAX handler for language switching
 */
function saw_language_switcher_ajax_handler() {
    // Verify nonce
    check_ajax_referer('saw_language_switcher', 'nonce');
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[Language Switcher AJAX] Request from user: %d',
            get_current_user_id()
        ));
    }
    
    // Get and validate language
    $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
    
    $valid_languages = ['cs', 'en'];
    if (!in_array($language, $valid_languages)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Language Switcher AJAX] Invalid language: ' . $language);
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
            error_log('[Language Switcher AJAX] DB update failed: ' . $wpdb->last_error);
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
            '[Language Switcher AJAX] Success - Language: %s, User: %d, Rows affected: %d',
            $language,
            get_current_user_id(),
            $updated
        ));
    }
    
    wp_send_json_success([
        'language' => $language,
        'message' => 'Jazyk byl úspěšně změněn'
    ]);
}