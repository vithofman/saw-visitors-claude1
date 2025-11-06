<?php
/**
 * Color Picker Input Template
 *
 * Reusable color picker component with label, preview, and help text support.
 * Generates HTML5 color input with synchronized text display.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/Templates
 * @since       1.0.0
 * @version     1.0.0
 *
 * @param string $id                 Input ID attribute (default: 'color')
 * @param string $name               Input name attribute (default: 'color')
 * @param string $value              Initial color value in hex format (default: '#1e40af')
 * @param string $label              Label text (default: 'Barva')
 * @param string $preview_target_id  Optional ID of external element to sync color to
 * @param string $help_text          Optional help text displayed below input
 * @param string $custom_class       Optional custom CSS class for wrapper
 */

if (!defined('ABSPATH')) {
    exit;
}

// Set defaults for all parameters
$id = $id ?? 'color';
$name = $name ?? 'color';
$value = $value ?? '#1e40af';
$label = $label ?? 'Barva';
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