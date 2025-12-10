<?php
/**
 * SAW Table Config - Validation and Parsing
 *
 * Validates and normalizes module configuration for SAW Table.
 * Ensures all required fields are present with proper defaults.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Config Class
 *
 * @since 3.0.0
 */
class SAW_Table_Config {
    
    /**
     * Default configuration values
     *
     * @var array
     */
    protected static $defaults = [
        // Basic settings
        'entity' => '',
        'singular' => '',
        'plural' => '',
        'route' => '',
        'icon' => '📋',
        'path' => '',
        
        // Multi-tenant filtering
        'filter_by_customer' => true,
        'filter_by_branch' => false,
        
        // Permissions
        'permissions' => [
            'list' => ['super_admin', 'admin', 'super_manager', 'manager'],
            'view' => ['super_admin', 'admin', 'super_manager', 'manager'],
            'create' => ['super_admin', 'admin'],
            'edit' => ['super_admin', 'admin'],
            'delete' => ['super_admin'],
        ],
        
        // Table configuration
        'table' => [
            'columns' => [],
            'default_order' => 'id',
            'default_order_dir' => 'DESC',
            'per_page' => 25,
        ],
        
        // Tabs configuration
        'tabs' => [
            'enabled' => false,
            'tab_param' => 'tab',
            'tabs' => [],
            'default_tab' => 'all',
        ],
        
        // Infinite scroll
        'infinite_scroll' => [
            'enabled' => true,
            'initial_load' => 100,
            'per_page' => 50,
            'threshold' => 0.6,
        ],
        
        // Detail sidebar
        'detail' => [
            'header_image' => null,
            'header_badges' => [],
            'sections' => [],
            'actions' => [],
        ],
        
        // Form sidebar
        'form' => [
            'fields' => [],
        ],
        
        // Cache settings
        'cache' => [
            'enabled' => true,
            'ttl' => 300,
            'invalidate_on' => ['save', 'delete'],
        ],
    ];
    
    /**
     * Parse and validate configuration
     *
     * @param array  $config Raw configuration
     * @param string $entity Entity name
     * @return array Parsed configuration
     */
    public static function parse($config, $entity = '') {
        // Merge with defaults
        $parsed = self::merge_recursive(self::$defaults, $config);
        
        // Set entity if not provided
        if (empty($parsed['entity']) && !empty($entity)) {
            $parsed['entity'] = $entity;
        }
        
        // Validate and normalize
        $parsed = self::validate($parsed);
        $parsed = self::normalize($parsed);
        
        return $parsed;
    }
    
    /**
     * Recursively merge arrays
     *
     * @param array $defaults Default values
     * @param array $config   User configuration
     * @return array Merged configuration
     */
    protected static function merge_recursive($defaults, $config) {
        $merged = $defaults;
        
        foreach ($config as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // Check if it's an indexed array (list) vs associative array
                if (self::is_indexed_array($value)) {
                    // For indexed arrays, replace entirely
                    $merged[$key] = $value;
                } else {
                    // For associative arrays, merge recursively
                    $merged[$key] = self::merge_recursive($merged[$key], $value);
                }
            } else {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }
    
    /**
     * Check if array is indexed (list) vs associative
     *
     * @param array $arr Array to check
     * @return bool
     */
    protected static function is_indexed_array($arr) {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
    
    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return array Validated configuration
     */
    protected static function validate($config) {
        // Validate entity
        if (empty($config['entity'])) {
            trigger_error('SAW_Table_Config: entity is required', E_USER_WARNING);
        }
        
        // Validate table columns
        if (!empty($config['table']['columns'])) {
            $config['table']['columns'] = self::validate_columns($config['table']['columns']);
        }
        
        // Validate detail sections
        if (!empty($config['detail']['sections'])) {
            $config['detail']['sections'] = self::validate_sections($config['detail']['sections']);
        }
        
        // Validate header badges
        if (!empty($config['detail']['header_badges'])) {
            $config['detail']['header_badges'] = self::validate_badges($config['detail']['header_badges']);
        }
        
        // Validate form fields
        if (!empty($config['form']['fields'])) {
            $config['form']['fields'] = self::validate_form_fields($config['form']['fields']);
        }
        
        return $config;
    }
    
    /**
     * Validate table columns
     *
     * @param array $columns Column configurations
     * @return array Validated columns
     */
    protected static function validate_columns($columns) {
        $validated = [];
        
        foreach ($columns as $key => $column) {
            // Normalize string columns to array
            if (is_string($column)) {
                $column = ['label' => $column];
            }
            
            // Set defaults
            $column = array_merge([
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'type' => 'text',
                'sortable' => false,
                'searchable' => false,
                'visible' => true,
            ], $column);
            
            $validated[$key] = $column;
        }
        
        return $validated;
    }
    
    /**
     * Validate detail sections
     *
     * @param array $sections Section configurations
     * @return array Validated sections
     */
    protected static function validate_sections($sections) {
        $validated = [];
        
        foreach ($sections as $key => $section) {
            // Set defaults
            $section = array_merge([
                'title' => '',
                'title_key' => '',
                'icon' => '',
                'type' => 'info_rows',
                'condition' => '',
                'permission' => '',
                'class' => '',
            ], $section);
            
            // Validate section type
            $valid_types = ['info_rows', 'related_list', 'text_block', 'metadata', 'special'];
            if (!in_array($section['type'], $valid_types)) {
                $section['type'] = 'info_rows';
            }
            
            // Validate rows for info_rows type
            if ($section['type'] === 'info_rows' && !empty($section['rows'])) {
                $section['rows'] = self::validate_info_rows($section['rows']);
            }
            
            $validated[$key] = $section;
        }
        
        return $validated;
    }
    
    /**
     * Validate info rows
     *
     * @param array $rows Row configurations
     * @return array Validated rows
     */
    protected static function validate_info_rows($rows) {
        $validated = [];
        
        foreach ($rows as $row) {
            $row = array_merge([
                'field' => '',
                'label' => '',
                'label_key' => '',
                'format' => null,
                'condition' => '',
                'empty_text' => '',
                'bold' => false,
            ], $row);
            
            $validated[] = $row;
        }
        
        return $validated;
    }
    
    /**
     * Validate header badges
     *
     * @param array $badges Badge configurations
     * @return array Validated badges
     */
    protected static function validate_badges($badges) {
        $validated = [];
        
        foreach ($badges as $badge) {
            $badge = array_merge([
                'type' => 'plain',
                'field' => '',
                'condition' => '',
                'permission' => '',
            ], $badge);
            
            // Validate badge type
            $valid_types = ['status', 'icon_text', 'code', 'plain', 'count', 'role', 'flag', 'image'];
            if (!in_array($badge['type'], $valid_types)) {
                $badge['type'] = 'plain';
            }
            
            $validated[] = $badge;
        }
        
        return $validated;
    }
    
    /**
     * Validate form fields
     *
     * @param array $fields Field configurations
     * @return array Validated fields
     */
    protected static function validate_form_fields($fields) {
        $validated = [];
        
        foreach ($fields as $key => $field) {
            $field = array_merge([
                'type' => 'text',
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'required' => false,
                'validation' => '',
                'help' => '',
                'default' => null,
            ], $field);
            
            $validated[$key] = $field;
        }
        
        return $validated;
    }
    
    /**
     * Normalize configuration
     *
     * @param array $config Configuration to normalize
     * @return array Normalized configuration
     */
    protected static function normalize($config) {
        // Normalize route
        if (empty($config['route'])) {
            $config['route'] = $config['entity'];
        }
        $config['route'] = trim($config['route'], '/');
        
        // Normalize singular/plural from entity if not set
        if (empty($config['singular'])) {
            $config['singular'] = ucfirst(str_replace(['_', '-'], ' ', $config['entity']));
        }
        if (empty($config['plural'])) {
            $config['plural'] = $config['singular'] . 's';
        }
        
        // Ensure detail actions have proper structure
        if (!empty($config['detail']['actions'])) {
            $config['detail']['actions'] = self::normalize_actions($config['detail']['actions']);
        }
        
        return $config;
    }
    
    /**
     * Normalize action buttons
     *
     * @param array $actions Action configurations
     * @return array Normalized actions
     */
    protected static function normalize_actions($actions) {
        $normalized = [];
        
        foreach ($actions as $key => $action) {
            $action = array_merge([
                'label' => ucfirst($key),
                'label_key' => '',
                'icon' => '',
                'type' => 'secondary',
                'permission' => '',
                'confirm' => '',
                'url' => '',
            ], $action);
            
            // Set default permissions
            if (empty($action['permission'])) {
                if ($key === 'edit') {
                    $action['permission'] = 'edit';
                } elseif ($key === 'delete') {
                    $action['permission'] = 'delete';
                }
            }
            
            // Set default icons
            if (empty($action['icon'])) {
                if ($key === 'edit') {
                    $action['icon'] = 'edit';
                } elseif ($key === 'delete') {
                    $action['icon'] = 'trash';
                }
            }
            
            // Set default type for delete
            if ($key === 'delete' && $action['type'] === 'secondary') {
                $action['type'] = 'danger';
            }
            
            $normalized[$key] = $action;
        }
        
        return $normalized;
    }
    
    /**
     * Get default configuration
     *
     * @return array
     */
    public static function get_defaults() {
        return self::$defaults;
    }
    
    /**
     * Validate a single config key exists
     *
     * @param array  $config Config array
     * @param string $key    Key to check (dot notation supported)
     * @return bool
     */
    public static function has($config, $key) {
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
    
    /**
     * Get config value with dot notation
     *
     * @param array  $config  Config array
     * @param string $key     Key to get (dot notation supported)
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get($config, $key, $default = null) {
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}
