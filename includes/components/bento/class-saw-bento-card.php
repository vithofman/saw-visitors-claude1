<?php
/**
 * SAW Bento Card - Base Class
 * 
 * Abstraktní třída pro všechny Bento karty.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Bento_Card {
    
    /**
     * Card arguments
     * 
     * @var array
     */
    protected $args = [];
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [];
    
    /**
     * Constructor
     * 
     * @param array $args Card arguments
     */
    public function __construct($args = []) {
        $this->args = wp_parse_args($args, $this->defaults);
    }
    
    /**
     * Render card - must be implemented by child classes
     */
    abstract public function render();
    
    /**
     * Get colspan class
     * 
     * @param int|string $colspan Colspan value (1, 2, 3, or 'full')
     * @return string CSS class
     */
    protected function get_colspan_class($colspan = 1) {
        if ($colspan === 'full') {
            return 'bento-colspan-full';
        }
        return 'bento-colspan-' . intval($colspan);
    }
    
    /**
     * Get rowspan class
     * 
     * @param int $rowspan Rowspan value
     * @return string CSS class
     */
    protected function get_rowspan_class($rowspan = 1) {
        return 'bento-rowspan-' . intval($rowspan);
    }
    
    /**
     * Get card variant class
     * 
     * @param string $variant Variant name (default, light-blue, blue, dark)
     * @return string CSS class
     */
    protected function get_variant_class($variant = 'default') {
        if ($variant === 'default') {
            return '';
        }
        return 'bento-card--' . sanitize_html_class($variant);
    }
    
    /**
     * Render SVG icon using SAW_Icons class
     * 
     * @param string $icon Icon name
     * @param string $class Additional CSS classes
     * @param int $size Icon size
     */
    protected function render_icon($icon, $class = '', $size = 20) {
        if (class_exists('SAW_Icons')) {
            $icon_class = $class ? 'bento-icon ' . $class : 'bento-icon';
            echo SAW_Icons::get($icon, $icon_class);
        } else {
            // Fallback - simple placeholder
            echo '<span class="bento-icon ' . esc_attr($class) . '">';
            echo '<svg width="' . intval($size) . '" height="' . intval($size) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>';
            echo '</svg>';
            echo '</span>';
        }
    }
    
    /**
     * Get raw SVG icon HTML
     * 
     * @param string $icon Icon name
     * @param string $class Additional CSS classes
     * @return string SVG HTML
     */
    protected function get_icon($icon, $class = '') {
        if (class_exists('SAW_Icons')) {
            $icon_class = $class ? 'bento-icon ' . $class : 'bento-icon';
            return SAW_Icons::get($icon, $icon_class);
        }
        return '';
    }
    
    /**
     * Escape and format value based on type
     * 
     * @param mixed $value Value to format
     * @param string $type Value type (text, code, badge, status, link, email, phone)
     * @param array $options Additional options
     * @return string Formatted HTML
     */
    protected function format_value($value, $type = 'text', $options = []) {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return '<span class="bento-value-empty">—</span>';
        }
        
        switch ($type) {
            case 'code':
                $copyable = !empty($options['copyable']);
                $html = '<code class="bento-code">' . esc_html($value) . '</code>';
                if ($copyable) {
                    $html .= '<button type="button" class="bento-copy-btn" data-copy="' . esc_attr($value) . '" title="Kopírovat">';
                    $html .= $this->get_icon('copy', 'bento-copy-icon');
                    $html .= '</button>';
                }
                return $html;
            
            case 'badge':
                $variant = $options['variant'] ?? 'default';
                return '<span class="bento-badge bento-badge--' . esc_attr($variant) . '">' . esc_html($value) . '</span>';
            
            case 'status':
                $status = $options['status'] ?? 'default';
                $dot = !empty($options['dot']);
                $dot_html = $dot ? '<span class="bento-status-dot"></span>' : '';
                return '<span class="bento-status bento-status--' . esc_attr($status) . '">' . $dot_html . esc_html($value) . '</span>';
            
            case 'link':
                $url = $options['url'] ?? '#';
                $target = !empty($options['external']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a href="' . esc_url($url) . '" class="bento-link"' . $target . '>' . esc_html($value) . '</a>';
            
            case 'email':
                return '<a href="mailto:' . esc_attr($value) . '" class="bento-link bento-link--email">' . esc_html($value) . '</a>';
            
            case 'phone':
                $clean = preg_replace('/[^0-9+]/', '', $value);
                return '<a href="tel:' . esc_attr($clean) . '" class="bento-link bento-link--phone">' . esc_html($value) . '</a>';
            
            case 'date':
                return '<span class="bento-date">' . esc_html($value) . '</span>';
            
            case 'html':
                return wp_kses_post($value);
            
            default:
                return esc_html($value);
        }
    }
    
    /**
     * Build CSS classes string
     * 
     * @param array $classes Array of class names
     * @return string Space-separated class string
     */
    protected function build_classes($classes) {
        $filtered = array_filter($classes, function($class) {
            return !empty($class);
        });
        return implode(' ', $filtered);
    }
    
    /**
     * Render card header (icon + title + optional subtitle/badge)
     * 
     * @param string $icon Icon name
     * @param string $title Card title
     * @param array $options Options (subtitle, badge_count)
     */
    protected function render_card_header($icon, $title, $options = []) {
        echo '<div class="bento-card-header">';
        
        if ($icon) {
            echo '<div class="bento-card-icon">';
            $this->render_icon($icon);
            echo '</div>';
        }
        
        echo '<div class="bento-card-titles">';
        echo '<h3 class="bento-card-title">' . esc_html($title) . '</h3>';
        
        if (!empty($options['subtitle'])) {
            echo '<span class="bento-card-subtitle">' . esc_html($options['subtitle']) . '</span>';
        }
        echo '</div>';
        
        if (isset($options['badge_count'])) {
            echo '<span class="bento-card-badge">' . intval($options['badge_count']) . '</span>';
        }
        
        echo '</div>';
    }
    
    /**
     * Check if a condition is truthy
     * 
     * @param mixed $condition Condition to check
     * @return bool
     */
    protected function check_condition($condition) {
        if (!isset($condition)) {
            return true;
        }
        return (bool) $condition;
    }
}

