<?php
/**
 * SAW Select-Create Input Template - FIXED v2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$field_name = $field_name ?? '';
$config = $config ?? array();

$label = $config['label'] ?? '';
$options = $config['options'] ?? array();
$selected = $config['selected'] ?? '';
$required = $config['required'] ?? false;
$placeholder = $config['placeholder'] ?? '-- Vyberte --';
$custom_class = $config['custom_class'] ?? '';
$inline_create = $config['inline_create'] ?? array();

$inline_enabled = $inline_create['enabled'] ?? false;
$target_module = $inline_create['target_module'] ?? '';
$button_text = $inline_create['button_text'] ?? '+ Nový';
$prefill = $inline_create['prefill'] ?? array();

$field_id = 'saw-select-' . esc_attr($field_name);
$selected = (string) $selected;
?>

<div class="saw-select-create-component <?php echo esc_attr($custom_class); ?>" 
     data-field-name="<?php echo esc_attr($field_name); ?>">
    
    <?php if (!empty($label)): ?>
        <label for="<?php echo esc_attr($field_id); ?>-search" class="saw-label <?php echo $required ? 'saw-required' : ''; ?>">
            <?php echo esc_html($label); ?>
        </label>
    <?php endif; ?>
    
    <div class="saw-select-create-wrapper" data-field-name="<?php echo esc_attr($field_name); ?>">
        
        <!-- CRITICAL: Single hidden input with consistent class -->
        <input type="hidden" 
               name="<?php echo esc_attr($field_name); ?>" 
               id="<?php echo esc_attr($field_id); ?>-value"
               value="<?php echo esc_attr($selected); ?>"
               class="saw-select-create-value"
               data-original-value="<?php echo esc_attr($selected); ?>">
        
        <!-- Original select - NO name attribute -->
        <select id="<?php echo esc_attr($field_id); ?>" 
                class="saw-input saw-select-create-select"
                data-field-name="<?php echo esc_attr($field_name); ?>"
                data-placeholder="<?php echo esc_attr($placeholder); ?>">
            <option value=""><?php echo esc_html($placeholder); ?></option>
            <?php foreach ($options as $value => $label_text): ?>
                <option value="<?php echo esc_attr($value); ?>" 
                        <?php selected($selected, (string) $value); ?>>
                    <?php echo esc_html($label_text); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($inline_enabled && !empty($target_module)): ?>
            <button type="button" 
                    class="saw-inline-create-btn"
                    data-field="<?php echo esc_attr($field_name); ?>"
                    data-module="<?php echo esc_attr($target_module); ?>"
                    data-prefill="<?php echo esc_attr(wp_json_encode($prefill)); ?>"
                    title="<?php echo esc_attr($button_text); ?>">
                <?php echo esc_html($button_text); ?>
            </button>
        <?php endif; ?>
        
    </div>
</div>
<!-- NO INLINE SCRIPT -->