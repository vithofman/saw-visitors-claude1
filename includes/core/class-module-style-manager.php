<?php
/**
 * Module Style Manager - Dynamic CSS Injection
 *
 * Manages module-specific CSS loading and injection into page head.
 * Provides caching and safe CSS delivery for active modules.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Singleton class for managing module-specific styles
 *
 * @since 1.0.0
 */
class SAW_Module_Style_Manager {
    
    /**
     * @var SAW_Module_Style_Manager|null Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var string|null Currently active module slug
     */
    private $active_module = null;
    
    /**
     * @var array CSS content cache
     */
    private $css_cache = [];
    
    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return SAW_Module_Style_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - prevents direct instantiation
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Intentionally empty - no cleanup needed (handled by PHP full page reload)
    }
    
    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     *
     * @since 1.0.0
     * @throws Exception
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Get module CSS content
     *
     * Retrieves and caches CSS content for a specific module.
     *
     * @since 1.0.0
     * @param string $module_slug Module slug
     * @return string CSS content or empty string if not found
     */
    public function get_module_css($module_slug) {
        if (isset($this->css_cache[$module_slug])) {
            return $this->css_cache[$module_slug];
        }
        
        $css_path = $this->get_module_css_path($module_slug);
        
        if (!file_exists($css_path) || !is_readable($css_path)) {
            $this->css_cache[$module_slug] = '';
            return '';
        }
        
        $css_content = file_get_contents($css_path);
        
        if ($css_content === false) {
            $this->css_cache[$module_slug] = '';
            return '';
        }
        
        $this->css_cache[$module_slug] = $css_content;
        return $css_content;
    }
    
    /**
     * Inject module CSS into page
     *
     * Creates a style tag with module-specific CSS for inline injection.
     *
     * @since 1.0.0
     * @param string $module_slug Module slug
     * @return string HTML style tag or empty string
     */
    public function inject_module_css($module_slug) {
        $css_content = $this->get_module_css($module_slug);
        
        if (empty($css_content)) {
            return '';
        }
        
        $this->active_module = $module_slug;
        
        return sprintf(
            '<style id="saw-module-css-%s" data-saw-module="%s" type="text/css">%s</style>',
            esc_attr($module_slug),
            esc_attr($module_slug),
            wp_strip_all_tags($css_content)
        );
    }
    
    /**
     * Get currently active module
     *
     * @since 1.0.0
     * @return string|null Active module slug or null
     */
    public function get_active_module() {
        return $this->active_module;
    }
    
    /**
     * Get module CSS file path
     *
     * @since 1.0.0
     * @param string $module_slug Module slug
     * @return string Full path to module CSS file
     */
    private function get_module_css_path($module_slug) {
        $sanitized_slug = sanitize_file_name($module_slug);
        return SAW_VISITORS_PLUGIN_DIR . 'includes/modules/' . $sanitized_slug . '/styles.css';
    }
    
    /**
     * Clear CSS cache
     *
     * @since 1.0.0
     */
    public function clear_cache() {
        $this->css_cache = [];
    }
}