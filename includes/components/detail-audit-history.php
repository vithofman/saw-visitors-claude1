<?php
/**
 * Detail Audit History Component
 * 
 * Universal collapsible section showing who created/updated the record and when.
 * Features: Gravatar avatars, timeline design, localStorage persistence, responsive design.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if item has audit info
$has_audit = $item['has_audit_info'] ?? false;

if (!$has_audit) {
    return; // Don't show if no audit info
}

// Get entity name and id for unique localStorage key
$entity_name = $entity ?? 'unknown';
$item_id = $item['id'] ?? uniqid();
$unique_id = 'audit-' . $entity_name . '-' . $item_id;
$storage_key = 'saw_audit_expanded_' . $entity_name . '_' . $item_id;

// Translations
$tr = function($key, $fallback) {
    // Try to get from translations if available
    return $fallback;
};

// Get audit data
$created_by = $item['created_by'] ?? null;
$created_at = $item['created_at_formatted'] ?? null;
$created_at_rel = $item['created_at_relative'] ?? null;

$updated_by = $item['updated_by'] ?? null;
$updated_at = $item['updated_at_formatted'] ?? null;
$updated_at_rel = $item['updated_at_relative'] ?? null;

// Helper function to get Gravatar URL
$get_gravatar = function($email) {
    if (empty($email)) {
        return 'https://www.gravatar.com/avatar/00000000000000000000000000000000?s=40&d=identicon';
    }
    $hash = md5(strtolower(trim($email)));
    return 'https://www.gravatar.com/avatar/' . $hash . '?s=40&d=identicon';
};

// Helper function to format field name to readable label
$format_field_label = function($field_name) {
    // Check if it's a translation field (format: translation_{lang}_{field})
    if (preg_match('/^translation_([a-z]{2})_(.+)$/', $field_name, $matches)) {
        $lang_code = $matches[1];
        $field = $matches[2];
        
        // Get language name
        $lang_names = array(
            'cs' => 'Čeština',
            'en' => 'Angličtina',
            'de' => 'Němčina',
            'sk' => 'Slovenština',
            'pl' => 'Polština',
        );
        $lang_name = $lang_names[$lang_code] ?? strtoupper($lang_code);
        
        // Get field label
        $field_labels = array(
            'name' => 'Název',
            'standards' => 'Normy',
            'risk_description' => 'Popis rizik',
            'protective_properties' => 'Ochranné vlastnosti',
            'usage_instructions' => 'Pokyny pro použití',
        );
        $field_label = $field_labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        
        return $lang_name . ' - ' . $field_label;
    }
    
    $labels = [
        'first_name' => 'Jméno',
        'last_name' => 'Příjmení',
        'name' => 'Název',
        'email' => 'Email',
        'phone' => 'Telefon',
        'company_name' => 'Název firmy',
        'ico' => 'IČO',
        'branch_id' => 'Pobočka',
        'department_id' => 'Oddělení',
        'is_active' => 'Aktivní',
        'status' => 'Stav',
        'visit_type' => 'Typ návštěvy',
        'risk_description' => 'Popis rizik',
        'protective_properties' => 'Ochranné vlastnosti',
        'usage_instructions' => 'Pokyny pro použití',
        'standards' => 'Normy',
        'image_path' => 'Obrázek',
        'created_at' => 'Datum vytvoření',
        'updated_at' => 'Datum aktualizace',
        'started_at' => 'Zahájeno',
        'completed_at' => 'Dokončeno',
        'purpose' => 'Účel návštěvy',
        'notes' => 'Poznámky',
        'invitation_email' => 'Email pro pozvánku',
        'pin_expires_at' => 'Platnost PIN',
        'customer_id' => 'Zákazník',
        'company_id' => 'Firma',
        'visit_id' => 'Návštěva',
        'group_id' => 'Skupina',
        'image_url' => 'Obrázek',
        'is_global' => 'Typ použití',
        'branch_ids' => 'Pobočky',
        'department_ids' => 'Oddělení',
    ];
    
    return $labels[$field_name] ?? ucfirst(str_replace('_', ' ', $field_name));
};

// Helper function to format value for display
$format_value = function($value, $field_name = null) {
    if ($value === null || $value === '') {
        return '<em>(prázdné)</em>';
    }
    
    if (is_bool($value)) {
        return $value ? 'Ano' : 'Ne';
    }
    
    if (is_array($value) || is_object($value)) {
        return '<em>(pole/objekt)</em>';
    }
    
    $str = (string) $value;
    
    // Special handling for image_path field - display image instead of URL
    if ($field_name === 'image_path' && !empty($str)) {
        $upload_dir = wp_upload_dir();
        $image_url = $upload_dir['baseurl'] . '/' . ltrim($str, '/');
        
        // Check if file exists
        $image_path = $upload_dir['basedir'] . '/' . ltrim($str, '/');
        if (file_exists($image_path)) {
            return '<img src="' . esc_url($image_url) . '" alt="Obrázek" class="saw-audit-image" style="max-width: 150px; max-height: 150px; border-radius: 8px; margin-top: 4px; display: block;">';
        } else {
            return '<em>(obrázek nenalezen)</em>';
        }
    }
    
    // Format datetime values
    if (preg_match('/^\d{4}-\d{2}-\d{2}(?:\s+\d{2}:\d{2}:\d{2})?$/', $str)) {
        $timestamp = strtotime($str);
        if ($timestamp !== false) {
            return esc_html(date_i18n('j. n. Y H:i', $timestamp));
        }
    }
    
    // Truncate long text
    if (strlen($str) > 100) {
        return esc_html(substr($str, 0, 100)) . '...';
    }
    
    return esc_html($str);
};

// Check if we have change history
$change_history = $item['change_history'] ?? [];
$has_change_history = !empty($change_history);

// Load branch names for display (cache in global if available)
global $wpdb;
$branch_cache = [];
if ($has_change_history) {
    $branch_ids = array_filter(array_column($change_history, 'branch_id'));
    if (!empty($branch_ids)) {
        $branch_ids = array_unique($branch_ids);
        $placeholders = implode(',', array_fill(0, count($branch_ids), '%d'));
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE id IN ($placeholders)",
            ...$branch_ids
        ), ARRAY_A);
        foreach ($branches as $branch) {
            $branch_cache[$branch['id']] = $branch['name'];
        }
    }
}
?>

<div class="saw-detail-audit-history" id="<?php echo esc_attr($unique_id); ?>" data-storage-key="<?php echo esc_attr($storage_key); ?>">
    <button type="button" class="saw-audit-header" onclick="sawToggleAuditHistory('<?php echo esc_js($unique_id); ?>')">
        <div class="saw-audit-header-left">
            <svg class="saw-audit-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <span class="saw-audit-title"><?php echo esc_html($tr('audit_title', 'Historie změn')); ?></span>
        </div>
        <div class="saw-audit-header-right">
            <?php
            $summary_text = null;
            if ($has_change_history && !empty($change_history)) {
                $latest_change = $change_history[0];
                if (!empty($latest_change['created_at'])) {
                    $latest_date_rel = human_time_diff(strtotime($latest_change['created_at']), current_time('timestamp')) . ' ' . __('před', 'saw-visitors');
                    $summary_text = $tr('audit_last_updated', 'Aktualizováno') . ': ' . $latest_date_rel;
                }
            } elseif ($updated_at_rel) {
                $summary_text = $tr('audit_last_updated', 'Aktualizováno') . ': ' . $updated_at_rel;
            }
            ?>
            <?php if ($summary_text): ?>
                <span class="saw-audit-summary"><?php echo esc_html($summary_text); ?></span>
            <?php endif; ?>
            <span class="saw-audit-toggle-icon" id="audit-icon-<?php echo esc_attr($unique_id); ?>">▼</span>
        </div>
    </button>
    
    <div class="saw-audit-content" id="audit-content-<?php echo esc_attr($unique_id); ?>" style="display: none;">
        <!-- Metadata dates at top (always visible when expanded) -->
        <?php if ($created_at || $updated_at): ?>
        <div class="saw-audit-meta-summary">
            <?php if ($created_at): ?>
            <div class="saw-audit-meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <span class="saw-audit-meta-label"><?php echo esc_html($tr('meta_created', 'Vytvořeno:')); ?></span>
                <span class="saw-audit-meta-value"><?php echo esc_html($created_at); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($updated_at): ?>
            <div class="saw-audit-meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <span class="saw-audit-meta-label"><?php echo esc_html($tr('meta_updated', 'Aktualizováno:')); ?></span>
                <span class="saw-audit-meta-value"><?php echo esc_html($updated_at); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="saw-audit-timeline">
            
            <?php if ($has_change_history): ?>
                <!-- Change History Timeline -->
                <?php foreach ($change_history as $change): ?>
                    <?php
                    $change_action = $change['action'] ?? 'updated';
                    $change_user_email = $change['user_email'] ?? null;
                    $change_user_id = $change['user_id'] ?? null;
                    $change_created_at = $change['created_at'] ?? null;
                    $change_branch_id = $change['branch_id'] ?? null;
                    $change_changed_fields = $change['changed_fields'] ?? [];
                    
                    // Format date
                    $change_date_formatted = null;
                    $change_date_relative = null;
                    if ($change_created_at) {
                        $change_date_formatted = date_i18n('j. n. Y H:i', strtotime($change_created_at));
                        $change_date_relative = human_time_diff(strtotime($change_created_at), current_time('timestamp')) . ' ' . __('před', 'saw-visitors');
                    }
                    
                    // Get branch name
                    $change_branch_name = null;
                    if ($change_branch_id && isset($branch_cache[$change_branch_id])) {
                        $change_branch_name = $branch_cache[$change_branch_id];
                    }
                    ?>
                    
                    <div class="saw-audit-timeline-item saw-audit-item-<?php echo esc_attr($change_action === 'created' ? 'created' : 'updated'); ?>">
                        <div class="saw-audit-timeline-content">
                            <div class="saw-audit-item-header">
                                <div class="saw-audit-timeline-dot">
                                    <?php if ($change_action === 'created'): ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="12" y1="5" x2="12" y2="19"/>
                                            <line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="saw-audit-item-label">
                                    <?php echo esc_html($change_action === 'created' ? $tr('audit_created_by', 'Vytvořil') : $tr('audit_updated_by', 'Aktualizoval')); ?>
                                </div>
                            </div>
                            <div class="saw-audit-item-body">
                                <?php if ($change_user_email): ?>
                                    <img src="<?php echo esc_url($get_gravatar($change_user_email)); ?>" 
                                         alt="" 
                                         class="saw-audit-avatar"
                                         onerror="this.onerror=null; this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?s=40&d=identicon';">
                                    <div class="saw-audit-item-info">
                                        <a href="mailto:<?php echo esc_attr($change_user_email); ?>" class="saw-audit-email">
                                            <?php echo esc_html($change_user_email); ?>
                                        </a>
                                        <?php if ($change_date_formatted): ?>
                                            <div class="saw-audit-item-time">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <polyline points="12 6 12 12 16 14"/>
                                                </svg>
                                                <span><?php echo esc_html($change_date_formatted); ?></span>
                                                <?php if ($change_date_relative): ?>
                                                    <span class="saw-audit-time-relative">(<?php echo esc_html($change_date_relative); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($change_branch_name): ?>
                                            <div class="saw-audit-branch-info">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                                <span><?php echo esc_html($change_branch_name); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Display changed fields if available
                                        if (!empty($change_changed_fields) && is_array($change_changed_fields)): 
                                            // Filter out fields that should not be displayed (internal/system fields)
                                            $hidden_fields = ['id']; // Basic internal fields - customer_id can be useful to show
                                            $display_fields = array();
                                            foreach ($change_changed_fields as $field_name => $field_change) {
                                                if (!in_array($field_name, $hidden_fields)) {
                                                    $display_fields[$field_name] = $field_change;
                                                }
                                            }
                                        ?>
                                            <?php if (!empty($display_fields)): ?>
                                            <div class="saw-audit-field-changes">
                                                <?php foreach ($display_fields as $field_name => $field_change): ?>
                                                    <?php
                                                    $field_label = $format_field_label($field_name);
                                                    $old_value = $field_change['old'] ?? null;
                                                    $new_value = $field_change['new'] ?? null;
                                                    
                                                    // Skip if both values are empty/null for update actions
                                                    if ($change_action !== 'created' && $old_value === null && ($new_value === null || $new_value === '')) {
                                                        continue;
                                                    }
                                                    ?>
                                                    <div class="saw-audit-field-change <?php echo $field_name === 'image_path' ? 'saw-audit-field-image' : ''; ?>">
                                                        <span class="saw-audit-field-label"><?php echo esc_html($field_label); ?>:</span>
                                                        <span class="saw-audit-field-diff">
                                                            <?php if ($change_action === 'created'): ?>
                                                                <span class="saw-audit-value-new"><?php echo $format_value($new_value, $field_name); ?></span>
                                                            <?php else: ?>
                                                                <span class="saw-audit-value-old"><?php echo $format_value($old_value, $field_name); ?></span>
                                                                <?php if ($field_name !== 'image_path'): ?>
                                                                    <span class="saw-audit-diff-arrow">→</span>
                                                                <?php endif; ?>
                                                                <span class="saw-audit-value-new"><?php echo $format_value($new_value, $field_name); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php elseif ($change_action === 'created'): ?>
                                                <!-- For create action, show message if no fields to display -->
                                                <div class="saw-audit-field-changes">
                                                    <div class="saw-audit-field-change">
                                                        <span class="saw-audit-value-new">Záznam byl vytvořen</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($change_action === 'created'): ?>
                                            <!-- For create action without changed_fields, show message -->
                                            <div class="saw-audit-field-changes">
                                                <div class="saw-audit-field-change">
                                                    <span class="saw-audit-value-new">Záznam byl vytvořen</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="saw-audit-item-info">
                                        <span class="saw-audit-unknown"><?php echo esc_html($tr('audit_unknown', 'Neznámý')); ?></span>
                                        <?php if ($change_date_formatted): ?>
                                            <div class="saw-audit-item-time">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <polyline points="12 6 12 12 16 14"/>
                                                </svg>
                                                <span><?php echo esc_html($change_date_formatted); ?></span>
                                                <?php if ($change_date_relative): ?>
                                                    <span class="saw-audit-time-relative">(<?php echo esc_html($change_date_relative); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback: Simple Created/Updated Info -->
                <!-- Created Info -->
                <?php if ($created_by || $created_at): ?>
            <div class="saw-audit-timeline-item saw-audit-item-created">
                <div class="saw-audit-timeline-content">
                    <div class="saw-audit-item-header">
                        <div class="saw-audit-timeline-dot">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </div>
                        <div class="saw-audit-item-label">
                            <?php echo esc_html($tr('audit_created_by', 'Vytvořil')); ?>
                        </div>
                    </div>
                    <div class="saw-audit-item-body">
                        <?php if ($created_by): ?>
                            <img src="<?php echo esc_url($get_gravatar($created_by)); ?>" 
                                 alt="" 
                                 class="saw-audit-avatar"
                                 onerror="this.onerror=null; this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?s=40&d=identicon';">
                            <div class="saw-audit-item-info">
                                <a href="mailto:<?php echo esc_attr($created_by); ?>" class="saw-audit-email">
                                    <?php echo esc_html($created_by); ?>
                                </a>
                                <?php if ($created_at): ?>
                                    <div class="saw-audit-item-time">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <span><?php echo esc_html($created_at); ?></span>
                                        <?php if ($created_at_rel): ?>
                                            <span class="saw-audit-time-relative">(<?php echo esc_html($created_at_rel); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="saw-audit-item-info">
                                <span class="saw-audit-unknown"><?php echo esc_html($tr('audit_unknown', 'Neznámý')); ?></span>
                                <?php if ($created_at): ?>
                                    <div class="saw-audit-item-time">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <span><?php echo esc_html($created_at); ?></span>
                                        <?php if ($created_at_rel): ?>
                                            <span class="saw-audit-time-relative">(<?php echo esc_html($created_at_rel); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Updated Info -->
            <?php if ($updated_by || $updated_at): ?>
            <div class="saw-audit-timeline-item saw-audit-item-updated">
                <div class="saw-audit-timeline-content">
                    <div class="saw-audit-item-header">
                        <div class="saw-audit-timeline-dot">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </div>
                        <div class="saw-audit-item-label">
                            <?php echo esc_html($tr('audit_updated_by', 'Naposledy aktualizoval')); ?>
                        </div>
                    </div>
                    <div class="saw-audit-item-body">
                        <?php if ($updated_by): ?>
                            <img src="<?php echo esc_url($get_gravatar($updated_by)); ?>" 
                                 alt="" 
                                 class="saw-audit-avatar"
                                 onerror="this.onerror=null; this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?s=40&d=identicon';">
                            <div class="saw-audit-item-info">
                                <a href="mailto:<?php echo esc_attr($updated_by); ?>" class="saw-audit-email">
                                    <?php echo esc_html($updated_by); ?>
                                </a>
                                <?php if ($updated_at): ?>
                                    <div class="saw-audit-item-time">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <span><?php echo esc_html($updated_at); ?></span>
                                        <?php if ($updated_at_rel): ?>
                                            <span class="saw-audit-time-relative">(<?php echo esc_html($updated_at_rel); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="saw-audit-item-info">
                                <span class="saw-audit-unknown"><?php echo esc_html($tr('audit_unknown', 'Neznámý')); ?></span>
                                <?php if ($updated_at): ?>
                                    <div class="saw-audit-item-time">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        <span><?php echo esc_html($updated_at); ?></span>
                                        <?php if ($updated_at_rel): ?>
                                            <span class="saw-audit-time-relative">(<?php echo esc_html($updated_at_rel); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; // End of fallback ?>
            
        </div>
    </div>
</div>

<script>
// Global toggle function (only define once)
if (typeof sawToggleAuditHistory === 'undefined') {
    function sawToggleAuditHistory(uniqueId) {
        const container = document.getElementById(uniqueId);
        if (!container) return;
        
        const content = document.getElementById('audit-content-' + uniqueId);
        const icon = document.getElementById('audit-icon-' + uniqueId);
        const storageKey = container.getAttribute('data-storage-key');
        
        if (content && icon) {
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            icon.textContent = isHidden ? '▲' : '▼';
            container.classList.toggle('expanded', isHidden);
            
            // Auto-scroll to expanded section (only when expanding)
            if (isHidden) {
                // Use requestAnimationFrame for better synchronization with DOM updates
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        // Find the sidebar content container
                        const sidebarContent = container.closest('.saw-sidebar-content');
                        if (sidebarContent) {
                            // Scroll the container to show the expanded section
                            const containerTop = container.offsetTop;
                            const sidebarScrollTop = sidebarContent.scrollTop;
                            const sidebarHeight = sidebarContent.clientHeight;
                            const containerHeight = container.offsetHeight;
                            
                            // Calculate target scroll position: align container top with 20px offset from sidebar top
                            const targetScroll = containerTop - 20;
                            
                            // Only scroll if container is not fully visible
                            if (containerTop < sidebarScrollTop + 20 || containerTop + containerHeight > sidebarScrollTop + sidebarHeight) {
                                sidebarContent.scrollTo({
                                    top: Math.max(0, targetScroll),
                                    behavior: 'smooth'
                                });
                            }
                        } else {
                            // Fallback: use scrollIntoView
                            container.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start',
                                inline: 'nearest'
                            });
                        }
                    });
                });
            }
            
            // Save state to localStorage
            if (storageKey && typeof Storage !== 'undefined') {
                try {
                    localStorage.setItem(storageKey, isHidden ? 'true' : 'false');
                } catch (e) {
                    // localStorage might be disabled
                }
            }
        }
    }
    
    // Restore state from localStorage on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.saw-detail-audit-history').forEach(function(container) {
            const storageKey = container.getAttribute('data-storage-key');
            if (storageKey && typeof Storage !== 'undefined') {
                try {
                    const savedState = localStorage.getItem(storageKey);
                    if (savedState === 'true') {
                        const uniqueId = container.id;
                        const content = document.getElementById('audit-content-' + uniqueId);
                        const icon = document.getElementById('audit-icon-' + uniqueId);
                        if (content && icon) {
                            content.style.display = 'block';
                            icon.textContent = '▲';
                            container.classList.add('expanded');
                        }
                    }
                } catch (e) {
                    // localStorage might be disabled
                }
            }
        });
    });
}
</script>

<style>
.saw-detail-audit-history {
    margin: 24px 0;
    padding: 0 12px;
    border-top: none;
}

@media (min-width: 768px) {
    .saw-detail-audit-history {
        padding: 0 20px;
    }
}

@media (min-width: 1024px) {
    .saw-detail-audit-history {
        padding: 0 24px;
    }
}

.saw-audit-header {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    margin-top: 0;
}

.saw-audit-header:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.saw-audit-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.saw-audit-icon {
    color: #64748b;
    flex-shrink: 0;
}

.saw-audit-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-audit-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.saw-audit-summary {
    font-size: 12px;
    color: #64748b;
}

.saw-audit-toggle-icon {
    font-size: 12px;
    color: #64748b;
    transition: transform 0.2s ease;
    user-select: none;
}

.saw-audit-header.expanded .saw-audit-toggle-icon,
.saw-detail-audit-history.expanded .saw-audit-toggle-icon {
    transform: rotate(180deg);
}

.saw-audit-content {
    margin-top: 8px;
    padding: 12px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    animation: sawAuditSlideDown 0.3s ease-out;
}

.saw-audit-meta-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-audit-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.saw-audit-meta-item svg {
    color: #94a3b8;
    flex-shrink: 0;
}

.saw-audit-meta-label {
    color: #64748b;
}

.saw-audit-meta-value {
    font-weight: 600;
    color: #1e293b;
}

@keyframes sawAuditSlideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.saw-audit-timeline {
    position: relative;
    padding-left: 0;
}

.saw-audit-timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-audit-timeline-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.saw-audit-timeline-line {
    display: none; /* Removed vertical line for simpler design */
}

.saw-audit-timeline-line-last {
    display: none;
}

.saw-audit-timeline-item:last-child .saw-audit-timeline-line {
    display: none;
}

.saw-audit-timeline-dot {
    position: static;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #e2e8f0;
    vertical-align: middle;
    margin-right: 0;
    flex-shrink: 0;
}

.saw-audit-item-created .saw-audit-timeline-dot {
    background: #d1fae5;
    color: #10b981;
    box-shadow: 0 0 0 2px #d1fae5;
}

.saw-audit-item-updated .saw-audit-timeline-dot {
    background: #dbeafe;
    color: #3b82f6;
    box-shadow: 0 0 0 2px #dbeafe;
}

.saw-audit-timeline-dot svg {
    width: 16px;
    height: 16px;
}

.saw-audit-timeline-content {
    padding-left: 0;
}

.saw-audit-item-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.saw-audit-item-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

.saw-audit-item-body {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.saw-audit-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 2px solid #e2e8f0;
}

.saw-audit-item-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.saw-audit-email {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    word-break: break-all;
}

.saw-audit-email:hover {
    text-decoration: underline;
    color: #2563eb;
}

.saw-audit-unknown {
    color: #94a3b8;
    font-style: italic;
    font-size: 14px;
}

.saw-audit-item-time {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #64748b;
}

.saw-audit-item-time svg {
    color: #94a3b8;
    flex-shrink: 0;
}

.saw-audit-time-relative {
    color: #94a3b8;
    font-size: 12px;
}

.saw-audit-branch-info {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}

.saw-audit-branch-info svg {
    color: #94a3b8;
    flex-shrink: 0;
}

.saw-audit-field-changes {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-audit-field-change {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-start;
    font-size: 13px;
}

.saw-audit-field-label {
    font-weight: 600;
    color: #475569;
    min-width: 100px;
}

.saw-audit-field-image {
    flex-direction: column;
    gap: 8px;
}

.saw-audit-field-image .saw-audit-field-diff {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
}

.saw-audit-image {
    max-width: 150px;
    max-height: 150px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-top: 4px;
    display: block;
}

.saw-audit-field-diff {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.saw-audit-value-old {
    color: #64748b;
    text-decoration: line-through;
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

.saw-audit-value-new {
    color: #059669;
    font-weight: 500;
    background: #d1fae5;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

.saw-audit-item-created .saw-audit-value-new {
    color: #059669;
    background: #d1fae5;
}

.saw-audit-item-updated .saw-audit-value-new {
    color: #0284c7;
    background: #dbeafe;
}

.saw-audit-diff-arrow {
    color: #94a3b8;
    font-weight: bold;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .saw-audit-header {
        padding: 10px 12px;
    }
    
    .saw-audit-summary {
        display: none; /* Hide summary on mobile */
    }
    
    .saw-audit-content {
        padding: 12px;
    }
    
    .saw-audit-avatar {
        width: 32px;
        height: 32px;
    }
    
    .saw-audit-timeline-dot {
        width: 28px;
        height: 28px;
    }
    
    .saw-audit-timeline-dot svg {
        width: 14px;
        height: 14px;
    }
    
    .saw-audit-item-header {
        gap: 10px;
    }
}
</style>

