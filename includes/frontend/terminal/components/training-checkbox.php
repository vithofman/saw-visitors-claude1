<?php
/**
 * Training Checkbox Component
 * 
 * Unified checkbox component for all training confirmations
 * Touch-friendly with custom styling and SVG checkmark
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * @param array $args {
 *     Component arguments
 *     
 *     @type string $id       Unique checkbox ID (required)
 *     @type string $name     Input name attribute (default: 'confirmed')
 *     @type string $text     Checkbox label text (required)
 *     @type bool   $checked  Is checkbox checked (default: false)
 *     @type bool   $disabled Is checkbox disabled (default: false)
 *     @type bool   $required Is checkbox required (default: true)
 *     @type string $value    Checkbox value (default: '1')
 * }
 * 
 * Usage:
 * get_template_part('components/training-checkbox', null, [
 *     'id' => 'video-confirmed',
 *     'name' => 'video_confirmed',
 *     'text' => 'Potvrzuji, že jsem shlédl celé video',
 *     'disabled' => true
 * ]);
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get component arguments
$id = $args['id'] ?? '';
$name = $args['name'] ?? 'confirmed';
$text = $args['text'] ?? '';
$checked = $args['checked'] ?? false;
$disabled = $args['disabled'] ?? false;
$required = $args['required'] ?? true;
$value = $args['value'] ?? '1';

// Validation
if (empty($id)) {
    error_log('[SAW Training Checkbox] Error: ID is required');
    return;
}

if (empty($text)) {
    error_log('[SAW Training Checkbox] Error: Text is required');
    return;
}

// Build CSS classes
$wrapper_classes = ['saw-training-confirm-box'];
if ($checked) {
    $wrapper_classes[] = 'checked';
}
?>

<div class="saw-training-confirm-wrapper">
    <label class="<?php echo implode(' ', $wrapper_classes); ?>" 
           for="<?php echo esc_attr($id); ?>"
           data-checkbox-wrapper>
        
        <!-- Hidden native checkbox -->
        <input type="checkbox" 
               id="<?php echo esc_attr($id); ?>"
               name="<?php echo esc_attr($name); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="saw-training-checkbox-input"
               <?php echo $checked ? 'checked' : ''; ?>
               <?php echo $disabled ? 'disabled' : ''; ?>
               <?php echo $required ? 'required' : ''; ?>>
        
        <!-- Custom checkbox icon -->
        <div class="saw-training-checkbox-icon">
            <svg class="saw-icon-check" 
                 viewBox="0 0 24 24" 
                 fill="none" 
                 stroke="currentColor"
                 stroke-width="3"
                 stroke-linecap="round"
                 stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        
        <!-- Checkbox content -->
        <div class="saw-training-checkbox-content">
            <span class="saw-training-checkbox-text">
                <?php echo esc_html($text); ?>
            </span>
        </div>
    </label>
</div>

<script>
(function() {
    'use strict';
    
    // Get checkbox and wrapper
    const checkbox = document.getElementById('<?php echo esc_js($id); ?>');
    const wrapper = checkbox ? checkbox.closest('[data-checkbox-wrapper]') : null;
    
    if (!checkbox || !wrapper) {
        console.error('[SAW Training Checkbox] Checkbox or wrapper not found');
        return;
    }
    
    // Toggle checked class on change
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            wrapper.classList.add('checked');
        } else {
            wrapper.classList.remove('checked');
        }
    });
    
    // Initialize checked state
    if (checkbox.checked) {
        wrapper.classList.add('checked');
    }
})();
</script>
