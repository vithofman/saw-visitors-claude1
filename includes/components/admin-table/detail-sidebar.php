<?php
/**
 * Detail Sidebar Template - ULTRA MODERN + COLLAPSIBLE
 *
 * Card-based layout with smooth collapse/expand
 *
 * @package     SAW_Visitors
 * @version     5.2.0 - Fixed related links to navigate properly
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_slug = str_replace('_', '-', $entity);
$detail_template = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/detail-modal-template.php";

$close_url = '#';
$edit_url = home_url('/admin/' . str_replace('admin/', '', $config['route'] ?? '') . '/' . intval($item['id']) . '/edit');
$delete_url = home_url('/admin/' . str_replace('admin/', '', $config['route'] ?? '') . '/delete/' . intval($item['id']));

$can_edit = function_exists('saw_can') ? saw_can('edit', $entity) : true;
$can_delete = function_exists('saw_can') ? saw_can('delete', $entity) : true;
?>

<div class="saw-sidebar saw-sidebar-detail" data-mode="detail" data-entity="<?php echo esc_attr($entity); ?>" data-current-id="<?php echo esc_attr($item['id']); ?>">
    <div class="saw-sidebar-header">
        <div class="saw-sidebar-title">
            <span class="saw-sidebar-icon"><?php echo esc_html($config['icon'] ?? '📋'); ?></span>
            <h2 class="saw-sidebar-heading"><?php echo esc_html($config['singular'] ?? 'Detail'); ?> #<?php echo intval($item['id']); ?></h2>
        </div>
        <div class="saw-sidebar-nav-controls">
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-prev" title="Předchozí">&lt;</button>
            <button type="button" class="saw-sidebar-nav-btn saw-sidebar-next" title="Další">&gt;</button>
        </div>
        <a href="<?php echo esc_url($close_url); ?>" class="saw-sidebar-close" title="Zavřít">&times;</a>
    </div>
    
    <div class="saw-sidebar-content">
        <?php 
        if (file_exists($detail_template)) {
            require $detail_template;
        } else {
            echo '<p>Detail template not found: ' . esc_html($detail_template) . '</p>';
        }
        ?>
        
        <?php if (!empty($related_data) && is_array($related_data)): ?>
        <div class="saw-related-sections">
            <h3 class="saw-related-sections-title">
                <?php echo esc_html__('Související záznamy', 'saw-visitors'); ?>
            </h3>
            
            <?php foreach ($related_data as $key => $relation): ?>
            <div class="saw-related-section" data-section="<?php echo esc_attr($key); ?>">
                <!-- Collapsible Header -->
                <div class="saw-related-section-header" data-toggle-section>
                    <div class="saw-related-section-toggle"></div>
                    
                    <div class="saw-related-section-icon-wrapper">
                        <span class="saw-related-section-icon"><?php echo esc_html($relation['icon']); ?></span>
                    </div>
                    
                    <div class="saw-related-section-info">
                        <h4 class="saw-related-section-label">
                            <?php echo esc_html($relation['label']); ?>
                        </h4>
                        <div class="saw-related-section-count">
                            <?php 
                            printf(
                                _n('%d záznam', '%d záznamy', $relation['count'], 'saw-visitors'),
                                $relation['count']
                            );
                            ?>
                        </div>
                    </div>
                    
                    <div class="saw-related-section-badge">
                        <?php echo intval($relation['count']); ?>
                    </div>
                </div>
                
                <!-- Collapsible Content -->
                <div class="saw-related-items">
                    <?php if (!empty($relation['items'])): ?>
                        <?php foreach ($relation['items'] as $related_item): ?>
                        <?php 
                        $item_route = str_replace('{id}', $related_item['id'], $relation['route']);
                        $full_url = home_url('/' . ltrim($item_route, '/'));
                        ?>
                        <a href="<?php echo esc_url($full_url); ?>" 
                           class="saw-related-item-link saw-spa-link"
                           data-spa-navigate="true"
                           data-entity="<?php echo esc_attr($relation['entity']); ?>"
                           data-id="<?php echo intval($related_item['id']); ?>"
                           data-route="<?php echo esc_attr($item_route); ?>"
                           title="<?php echo esc_attr__('Zobrazit detail', 'saw-visitors'); ?>">
                            <div class="saw-related-item-content">
                                <span class="saw-related-item-dot"></span>
                                <span class="saw-related-item-text">
                                    <?php echo esc_html($related_item['display']); ?>
                                </span>
                            </div>
                            <span class="saw-related-item-arrow">→</span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="saw-related-empty">
                            <?php echo esc_html__('Žádné záznamy', 'saw-visitors'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($can_edit || $can_delete): ?>
    <div class="saw-sidebar-floating-actions">
        <?php if ($can_edit): ?>
        <a href="<?php echo esc_url($edit_url); ?>" 
           class="saw-floating-action-btn edit saw-edit-ajax" 
           data-entity="<?php echo esc_attr($entity); ?>"
           data-id="<?php echo intval($item['id']); ?>"
           title="Upravit">
            <span class="dashicons dashicons-edit"></span>
        </a>
        <?php endif; ?>
        
        <?php if ($can_delete): ?>
        <button type="button" 
                class="saw-floating-action-btn delete saw-delete-btn" 
                data-id="<?php echo intval($item['id']); ?>"
                data-entity="<?php echo esc_attr($entity); ?>"
                data-name="<?php echo esc_attr($item['name'] ?? '#' . $item['id']); ?>"
                title="Smazat">
            <span class="dashicons dashicons-trash"></span>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function($) {
    'use strict';
    
    /**
     * Initialize collapsible related sections
     */
    function initCollapsibleSections() {
        $(document).on('click', '[data-toggle-section]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $header = $(this);
            const $section = $header.closest('.saw-related-section');
            
            $section.toggleClass('is-collapsed');
            
            console.log('🔽 Section toggled:', $section.data('section'));
        });
    }
    
    /**
     * Handle Edit button - USE AJAX NAVIGATION
     */
    function initEditButton() {
        $(document).off('click', '.saw-edit-ajax');
        
        $(document).on('click', '.saw-edit-ajax', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const entity = $btn.data('entity');
            const id = $btn.data('id');
            
            console.warn('🔴 EDIT BUTTON CLICKED!', {entity, id});
            console.warn('🔴 Event prevented and stopped');
            
            if (entity && id && typeof window.openSidebarAjax === 'function') {
                console.log('✅ Using AJAX navigation to edit mode');
                window.openSidebarAjax(id, 'edit', entity);
            } else {
                // Fallback to full page reload
                const href = $btn.attr('href');
                if (href) {
                    console.log('⚠️ Fallback to full page reload');
                    window.location.href = href;
                }
            }
            
            return false;
        });
        
        console.log('✅ Edit button handler initialized');
    }
    
    /**
     * Handle related item links - USE AJAX NAVIGATION
     */
    function initRelatedItemLinks() {
        $(document).off('click', '.saw-related-item-link');
        
        $(document).on('click', '.saw-related-item-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const $link = $(this);
            const entity = $link.data('entity');
            const id = $link.data('id');
            
            console.log('🔗 Related link clicked:', {entity, id});
            
            if (entity && id && typeof window.openSidebarAjax === 'function') {
                console.log('✅ Using AJAX navigation');
                window.openSidebarAjax(id, 'detail', entity);
            } else {
                // Fallback to full page reload
                const href = $link.attr('href');
                if (href && href !== '#') {
                    console.log('⚠️ Fallback to full page reload');
                    setTimeout(function() {
                        window.location.href = href;
                    }, 10);
                }
            }
            
            return false;
        });
        
        console.log('✅ Related item links handler initialized');
    }
    
    $(document).ready(function() {
        console.log('🎨 Detail sidebar scripts initialized');
        initCollapsibleSections();
        initEditButton();
        initRelatedItemLinks();
    });
    
    $(document).on('saw-sidebar-loaded', function() {
        console.log('🔄 Re-initializing after AJAX load');
        initEditButton();
        initRelatedItemLinks();
    });
    
})(jQuery);
</script>