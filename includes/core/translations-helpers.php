<?php
/**
 * SAW Translations Helper Functions
 * 
 * Globální helper funkce pro snadné použití překladů v templatech.
 * 
 * Použití:
 * - saw_t('title', $lang, 'terminal', 'video') - vrátí překlad
 * - saw_te('title', $lang, 'terminal', 'video') - vypíše překlad (escaped)
 * - saw_get_translations($lang, 'terminal', 'video') - vrátí všechny pro stránku
 * 
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      5.1.0
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get single translation
 * 
 * Shorthand pro SAW_Translations::instance()->get()
 * 
 * @since 5.1.0
 * 
 * @param string      $key          Translation key (e.g., 'title', 'confirm')
 * @param string      $lang         Language code (e.g., 'cs', 'en')
 * @param string      $context      Context (terminal, invitation, admin, common)
 * @param string|null $section      Section within context (video, risks, etc.)
 * @param array       $replacements Placeholder replacements ['name' => 'John']
 * @return string Translated text or key if not found
 * 
 * @example
 * // Jednoduchý překlad
 * $title = saw_t('title', 'cs', 'terminal', 'video');
 * 
 * // S placeholders
 * $msg = saw_t('welcome', 'cs', 'terminal', 'success', ['name' => 'Jan']);
 */
function saw_t($key, $lang, $context, $section = null, $replacements = []) {
    if (!class_exists('SAW_Translations')) {
        return $key;
    }
    return SAW_Translations::instance()->get($key, $lang, $context, $section, $replacements);
}

/**
 * Echo translation (HTML escaped)
 * 
 * Vypíše překlad s HTML escapováním - bezpečné pro výstup.
 * 
 * @since 5.1.0
 * 
 * @param string      $key          Translation key
 * @param string      $lang         Language code
 * @param string      $context      Context
 * @param string|null $section      Section
 * @param array       $replacements Placeholder replacements
 * @return void
 * 
 * @example
 * <h1><?php saw_te('title', $lang, 'terminal', 'video'); ?></h1>
 */
function saw_te($key, $lang, $context, $section = null, $replacements = []) {
    echo esc_html(saw_t($key, $lang, $context, $section, $replacements));
}

/**
 * Echo translation (with HTML allowed)
 * 
 * Vypíše překlad s povoleným bezpečným HTML (wp_kses_post).
 * Použij když překlad obsahuje <strong>, <em>, <a> atd.
 * 
 * @since 5.1.0
 * 
 * @param string      $key          Translation key
 * @param string      $lang         Language code
 * @param string      $context      Context
 * @param string|null $section      Section
 * @param array       $replacements Placeholder replacements
 * @return void
 * 
 * @example
 * <p><?php saw_te_html('message_with_link', $lang, 'terminal', 'success'); ?></p>
 */
function saw_te_html($key, $lang, $context, $section = null, $replacements = []) {
    echo wp_kses_post(saw_t($key, $lang, $context, $section, $replacements));
}

/**
 * Get all translations for page/section
 * 
 * Vrátí všechny překlady pro konkrétní stránku jako pole key => text.
 * Automaticky merguje: common → context general → context/section
 * 
 * @since 5.1.0
 * 
 * @param string      $lang    Language code
 * @param string      $context Context (terminal, invitation, admin, common)
 * @param string|null $section Section (video, risks, success, etc.)
 * @return array Key => text pairs
 * 
 * @example
 * $t = saw_get_translations('cs', 'terminal', 'video');
 * echo $t['title'];    // "Školící video"
 * echo $t['confirm'];  // "Potvrzuji zhlédnutí videa"
 * echo $t['continue']; // "Pokračovat" (z terminal general)
 * echo $t['yes'];      // "Ano" (z common)
 */
function saw_get_translations($lang, $context, $section = null) {
    if (!class_exists('SAW_Translations')) {
        return [];
    }
    return SAW_Translations::instance()->get_for_page($lang, $context, $section);
}

/**
 * Output translations for JavaScript
 * 
 * Vypíše <script> tag s překlady pro použití v JS.
 * 
 * @since 5.1.0
 * 
 * @param string      $lang     Language code
 * @param string      $context  Context
 * @param string|null $section  Section
 * @param string      $var_name JavaScript variable name (default: 'sawT')
 * @return void
 * 
 * @example
 * // V PHP šabloně (header nebo footer)
 * <?php saw_translations_js($lang, 'terminal', 'video'); ?>
 * 
 * // Pak v JavaScript:
 * const title = sawGetText('title'); // "Školící video"
 */
function saw_translations_js($lang, $context, $section = null, $var_name = 'sawT') {
    if (!class_exists('SAW_Translations')) {
        return;
    }
    
    $data = SAW_Translations::instance()->get_for_js($lang, $context, $section);
    
    echo '<script>';
    echo 'window.' . esc_js($var_name) . ' = ' . wp_json_encode($data) . ';';
    echo 'window.sawGetText = function(key, replacements) {';
    echo '  var text = window.' . esc_js($var_name) . '.strings[key] || key;';
    echo '  if (replacements) {';
    echo '    for (var placeholder in replacements) {';
    echo '      text = text.replace("{" + placeholder + "}", replacements[placeholder]);';
    echo '    }';
    echo '  }';
    echo '  return text;';
    echo '};';
    echo '</script>';
}

/**
 * Get available UI languages
 * 
 * Vrátí seznam dostupných jazyků pro UI.
 * 
 * @since 5.1.0
 * 
 * @param bool $active_only Only active languages (default: true)
 * @return array Array of language data
 * 
 * @example
 * $languages = saw_get_ui_languages();
 * foreach ($languages as $lang) {
 *     echo $lang['flag_emoji'] . ' ' . $lang['native_name'];
 * }
 */
function saw_get_ui_languages($active_only = true) {
    if (!class_exists('SAW_Translations')) {
        return [];
    }
    return SAW_Translations::instance()->get_available_languages($active_only);
}

/**
 * Normalize language code
 * 
 * Normalizuje kód jazyka (např. 'cz' → 'cs').
 * Vrátí fallback jazyk pokud jazyk není dostupný.
 * 
 * @since 5.1.0
 * 
 * @param string $lang Language code
 * @return string Normalized language code
 * 
 * @example
 * $lang = saw_normalize_language('cz');  // Returns 'cs'
 * $lang = saw_normalize_language('xyz'); // Returns 'en' (fallback)
 */
function saw_normalize_language($lang) {
    if (!class_exists('SAW_Translations')) {
        return 'en';
    }
    return SAW_Translations::instance()->normalize_language($lang);
}

/**
 * Check if language is available
 * 
 * @since 5.1.0
 * 
 * @param string $lang Language code
 * @return bool
 */
function saw_is_language_available($lang) {
    if (!class_exists('SAW_Translations')) {
        return false;
    }
    return SAW_Translations::instance()->is_language_available($lang);
}

/**
 * Clear translation cache
 * 
 * Vymaže cache překladů. Volej po úpravě překladů v DB.
 * 
 * @since 5.1.0
 * 
 * @return void
 */
function saw_clear_translations_cache() {
    if (!class_exists('SAW_Translations')) {
        return;
    }
    SAW_Translations::instance()->clear_cache();
}

/**
 * Import translations
 * 
 * Importuje překlady z pole do databáze.
 * 
 * @since 5.1.0
 * 
 * @param array $translations Array of translations
 * @return int Number of imported translations
 * 
 * @example
 * $translations = [
 *     ['key' => 'title', 'lang' => 'cs', 'context' => 'terminal', 'section' => 'video', 'text' => 'Školící video'],
 *     ['key' => 'title', 'lang' => 'en', 'context' => 'terminal', 'section' => 'video', 'text' => 'Training Video'],
 * ];
 * $count = saw_import_translations($translations);
 */
function saw_import_translations($translations) {
    if (!class_exists('SAW_Translations')) {
        return 0;
    }
    return SAW_Translations::instance()->import($translations);
}