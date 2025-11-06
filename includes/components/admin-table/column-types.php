<?php
/**
 * Table Column Types Renderer
 *
 * Handles rendering of different column types in admin tables.
 * Supports images, badges, dates, booleans, enums, colors, and custom callbacks.
 *
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     1.0.0
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Column Types
 *
 * Static utility class for rendering table column values based on their type.
 *
 * @since 1.0.0
 */
class SAW_Table_Column_Types {
    
    /**
     * Render column value based on type
     *
     * Main router method that delegates to specific type renderers.
     *
     * @since 1.0.0
     * @param string $type   Column type (image, badge, date, boolean, etc.)
     * @param mixed  $value  Column value to render
     * @param array  $column Column configuration
     * @param array  $row    Full row data (for custom callbacks)
     * @return string Rendered HTML
     */
    public static function render($type, $value, $column, $row) {
        switch ($type) {
            case 'image':
                return self::render_image($value, $column);
                
            case 'badge':
                return self::render_badge($value, $column);
                
            case 'enum':
                return self::render_enum($value, $column);
                
            case 'date':
                return self::render_date($value, $column);
                
            case 'color_badge':
                return self::render_color_badge($value);
                
            case 'boolean':
                return self::render_boolean($value);
                
            case 'custom':
                return self::render_custom($value, $column, $row);
                
            case 'text':
            default:
                return self::render_text($value, $column);
        }
    }
    
    /**
     * Render image/logo column
     *
     * Displays image or placeholder icon if image is not available.
     *
     * @since 1.0.0
     * @param mixed $value  Image URL
     * @param array $column Column configuration with 'alt' and 'placeholder' keys
     * @return string HTML image or placeholder
     */
    private static function render_image($value, $column) {
        if (!empty($value)) {
            $alt = $column['alt'] ?? '';
            return '<div class="saw-table-logo-wrapper">' .
                   '<img src="' . esc_url($value) . '" alt="' . esc_attr($alt) . '">' .
                   '</div>';
        }
        
        $placeholder_icon = $column['placeholder'] ?? 'admin-generic';
        return '<div class="saw-table-logo-placeholder">' .
               '<span class="dashicons dashicons-' . esc_attr($placeholder_icon) . '"></span>' .
               '</div>';
    }
    
    /**
     * Render badge column
     *
     * Displays colored badge based on value mapping.
     *
     * @since 1.0.0
     * @param mixed $value  Badge value
     * @param array $column Column configuration with 'map' and 'labels' keys
     * @return string HTML badge element
     */
    private static function render_badge($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $map = $column['map'] ?? array();
        $labels = $column['labels'] ?? array();
        
        $badge_class = isset($map[$value]) ? $map[$value] : 'secondary';
        $label = isset($labels[$value]) ? $labels[$value] : $value;
        
        return '<span class="saw-badge saw-badge-' . esc_attr($badge_class) . '">' . 
               esc_html($label) . 
               '</span>';
    }
    
    /**
     * Render enum column (plain text, no badge)
     *
     * Maps value to label without styling.
     *
     * @since 1.0.0
     * @param mixed $value  Enum value
     * @param array $column Column configuration with 'map' key
     * @return string Plain text label
     */
    private static function render_enum($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $map = $column['map'] ?? array();
        $label = isset($map[$value]) ? $map[$value] : $value;
        
        return esc_html($label);
    }
    
    /**
     * Render date column
     *
     * Formats date according to specified or default format.
     *
     * @since 1.0.0
     * @param mixed $value  Date string
     * @param array $column Column configuration with optional 'format' key
     * @return string Formatted date or em dash if empty
     */
    private static function render_date($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $format = $column['format'] ?? 'd.m.Y';
        
        try {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return esc_html($value);
            }
            return date_i18n($format, $timestamp);
        } catch (Exception $e) {
            return esc_html($value);
        }
    }
    
    /**
     * Render color badge
     *
     * Displays small colored circle representing the color value.
     * Note: Inline style is required here because color is dynamic.
     *
     * @since 1.0.0
     * @param mixed $value Color hex code
     * @return string HTML color badge element
     */
    private static function render_color_badge($value) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        return '<span class="saw-table-color-badge" style="background-color: ' . esc_attr($value) . ';" title="' . esc_attr($value) . '"></span>';
    }
    
    /**
     * Render boolean column
     *
     * Displays "Ano"/"Ne" badge based on boolean value.
     *
     * @since 1.0.0
     * @param mixed $value Boolean value (true/false, 1/0)
     * @return string HTML badge element
     */
    private static function render_boolean($value) {
        if ($value) {
            return '<span class="saw-badge saw-badge-success">Ano</span>';
        } else {
            return '<span class="saw-badge saw-badge-secondary">Ne</span>';
        }
    }
    
    /**
     * Render custom column
     *
     * Executes custom callback function if provided, otherwise returns plain text.
     *
     * @since 1.0.0
     * @param mixed $value  Column value
     * @param array $column Column configuration with optional 'callback' key
     * @param array $row    Full row data
     * @return string Custom rendered HTML or plain text
     */
    private static function render_custom($value, $column, $row) {
        if (isset($column['callback']) && is_callable($column['callback'])) {
            return call_user_func($column['callback'], $value, $row);
        }
        
        return esc_html($value);
    }
    
    /**
     * Render text column
     *
     * Default text rendering with optional bold styling.
     *
     * @since 1.0.0
     * @param mixed $value  Text value
     * @param array $column Column configuration with optional 'bold' key
     * @return string HTML text or em dash if empty
     */
    private static function render_text($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $bold = $column['bold'] ?? false;
        
        if ($bold) {
            return '<strong>' . esc_html($value) . '</strong>';
        }
        
        return esc_html($value);
    }
}