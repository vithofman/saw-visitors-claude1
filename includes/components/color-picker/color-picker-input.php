<?php
/**
 * Color Picker Input Template
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

$id = $id ?? 'color';
$name = $name ?? 'color';
$value = $value ?? '#1e40af';
$label = $label ?? 'Barva';
$show_preview = $show_preview ?? false;
$preview_text = $preview_text ?? 'Náhled';
$preview_target_id = $preview_target_id ?? '';
$help_text = $help_text ?? '';
$custom_class = $custom_class ?? '';
?>

<div class="saw-color-picker-component <?php echo esc_attr($custom_class); ?>">
    <label for="<?php echo esc_attr($id); ?>" class="saw-label"><?php echo esc_html($label); ?></label>
    
    <div class="saw-color-picker-wrapper">
        <input 
            type="color" 
            id="<?php echo esc_attr($id); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            class="saw-color-picker"
            value="<?php echo esc_attr($value); ?>"
            data-target-id="<?php echo esc_attr($preview_target_id); ?>"
        >
        <input 
            type="text" 
            id="<?php echo esc_attr($id); ?>_value" 
            class="saw-color-value" 
            value="<?php echo esc_attr($value); ?>" 
            readonly
        >
    </div>
    
    <?php if ($help_text): ?>
        <span class="saw-help-text"><?php echo esc_html($help_text); ?></span>
    <?php endif; ?>
</div>