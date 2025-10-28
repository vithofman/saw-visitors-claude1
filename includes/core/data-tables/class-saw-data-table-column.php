<?php
/**
 * SAW Data Table Column - Column definition for data tables
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Data_Table_Column {
    
    private $key;
    private $label;
    private $sortable;
    private $width;
    private $align;
    private $type;
    private $custom_render;
    
    /**
     * Constructor
     * 
     * @param string $key Column key (matches data property)
     * @param array $config Column configuration
     */
    public function __construct($key, $config = array()) {
        $this->key = sanitize_key($key);
        
        $defaults = array(
            'label' => ucfirst($key),
            'sortable' => true,
            'width' => '',
            'align' => 'left', // left, center, right
            'type' => 'text', // text, image, badge, date, custom
            'custom_render' => null, // Callback function for custom rendering
        );
        
        $config = wp_parse_args($config, $defaults);
        
        $this->label = $config['label'];
        $this->sortable = $config['sortable'];
        $this->width = $config['width'];
        $this->align = $config['align'];
        $this->type = $config['type'];
        $this->custom_render = $config['custom_render'];
    }
    
    /**
     * Render table header
     */
    public function render_header($current_orderby = '', $current_order = 'ASC') {
        $classes = array();
        $styles = array();
        
        if ($this->sortable) {
            $classes[] = 'saw-sortable';
        }
        
        if ($this->align === 'center') {
            $classes[] = 'saw-text-center';
        } elseif ($this->align === 'right') {
            $classes[] = 'saw-text-right';
        }
        
        if ($this->width) {
            $styles[] = 'width: ' . esc_attr($this->width);
        }
        
        $class_attr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        $style_attr = !empty($styles) ? ' style="' . implode('; ', $styles) . '"' : '';
        
        echo '<th' . $class_attr . $style_attr;
        
        if ($this->sortable) {
            echo ' data-column="' . esc_attr($this->key) . '"';
        }
        
        if ($this->label) {
            echo ' data-label="' . esc_attr($this->label) . '"';
        }
        
        echo '>';
        
        if ($this->sortable) {
            echo '<a href="#">';
            echo esc_html($this->label);
            echo ' ' . $this->get_sort_icon($current_orderby, $current_order);
            echo '</a>';
        } else {
            echo esc_html($this->label);
        }
        
        echo '</th>';
    }
    
    /**
     * Get sort icon based on current state
     */
    private function get_sort_icon($current_orderby, $current_order) {
        if ($current_orderby !== $this->key) {
            return '<span class="saw-sort-icon">⇅</span>';
        }
        
        return $current_order === 'ASC' 
            ? '<span class="saw-sort-icon saw-sort-asc">▲</span>' 
            : '<span class="saw-sort-icon saw-sort-desc">▼</span>';
    }
    
    /**
     * Render cell content
     */
    public function render_cell($value, $item = null) {
        if ($this->custom_render && is_callable($this->custom_render)) {
            call_user_func($this->custom_render, $value, $item);
            return;
        }
        
        switch ($this->type) {
            case 'image':
                $this->render_image_cell($value);
                break;
                
            case 'badge':
                $this->render_badge_cell($value);
                break;
                
            case 'date':
                $this->render_date_cell($value);
                break;
                
            case 'text':
            default:
                echo esc_html($value);
                break;
        }
    }
    
    /**
     * Render image cell
     */
    private function render_image_cell($value) {
        if (empty($value)) {
            echo '<div class="saw-table-avatar saw-table-avatar-empty">';
            echo '<span class="dashicons dashicons-businessman"></span>';
            echo '</div>';
        } else {
            echo '<div class="saw-table-avatar">';
            echo '<img src="' . esc_url($value) . '" alt="">';
            echo '</div>';
        }
    }
    
    /**
     * Render badge cell
     */
    private function render_badge_cell($value) {
        $badge_class = 'saw-badge';
        
        // Add color class based on value
        $value_lower = strtolower($value);
        if (in_array($value_lower, array('active', 'aktivní', 'success', 'úspěch'))) {
            $badge_class .= ' saw-badge-success';
        } elseif (in_array($value_lower, array('inactive', 'neaktivní', 'pending', 'čeká'))) {
            $badge_class .= ' saw-badge-warning';
        } elseif (in_array($value_lower, array('error', 'chyba', 'failed', 'selhalo'))) {
            $badge_class .= ' saw-badge-danger';
        }
        
        echo '<span class="' . esc_attr($badge_class) . '">' . esc_html($value) . '</span>';
    }
    
    /**
     * Render date cell
     */
    private function render_date_cell($value) {
        if (empty($value)) {
            echo '—';
            return;
        }
        
        $timestamp = strtotime($value);
        if ($timestamp) {
            echo '<span title="' . esc_attr(date_i18n('j.n.Y H:i:s', $timestamp)) . '">';
            echo esc_html(date_i18n('j.n.Y', $timestamp));
            echo '</span>';
        } else {
            echo esc_html($value);
        }
    }
    
    /**
     * Getters
     */
    public function get_key() {
        return $this->key;
    }
    
    public function get_label() {
        return $this->label;
    }
    
    public function is_sortable() {
        return $this->sortable;
    }
    
    public function get_width() {
        return $this->width;
    }
    
    public function get_align() {
        return $this->align;
    }
    
    public function get_type() {
        return $this->type;
    }
}