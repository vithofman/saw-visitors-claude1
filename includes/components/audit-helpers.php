<?php
/**
 * Audit History Helper Functions
 * Universal Audit System v3.0
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('wp_strip_all_tags')) {
    // Fallback if WordPress functions are not loaded
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

if (!function_exists('date_i18n')) {
    // Fallback if WordPress functions are not loaded
    function date_i18n($format, $timestamp) {
        return date($format, $timestamp);
    }
}

/**
 * Get Gravatar URL for email
 */
function saw_get_gravatar_url($email, $size = 40) {
    if (empty($email)) {
        return 'https://www.gravatar.com/avatar/00000000000000000000000000000000?s=' . $size . '&d=identicon';
    }
    $hash = md5(strtolower(trim($email)));
    return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=identicon';
}

/**
 * Get audit action configuration
 */
function saw_get_audit_action_config($action) {
    $configs = [
        'created' => ['icon' => '➕', 'label' => 'Vytvořeno', 'color' => '#10b981'],
        'updated' => ['icon' => '✏️', 'label' => 'Upraveno', 'color' => '#6366f1'],
        'status_changed' => ['icon' => '🔄', 'label' => 'Stav změněn', 'color' => '#f59e0b'],
        'pin_generated' => ['icon' => '🔑', 'label' => 'PIN vygenerován', 'color' => '#8b5cf6'],
        'invitation_sent' => ['icon' => '📧', 'label' => 'Pozvánka odeslána', 'color' => '#ec4899'],
        'invitation_confirmed' => ['icon' => '✅', 'label' => 'Pozvánka potvrzena', 'color' => '#10b981'],
        'visitor_arrived' => ['icon' => '🚶', 'label' => 'Návštěvník příchozí', 'color' => '#10b981'],
        'visitor_departed' => ['icon' => '👋', 'label' => 'Návštěvník odchozí', 'color' => '#6b7280'],
        'relation_changed' => ['icon' => '🔗', 'label' => 'Vztah změněn', 'color' => '#6366f1'],
        'relation_added' => ['icon' => '➕', 'label' => 'Přidáno', 'color' => '#10b981'],
        'relation_removed' => ['icon' => '➖', 'label' => 'Odebráno', 'color' => '#ef4444'],
        'translation_changed' => ['icon' => '🌐', 'label' => 'Změna překladu', 'color' => '#8b5cf6'],
    ];
    
    return $configs[$action] ?? ['icon' => '📝', 'label' => ucfirst(str_replace('_', ' ', $action)), 'color' => '#6b7280'];
}

/**
 * Render audit author badge (compact - only gravatar + email)
 */
function saw_render_audit_author($details) {
    $source = $details['source'] ?? 'system';
    $context = $details['source_context'] ?? [];
    
    $html = '<div class="saw-audit-author saw-audit-author-' . esc_attr($source) . '">';
    
    if ($source === 'admin') {
        $user_email = $context['user_email'] ?? '';
        $gravatar_url = saw_get_gravatar_url($user_email, 28);
        $html .= '<img src="' . esc_url($gravatar_url) . '" alt="' . esc_attr($user_email) . '" class="saw-audit-author-avatar" width="28" height="28">';
        $html .= '<span class="saw-audit-author-email">' . esc_html($user_email) . '</span>';
    } elseif ($source === 'terminal') {
        if (!empty($context['user_email'])) {
            $user_email = $context['user_email'];
            $gravatar_url = saw_get_gravatar_url($user_email, 28);
            $html .= '<img src="' . esc_url($gravatar_url) . '" alt="' . esc_attr($user_email) . '" class="saw-audit-author-avatar" width="28" height="28">';
            $html .= '<span class="saw-audit-author-email">' . esc_html($user_email) . '</span>';
        } else {
            $html .= '<span class="saw-audit-author-icon">🖥️</span>';
            $html .= '<span class="saw-audit-author-email">' . esc_html($context['terminal_name'] ?? 'Terminál') . '</span>';
        }
    } elseif ($source === 'invitation') {
        $invitation_email = $context['invitation_email'] ?? 'Pozvánka';
        $gravatar_url = saw_get_gravatar_url($invitation_email, 28);
        $html .= '<img src="' . esc_url($gravatar_url) . '" alt="' . esc_attr($invitation_email) . '" class="saw-audit-author-avatar" width="28" height="28">';
        $html .= '<span class="saw-audit-author-email">' . esc_html($invitation_email) . '</span>';
    } else {
        $html .= '<span class="saw-audit-author-icon">⚙️</span>';
        $html .= '<span class="saw-audit-author-email">Systém</span>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Get field label
 */
function saw_get_field_label($field) {
    $labels = [
        'started_at' => 'Zahájeno',
        'completed_at' => 'Dokončeno',
        'planned_date_from' => 'Plánované datum od',
        'planned_date_to' => 'Plánované datum do',
        'visit_type' => 'Typ návštěvy',
        'status' => 'Stav',
        'risks_text' => 'Text rizik',
        'purpose' => 'Účel návštěvy',
        'notes' => 'Poznámky',
        'pin_expires_at' => 'Platnost PIN',
    ];
    
    return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
}

/**
 * Resolve ID to name for relation fields
 */
function saw_resolve_id_to_name($field, $id) {
    if (empty($id) || !is_numeric($id)) {
        return null;
    }
    
    global $wpdb;
    $id = absint($id);
    
    // Handle company_id
    if ($field === 'company_id') {
        $company = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}saw_companies WHERE id = %d",
            $id
        ));
        return $company ? $company : null;
    }
    
    // Handle customer_id
    if ($field === 'customer_id') {
        $customer = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $id
        ));
        return $customer ? $customer : null;
    }
    
    // Handle branch_id
    if ($field === 'branch_id') {
        $branch = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $id
        ));
        return $branch ? $branch : null;
    }
    
    return null;
}

/**
 * Format field value for display (strip HTML, resolve IDs to names)
 */
function saw_format_field_value($value, $field = null) {
    if ($value === null || $value === '') {
        return '[Prázdné]';
    }
    
    if (is_bool($value)) {
        return $value ? 'Ano' : 'Ne';
    }
    
    if (is_array($value)) {
        return implode(', ', array_map(function($v) use ($field) {
            return esc_html(wp_strip_all_tags((string)$v));
        }, $value));
    }
    
    // Try to resolve ID to name for relation fields
    if ($field && is_numeric($value)) {
        $resolved_name = saw_resolve_id_to_name($field, $value);
        if ($resolved_name) {
            return esc_html(wp_strip_all_tags($resolved_name));
        }
    }
    
    return wp_strip_all_tags((string)$value);
}

/**
 * Format compact changes for table display
 */
function saw_format_compact_changes($details) {
    $parts = [];
    
    // Field changes
    if (!empty($details['changed_fields'])) {
        foreach ($details['changed_fields'] as $field => $change) {
            $field_label = saw_get_field_label($field);
            $old_val = saw_format_field_value($change['old'] ?? null, $field);
            $new_val = saw_format_field_value($change['new'] ?? null, $field);
            
            // Format datetime values - ALWAYS show time for DATETIME fields
            if (in_array($field, ['started_at', 'completed_at', 'pin_expires_at'])) {
                // These are DATETIME fields, so show date AND time
                if (!empty($change['old']) && $change['old'] !== '0000-00-00 00:00:00' && $change['old'] !== null) {
                    $timestamp = strtotime($change['old']);
                    if ($timestamp !== false) {
                        $old_val = date_i18n('j.n.Y H:i', $timestamp);
                    }
                }
                if (!empty($change['new']) && $change['new'] !== '0000-00-00 00:00:00' && $change['new'] !== null) {
                    $timestamp = strtotime($change['new']);
                    if ($timestamp !== false) {
                        $new_val = date_i18n('j.n.Y H:i', $timestamp);
                    }
                }
            } elseif (in_array($field, ['planned_date_from', 'planned_date_to'])) {
                // These are DATE fields (not DATETIME), so only date, no time
                if (!empty($change['old']) && $change['old'] !== '0000-00-00' && $change['old'] !== '0000-00-00 00:00:00' && $change['old'] !== null) {
                    $timestamp = strtotime($change['old']);
                    if ($timestamp !== false) {
                        $old_val = date_i18n('j.n.Y', $timestamp);
                    }
                }
                if (!empty($change['new']) && $change['new'] !== '0000-00-00' && $change['new'] !== '0000-00-00 00:00:00' && $change['new'] !== null) {
                    $timestamp = strtotime($change['new']);
                    if ($timestamp !== false) {
                        $new_val = date_i18n('j.n.Y', $timestamp);
                    }
                }
            }
            
            $parts[] = '<div class="saw-audit-change-item"><strong>' . esc_html($field_label) . ':</strong> ' . esc_html($old_val) . ' → ' . esc_html($new_val) . '</div>';
        }
    }
    
    // Long text changes
    if (!empty($details['long_text_changes'])) {
        foreach ($details['long_text_changes'] as $field => $change) {
            $field_label = saw_get_field_label($field);
            $old_length = $change['old_length'] ?? 0;
            $new_length = $change['new_length'] ?? 0;
            $preview = wp_strip_all_tags($change['preview'] ?? '');
            if ($preview) {
                $preview = mb_substr($preview, 0, 50) . (mb_strlen($preview) > 50 ? '...' : '');
            }
            $parts[] = '<div class="saw-audit-change-item"><strong>' . esc_html($field_label) . ':</strong> ' . $old_length . ' → ' . $new_length . ' znaků' . ($preview ? ' (' . esc_html($preview) . ')' : '') . '</div>';
        }
    }
    
    // Related items (hosts, visitors, OOPP, files)
    if (!empty($details['related_items'])) {
        // Group by type
        $by_type = [];
        foreach ($details['related_items'] as $item) {
            $type = $item['type'] ?? 'unknown';
            $action = $item['action'] ?? 'changed';
            $name = $item['name'] ?? '';
            $id = $item['id'] ?? null;
            
            // If name is empty but we have ID, use fallback
            if (empty($name) && $id) {
                $type_label_fallback = saw_get_relation_type_label($type);
                $name = $type_label_fallback . ' #' . absint($id);
            }
            
            // Skip only if both name and ID are empty
            if (empty($name) && empty($id)) {
                continue;
            }
            
            $icon = ($action === 'added') ? '➕' : (($action === 'removed') ? '➖' : '');
            
            if (!isset($by_type[$type])) {
                $by_type[$type] = [];
            }
            $by_type[$type][] = $icon . ' ' . esc_html($name);
        }
        
        // Format by type with labels
        foreach ($by_type as $type => $items) {
            if (empty($items)) {
                continue;
            }
            $type_label = saw_get_relation_type_label($type);
            $parts[] = '<div class="saw-audit-change-item"><strong>' . esc_html($type_label) . ':</strong> ' . implode(', ', $items) . '</div>';
        }
    }
    
    // Translation badge
    if (!empty($details['translation'])) {
        $lang_code = $details['translation']['lang_code'] ?? '';
        $lang_names = ['cs' => 'Čeština', 'sk' => 'Slovenština', 'en' => 'Angličtina'];
        $lang_name = $lang_names[$lang_code] ?? strtoupper($lang_code);
        $parts[] = '<div class="saw-audit-change-item"><span class="saw-audit-lang-badge">' . esc_html($lang_name) . '</span></div>';
    }
    
    return !empty($parts) ? implode('', $parts) : '';
}

/**
 * Get relation type label
 */
function saw_get_relation_type_label($type) {
    $labels = [
        'hosts' => 'Hostitelé',
        'visitors' => 'Návštěvníci',
        'action_oopp' => 'OOPP',
        'file' => 'Soubory',
        'documents' => 'Dokumenty',
        'invitation_materials' => 'Materiály pozvánky',
        'action_documents' => 'Dokumenty akce',
    ];
    
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/**
 * Render translation badge
 */
function saw_render_translation_badge($translation) {
    $lang_code = $translation['lang_code'] ?? '';
    $lang_names = ['cs' => 'Čeština', 'sk' => 'Slovenština', 'en' => 'Angličtina'];
    $lang_name = $lang_names[$lang_code] ?? strtoupper($lang_code);
    
    return '<span class="saw-audit-lang-badge">' . esc_html($lang_name) . '</span>';
}
