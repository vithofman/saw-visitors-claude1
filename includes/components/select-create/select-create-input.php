<?php
/**
 * SAW Select-Create Input Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SelectCreate
 * @version     1.3.0 - NO required attribute anywhere
 * @since       13.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$field_name = $field_name ?? 'select_field';
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
?>

<!-- SAW Select-Create v1.3.0 -->
<div class="saw-select-create-component <?php echo esc_attr($custom_class); ?>">
    
    <?php if (!empty($label)): ?>
        <label for="<?php echo esc_attr($field_id); ?>-search" class="saw-label <?php echo $required ? 'saw-required' : ''; ?>">
            <?php echo esc_html($label); ?>
        </label>
    <?php endif; ?>
    
    <div class="saw-select-create-wrapper">
        
        <!-- Hidden input - form value -->
        <input type="hidden" 
               name="<?php echo esc_attr($field_name); ?>" 
               id="<?php echo esc_attr($field_id); ?>-hidden" 
               value="<?php echo esc_attr($selected); ?>">
        
        <!-- Select - NO name, NO required -->
        <select id="<?php echo esc_attr($field_id); ?>" 
                class="saw-input saw-select-create-select"
                data-field-name="<?php echo esc_attr($field_name); ?>">
            <option value=""><?php echo esc_html($placeholder); ?></option>
            <?php foreach ($options as $value => $label_text): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
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

<script>
(function() {
    var s = document.getElementById('<?php echo esc_js($field_id); ?>');
    var h = document.getElementById('<?php echo esc_js($field_id); ?>-hidden');
    if (s && h) {
        s.addEventListener('change', function() { h.value = this.value; });
    }
})();
</script>