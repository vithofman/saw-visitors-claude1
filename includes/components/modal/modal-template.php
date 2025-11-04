<?php
/**
 * SAW Modal Template
 * 
 * @package SAW_Visitors
 * @version 4.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract config variables
$title = $config['title'] ?? '';
$content = $config['content'] ?? '';
$size = $config['size'] ?? 'md';
$show_close = $config['show_close'] ?? true;
$show_footer = $config['show_footer'] ?? false;
$footer_buttons = $config['footer_buttons'] ?? array();
$header_actions = $config['header_actions'] ?? array();
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
// ✅ OPRAVENO: Používá 'active' místo 'saw-modal-open'
if ($auto_open) {
    $modal_classes[] = 'active';
}

// Size mapping
$size_map = array(
    'small' => 'sm',
    'medium' => 'md',
    'large' => 'lg',
    'fullscreen' => 'xl',
);
$size_class = $size_map[$size] ?? $size;
?>

<div id="saw-modal-<?php echo esc_attr($id); ?>" 
     class="<?php echo esc_attr(implode(' ', $modal_classes)); ?>"
     data-modal-id="<?php echo esc_attr($id); ?>"
     data-close-backdrop="<?php echo $close_on_backdrop ? '1' : '0'; ?>"
     data-close-escape="<?php echo $close_on_escape ? '1' : '0'; ?>"
     data-ajax-enabled="<?php echo $ajax_enabled ? '1' : '0'; ?>"
     data-ajax-action="<?php echo esc_attr($ajax_action); ?>"
     data-ajax-data="<?php echo esc_attr(wp_json_encode($ajax_data)); ?>">
    
    <div class="saw-modal-content saw-modal-content-<?php echo esc_attr($size_class); ?>">
        
        <!-- Modal Header -->
        <div class="saw-modal-header">
            <h2 class="saw-modal-title"><?php echo esc_html($title); ?></h2>
            
            <div class="saw-modal-actions">
                <?php if (!empty($header_actions)): ?>
                    <?php foreach ($header_actions as $action): ?>
                        <?php
                        $action_type = $action['type'] ?? 'custom';
                        $action_label = $action['label'] ?? '';
                        $action_icon = $action['icon'] ?? 'dashicons-admin-generic';
                        $action_url = $action['url'] ?? '';
                        $action_confirm = $action['confirm'] ?? false;
                        $action_confirm_msg = $action['confirm_message'] ?? 'Opravdu chcete provést tuto akci?';
                        $action_ajax = $action['ajax_action'] ?? '';
                        $action_callback = $action['callback'] ?? '';
                        $action_class = $action['class'] ?? '';
                        
                        $data_attrs = array(
                            'data-action-type' => $action_type,
                        );
                        
                        if ($action_url) {
                            $data_attrs['data-action-url'] = $action_url;
                        }
                        
                        if ($action_confirm) {
                            $data_attrs['data-action-confirm'] = '1';
                            $data_attrs['data-action-confirm-message'] = $action_confirm_msg;
                        }
                        
                        if ($action_ajax) {
                            $data_attrs['data-action-ajax'] = $action_ajax;
                        }
                        
                        if ($action_callback) {
                            $data_attrs['data-action-callback'] = $action_callback;
                        }
                        
                        $data_attrs_string = '';
                        foreach ($data_attrs as $attr => $value) {
                            $data_attrs_string .= ' ' . $attr . '="' . esc_attr($value) . '"';
                        }
                        
                        $btn_classes = array('saw-modal-action-btn', $action_type);
                        if ($action_class) {
                            $btn_classes[] = $action_class;
                        }
                        ?>
                        
                        <button type="button" 
                                class="<?php echo esc_attr(implode(' ', $btn_classes)); ?>"
                                <?php echo $data_attrs_string; ?>
                                title="<?php echo esc_attr($action_label ?: ucfirst($action_type)); ?>">
                            <span class="dashicons <?php echo esc_attr($action_icon); ?>"></span>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($show_close): ?>
                    <button type="button" class="saw-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                <?php endif; ?>
            </div>
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
                    $btn_class = $button['class'] ?? 'saw-btn saw-btn-secondary';
                    $btn_action = $button['action'] ?? '';
                    ?>
                    <button type="button" 
                            class="<?php echo esc_attr($btn_class); ?>"
                            <?php if ($btn_action): ?>data-action="<?php echo esc_attr($btn_action); ?>"<?php endif; ?>>
                        <?php echo esc_html($btn_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>