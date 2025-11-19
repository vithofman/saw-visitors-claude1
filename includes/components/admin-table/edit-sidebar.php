<?php
/**
 * Edit Sidebar Template
 * 
 * Wraps edit form in proper sidebar structure with header, scrollable content, and close button.
 * 
 * @package SAW_Visitors
 * @since 1.0.0
 * 
 * @var array  $item    Item data
 * @var string $entity  Entity name (e.g., 'visitors', 'branches')
 * @var array  $config  Module configuration
 * @var string $form_html The rendered form HTML from form-template.php
 */

if (!defined('ABSPATH')) exit;

// Close URL should return to detail view, not list
$detail_url = home_url('/admin/' . $entity . '/' . $item['id'] . '/');
$item_name = $item['name'] ?? $item['first_name'] . ' ' . ($item['last_name'] ?? '') ?? '#' . $item['id'];
?>

<div class="saw-sidebar saw-detail-sidebar" data-entity="<?php echo esc_attr($entity); ?>" data-id="<?php echo intval($item['id']); ?>">
    
    <!-- Sidebar Header -->
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-header-content">
            <h2 class="saw-sidebar-title">
                <span class="dashicons dashicons-edit"></span>
                Upravit: <?php echo esc_html($item_name); ?>
            </h2>
            <a href="<?php echo esc_url($detail_url); ?>" 
               class="saw-sidebar-close" 
               title="Zavřít">
                <span class="dashicons dashicons-no-alt"></span>
            </a>
        </div>
    </div>

    <!-- Scrollable Content (Form) -->
    <div class="saw-sidebar-content">
        <?php echo $form_html; ?>
    </div>

</div>
