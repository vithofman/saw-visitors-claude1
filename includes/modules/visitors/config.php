<?php
/**
 * Visitors Module Configuration
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     3.0.0 - FIXED: Removed custom_ajax_actions (now handled in controller)
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'entity' => 'visitors',
    'table' => 'saw_visitors',
    'singular' => 'Návštěvník',
    'plural' => 'Návštěvníci',
    'route' => 'visitors',
    'icon' => '👤',
    'has_customer_isolation' => true,
    'edit_url' => 'visitors/{id}/edit',
    
    'capabilities' => array(
        'list' => 'manage_options',
        'view' => 'manage_options',
        'create' => 'manage_options',
        'edit' => 'manage_options',
        'delete' => 'manage_options',
    ),
    
    'fields' => array(
        'visit_id' => array(
            'type' => 'number',
            'label' => 'Návštěva ID',
            'required' => true,
            'sanitize' => 'absint',
        ),
        'first_name' => array(
            'type' => 'text',
            'label' => 'Jméno',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'last_name' => array(
            'type' => 'text',
            'label' => 'Příjmení',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
        ),
        'position' => array(
            'type' => 'text',
            'label' => 'Pozice',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'email' => array(
            'type' => 'email',
            'label' => 'Email',
            'required' => false,
            'sanitize' => 'sanitize_email',
        ),
        'phone' => array(
            'type' => 'text',
            'label' => 'Telefon',
            'required' => false,
            'sanitize' => 'sanitize_text_field',
        ),
        'participation_status' => array(
            'type' => 'select',
            'label' => 'Stav účasti',
            'required' => true,
            'sanitize' => 'sanitize_text_field',
            'default' => 'planned',
            'options' => array(
                'planned' => 'Plánovaný',
                'confirmed' => 'Potvrzený',
                'no_show' => 'Nedostavil se',
            ),
        ),
        'training_skipped' => array(
            'type' => 'checkbox',
            'label' => 'Školení absolvováno do 1 roku',
            'required' => false,
            'sanitize' => 'absint',
            'default' => 0,
        ),
    ),
    
    'list_config' => array(
        'columns' => array(
            'id',
            'first_name',
            'last_name',
            'visit_id',
            'current_status',
            'first_checkin_at',
            'last_checkout_at'
        ),
        'searchable' => array('first_name', 'last_name', 'email'),
        'sortable' => array('id', 'first_name', 'last_name', 'created_at'),
        'filters' => array(
            'training_status' => true, // Filter by training status instead of training_required
        ),
        'per_page' => 20,
        'enable_detail_modal' => true,
    ),
    
    // TABS configuration - for horizontal tabs navigation
    'tabs' => array(
        'enabled' => true,
        'tab_param' => 'current_status', // GET parameter (?current_status=present)
        'tabs' => array(
            'all' => array(
                'label' => 'Všechny',
                'icon' => '📋',
                'filter_value' => null, // null = no filter (all records)
                'count_query' => true,
            ),
            'present' => array(
                'label' => 'Přítomen',
                'icon' => '✅',
                'filter_value' => 'present',
                'count_query' => true,
            ),
            'checked_out' => array(
                'label' => 'Odhlášen',
                'icon' => '🚪',
                'filter_value' => 'checked_out',
                'count_query' => true,
            ),
            'confirmed' => array(
                'label' => 'Potvrzený',
                'icon' => '⏳',
                'filter_value' => 'confirmed',
                'count_query' => true,
            ),
            'planned' => array(
                'label' => 'Plánovaný',
                'icon' => '📅',
                'filter_value' => 'planned',
                'count_query' => true,
            ),
            'no_show' => array(
                'label' => 'Nedostavil se',
                'icon' => '❌',
                'filter_value' => 'no_show',
                'count_query' => true,
            ),
        ),
        'default_tab' => 'all',
    ),
    
    // ========================================
    // VIRTUAL COLUMNS CONFIGURATION
    // ========================================
    // Tyto sloupce nejsou v databázi, ale jsou počítané dynamicky.
    // Base Model je aplikuje automaticky po načtení dat.
    'virtual_columns' => array(
        // ====================================
        // current_status - Aktuální stav návštěvníka
        // ====================================
        'current_status' => array(
            'type' => 'batch_computed',
            
            // Batch query - JEDEN dotaz pro VŠECHNY návštěvníky (efektivní)
            'batch_query' => function($visitor_ids, $wpdb) {
                $today = current_time('Y-m-d');
                
                // ✅ BEZPEČNOST: Získej scope
                $customer_id = 0;
                $branch_id = 0;
                if (class_exists('SAW_Context')) {
                    $customer_id = SAW_Context::get_customer_id();
                    $branch_id = SAW_Context::get_branch_id();
                }
                
                // Validace
                if (empty($visitor_ids)) {
                    return array();
                }
                
                // Připrav placeholders
                $placeholders = implode(',', array_fill(0, count($visitor_ids), '%d'));
                
                // Sestavení query
                $query = "SELECT 
                    visitor_id,
                    MAX(checked_in_at) as last_checkin,
                    MAX(checked_out_at) as last_checkout
                 FROM {$wpdb->prefix}saw_visit_daily_logs 
                 WHERE visitor_id IN ($placeholders)
                   AND log_date = %s";
                
                $params = array_merge($visitor_ids, array($today));
                
                // ✅ KRITICKÉ: Přidej scope filtering (bezpečnost!)
                if ($customer_id) {
                    $query .= " AND customer_id = %d";
                    $params[] = $customer_id;
                }
                if ($branch_id) {
                    $query .= " AND branch_id = %d";
                    $params[] = $branch_id;
                }
                
                $query .= " GROUP BY visitor_id";
                
                return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
            },
            
            // Apply - Aplikuj výsledky batch query na konkrétní item
            'apply' => function($item, $batch_results) {
                // ✅ Zkontroluj že item má ID
                if (empty($item['id'])) {
                    return 'unknown';
                }
                
                // ✅ Zkontroluj že participation_status existuje
                $participation_status = $item['participation_status'] ?? 'planned';
                
                // Získej log pro tohoto návštěvníka z batch výsledků
                $log = $batch_results[$item['id']] ?? null;
                
                // Výpočet statusu podle business logiky
                if ($participation_status === 'confirmed') {
                    if ($log && $log['last_checkin'] && !$log['last_checkout']) {
                        return 'present'; // Checked in, not checked out
                    } elseif ($log && $log['last_checkout']) {
                        return 'checked_out'; // Checked out today
                    } else {
                        return 'confirmed'; // Confirmed but not checked in today
                    }
                } elseif ($participation_status === 'no_show') {
                    return 'no_show';
                } else {
                    return 'planned';
                }
            }
        ),
        
        // ====================================
        // training_status - Stav školení
        // ====================================
        'training_status' => array(
            'type' => 'computed',
            
            // Jednoduchý výpočet - bez DB access
            'compute' => function($item) {
                // Jednoduché rozhodování na základě existujících polí
                if (!empty($item['training_skipped'])) {
                    return 'skipped';
                } elseif (!empty($item['training_completed_at'])) {
                    return 'completed';
                } elseif (!empty($item['training_started_at'])) {
                    return 'in_progress';
                } else {
                    return 'not_started';
                }
            }
        ),
        
        // ====================================
        // full_name - Celé jméno (concat example)
        // ====================================
        'full_name' => array(
            'type' => 'concat',
            'fields' => array('first_name', 'last_name'),
            'separator' => ' '
        )
    ),
    
    'cache' => array(
        'enabled' => true,
        'ttl' => 300,
        'invalidate_on' => array('save', 'delete'),
    ),
);