<?php
/**
 * Admin Table Wrapper Template
 * 
 * Kompletní wrapper pro admin tabulku s hlavičkou, vyhledáváním a paginací
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

$entity = $entity ?? 'entity';
$config = $config ?? array();

$title = $config['title'] ?? ucfirst($entity);
$subtitle = $config['subtitle'] ?? '';
$create_url = $config['create_url'] ?? '';
$add_new_label = $config['add_new'] ?? 'Přidat nový';
$message = $config['message'] ?? '';
$message_type = $config['message_type'] ?? '';
$total_items = $config['total_items'] ?? 0;
$search_value = $config['search_value'] ?? '';
$singular = $config['singular'] ?? $entity;
$plural = $config['plural'] ?? $entity;
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title"><?php echo esc_html($title); ?></h1>
        <?php if (!empty($subtitle)): ?>
            <p class="saw-page-subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($create_url)): ?>
        <div class="saw-page-header-actions">
            <a href="<?php echo esc_url($create_url); ?>" class="saw-btn saw-btn-primary">
                <span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html($add_new_label); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div class="saw-alert saw-alert-<?php echo esc_attr($message_type); ?>">
        <?php echo esc_html($message); ?>
        <button type="button" class="saw-alert-close">&times;</button>
    </div>
<?php endif; ?>

<div class="saw-card">
    <div class="saw-card-header">
        <div class="saw-card-header-left">
            <h2 class="saw-card-title">
                <?php echo esc_html($plural); ?> (<span id="saw-<?php echo esc_attr($entity); ?>-count"><?php echo esc_html($total_items); ?></span>)
            </h2>
        </div>
        <div class="saw-card-header-right">
            <?php if ($config['search']): ?>
                <div class="saw-search-input-wrapper">
                    <input 
                        type="text" 
                        id="saw-<?php echo esc_attr($entity); ?>-search" 
                        value="<?php echo esc_attr($search_value); ?>" 
                        placeholder="Hledat <?php echo esc_attr($singular); ?>..."
                        class="saw-search-input"
                        data-entity="<?php echo esc_attr($entity); ?>"
                        data-ajax-action="<?php echo esc_attr($config['ajax_action']); ?>"
                        data-ajax-enabled="<?php echo $config['ajax_search'] ? '1' : '0'; ?>"
                        autocomplete="off"
                    >
                    <button 
                        type="button" 
                        id="saw-search-clear" 
                        class="saw-search-clear" 
                        style="display: <?php echo !empty($search_value) ? 'flex' : 'none'; ?>;"
                    >
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    <div class="saw-search-spinner" style="display: none;">
                        <span class="spinner is-active"></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="saw-card-body" id="saw-<?php echo esc_attr($entity); ?>-container">
        <div id="saw-<?php echo esc_attr($entity); ?>-loading" class="saw-loading-overlay" style="display: none;">
            <div class="saw-loading-spinner">
                <span class="spinner is-active"></span>
                <p>Načítám...</p>
            </div>
        </div>
        
        <?php 
        include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/body.php'; 
        ?>
        
        <?php 
        if ($config['total_pages'] > 1) {
            include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/pagination.php';
        }
        ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.saw-alert-close').on('click', function() {
        $(this).closest('.saw-alert').fadeOut(300);
    });
    
    <?php if (defined('SAW_DEBUG') && SAW_DEBUG): ?>
    console.log('SAW Admin Table: Initialized for entity:', '<?php echo esc_js($entity); ?>');
    console.log('SAW Admin Table: Total items:', <?php echo intval($total_items); ?>);
    console.log('SAW Admin Table: AJAX enabled:', <?php echo $config['ajax_search'] ? 'true' : 'false'; ?>);
    <?php endif; ?>
});
</script>