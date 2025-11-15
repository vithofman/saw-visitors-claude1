<?php
/**
 * SAW Select-Create Input Template
 * 
 * Renders select dropdown with optional inline create button.
 * Provides complete interface for selecting existing records or creating new ones.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SelectCreate
 * @version     1.0.0
 * @since       13.0.0
 * @author      SAW Visitors Team
 * 
 * Variables:
 * @var string $field_name Field identifier
 * @var array  $config     Component configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$field_name = $field_name ?? 'select_field';
$config = $config ?? array();

// Extract configuration
$label = $config['label'] ?? '';
$options = $config['options'] ?? array();
$selected = $config['selected'] ?? '';
$required = $config['required'] ?? false;
$placeholder = $config['placeholder'] ?? '-- Vyberte --';
$custom_class = $config['custom_class'] ?? '';
$inline_create = $config['inline_create'] ?? array();

// Inline create settings
$inline_enabled = $inline_create['enabled'] ?? false;
$target_module = $inline_create['target_module'] ?? '';
$button_text = $inline_create['button_text'] ?? '+ Nový';
$prefill = $inline_create['prefill'] ?? array();

// Generate unique ID for the field
$field_id = 'saw-select-' . esc_attr($field_name);
?>

<div class="saw-select-create-component <?php echo esc_attr($custom_class); ?>">
    
    <?php if (!empty($label)): ?>
        <label for="<?php echo esc_attr($field_id); ?>" class="saw-label <?php echo $required ? 'saw-required' : ''; ?>">
            <?php echo esc_html($label); ?>
        </label>
    <?php endif; ?>
    
    <div class="saw-select-create-wrapper">
        
        <!-- Standard select dropdown -->
        <select 
            name="<?php echo esc_attr($field_name); ?>" 
            id="<?php echo esc_attr($field_id); ?>" 
            class="saw-input saw-select-create-select"
            <?php echo $required ? 'required' : ''; ?>
        >
            <option value=""><?php echo esc_html($placeholder); ?></option>
            
            <?php foreach ($options as $value => $label_text): ?>
                <option 
                    value="<?php echo esc_attr($value); ?>"
                    <?php selected($selected, $value); ?>
                >
                    <?php echo esc_html($label_text); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- Inline create button (conditional) -->
        <?php if ($inline_enabled && !empty($target_module)): ?>
            <button 
                type="button" 
                class="saw-inline-create-btn"
                data-field="<?php echo esc_attr($field_name); ?>"
                data-module="<?php echo esc_attr($target_module); ?>"
                data-prefill="<?php echo esc_attr(wp_json_encode($prefill)); ?>"
                title="<?php echo esc_attr($button_text); ?>"
            >
                <?php echo esc_html($button_text); ?>
            </button>
        <?php endif; ?>
        
    </div>
    
</div>
