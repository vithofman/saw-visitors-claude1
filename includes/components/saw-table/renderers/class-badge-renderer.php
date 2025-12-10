<?php
/**
 * SAW Badge Renderer
 *
 * Renders header badges based on configuration.
 * Supports translations, permissions, and multiple badge types.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     1.0.0
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Badge Renderer Class
 *
 * @since 3.0.0
 */
class SAW_Badge_Renderer {
    
    /**
     * Badge types
     */
    const TYPE_STATUS = 'status';       // Value → color + icon + label
    const TYPE_ICON_TEXT = 'icon_text'; // Icon + field value
    const TYPE_CODE = 'code';           // Monospace code
    const TYPE_PLAIN = 'plain';         // Just value
    const TYPE_COUNT = 'count';         // Number + suffix
    const TYPE_ROLE = 'role';           // User role (special colors)
    const TYPE_FLAG = 'flag';           // Large emoji
    const TYPE_IMAGE = 'image';         // Logo/image thumbnail
    
    /**
     * Translation function
     * @var callable|null
     */
    private static $translator = null;
    
    /**
     * Set translator function
     *
     * @param callable $translator Translation function
     */
    public static function set_translator($translator) {
        self::$translator = $translator;
    }
    
    /**
     * Translate key
     *
     * @param string      $key      Translation key
     * @param string|null $fallback Fallback value
     * @return string
     */
    private static function tr($key, $fallback = null) {
        if (self::$translator && is_callable(self::$translator)) {
            return call_user_func(self::$translator, $key, $fallback);
        }
        return $fallback ?? $key;
    }
    
    /**
     * Render badge based on config
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
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
            default:
                return self::render_plain($config, $item);
        }
    }
    
    /**
     * Render multiple badges
     *
     * @param array $badges Array of badge configs
     * @param array $item   Item data
     * @return string HTML
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
            
            // Check permission for badge visibility
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
     * Render status badge with translation support
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_status($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        // Allow empty string but not null
        if ($value === null || ($value === '' && !isset($config['map']['']))) {
            return '';
        }
        
        // Convert to string for map lookup
        $value = (string) $value;
        
        $map = $config['map'] ?? [];
        $mapping = $map[$value] ?? null;
        
        if (!$mapping) {
            return '';
        }
        
        $icon = $mapping['icon'] ?? '';
        
        // Support translation keys in label
        $label = $mapping['label'] ?? $value;
        if (!empty($mapping['label_key'])) {
            $label = self::tr($mapping['label_key'], $label);
        }
        
        $color = $mapping['color'] ?? 'secondary';
        
        return sprintf(
            '<span class="saw-badge-transparent saw-badge-%s">%s%s</span>',
            esc_attr($color),
            $icon ? esc_html($icon) . ' ' : '',
            esc_html($label)
        );
    }
    
    /**
     * Render icon + text badge
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_icon_text($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value) && $value !== '0') {
            return '';
        }
        
        $icon = $config['icon'] ?? '';
        $prefix = $config['prefix'] ?? '';
        $suffix = $config['suffix'] ?? '';
        $color = $config['color'] ?? '';
        
        // Support translation key for label
        if (!empty($config['label_key'])) {
            $value = self::tr($config['label_key'], $value);
        }
        
        $class = $color ? "saw-badge-transparent saw-badge-{$color}" : 'saw-badge-transparent';
        
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
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_code($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            return '';
        }
        
        $prefix = $config['prefix'] ?? '';
        
        return sprintf(
            '<span class="saw-badge-transparent saw-badge-code">%s%s</span>',
            esc_html($prefix),
            esc_html($value)
        );
    }
    
    /**
     * Render plain badge (just value)
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_plain($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value) && $value !== '0') {
            return '';
        }
        
        $color = $config['color'] ?? '';
        $class = $color ? "saw-badge-transparent saw-badge-{$color}" : 'saw-badge-transparent';
        
        return sprintf(
            '<span class="%s">%s</span>',
            esc_attr($class),
            esc_html($value)
        );
    }
    
    /**
     * Render count badge with Czech pluralization
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_count($config, $item) {
        $field = $config['field'] ?? '';
        $count = intval($item[$field] ?? 0);
        $icon = $config['icon'] ?? '';
        
        // Czech pluralization with translation support
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
        $class = $color ? "saw-badge-transparent saw-badge-{$color}" : 'saw-badge-transparent';
        
        return sprintf(
            '<span class="%s">%s%d %s</span>',
            esc_attr($class),
            $icon ? esc_html($icon) . ' ' : '',
            $count,
            esc_html($word)
        );
    }
    
    /**
     * Render role badge (users module)
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_role($config, $item) {
        $field = $config['field'] ?? 'role';
        $role = $item[$field] ?? '';
        
        if (empty($role)) {
            return '';
        }
        
        // Default labels with translation support
        $labels = $config['labels'] ?? [
            'super_admin' => self::tr('role_super_admin', 'Super Admin'),
            'admin' => self::tr('role_admin', 'Admin'),
            'super_manager' => self::tr('role_super_manager', 'Super Manager'),
            'manager' => self::tr('role_manager', 'Manager'),
            'terminal' => self::tr('role_terminal', 'Terminál'),
        ];
        
        $label = $labels[$role] ?? $role;
        
        return sprintf(
            '<span class="saw-role-badge saw-role-%s">%s</span>',
            esc_attr($role),
            esc_html($label)
        );
    }
    
    /**
     * Render flag badge (large emoji)
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_flag($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            return '';
        }
        
        return sprintf(
            '<span class="saw-badge-flag">%s</span>',
            esc_html($value)
        );
    }
    
    /**
     * Render image badge (logo/thumbnail)
     *
     * @param array $config Badge configuration
     * @param array $item   Item data
     * @return string HTML
     */
    private static function render_image($config, $item) {
        $field = $config['field'] ?? '';
        $value = $item[$field] ?? '';
        
        if (empty($value)) {
            // Fallback icon if no image
            $fallback = $config['fallback_icon'] ?? '';
            if ($fallback) {
                return sprintf(
                    '<span class="saw-header-image-fallback">%s</span>',
                    esc_html($fallback)
                );
            }
            return '';
        }
        
        $size = $config['size'] ?? '48px';
        $rounded = !empty($config['rounded']) ? 'border-radius: 50%;' : 'border-radius: 4px;';
        $alt = $config['alt'] ?? '';
        
        return sprintf(
            '<img src="%s" alt="%s" class="saw-header-image" style="width: %s; height: %s; object-fit: cover; %s">',
            esc_url($value),
            esc_attr($alt),
            esc_attr($size),
            esc_attr($size),
            $rounded
        );
    }
    
    /**
     * Check permission
     *
     * @param string $permission Permission string (e.g., "view:users")
     * @return bool
     */
    private static function check_permission($permission) {
        if (!class_exists('SAW_Table_Permissions')) {
            return true; // Fallback: allow if permissions not available
        }
        
        $parts = explode(':', $permission);
        $action = $parts[0] ?? 'view';
        $module = $parts[1] ?? '';
        
        return SAW_Table_Permissions::can($module, $action);
    }
    
    /**
     * Evaluate condition
     *
     * Safely evaluates a condition string against item data.
     *
     * @param string $condition Condition string
     * @param array  $item      Item data
     * @return bool
     */
    private static function evaluate_condition($condition, $item) {
        // Replace $item references with actual values
        $condition = preg_replace_callback(
            '/\$item\[([\'"])(.+?)\1\]/',
            function($matches) use ($item) {
                $field = $matches[2];
                $value = $item[$field] ?? null;
                
                if (is_null($value)) {
                    return 'null';
                }
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }
                if (is_string($value)) {
                    return "'" . addslashes($value) . "'";
                }
                if (is_array($value)) {
                    return 'array()';
                }
                return $value;
            },
            $condition
        );
        
        try {
            // phpcs:ignore WordPress.PHP.RestrictedPHPFunctions.eval_eval
            return eval("return {$condition};");
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }
    
    /**
     * Get available badge types
     *
     * @return array
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
        ];
    }
    
    /**
     * Get badge type info
     *
     * @param string $type Badge type
     * @return array Type information
     */
    public static function get_type_info($type) {
        $info = [
            self::TYPE_STATUS => [
                'label' => 'Status Badge',
                'description' => 'Colored badge based on value mapping',
                'required' => ['field', 'map'],
            ],
            self::TYPE_ICON_TEXT => [
                'label' => 'Icon + Text',
                'description' => 'Icon followed by field value',
                'required' => ['field'],
            ],
            self::TYPE_CODE => [
                'label' => 'Code',
                'description' => 'Monospace code display',
                'required' => ['field'],
            ],
            self::TYPE_PLAIN => [
                'label' => 'Plain Text',
                'description' => 'Simple text value',
                'required' => ['field'],
            ],
            self::TYPE_COUNT => [
                'label' => 'Count',
                'description' => 'Number with pluralized suffix',
                'required' => ['field', 'suffix'],
            ],
            self::TYPE_ROLE => [
                'label' => 'Role Badge',
                'description' => 'User role with special styling',
                'required' => ['field'],
            ],
            self::TYPE_FLAG => [
                'label' => 'Flag/Emoji',
                'description' => 'Large emoji display',
                'required' => ['field'],
            ],
            self::TYPE_IMAGE => [
                'label' => 'Image',
                'description' => 'Logo or thumbnail image',
                'required' => ['field'],
            ],
        ];
        
        return $info[$type] ?? [];
    }
}
