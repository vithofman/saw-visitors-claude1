<?php
/**
 * Table Column Types Renderer
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Table_Column_Types {
    
    /**
     * Render column value based on type
     * 
     * @param string $type Column type
     * @param mixed $value Column value
     * @param array $column Column config
     * @param array $row Full row data
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
     * Render image/logo
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
     * Render badge
     */
    private static function render_badge($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $map = $column['map'] ?? [];
        $labels = $column['labels'] ?? [];
        
        $badge_class = $map[$value] ?? 'secondary';
        $label = $labels[$value] ?? $value;
        
        return '<span class="saw-badge saw-badge-' . esc_attr($badge_class) . '">' . 
               esc_html($label) . 
               '</span>';
    }
    
    /**
     * Render enum (plain text, no badge)
     */
    private static function render_enum($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $map = $column['map'] ?? [];
        $label = $map[$value] ?? $value;
        
        return esc_html($label);
    }
    
    /**
     * Render date
     */
    private static function render_date($value, $column) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $format = $column['format'] ?? 'd.m.Y';
        
        try {
            $timestamp = strtotime($value);
            return date_i18n($format, $timestamp);
        } catch (Exception $e) {
            return esc_html($value);
        }
    }
    
    /**
     * Render color badge
     */
    private static function render_color_badge($value) {
        if (empty($value)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        return '<span class="saw-table-color-badge" style="background-color: ' . esc_attr($value) . ';" title="' . esc_attr($value) . '"></span>';
    }
    
    /**
     * Render boolean
     */
    private static function render_boolean($value) {
        if ($value) {
            return '<span class="saw-badge saw-badge-success">Ano</span>';
        } else {
            return '<span class="saw-badge saw-badge-secondary">Ne</span>';
        }
    }
    
    /**
     * Render custom
     */
    private static function render_custom($value, $column, $row) {
        if (isset($column['callback']) && is_callable($column['callback'])) {
            return call_user_func($column['callback'], $value, $row);
        }
        
        return esc_html($value);
    }
    
    /**
     * Render text
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