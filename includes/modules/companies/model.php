<?php
/**
 * Companies Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     3.1.0 - FIXED: Lepší normalizace pro detekci duplicit
 */
if (!defined('ABSPATH')) {
    exit;
}
class SAW_Module_Companies_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 300;
    }
    
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (empty($data['name'])) {
            $errors['name'] = 'Company name is required';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Invalid website URL';
        }
        
        if (!empty($data['ico']) && $this->ico_exists($data['customer_id'], $data['ico'], $id)) {
            $errors['ico'] = 'Company with this IČO already exists';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    private function ico_exists($customer_id, $ico, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($ico)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d AND ico = %s AND id != %d",
            $this->table,
            $customer_id,
            $ico,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if (!$item) {
            return null;
        }
        
        $item['is_archived_label'] = !empty($item['is_archived']) ? 'Archivováno' : 'Aktivní';
        $item['is_archived_badge_class'] = !empty($item['is_archived']) ? 'saw-badge saw-badge-secondary' : 'saw-badge saw-badge-success';
        
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    public function get_branches_for_select($customer_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
            $wpdb->prefix . 'saw_branches',
            $customer_id
        ), ARRAY_A);
    }
    
    // ==========================================
    // ✅ MERGE & DUPLICATE DETECTION FUNCTIONS
    // ==========================================
    
    /**
     * Find similar companies (fuzzy search for duplicates)
     * 
     * @param string $name Company name to search for
     * @param int $branch_id Branch ID
     * @param int $exclude_id Company ID to exclude (master company)
     * @return array Array of similar companies with similarity scores
     */
    public function find_similar_companies($name, $branch_id, $exclude_id) {
        global $wpdb;
        
        // Normalize master name for comparison
        $normalized = $this->normalize_company_name($name);
        
        // Get all companies from same branch (excluding master)
        $companies = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c.id, 
                c.name, 
                c.ico,
                (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE company_id = c.id) as visit_count
             FROM %i c
             WHERE c.branch_id = %d 
             AND c.id != %d
             AND c.is_archived = 0
             ORDER BY c.name ASC",
            $this->table,
            $branch_id, 
            $exclude_id
        ), ARRAY_A);
        
        // Calculate similarity for each company
        $similar = array();
        foreach ($companies as $company) {
            $similarity = $this->calculate_similarity(
                $normalized, 
                $this->normalize_company_name($company['name'])
            );
            
            // Only include if similarity > 70%
            if ($similarity > 0.7) {
                $company['similarity'] = round($similarity * 100);
                $similar[] = $company;
            }
        }
        
        // Sort by similarity (highest first)
        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return $similar;
    }
    
    /**
     * ✅ ULTRA AGGRESSIVE NORMALIZATION
     * Finds even "ABC s. r.o." vs "ABC s.r.o."
     */
    private function normalize_company_name($name) {
        // Remove diacritics
        $name = remove_accents($name);
        
        // Lowercase
        $name = mb_strtolower($name, 'UTF-8');
        
        // Remove ALL special chars (keep alphanumeric only)
        // This removes spaces too, so we don't need to handle them in legal forms
        $name = preg_replace('/[^a-z0-9]/', '', $name);
        
        // Legal forms - stripped of spaces
        $legal_forms = array(
            'spolecnostslimitovanymrucenim',
            'akciovaspolecnost',
            'spolsro',
            'sro', 
            'as', 
            'vos',
            'ks',
            'ops',
            'zs',
            'os',
        );
        
        // Sort by length descending to match longest first
        usort($legal_forms, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($legal_forms as $form) {
            $name = str_replace($form, '', $name);
        }
        
        return trim($name);
    }
    
    /**
     * Calculate similarity between two strings
     * Uses similar_text() function
     * 
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculate_similarity($str1, $str2) {
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }
    
    /**
     * ✅ UNIVERSAL MERGE FUNCTION
     */
    public function merge_companies($master_id, $duplicate_ids) {
        global $wpdb;
        
        if (empty($duplicate_ids)) {
            return new WP_Error('empty_duplicates', 'No duplicate companies provided');
        }
        
        $master = $this->get_by_id($master_id);
        if (!$master) {
            return new WP_Error('invalid_master', 'Master company not found');
        }
        
        $tables = $wpdb->get_results("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND COLUMN_NAME = 'company_id'
            AND TABLE_NAME LIKE '{$wpdb->prefix}saw_%'
        ", ARRAY_A);
        
        if (empty($tables)) {
            return new WP_Error('no_tables', 'No tables with company_id found');
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $total_updated = 0;
            
            foreach ($tables as $table_row) {
                $table = $table_row['TABLE_NAME'];
                
                foreach ($duplicate_ids as $dup_id) {
                    $updated = $wpdb->update(
                        $table,
                        array('company_id' => $master_id),
                        array('company_id' => $dup_id),
                        array('%d'),
                        array('%d')
                    );
                    
                    if ($updated !== false) {
                        $total_updated += $updated;
                    }
                }
            }
            
            $placeholders = implode(',', array_fill(0, count($duplicate_ids), '%d'));
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE id IN ($placeholders)",
                $this->table,
                ...$duplicate_ids
            ));
            
            $wpdb->query('COMMIT');
            
            $this->invalidate_cache();
            
            SAW_Logger::debug(sprintf(
                '[Companies Model] Successfully merged companies %s into %d. Updated %d records, deleted %d companies.',
                implode(',', $duplicate_ids),
                $master_id,
                $total_updated,
                $deleted
            ));
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            SAW_Logger::error('SAW Merge Error: ' . $e->getMessage());
            return new WP_Error('merge_failed', 'Failed to merge companies: ' . $e->getMessage());
        }
    }
    
    public function get_tables_with_company_id() {
        global $wpdb;
        
        $tables = $wpdb->get_results("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND COLUMN_NAME = 'company_id'
            AND TABLE_NAME LIKE '{$wpdb->prefix}saw_%'
        ", ARRAY_A);
        
        return array_column($tables, 'TABLE_NAME');
    }
    
    public function find_all_duplicates($customer_id) {
        global $wpdb;
        
        $companies = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, branch_id, ico,
             (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE company_id = c.id) as visit_count
             FROM %i c
             WHERE customer_id = %d 
             AND is_archived = 0
             ORDER BY name ASC",
            $this->table,
            $customer_id
        ), ARRAY_A);
        
        $groups = array();
        foreach ($companies as $company) {
            $normalized = $this->normalize_company_name($company['name']);
            
            if (!isset($groups[$normalized])) {
                $groups[$normalized] = array();
            }
            
            $groups[$normalized][] = $company;
        }
        
        $duplicates = array();
        foreach ($groups as $normalized => $group) {
            if (count($group) >= 2) {
                $duplicates[] = array(
                    'normalized_name' => $normalized,
                    'count' => count($group),
                    'companies' => $group,
                    'total_visits' => array_sum(array_column($group, 'visit_count'))
                );
            }
        }
        
        usort($duplicates, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        return $duplicates;
    }
}