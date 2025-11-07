<?php
/**
 * Detail Sidebar Template
 *
 * @package     SAW_Visitors
 * @subpackage  Components/AdminTable
 * @version     1.1.0
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

// Build URLs from module config
$route = $config['route'] ?? '';

$close_url = home_url('/' . $route . '/');
$edit_url = home_url('/' . $route . '/' . intval($item['id']) . '/edit');
$delete_url = home_url('/' . $route . '/delete/' . intval($item['id']));
?>

<div class="saw-sidebar saw-sidebar-detail" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span class="saw-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2 class="saw-sidebar-heading"><?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo intval($item['id']); ?></h2>
        </div>
        
        <div class="saw-sidebar-nav-controls">
            <button type="button" class="saw-sidebar-nav-btn" onclick="saw_navigate_sidebar('prev')" title="Předchozí (Ctrl+↑)">
                ‹
            </button>
            <button type="button" class="saw-sidebar-nav-btn" onclick="saw_navigate_sidebar('next')" title="Další (Ctrl+↓)">
                ›
            </button>
            <a href="<?php echo esc_url($close_url); ?>" 
               class="saw-sidebar-close" 
               title="Zavřít (ESC)">
                <span class="saw-sidebar-close-icon">&times;</span>
            </a>
        </div>
    </div>
    
    <div class="saw-sidebar-content">
        <?php
        if (file_exists($detail_template)) {
            require $detail_template;
        } else {
            echo '<div class="saw-sidebar-section"><h3>Detail</h3><dl>';
            foreach ($item as $key => $value) {
                if ($key === 'id') continue;
                echo '<dt>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . '</dt>';
                echo '<dd>' . esc_html($value) . '</dd>';
            }
            echo '</dl></div>';
        }
        ?>
    </div>
    
</div>

<!-- Floating Action Buttons -->
<div class="saw-sidebar-floating-actions">
    <a href="<?php echo esc_url($edit_url); ?>" 
       class="saw-floating-action-btn edit" 
       title="Upravit">
        <span class="dashicons dashicons-edit"></span>
    </a>
    <button type="button" 
            class="saw-floating-action-btn delete" 
            onclick="if(confirm('Opravdu smazat tento záznam?')) { window.location.href='<?php echo esc_js($delete_url); ?>'; }"
            title="Smazat">
        <span class="dashicons dashicons-trash"></span>
    </button>
</div>

<style>
.saw-sidebar-close {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 48px !important;
    height: 48px !important;
    min-width: 48px !important;
    min-height: 48px !important;
    padding: 0 !important;
    margin: 0 !important;
    background: transparent !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    border-radius: 8px !important;
    flex-shrink: 0 !important;
}

.saw-sidebar-close-icon {
    display: block !important;
    font-size: 42px !important;
    font-weight: 200 !important;
    line-height: 1 !important;
    color: #6b7280 !important;
    font-family: Arial, sans-serif !important;
    text-decoration: none !important;
}

.saw-sidebar-close:hover {
    background: #f3f4f6 !important;
}

.saw-sidebar-close:hover .saw-sidebar-close-icon {
    color: #111827 !important;
}

.saw-sidebar-header {
    display: flex !important;
    padding: 0 16px !important;
    border-bottom: 1px solid #e5e7eb !important;
    background: #fafbfc !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 16px !important;
    min-height: 60px !important;
    height: 60px !important;
}

.saw-sidebar-title {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    flex: 1 !important;
}

.saw-sidebar-icon {
    font-size: 20px !important;
    line-height: 1 !important;
}

.saw-sidebar-heading {
    margin: 0 !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    color: #111827 !important;
}

.saw-sidebar-nav-controls {
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
}

.saw-sidebar-nav-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 36px !important;
    height: 36px !important;
    padding: 0 !important;
    background: transparent !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    font-size: 28px !important;
    line-height: 1 !important;
    color: #6b7280 !important;
    font-weight: 300 !important;
    font-family: Arial, sans-serif !important;
    padding-bottom: 2px !important;
}

.saw-sidebar-nav-btn:hover {
    background: #f3f4f6 !important;
    border-color: #d1d5db !important;
    color: #111827 !important;
}

.saw-sidebar-nav-btn:active {
    background: #e5e7eb !important;
}
</style>