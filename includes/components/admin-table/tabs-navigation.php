<?php
/**
 * Admin Table - Tabs Navigation
 * 
 * Horizontal tabs for category filtering
 * 
 * @package SAW_Visitors
 * @since 7.1.0
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
$current_tab = (string) $current_tab;

// KRITICKÉ: Remove tab param and paged from current params
$base_params = $current_params;
unset($base_params[$tab_param], $base_params['paged']);
?>

<div class="saw-table-tabs-wrapper">
    <div class="saw-table-tabs">
        <?php foreach ($tabs as $tab_key => $tab): ?>
            <?php
            // Ensure tab_key is string for comparison
            $tab_key = (string) $tab_key;
            $is_active = ($tab_key === $current_tab);
            $tab_url = add_query_arg(array_merge($base_params, array($tab_param => $tab_key)), $base_url);
            $count = $tab_counts[$tab_key] ?? 0;
            ?>
            <a href="<?php echo esc_url($tab_url); ?>" 
               class="saw-table-tab<?php echo $is_active ? ' active' : ''; ?>"
               data-tab="<?php echo esc_attr($tab_key); ?>">
                <span class="saw-tab-icon"><?php echo $tab['icon'] ?? ''; ?></span>
                <span class="saw-tab-label"><?php echo esc_html($tab['label']); ?></span>
                <span class="saw-tab-count"><?php echo number_format_i18n($count); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

