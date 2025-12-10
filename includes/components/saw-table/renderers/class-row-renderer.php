<?php
/**
 * SAW Row Renderer
 *
 * Renders info rows in detail sidebar sections.
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
     * Render a single info row
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
                    self::FORMAT_COLOR
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
     * Format value based on type
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
    
    /**
     * Format text value
     */
    private static function format_text($value, $row_config = []) {
        $truncate = $row_config['truncate'] ?? 0;
        
        if ($truncate > 0 && strlen($value) > $truncate) {
            $value = substr($value, 0, $truncate) . '…';
        }
        
        return $value;
    }
    
    /**
     * Format date value
     */
    private static function format_date($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        $format = $row_config['date_format'] ?? 'j. n. Y';
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        
        if (!$timestamp) {
            return $value;
        }
        
        return date_i18n($format, $timestamp);
    }
    
    /**
     * Format datetime value
     */
    private static function format_datetime($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        $format = $row_config['datetime_format'] ?? 'j. n. Y H:i';
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        
        if (!$timestamp) {
            return $value;
        }
        
        return date_i18n($format, $timestamp);
    }
    
    /**
     * Format time value
     */
    private static function format_time($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        $format = $row_config['time_format'] ?? 'H:i';
        $timestamp = strtotime($value);
        
        if (!$timestamp) {
            return $value;
        }
        
        return date_i18n($format, $timestamp);
    }
    
    /**
     * Format phone value
     */
    private static function format_phone($value) {
        if (empty($value)) {
            return '—';
        }
        
        $clean = preg_replace('/[^0-9+]/', '', $value);
        
        return sprintf(
            '<a href="tel:%s" class="sawt-info-link">%s</a>',
            esc_attr($clean),
            esc_html($value)
        );
    }
    
    /**
     * Format email value
     */
    private static function format_email($value) {
        if (empty($value)) {
            return '—';
        }
        
        return sprintf(
            '<a href="mailto:%s" class="sawt-info-link">%s</a>',
            esc_attr($value),
            esc_html($value)
        );
    }
    
    /**
     * Format URL value
     */
    private static function format_url($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        if (!preg_match('/^https?:\/\//', $value)) {
            $value = 'https://' . $value;
        }
        
        $label = $row_config['url_label'] ?? parse_url($value, PHP_URL_HOST) ?? $value;
        $target = !empty($row_config['url_new_tab']) ? ' target="_blank" rel="noopener"' : '';
        
        return sprintf(
            '<a href="%s" class="sawt-info-link"%s>%s</a>',
            esc_url($value),
            $target,
            esc_html($label)
        );
    }
    
    /**
     * Format value as badge
     */
    private static function format_badge($value, $row_config = []) {
        if ($value === null || $value === '') {
            return '<span class="sawt-text-muted">—</span>';
        }
        
        $value = (string) $value;
        $map = $row_config['map'] ?? [];
        
        if (isset($map[$value])) {
            $config = $map[$value];
            $label = $config['label'] ?? $value;
            
            if (!empty($config['label_key'])) {
                $label = self::tr($config['label_key'], $label);
            }
            
            $color = $config['color'] ?? 'secondary';
            $icon = $config['icon'] ?? '';
            
            return sprintf(
                '<span class="sawt-badge sawt-badge-%s">%s%s</span>',
                esc_attr($color),
                $icon ? esc_html($icon) . ' ' : '',
                esc_html($label)
            );
        }
        
        return sprintf(
            '<span class="sawt-badge sawt-badge-secondary">%s</span>',
            esc_html($value)
        );
    }
    
    /**
     * Format boolean value
     */
    private static function format_boolean($value, $row_config = []) {
        $true_label = $row_config['true_label'] ?? self::tr('yes', 'Ano');
        $false_label = $row_config['false_label'] ?? self::tr('no', 'Ne');
        
        if (!empty($row_config['true_label_key'])) {
            $true_label = self::tr($row_config['true_label_key'], $true_label);
        }
        if (!empty($row_config['false_label_key'])) {
            $false_label = self::tr($row_config['false_label_key'], $false_label);
        }
        
        return $value ? $true_label : $false_label;
    }
    
    /**
     * Format number value
     */
    private static function format_number($value, $row_config = []) {
        if (!is_numeric($value)) {
            return $value;
        }
        
        $decimals = $row_config['decimals'] ?? 0;
        $dec_point = $row_config['decimal_point'] ?? ',';
        $thousands = $row_config['thousands_separator'] ?? ' ';
        $prefix = $row_config['prefix'] ?? '';
        $suffix = $row_config['suffix'] ?? '';
        
        $formatted = number_format((float) $value, $decimals, $dec_point, $thousands);
        
        return $prefix . $formatted . $suffix;
    }
    
    /**
     * Format currency value
     */
    private static function format_currency($value, $row_config = []) {
        if (!is_numeric($value)) {
            return $value;
        }
        
        $currency = $row_config['currency'] ?? 'Kč';
        $decimals = $row_config['decimals'] ?? 0;
        $dec_point = $row_config['decimal_point'] ?? ',';
        $thousands = $row_config['thousands_separator'] ?? ' ';
        $position = $row_config['currency_position'] ?? 'after';
        
        $formatted = number_format((float) $value, $decimals, $dec_point, $thousands);
        
        if ($position === 'before') {
            return $currency . ' ' . $formatted;
        }
        
        return $formatted . ' ' . $currency;
    }
    
    /**
     * Format percent value
     */
    private static function format_percent($value, $row_config = []) {
        if (!is_numeric($value)) {
            return $value;
        }
        
        $decimals = $row_config['decimals'] ?? 0;
        $dec_point = $row_config['decimal_point'] ?? ',';
        
        $is_decimal = $row_config['is_decimal'] ?? ($value >= 0 && $value <= 1);
        if ($is_decimal) {
            $value = $value * 100;
        }
        
        $formatted = number_format((float) $value, $decimals, $dec_point, '');
        
        return $formatted . ' %';
    }
    
    /**
     * Format value as image
     */
    private static function format_image($value, $row_config = []) {
        if (empty($value)) {
            return '<span class="sawt-text-muted">—</span>';
        }
        
        $size = $row_config['image_size'] ?? '40px';
        $alt = $row_config['alt'] ?? '';
        
        return sprintf(
            '<img src="%s" alt="%s" class="sawt-info-image" style="max-width: %s; max-height: %s;">',
            esc_url($value),
            esc_attr($alt),
            esc_attr($size),
            esc_attr($size)
        );
    }
    
    /**
     * Format value as code
     */
    private static function format_code($value) {
        return '<code class="sawt-code">' . esc_html($value) . '</code>';
    }
    
    /**
     * Format color value
     */
    private static function format_color($value) {
        if (empty($value)) {
            return '<span class="sawt-text-muted">—</span>';
        }
        
        return sprintf(
            '<span class="sawt-color-inline">
                <span class="sawt-color-swatch-sm" style="background-color: %s;"></span>
                <code class="sawt-code-sm">%s</code>
            </span>',
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
    
    /**
     * Get available format types
     */
    public static function get_formats() {
        return [
            self::FORMAT_TEXT,
            self::FORMAT_DATE,
            self::FORMAT_DATETIME,
            self::FORMAT_TIME,
            self::FORMAT_PHONE,
            self::FORMAT_EMAIL,
            self::FORMAT_URL,
            self::FORMAT_BADGE,
            self::FORMAT_BOOLEAN,
            self::FORMAT_NUMBER,
            self::FORMAT_CURRENCY,
            self::FORMAT_PERCENT,
            self::FORMAT_HTML,
            self::FORMAT_IMAGE,
            self::FORMAT_CODE,
            self::FORMAT_COLOR,
        ];
    }
}
