<?php
/**
 * SAW Row Renderer
 *
 * Renders info rows in detail sidebar sections.
 * Supports various formats: text, date, phone, email, url, badge, etc.
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
 * SAW Row Renderer Class
 *
 * @since 3.0.0
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
     * Render a single info row
     *
     * @param array $row_config Row configuration
     * @param array $item       Item data
     * @return string HTML
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
        
        // Check if empty and handle
        if (self::is_empty($value)) {
            // Skip if no empty_text defined
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
        
        // Build HTML
        $bold_class = !empty($row_config['bold']) ? ' saw-info-val-bold' : '';
        $row_class = $row_config['class'] ?? '';
        
        ob_start();
        ?>
        <div class="saw-info-row<?php echo $row_class ? ' ' . esc_attr($row_class) : ''; ?>">
            <span class="saw-info-label"><?php echo esc_html($label); ?></span>
            <span class="saw-info-val<?php echo esc_attr($bold_class); ?>"><?php 
                // Some formats output HTML, others need escaping
                if (in_array($format, [self::FORMAT_HTML, self::FORMAT_BADGE, self::FORMAT_IMAGE, self::FORMAT_PHONE, self::FORMAT_EMAIL, self::FORMAT_URL])) {
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
     *
     * @param array $rows Array of row configurations
     * @param array $item Item data
     * @return string HTML
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
     * Get field value from item
     *
     * Supports dot notation for nested values.
     *
     * @param string $field Field name or path
     * @param array  $item  Item data
     * @return mixed
     */
    private static function get_field_value($field, $item) {
        if (empty($field)) {
            return null;
        }
        
        // Check for dot notation
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
     *
     * @param mixed $value Value to check
     * @return bool
     */
    private static function is_empty($value) {
        if ($value === null) {
            return true;
        }
        if ($value === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }
        // 0 and '0' are NOT empty
        return false;
    }
    
    /**
     * Format value based on type
     *
     * @param mixed  $value      Value to format
     * @param string $format     Format type
     * @param array  $row_config Row configuration
     * @param array  $item       Full item data
     * @return string Formatted value
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
                
            case self::FORMAT_HTML:
                return $value; // Return as-is
                
            default:
                return self::format_text($value, $row_config);
        }
    }
    
    /**
     * Format text value
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_text($value, $row_config = []) {
        $value = (string) $value;
        
        // Apply prefix/suffix
        $prefix = $row_config['prefix'] ?? '';
        $suffix = $row_config['suffix'] ?? '';
        
        // Apply max length
        $max_length = $row_config['max_length'] ?? 0;
        if ($max_length > 0 && mb_strlen($value) > $max_length) {
            $value = mb_substr($value, 0, $max_length) . '…';
        }
        
        return $prefix . $value . $suffix;
    }
    
    /**
     * Format date value
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_date($value, $row_config = []) {
        if (empty($value) || $value === '0000-00-00') {
            return '—';
        }
        
        $format = $row_config['date_format'] ?? 'd.m.Y';
        
        try {
            $timestamp = is_numeric($value) ? $value : strtotime($value);
            if ($timestamp === false) {
                return $value;
            }
            return date_i18n($format, $timestamp);
        } catch (Exception $e) {
            return $value;
        }
    }
    
    /**
     * Format datetime value
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_datetime($value, $row_config = []) {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        
        $format = $row_config['datetime_format'] ?? 'd.m.Y H:i';
        
        try {
            $timestamp = is_numeric($value) ? $value : strtotime($value);
            if ($timestamp === false) {
                return $value;
            }
            return date_i18n($format, $timestamp);
        } catch (Exception $e) {
            return $value;
        }
    }
    
    /**
     * Format time value
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_time($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        $format = $row_config['time_format'] ?? 'H:i';
        
        try {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return $value;
            }
            return date_i18n($format, $timestamp);
        } catch (Exception $e) {
            return $value;
        }
    }
    
    /**
     * Format phone number as clickable link
     *
     * @param string $value Phone number
     * @return string HTML
     */
    private static function format_phone($value) {
        if (empty($value)) {
            return '—';
        }
        
        // Clean phone number for tel: link
        $clean = preg_replace('/[^0-9+]/', '', $value);
        
        return sprintf(
            '<a href="tel:%s" class="saw-info-link saw-info-phone">%s</a>',
            esc_attr($clean),
            esc_html($value)
        );
    }
    
    /**
     * Format email as clickable link
     *
     * @param string $value Email address
     * @return string HTML
     */
    private static function format_email($value) {
        if (empty($value)) {
            return '—';
        }
        
        return sprintf(
            '<a href="mailto:%s" class="saw-info-link saw-info-email">%s</a>',
            esc_attr($value),
            esc_html($value)
        );
    }
    
    /**
     * Format URL as clickable link
     *
     * @param string $value      URL
     * @param array  $row_config Configuration
     * @return string HTML
     */
    private static function format_url($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $value)) {
            $value = 'https://' . $value;
        }
        
        $label = $row_config['url_label'] ?? parse_url($value, PHP_URL_HOST) ?? $value;
        $target = !empty($row_config['url_new_tab']) ? ' target="_blank" rel="noopener"' : '';
        
        return sprintf(
            '<a href="%s" class="saw-info-link saw-info-url"%s>%s</a>',
            esc_url($value),
            $target,
            esc_html($label)
        );
    }
    
    /**
     * Format value as badge
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration with map
     * @return string HTML
     */
    private static function format_badge($value, $row_config = []) {
        if ($value === null || $value === '') {
            return '—';
        }
        
        $value = (string) $value;
        $map = $row_config['map'] ?? [];
        
        if (isset($map[$value])) {
            $config = $map[$value];
            $label = $config['label'] ?? $value;
            
            // Support translation key
            if (!empty($config['label_key'])) {
                $label = self::tr($config['label_key'], $label);
            }
            
            $color = $config['color'] ?? 'secondary';
            $icon = $config['icon'] ?? '';
            
            return sprintf(
                '<span class="saw-badge saw-badge-%s">%s%s</span>',
                esc_attr($color),
                $icon ? esc_html($icon) . ' ' : '',
                esc_html($label)
            );
        }
        
        return sprintf(
            '<span class="saw-badge saw-badge-secondary">%s</span>',
            esc_html($value)
        );
    }
    
    /**
     * Format boolean value
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_boolean($value, $row_config = []) {
        $true_label = $row_config['true_label'] ?? self::tr('yes', 'Ano');
        $false_label = $row_config['false_label'] ?? self::tr('no', 'Ne');
        
        // Support translation keys
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
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
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
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_currency($value, $row_config = []) {
        if (!is_numeric($value)) {
            return $value;
        }
        
        $currency = $row_config['currency'] ?? 'Kč';
        $decimals = $row_config['decimals'] ?? 2;
        $dec_point = $row_config['decimal_point'] ?? ',';
        $thousands = $row_config['thousands_separator'] ?? ' ';
        $position = $row_config['currency_position'] ?? 'after'; // 'before' or 'after'
        
        $formatted = number_format((float) $value, $decimals, $dec_point, $thousands);
        
        if ($position === 'before') {
            return $currency . ' ' . $formatted;
        }
        
        return $formatted . ' ' . $currency;
    }
    
    /**
     * Format percent value
     *
     * @param mixed $value      Value
     * @param array $row_config Configuration
     * @return string
     */
    private static function format_percent($value, $row_config = []) {
        if (!is_numeric($value)) {
            return $value;
        }
        
        $decimals = $row_config['decimals'] ?? 0;
        $dec_point = $row_config['decimal_point'] ?? ',';
        
        // Check if value is already a percentage (0-100) or decimal (0-1)
        $is_decimal = $row_config['is_decimal'] ?? ($value >= 0 && $value <= 1);
        if ($is_decimal) {
            $value = $value * 100;
        }
        
        $formatted = number_format((float) $value, $decimals, $dec_point, '');
        
        return $formatted . ' %';
    }
    
    /**
     * Format value as image
     *
     * @param string $value      Image URL
     * @param array  $row_config Configuration
     * @return string HTML
     */
    private static function format_image($value, $row_config = []) {
        if (empty($value)) {
            return '—';
        }
        
        $size = $row_config['image_size'] ?? '40px';
        $alt = $row_config['alt'] ?? '';
        
        return sprintf(
            '<img src="%s" alt="%s" class="saw-info-image" style="max-width: %s; max-height: %s;">',
            esc_url($value),
            esc_attr($alt),
            esc_attr($size),
            esc_attr($size)
        );
    }
    
    /**
     * Format value as code
     *
     * @param string $value Value
     * @return string
     */
    private static function format_code($value) {
        return '<code class="saw-info-code">' . esc_html($value) . '</code>';
    }
    
    /**
     * Evaluate condition
     *
     * @param string $condition Condition string
     * @param array  $item      Item data
     * @return bool
     */
    private static function evaluate_condition($condition, $item) {
        // Replace $item references
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
     * Get available format types
     *
     * @return array
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
        ];
    }
}
