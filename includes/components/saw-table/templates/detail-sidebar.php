<?php
/**
 * SAW Table - Detail Sidebar Template
 * 
 * Standalone template for detail sidebar.
 * Uses only SAW Table CSS classes and renderers.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Templates
 * @version     1.0.0
 * @since       5.2.0
 * 
 * Required variables:
 * @var array  $item         Item data
 * @var array  $config       Module config
 * @var array  $related_data Related data (optional)
 * @var string $entity       Entity slug
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// VALIDATION
// ============================================
if (empty($item) || empty($config)) {
    echo '<div class="saw-alert saw-alert-danger">Missing required data</div>';
    return;
}

// ============================================
// SETUP
// ============================================
$entity = $entity ?? $config['entity'] ?? 'unknown';
$related_data = $related_data ?? [];
$detail_config = $config['detail'] ?? [];

// Route for URLs
$route = $config['route'] ?? str_replace('_', '-', $entity);
$route = trim(str_replace('admin/', '', $route), '/');
$base_url = home_url('/admin/' . $route);

// URLs
$close_url = $base_url . '/';
$edit_url = $base_url . '/' . intval($item['id']) . '/edit';
$delete_url = $base_url . '/delete/' . intval($item['id']);

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', $entity) 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// PERMISSIONS
// ============================================
$can_edit = true;
$can_delete = true;

if (class_exists('SAW_Table_Permissions')) {
    $can_edit = SAW_Table_Permissions::canEdit($entity);
    $can_delete = SAW_Table_Permissions::canDelete($entity);
} elseif (function_exists('saw_can')) {
    $can_edit = saw_can('edit', $entity);
    $can_delete = saw_can('delete', $entity);
}

// ============================================
// DISPLAY NAME
// ============================================
$display_name = '';

// Try common fields
if (!empty($item['display_name'])) {
    $display_name = $item['display_name'];
} elseif (!empty($item['name'])) {
    $display_name = $item['name'];
} elseif (!empty($item['title'])) {
    $display_name = $item['title'];
} elseif (!empty($item['first_name'])) {
    $display_name = trim($item['first_name'] . ' ' . ($item['last_name'] ?? ''));
}

if (empty($display_name)) {
    $display_name = ($config['singular'] ?? 'Záznam') . ' #' . $item['id'];
}

// ============================================
// HEADER IMAGE
// ============================================
$header_image = '';
$header_image_config = $detail_config['header_image'] ?? [];

if (!empty($header_image_config['enabled'])) {
    $image_field = $header_image_config['field'] ?? 'logo_url';
    $header_image = $item[$image_field] ?? '';
}

// Fallback detection
if (empty($header_image)) {
    if (!empty($item['header_image'])) {
        $header_image = $item['header_image'];
    } elseif (!empty($item['logo_url'])) {
        $header_image = $item['logo_url'];
    } elseif (!empty($item['image_url'])) {
        $header_image = $item['image_url'];
    }
}

$fallback_icon = $header_image_config['fallback_icon'] ?? '';

// ============================================
// HEADER BADGES
// ============================================
$header_badges_html = '';
$header_badges = $detail_config['header_badges'] ?? [];

if (!empty($header_badges) && class_exists('SAW_Badge_Renderer')) {
    $header_badges_html = SAW_Badge_Renderer::render_badges($header_badges, $item);
} else {
    // Fallback - basic ID badge
    $header_badges_html = '<span class="saw-badge-transparent">ID: ' . intval($item['id']) . '</span>';
}

// ============================================
// SECTIONS
// ============================================
$sections = $detail_config['sections'] ?? [];

// ============================================
// ACTIONS
// ============================================
$actions = $detail_config['actions'] ?? [
    'edit' => [
        'label' => $tr('btn_edit', 'Upravit'),
        'icon' => 'edit',
        'type' => 'primary',
        'permission' => 'edit',
    ],
    'delete' => [
        'label' => $tr('btn_delete', 'Smazat'),
        'icon' => 'trash',
        'type' => 'danger',
        'permission' => 'delete',
        'confirm' => $tr('confirm_delete', 'Opravdu chcete smazat tento záznam?'),
    ],
];
?>

<div class="saw-table-sidebar saw-table-sidebar-detail" 
     data-mode="detail" 
     data-entity="<?php echo esc_attr($entity); ?>" 
     data-current-id="<?php echo esc_attr($item['id']); ?>">
    
    <!-- ============================================
         SIDEBAR HEADER
         ============================================ -->
    <div class="saw-table-sidebar-header">
        <div class="saw-table-sidebar-title">
            <span class="saw-table-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2 class="saw-table-sidebar-heading">
                <?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo intval($item['id']); ?>
            </h2>
        </div>
        
        <div class="saw-table-sidebar-controls">
            <!-- Navigation buttons -->
            <div class="saw-table-sidebar-nav">
                <button type="button" class="saw-table-nav-btn" data-action="prev" title="<?php echo esc_attr($tr('nav_prev', 'Předchozí')); ?>">
                    <span>&lt;</span>
                </button>
                <button type="button" class="saw-table-nav-btn" data-action="next" title="<?php echo esc_attr($tr('nav_next', 'Následující')); ?>">
                    <span>&gt;</span>
                </button>
            </div>
            
            <!-- Close button -->
            <a href="<?php echo esc_url($close_url); ?>" class="saw-table-sidebar-close" title="<?php echo esc_attr($tr('close', 'Zavřít')); ?>">
                <span>&times;</span>
            </a>
        </div>
    </div>
    
    <!-- ============================================
         SIDEBAR CONTENT
         ============================================ -->
    <div class="saw-table-sidebar-content">
        
        <!-- ============================================
             DETAIL HEADER (Blue with badges)
             ============================================ -->
        <div class="saw-table-detail-header<?php echo !empty($header_image) ? ' has-image' : ''; ?>">
            <div class="saw-table-detail-header-inner">
                
                <?php if (!empty($header_image)): ?>
                <!-- Header with Image -->
                <div class="saw-table-detail-header-with-image">
                    <div class="saw-table-detail-header-image-col">
                        <img src="<?php echo esc_url($header_image); ?>" 
                             alt="<?php echo esc_attr($display_name); ?>"
                             class="saw-table-detail-header-image">
                    </div>
                    <div class="saw-table-detail-header-text-col">
                        <h3 class="saw-table-detail-header-title"><?php echo esc_html($display_name); ?></h3>
                        <?php if (!empty($header_badges_html)): ?>
                        <div class="saw-table-detail-header-meta">
                            <?php echo $header_badges_html; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php elseif (!empty($fallback_icon)): ?>
                <!-- Header with Fallback Icon -->
                <div class="saw-table-detail-header-with-image">
                    <div class="saw-table-detail-header-image-col">
                        <span class="saw-table-header-image-fallback"><?php echo esc_html($fallback_icon); ?></span>
                    </div>
                    <div class="saw-table-detail-header-text-col">
                        <h3 class="saw-table-detail-header-title"><?php echo esc_html($display_name); ?></h3>
                        <?php if (!empty($header_badges_html)): ?>
                        <div class="saw-table-detail-header-meta">
                            <?php echo $header_badges_html; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Header without Image -->
                <h3 class="saw-table-detail-header-title"><?php echo esc_html($display_name); ?></h3>
                <?php if (!empty($header_badges_html)): ?>
                <div class="saw-table-detail-header-meta">
                    <?php echo $header_badges_html; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
            </div>
            <div class="saw-table-detail-header-stripe"></div>
        </div>
        
        <!-- ============================================
             SECTIONS
             ============================================ -->
        <div class="saw-table-detail-wrapper">
            <div class="saw-table-detail-stack">
                
                <?php if (!empty($sections)): ?>
                    <?php foreach ($sections as $section_key => $section_config): ?>
                        <?php
                        // Render section using SAW_Section_Renderer if available
                        if (class_exists('SAW_Section_Renderer')) {
                            echo SAW_Section_Renderer::render($section_config, $item, $related_data, $entity);
                        } else {
                            // Fallback rendering
                            echo self::render_section_fallback($section_key, $section_config, $item, $related_data, $tr);
                        }
                        ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- No sections configured - show basic info -->
                    <div class="saw-table-section">
                        <div class="saw-table-section-head">
                            <h4 class="saw-table-section-title">📋 <?php echo esc_html($tr('section_info', 'Informace')); ?></h4>
                        </div>
                        <div class="saw-table-section-body">
                            <?php foreach ($item as $key => $value): ?>
                                <?php if (!in_array($key, ['id', 'created_at', 'updated_at']) && !empty($value) && is_scalar($value)): ?>
                                <div class="saw-table-info-row">
                                    <span class="saw-table-info-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></span>
                                    <span class="saw-table-info-val"><?php echo esc_html($value); ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
        
    </div>
    
    <!-- ============================================
         FLOATING ACTION BUTTONS
         ============================================ -->
    <div class="saw-table-floating-actions">
        <?php if ($can_edit): ?>
        <a href="<?php echo esc_url($edit_url); ?>" 
           class="saw-table-floating-btn edit" 
           title="<?php echo esc_attr($tr('btn_edit', 'Upravit')); ?>">
            <span class="dashicons dashicons-edit"></span>
        </a>
        <?php endif; ?>
        
        <?php if ($can_delete): ?>
        <button type="button" 
                class="saw-table-floating-btn delete" 
                data-action="delete"
                data-id="<?php echo intval($item['id']); ?>"
                data-confirm="<?php echo esc_attr($actions['delete']['confirm'] ?? $tr('confirm_delete', 'Opravdu chcete smazat tento záznam?')); ?>"
                title="<?php echo esc_attr($tr('btn_delete', 'Smazat')); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
        <?php endif; ?>
    </div>
    
</div>

<?php
/**
 * Fallback section rendering when SAW_Section_Renderer is not available
 */
function render_section_fallback($key, $config, $item, $related_data, $tr) {
    $type = $config['type'] ?? 'info_rows';
    $title = $config['title'] ?? ucfirst($key);
    $icon = $config['icon'] ?? '📋';
    
    // Check condition
    if (!empty($config['condition'])) {
        $condition = $config['condition'];
        $condition = preg_replace_callback(
            '/\$item\[([\'"])(.+?)\1\]/',
            function($matches) use ($item) {
                $field = $matches[2];
                $value = $item[$field] ?? null;
                if (is_null($value)) return 'null';
                if (is_bool($value)) return $value ? 'true' : 'false';
                if (is_string($value)) return "'" . addslashes($value) . "'";
                return $value;
            },
            $condition
        );
        try {
            if (!eval("return {$condition};")) {
                return '';
            }
        } catch (Exception $e) {
            return '';
        }
    }
    
    ob_start();
    ?>
    <div class="saw-table-section">
        <div class="saw-table-section-head">
            <h4 class="saw-table-section-title">
                <?php echo esc_html($icon); ?>
                <?php echo esc_html($title); ?>
                <?php if (!empty($config['show_count']) && isset($config['data_key'])): ?>
                    <?php $count = isset($related_data[$config['data_key']]) ? count($related_data[$config['data_key']]) : 0; ?>
                    <span class="saw-table-section-count"><?php echo intval($count); ?></span>
                <?php endif; ?>
            </h4>
        </div>
        <div class="saw-table-section-body">
            <?php if ($type === 'info_rows' && !empty($config['rows'])): ?>
                <?php foreach ($config['rows'] as $row): ?>
                    <?php
                    $field = $row['field'] ?? '';
                    $value = $item[$field] ?? '';
                    
                    // Skip empty values unless configured otherwise
                    if (empty($value) && $value !== '0' && empty($row['show_empty'])) {
                        continue;
                    }
                    
                    // Check row condition
                    if (!empty($row['condition'])) {
                        $row_condition = $row['condition'];
                        $row_condition = preg_replace_callback(
                            '/\$item\[([\'"])(.+?)\1\]/',
                            function($matches) use ($item) {
                                $field = $matches[2];
                                $value = $item[$field] ?? null;
                                if (is_null($value)) return 'null';
                                if (is_bool($value)) return $value ? 'true' : 'false';
                                if (is_string($value)) return "'" . addslashes($value) . "'";
                                return $value;
                            },
                            $row_condition
                        );
                        try {
                            if (!eval("return {$row_condition};")) {
                                continue;
                            }
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                    
                    $label = $row['label'] ?? ucfirst($field);
                    $bold = !empty($row['bold']);
                    ?>
                    <div class="saw-table-info-row">
                        <span class="saw-table-info-label"><?php echo esc_html($label); ?></span>
                        <span class="saw-table-info-val<?php echo $bold ? ' saw-table-info-val-bold' : ''; ?>">
                            <?php echo esc_html($value); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                
            <?php elseif ($type === 'metadata'): ?>
                <?php if (!empty($item['created_at_formatted']) || !empty($item['created_at'])): ?>
                <div class="saw-table-info-row saw-table-info-row-meta">
                    <span class="saw-table-info-label"><?php echo esc_html($tr('field_created_at', 'Vytvořeno')); ?></span>
                    <span class="saw-table-info-val">
                        <?php echo esc_html($item['created_at_formatted'] ?? date_i18n('j. n. Y H:i', strtotime($item['created_at']))); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at_formatted']) || !empty($item['updated_at'])): ?>
                <div class="saw-table-info-row saw-table-info-row-meta">
                    <span class="saw-table-info-label"><?php echo esc_html($tr('field_updated_at', 'Aktualizováno')); ?></span>
                    <span class="saw-table-info-val">
                        <?php echo esc_html($item['updated_at_formatted'] ?? date_i18n('j. n. Y H:i', strtotime($item['updated_at']))); ?>
                    </span>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p class="saw-table-text-muted"><?php echo esc_html($tr('section_not_configured', 'Sekce není nakonfigurována')); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>