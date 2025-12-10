<?php
/**
 * SAW Table - Detail Renderer
 * 
 * Renders detail sidebar using SAW Table template.
 * Uses sawt- CSS prefix.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
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
     * Translation function
     * @var callable|null
     */
    private static $translator = null;
    
    /**
     * Initialize template directory
     */
    private static function init() {
        if (self::$template_dir === null) {
            self::$template_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/templates/';
        }
    }
    
    /**
     * Set translator function
     */
    public static function set_translator($translator) {
        self::$translator = $translator;
    }
    
    /**
     * Translate key
     */
    private static function tr($key, $fallback = null) {
        if (self::$translator && is_callable(self::$translator)) {
            return call_user_func(self::$translator, $key, $fallback);
        }
        return $fallback ?? $key;
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
            return '<div class="sawt-alert sawt-alert-danger">' . 
                   self::tr('record_not_found', 'Záznam nebyl nalezen') . '</div>';
        }
        
        $entity = $entity ?: ($config['entity'] ?? 'unknown');
        $singular = $config['singular'] ?? ucfirst($entity);
        $icon = $config['icon'] ?? '📋';
        
        // Get title field
        $title_field = $config['detail']['title_field'] ?? 'name';
        $title = $item[$title_field] ?? $item['name'] ?? $item['title'] ?? 'ID: ' . $item['id'];
        
        // URLs
        $route = $config['route'] ?? $entity;
        $base_url = home_url('/admin/' . $route);
        $edit_url = $base_url . '/' . $item['id'] . '/edit';
        $close_url = $base_url;
        
        ob_start();
        ?>
        <div class="sawt-detail-sidebar" data-entity="<?php echo esc_attr($entity); ?>" data-id="<?php echo esc_attr($item['id']); ?>">
            
            <!-- Sidebar Header -->
            <header class="sawt-sidebar-header">
                <div class="sawt-sidebar-header-left">
                    <span class="sawt-sidebar-icon"><?php echo esc_html($icon); ?></span>
                    <h3 class="sawt-sidebar-title"><?php echo esc_html($singular); ?></h3>
                </div>
                
                <div class="sawt-sidebar-header-right">
                    <div class="sawt-sidebar-nav">
                        <button type="button" class="sawt-sidebar-nav-btn" data-nav="prev" title="<?php echo esc_attr(self::tr('nav_prev', 'Předchozí')); ?>">‹</button>
                        <button type="button" class="sawt-sidebar-nav-btn" data-nav="next" title="<?php echo esc_attr(self::tr('nav_next', 'Další')); ?>">›</button>
                    </div>
                    
                    <a href="<?php echo esc_url($close_url); ?>" class="sawt-sidebar-close" data-close-sidebar title="<?php echo esc_attr(self::tr('close', 'Zavřít')); ?>">×</a>
                </div>
            </header>
            
            <!-- Detail Header (Blue) -->
            <?php echo self::render_detail_header($config, $item, $title); ?>
            
            <!-- Content -->
            <div class="sawt-sidebar-content">
                <div class="sawt-detail-wrapper">
                    <div class="sawt-detail-stack">
                        <?php echo self::render_sections($config, $item, $related_data, $entity); ?>
                    </div>
                </div>
            </div>
            
            <!-- Footer Actions -->
            <?php echo self::render_actions($config, $item, $entity, $edit_url); ?>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render detail header (blue gradient)
     */
    private static function render_detail_header($config, $item, $title) {
        $header_image = $config['detail']['header_image'] ?? [];
        $header_badges = $config['detail']['header_badges'] ?? [];
        
        ob_start();
        ?>
        <div class="sawt-detail-header">
            <div class="sawt-detail-header-inner">
                <?php if (!empty($header_image['enabled'])): ?>
                    <?php echo self::render_header_with_image($header_image, $item, $title, $header_badges); ?>
                <?php else: ?>
                    <h2 class="sawt-detail-header-title"><?php echo esc_html($title); ?></h2>
                    <?php if (!empty($header_badges)): ?>
                        <div class="sawt-detail-header-meta">
                            <?php echo self::render_header_badges_html($header_badges, $item); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="sawt-detail-header-stripe"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render header with image
     */
    private static function render_header_with_image($header_image, $item, $title, $header_badges) {
        $image_field = $header_image['field'] ?? 'image_url';
        $image_url = $item[$image_field] ?? '';
        $fallback_icon = $header_image['fallback_icon'] ?? '';
        $size = $header_image['size'] ?? '64px';
        $rounded = !empty($header_image['rounded']);
        
        ob_start();
        ?>
        <div class="sawt-detail-header-with-image">
            <div class="sawt-detail-header-image-col">
                <?php if ($image_url): ?>
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="" 
                         class="sawt-detail-header-image<?php echo $rounded ? ' is-rounded' : ''; ?>"
                         style="width: <?php echo esc_attr($size); ?>; height: <?php echo esc_attr($size); ?>;">
                <?php elseif ($fallback_icon): ?>
                    <div class="sawt-detail-header-fallback"><?php echo esc_html($fallback_icon); ?></div>
                <?php endif; ?>
            </div>
            <div class="sawt-detail-header-text-col">
                <h2 class="sawt-detail-header-title"><?php echo esc_html($title); ?></h2>
                <?php if (!empty($header_badges)): ?>
                    <div class="sawt-detail-header-meta">
                        <?php echo self::render_header_badges_html($header_badges, $item); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render header badges HTML
     */
    private static function render_header_badges_html($badges, $item) {
        if (class_exists('SAW_Badge_Renderer')) {
            return SAW_Badge_Renderer::render_badges($badges, $item);
        }
        
        // Fallback
        return '<span class="sawt-badge-transparent">ID: ' . intval($item['id']) . '</span>';
    }
    
    /**
     * Render all sections
     */
    private static function render_sections($config, $item, $related_data, $entity) {
        $sections = $config['detail']['sections'] ?? [];
        
        if (empty($sections)) {
            return '<div class="sawt-alert sawt-alert-info">' . 
                   self::tr('no_sections', 'Žádné sekce definovány') . '</div>';
        }
        
        ob_start();
        foreach ($sections as $section_key => $section_config) {
            if (class_exists('SAW_Section_Renderer')) {
                echo SAW_Section_Renderer::render($section_config, $item, $related_data, $entity);
            }
        }
        return ob_get_clean();
    }
    
    /**
     * Render action buttons
     */
    private static function render_actions($config, $item, $entity, $edit_url) {
        $actions = $config['detail']['actions'] ?? [];
        
        // Default actions if not specified
        if (empty($actions)) {
            $actions = [
                'edit' => [
                    'label' => self::tr('btn_edit', 'Upravit'),
                    'icon' => 'edit',
                    'type' => 'primary',
                    'permission' => 'edit',
                ],
            ];
        }
        
        // Filter by permissions
        if (class_exists('SAW_Table_Permissions')) {
            $actions = SAW_Table_Permissions::filterActionButtons($actions, $entity);
        }
        
        if (empty($actions)) {
            return '';
        }
        
        $route = $config['route'] ?? $entity;
        $base_url = home_url('/admin/' . $route);
        
        ob_start();
        ?>
        <footer class="sawt-sidebar-footer">
            <?php foreach ($actions as $action_key => $action): ?>
                <?php
                $label = $action['label'] ?? ucfirst($action_key);
                $icon = $action['icon'] ?? '';
                $type = $action['type'] ?? 'secondary';
                $confirm = $action['confirm'] ?? '';
                
                // Determine URL
                switch ($action_key) {
                    case 'edit':
                        $url = $base_url . '/' . $item['id'] . '/edit';
                        break;
                    case 'delete':
                        $url = '#';
                        break;
                    default:
                        $url = $action['url'] ?? '#';
                        if (strpos($url, '{id}') !== false) {
                            $url = str_replace('{id}', $item['id'], $url);
                        }
                }
                
                $btn_class = 'sawt-btn sawt-btn-' . $type;
                ?>
                
                <?php if ($action_key === 'delete'): ?>
                    <button type="button" 
                            class="<?php echo esc_attr($btn_class); ?>"
                            data-action="delete"
                            data-id="<?php echo esc_attr($item['id']); ?>"
                            data-entity="<?php echo esc_attr($entity); ?>"
                            data-confirm="<?php echo esc_attr($confirm); ?>">
                        <?php if ($icon): ?>
                            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                        <?php endif; ?>
                        <?php echo esc_html($label); ?>
                    </button>
                <?php else: ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($btn_class); ?>">
                        <?php if ($icon): ?>
                            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                        <?php endif; ?>
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </footer>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render only the content sections (for AJAX updates)
     */
    public static function render_content($config, $item, $related_data = [], $entity = '') {
        if (empty($item) || empty($config['detail']['sections'])) {
            return '';
        }
        
        return self::render_sections($config, $item, $related_data, $entity);
    }
    
    /**
     * Render header badges (public method)
     */
    public static function render_header_badges($config, $item) {
        $badges = $config['detail']['header_badges'] ?? [];
        return self::render_header_badges_html($badges, $item);
    }
    
    /**
     * Check if module has SAW Table detail config
     */
    public static function has_config($config) {
        return !empty($config['detail']['sections']);
    }
}
