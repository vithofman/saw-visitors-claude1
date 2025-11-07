<?php
/**
 * SAW Search Input Template
 * 
 * Renders the search input interface with optional clear button,
 * submit button, and search result information banner.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Search
 * @version     1.1.0
 * @since       1.0.0
 * @author      SAW Visitors Team
 * 
 * Variables:
 * @var string $entity Entity identifier
 * @var array  $config Search configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$entity = $entity ?? 'entity';
$config = $config ?? array();

$placeholder = $config['placeholder'] ?? 'Hledat...';
$search_value = $config['search_value'] ?? '';
$ajax_enabled = $config['ajax_enabled'] ?? true;
$ajax_action = $config['ajax_action'] ?? 'saw_search';
$show_clear = $config['show_clear'] ?? true;
$show_button = $config['show_button'] ?? true;
$show_info_banner = $config['show_info_banner'] ?? true;
$info_banner_label = $config['info_banner_label'] ?? 'Vyhledávání:';
$clear_url = $config['clear_url'] ?? '';
?>

<div class="saw-search-component-container">
    <?php if ($show_info_banner && !empty($search_value)): ?>
        <div class="saw-search-info">
            <?php echo esc_html($info_banner_label); ?> <strong><?php echo esc_html($search_value); ?></strong>
            <a href="<?php echo esc_url($clear_url); ?>">Zrušit</a>
        </div>
    <?php endif; ?>

    <div class="saw-search-wrapper">
        <input 
            type="text" 
            id="saw-<?php echo esc_attr($entity); ?>-search" 
            value="<?php echo esc_attr($search_value); ?>" 
            placeholder="<?php echo esc_attr($placeholder); ?>"
            class="saw-search-input"
            data-entity="<?php echo esc_attr($entity); ?>"
            data-ajax-action="<?php echo esc_attr($ajax_action); ?>"
            data-ajax-enabled="<?php echo $ajax_enabled ? '1' : '0'; ?>"
        >
        <span class="dashicons dashicons-search saw-search-icon"></span>
        <?php if ($show_clear): ?>
            <button 
                type="button" 
                class="saw-search-clear" 
                style="display: <?php echo !empty($search_value) ? 'flex' : 'none'; ?>;"
            >
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        <?php endif; ?>
        <?php if ($show_button): ?>
            <button 
                type="button" 
                class="saw-search-submit"
            >
                <span class="dashicons dashicons-search"></span>
                <span class="saw-search-submit-text">Hledat</span>
            </button>
        <?php endif; ?>
    </div>
</div>