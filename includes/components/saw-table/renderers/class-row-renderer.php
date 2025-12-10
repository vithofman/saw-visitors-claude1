<?php
/**
 * SAW Row Renderer
 *
 * Renders info rows in detail sidebar sections AND cell values in tables.
 * Uses sawt- CSS prefix.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     2.1.0 - FIXED: Added missing render_value() method
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Row Renderer Class
 */
class SAW_Row_Renderer {
    
    /**
     * Format types
     */
    const FORMAT_TEXT = 'text';
    const FORMAT_DATE = 'date';
    const FORMAT_DATETIME = 'datetime';
    const FORMAT_TIME = 'time';
    const FORMAT_PHONE = 'phone';
    const FORMAT_EMAIL = 'email';
    const FORMAT_URL = 'url';
    const FORMAT_BADGE = 'badge';
    const FORMAT_BOOLEAN = 'boolean';
    const FORMAT_NUMBER = 'number';
    const FORMAT_CURRENCY = 'currency';
    const FORMAT_PERCENT = 'percent';
    const FORMAT_HTML = 'html';
    const FORMAT_IMAGE = 'image';
    const FORMAT_CODE = 'code';
    const FORMAT_COLOR = 'color';
    
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
     * =========================================================
     * RENDER VALUE - PUBLIC METHOD FOR TABLE CELLS
     * =========================================================
     * 
     * This is the PUBLIC method called by SAW_Table_Renderer
     * and SAW_Table to render cell values in tables.
     * 
     * @param mixed  $value   The value to render
     * @param string $type    Column type (text, badge, code, etc.)
     * @param array  $config  Column configuration
     * @param array  $item    Full item data (for callbacks)
     * @return string HTML
     */
    public static function render_value($value, $type, $config = [], $item = []) {
        // Handle custom callback FIRST
        if (!empty($config['callback']) && is_callable($config['callback'])) {
            return call_user_func($config['callback'], $value, $item);
        }
        
        // Handle badge type with map
        if ($type === 'badge' && !empty($config['map'])) {
            $key = (string) $value;
            $map = $config['map'][$key] ?? null;
            
            if ($map) {
                $label = $map['label'] ?? $value;
                $color = $map['color'] ?? 'secondary';
                $icon = $map['icon'] ?? '';
                
                return sprintf(
                    '<span class="sawt-badge sawt-badge-%s">%s%s</span>',
                    esc_attr($color),
                    $icon ? esc_html($icon) . ' ' : '',
                    esc_html($label)
                );
            }
            
            return esc_html($value);
        }
        
        // Handle image type
        if ($type === 'image') {
            if (empty($value)) {
                $fallback = $config['fallback'] ?? $config['fallback_icon'] ?? '';
                if ($fallback) {
                    return '<span class="sawt-image-fallback">' . esc_html($fallback) . '</span>';
                }
                return '';
            }
            
            $size = $config['size'] ?? '32px';
            $rounded = !empty($config['rounded']) ? 'border-radius:50%;' : 'border-radius:4px;';
            
            return sprintf(
                '<img src="%s" alt="" class="sawt-table-image" style="width:%s;height:%s;object-fit:cover;%s">',
                esc_url($value),
                esc_attr($size),
                esc_attr($size),
                $rounded
            );
        }
        
        // Handle code type
        if ($type === 'code') {
            if (empty($value)) {
                return '<span class="sawt-text-muted">—</span>';
            }
            return '<code class="sawt-code">' . esc_html($value) . '</code>';
        }
        
        // Handle color type
        if ($type === 'color' || $type === 'color_badge') {
            if (empty($value)) {
                return '<span class="sawt-text-muted">—</span>';
            }
            return sprintf(
                '<div class="sawt-color-swatch" style="background-color:%s;width:24px;height:24px;border-radius:4px;border:2px solid #e5e7eb;" title="%s"></div>',
                esc_attr($value),
                esc_attr($value)
            );
        }
        
        // Handle boolean type
        if ($type === 'boolean') {
            $true_label = $config['true_label'] ?? '✓';
            $false_label = $config['false_label'] ?? '—';
            $true_color = $config['true_color'] ?? 'success';
            $false_color = $config['false_color'] ?? 'muted';
            
            if ($value) {
                return '<span class="sawt-badge sawt-badge-' . esc_attr($true_color) . '">' . esc_html($true_label) . '</span>';
            }
            return '<span class="sawt-text-' . esc_attr($false_color) . '">' . esc_html($false_label) . '</span>';
        }
        
        // Handle date type
        if ($type === 'date') {
            if (empty($value)) return '<span class="sawt-text-muted">—</span>';
            $format = $config['date_format'] ?? 'j. n. Y';
            return esc_html(date_i18n($format, strtotime($value)));
        }
        
        // Handle datetime type
        if ($type === 'datetime') {
            if (empty($value)) return '<span class="sawt-text-muted">—</span>';
            $format = $config['datetime_format'] ?? 'j. n. Y H:i';
            return esc_html(date_i18n($format, strtotime($value)));
        }
        
        // Handle number type
        if ($type === 'number') {
            if (!is_numeric($value)) return esc_html($value);
            $decimals = $config['decimals'] ?? 0;
            return esc_html(number_format((float)$value, $decimals, ',', ' '));
        }
        
        // Handle currency type
        if ($type === 'currency') {
            if (!is_numeric($value)) return esc_html($value);
            $currency = $config['currency'] ?? 'Kč';
            $decimals = $config['decimals'] ?? 0;
            return esc_html(number_format((float)$value, $decimals, ',', ' ') . ' ' . $currency);
        }
        
        // Handle custom type (callback should have been handled above)
        if ($type === 'custom') {
            // If we get here without callback, just output value
            if (empty($value) && $value !== '0') {
                return '<span class="sawt-text-muted">—</span>';
            }
            return esc_html($value);
        }
        
        // Default: text
        if (empty($value) && $value !== '0') {
            return '<span class="sawt-text-muted">—</span>';
        }
        
        $output = esc_html($value);
        
        if (!empty($config['bold'])) {
            $output = '<strong>' . $output . '</strong>';
        }
        
        return $output;
    }
    
    /**
     * =========================================================
     * RENDER INFO ROW - FOR DETAIL SIDEBAR
     * =========================================================
     */
    public static function render($row_config, $item) {
        // Check condition
        if (!empty($row_config['condition'])) {
            if (!self::evaluate_condition($row_config['condition'], $item)) {
                return '';
            }
        }
        
        // Get field value
        $field = $row_config['field'] ?? '';
        $value = self::get_field_value($field, $item);
        
        // Check if empty
        if (self::is_empty($value)) {
            if (empty($row_config['empty_text']) && empty($row_config['show_empty'])) {
                return '';
            }
            $value = $row_config['empty_text'] ?? '—';
        }
        
        // Get label
        $label = $row_config['label'] ?? '';
        if (!empty($row_config['label_key'])) {
            $label = self::tr($row_config['label_key'], $label);
        }
        
        // Format value
        $format = $row_config['format'] ?? self::FORMAT_TEXT;
        $formatted_value = self::format_value($value, $format, $row_config, $item);
        
        // Build classes
        $row_class = 'sawt-info-row';
        if (!empty($row_config['class'])) {
            $row_class .= ' ' . $row_config['class'];
        }
        if (!empty($row_config['stacked'])) {
            $row_class .= ' is-stacked';
        }
        
        $val_class = 'sawt-info-val';
        if (!empty($row_config['bold'])) {
            $val_class .= ' sawt-info-val-bold';
        }
        if (!empty($row_config['highlight'])) {
            $val_class .= ' sawt-info-val-highlight';
        }
        if (!empty($row_config['muted'])) {
            $val_class .= ' sawt-info-val-muted';
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($row_class); ?>">
            <span class="sawt-info-label"><?php echo esc_html($label); ?></span>
            <span class="<?php echo esc_attr($val_class); ?>"><?php 
                // Some formats output HTML, others need escaping
                $html_formats = [
                    self::FORMAT_HTML, 
                    self::FORMAT_BADGE, 
                    self::FORMAT_IMAGE, 
                    self::FORMAT_PHONE, 
                    self::FORMAT_EMAIL, 
                    self::FORMAT_URL,
                    self::FORMAT_COLOR,
                    self::FORMAT_CODE,
                ];
                if (in_array($format, $html_formats)) {
                    echo $formatted_value;
                } else {
                    echo esc_html($formatted_value);
                }
            ?></span>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render multiple rows
     */
    public static function render_rows($rows, $item) {
        $html = [];
        
        foreach ($rows as $row) {
            $rendered = self::render($row, $item);
            if (!empty($rendered)) {
                $html[] = $rendered;
            }
        }
        
        return implode('', $html);
    }
    
    /**
     * Get field value from item (supports dot notation)
     */
    private static function get_field_value($field, $item) {
        if (empty($field)) {
            return null;
        }
        
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $item;
            
            foreach ($parts as $part) {
                if (!is_array($value) || !isset($value[$part])) {
                    return null;
                }
                $value = $value[$part];
            }
            
            return $value;
        }
        
        return $item[$field] ?? null;
    }
    
    /**
     * Check if value is empty
     */
    private static function is_empty($value) {
        if ($value === null) return true;
        if ($value === '') return true;
        if (is_array($value) && empty($value)) return true;
        return false;
    }
    
    /**
     * Format value based on type (for detail sidebar)
     */
    private static function format_value($value, $format, $row_config = [], $item = []) {
        switch ($format) {
            case self::FORMAT_DATE:
                return self::format_date($value, $row_config);
                
            case self::FORMAT_DATETIME:
                return self::format_datetime($value, $row_config);
                
            case self::FORMAT_TIME:
                return self::format_time($value, $row_config);
                
            case self::FORMAT_PHONE:
                return self::format_phone($value);
                
            case self::FORMAT_EMAIL:
                return self::format_email($value);
                
            case self::FORMAT_URL:
                return self::format_url($value, $row_config);
                
            case self::FORMAT_BADGE:
                return self::format_badge($value, $row_config);
                
            case self::FORMAT_BOOLEAN:
                return self::format_boolean($value, $row_config);
                
            case self::FORMAT_NUMBER:
                return self::format_number($value, $row_config);
                
            case self::FORMAT_CURRENCY:
                return self::format_currency($value, $row_config);
                
            case self::FORMAT_PERCENT:
                return self::format_percent($value, $row_config);
                
            case self::FORMAT_IMAGE:
                return self::format_image($value, $row_config);
                
            case self::FORMAT_CODE:
                return self::format_code($value);
                
            case self::FORMAT_COLOR:
                return self::format_color($value);
                
            case self::FORMAT_HTML:
                return wp_kses_post($value);
                
            default:
                return self::format_text($value, $row_config);
        }
    }
    
    // =========================================================
    // PRIVATE FORMAT METHODS
    // =========================================================
    
    private static function format_text($value, $row_config = []) {
        $truncate = $row_config['truncate'] ?? 0;
        
        if ($truncate > 0 && strlen($value) > $truncate) {
            $value = substr($value, 0, $truncate) . '...';
        }
        
        return $value;
    }
    
    private static function format_date($value, $row_config = []) {
        if (empty($value)) return '—';
        $format = $row_config['date_format'] ?? 'j. n. Y';
        return date_i18n($format, strtotime($value));
    }
    
    private static function format_datetime($value, $row_config = []) {
        if (empty($value)) return '—';
        $format = $row_config['datetime_format'] ?? 'j. n. Y H:i';
        return date_i18n($format, strtotime($value));
    }
    
    private static function format_time($value, $row_config = []) {
        if (empty($value)) return '—';
        $format = $row_config['time_format'] ?? 'H:i';
        return date_i18n($format, strtotime($value));
    }
    
    private static function format_phone($value) {
        if (empty($value)) return '—';
        return sprintf('<a href="tel:%s" class="sawt-link">%s</a>', esc_attr($value), esc_html($value));
    }
    
    private static function format_email($value) {
        if (empty($value)) return '—';
        return sprintf('<a href="mailto:%s" class="sawt-link">%s</a>', esc_attr($value), esc_html($value));
    }
    
    private static function format_url($value, $row_config = []) {
        if (empty($value)) return '—';
        $text = $row_config['url_text'] ?? $value;
        $target = $row_config['url_target'] ?? '_blank';
        return sprintf('<a href="%s" target="%s" class="sawt-link">%s</a>', esc_url($value), esc_attr($target), esc_html($text));
    }
    
    private static function format_badge($value, $row_config = []) {
        $map = $row_config['map'] ?? [];
        $key = (string) $value;
        
        if (isset($map[$key])) {
            $badge = $map[$key];
            $label = $badge['label'] ?? $value;
            $color = $badge['color'] ?? 'secondary';
            $icon = $badge['icon'] ?? '';
            
            return sprintf(
                '<span class="sawt-badge sawt-badge-%s">%s%s</span>',
                esc_attr($color),
                $icon ? esc_html($icon) . ' ' : '',
                esc_html($label)
            );
        }
        
        return esc_html($value);
    }
    
    private static function format_boolean($value, $row_config = []) {
        $true_label = $row_config['true_label'] ?? self::tr('yes', 'Ano');
        $false_label = $row_config['false_label'] ?? self::tr('no', 'Ne');
        
        return $value ? $true_label : $false_label;
    }
    
    private static function format_number($value, $row_config = []) {
        if (!is_numeric($value)) return $value;
        
        $decimals = $row_config['decimals'] ?? 0;
        $dec_point = $row_config['decimal_point'] ?? ',';
        $thousands = $row_config['thousands_separator'] ?? ' ';
        
        return number_format((float) $value, $decimals, $dec_point, $thousands);
    }
    
    private static function format_currency($value, $row_config = []) {
        if (!is_numeric($value)) return $value;
        
        $currency = $row_config['currency'] ?? 'Kč';
        $decimals = $row_config['decimals'] ?? 0;
        
        return number_format((float) $value, $decimals, ',', ' ') . ' ' . $currency;
    }
    
    private static function format_percent($value, $row_config = []) {
        if (!is_numeric($value)) return $value;
        
        $decimals = $row_config['decimals'] ?? 0;
        $is_decimal = $row_config['is_decimal'] ?? ($value >= 0 && $value <= 1);
        
        if ($is_decimal) {
            $value = $value * 100;
        }
        
        return number_format((float) $value, $decimals, ',', '') . ' %';
    }
    
    private static function format_image($value, $row_config = []) {
        if (empty($value)) return '<span class="sawt-text-muted">—</span>';
        
        $size = $row_config['image_size'] ?? '40px';
        
        return sprintf(
            '<img src="%s" alt="" class="sawt-info-image" style="max-width:%s;max-height:%s;">',
            esc_url($value),
            esc_attr($size),
            esc_attr($size)
        );
    }
    
    private static function format_code($value) {
        return '<code class="sawt-code">' . esc_html($value) . '</code>';
    }
    
    private static function format_color($value) {
        if (empty($value)) return '<span class="sawt-text-muted">—</span>';
        
        return sprintf(
            '<span class="sawt-color-inline"><span class="sawt-color-swatch-sm" style="background-color:%s;"></span><code class="sawt-code-sm">%s</code></span>',
            esc_attr($value),
            esc_html(strtoupper($value))
        );
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
}
