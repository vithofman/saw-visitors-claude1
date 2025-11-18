<?php
/**
 * Training Header Component
 * 
 * Standardized header for all training steps
 * Displays icon, title, and optional subtitle
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * @param array $args {
 *     Component arguments
 *     
 *     @type string $icon     Emoji icon (default: '📄')
 *     @type string $title    Header title (required)
 *     @type string $subtitle Optional subtitle text
 * }
 * 
 * Usage:
 * get_template_part('components/training-header', null, [
 *     'icon' => '🎬',
 *     'title' => 'Školící video',
 *     'subtitle' => 'Sledujte celé video do konce'
 * ]);
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get component arguments
$icon = $args['icon'] ?? '📄';
$title = $args['title'] ?? '';
$subtitle = $args['subtitle'] ?? '';

// Validation
if (empty($title)) {
    error_log('[SAW Training Header] Warning: Title is required');
    return;
}
?>

<div class="saw-training-header">
    <?php if (!empty($icon)): ?>
    <div class="saw-training-icon"><?php echo esc_html($icon); ?></div>
    <?php endif; ?>
    
    <h1 class="saw-training-title"><?php echo esc_html($title); ?></h1>
    
    <?php if (!empty($subtitle)): ?>
    <p class="saw-training-subtitle"><?php echo esc_html($subtitle); ?></p>
    <?php endif; ?>
</div>
