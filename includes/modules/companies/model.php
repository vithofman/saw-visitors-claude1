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
        $normalized_len = mb_strlen($normalized);
        
        // Get ALL companies from same branch (including archived)
        $companies = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c.id, 
                c.name, 
                c.ico,
                c.is_archived,
                (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE company_id = c.id) as visit_count
             FROM %i c
             WHERE c.branch_id = %d 
             AND c.id != %d
             ORDER BY c.name ASC",
            $this->table,
            $branch_id, 
            $exclude_id
        ), ARRAY_A);
        
        // Calculate similarity for each company
        $similar = array();
        foreach ($companies as $company) {
            $candidate_normalized = $this->normalize_company_name($company['name']);
            $candidate_len = mb_strlen($candidate_normalized);
            
            // Check for EXACT name match (including legal form)
            $original_lower = mb_strtolower(trim($name), 'UTF-8');
            $candidate_lower = mb_strtolower(trim($company['name']), 'UTF-8');
            $is_exact_match = ($original_lower === $candidate_lower);
            
            // Check for normalized match (same name, different legal form)
            $is_normalized_match = ($normalized === $candidate_normalized);
            
            // Calculate combined similarity score
            $similarity = $this->calculate_similarity_advanced(
                $normalized, 
                $candidate_normalized
            );
            
            // Dynamic threshold based on name length
            // Short names (1-3 chars): 50% threshold (e.g., "a1" vs "a2")
            // Medium names (4-8 chars): 60% threshold
            // Long names (9+ chars): 70% threshold
            $min_len = min($normalized_len, $candidate_len);
            if ($min_len <= 3) {
                $threshold = 0.50;
            } elseif ($min_len <= 8) {
                $threshold = 0.60;
            } else {
                $threshold = 0.70;
            }
            
            // Check for prefix match (e.g., "archivovana" matches "archivovana2")
            $is_prefix_match = false;
            if ($min_len >= 2) {
                $shorter = $normalized_len <= $candidate_len ? $normalized : $candidate_normalized;
                $longer = $normalized_len > $candidate_len ? $normalized : $candidate_normalized;
                if (strpos($longer, $shorter) === 0 || strpos($shorter, $longer) === 0) {
                    $is_prefix_match = true;
                }
            }
            
            // Include if meets threshold OR is prefix match OR is normalized match
            if ($similarity >= $threshold || $is_prefix_match || $is_normalized_match) {
                // Determine final similarity percentage
                if ($is_exact_match) {
                    // EXACT match (including legal form) = 100%
                    $final_similarity = 100;
                } elseif ($is_normalized_match) {
                    // Same name, different legal form = 95%
                    $final_similarity = 95;
                } elseif ($is_prefix_match) {
                    // Prefix match = 80-90%
                    $final_similarity = 85;
                } else {
                    // Regular similarity
                    $final_similarity = round($similarity * 100);
                }
                
                $company['similarity'] = $final_similarity;
                $company['is_exact_match'] = $is_exact_match;
                $company['is_archived'] = !empty($company['is_archived']);
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
     * Advanced similarity calculation
     * Combines multiple algorithms for better short-string matching
     * 
     * @param string $str1 First string (normalized)
     * @param string $str2 Second string (normalized)
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculate_similarity_advanced($str1, $str2) {
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        // Exact match
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // 1. similar_text percentage
        similar_text($str1, $str2, $similar_percent);
        $similar_score = $similar_percent / 100;
        
        // 2. Levenshtein distance (good for typos and small differences)
        $max_len = max(mb_strlen($str1), mb_strlen($str2));
        if ($max_len > 0 && $max_len <= 255) { // levenshtein has length limit
            $lev_distance = levenshtein($str1, $str2);
            $lev_score = 1 - ($lev_distance / $max_len);
        } else {
            $lev_score = $similar_score;
        }
        
        // 3. Common prefix bonus (important for "A1" vs "A2" style names)
        $prefix_len = 0;
        $min_len = min(mb_strlen($str1), mb_strlen($str2));
        for ($i = 0; $i < $min_len; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefix_len++;
            } else {
                break;
            }
        }
        $prefix_score = $prefix_len / $max_len;
        
        // Weighted combination
        // - 40% similar_text
        // - 40% levenshtein
        // - 20% common prefix bonus
        $combined = ($similar_score * 0.4) + ($lev_score * 0.4) + ($prefix_score * 0.2);
        
        return min(1.0, $combined);
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