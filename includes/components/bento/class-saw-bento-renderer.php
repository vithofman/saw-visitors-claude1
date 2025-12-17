<?php
/**
 * SAW Bento Renderer
 * 
 * Hlavní třída pro renderování Bento gridu a karet.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Renderer {
    
    /**
     * Instance
     * 
     * @var SAW_Bento_Renderer|null
     */
    private static $instance = null;
    
    /**
     * Registrované karty
     * 
     * @var array
     */
    private $cards = [];
    
    /**
     * Get instance (Singleton)
     * 
     * @return SAW_Bento_Renderer
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
    }
    
    /**
     * Load base class and all card classes
     */
    private function load_dependencies() {
        $bento_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/components/bento/';
        
        // Load base card class first (if not already loaded)
        if (!class_exists('SAW_Bento_Card')) {
            require_once $bento_dir . 'class-saw-bento-card.php';
        }
        
        // Load helper functions (if not already loaded)
        if (!function_exists('saw_bento')) {
            require_once $bento_dir . 'functions.php';
        }
        
        // Load all card types
        $cards_dir = $bento_dir . 'cards/';
        
        $card_types = [
            'header',
            'stat', 
            'info', 
            'address', 
            'contact', 
            'list', 
            'text',
            'image',
            'timeline',
            'status-grid',
            'schedule',
            'visitors',
            'history-table',
            'status-box',
            'language-tabs',
            'expandable',
            'meta',
            'actions',
            'merge'
        ];
        
        foreach ($card_types as $type) {
            $file = $cards_dir . 'class-bento-' . $type . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
    
    /**
     * Start Bento grid
     * 
     * @param string $class Additional CSS classes
     * @param array $attrs Additional HTML attributes
     */
    public function start_grid($class = '', $attrs = []) {
        $classes = 'bento-grid';
        if ($class) {
            $classes .= ' ' . esc_attr($class);
        }
        
        $attr_string = '';
        foreach ($attrs as $key => $value) {
            $attr_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        echo '<div class="' . esc_attr($classes) . '"' . $attr_string . '>';
    }
    
    /**
     * End Bento grid
     */
    public function end_grid() {
        echo '</div>';
    }
    
    /**
     * Render card
     * 
     * @param string $type Card type (e.g., 'header', 'stat', 'info')
     * @param array $args Card arguments
     */
    public function render($type, $args = []) {
        // Convert type to class name (e.g., 'status-grid' -> 'SAW_Bento_Status_Grid')
        $type_parts = explode('-', $type);
        $class_parts = array_map('ucfirst', $type_parts);
        $class_name = 'SAW_Bento_' . implode('_', $class_parts);
        
        if (class_exists($class_name)) {
            $card = new $class_name($args);
            $card->render();
        } else {
            // Fallback - show debug info in development
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<!-- Bento: Unknown card type: ' . esc_html($type) . ' (class: ' . esc_html($class_name) . ') -->';
            }
            error_log("[SAW Bento] Unknown card type: {$type} - expected class: {$class_name}");
        }
    }
    
    /**
     * Check if Bento is enabled for current context
     * 
     * @return bool
     */
    public static function is_enabled() {
        // Can be controlled via filter
        return apply_filters('saw_bento_enabled', true);
    }
    
    /**
     * Get CSS variables for customization
     * 
     * @return array
     */
    public static function get_css_variables() {
        return [
            // Brand colors
            '--saw-brand' => '#005A8C',
            '--saw-brand-50' => '#E8F4F8',
            '--saw-brand-100' => '#C5E3EE',
            '--saw-brand-200' => '#8BC5DB',
            '--saw-brand-300' => '#4FA7C8',
            '--saw-brand-400' => '#1A89B5',
            '--saw-brand-500' => '#0077B5',
            '--saw-brand-600' => '#005A8C',
            '--saw-brand-700' => '#004A73',
            '--saw-brand-800' => '#003D5C',
            '--saw-brand-900' => '#002E45',
            
            // Bento specific
            '--bento-grid-gap' => '16px',
            '--bento-grid-columns' => '3',
            '--bento-card-radius' => '20px',
            '--bento-card-padding' => '20px',
        ];
    }
}

