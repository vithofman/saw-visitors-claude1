<?php
/**
 * Seed UI Languages
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seed default UI languages
 * 
 * @return int Number of seeded records
 */
function saw_seed_ui_languages() {
    global $wpdb;
    $table = $wpdb->prefix . 'saw_ui_languages';
    
    // Check if already seeded
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    if ($count > 0) {
        return 0; // Already seeded
    }
    
    $languages = [
        [
            'language_code' => 'cs',
            'language_name' => 'Čeština',
            'native_name' => 'Čeština',
            'flag_emoji' => '🇨🇿',
            'is_default' => 1,
            'is_fallback' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ],
        [
            'language_code' => 'en',
            'language_name' => 'Angličtina',
            'native_name' => 'English',
            'flag_emoji' => '🇬🇧',
            'is_default' => 0,
            'is_fallback' => 1,
            'is_active' => 1,
            'sort_order' => 2,
        ],
    ];
    
    $inserted = 0;
    foreach ($languages as $lang) {
        $result = $wpdb->insert($table, $lang);
        if ($result) {
            $inserted++;
        }
    }
    
    return $inserted;
}
