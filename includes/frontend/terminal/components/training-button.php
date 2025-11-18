<?php
/**
 * Training Button Component
 * 
 * Unified action button for all training steps
 * Touch-friendly with variants (primary, success, danger, secondary)
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * @param array $args {
 *     Component arguments
 *     
 *     @type string $text       Button text (required)
 *     @type string $type       Button type: 'submit', 'button', 'link' (default: 'submit')
 *     @type string $variant    Style variant: 'primary', 'success', 'danger', 'secondary' (default: 'success')
 *     @type bool   $disabled   Is button disabled (default: false)
 *     @type string $icon       Icon text/emoji (default: '→')
 *     @type string $href       URL for link type (required if type='link')
 *     @type array  $attributes Additional HTML attributes (default: [])
 *     @type string $id         Button ID (optional)
 *     @type bool   $full_width Full width button (default: true)
 * }
 * 
 * Usage:
 * get_template_part('components/training-button', null, [
 *     'text' => 'Pokračovat',
 *     'variant' => 'success',
 *     'icon' => '→',
 *     'attributes' => ['id' => 'continue-btn']
 * ]);
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get component arguments
$text = $args['text'] ?? '';
$type = $args['type'] ?? 'submit';
$variant = $args['variant'] ?? 'success';
$disabled = $args['disabled'] ?? false;
$icon = $args['icon'] ?? '→';
$href = $args['href'] ?? '';
$attributes = $args['attributes'] ?? [];
$id = $args['id'] ?? '';
$full_width = $args['full_width'] ?? true;

// Validation
if (empty($text)) {
    error_log('[SAW Training Button] Error: Text is required');
    return;
}

if ($type === 'link' && empty($href)) {
    error_log('[SAW Training Button] Error: href is required for link type');
    return;
}

// Valid variants
$valid_variants = ['primary', 'success', 'danger', 'secondary'];
if (!in_array($variant, $valid_variants)) {
    $variant = 'success';
}

// Build CSS classes
$classes = ['saw-training-btn', 'saw-training-btn-' . $variant];
if ($disabled) {
    $classes[] = 'saw-training-btn-disabled';
}
if (!$full_width) {
    $classes[] = 'saw-training-btn-auto-width';
}

// Build attributes string
$attrs_string = '';
if (!empty($id)) {
    $attrs_string .= ' id="' . esc_attr($id) . '"';
}
foreach ($attributes as $attr => $value) {
    if ($attr !== 'class' && $attr !== 'id') { // Skip class and id as we handle them separately
        $attrs_string .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
    }
}
?>

<?php if ($type === 'link'): ?>
    <!-- Link button -->
    <a href="<?php echo esc_url($href); ?>"
       class="<?php echo esc_attr(implode(' ', $classes)); ?>"
       <?php echo $attrs_string; ?>
       <?php echo $disabled ? 'aria-disabled="true"' : ''; ?>>
        <span class="saw-training-btn-text"><?php echo esc_html($text); ?></span>
        <?php if (!empty($icon)): ?>
        <span class="saw-training-btn-icon"><?php echo esc_html($icon); ?></span>
        <?php endif; ?>
    </a>

<?php else: ?>
    <!-- Regular button or submit -->
    <button type="<?php echo esc_attr($type); ?>"
            class="<?php echo esc_attr(implode(' ', $classes)); ?>"
            <?php echo $attrs_string; ?>
            <?php echo $disabled ? 'disabled' : ''; ?>>
        <span class="saw-training-btn-text"><?php echo esc_html($text); ?></span>
        <?php if (!empty($icon)): ?>
        <span class="saw-training-btn-icon"><?php echo esc_html($icon); ?></span>
        <?php endif; ?>
    </button>
<?php endif; ?>

<style>
/* Auto-width button override */
.saw-training-btn-auto-width {
    width: auto !important;
    min-width: 200px;
}

/* Link button - prevent pointer events when disabled */
a.saw-training-btn-disabled {
    pointer-events: none;
    cursor: not-allowed;
}
</style>
