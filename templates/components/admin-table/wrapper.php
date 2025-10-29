<?php
/**
 * Admin Table Wrapper Template
 * 
 * Kompletní wrapper pro admin tabulku s hlavičkou, vyhledáváním a paginací
 * 
 * @package SAW_Visitors
 * @version 4.6.1 FIXED
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

<?php if (!empty($message)): ?>
    <div class="saw-alert saw-alert-<?php echo esc_attr($message_type); ?>">
        <?php echo esc_html($message); ?>
        <button type="button" class="saw-alert-close">&times;</button>
    </div>
<?php endif; ?>

<div class="saw-card">
    <!-- ✨ NOVÉ UNIFIED HEADER - vše v jedné sekci -->
    <div class="saw-card-header-unified">
        <!-- Hlavní nadpis + tlačítko -->
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
        
        <!-- Počítadlo + search -->
        <div class="saw-unified-controls-row">
            <div class="saw-unified-count">
                <span class="saw-count-label"><?php echo esc_html($plural); ?></span>
                (<span id="saw-<?php echo esc_attr($entity); ?>-count" class="saw-count-number"><?php echo esc_html($total_items); ?></span>)
            </div>
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
                    >
                    <span class="dashicons dashicons-search saw-search-icon"></span>
                    <?php 
                    // ✅ OPRAVA: Změna class na id pro správnou funkci JS
                    ?>
                    <button type="button" id="saw-search-clear" class="saw-search-clear" style="display: <?php echo !empty($search_value) ? 'flex' : 'none'; ?>;">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="saw-card-body">
        <!-- Načtení table header a body -->
        <?php 
        include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/body.php';
        ?>
        
        <!-- Loading overlay -->
        <div class="saw-table-loading" style="display: none;">
            <div class="saw-spinner"></div>
            <span>Načítání...</span>
        </div>
    </div>
    
    <!-- Footer s paginací -->
    <?php 
    include SAW_VISITORS_PLUGIN_DIR . 'templates/components/admin-table/footer.php';
    ?>
</div>