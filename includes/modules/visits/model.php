<?php
/**
 * Visits Module Model
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.6.0 - PIN/Token System Revision
 * 
 * Changelog:
 * - 3.6.0 (2025-12-12): PIN/Token System Revision
 *                        - Added calculate_expiry() and get_effective_end_date() helper methods
 *                        - Added sync_expiration_dates() for automatic PIN+Token sync
 *                        - Modified update() to auto-sync expirations on date change
 *                        - Modified generate_pin() to scope uniqueness to customer_id
 *                        - Unified expiry calculation across all methods
 * - 3.5.0 (2025-12-08): Added check_and_complete_visit(), complete_visit(), 
 *                        reopen_visit(), get_visit_info_for_checkout(), 
 *                        update_pin_expiration() for Checkout Confirmation System v2
 *                        Updated generate_pin() to prioritize planned_date_to
 * - 3.4.0: Added find_or_create_company() method for terminal
 */

if (!defined('ABSPATH')) exit;

class SAW_Module_Visits_Model extends SAW_Base_Model 
{
    public function __construct($config) {
        global $wpdb;
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = 300;
    }
    
    public function validate($data, $id = 0) {
        $errors = array();
        
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer ID is required';
        }
        
        if (empty($data['branch_id'])) {
            $errors['branch_id'] = 'Branch is required';
        }
        
        if (!empty($data['invitation_email']) && !is_email($data['invitation_email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validation failed', $errors);
    }
    
    public function get_by_id($id, $bypass_cache = false) {
        $item = parent::get_by_id($id, $bypass_cache);
        
        if ($item) {
            $item['status_label'] = $this->get_status_label($item['status'] ?? 'pending');
            
            if (!empty($item['created_at'])) {
                $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
            }
        }
        
        return $item;
    }
    
    public function get_all($filters = array()) {
        global $wpdb;
        
        $customer_id = SAW_Context::get_customer_id();
        if (!$customer_id) {
            return array('items' => array(), 'total' => 0);
        }
        
        $page = isset($filters['page']) ? intval($filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 20;
        // ⭐ KRITICKÁ OPRAVA: Podpora vlastního offsetu pro infinite scroll
        if (isset($filters['offset']) && $filters['offset'] >= 0) {
            $offset = intval($filters['offset']);
        } else {
            $offset = ($page - 1) * $per_page;
        }
        
        $where = array("v.customer_id = %d");
        $where_values = array($customer_id);
        
        $branch_id = SAW_Context::get_branch_id();
        if ($branch_id) {
            $where[] = "v.branch_id = %d";
            $where_values[] = $branch_id;
        }
        
        // ⭐ NOVÉ: Přidat podporu pro status filtr (pro tabs)
        if (!empty($filters['status'])) {
            $where[] = "v.status = %s";
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['risks_status'])) {
            // Use COALESCE to handle NULL values (treat NULL as 'pending')
            $where[] = "COALESCE(v.risks_status, 'pending') = %s";
            $where_values[] = $filters['risks_status'];
        }
        
        if (!empty($filters['visit_type'])) {
            $where[] = "v.visit_type = %s";
            $where_values[] = $filters['visit_type'];
        }
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $search_conditions = array();
            $search_params = array();
            
            // If search is numeric, try exact ID match
            if (is_numeric($search)) {
                $search_conditions[] = "v.id = %d";
                $search_params[] = intval($search);
            }
            
            // Always search in text fields
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            
            // Search in company name
            $search_conditions[] = "c.name LIKE %s";
            $search_params[] = $search_term;
            
            // Search in visitor names from visitors table (via JOIN)
            $search_conditions[] = "vis.first_name LIKE %s";
            $search_conditions[] = "vis.last_name LIKE %s";
            $search_conditions[] = "CONCAT(vis.first_name, ' ', vis.last_name) LIKE %s";
            $search_params[] = $search_term;
            $search_params[] = $search_term;
            $search_params[] = $search_term;
            
            // Search in first_visitor_name subquery (for physical persons without company_id)
            // Use EXISTS for better performance - matches any visitor name for this visit
            $search_conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id AND (first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s))";
            $search_params[] = $search_term;
            $search_params[] = $search_term;
            $search_params[] = $search_term;
            
            $where[] = "(" . implode(" OR ", $search_conditions) . ")";
            $where_values = array_merge($where_values, $search_params);
        }
        
        $where_sql = implode(' AND ', $where);
        
        $orderby = isset($filters['orderby']) ? $filters['orderby'] : 'id';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Mapování virtuálních sloupců na skutečné databázové sloupce nebo aliasy
        $orderby_map = array(
            'company_person' => 'COALESCE(c.name, (SELECT CONCAT(first_name, \' \', last_name) FROM ' . $wpdb->prefix . 'saw_visitors WHERE visit_id = v.id ORDER BY id ASC LIMIT 1))',
            'risks_status' => 'risks_status', // alias z SELECT
            'visit_type' => 'v.visit_type',
            'status' => 'v.status',
            'planned_date_from' => 'v.planned_date_from',
        );
        
        // Použít mapování pokud existuje, jinak použít přímo orderby s prefixem v.
        if (isset($orderby_map[$orderby])) {
            $orderby_column = $orderby_map[$orderby];
        } else {
            $orderby_column = 'v.' . $orderby;
        }
        
        // Build count query - must match the same WHERE conditions as main query
        $count_sql = "SELECT COUNT(DISTINCT v.id) FROM {$wpdb->prefix}saw_visits v
                      LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                      LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
                      WHERE {$where_sql}";
        
        $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$where_values));
        
        $sql = "SELECT v.*, 
                       c.name as company_name,
                       b.name as branch_name,
                       COALESCE(v.risks_status, 'pending') as risks_status,
                       (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id) as visitor_count,
                       (SELECT CONCAT(first_name, ' ', last_name) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id ORDER BY id ASC LIMIT 1) as first_visitor_name
                FROM {$wpdb->prefix}saw_visits v
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
                WHERE {$where_sql}
                GROUP BY v.id
                ORDER BY {$orderby_column} {$order}
                LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$where_values), ARRAY_A);
    
        foreach ($items as &$item) {
            if (!empty($item['created_at'])) {
                $item['created_at'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
            }
        }
        
        $items = $this->apply_virtual_columns($items);
        
        // ⭐ FIX: Automatická aktualizace risks_status pro záznamy, které potřebují změnu
        $this->auto_update_risks_status($items);
        
        return array(
            'items' => $items,
            'total' => intval($total),
        );
    }
    
    /**
     * Calculate risks status for a visit
     * 
     * Determines risks_status based on:
     * - Whether risks are uploaded (risks_text, risks_document_path, or invitation_materials)
     * - Visit date relative to today
     * 
     * @since 3.8.0
     * @param array $visit Visit data array
     * @return string 'pending', 'completed', or 'missing'
     */
    public function calculate_risks_status($visit) {
        global $wpdb;
        
        $visit_id = intval($visit['id'] ?? 0);
        if (!$visit_id) {
            return 'pending';
        }
        
        // Check if risks are uploaded
        $has_risks = false;
        
        // Check risks_text and risks_document_path in visits table
        if (!empty($visit['risks_text']) || !empty($visit['risks_document_path'])) {
            $has_risks = true;
        } else {
            // Check invitation_materials table
            $has_materials = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_invitation_materials 
                 WHERE visit_id = %d AND material_type = 'text'",
                $visit_id
            ));
            $has_risks = ($has_materials > 0);
        }
        
        // If risks are uploaded, status is always 'completed'
        if ($has_risks) {
            return 'completed';
        }
        
        // If no risks, determine based on visit date
        $visit_date = $visit['planned_date_from'] ?? null;
        if (!$visit_date) {
            return 'pending'; // No date = waiting
        }
        
        $today = current_time('Y-m-d');
        $visit_date_obj = new DateTime($visit_date);
        $today_obj = new DateTime($today);
        
        if ($visit_date_obj > $today_obj) {
            return 'pending'; // Before visit date = waiting
        } else {
            return 'missing'; // On or after visit date, but no risks = missing
        }
    }
    
    /**
     * Update risks status for a visit
     * 
     * @since 3.8.0
     * @param int $visit_id Visit ID
     * @return bool|WP_Error True on success
     */
    public function update_risks_status($visit_id) {
        global $wpdb;
        
        $visit = $this->get_by_id($visit_id);
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Visit not found');
        }
        
        $status = $this->calculate_risks_status($visit);
        
        $result = $wpdb->update(
            $this->table,
            array('risks_status' => $status),
            array('id' => $visit_id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Auto-update risks_status for items that need it
     * 
     * Checks each item and updates risks_status if needed based on current date.
     * Only updates records that actually need a change (pending -> missing for past visits).
     * 
     * @since 3.8.0
     * @param array $items Array of visit items (by reference)
     * @return void
     */
    private function auto_update_risks_status(&$items) {
        if (empty($items)) {
            return;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        $today_obj = new DateTime($today);
        $needs_update = array();
        
        // Identify items that need status update
        foreach ($items as &$item) {
            $current_status = $item['risks_status'] ?? 'pending';
            $visit_date = $item['planned_date_from'] ?? null;
            
            // Skip if already completed or missing
            if ($current_status === 'completed') {
                continue;
            }
            
            // Check if visit date has passed
            if ($visit_date) {
                $visit_date_obj = new DateTime($visit_date);
                
                // If visit date <= today and status is pending, should be missing (if no risks)
                if ($visit_date_obj <= $today_obj && $current_status === 'pending') {
                    // Double-check if risks exist
                    $has_risks = !empty($item['risks_text']) || !empty($item['risks_document_path']);
                    
                    if (!$has_risks) {
                        // Check invitation_materials
                        $has_materials = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_invitation_materials 
                             WHERE visit_id = %d AND material_type = 'text'",
                            intval($item['id'])
                        ));
                        
                        if (!$has_materials) {
                            // Needs update: pending -> missing
                            $needs_update[intval($item['id'])] = 'missing';
                            $item['risks_status'] = 'missing'; // Update in-memory too
                        }
                    }
                }
            }
        }
        
        // Batch update if needed
        if (!empty($needs_update)) {
            $this->update_risks_status_batch($needs_update);
        }
    }
    
    /**
     * Batch update risks_status for multiple visits
     * 
     * @since 3.8.0
     * @param array $updates Array of [visit_id => new_status]
     * @return void
     */
    private function update_risks_status_batch($updates) {
        if (empty($updates)) {
            return;
        }
        
        global $wpdb;
        
        // Group by status for efficient batch update
        $by_status = array();
        foreach ($updates as $visit_id => $status) {
            if (!isset($by_status[$status])) {
                $by_status[$status] = array();
            }
            $by_status[$status][] = intval($visit_id);
        }
        
        // Update each status group
        foreach ($by_status as $status => $visit_ids) {
            if (empty($visit_ids)) {
                continue;
            }
            
            $placeholders = implode(',', array_fill(0, count($visit_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table} 
                 SET risks_status = %s 
                 WHERE id IN ({$placeholders})",
                array_merge(array($status), $visit_ids)
            ));
        }
    }
    
    /**
     * Get currently present visitors for dashboard widget
     * 
     * @since 3.3.0
     * @param int $branch_id Branch ID
     * @return array List of currently present visitors
     */
    public function get_currently_present($branch_id) {
        global $wpdb;
        
        $sql = "SELECT 
                    vis.id as visitor_id,
                    CONCAT(vis.first_name, ' ', vis.last_name) as visitor_name,
                    vis.phone,
                    c.name as company_name,
                    log.checked_in_at as today_checkin,
                    log.log_date,
                    TIMESTAMPDIFF(MINUTE, log.checked_in_at, NOW()) as minutes_inside
                FROM {$wpdb->prefix}saw_visit_daily_logs log
                INNER JOIN {$wpdb->prefix}saw_visitors vis ON log.visitor_id = vis.id
                INNER JOIN {$wpdb->prefix}saw_visits v ON log.visit_id = v.id
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                WHERE v.branch_id = %d
                  AND log.checked_in_at IS NOT NULL
                  AND log.checked_out_at IS NULL
                ORDER BY log.checked_in_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $branch_id), ARRAY_A);
    }
    
    /**
     * Find or create company by name
     * 
     * Used by terminal for walk-in registration.
     * Searches for existing company by normalized name, creates new if not found.
     * 
     * @since 3.4.0
     * @param int $branch_id Branch ID
     * @param string $company_name Company name
     * @param int|null $customer_id Customer ID (optional, for terminal - if not provided, uses SAW_Context)
     * @return int|WP_Error Company ID or WP_Error on failure
     */
    public function find_or_create_company($branch_id, $company_name, $customer_id = null) {
        global $wpdb;
        
        // Allow passing customer_id directly (for terminal)
        if (!$customer_id) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            return new WP_Error('no_customer', 'Customer context required');
        }
        
        // Normalize name for search (remove spaces, common suffixes)
        $normalized = strtolower(str_replace([' ', 's.r.o.', 'a.s.', '.', ','], '', $company_name));
        
        // Search for similar company
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_companies 
             WHERE customer_id = %d 
             AND branch_id = %d
             AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, ' ', ''), 's.r.o.', ''), 'a.s.', ''), '.', ''), ',', '')) LIKE %s
             LIMIT 1",
            $customer_id,
            $branch_id,
            '%' . $wpdb->esc_like($normalized) . '%'
        ));
        
        if ($existing) {
            SAW_Logger::debug("[SAW Visits Model] Found existing company ID: {$existing}");
            return intval($existing);
        }
        
        // Create new company
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_companies',
            [
                'customer_id' => $customer_id,
                'branch_id' => $branch_id,
                'name' => sanitize_text_field($company_name),
                'ico' => null, // Walk-in doesn't provide ICO
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        
        if (!$result) {
            SAW_Logger::error("[SAW Visits Model] Failed to create company: " . $wpdb->last_error);
            return new WP_Error('insert_failed', 'Failed to create company: ' . $wpdb->last_error);
        } else {
            $new_id = $wpdb->insert_id;
            SAW_Logger::debug("[SAW Visits Model] Created new company ID: {$new_id}");
            return $new_id;
        }  
    }
    
    /**
     * Create visit with automatic PIN expiry setting
     * 
     * @since 4.8.0
     * @param array $data Visit data
     * @return int|WP_Error Visit ID or error
     */
    public function create($data) {
        global $wpdb;
        
        // Use parent's create method
        $id = parent::create($data);
        
        if (is_wp_error($id)) {
            return $id;
        }
        
        // Automaticky vygenerovat PIN pokud není v datech
        if (!empty($id)) {
            // Zkontroluj, zda už má PIN
            $existing_pin = $wpdb->get_var($wpdb->prepare(
                "SELECT pin_code FROM {$this->table} WHERE id = %d",
                $id
            ));
            
            // Pokud PIN neexistuje, vygeneruj ho
            if (empty($existing_pin)) {
                $pin = $this->generate_pin($id);
                if ($pin) {
                    error_log("[Visits Model] Auto-generated PIN {$pin} for visit #{$id}");
                } else {
                    error_log("[Visits Model] WARNING: Failed to auto-generate PIN for visit #{$id}");
                }
            }
            
            // Nastavit PIN platnost při vytvoření
            $last_schedule_date = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(date) FROM {$wpdb->prefix}saw_visit_schedules WHERE visit_id = %d",
                $id
            ));
            
            if ($last_schedule_date) {
                // PIN platí do: poslední den + 1 den (23:59:59)
                $pin_expires_at = date('Y-m-d 23:59:59', strtotime($last_schedule_date . ' +1 day'));
                
                $wpdb->update(
                    $this->table,
                    ['pin_expires_at' => $pin_expires_at],
                    ['id' => $id],
                    ['%s'],
                    ['%d']
                );
                
                error_log("[Visits Model] PIN expiry set to {$pin_expires_at} for visit #{$id}");
            }
        }
        
        return $id;
    }

    /**
     * Update visit with automatic expiry synchronization
     * 
     * When visit dates change, PIN and Token expiration are automatically
     * recalculated to match the new end date.
     * 
     * @since 3.6.0 - Added auto-sync for token and PIN expiry
     * @param int $id Visit ID
     * @param array $data Update data
     * @return int|WP_Error Updated ID or error
     */
    public function update($id, $data) {
        global $wpdb;
        
        $id = intval($id);
        if (!$id) {
            return new WP_Error('invalid_id', 'Neplatné ID návštěvy');
        }
        
        // Get old data for comparison BEFORE update
        $old_visit = $this->get_by_id($id);
        
        if (!$old_visit) {
            return new WP_Error('not_found', 'Návštěva nenalezena');
        }
        
        // Store old dates for comparison
        $old_date_to = $old_visit['planned_date_to'] ?? null;
        $old_date_from = $old_visit['planned_date_from'] ?? null;
        
        // Perform standard update via parent class
        $result = parent::update($id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check if dates changed
        $new_date_to = $data['planned_date_to'] ?? $old_date_to;
        $new_date_from = $data['planned_date_from'] ?? $old_date_from;
        
        $date_to_changed = ($new_date_to !== $old_date_to);
        $date_from_changed = ($new_date_from !== $old_date_from);
        
        // If any date changed, synchronize expirations
        if ($date_to_changed || $date_from_changed) {
            $this->sync_expiration_dates($id);
            
            error_log(sprintf(
                "[SAW Visits] Dates changed for visit #%d: date_to %s→%s, date_from %s→%s - expirations synced",
                $id,
                $old_date_to ?? 'NULL',
                $new_date_to ?? 'NULL',
                $old_date_from ?? 'NULL',
                $new_date_from ?? 'NULL'
            ));
        }
        
        return $result;
    }

    // ============================================
    // EXPIRATION HELPER METHODS
    // Added: 2025-12-12 (v3.6.0)
    // ============================================

    /**
     * Calculate standard expiry datetime from visit end date
     * 
     * Standard rule: end_date 23:59:59 + 24 hours
     * This ensures the visitor can still check-in the day after the planned end.
     * 
     * @since 3.6.0
     * @param string|null $end_date End date in Y-m-d format
     * @param int $fallback_hours Hours to add if no end_date (default 720 = 30 days)
     * @return string Datetime in Y-m-d H:i:s format
     */
    public function calculate_expiry($end_date = null, $fallback_hours = 720) {
        if (!empty($end_date)) {
            // Parse end_date as DATE in Prague timezone
            // Then set to end of day (23:59:59) and add 24 hours
            try {
                $tz_prague = new DateTimeZone('Europe/Prague');
                
                // Parse the date (assumes it's already in Y-m-d format)
                $end_dt = new DateTime($end_date . ' 23:59:59', $tz_prague);
                
                // Add 24 hours buffer
                $end_dt->modify('+24 hours');
                
                return $end_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Fallback to old method if DateTime fails
                error_log("[SAW Visits] DateTime error in calculate_expiry: " . $e->getMessage());
                return date('Y-m-d H:i:s', strtotime($end_date . ' 23:59:59 +24 hours'));
            }
        }
        
        // Fallback: 30 days from now
        try {
            $tz_prague = new DateTimeZone('Europe/Prague');
            $now = new DateTime('now', $tz_prague);
            $now->modify("+{$fallback_hours} hours");
            return $now->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log("[SAW Visits] DateTime error in calculate_expiry fallback: " . $e->getMessage());
            return date('Y-m-d H:i:s', strtotime("+{$fallback_hours} hours"));
        }
    }

    /**
     * Get effective end date for a visit
     * 
     * Determines the actual end date using priority:
     * 1. planned_date_to (explicit end date)
     * 2. Last date from visit_schedules table
     * 3. planned_date_from (single-day visit)
     * 4. Today (fallback)
     * 
     * @since 3.6.0
     * @param int $visit_id Visit ID
     * @return string Date in Y-m-d format
     */
    public function get_effective_end_date($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return current_time('Y-m-d');
        }
        
        // Get visit dates
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT planned_date_to, planned_date_from FROM {$this->table} WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return current_time('Y-m-d');
        }
        
        // Priority 1: Explicit end date
        if (!empty($visit['planned_date_to'])) {
            return $visit['planned_date_to'];
        }
        
        // Priority 2: Last schedule date
        $last_schedule = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(date) FROM {$wpdb->prefix}saw_visit_schedules WHERE visit_id = %d",
            $visit_id
        ));
        
        if ($last_schedule) {
            return $last_schedule;
        }
        
        // Priority 3: Start date (single-day visit)
        if (!empty($visit['planned_date_from'])) {
            return $visit['planned_date_from'];
        }
        
        // Fallback: Today
        return current_time('Y-m-d');
    }

    /**
     * Synchronize token and PIN expiration with visit dates
     * 
     * Called automatically when visit dates change.
     * Recalculates both PIN and Token expiration based on the effective end date.
     * 
     * @since 3.6.0
     * @param int $visit_id Visit ID
     * @return bool Success
     */
    public function sync_expiration_dates($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return false;
        }
        
        $visit = $this->get_by_id($visit_id);
        if (!$visit) {
            return false;
        }
        
        // Calculate new expiry from effective end date
        $end_date = $this->get_effective_end_date($visit_id);
        $new_expiry = $this->calculate_expiry($end_date);
        
        $update_data = [];
        $update_formats = [];
        $updated_fields = [];
        
        // Update PIN expiry if PIN exists
        if (!empty($visit['pin_code'])) {
            $update_data['pin_expires_at'] = $new_expiry;
            $update_formats[] = '%s';
            $updated_fields[] = 'PIN';
        }
        
        // Update Token expiry if Token exists
        if (!empty($visit['invitation_token'])) {
            $update_data['invitation_token_expires_at'] = $new_expiry;
            $update_formats[] = '%s';
            $updated_fields[] = 'Token';
        }
        
        // Nothing to update
        if (empty($update_data)) {
            error_log("[SAW Visits] sync_expiration_dates: visit #{$visit_id} has no PIN or Token to sync");
            return true;
        }
        
        // Perform update
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $visit_id],
            $update_formats,
            ['%d']
        );
        
        if ($result !== false) {
            error_log(sprintf(
                "[SAW Visits] Synced %s expiration for visit #%d: end_date=%s → expiry=%s",
                implode('+', $updated_fields),
                $visit_id,
                $end_date,
                $new_expiry
            ));
            
            $this->invalidate_cache();
            return true;
        }
        
        error_log("[SAW Visits] Failed to sync expiration for visit #{$visit_id}: " . $wpdb->last_error);
        return false;
    }

    /**
     * Generate unique 6-digit PIN for visit
     * 
     * @since 5.1.0
     * @updated 3.6.0 - PIN uniqueness scoped to customer_id, uses unified expiry calculation
     * @updated 3.5.0 - Added planned_date_to priority for expiration calculation
     * @param int $visit_id Visit ID
     * @return string|false Generated PIN or false on failure
     */
    public function generate_pin($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return false;
        }
        
        // Get customer_id for uniqueness scope
        $customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM {$this->table} WHERE id = %d",
            $visit_id
        ));
        
        if (!$customer_id) {
            error_log("[Visits Model] Cannot generate PIN: visit #{$visit_id} not found");
            return false;
        }
        
        // Generate unique PIN within customer scope
        $pin = '';
        $max_attempts = 10;
        $attempt = 0;
        
        do {
            $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Check uniqueness only within same customer (v3.6.0 change)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} 
                 WHERE pin_code = %s 
                   AND customer_id = %d 
                   AND id != %d",
                $pin,
                $customer_id,
                $visit_id
            ));
            $attempt++;
        } while ($exists && $attempt < $max_attempts);
        
        if ($exists) {
            error_log("[Visits Model] Failed to generate unique PIN after {$max_attempts} attempts for customer #{$customer_id}");
            return false;
        }
        
        // Calculate expiry using unified method (v3.6.0)
        $end_date = $this->get_effective_end_date($visit_id);
        $pin_expires_at = $this->calculate_expiry($end_date);
        
        error_log("[Visits Model] PIN expiry calculated: end_date={$end_date} -> expires={$pin_expires_at}");
        
        // Update visit
        $result = $wpdb->update(
            $this->table,
            [
                'pin_code' => $pin,
                'pin_expires_at' => $pin_expires_at
            ],
            ['id' => $visit_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("[Visits Model] Failed to save PIN for visit #{$visit_id}: " . $wpdb->last_error);
            return false;
        }
        
        error_log("[Visits Model] PIN generated for visit #{$visit_id}: {$pin}, expires: {$pin_expires_at}");
        
        // Invalidate cache
        $this->invalidate_cache();
        
        return $pin;
    }

    /**
     * Get status label in Czech
     * 
     * @param string $status Status code
     * @return string Translated status label
     */
    private function get_status_label($status) {
        $labels = array(
            'draft' => 'Koncept',
            'pending' => 'Čekající',
            'confirmed' => 'Potvrzená',
            'in_progress' => 'Probíhající',
            'completed' => 'Dokončená',
            'cancelled' => 'Zrušená',
        );
        return $labels[$status] ?? $status;
    }
    
    /**
     * Generate unique invitation token
     * 
     * @since 1.0.0
     * @param int $customer_id Customer ID for uniqueness check
     * @return string Unique 64-character hex token
     */
    public function ensure_unique_token($customer_id) {
        global $wpdb;
        
        do {
            $token = bin2hex(random_bytes(32));
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                 WHERE invitation_token = %s AND customer_id = %d",
                $token, $customer_id
            ));
            
        } while ($exists > 0);
        
        return $token;
    }
    
    /**
     * Get invitation materials
     * 
     * @since 1.0.0
     * @param int $visit_id Visit ID
     * @return array List of materials
     */
    public function get_invitation_materials($visit_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d 
             ORDER BY material_type, uploaded_at ASC",
            $visit_id
        ), ARRAY_A);
    }
    
    /**
     * Delete invitation material
     * 
     * @since 1.0.0
     * @param int $material_id Material ID
     * @return bool|int Number of rows deleted or false on error
     */
    public function delete_invitation_material($material_id) {
        global $wpdb;
        
        // Get file path first (pokud je to document)
        $material = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials WHERE id = %d",
            $material_id
        ), ARRAY_A);
        
        if ($material && $material['material_type'] === 'document' && !empty($material['file_path'])) {
            $upload_dir = wp_upload_dir();
            $file = $upload_dir['basedir'] . $material['file_path'];
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        // Delete from DB
        return $wpdb->delete(
            $wpdb->prefix . 'saw_visit_invitation_materials',
            ['id' => $material_id],
            ['%d']
        );
    }

    // ============================================
    // CHECKOUT CONFIRMATION SYSTEM v2 METHODS
    // Added: 2025-12-08
    // ============================================

    /**
     * Check if all visitors checked out (PUBLIC version)
     * 
     * Used by terminal.php to determine if checkout confirmation dialog should be shown.
     * This method DOES NOT auto-complete the visit - it only returns the status.
     * The actual completion decision is made by the user via the terminal dialog.
     * 
     * @since 3.5.0
     * @param int $visit_id Visit ID
     * @return bool True if all visitors are checked out
     */
    public function check_and_complete_visit($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return false;
        }
        
        // Count visitors still checked in (ANY day, not just today - supports overnight visits)
        $checked_in_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT vis.id)
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visit_daily_logs log ON vis.id = log.visitor_id
             WHERE vis.visit_id = %d
             AND log.checked_in_at IS NOT NULL
             AND log.checked_out_at IS NULL",
            $visit_id
        ));
        
        $all_checked_out = ((int)$checked_in_count === 0);
        
        error_log("[SAW Visits Model] check_and_complete_visit: visit #{$visit_id}, checked_in_count={$checked_in_count}, all_out=" . ($all_checked_out ? 'YES' : 'NO'));
        
        // NOTE: Auto-completion logic was REMOVED in v3.5.0
        // The decision to complete or keep the visit active is now made by the user
        // via the checkout confirmation dialog in the terminal.
        // This method now only returns a boolean status.
        
        return $all_checked_out;
    }

    /**
     * Explicitly complete a visit
     * 
     * Called when user confirms visit completion in terminal dialog by clicking
     * "Ukončit návštěvu" button. This explicitly marks the visit as completed.
     * 
     * @since 3.5.0
     * @param int $visit_id Visit ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function complete_visit($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return new WP_Error('invalid_visit_id', 'Neplatné ID návštěvy');
        }
        
        // Get current visit status
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$this->table} WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Návštěva nenalezena');
        }
        
        // Validate status transitions
        if ($visit['status'] === 'completed') {
            // Already completed - not an error, just return success
            error_log("[SAW Visits] Visit #{$visit_id} is already completed");
            return true;
        }
        
        if ($visit['status'] === 'cancelled') {
            return new WP_Error('visit_cancelled', 'Nelze dokončit zrušenou návštěvu');
        }
        
        // Update visit status to completed
        $result = $wpdb->update(
            $this->table,
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $visit_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("[SAW Visits] Failed to complete visit #{$visit_id}: " . $wpdb->last_error);
            return new WP_Error('update_failed', 'Nepodařilo se dokončit návštěvu: ' . $wpdb->last_error);
        }
        
        error_log("[SAW Visits] Visit #{$visit_id} explicitly completed by user via terminal dialog");
        
        // Invalidate cache
        $this->invalidate_cache();
        
        return true;
    }

    /**
     * Reopen a completed visit
     * 
     * Called when a visitor tries to check in after the visit was marked as completed
     * (e.g., they selected "Ukončit návštěvu" but came back the next day).
     * This allows the visit to be reopened if the PIN is still valid.
     * 
     * @since 3.5.0
     * @param int $visit_id Visit ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function reopen_visit($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return new WP_Error('invalid_visit_id', 'Neplatné ID návštěvy');
        }
        
        // Get current visit status and PIN expiration
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, pin_expires_at FROM {$this->table} WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return new WP_Error('visit_not_found', 'Návštěva nenalezena');
        }
        
        // Cannot reopen cancelled visits
        if ($visit['status'] === 'cancelled') {
            return new WP_Error('visit_cancelled', 'Nelze znovu otevřít zrušenou návštěvu');
        }
        
        // If not completed, nothing to reopen
        if ($visit['status'] !== 'completed') {
            error_log("[SAW Visits] Visit #{$visit_id} is not completed (status: {$visit['status']}), no reopen needed");
            return true; // Already open, nothing to do
        }
        
        // Check PIN expiration - cannot reopen if PIN has expired
        if (!empty($visit['pin_expires_at']) && strtotime($visit['pin_expires_at']) < time()) {
            error_log("[SAW Visits] Cannot reopen visit #{$visit_id}: PIN expired at {$visit['pin_expires_at']}");
            return new WP_Error('pin_expired', 'PIN kód vypršel, nelze znovu otevřít návštěvu. Kontaktujte recepci.');
        }
        
        // Reopen the visit
        $result = $wpdb->update(
            $this->table,
            [
                'status' => 'in_progress',
                'completed_at' => null
            ],
            ['id' => $visit_id],
            ['%s', null],
            ['%d']
        );
        
        if ($result === false) {
            error_log("[SAW Visits] Failed to reopen visit #{$visit_id}: " . $wpdb->last_error);
            return new WP_Error('update_failed', 'Nepodařilo se znovu otevřít návštěvu');
        }
        
        error_log("[SAW Visits] Visit #{$visit_id} reopened from completed status (visitor check-in after completion)");
        
        // Invalidate cache
        $this->invalidate_cache();
        
        return true;
    }

    /**
     * Get visit information needed for checkout confirmation dialog
     * 
     * Returns visit data enriched with computed fields needed for the
     * checkout confirmation dialog UI (effective end date, is_last_day flag, etc.)
     * 
     * @since 3.5.0
     * @param int $visit_id Visit ID
     * @return array|null Visit info array or null if not found
     */
    public function get_visit_info_for_checkout($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return null;
        }
        
        // Get visit with company name
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                v.id,
                v.status,
                v.visit_type,
                v.planned_date_from,
                v.planned_date_to,
                v.started_at,
                v.pin_expires_at,
                c.name as company_name
             FROM {$this->table} v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             WHERE v.id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return null;
        }
        
        // Compute effective end date with fallbacks
        // Priority: planned_date_to > planned_date_from > started_at > today
        $today = current_time('Y-m-d');
        
        if (!empty($visit['planned_date_to'])) {
            $end_date = $visit['planned_date_to'];
        } elseif (!empty($visit['planned_date_from'])) {
            $end_date = $visit['planned_date_from'];
        } elseif (!empty($visit['started_at'])) {
            $end_date = date('Y-m-d', strtotime($visit['started_at']));
        } else {
            $end_date = $today;
        }
        
        // Add computed fields for dialog UI
        $visit['effective_end_date'] = $end_date;
        $visit['is_last_day'] = ($end_date === $today);
        $visit['is_past_end_date'] = ($end_date < $today);
        $visit['is_multi_day'] = (!empty($visit['planned_date_from']) && 
                                   !empty($visit['planned_date_to']) && 
                                   $visit['planned_date_from'] !== $visit['planned_date_to']);
        
        return $visit;
    }

    /**
     * Update PIN expiration based on planned_date_to
     * 
     * Called when visit dates are changed via admin interface.
     * Recalculates PIN expiration to be planned_date_to + 24 hours.
     * 
     * @since 3.5.0
     * @deprecated 3.6.0 Use sync_expiration_dates() instead
     * @param int $visit_id Visit ID
     * @return bool True on success, false on failure
     */
    public function update_pin_expiration($visit_id) {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return false;
        }
        
        // Get visit data
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT planned_date_to, planned_date_from, pin_code 
             FROM {$this->table} WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        // No visit or no PIN - nothing to update
        if (!$visit || empty($visit['pin_code'])) {
            return false;
        }
        
        // Use unified helper methods (v3.6.0)
        $end_date = $this->get_effective_end_date($visit_id);
        $new_expiration = $this->calculate_expiry($end_date);
        
        // Update PIN expiration
        $result = $wpdb->update(
            $this->table,
            ['pin_expires_at' => $new_expiration],
            ['id' => $visit_id],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            error_log("[SAW Visits] Updated PIN expiration for visit #{$visit_id}: {$end_date} -> {$new_expiration}");
            $this->invalidate_cache();
            return true;
        }
        
        error_log("[SAW Visits] Failed to update PIN expiration for visit #{$visit_id}: " . $wpdb->last_error);
        return false;
    }
}