<?php
/**
 * TESTOVACÍ SKRIPT - vlož do root pluginu
 * Spusť: yoursite.com/wp-content/plugins/saw-visitors/test-ajax.php
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: application/json');

// Test 1: Zkontroluj tabulku
global $wpdb;
$table = $wpdb->prefix . 'saw_customers';
$customers = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active'", ARRAY_A);

echo json_encode([
    'test' => 'Customer Switcher Debug',
    'table' => $table,
    'customers_count' => count($customers),
    'customers' => $customers,
    'ajax_actions' => [
        'saw_get_customers_for_switcher' => has_action('wp_ajax_saw_get_customers_for_switcher'),
        'saw_switch_customer' => has_action('wp_ajax_saw_switch_customer'),
    ],
    'class_exists' => class_exists('SAW_Module_Customers_Controller'),
], JSON_PRETTY_PRINT);