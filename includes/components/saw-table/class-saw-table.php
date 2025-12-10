<?php
/**
 * SAW Table - Main Orchestration Class
 *
 * Central class that coordinates all SAW Table components.
 * Handles config loading, permissions, translations, and rendering delegation.
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
 * SAW Table Main Class
 *
 * @since 3.0.0
 */
class SAW_Table {
    
    /**
     * Module entity name
     * @var string
     */
    protected $entity;
    
    /**
     * Module configuration
     * @var array
     */
    protected $config;
    
    /**
     * Translations helper
     * @var SAW_Table_Translations
     */
    protected $translations;
    
    /**
     * Permissions helper
     * @var SAW_Table_Permissions
     */
    protected $permissions;
    
    /**
     * Context helper
     * @var SAW_Table_Context
     */
    protected $context;
    
    /**
     * Feature flag for new table system
     */
    const FEATURE_FLAG = 'SAW_USE_NEW_TABLE';
    
    /**
     * Constructor
     *
     * @param string $entity Module entity name
     * @param array  $config Module configuration
     */
    public function __construct($entity, $config = []) {
        $this->entity = sanitize_key($entity);
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize helpers
        $this->init_helpers();
        
        // Parse and validate config
        $this->config = $this->parse_config($config);
        
        // Set translator for renderers
        $this->setup_renderers();
    }
    
    /**
     * Load required dependencies
     *
     * @since 1.0.0
     */
    protected function load_dependencies() {
        $base_path = dirname(__FILE__) . '/';
        
        // Core classes
        $files = [
            'class-saw-table-config.php',
            'class-saw-table-permissions.php',
            'class-saw-table-translations.php',
            'class-saw-table-context.php',
        ];
        
        foreach ($files as $file) {
            $path = $base_path . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Renderers
        $renderers = [
            'class-badge-renderer.php',
            'class-row-renderer.php',
            'class-section-renderer.php',
            'class-detail-renderer.php',
        ];
        
        foreach ($renderers as $renderer) {
            $path = $base_path . 'renderers/' . $renderer;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
    
    /**
     * Initialize helper classes
     *
     * @since 1.0.0
     */
    protected function init_helpers() {
        // Initialize translations
        if (class_exists('SAW_Table_Translations')) {
            $this->translations = new SAW_Table_Translations('admin', $this->entity);
        }
        
        // Initialize permissions
        if (class_exists('SAW_Table_Permissions')) {
            SAW_Table_Permissions::init();
            $this->permissions = new SAW_Table_Permissions();
        }
        
        // Initialize context
        if (class_exists('SAW_Table_Context')) {
            $this->context = new SAW_Table_Context();
        }
    }
    
    /**
     * Setup renderers with translator
     *
     * @since 1.0.0
     */
    protected function setup_renderers() {
        if (!$this->translations) {
            return;
        }
        
        $translator = $this->translations->createTranslator();
        
        if (class_exists('SAW_Badge_Renderer')) {
            SAW_Badge_Renderer::set_translator($translator);
        }
        
        if (class_exists('SAW_Row_Renderer')) {
            SAW_Row_Renderer::set_translator($translator);
        }
        
        if (class_exists('SAW_Section_Renderer')) {
            SAW_Section_Renderer::set_translator($translator);
        }
    }
    
    /**
     * Parse and validate configuration
     *
     * @param array $config Raw configuration
     * @return array Parsed configuration
     */
    protected function parse_config($config) {
        if (class_exists('SAW_Table_Config')) {
            return SAW_Table_Config::parse($config, $this->entity);
        }
        
        // Fallback: return as-is
        return $config;
    }
    
    /**
     * Check if new table system is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        return defined(self::FEATURE_FLAG) && constant(self::FEATURE_FLAG) === true;
    }
    
    /**
     * Get entity name
     *
     * @return string
     */
    public function get_entity() {
        return $this->entity;
    }
    
    /**
     * Get configuration
     *
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Get translations helper
     *
     * @return SAW_Table_Translations|null
     */
    public function get_translations() {
        return $this->translations;
    }
    
    /**
     * Get translation value
     *
     * @param string $key     Translation key
     * @param string $fallback Fallback value
     * @return string
     */
    public function tr($key, $fallback = null) {
        if ($this->translations) {
            return $this->translations->get($key, $fallback);
        }
        return $fallback ?? $key;
    }
    
    /**
     * Check permission
     *
     * @param string $action Action name (view, edit, delete, create)
     * @return bool
     */
    public function can($action) {
        if (class_exists('SAW_Table_Permissions')) {
            return SAW_Table_Permissions::can($this->entity, $action);
        }
        
        // Fallback: use saw_can if available
        if (function_exists('saw_can')) {
            return saw_can($action, $this->entity);
        }
        
        return true;
    }
    
    /**
     * Apply context filtering to query args
     *
     * @param array $args Query arguments
     * @return array Modified arguments
     */
    public function apply_context($args) {
        if ($this->context) {
            return $this->context->applyFilter($args, $this->config);
        }
        
        return $args;
    }
    
    /**
     * Get context-aware cache key
     *
     * @param string $base_key Base cache key
     * @return string
     */
    public function get_cache_key($base_key) {
        if ($this->context) {
            return $this->context->getCacheKey($base_key, $this->config);
        }
        
        return $base_key;
    }
    
    /**
     * Check if detail should be rendered from config
     *
     * @return bool
     */
    public function has_detail_config() {
        return !empty($this->config['detail']['sections']);
    }
    
    /**
     * Check if form should be rendered from config
     *
     * @return bool
     */
    public function has_form_config() {
        return !empty($this->config['form']['fields']);
    }
    
    /**
     * Render detail sidebar
     *
     * @param array $item        Item data
     * @param array $related_data Related data
     * @return string HTML
     */
    public function render_detail($item, $related_data = []) {
        // Check if new system is enabled and config exists
        if (self::is_enabled() && $this->has_detail_config()) {
            if (class_exists('SAW_Detail_Renderer')) {
                return SAW_Detail_Renderer::render(
                    $this->config,
                    $item,
                    $related_data,
                    $this->entity
                );
            }
        }
        
        // Fallback: use old template
        return $this->render_legacy_detail($item, $related_data);
    }
    
    /**
     * Render legacy detail template
     *
     * @param array $item        Item data
     * @param array $related_data Related data
     * @return string HTML
     */
    protected function render_legacy_detail($item, $related_data = []) {
        $module_slug = str_replace('_', '-', $this->entity);
        $template_path = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";
        
        if (!file_exists($template_path)) {
            return '<div class="saw-alert saw-alert-warning">' . 
                   esc_html__('Detail template not found', 'saw-visitors') . 
                   '</div>';
        }
        
        // Make variables available to template
        $entity = $this->entity;
        $config = $this->config;
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
    
    /**
     * Render header badges
     *
     * @param array $item Item data
     * @return string HTML
     */
    public function render_header_badges($item) {
        if (!class_exists('SAW_Badge_Renderer')) {
            return '';
        }
        
        $badges = $this->config['detail']['header_badges'] ?? [];
        
        if (empty($badges)) {
            return '';
        }
        
        return SAW_Badge_Renderer::render_badges($badges, $item);
    }
    
    /**
     * Render detail section
     *
     * @param array  $section_config Section configuration
     * @param array  $item           Item data
     * @param array  $related_data   Related data
     * @return string HTML
     */
    public function render_section($section_config, $item, $related_data = []) {
        if (!class_exists('SAW_Section_Renderer')) {
            return '';
        }
        
        return SAW_Section_Renderer::render(
            $section_config,
            $item,
            $related_data,
            $this->entity
        );
    }
    
    /**
     * Filter action buttons based on permissions
     *
     * @param array $buttons Button configurations
     * @return array Filtered buttons
     */
    public function filter_action_buttons($buttons) {
        if (class_exists('SAW_Table_Permissions')) {
            return SAW_Table_Permissions::filterActionButtons($buttons, $this->entity);
        }
        
        return $buttons;
    }
    
    /**
     * Get allowed actions for current user
     *
     * @return array
     */
    public function get_allowed_actions() {
        if (class_exists('SAW_Table_Permissions')) {
            return SAW_Table_Permissions::getAllowedActions($this->entity);
        }
        
        return ['list', 'view', 'create', 'edit', 'delete'];
    }
}
