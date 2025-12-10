<?php
/**
 * SAW Table Config - Normalization and Validation
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Table_Config {
    
    /**
     * Normalize module configuration
     * Fills in defaults and validates structure
     * 
     * @param array $config Raw config
     * @return array Normalized config
     */
    public static function normalize($config) {
        $defaults = array(
            'entity'   => '',
            'table'    => '',
            'singular' => 'Záznam',
            'plural'   => 'Záznamy',
            'route'    => '',
            'icon'     => '📋',
            'path'     => '',
            
            'columns' => array(),
            
            'tabs' => array(
                'enabled' => false,
                'tabs'    => array(),
            ),
            
            'list' => array(
                'per_page'           => 50,
                'default_orderby'    => 'id',
                'default_order'      => 'DESC',
                'searchable'         => array(),
                'search_placeholder' => 'Hledat...',
                'empty_message'      => 'Žádné záznamy',
                'empty_icon'         => '📋',
            ),
            
            'infinite_scroll' => array(
                'enabled'      => true,
                'initial_load' => 100,
                'per_page'     => 50,
                'threshold'    => 0.6,
            ),
            
            'detail' => array(
                'header_badges' => array(),
                'sections'      => array(),
                'actions'       => array(),
            ),
            
            'form' => array(
                'sections' => array(),
                'fields'   => array(),
            ),
            
            'cache' => array(
                'enabled' => true,
                'ttl'     => 300,
            ),
        );
        
        // Merge with defaults
        $config = self::merge_recursive($defaults, $config);
        
        // Auto-generate route from entity if not set
        if (empty($config['route'])) {
            $config['route'] = str_replace('_', '-', $config['entity']);
        }
        
        // Normalize columns
        $config['columns'] = self::normalize_columns($config['columns']);
        
        // Normalize detail sections
        $config['detail']['sections'] = self::normalize_sections($config['detail']['sections']);
        
        return $config;
    }
    
    /**
     * Normalize columns configuration
     */
    protected static function normalize_columns($columns) {
        $normalized = array();
        
        foreach ($columns as $key => $col) {
            // Defaults for column
            $col = array_merge(array(
                'label'    => ucfirst($key),
                'type'     => 'text',
                'sortable' => false,
                'width'    => '',
                'align'    => 'left',
                'bold'     => false,
            ), $col);
            
            $normalized[$key] = $col;
        }
        
        return $normalized;
    }
    
    /**
     * Normalize sections configuration
     */
    protected static function normalize_sections($sections) {
        $normalized = array();
        
        foreach ($sections as $key => $section) {
            // Defaults for section
            $section = array_merge(array(
                'title'      => ucfirst($key),
                'icon'       => '',
                'type'       => 'info_rows',
                'rows'       => array(),
                'condition'  => '',
                'show_count' => false,
            ), $section);
            
            // Normalize rows
            if (!empty($section['rows'])) {
                $section['rows'] = self::normalize_rows($section['rows']);
            }
            
            $normalized[$key] = $section;
        }
        
        return $normalized;
    }
    
    /**
     * Normalize rows configuration
     */
    protected static function normalize_rows($rows) {
        $normalized = array();
        
        foreach ($rows as $row) {
            // Defaults for row
            $row = array_merge(array(
                'field'     => '',
                'label'     => '',
                'format'    => 'text',
                'bold'      => false,
                'highlight' => false,
                'condition' => '',
            ), $row);
            
            $normalized[] = $row;
        }
        
        return $normalized;
    }
    
    /**
     * Recursive merge
     */
    protected static function merge_recursive($defaults, $config) {
        foreach ($defaults as $key => $value) {
            if (!isset($config[$key])) {
                $config[$key] = $value;
            } elseif (is_array($value) && is_array($config[$key])) {
                // Only merge if both are associative arrays
                if (self::is_assoc($value) && self::is_assoc($config[$key])) {
                    $config[$key] = self::merge_recursive($value, $config[$key]);
                }
            }
        }
        return $config;
    }
    
    /**
     * Check if array is associative
     */
    protected static function is_assoc($arr) {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    /**
     * Check if config has detail configuration
     * Used for fallback detection
     */
    public static function has_detail_config($config) {
        return !empty($config['detail']['sections']);
    }
    
    /**
     * Check if config has form configuration
     */
    public static function has_form_config($config) {
        return !empty($config['form']['fields']);
    }
}
