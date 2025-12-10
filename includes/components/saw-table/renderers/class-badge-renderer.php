<?php
/**
 * SAW Badge Renderer
 *
 * Renders header badges based on configuration.
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
 * SAW Badge Renderer Class
 */
class SAW_Badge_Renderer {
    
    /**
     * Badge types
     */
    const TYPE_STATUS = 'status';
    const TYPE_ICON_TEXT = 'icon_text';
    const TYPE_CODE = 'code';
    const TYPE_PLAIN = 'plain';
    const TYPE_COUNT = 'count';
    const TYPE_ROLE = 'role';
    const TYPE_FLAG = 'flag';
    const TYPE_IMAGE = 'image';
    const TYPE_COLOR = 'color';
    
    /**
     * Translation function
     * @var callable|null
     */
    private static $translator = null;
    
    /**
     * Set translator function
     */
    public static function set_translator($translator) {
        self::$translator = $translator;
    }
    
    /**
     * Translate key
     */
    private static function tr($key, $fallback = null) {
        if (self::$translator && is_callable(self::$translator)) {
            return call_user_func(self::$translator, $key, $fallback);
        }
        return $fallback ?? $key;
    }
    
    /**
     * Render badge based on config
     */
    public static function render($config, $item) {
        $type = $config['type'] ?? self::TYPE_PLAIN;
        
        switch ($type) {
            case self::TYPE_STATUS:
                return self::render_status($config, $item);
            case self::TYPE_ICON_TEXT:
                return self::render_icon_text($config, $item);
            case self::TYPE_CODE:
                return self::render_code($config, $item);
            case self::TYPE_COUNT:
                return self::render_count($config, $item);
            case self::TYPE_ROLE:
                return self::render_role($config, $item);
            case self::TYPE_FLAG:
                return self::render_flag($config, $item);
            case self::TYPE_IMAGE:
                return self::render_image($config, $item);
            case self::TYPE_COLOR:
                return self::render_color($config, $item);
            default:
                return self::render_plain($config, $item);
        }
    }
    
    /**
     * Render multiple badges
     */
    public static function render_badges($badges, $item) {
        $html = [];
        
        foreach ($badges as $badge) {
            // Check condition
            if (!empty($badge['condition'])) {
                if (!self::evaluate_condition($badge['condition'], $item)) {
                    continue;
                }
            }
            
            // Check permission
            if (!empty($badge['permission'])) {
                if (!self::check_permission($badge['permission'])) {
                    continue;
                }
            }
            
            $rendered = self::render($badge, $item);
            if (!empty($rendered)) {
                $html[] = $rendered;
            }
        }
        
        return implode(' ', $html);
    }
    
    /**
     * Render status badge
     */
    private static function render_status($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if ($value === null || ($value === '' && !isset($config['map']['']))) {
            return '';
        }
        
        $value = (string) $value;
        $map = $config['map'] ?? [];
        $mapping = $map[$value] ?? null;
        
        if (!$mapping) {
            return '';
        }
        
        $icon = $mapping['icon'] ?? '';
        $label = $mapping['label'] ?? $value;
        if (!empty($mapping['label_key'])) {
            $label = self::tr($mapping['label_key'], $label);
        }
        
        $color = $mapping['color'] ?? 'secondary';
        
        return sprintf(
            '<span class="sawt-badge-transparent sawt-badge-%s">%s%s</span>',
            esc_attr($color),
            $icon ? esc_html($icon) . ' ' : '',
            esc_html($label)
        );
    }
    
    /**
     * Render icon + text badge
     */
    private static function render_icon_text($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value) && $value !== '0') {
            return '';
        }
        
        $icon = $config['icon'] ?? '';
        $prefix = $config['prefix'] ?? '';
        $suffix = $config['suffix_text'] ?? '';
        $color = $config['color'] ?? '';
        
        if (!empty($config['label_key'])) {
            $value = self::tr($config['label_key'], $value);
        }
        
        $class = $color ? "sawt-badge-transparent sawt-badge-{$color}" : 'sawt-badge-transparent';
        
        return sprintf(
            '<span class="%s">%s%s%s%s</span>',
            esc_attr($class),
            $icon ? esc_html($icon) . ' ' : '',
            esc_html($prefix),
            esc_html($value),
            esc_html($suffix)
        );
    }
    
    /**
     * Render code badge (monospace)
     */
    private static function render_code($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            return '';
        }
        
        $prefix = $config['prefix'] ?? '';
        
        return sprintf(
            '<span class="sawt-badge-transparent sawt-badge-code">%s%s</span>',
            esc_html($prefix),
            esc_html($value)
        );
    }
    
    /**
     * Render plain badge
     */
    private static function render_plain($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value) && $value !== '0') {
            return '';
        }
        
        $color = $config['color'] ?? '';
        $class = $color ? "sawt-badge-transparent sawt-badge-{$color}" : 'sawt-badge-transparent';
        
        return sprintf(
            '<span class="%s">%s</span>',
            esc_attr($class),
            esc_html($value)
        );
    }
    
    /**
     * Render count badge with Czech pluralization
     */
    private static function render_count($config, $item) {
        $field = $config['field'] ?? '';
        $count = intval($item[$field] ?? 0);
        $icon = $config['icon'] ?? '';
        
        // Czech pluralization
        $suffix = $config['suffix'] ?? [];
        if ($count === 1) {
            $word = $suffix['singular'] ?? '';
            if (!empty($suffix['singular_key'])) {
                $word = self::tr($suffix['singular_key'], $word);
            }
        } elseif ($count >= 2 && $count <= 4) {
            $word = $suffix['few'] ?? '';
            if (!empty($suffix['few_key'])) {
                $word = self::tr($suffix['few_key'], $word);
            }
        } else {
            $word = $suffix['many'] ?? '';
            if (!empty($suffix['many_key'])) {
                $word = self::tr($suffix['many_key'], $word);
            }
        }
        
        $color = $config['color'] ?? '';
        $class = $color ? "sawt-badge-transparent sawt-badge-{$color}" : 'sawt-badge-transparent';
        
        return sprintf(
            '<span class="%s">%s%d %s</span>',
            esc_attr($class),
            $icon ? esc_html($icon) . ' ' : '',
            $count,
            esc_html($word)
        );
    }
    
    /**
     * Render role badge
     */
    private static function render_role($config, $item) {
        $field = $config['field'] ?? 'role';
        $role = $item[$field] ?? '';
        
        if (empty($role)) {
            return '';
        }
        
        $labels = $config['labels'] ?? [
            'super_admin' => self::tr('role_super_admin', 'Super Admin'),
            'admin' => self::tr('role_admin', 'Admin'),
            'super_manager' => self::tr('role_super_manager', 'Super Manager'),
            'manager' => self::tr('role_manager', 'Manager'),
            'terminal' => self::tr('role_terminal', 'Terminál'),
        ];
        
        $label = $labels[$role] ?? $role;
        
        return sprintf(
            '<span class="sawt-role-badge sawt-role-%s">%s</span>',
            esc_attr($role),
            esc_html($label)
        );
    }
    
    /**
     * Render flag badge (large emoji)
     */
    private static function render_flag($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            return '';
        }
        
        return sprintf(
            '<span class="sawt-badge-flag">%s</span>',
            esc_html($value)
        );
    }
    
    /**
     * Render image badge
     */
    private static function render_image($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            $fallback = $config['fallback_icon'] ?? '';
            if ($fallback) {
                return sprintf(
                    '<span class="sawt-header-image-fallback">%s</span>',
                    esc_html($fallback)
                );
            }
            return '';
        }
        
        $size = $config['size'] ?? '48px';
        $rounded = !empty($config['rounded']) ? 'border-radius: 50%;' : 'border-radius: 4px;';
        $alt = $config['alt'] ?? '';
        
        return sprintf(
            '<img src="%s" alt="%s" class="sawt-header-image" style="width: %s; height: %s; object-fit: cover; %s">',
            esc_url($value),
            esc_attr($alt),
            esc_attr($size),
            esc_attr($size),
            $rounded
        );
    }
    
    /**
     * Render color badge with swatch
     */
    private static function render_color($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            return '';
        }
        
        $show_value = !empty($config['show_value']);
        
        $html = '<span class="sawt-badge-transparent sawt-badge-color">';
        $html .= sprintf(
            '<span class="sawt-badge-color-dot" style="background-color: %s;"></span>',
            esc_attr($value)
        );
        if ($show_value) {
            $html .= ' ' . esc_html($value);
        }
        $html .= '</span>';
        
        return $html;
    }
    
    /**
     * Check permission
     */
    private static function check_permission($permission) {
        if (!class_exists('SAW_Table_Permissions')) {
            return true;
        }
        
        $parts = explode(':', $permission);
        $action = $parts[0] ?? 'view';
        $module = $parts[1] ?? '';
        
        return SAW_Table_Permissions::can($module, $action);
    }
    
    /**
     * Evaluate condition
     */
    private static function evaluate_condition($condition, $item) {
        $condition = preg_replace_callback(
            '/\$item\[([\'"])(.+?)\1\]/',
            function($matches) use ($item) {
                $field = $matches[2];
                $value = $item[$field] ?? null;
                
                if (is_null($value)) return 'null';
                if (is_bool($value)) return $value ? 'true' : 'false';
                if (is_string($value)) return "'" . addslashes($value) . "'";
                if (is_array($value)) return 'array()';
                return $value;
            },
            $condition
        );
        
        try {
            return eval("return {$condition};");
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }
    
    /**
     * Get available badge types
     */
    public static function get_types() {
        return [
            self::TYPE_STATUS,
            self::TYPE_ICON_TEXT,
            self::TYPE_CODE,
            self::TYPE_PLAIN,
            self::TYPE_COUNT,
            self::TYPE_ROLE,
            self::TYPE_FLAG,
            self::TYPE_IMAGE,
            self::TYPE_COLOR,
        ];
    }
}
