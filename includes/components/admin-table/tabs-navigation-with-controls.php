<?php
/**
 * Admin Table - Tabs Navigation with Search and Filters
 * 
 * Horizontal tabs with search and filters in the same row
 * 
 * @package SAW_Visitors
 * @since 7.1.0
 * @version 2.0 - FIXED: URL uses filter_value instead of tab_key
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Map emoji icon to Lucide icon name
 */
function saw_map_tab_icon($emoji) {
    if (!class_exists('SAW_Icons')) {
        return $emoji; // Fallback na emoji
    }
    
    $map = [
        '📋' => 'clipboard-list',
        '📝' => 'file-text',
        '⏳' => 'clock',
        '✅' => 'check-circle',
        '🔄' => 'refresh-cw',
        '✔️' => 'check',
        '❌' => 'x-circle',
        '📊' => 'bar-chart-3',
        '📅' => 'calendar',
        '🚪' => 'log-out',
        '📦' => 'package',
        '⏸️' => 'pause',
        '✓' => 'check',
        '✕' => 'x',
        '🏛️' => 'building',
        '🏢' => 'building-2',
        '👔' => 'briefcase',
        '🎯' => 'target',
        '📧' => 'mail',
        '⚙️' => 'settings',
        '🌐' => 'globe',
        '🖥️' => 'monitor',
        '👥' => 'users',
    ];
    
    return $map[$emoji] ?? 'circle';
}

$tabs_config = $config['tabs'] ?? array();
if (empty($tabs_config['enabled'])) {
    return;
}

$tabs = $tabs_config['tabs'] ?? array();
$tab_param = $tabs_config['tab_param'] ?? 'tab';
$current_tab = $config['current_tab'] ?? ($tabs_config['default_tab'] ?? 'all');
$tab_counts = $config['tab_counts'] ?? array();
$base_url = $config['base_url'] ?? '';
$current_params = $config['current_params'] ?? array();

// Ensure current_tab is a string and not null
// Handle case where current_tab might be null or empty
// Use isset() and !== null/'' instead of !empty() to handle '0' values
$current_tab = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
    ? (string) $current_tab 
    : ($tabs_config['default_tab'] ?? 'all');

// KRITICKÉ: Remove tab param and paged from current params
$base_params = $current_params;
unset($base_params[$tab_param], $base_params['paged']);

$search_enabled = $config['search_enabled'] ?? false;
$filters_enabled = $config['filters_enabled'] ?? false;
$search_html = $config['search_html'] ?? '';
$filters_html = $config['filters_html'] ?? '';
?>

<div class="saw-table-tabs-wrapper">
    <div class="saw-table-tabs-container">
        <!-- Left Arrow - před tabs -->
        <button type="button" class="saw-tabs-nav-arrow saw-tabs-nav-arrow-left" aria-label="Scroll left" style="display: none;">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php endif; ?>
        </button>
        
        <!-- Tabs Navigation -->
        <div class="saw-table-tabs">
            <?php foreach ($tabs as $tab_key => $tab): ?>
                <?php
                // Ensure tab_key is string for comparison
                $tab_key = (string) $tab_key;
                // Strict comparison with both as strings
                $is_active = ((string)$tab_key === (string)$current_tab);
                
                // CRITICAL FIX: Generate URL with filter_value, not tab_key
                if ($tab['filter_value'] === null || $tab['filter_value'] === '') {
                    // "All" tab - no filter parameter in URL
                    $tab_url = add_query_arg($base_params, $base_url);
                } else {
                    // Other tabs - use filter_value in URL
                    $url_params = array_merge($base_params, array($tab_param => $tab['filter_value']));
                    $tab_url = add_query_arg($url_params, $base_url);
                }
                
                $count = $tab_counts[$tab_key] ?? 0;
                ?>
                <a href="<?php echo esc_url($tab_url); ?>" 
                   class="saw-table-tab<?php echo $is_active ? ' active' : ''; ?>"
                   data-tab="<?php echo esc_attr($tab_key); ?>"
                   data-filter-value="<?php echo esc_attr($tab['filter_value'] ?? ''); ?>">
                    <span class="saw-tab-icon">
                        <?php 
                        if (!empty($tab['icon'])) {
                            if (class_exists('SAW_Icons')) {
                                $lucide_name = saw_map_tab_icon($tab['icon']);
                                echo SAW_Icons::get($lucide_name, 'saw-icon--sm');
                            } else {
                                echo esc_html($tab['icon']);
                            }
                        }
                        ?>
                    </span>
                    <span class="saw-tab-label"><?php echo esc_html($tab['label']); ?></span>
                    <span class="saw-tab-count"><?php echo number_format_i18n($count); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Right Arrow - za tabs, před search -->
        <button type="button" class="saw-tabs-nav-arrow saw-tabs-nav-arrow-right" aria-label="Scroll right" style="display: none;">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-right'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            <?php endif; ?>
        </button>
        
        <!-- Search and Filters -->
        <?php if ($search_enabled || $filters_enabled): ?>
        <div class="saw-table-controls-inline">
            <?php if ($search_enabled && !empty($search_html)): ?>
                <div class="saw-table-search">
                    <?php echo $search_html; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($filters_enabled && !empty($filters_html)): ?>
                <div class="saw-table-filters">
                    <?php echo $filters_html; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
