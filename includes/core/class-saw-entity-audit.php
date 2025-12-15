<?php
/**
 * Universal Entity Audit System v3.0
 * 
 * Provides comprehensive audit logging with source detection (admin/invitation/terminal/system),
 * support for long texts, files, relations, and translations.
 * 
 * @package     SAW_Visitors
 * @subpackage  Core
 * @version     3.0.0
 */
if (!defined('ABSPATH')) exit;

class SAW_Entity_Audit {
    
    const SOURCE_ADMIN = 'admin';
    const SOURCE_INVITATION = 'invitation';
    const SOURCE_TERMINAL = 'terminal';
    const SOURCE_SYSTEM = 'system';
    
    protected $entity_type;
    protected $entity_id;
    protected $source;
    protected $source_context = [];
    protected $visitor_context = [];
    
    /**
     * Factory: Create instance for entity
     * 
     * @param string $entity_type
     * @param int $entity_id
     * @return SAW_Entity_Audit
     */
    public static function for_entity($entity_type, $entity_id) {
        $instance = new self($entity_type, $entity_id);
        // Don't detect source immediately - wait until actually logging
        // This avoids issues if class is loaded before WordPress is fully initialized
        return $instance;
    }
    
    /**
     * Factory: Create instance with explicit source
     * 
     * @param string $entity_type
     * @param int $entity_id
     * @param string $source
     * @param array $context Optional context data
     * @return SAW_Entity_Audit
     */
    public static function for_entity_from($entity_type, $entity_id, $source, $context = []) {
        $instance = new self($entity_type, $entity_id);
        $instance->set_source($source, $context);
        return $instance;
    }
    
    /**
     * Constructor
     * 
     * @param string $entity_type
     * @param int $entity_id
     */
    protected function __construct($entity_type, $entity_id) {
        $this->entity_type = sanitize_text_field($entity_type);
        $this->entity_id = absint($entity_id);
    }
    
    /**
     * Set source explicitly
     * 
     * @param string $source
     * @param array $context
     * @return $this
     */
    public function set_source($source, $context = []) {
        $this->source = sanitize_text_field($source);
        $this->source_context = $context;
        return $this;
    }
    
    /**
     * Set visitor context (for terminal source)
     * 
     * @param int $visitor_id
     * @param string $visitor_name
     * @param string|null $visitor_email
     * @return $this
     */
    public function set_visitor_context($visitor_id, $visitor_name, $visitor_email = null) {
        $this->visitor_context = [
            'visitor_id' => absint($visitor_id),
            'visitor_name' => sanitize_text_field($visitor_name),
            'visitor_email' => $visitor_email ? sanitize_email($visitor_email) : null,
        ];
        return $this;
    }
    
    /**
     * Detect source automatically
     * Priority: Invitation > Terminal (only if not admin context) > Admin > System
     * 
     * IMPORTANT: If user is logged in and making changes in admin, it's always 'admin',
     * even if terminal_flow exists in session (terminal_flow might be leftover from previous session)
     */
    protected function detect_source() {
        // Check if user is logged in (admin) - this takes priority for admin changes
        // Terminal flow should only be used for actual terminal operations, not admin changes
        $is_admin_context = is_user_logged_in() && (is_admin() || (defined('DOING_AJAX') && DOING_AJAX && !empty($_POST['_ajax_sidebar_submit'])));
        
        // Check invitation flow (highest priority - explicit invitation context)
        if (class_exists('SAW_Session_Manager')) {
            $session = SAW_Session_Manager::instance();
            $invitation_flow = $session->get('invitation_flow', []);
            if (!empty($invitation_flow['visit_id'])) {
                $this->source = self::SOURCE_INVITATION;
                $visit_id = absint($invitation_flow['visit_id']);
                $this->source_context = $this->get_invitation_context($visit_id);
                return;
            }
        }
        
        // Check terminal flow (only if NOT admin context)
        // Terminal should only be used for actual terminal check-in/out operations
        if (!$is_admin_context && class_exists('SAW_Session_Manager')) {
            $session = SAW_Session_Manager::instance();
            $terminal_flow = $session->get('terminal_flow', []);
            if (!empty($terminal_flow['visit_id'])) {
                $this->source = self::SOURCE_TERMINAL;
                $branch_id = $terminal_flow['branch_id'] ?? null;
                $this->source_context = [
                    'terminal_id' => null, // Not stored in session
                    'terminal_name' => $this->get_branch_name($branch_id),
                    'branch_id' => $branch_id,
                ];
                
                // Merge visitor context if set
                if (!empty($this->visitor_context)) {
                    $this->source_context = array_merge($this->source_context, $this->visitor_context);
                }
                
                // If user is logged in, also include user info (terminal operations can have logged-in users)
                if (is_user_logged_in()) {
                    $user = wp_get_current_user();
                    $this->source_context['user_email'] = $user->user_email;
                    $this->source_context['user_name'] = $user->display_name ?: $user->user_login;
                }
                
                return;
            }
        }
        
        // Admin context (logged in user making changes in admin)
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $this->source = self::SOURCE_ADMIN;
            $this->source_context = [
                'user_email' => $user->user_email,
                'user_name' => $user->display_name ?: $user->user_login,
                'user_id' => $user->ID,
            ];
            return;
        }
        
        // Default to system
        $this->source = self::SOURCE_SYSTEM;
        $this->source_context = [];
    }
    
    /**
     * Get invitation context from visit
     * 
     * @param int $visit_id
     * @return array
     */
    protected function get_invitation_context($visit_id) {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT invitation_email, company_id FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_visits',
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return [];
        }
        
        $context = [
            'invitation_email' => $visit['invitation_email'] ?? null,
        ];
        
        // Get company name if company_id exists
        if (!empty($visit['company_id'])) {
            $company = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_companies',
                absint($visit['company_id'])
            ));
            if ($company) {
                $context['company_name'] = $company;
            }
        }
        
        return $context;
    }
    
    /**
     * Get branch name from branch_id
     * 
     * @param int|null $branch_id
     * @return string
     */
    protected function get_branch_name($branch_id) {
        if (!$branch_id) {
            return 'Terminál';
        }
        
        global $wpdb;
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_branches',
            absint($branch_id)
        ));
        
        return $name ?: 'Terminál';
    }
    
    /**
     * Log general change
     * 
     * @param array $old_values
     * @param array $new_values
     * @param array $options
     * @return int|false
     */
    public function log_change($old_values, $new_values, $options = []) {
        $config = $this->get_entity_config();
        if (isset($config['audit']['enabled']) && !$config['audit']['enabled']) {
            return false;
        }
        
        // Determine action first
        $action = $options['action'] ?? 'updated';
        $is_create = ($action === 'created') || (empty($old_values) && empty($options['action']));
        
        if ($is_create) {
            $action = 'created';
        } else {
            $action = 'updated';
        }
        
        // For create action, don't log changed_fields (just log that entity was created)
        if ($is_create) {
            $details = [
                'source' => $this->source,
                'source_context' => $this->source_context,
            ];
            return $this->write_log($action, $details);
        }
        
        // For update action, calculate and log actual changes
        $changed_fields = $this->calculate_changes($old_values, $new_values, $config);
        
        // Process long text fields
        $long_text_changes = [];
        if (!empty($config['audit']['long_text_fields'])) {
            foreach ($config['audit']['long_text_fields'] as $field) {
                if (isset($changed_fields[$field])) {
                    $long_text_changes[$field] = $this->process_long_text_change(
                        $changed_fields[$field]['old'] ?? null,
                        $changed_fields[$field]['new'] ?? null
                    );
                    // Remove from changed_fields (will be in long_text_changes)
                    unset($changed_fields[$field]);
                }
            }
        }
        
        // Prepare details
        $details = [
            'source' => $this->source,
            'source_context' => $this->source_context,
        ];
        
        if (!empty($changed_fields)) {
            $details['changed_fields'] = $changed_fields;
        }
        
        if (!empty($long_text_changes)) {
            $details['long_text_changes'] = $long_text_changes;
        }
        
        return $this->write_log($action, $details);
    }
    
    /**
     * Log status change
     * 
     * @param string $old_status
     * @param string $new_status
     * @return int|false
     */
    public function log_status_change($old_status, $new_status) {
        $details = [
            'source' => $this->source,
            'source_context' => $this->source_context,
            'changed_fields' => [
                'status' => [
                    'old' => $old_status,
                    'new' => $new_status,
                ]
            ]
        ];
        
        return $this->write_log('status_changed', $details);
    }
    
    /**
     * Log relation change (many-to-many)
     * 
     * @param string $relation_name
     * @param array $old_ids
     * @param array $new_ids
     * @return int|false
     */
    public function log_relation_change($relation_name, $old_ids, $new_ids) {
        $config = $this->get_entity_config();
        if (empty($config['audit']['relations'][$relation_name])) {
            return false;
        }
        
        $relation_config = $config['audit']['relations'][$relation_name];
        
        // Normalize IDs
        $old_ids = array_map('absint', (array)$old_ids);
        $new_ids = array_map('absint', (array)$new_ids);
        $old_ids = array_unique($old_ids);
        $new_ids = array_unique($new_ids);
        
        // Calculate differences
        $added_ids = array_diff($new_ids, $old_ids);
        $removed_ids = array_diff($old_ids, $new_ids);
        
        if (empty($added_ids) && empty($removed_ids)) {
            return false; // No changes
        }
        
        // Resolve related items
        $related_items = [];
        
        if (!empty($added_ids)) {
            $added_items = $this->resolve_related_items($relation_config, $added_ids);
            // DEBUG: Log what we're resolving
            error_log('[SAW_Entity_Audit] Resolved added items for ' . $relation_name . ': ' . print_r($added_items, true));
            foreach ($added_items as $item) {
                $related_items[] = [
                    'type' => $relation_name,
                    'id' => $item['id'],
                    'name' => $item['name'] ?? 'MISSING NAME',
                    'action' => 'added',
                    'extra' => $item['extra'] ?? null,
                ];
            }
        }
        
        if (!empty($removed_ids)) {
            $removed_items = $this->resolve_related_items($relation_config, $removed_ids);
            // DEBUG: Log what we're resolving
            error_log('[SAW_Entity_Audit] Resolved removed items for ' . $relation_name . ': ' . print_r($removed_items, true));
            foreach ($removed_items as $item) {
                $related_items[] = [
                    'type' => $relation_name,
                    'id' => $item['id'],
                    'name' => $item['name'] ?? 'MISSING NAME',
                    'action' => 'removed',
                    'extra' => $item['extra'] ?? null,
                ];
            }
        }
        
        // DEBUG: Log final related_items before saving
        error_log('[SAW_Entity_Audit] Final related_items for ' . $relation_name . ': ' . print_r($related_items, true));
        
        $details = [
            'source' => $this->source,
            'source_context' => $this->source_context,
            'related_items' => $related_items,
        ];
        
        // Determine action label
        $action = 'relation_changed';
        if (!empty($added_ids) && empty($removed_ids)) {
            $action = 'relation_added';
        } elseif (empty($added_ids) && !empty($removed_ids)) {
            $action = 'relation_removed';
        }
        
        return $this->write_log($action, $details);
    }
    
    /**
     * Log translation change
     * 
     * @param string $lang_code
     * @param array $old_values
     * @param array $new_values
     * @param array $config Translation table config
     * @return int|false
     */
    public function log_translation_change($lang_code, $old_values, $new_values, $config = []) {
        $changed_fields = $this->calculate_changes($old_values, $new_values, ['audit' => $config]);
        
        // Process long text fields from translation config
        $long_text_changes = [];
        if (!empty($config['long_text_fields'])) {
            foreach ($config['long_text_fields'] as $field) {
                if (isset($changed_fields[$field])) {
                    $long_text_changes[$field] = $this->process_long_text_change(
                        $changed_fields[$field]['old'] ?? null,
                        $changed_fields[$field]['new'] ?? null
                    );
                    unset($changed_fields[$field]);
                }
            }
        }
        
        $details = [
            'source' => $this->source,
            'source_context' => $this->source_context,
            'translation' => [
                'lang_code' => $lang_code,
            ],
        ];
        
        if (!empty($changed_fields)) {
            $details['changed_fields'] = $changed_fields;
        }
        
        if (!empty($long_text_changes)) {
            $details['long_text_changes'] = $long_text_changes;
        }
        
        return $this->write_log('translation_changed', $details);
    }
    
    /**
     * Log file change (upload/delete)
     * 
     * @param string $action 'added' or 'removed'
     * @param array $files Array of file info (name, size, mime, path - but path will not be stored)
     * @param string $file_category Category name (e.g., 'action_documents', 'invitation_materials')
     * @return int|false
     */
    public function log_file_change($action, $files, $file_category = 'documents') {
        if (empty($files) || !in_array($action, ['added', 'removed'])) {
            return false;
        }
        
        // Prepare file info (without paths/URLs)
        $file_items = [];
        foreach ($files as $file) {
            $file_info = [
                'name' => $file['name'] ?? basename($file['path'] ?? ''),
                'size' => isset($file['size']) ? absint($file['size']) : null,
                'mime' => $file['mime'] ?? null,
            ];
            
            // Determine if image
            if ($this->is_image_file($file_info['name'], $file_info['mime'])) {
                $file_info['is_image'] = true;
            }
            
            $file_items[] = $file_info;
        }
        
        $related_items = [];
        foreach ($file_items as $file) {
            $related_items[] = [
                'type' => 'file',
                'name' => $file['name'],
                'size' => $file['size'],
                'mime' => $file['mime'],
                'is_image' => $file['is_image'] ?? false,
                'action' => $action,
                'category' => $file_category,
            ];
        }
        
        $details = [
            'source' => $this->source,
            'source_context' => $this->source_context,
            'related_items' => $related_items,
        ];
        
        return $this->write_log('file_' . $action, $details);
    }
    
    /**
     * Log related items directly (for cases where items are deleted before resolution)
     * 
     * @param array $related_items Array of related items with type, id, name, action
     * @param string $action Action name (default: 'relation_changed')
     * @return int|false
     */
    public function log_related_items($related_items, $action = 'relation_changed') {
        if (empty($related_items) || !is_array($related_items)) {
            return false;
        }
        
        $details = [
            'source' => $this->get_source(),
            'source_context' => $this->get_source_context(),
            'related_items' => $related_items,
        ];
        
        return $this->write_log($action, $details);
    }
    
    /**
     * Log custom action
     * 
     * @param string $action Action name
     * @param array $extra_data Additional data to include
     * @return int|false
     */
    public function log_custom_action($action, $extra_data = []) {
        $details = [
            'source' => $this->source,
            'source_context' => $this->source_context,
        ];
        
        // Merge extra data (but don't override source/source_context)
        foreach ($extra_data as $key => $value) {
            if (!in_array($key, ['source', 'source_context'])) {
                $details[$key] = $value;
            }
        }
        
        return $this->write_log($action, $details);
    }
    
    /**
     * Calculate changes between old and new values
     * 
     * @param array $old_values
     * @param array $new_values
     * @param array $config Entity config
     * @return array
     */
    protected function calculate_changes($old_values, $new_values, $config) {
        $changed_fields = [];
        $excluded_fields = $config['audit']['excluded_fields'] ?? [];
        $sensitive_fields = $config['audit']['sensitive_fields'] ?? [];
        
        /**
         * Normalize value for comparison
         * Handles type differences (int vs string, null vs empty string)
         */
        $normalize_value = function($value) {
            if ($value === null || $value === '') {
                return null;
            }
            // For numeric values, normalize to string for consistent comparison
            // This prevents false positives like int(3) !== string("3")
            if (is_numeric($value) && !is_float($value)) {
                return (string) $value;
            }
            return $value;
        };
        
        foreach ($new_values as $field => $new_value) {
            // Skip excluded fields
            if (in_array($field, $excluded_fields)) {
                continue;
            }
            
            $old_value = $old_values[$field] ?? null;
            
            // Handle sensitive fields (before normalization)
            if (isset($sensitive_fields[$field])) {
                if ($sensitive_fields[$field] === 'mask') {
                    $old_value = $old_value ? '***' : null;
                    $new_value = $new_value ? '***' : null;
                }
            }
            
            // Normalize values for comparison
            $old_normalized = $normalize_value($old_value);
            $new_normalized = $normalize_value($new_value);
            
            // Compare normalized values
            if ($old_normalized !== $new_normalized) {
                // Store original (non-normalized) values for display
                $changed_fields[$field] = [
                    'old' => $old_value,
                    'new' => $new_value,
                ];
            }
        }
        
        return $changed_fields;
    }
    
    /**
     * Process long text change - return metadata instead of full diff
     * 
     * @param string|null $old_text
     * @param string|null $new_text
     * @return array
     */
    protected function process_long_text_change($old_text, $new_text) {
        $old_length = $old_text ? mb_strlen($old_text, 'UTF-8') : 0;
        $new_length = $new_text ? mb_strlen($new_text, 'UTF-8') : 0;
        
        $change = [
            'old_length' => $old_length,
            'new_length' => $new_length,
            'diff_type' => 'length_change',
        ];
        
        // Add preview (first 200 chars)
        if ($new_text) {
            $change['preview'] = mb_substr($new_text, 0, 200, 'UTF-8');
            if ($new_length > 200) {
                $change['preview'] .= '...';
            }
        } elseif ($old_text) {
            $change['preview'] = '[Odstraněno]';
        }
        
        return $change;
    }
    
    /**
     * Resolve related items (e.g., hosts, OOPP)
     * 
     * @param array $relation_config
     * @param array $ids
     * @return array
     */
    protected function resolve_related_items($relation_config, $ids) {
        if (empty($ids)) {
            return [];
        }
        
        global $wpdb;
        $items = [];
        
        if (!empty($relation_config['resolve'])) {
            $resolve_config = $relation_config['resolve'];
            
            // For many-to-many relations with pivot, IDs are pivot_key values
            // So we need to use the actual entity table, not the pivot table
            // If resolve['table'] is specified, use it; otherwise fall back to relation table
            $entity_table_name = !empty($resolve_config['table']) ? $resolve_config['table'] : $relation_config['table'];
            $resolve_table = $wpdb->prefix . $entity_table_name;
            
            // Build query
            $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
            $ids_for_query = array_map('absint', $ids);
            
            // Handle different resolve types
            if (isset($resolve_config['display'])) {
                // SQL expression for display name (trusted from config)
                // Escape table name with backticks for safety
                $table_name_escaped = '`' . str_replace('`', '``', $resolve_table) . '`';
                $display_expr = $resolve_config['display']; // SQL expression like CONCAT(...)
                
                $sql = "SELECT id, ({$display_expr}) as name";
                
                // Add extra fields if needed
                if (!empty($resolve_config['extra_fields'])) {
                    $extra_fields_parts = [];
                    foreach ($resolve_config['extra_fields'] as $f) {
                        $field_safe = sanitize_key($f);
                        $extra_fields_parts[] = '`' . str_replace('`', '``', $field_safe) . '`';
                    }
                    $sql .= ", " . implode(', ', $extra_fields_parts);
                }
                
                $sql .= " FROM {$table_name_escaped} WHERE id IN ({$ids_placeholder})";
                
                $results = $wpdb->get_results($wpdb->prepare($sql, ...$ids_for_query), ARRAY_A);
                
                foreach ($results as $row) {
                    $item = [
                        'id' => absint($row['id']),
                        'name' => $row['name'] ?? '',
                    ];
                    
                    // Add extra fields
                    if (!empty($resolve_config['extra_fields'])) {
                        $item['extra'] = [];
                        foreach ($resolve_config['extra_fields'] as $field) {
                            if (isset($row[$field])) {
                                $item['extra'][$field] = $row[$field];
                            }
                        }
                    }
                    
                    $items[] = $item;
                }
            } elseif (isset($resolve_config['field'])) {
                // Simple field name
                $field = sanitize_key($resolve_config['field']);
                // Escape table and field names
                $table_name_escaped = '`' . str_replace('`', '``', $resolve_table) . '`';
                $field_escaped = '`' . str_replace('`', '``', $field) . '`';
                
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, {$field_escaped} as name FROM {$table_name_escaped} WHERE id IN ({$ids_placeholder})",
                    ...$ids_for_query
                ), ARRAY_A);
                
                foreach ($results as $row) {
                    $items[] = [
                        'id' => absint($row['id']),
                        'name' => $row['name'] ?? '',
                    ];
                }
            } elseif (isset($resolve_config['translation_table'])) {
                // Translation table (e.g., OOPP)
                // For many-to-many with pivot, IDs are pivot_key values (e.g., oopp_id), not main table IDs
                // So we need to resolve from the actual entity table, not the pivot table
                
                // If resolve table is specified, use it (e.g., saw_oopp), otherwise use relation table
                $entity_table_name = !empty($resolve_config['table']) ? $resolve_config['table'] : $relation_config['table'];
                
                // Remove pivot table prefix if it exists (e.g., saw_visit_action_oopp -> saw_oopp)
                // But if resolve['table'] is specified, use that directly
                $entity_table = $wpdb->prefix . $entity_table_name;
                $translation_table = $wpdb->prefix . $resolve_config['translation_table'];
                $field = sanitize_key($resolve_config['field']);
                
                // Use translation_foreign_key if specified, otherwise use 'id' (default)
                $translation_fk = sanitize_key($resolve_config['translation_foreign_key'] ?? 'id');
                
                // Determine language column name
                // Most translation tables use 'language_code', but some might use 'lang_code'
                // Default to 'language_code' as it's used by OOPP and other translation tables
                $lang_column = $resolve_config['language_column'] ?? 'language_code';
                
                // Escape table and field names
                $entity_table_escaped = '`' . str_replace('`', '``', $entity_table) . '`';
                $translation_table_escaped = '`' . str_replace('`', '``', $translation_table) . '`';
                $field_escaped = '`' . str_replace('`', '``', $field) . '`';
                $translation_fk_escaped = '`' . str_replace('`', '``', $translation_fk) . '`';
                $lang_column_escaped = '`' . str_replace('`', '``', $lang_column) . '`';
                
                // Get current language or default to 'cs'
                $lang = 'cs';
                if (class_exists('SAW_Component_Language_Switcher')) {
                    $lang = SAW_Component_Language_Switcher::get_user_language();
                }
                
                // Use the same pattern as in visits/model.php and detail-modal-template.php
                // Try current language first, then fallback to 'cs', then any language
                $lang = 'cs';
                if (class_exists('SAW_Component_Language_Switcher')) {
                    $lang = SAW_Component_Language_Switcher::get_user_language();
                }
                
                // First try with current language
                $sql = "SELECT o.id, t.{$field_escaped} as name
                 FROM {$entity_table_escaped} o
                 LEFT JOIN {$translation_table_escaped} t ON o.id = t.{$translation_fk_escaped} AND t.{$lang_column_escaped} = %s
                 WHERE o.id IN ({$ids_placeholder})";
                
                $results = $wpdb->get_results($wpdb->prepare($sql, $lang, ...$ids_for_query), ARRAY_A);
                
                // Process results - collect IDs with names
                $name_map = [];
                $found_ids = [];
                foreach ($results as $row) {
                    $id = absint($row['id']);
                    $name = $row['name'] ?? null;
                    if (!empty($name) && trim($name) !== '') {
                        $name_map[$id] = trim($name);
                        $found_ids[] = $id;
                    }
                }
                
                // For missing IDs, try fallback to 'cs'
                $missing_ids = array_diff($ids_for_query, $found_ids);
                if (!empty($missing_ids) && $lang !== 'cs') {
                    $missing_placeholder = implode(',', array_fill(0, count($missing_ids), '%d'));
                    $sql_cs = "SELECT o.id, t.{$field_escaped} as name
                     FROM {$entity_table_escaped} o
                     LEFT JOIN {$translation_table_escaped} t ON o.id = t.{$translation_fk_escaped} AND t.{$lang_column_escaped} = 'cs'
                     WHERE o.id IN ({$missing_placeholder})";
                    $results_cs = $wpdb->get_results($wpdb->prepare($sql_cs, ...$missing_ids), ARRAY_A);
                    foreach ($results_cs as $row) {
                        $id = absint($row['id']);
                        $name = $row['name'] ?? null;
                        if (!empty($name) && trim($name) !== '' && !isset($name_map[$id])) {
                            $name_map[$id] = trim($name);
                            $found_ids[] = $id;
                        }
                    }
                }
                
                // For still missing names, try any language
                $still_missing = array_diff($ids_for_query, $found_ids);
                if (!empty($still_missing)) {
                    foreach ($still_missing as $id) {
                        $sql_any = "SELECT t.{$field_escaped} as name
                         FROM {$translation_table_escaped} t
                         WHERE t.{$translation_fk_escaped} = %d
                         LIMIT 1";
                        $result_any = $wpdb->get_row($wpdb->prepare($sql_any, $id), ARRAY_A);
                        if ($result_any && !empty($result_any['name']) && trim($result_any['name']) !== '') {
                            $name_map[$id] = trim($result_any['name']);
                        }
                    }
                }
                
                // Build items array with all IDs - use fallback for missing names
                foreach ($ids_for_query as $id) {
                    $name = $name_map[$id] ?? null;
                    // Fallback if name is still empty - ensure we always have a name
                    if (empty($name) || trim($name) === '') {
                        // Try one more time with direct query to translation table
                        $direct_result = $wpdb->get_var($wpdb->prepare(
                            "SELECT {$field_escaped} FROM {$translation_table_escaped} WHERE {$translation_fk_escaped} = %d LIMIT 1",
                            $id
                        ));
                        if (!empty($direct_result) && trim($direct_result) !== '') {
                            $name = trim($direct_result);
                        } else {
                            $name = 'OOPP #' . absint($id);
                        }
                    }
                    $items[] = [
                        'id' => absint($id),
                        'name' => $name,
                    ];
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Check if file is an image
     * 
     * @param string $filename
     * @param string|null $mime
     * @return bool
     */
    protected function is_image_file($filename, $mime = null) {
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $image_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp'];
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $image_extensions)) {
            return true;
        }
        
        if ($mime && in_array(strtolower($mime), $image_mimes)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get source
     * 
     * @return string
     */
    public function get_source() {
        if (empty($this->source)) {
            $this->detect_source();
        }
        return $this->source ?? self::SOURCE_SYSTEM;
    }
    
    /**
     * Get source context
     * 
     * @return array
     */
    public function get_source_context() {
        if (empty($this->source)) {
            $this->detect_source();
        }
        return $this->source_context ?? [];
    }
    
    /**
     * Get entity config
     * 
     * @return array
     */
    protected function get_entity_config() {
        // Load module config
        $config_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$this->entity_type}/config.php";
        
        // Handle potential missing trailing slash in constant
        if (!file_exists($config_file) && defined('SAW_VISITORS_PLUGIN_DIR')) {
            $config_file = rtrim(SAW_VISITORS_PLUGIN_DIR, '/\\') . "/includes/modules/{$this->entity_type}/config.php";
        }
        
        if (file_exists($config_file)) {
            $config = include $config_file;
            return is_array($config) ? $config : [];
        }
        
        return [];
    }
    
    /**
     * Write log to database using SAW_Audit
     * 
     * @param string $action
     * @param array $details
     * @return int|false
     */
    protected function write_log($action, $details) {
        if (!class_exists('SAW_Audit')) {
            return false;
        }
        
        // Detect source lazily (only when actually logging)
        // This avoids issues if class is loaded before WordPress is fully initialized
        if (empty($this->source)) {
            $this->detect_source();
        }
        
        // Ensure source and source_context are set
        if (empty($details['source'])) {
            $details['source'] = $this->source ?? self::SOURCE_SYSTEM;
        }
        
        if (empty($details['source_context'])) {
            $details['source_context'] = $this->source_context;
        }
        
        // Get customer_id and branch_id from context
        $customer_id = null;
        $branch_id = null;
        
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
            $branch_id = SAW_Context::get_branch_id();
        }
        
        // For terminal source, use branch_id from source_context if available
        if ($this->source === self::SOURCE_TERMINAL && !empty($this->source_context['branch_id'])) {
            $branch_id = $this->source_context['branch_id'];
        }
        
        // For visits entity, try to get from entity itself
        if ($this->entity_type === 'visits' && $this->entity_id) {
            global $wpdb;
            $visit = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id, branch_id FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_visits',
                $this->entity_id
            ), ARRAY_A);
            
            if ($visit) {
                $customer_id = $visit['customer_id'] ?? $customer_id;
                $branch_id = $visit['branch_id'] ?? $branch_id;
            }
        }
        
        // log_change() has logic that if 'changed_fields' is provided separately,
        // it creates details JSON only with changed_fields (losing source, source_context, etc.)
        // Solution: Don't pass 'changed_fields' separately - include everything in 'details'
        // as JSON string. log_change() will use details directly if changed_fields is not provided.
        
        // Serialize complete details to JSON
        $details_json = wp_json_encode($details);
        
        // DEBUG: Log what we're saving
        error_log('[SAW_Entity_Audit] Writing log - Action: ' . $action . ', Entity: ' . $this->entity_type . ' #' . $this->entity_id);
        error_log('[SAW_Entity_Audit] Details JSON: ' . $details_json);
        if (!empty($details['related_items'])) {
            error_log('[SAW_Entity_Audit] Related items in details: ' . print_r($details['related_items'], true));
        }
        
        // Build log data
        $log_data = [
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'action' => $action,
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
            'details' => $details_json, // Pass as JSON string - log_change() will use it directly
        ];
        
        // DON'T pass 'changed_fields' separately - it's already in details
        // If we pass it separately, log_change() will override details and lose other data
        
        $result = SAW_Audit::log_change($log_data);
        
        // DEBUG: Log result and verify what was saved
        error_log('[SAW_Entity_Audit] Log result: ' . ($result ? 'SUCCESS (ID: ' . $result . ')' : 'FAILED'));
        if ($result) {
            global $wpdb;
            $saved = $wpdb->get_row($wpdb->prepare(
                "SELECT details FROM {$wpdb->prefix}saw_audit_log WHERE id = %d",
                $result
            ), ARRAY_A);
            if ($saved) {
                error_log('[SAW_Entity_Audit] Saved details in DB: ' . $saved['details']);
            }
        }
        
        return $result;
    }
}

