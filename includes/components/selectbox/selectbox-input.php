<?php
/**
 * SAW Selectbox Input Template
 * 
 * Renders the complete selectbox interface including trigger button,
 * dropdown with search, and options list (static or AJAX-loaded).
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/Selectbox
 * @version     4.6.1
 * @since       4.6.1
 * @author      SAW Visitors Team
 * 
 * Variables:
 * @var string $id     Selectbox identifier
 * @var array  $config Selectbox configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$id = $id ?? 'selectbox';
$config = $config ?? array();

$options = $config['options'] ?? array();
$selected = $config['selected'] ?? '';
$placeholder = $config['placeholder'] ?? 'Vyberte...';
$ajax_enabled = $config['ajax_enabled'] ?? false;
$ajax_action = $config['ajax_action'] ?? '';
$ajax_nonce = $config['ajax_nonce'] ?? '';
$searchable = $config['searchable'] ?? false;
$allow_empty = $config['allow_empty'] ?? true;
$empty_label = $config['empty_label'] ?? '';
$on_change = $config['on_change'] ?? '';
$custom_class = $config['custom_class'] ?? '';
$show_icons = $config['show_icons'] ?? false;
$grouped = $config['grouped'] ?? false;
$name = $config['name'] ?? $id;

$selected_label = '';
if (!empty($selected) && !empty($options)) {
    if (is_array($options) && isset($options[$selected])) {
        $selected_label = is_array($options[$selected]) ? $options[$selected]['label'] : $options[$selected];
    }
}
?>

<div class="saw-selectbox-component <?php echo esc_attr($custom_class); ?>" 
     id="saw-selectbox-<?php echo esc_attr($id); ?>"
     data-id="<?php echo esc_attr($id); ?>"
     data-ajax-enabled="<?php echo $ajax_enabled ? '1' : '0'; ?>"
     data-ajax-action="<?php echo esc_attr($ajax_action); ?>"
     data-ajax-nonce="<?php echo esc_attr($ajax_nonce); ?>"
     data-searchable="<?php echo $searchable ? '1' : '0'; ?>"
     data-on-change="<?php echo esc_attr($on_change); ?>"
     data-show-icons="<?php echo $show_icons ? '1' : '0'; ?>">
    
    <input type="hidden" 
           name="<?php echo esc_attr($name); ?>" 
           value="<?php echo esc_attr($selected); ?>" 
           class="saw-selectbox-value">
    
    <button type="button" class="saw-selectbox-trigger">
        <span class="saw-selectbox-trigger-text">
            <?php echo esc_html($selected_label ?: $placeholder); ?>
        </span>
        <svg class="saw-selectbox-trigger-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 10.5l-4-4h8l-4 4z"/>
        </svg>
    </button>
    
    <div class="saw-selectbox-dropdown">
        <?php if ($searchable): ?>
            <div class="saw-selectbox-search">
                <input type="text" 
                       class="saw-selectbox-search-input" 
                       placeholder="Hledat...">
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('search', 'saw-selectbox-search-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-search saw-selectbox-search-icon"></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="saw-selectbox-options">
            <?php if ($ajax_enabled): ?>
                <div class="saw-selectbox-loading">
                    <div class="spinner is-active"></div>
                    <div>Načítám...</div>
                </div>
            <?php else: ?>
                <?php if ($allow_empty): ?>
                    <div class="saw-selectbox-option <?php echo empty($selected) ? 'active' : ''; ?>" 
                         data-value="">
                        <?php echo esc_html($empty_label ?: $placeholder); ?>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($options as $value => $option): ?>
                    <?php
                    $option_value = $value;
                    $option_label = is_array($option) ? $option['label'] : $option;
                    $option_icon = is_array($option) && isset($option['icon']) ? $option['icon'] : '';
                    $option_meta = is_array($option) && isset($option['meta']) ? $option['meta'] : '';
                    $is_active = ($selected == $option_value);
                    ?>
                    <div class="saw-selectbox-option <?php echo $is_active ? 'active' : ''; ?>" 
                         data-value="<?php echo esc_attr($option_value); ?>"
                         <?php if ($option_icon && $show_icons): ?>data-icon="<?php echo esc_attr($option_icon); ?>"<?php endif; ?>>
                        
                        <?php if ($is_active): ?>
                            <span class="saw-selectbox-option-check">✓</span>
                        <?php endif; ?>
                        
                        <?php if ($option_icon && $show_icons): ?>
                            <img src="<?php echo esc_url($option_icon); ?>" 
                                 alt="<?php echo esc_attr($option_label); ?>" 
                                 class="saw-selectbox-option-icon">
                        <?php endif; ?>
                        
                        <div class="saw-selectbox-option-content">
                            <div class="saw-selectbox-option-label"><?php echo esc_html($option_label); ?></div>
                            <?php if ($option_meta): ?>
                                <div class="saw-selectbox-option-meta"><?php echo esc_html($option_meta); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($options)): ?>
                    <div class="saw-selectbox-empty">
                        Žádné možnosti
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>