<?php
/**
 * SAW Table - Form Renderer
 * 
 * Renders form sidebar from configuration.
 * Uses sawt- CSS prefix.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Form Renderer Class
 */
class SAW_Form_Renderer {
    
    const TYPE_TEXT = 'text';
    const TYPE_EMAIL = 'email';
    const TYPE_TEL = 'tel';
    const TYPE_URL = 'url';
    const TYPE_NUMBER = 'number';
    const TYPE_PASSWORD = 'password';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_SELECT = 'select';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_RADIO = 'radio';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime-local';
    const TYPE_TIME = 'time';
    const TYPE_COLOR = 'color';
    const TYPE_FILE = 'file';
    const TYPE_HIDDEN = 'hidden';
    const TYPE_SECTION = 'section';
    const TYPE_DIVIDER = 'divider';
    
    private static $translator = null;
    
    public static function set_translator($translator) {
        self::$translator = $translator;
    }
    
    private static function tr($key, $fallback = null) {
        if (self::$translator && is_callable(self::$translator)) {
            return call_user_func(self::$translator, $key, $fallback);
        }
        return $fallback ?? $key;
    }
    
    /**
     * Render complete form sidebar
     */
    public static function render($config, $item = null, $entity = '') {
        $entity = $entity ?: ($config['entity'] ?? 'item');
        $is_edit = !empty($item);
        $item_id = $item['id'] ?? 0;
        
        $fields = $config['form']['fields'] ?? [];
        
        if (empty($fields)) {
            return '<div class="sawt-alert sawt-alert-warning">' . 
                   self::tr('no_form_fields', 'Žádná pole formuláře') . '</div>';
        }
        
        $singular = $config['singular'] ?? ucfirst($entity);
        $icon = $config['icon'] ?? '📝';
        $title = $is_edit 
            ? self::tr('form_title_edit', 'Upravit') . ' ' . strtolower($singular)
            : self::tr('form_title_new', 'Nový') . ' ' . strtolower($singular);
        
        $route = $config['route'] ?? $entity;
        $base_url = home_url('/admin/' . $route);
        $close_url = $is_edit ? $base_url . '/' . $item_id . '/' : $base_url;
        
        $nonce_action = $is_edit 
            ? 'saw_update_' . str_replace('-', '_', $entity)
            : 'saw_create_' . str_replace('-', '_', $entity);
        
        ob_start();
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
                        <button type="button" class="sawt-sidebar-nav-btn" data-nav="prev" title="<?php echo esc_attr(self::tr('nav_prev', 'Předchozí')); ?>">‹</button>
                        <button type="button" class="sawt-sidebar-nav-btn" data-nav="next" title="<?php echo esc_attr(self::tr('nav_next', 'Další')); ?>">›</button>
                    </div>
                    <?php endif; ?>
                    
                    <a href="<?php echo esc_url($close_url); ?>" class="sawt-sidebar-close" data-close-sidebar title="<?php echo esc_attr(self::tr('close', 'Zavřít')); ?>">×</a>
                </div>
            </header>
            
            <!-- Form -->
            <form class="sawt-form" method="post" enctype="multipart/form-data" data-entity="<?php echo esc_attr($entity); ?>" data-id="<?php echo esc_attr($item_id); ?>">
                
                <?php wp_nonce_field($nonce_action, 'nonce'); ?>
                
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($item_id); ?>">
                <?php endif; ?>
                
                <input type="hidden" name="action" value="<?php echo esc_attr($is_edit ? 'saw_update_' . str_replace('-', '_', $entity) : 'saw_create_' . str_replace('-', '_', $entity)); ?>">
                
                <!-- Body -->
                <div class="sawt-sidebar-content">
                    <div class="sawt-form-body">
                        <?php self::render_fields($fields, $item); ?>
                    </div>
                </div>
                
                <!-- Footer -->
                <footer class="sawt-sidebar-footer">
                    <a href="<?php echo esc_url($close_url); ?>" class="sawt-btn sawt-btn-secondary" data-close-sidebar>
                        <?php echo esc_html(self::tr('btn_cancel', 'Zrušit')); ?>
                    </a>
                    <button type="submit" class="sawt-btn sawt-btn-primary">
                        <?php echo esc_html($is_edit 
                            ? self::tr('btn_save', 'Uložit změny')
                            : self::tr('btn_create', 'Vytvořit')); ?>
                    </button>
                </footer>
                
            </form>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render all fields
     */
    private static function render_fields($fields, $item) {
        foreach ($fields as $key => $field) {
            self::render_field($key, $field, $item);
        }
    }
    
    /**
     * Render single field
     */
    public static function render_field($key, $field, $item) {
        $type = $field['type'] ?? self::TYPE_TEXT;
        
        // Special types
        switch ($type) {
            case self::TYPE_SECTION:
                self::render_section_header($field);
                return;
            case self::TYPE_DIVIDER:
                echo '<hr class="sawt-form-divider">';
                return;
            case self::TYPE_HIDDEN:
                self::render_hidden_field($key, $field, $item);
                return;
        }
        
        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
        $required = !empty($field['required']);
        $help = $field['help'] ?? '';
        $class = $field['class'] ?? '';
        
        $value = $item[$key] ?? ($field['default'] ?? '');
        
        ?>
        <div class="sawt-form-field<?php echo $class ? ' ' . esc_attr($class) : ''; ?>" data-field="<?php echo esc_attr($key); ?>">
            
            <label class="sawt-form-label" for="field-<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="sawt-form-required">*</span>
                <?php endif; ?>
            </label>
            
            <div class="sawt-form-input-wrapper">
                <?php
                switch ($type) {
                    case self::TYPE_TEXTAREA:
                        self::render_textarea($key, $field, $value);
                        break;
                    case self::TYPE_SELECT:
                        self::render_select($key, $field, $value);
                        break;
                    case self::TYPE_CHECKBOX:
                        self::render_checkbox($key, $field, $value);
                        break;
                    case self::TYPE_RADIO:
                        self::render_radio($key, $field, $value);
                        break;
                    case self::TYPE_FILE:
                        self::render_file($key, $field, $value);
                        break;
                    case self::TYPE_COLOR:
                        self::render_color($key, $field, $value);
                        break;
                    default:
                        self::render_input($key, $type, $field, $value);
                }
                ?>
            </div>
            
            <?php if ($help): ?>
                <p class="sawt-form-help"><?php echo esc_html($help); ?></p>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    private static function render_section_header($field) {
        $label = $field['label'] ?? '';
        $icon = $field['icon'] ?? '';
        ?>
        <div class="sawt-form-section">
            <h4 class="sawt-form-section-title">
                <?php if ($icon): ?><span class="sawt-form-section-icon"><?php echo esc_html($icon); ?></span><?php endif; ?>
                <?php echo esc_html($label); ?>
            </h4>
        </div>
        <?php
    }
    
    private static function render_hidden_field($key, $field, $item) {
        $value = $item[$key] ?? ($field['default'] ?? '');
        ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
        <?php
    }
    
    private static function render_input($key, $type, $field, $value) {
        $placeholder = $field['placeholder'] ?? '';
        $required = !empty($field['required']);
        $readonly = !empty($field['readonly']);
        $disabled = !empty($field['disabled']);
        $min = $field['min'] ?? null;
        $max = $field['max'] ?? null;
        $step = $field['step'] ?? null;
        ?>
        <input type="<?php echo esc_attr($type); ?>"
               id="field-<?php echo esc_attr($key); ?>"
               name="<?php echo esc_attr($key); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="sawt-form-input"
               <?php if ($placeholder): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
               <?php if ($required): ?>required<?php endif; ?>
               <?php if ($readonly): ?>readonly<?php endif; ?>
               <?php if ($disabled): ?>disabled<?php endif; ?>
               <?php if ($min !== null): ?>min="<?php echo esc_attr($min); ?>"<?php endif; ?>
               <?php if ($max !== null): ?>max="<?php echo esc_attr($max); ?>"<?php endif; ?>
               <?php if ($step !== null): ?>step="<?php echo esc_attr($step); ?>"<?php endif; ?>>
        <?php
    }
    
    private static function render_textarea($key, $field, $value) {
        $placeholder = $field['placeholder'] ?? '';
        $required = !empty($field['required']);
        $rows = $field['rows'] ?? 4;
        ?>
        <textarea id="field-<?php echo esc_attr($key); ?>"
                  name="<?php echo esc_attr($key); ?>"
                  class="sawt-form-textarea"
                  rows="<?php echo intval($rows); ?>"
                  <?php if ($placeholder): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
                  <?php if ($required): ?>required<?php endif; ?>><?php echo esc_textarea($value); ?></textarea>
        <?php
    }
    
    private static function render_select($key, $field, $value) {
        $options = $field['options'] ?? [];
        $required = !empty($field['required']);
        $placeholder = $field['placeholder'] ?? '';
        
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
        <?php
    }
    
    private static function render_checkbox($key, $field, $value) {
        $checked = !empty($value) || (!isset($value) && !empty($field['default']));
        $label = $field['checkbox_label'] ?? '';
        ?>
        <label class="sawt-form-checkbox-label">
            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="0">
            <input type="checkbox"
                   id="field-<?php echo esc_attr($key); ?>"
                   name="<?php echo esc_attr($key); ?>"
                   value="1"
                   class="sawt-form-checkbox"
                   <?php checked($checked); ?>>
            <?php if ($label): ?>
                <span class="sawt-form-checkbox-text"><?php echo esc_html($label); ?></span>
            <?php endif; ?>
        </label>
        <?php
    }
    
    private static function render_radio($key, $field, $value) {
        $options = $field['options'] ?? [];
        ?>
        <div class="sawt-form-radio-group">
            <?php foreach ($options as $opt_value => $opt_label): ?>
                <label class="sawt-form-radio-label">
                    <input type="radio"
                           name="<?php echo esc_attr($key); ?>"
                           value="<?php echo esc_attr($opt_value); ?>"
                           class="sawt-form-radio"
                           <?php checked($opt_value, $value); ?>>
                    <span class="sawt-form-radio-text"><?php echo esc_html($opt_label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    private static function render_file($key, $field, $value) {
        $accept = $field['accept'] ?? '';
        $required = !empty($field['required']);
        ?>
        <div class="sawt-form-file-wrapper">
            <?php if ($value): ?>
                <div class="sawt-form-file-preview">
                    <?php if (self::is_image($value)): ?>
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
                   <?php if ($accept): ?>accept="<?php echo esc_attr($accept); ?>"<?php endif; ?>
                   <?php if ($required && !$value): ?>required<?php endif; ?>>
        </div>
        <?php
    }
    
    private static function render_color($key, $field, $value) {
        $default = $field['default'] ?? '#3b82f6';
        $value = $value ?: $default;
        ?>
        <div class="sawt-form-color-wrapper">
            <input type="color"
                   id="field-<?php echo esc_attr($key); ?>"
                   name="<?php echo esc_attr($key); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   class="sawt-form-color">
            <input type="text"
                   class="sawt-form-color-text"
                   value="<?php echo esc_attr($value); ?>"
                   pattern="^#[0-9A-Fa-f]{6}$"
                   data-color-input="<?php echo esc_attr($key); ?>">
        </div>
        <?php
    }
    
    private static function is_image($url) {
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    }
    
    public static function has_config($config) {
        return !empty($config['form']['fields']);
    }
}
