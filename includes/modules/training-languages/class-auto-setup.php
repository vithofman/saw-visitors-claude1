<?php
/**
 * Training Languages Auto Setup
 * 
 * Automaticky vytváří češtinu pro nové zákazníky a pobočky.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Training_Languages_Auto_Setup {
    
    /**
     * Constructor - registruje WordPress akce
     */
    public function __construct() {
        // Hook pro vytvoření zákazníka
        add_action('saw_customer_created', [$this, 'create_czech_for_customer'], 10, 1);
        
        // Hook pro vytvoření pobočky
        add_action('saw_branch_created', [$this, 'activate_czech_for_branch'], 10, 2);
    }
    
    /**
     * Vytvoří češtinu pro nového zákazníka
     * 
     * @param int $customer_id ID zákazníka
     */
    public function create_czech_for_customer($customer_id) {
        global $wpdb;
        
        if (empty($customer_id)) {
            return;
        }
        
        // Zkontrolovat, zda čeština už neexistuje
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = 'cs'",
            $customer_id
        ));
        
        if ($exists) {
            error_log("[SAW Auto-Setup] Czech already exists for customer #{$customer_id}");
            return;
        }
        
        // ✅ OPRAVENO - pouze sloupce které skutečně existují v tabulce
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_training_languages',
            [
                'customer_id' => $customer_id,
                'language_code' => 'cs',
                'language_name' => 'Čeština',
                'flag_emoji' => '🇨🇿',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            $language_id = $wpdb->insert_id;
            
            // Log úspěchu
            error_log("[SAW Auto-Setup] ✓ Created Czech language (ID: {$language_id}) for customer #{$customer_id}");
            
            // Aktivovat češtinu pro všechny existující pobočky zákazníka
            $this->activate_czech_for_all_branches($customer_id, $language_id);
            
            // Audit log
            if (class_exists('SAW_Audit_Log')) {
                SAW_Audit_Log::log(
                    'language_auto_created',
                    "Czech language automatically created for new customer",
                    null,
                    $customer_id
                );
            }
        } else {
            error_log("[SAW Auto-Setup] ✗ Failed to create Czech for customer #{$customer_id}: " . $wpdb->last_error);
        }
    }
    
    /**
     * Aktivuje češtinu pro novou pobočku
     * 
     * @param int $branch_id ID pobočky
     * @param int $customer_id ID zákazníka
     */
    public function activate_czech_for_branch($branch_id, $customer_id) {
        global $wpdb;
        
        if (empty($branch_id) || empty($customer_id)) {
            return;
        }
        
        // Najít češtinu pro zákazníka
        $czech_lang = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = 'cs'",
            $customer_id
        ));
        
        if (!$czech_lang) {
            error_log("[SAW Auto-Setup] Czech language not found for customer #{$customer_id}");
            return;
        }
        
        // Zkontrolovat, zda už není aktivována
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_language_branches 
             WHERE language_id = %d AND branch_id = %d",
            $czech_lang->id,
            $branch_id
        ));
        
        if ($exists) {
            error_log("[SAW Auto-Setup] Czech already activated for branch #{$branch_id}");
            return;
        }
        
        // Zjistit, zda je to první pobočka zákazníka
        $branch_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND id <= %d",
            $customer_id,
            $branch_id
        ));
        
        $is_first_branch = ($branch_count == 1);
        
        // Aktivovat češtinu pro pobočku
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_training_language_branches',
            [
                'language_id' => $czech_lang->id,
                'branch_id' => $branch_id,
                'is_default' => $is_first_branch ? 1 : 0,
                'is_active' => 1,
                'display_order' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%d', '%s']
        );
        
        if ($result) {
            error_log("[SAW Auto-Setup] ✓ Activated Czech for branch #{$branch_id} (customer #{$customer_id})");
            
            // Audit log
            if (class_exists('SAW_Audit_Log')) {
                SAW_Audit_Log::log(
                    'language_auto_activated',
                    "Czech language automatically activated for new branch",
                    null,
                    $customer_id
                );
            }
        } else {
            error_log("[SAW Auto-Setup] ✗ Failed to activate Czech for branch #{$branch_id}: " . $wpdb->last_error);
        }
    }
    
    /**
     * Aktivuje češtinu pro všechny pobočky zákazníka
     * 
     * @param int $customer_id ID zákazníka
     * @param int $language_id ID češtiny
     */
    private function activate_czech_for_all_branches($customer_id, $language_id) {
        global $wpdb;
        
        // Získat všechny pobočky zákazníka
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND is_active = 1
             ORDER BY is_headquarters DESC, created_at ASC",
            $customer_id
        ));
        
        if (empty($branches)) {
            return;
        }
        
        $first_branch = true;
        
        foreach ($branches as $branch) {
            // Zkontrolovat, zda už není aktivována
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_training_language_branches 
                 WHERE language_id = %d AND branch_id = %d",
                $language_id,
                $branch->id
            ));
            
            if ($exists) {
                continue;
            }
            
            // Aktivovat
            $wpdb->insert(
                $wpdb->prefix . 'saw_training_language_branches',
                [
                    'language_id' => $language_id,
                    'branch_id' => $branch->id,
                    'is_default' => $first_branch ? 1 : 0,
                    'is_active' => 1,
                    'display_order' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%d', '%s']
            );
            
            $first_branch = false;
        }
        
        error_log("[SAW Auto-Setup] ✓ Czech activated for " . count($branches) . " branches");
    }
}

// Inicializace
new SAW_Training_Languages_Auto_Setup();