<?php
/**
 * Admin Table - Tabs Navigation
 * 
 * Horizontal tabs for category filtering
 * 
 * @package SAW_Visitors
 * @since 7.1.0
 * @version 2.0 - FIXED: URL uses filter_value instead of tab_key
 */

if (!defined('ABSPATH')) {
    exit;
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
?>

<div class="saw-table-tabs-wrapper">
    <!-- Left Arrow -->
    <button type="button" class="saw-tabs-nav-arrow saw-tabs-nav-arrow-left" aria-label="Scroll left" style="display: none;">
        <span class="dashicons dashicons-arrow-left-alt2"></span>
    </button>
    
    <!-- Right Arrow -->
    <button type="button" class="saw-tabs-nav-arrow saw-tabs-nav-arrow-right" aria-label="Scroll right" style="display: none;">
        <span class="dashicons dashicons-arrow-right-alt2"></span>
    </button>
    
    <div class="saw-table-tabs">
        <?php foreach ($tabs as $tab_key => $tab): ?>
            <?php
            // Ensure tab_key is string for comparison
            $tab_key = (string) $tab_key;
            // Strict comparison with both as strings
            $is_active = ((string)$tab_key === (string)$current_tab);
            
            // CRITICAL FIX: Generate URL with filter_value, not tab_key
            // This ensures URL refresh works correctly
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
                <span class="saw-tab-icon"><?php echo $tab['icon'] ?? ''; ?></span>
                <span class="saw-tab-label"><?php echo esc_html($tab['label']); ?></span>
                <span class="saw-tab-count"><?php echo number_format_i18n($count); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
