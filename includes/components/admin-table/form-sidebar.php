<?php
/**
 * Form Sidebar Template
 *
 * Displays create/edit form in sidebar.
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
$form_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/form-template.php";

// Build URLs from module config
$route = $config['route'] ?? '';
$close_url = home_url('/' . $route . '/');
?>

<div class="saw-sidebar" data-mode="form" data-entity="<?php echo esc_attr($entity); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span><?php echo esc_html($config['icon'] ?? '📝'); ?></span>
            <h2><?php echo $is_edit ? 'Upravit ' : 'Nový '; echo esc_html($config['singular'] ?? 'záznam'); ?></h2>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" 
           class="saw-sidebar-close" 
           title="Zavřít (ESC)">
            <span class="saw-sidebar-close-icon">&times;</span>
        </a>
    </div>
    
    <div class="saw-sidebar-content">
        <?php
        if (file_exists($form_template)) {
            $GLOBALS['saw_sidebar_form'] = true;
            
            // Extract account_types from config to make it available in template
            $account_types = $config['account_types'] ?? array();
            
            require $form_template;
            unset($GLOBALS['saw_sidebar_form']);
        } else {
            echo '<p>Form template not found: ' . esc_html($form_template) . '</p>';
        }
        ?>
    </div>
</div>

<style>
.saw-sidebar-header {
    height: 60px !important;
    min-height: 60px !important;
    padding: 0 16px !important;
}

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
</style>