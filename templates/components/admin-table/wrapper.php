<?php
if (!defined('ABSPATH')) {
    exit;
}

$entity = $entity ?? 'entity';
$config = $config ?? array();

$title = $config['title'] ?? ucfirst($entity);
$subtitle = $config['subtitle'] ?? '';
$create_url = $config['create_url'] ?? '';
$add_new_label = $config['add_new'] ?? 'Přidat nový';
$total_items = $config['total_items'] ?? 0;
$search_value = $config['search_value'] ?? '';
$singular = $config['singular'] ?? $entity;
$plural = $config['plural'] ?? $entity;
?>

<div class="saw-card">
    <div class="saw-card-header-unified">
        <div class="saw-unified-title-row">
            <div class="saw-unified-title-content">
                <h1 class="saw-unified-title"><?php echo esc_html($title); ?></h1>
                <?php if (!empty($subtitle)): ?>
                    <p class="saw-unified-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($create_url)): ?>
                <div class="saw-unified-actions">
                    <a href="<?php echo esc_url($create_url); ?>" class="saw-btn saw-btn-primary">
                        <span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html($add_new_label); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="saw-unified-controls-row">
            <div class="saw-unified-count">
                <span class="saw-count-label"><?php echo esc_html($plural); ?></span>
                (<span id="saw-<?php echo esc_attr($entity); ?>-count" class="saw-count-number"><?php echo esc_html($total_items); ?></span>)
            </div>
            <?php if ($config['search']): ?>
                <?php
                if (!class_exists('SAW_Component_Search')) {
                    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
                }
                
                $search = new SAW_Component_Search($entity, array(
                    'placeholder' => 'Hledat ' . $singular . '...',
                    'search_value' => $search_value,
                    'ajax_enabled' => $config['ajax_search'],
                    'ajax_action' => $config['ajax_action'],
                ));
                
                $search->render();
                ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="saw-card-body">
        <?php 
        include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/body.php';
        ?>
        
        <div class="saw-table-loading" style="display: none;">
            <div class="saw-spinner"></div>
            <span>Načítání...</span>
        </div>
    </div>
    
    <?php 
    include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/footer.php';
    ?>
</div>