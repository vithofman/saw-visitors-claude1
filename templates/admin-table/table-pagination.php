<?php
/**
 * Admin Table - Pagination Template
 * 
 * Pagination - kopíruje design z customers-list.php
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = $config['current_page'] ?? 1;
$total_pages = $config['total_pages'] ?? 1;
$total_items = $config['total_items'] ?? 0;

// Nezobrazovat pokud je jen 1 stránka
if ($total_pages <= 1) {
    return;
}

$base_url = remove_query_arg('paged');
if (!empty($config['search_value'])) {
    $base_url = add_query_arg('s', urlencode($config['search_value']), $base_url);
}
?>

<div class="saw-pagination" id="saw-<?php echo esc_attr($entity); ?>-pagination">
    <?php if ($current_page > 1): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>" 
           class="saw-pagination-link saw-pagination-prev" 
           data-page="<?php echo ($current_page - 1); ?>">
            &laquo; Předchozí
        </a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i == $current_page): ?>
            <span class="saw-pagination-link saw-pagination-active" data-page="<?php echo $i; ?>">
                <?php echo $i; ?>
            </span>
        <?php else: ?>
            <a href="<?php echo esc_url(add_query_arg('paged', $i, $base_url)); ?>" 
               class="saw-pagination-link" 
               data-page="<?php echo $i; ?>">
                <?php echo $i; ?>
            </a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($current_page < $total_pages): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>" 
           class="saw-pagination-link saw-pagination-next" 
           data-page="<?php echo ($current_page + 1); ?>">
            Další &raquo;
        </a>
    <?php endif; ?>
</div>