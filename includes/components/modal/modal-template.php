<?php
/**
 * SAW Modal Template
 * 
 * Template pro zobrazení modálního okna.
 * Používá proměnné $id a $config z class-saw-component-modal.php
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract config variables
$title = $config['title'] ?? '';
$content = $config['content'] ?? '';
$size = $config['size'] ?? 'medium';
$show_close = $config['show_close'] ?? true;
$show_footer = $config['show_footer'] ?? false;
$footer_buttons = $config['footer_buttons'] ?? array();
$custom_class = $config['custom_class'] ?? '';
$close_on_backdrop = $config['close_on_backdrop'] ?? true;
$close_on_escape = $config['close_on_escape'] ?? true;
$ajax_enabled = $config['ajax_enabled'] ?? false;
$ajax_action = $config['ajax_action'] ?? '';
$ajax_data = $config['ajax_data'] ?? array();
$auto_open = $config['auto_open'] ?? false;

// Build CSS classes
$modal_classes = array('saw-modal');
if (!empty($custom_class)) {
    $modal_classes[] = $custom_class;
}
if ($auto_open) {
    $modal_classes[] = 'saw-modal-open';
}

$panel_classes = array('saw-modal-panel');
$panel_classes[] = 'saw-modal-' . $size;
?>

<div id="saw-modal-<?php echo esc_attr($id); ?>" 
     class="<?php echo esc_attr(implode(' ', $modal_classes)); ?>"
     data-modal-id="<?php echo esc_attr($id); ?>"
     data-close-backdrop="<?php echo $close_on_backdrop ? '1' : '0'; ?>"
     data-close-escape="<?php echo $close_on_escape ? '1' : '0'; ?>"
     data-ajax-enabled="<?php echo $ajax_enabled ? '1' : '0'; ?>"
     data-ajax-action="<?php echo esc_attr($ajax_action); ?>"
     data-ajax-data="<?php echo esc_attr(wp_json_encode($ajax_data)); ?>">
    
    <div class="saw-modal-overlay"></div>
    
    <div class="<?php echo esc_attr(implode(' ', $panel_classes)); ?>">
        
        <!-- Modal Header -->
        <div class="saw-modal-header">
            <h2 class="saw-modal-title"><?php echo esc_html($title); ?></h2>
            
            <?php if ($show_close): ?>
                <button type="button" class="saw-modal-close" data-modal-close>
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Modal Body -->
        <div class="saw-modal-body">
            <?php if ($ajax_enabled): ?>
                <div class="saw-modal-loading">
                    <div class="saw-spinner"></div>
                    <p>Načítám...</p>
                </div>
            <?php else: ?>
                <?php echo $content; ?>
            <?php endif; ?>
        </div>
        
        <!-- Modal Footer (optional) -->
        <?php if ($show_footer && !empty($footer_buttons)): ?>
            <div class="saw-modal-footer">
                <?php foreach ($footer_buttons as $button): ?>
                    <?php
                    $btn_label = $button['label'] ?? 'Button';
                    $btn_class = $button['class'] ?? 'saw-button';
                    $btn_action = $button['action'] ?? '';
                    $btn_data = $button['data'] ?? array();
                    ?>
                    <button type="button" 
                            class="<?php echo esc_attr($btn_class); ?>"
                            data-modal-action="<?php echo esc_attr($btn_action); ?>"
                            <?php foreach ($btn_data as $key => $value): ?>
                                data-<?php echo esc_attr($key); ?>="<?php echo esc_attr($value); ?>"
                            <?php endforeach; ?>>
                        <?php echo esc_html($btn_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php if ($auto_open): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof SAWModal !== 'undefined') {
        SAWModal.open('<?php echo esc_js($id); ?>');
    }
});
</script>
<?php endif; ?>
