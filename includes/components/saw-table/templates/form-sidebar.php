<?php
/**
 * SAW Table - Form Sidebar Template
 * 
 * Template for create/edit form sidebar.
 * Uses sawt- CSS prefix.
 * 
 * Variables expected:
 * - $config: Module configuration
 * - $item: Item data (null for create, array for edit)
 * - $entity: Entity name
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Templates
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Setup
$config = $config ?? [];
$item = $item ?? null;
$entity = $entity ?? ($config['entity'] ?? 'item');
$is_edit = !empty($item);
$item_id = $item['id'] ?? 0;

$singular = $config['singular'] ?? ucfirst($entity);
$icon = $config['icon'] ?? '📝';
$title = $is_edit 
    ? ($tr('form_title_edit', 'Upravit') ?? 'Upravit') . ' ' . strtolower($singular)
    : ($tr('form_title_new', 'Nový') ?? 'Nový') . ' ' . strtolower($singular);

$route = $config['route'] ?? $entity;
$base_url = home_url('/admin/' . $route);
$close_url = $is_edit ? $base_url . '/' . $item_id . '/' : $base_url;

$fields = $config['form']['fields'] ?? [];
$nonce_action = $is_edit 
    ? 'saw_update_' . str_replace('-', '_', $entity)
    : 'saw_create_' . str_replace('-', '_', $entity);

// Translation helper
$tr = $tr ?? function($key, $fallback = null) {
    return $fallback ?? $key;
};
?>
<div class="sawt-form-sidebar" data-entity="<?php echo esc_attr($entity); ?>" data-mode="<?php echo $is_edit ? 'edit' : 'create'; ?>">
    
    <!-- Header -->
    <header class="sawt-sidebar-header">
        <div class="sawt-sidebar-header-left">
            <span class="sawt-sidebar-icon"><?php echo esc_html($icon); ?></span>
            <h3 class="sawt-sidebar-title"><?php echo esc_html($title); ?></h3>
        </div>
        
        <div class="sawt-sidebar-header-right">
            <?php if ($is_edit): ?>
            <div class="sawt-sidebar-nav">
                <button type="button" class="sawt-sidebar-nav-btn" data-nav="prev" title="<?php echo esc_attr($tr('nav_prev', 'Předchozí')); ?>">‹</button>
                <button type="button" class="sawt-sidebar-nav-btn" data-nav="next" title="<?php echo esc_attr($tr('nav_next', 'Další')); ?>">›</button>
            </div>
            <?php endif; ?>
            
            <a href="<?php echo esc_url($close_url); ?>" class="sawt-sidebar-close" data-close-sidebar title="<?php echo esc_attr($tr('close', 'Zavřít')); ?>">×</a>
        </div>
    </header>
    
    <!-- Form -->
    <form class="sawt-form" method="post" enctype="multipart/form-data" 
          data-entity="<?php echo esc_attr($entity); ?>" 
          data-id="<?php echo esc_attr($item_id); ?>">
        
        <?php wp_nonce_field($nonce_action, 'nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item_id); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="action" value="<?php echo esc_attr($is_edit ? 'saw_update_' . str_replace('-', '_', $entity) : 'saw_create_' . str_replace('-', '_', $entity)); ?>">
        
        <!-- Body -->
        <div class="sawt-sidebar-content">
            <div class="sawt-form-body">
                <?php 
                if (class_exists('SAW_Form_Renderer') && method_exists('SAW_Form_Renderer', 'render_fields')) {
                    // Use Form Renderer if available
                    foreach ($fields as $key => $field) {
                        SAW_Form_Renderer::render_field($key, $field, $item);
                    }
                } else {
                    // Inline rendering fallback
                    foreach ($fields as $key => $field): 
                        $type = $field['type'] ?? 'text';
                        
                        // Section header
                        if ($type === 'section'):
                            ?>
                            <div class="sawt-form-section">
                                <h4 class="sawt-form-section-title">
                                    <?php if (!empty($field['icon'])): ?>
                                        <span class="sawt-form-section-icon"><?php echo esc_html($field['icon']); ?></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($field['label'] ?? ''); ?>
                                </h4>
                            </div>
                            <?php
                            continue;
                        endif;
                        
                        // Divider
                        if ($type === 'divider'):
                            echo '<hr class="sawt-form-divider">';
                            continue;
                        endif;
                        
                        // Hidden field
                        if ($type === 'hidden'):
                            $value = $item[$key] ?? ($field['default'] ?? '');
                            ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                            <?php
                            continue;
                        endif;
                        
                        // Regular field
                        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
                        $required = !empty($field['required']);
                        $help = $field['help'] ?? '';
                        $value = $item[$key] ?? ($field['default'] ?? '');
                        $placeholder = $field['placeholder'] ?? '';
                        ?>
                        <div class="sawt-form-field" data-field="<?php echo esc_attr($key); ?>">
                            <label class="sawt-form-label" for="field-<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($label); ?>
                                <?php if ($required): ?>
                                    <span class="sawt-form-required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <div class="sawt-form-input-wrapper">
                                <?php if ($type === 'textarea'): ?>
                                    <textarea id="field-<?php echo esc_attr($key); ?>"
                                              name="<?php echo esc_attr($key); ?>"
                                              class="sawt-form-textarea"
                                              rows="<?php echo intval($field['rows'] ?? 4); ?>"
                                              <?php if ($placeholder): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
                                              <?php if ($required): ?>required<?php endif; ?>><?php echo esc_textarea($value); ?></textarea>
                                
                                <?php elseif ($type === 'select'): ?>
                                    <?php
                                    $options = $field['options'] ?? [];
                                    if (!empty($field['options_callback']) && is_callable($field['options_callback'])) {
                                        $options = call_user_func($field['options_callback']);
                                    }
                                    ?>
                                    <select id="field-<?php echo esc_attr($key); ?>"
                                            name="<?php echo esc_attr($key); ?>"
                                            class="sawt-form-select"
                                            <?php if ($required): ?>required<?php endif; ?>>
                                        <?php if ($placeholder): ?>
                                            <option value=""><?php echo esc_html($placeholder); ?></option>
                                        <?php endif; ?>
                                        <?php foreach ($options as $opt_value => $opt_label): ?>
                                            <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, $value); ?>>
                                                <?php echo esc_html($opt_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                
                                <?php elseif ($type === 'checkbox'): ?>
                                    <?php $checked = !empty($value) || (!isset($value) && !empty($field['default'])); ?>
                                    <label class="sawt-form-checkbox-label">
                                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="0">
                                        <input type="checkbox"
                                               id="field-<?php echo esc_attr($key); ?>"
                                               name="<?php echo esc_attr($key); ?>"
                                               value="1"
                                               class="sawt-form-checkbox"
                                               <?php checked($checked); ?>>
                                        <?php if (!empty($field['checkbox_label'])): ?>
                                            <span class="sawt-form-checkbox-text"><?php echo esc_html($field['checkbox_label']); ?></span>
                                        <?php endif; ?>
                                    </label>
                                
                                <?php elseif ($type === 'color'): ?>
                                    <?php $value = $value ?: ($field['default'] ?? '#3b82f6'); ?>
                                    <div class="sawt-form-color-wrapper">
                                        <input type="color"
                                               id="field-<?php echo esc_attr($key); ?>"
                                               name="<?php echo esc_attr($key); ?>"
                                               value="<?php echo esc_attr($value); ?>"
                                               class="sawt-form-color">
                                        <input type="text"
                                               class="sawt-form-color-text"
                                               value="<?php echo esc_attr($value); ?>"
                                               data-color-input="<?php echo esc_attr($key); ?>">
                                    </div>
                                
                                <?php elseif ($type === 'file'): ?>
                                    <div class="sawt-form-file-wrapper">
                                        <?php if ($value): ?>
                                            <div class="sawt-form-file-preview">
                                                <?php 
                                                $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                                                $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                                ?>
                                                <?php if ($is_image): ?>
                                                    <img src="<?php echo esc_url($value); ?>" alt="" class="sawt-form-file-image">
                                                <?php else: ?>
                                                    <span class="sawt-form-file-name"><?php echo esc_html(basename($value)); ?></span>
                                                <?php endif; ?>
                                                <button type="button" class="sawt-form-file-remove" data-field="<?php echo esc_attr($key); ?>">×</button>
                                            </div>
                                            <input type="hidden" name="<?php echo esc_attr($key); ?>_existing" value="<?php echo esc_url($value); ?>">
                                        <?php endif; ?>
                                        <input type="file"
                                               id="field-<?php echo esc_attr($key); ?>"
                                               name="<?php echo esc_attr($key); ?>"
                                               class="sawt-form-file"
                                               <?php if (!empty($field['accept'])): ?>accept="<?php echo esc_attr($field['accept']); ?>"<?php endif; ?>
                                               <?php if ($required && !$value): ?>required<?php endif; ?>>
                                    </div>
                                
                                <?php else: ?>
                                    <?php // text, email, tel, url, number, password, date, datetime-local, time ?>
                                    <input type="<?php echo esc_attr($type); ?>"
                                           id="field-<?php echo esc_attr($key); ?>"
                                           name="<?php echo esc_attr($key); ?>"
                                           value="<?php echo esc_attr($value); ?>"
                                           class="sawt-form-input"
                                           <?php if ($placeholder): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
                                           <?php if ($required): ?>required<?php endif; ?>
                                           <?php if (!empty($field['readonly'])): ?>readonly<?php endif; ?>
                                           <?php if (!empty($field['disabled'])): ?>disabled<?php endif; ?>
                                           <?php if (isset($field['min'])): ?>min="<?php echo esc_attr($field['min']); ?>"<?php endif; ?>
                                           <?php if (isset($field['max'])): ?>max="<?php echo esc_attr($field['max']); ?>"<?php endif; ?>
                                           <?php if (isset($field['step'])): ?>step="<?php echo esc_attr($field['step']); ?>"<?php endif; ?>>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($help): ?>
                                <p class="sawt-form-help"><?php echo esc_html($help); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach;
                }
                ?>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="sawt-sidebar-footer">
            <a href="<?php echo esc_url($close_url); ?>" class="sawt-btn sawt-btn-secondary" data-close-sidebar>
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
            <button type="submit" class="sawt-btn sawt-btn-primary">
                <?php echo esc_html($is_edit 
                    ? $tr('btn_save', 'Uložit změny')
                    : $tr('btn_create', 'Vytvořit')); ?>
            </button>
        </footer>
        
    </form>
    
</div>
