<?php
/**
 * SAW Table - Detail Renderer
 * 
 * Renders detail sidebar using new SAW Table template.
 * Completely standalone - no dependency on legacy templates.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     1.1.0
 * @since       5.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Detail_Renderer {
    
    /**
     * Template directory path
     * @var string
     */
    private static $template_dir;
    
    /**
     * Initialize template directory
     */
    private static function init() {
        if (self::$template_dir === null) {
            self::$template_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/templates/';
        }
    }
    
    /**
     * Render complete detail sidebar
     * 
     * @param array  $config       Module config
     * @param array  $item         Item data
     * @param array  $related_data Related data
     * @param string $entity       Entity slug
     * @return string HTML
     */
    public static function render($config, $item, $related_data = [], $entity = '') {
        self::init();
        
        if (empty($item)) {
            return '<div class="saw-table-alert saw-table-alert-danger">Záznam nebyl nalezen</div>';
        }
        
        $entity = $entity ?: ($config['entity'] ?? 'unknown');
        
        // Use new SAW Table template
        $template = self::$template_dir . 'detail-sidebar.php';
        
        if (!file_exists($template)) {
            return '<div class="saw-table-alert saw-table-alert-danger">Template not found: ' . esc_html($template) . '</div>';
        }
        
        ob_start();
        include $template;
        return ob_get_clean();
    }
    
    /**
     * Render only the content sections (without sidebar wrapper)
     * For AJAX updates
     * 
     * @param array  $config       Module config
     * @param array  $item         Item data
     * @param array  $related_data Related data
     * @param string $entity       Entity slug
     * @return string HTML
     */
    public static function render_content($config, $item, $related_data = [], $entity = '') {
        if (empty($item) || empty($config['detail']['sections'])) {
            return '';
        }
        
        $sections = $config['detail']['sections'];
        $entity = $entity ?: ($config['entity'] ?? 'unknown');
        
        ob_start();
        ?>
        <div class="saw-table-detail-stack">
            <?php foreach ($sections as $section_key => $section_config): ?>
                <?php
                if (class_exists('SAW_Section_Renderer')) {
                    echo SAW_Section_Renderer::render($section_config, $item, $related_data, $entity);
                }
                ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render header badges
     * 
     * @param array $config Module config
     * @param array $item   Item data
     * @return string HTML
     */
    public static function render_header_badges($config, $item) {
        $badges = $config['detail']['header_badges'] ?? [];
        
        if (empty($badges)) {
            return '<span class="saw-badge-transparent">ID: ' . intval($item['id']) . '</span>';
        }
        
        if (class_exists('SAW_Badge_Renderer')) {
            return SAW_Badge_Renderer::render_badges($badges, $item);
        }
        
        // Fallback
        return '<span class="saw-badge-transparent">ID: ' . intval($item['id']) . '</span>';
    }
    
    /**
     * Check if module has SAW Table detail config
     * 
     * @param array $config Module config
     * @return bool
     */
    public static function has_config($config) {
        return !empty($config['detail']['sections']);
    }
}