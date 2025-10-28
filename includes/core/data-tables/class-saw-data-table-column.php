<?php
/**
 * SAW Data Table Column - FIXED with custom_render support
 * 
 * OPRAVY v4.6.2:
 * 1. Přidána metoda get_custom_render() pro získání custom rendereru
 * 2. Přidána metoda get_type() pro získání typu sloupce
 * 3. Přidána metoda get_label() pro získání labelu
 * 4. Přidána metoda get_width() pro získání šířky
 * 
 * @package SAW_Visitors
 * @version 4.6.2
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
            'custom_render' => null, // ✅ Callback function for custom rendering
        );
        
        $config = wp_parse_args($config, $defaults);
        
        $this->label = $config['label'];
        $this->sortable = $config['sortable'];
        $this->width = $config['width'];
        $this->align = $config['align'];
        $this->type = $config['type'];
        $this->custom_render = $config['custom_render'];
        
        // 🔍 DEBUG: Log column creation
        error_log("Column created: $key, type: {$this->type}, has custom_render: " . (is_callable($this->custom_render) ? 'YES' : 'NO'));
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
            echo ' ';
            
            // Sort indicator
            if ($current_orderby === $this->key) {
                if ($current_order === 'ASC') {
                    echo '<span class="dashicons dashicons-arrow-up-alt2"></span>';
                } else {
                    echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                }
            } else {
                echo '<span class="dashicons dashicons-sort"></span>';
            }
            
            echo '</a>';
        } else {
            echo esc_html($this->label);
        }
        
        echo '</th>';
    }
    
    /**
     * ✅ NOVÁ METODA: Get custom render callback
     * 
     * @return callable|null
     */
    public function get_custom_render() {
        return $this->custom_render;
    }
    
    /**
     * ✅ NOVÁ METODA: Get column type
     * 
     * @return string
     */
    public function get_type() {
        return $this->type;
    }
    
    /**
     * ✅ NOVÁ METODA: Get column label
     * 
     * @return string
     */
    public function get_label() {
        return $this->label;
    }
    
    /**
     * ✅ NOVÁ METODA: Get column width
     * 
     * @return string
     */
    public function get_width() {
        return $this->width;
    }
    
    /**
     * Get column key
     * 
     * @return string
     */
    public function get_key() {
        return $this->key;
    }
    
    /**
     * Is column sortable?
     * 
     * @return bool
     */
    public function is_sortable() {
        return $this->sortable;
    }
    
    /**
     * Get column align
     * 
     * @return string
     */
    public function get_align() {
        return $this->align;
    }
}