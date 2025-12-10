<?php
/**
 * SAW Table - Detail Sidebar Template
 * 
 * Template for detail view sidebar.
 * Uses sawt- CSS prefix.
 * 
 * This is an alternative template if you don't want to use SAW_Detail_Renderer.
 * Most modules should use SAW_Detail_Renderer::render() instead.
 * 
 * Variables expected:
 * - $config: Module configuration
 * - $item: Item data
 * - $related_data: Related data array
 * - $entity: Entity name
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Templates
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Setup
$config = $config ?? [];
$item = $item ?? [];
$related_data = $related_data ?? [];
$entity = $entity ?? ($config['entity'] ?? 'item');

if (empty($item)) {
    echo '<div class="sawt-alert sawt-alert-danger">Záznam nebyl nalezen</div>';
    return;
}

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

// Translation helper
$tr = $tr ?? function($key, $fallback = null) use ($config) {
    if (function_exists('saw_get_translations')) {
        static $translations = null;
        if ($translations === null) {
            $lang = class_exists('SAW_Component_Language_Switcher') 
                ? SAW_Component_Language_Switcher::get_user_language() 
                : 'cs';
            $translations = saw_get_translations($lang, 'admin', $config['entity'] ?? 'common');
        }
        return $translations[$key] ?? $fallback ?? $key;
    }
    return $fallback ?? $key;
};

// Header config
$header_image = $config['detail']['header_image'] ?? [];
$header_badges = $config['detail']['header_badges'] ?? [];
$sections = $config['detail']['sections'] ?? [];
$actions = $config['detail']['actions'] ?? [];
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
                <button type="button" class="sawt-sidebar-nav-btn" data-nav="prev" title="<?php echo esc_attr($tr('nav_prev', 'Předchozí')); ?>">‹</button>
                <button type="button" class="sawt-sidebar-nav-btn" data-nav="next" title="<?php echo esc_attr($tr('nav_next', 'Další')); ?>">›</button>
            </div>
            
            <a href="<?php echo esc_url($close_url); ?>" class="sawt-sidebar-close" data-close-sidebar title="<?php echo esc_attr($tr('close', 'Zavřít')); ?>">×</a>
        </div>
    </header>
    
    <!-- Detail Header (Blue) -->
    <div class="sawt-detail-header">
        <div class="sawt-detail-header-inner">
            <?php if (!empty($header_image['enabled'])): ?>
                <?php
                $image_field = $header_image['field'] ?? 'image_url';
                $image_url = $item[$image_field] ?? '';
                $fallback_icon = $header_image['fallback_icon'] ?? '';
                $size = $header_image['size'] ?? '64px';
                $rounded = !empty($header_image['rounded']);
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
                                <?php 
                                if (class_exists('SAW_Badge_Renderer')) {
                                    echo SAW_Badge_Renderer::render_badges($header_badges, $item);
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <h2 class="sawt-detail-header-title"><?php echo esc_html($title); ?></h2>
                <?php if (!empty($header_badges)): ?>
                    <div class="sawt-detail-header-meta">
                        <?php 
                        if (class_exists('SAW_Badge_Renderer')) {
                            echo SAW_Badge_Renderer::render_badges($header_badges, $item);
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="sawt-detail-header-stripe"></div>
    </div>
    
    <!-- Content -->
    <div class="sawt-sidebar-content">
        <div class="sawt-detail-wrapper">
            <div class="sawt-detail-stack">
                <?php 
                if (!empty($sections) && class_exists('SAW_Section_Renderer')) {
                    foreach ($sections as $section_key => $section_config) {
                        echo SAW_Section_Renderer::render($section_config, $item, $related_data, $entity);
                    }
                } else {
                    echo '<div class="sawt-alert sawt-alert-info">' . esc_html($tr('no_sections', 'Žádné sekce definovány')) . '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Footer Actions -->
    <?php if (!empty($actions)): ?>
        <?php
        // Filter by permissions
        if (class_exists('SAW_Table_Permissions')) {
            $actions = SAW_Table_Permissions::filterActionButtons($actions, $entity);
        }
        ?>
        <?php if (!empty($actions)): ?>
            <footer class="sawt-sidebar-footer">
                <?php foreach ($actions as $action_key => $action): ?>
                    <?php
                    $label = $action['label'] ?? ucfirst($action_key);
                    $icon_name = $action['icon'] ?? '';
                    $type = $action['type'] ?? 'secondary';
                    $confirm = $action['confirm'] ?? '';
                    
                    // Determine URL
                    switch ($action_key) {
                        case 'edit':
                            $url = $edit_url;
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
                            <?php if ($icon_name): ?>
                                <span class="dashicons dashicons-<?php echo esc_attr($icon_name); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html($label); ?>
                        </button>
                    <?php else: ?>
                        <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($btn_class); ?>">
                            <?php if ($icon_name): ?>
                                <span class="dashicons dashicons-<?php echo esc_attr($icon_name); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </footer>
        <?php endif; ?>
    <?php endif; ?>
    
</div>
