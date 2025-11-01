<?php
/**
 * Branches List Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

$current_is_active = $_GET['is_active'] ?? '';
$current_is_headquarters = $_GET['is_headquarters'] ?? '';

function build_filter_url($params = []) {
    $base_params = [];
    
    if (!empty($_GET['s'])) {
        $base_params['s'] = sanitize_text_field($_GET['s']);
    }
    
    if (!empty($_GET['is_active']) && !isset($params['is_active'])) {
        $base_params['is_active'] = sanitize_text_field($_GET['is_active']);
    }
    
    if (!empty($_GET['is_headquarters']) && !isset($params['is_headquarters'])) {
        $base_params['is_headquarters'] = sanitize_text_field($_GET['is_headquarters']);
    }
    
    if (!empty($_GET['orderby'])) {
        $base_params['orderby'] = sanitize_text_field($_GET['orderby']);
    }
    
    if (!empty($_GET['order'])) {
        $base_params['order'] = sanitize_text_field($_GET['order']);
    }
    
    $all_params = array_merge($base_params, $params);
    
    return '?' . http_build_query($all_params);
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            🏢 Pobočky
        </h1>
        <a href="<?php echo home_url('/admin/branches/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nová pobočka</span>
        </a>
    </div>
</div>

<div class="saw-list-container">
    
    <div class="saw-table-controls">
        
        <div class="saw-search-form">
            <?php
            $search_component = new SAW_Component_Search('branches', array(
                'placeholder' => 'Hledat pobočku...',
                'search_value' => $search,
                'ajax_enabled' => false,
                'ajax_action' => 'saw_search_branches',
                'show_button' => true,
                'show_info_banner' => true,
                'info_banner_label' => 'Vyhledávání:',
                'clear_url' => home_url('/admin/branches/'),
            ));
            $search_component->render();
            ?>
        </div>
        
        <div class="saw-filters">
            <select name="is_active" class="saw-select-responsive" onchange="window.location.href='<?php echo build_filter_url(['is_active' => '']); ?>'.replace('is_active=', 'is_active=' + this.value)">
                <option value="">Všechny statusy</option>
                <option value="1" <?php selected($current_is_active, '1'); ?>>Aktivní</option>
                <option value="0" <?php selected($current_is_active, '0'); ?>>Neaktivní</option>
            </select>
            
            <select name="is_headquarters" class="saw-select-responsive" onchange="window.location.href='<?php echo build_filter_url(['is_headquarters' => '']); ?>'.replace('is_headquarters=', 'is_headquarters=' + this.value)">
                <option value="">Všechny pobočky</option>
                <option value="1" <?php selected($current_is_headquarters, '1'); ?>>Jen hlavní sídla</option>
                <option value="0" <?php selected($current_is_headquarters, '0'); ?>>Bez hlavních sídel</option>
            </select>
        </div>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-building"></span>
            <p>Žádné pobočky nenalezeny</p>
            <a href="<?php echo home_url('/admin/branches/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit první pobočku
            </a>
        </div>
    <?php else: ?>
        
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-branches-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo build_filter_url(['orderby' => 'name', 'order' => ($orderby === 'name' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Název
                                <?php if ($orderby === 'name'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 100px;">
                            <a href="<?php echo build_filter_url(['orderby' => 'code', 'order' => ($orderby === 'code' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Kód
                                <?php if ($orderby === 'code'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 150px;">
                            <a href="<?php echo build_filter_url(['orderby' => 'city', 'order' => ($orderby === 'city' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Město
                                <?php if ($orderby === 'city'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 120px;">Telefon</th>
                        <th style="width: 100px; text-align: center;">Hlavní</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 80px; text-align: center;">
                            <a href="<?php echo build_filter_url(['orderby' => 'sort_order', 'order' => ($orderby === 'sort_order' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Pořadí
                                <?php if ($orderby === 'sort_order'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 120px; text-align: center;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="saw-branch-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            <td class="saw-branch-name">
                                <?php if (!empty($item['image_thumbnail'])): ?>
                                    <img src="<?php echo esc_url($item['image_thumbnail']); ?>" 
                                         alt="<?php echo esc_attr($item['name']); ?>" 
                                         class="saw-branch-thumbnail">
                                <?php else: ?>
                                    <span class="saw-branch-icon">🏢</span>
                                <?php endif; ?>
                                <strong><?php echo esc_html($item['name']); ?></strong>
                                <?php if (!empty($item['is_headquarters'])): ?>
                                    <span class="saw-badge saw-badge-info saw-badge-sm">HQ</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($item['code'])): ?>
                                    <span class="saw-code-badge"><?php echo esc_html($item['code']); ?></span>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($item['city'])): ?>
                                    <?php echo esc_html($item['city']); ?>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($item['phone'])): ?>
                                    <a href="tel:<?php echo esc_attr($item['phone']); ?>" 
                                       class="saw-phone-link" 
                                       onclick="event.stopPropagation();">
                                        <?php echo esc_html($item['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if (!empty($item['is_headquarters'])): ?>
                                    <span class="saw-badge saw-badge-info">Ano</span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">Ne</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if (!empty($item['is_active'])): ?>
                                    <span class="saw-badge saw-badge-success">Aktivní</span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <span class="saw-sort-order-badge"><?php echo esc_html($item['sort_order'] ?? 0); ?></span>
                            </td>
                            
                            <td style="width: 120px; text-align: center;">
                                <div class="saw-action-buttons">
                                    <a href="<?php echo home_url('/admin/branches/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <button type="button" 
                                            class="saw-action-btn saw-action-delete saw-delete-btn" 
                                            data-id="<?php echo esc_attr($item['id']); ?>" 
                                            data-name="<?php echo esc_attr($item['name']); ?>" 
                                            data-entity="branches" 
                                            title="Smazat" 
                                            onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="saw-pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo build_filter_url(['paged' => $page - 1]); ?>" class="saw-pagination-link">
                        « Předchozí
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="saw-pagination-link current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo build_filter_url(['paged' => $i]); ?>" class="saw-pagination-link">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo build_filter_url(['paged' => $page + 1]); ?>" class="saw-pagination-link">
                        Další »
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
    
</div>

<?php
if (!class_exists('SAW_Component_Modal')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
}

$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

$branch_modal = new SAW_Component_Modal('branch-detail', array(
    'title' => 'Detail pobočky',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_branches_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/branches/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tuto pobočku?',
            'ajax_action' => 'saw_delete_branches',
        ),
    ),
));
$branch_modal->render();
?>

<script>
jQuery(document).ready(function($) {
    console.log('[BRANCHES] Initializing');
    
    $('.saw-branch-row').on('click', function(e) {
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const branchId = $(this).data('id');
        
        if (!branchId) {
            console.error('[BRANCHES] Branch ID not found');
            return;
        }
        
        console.log('[BRANCHES] Opening modal for ID:', branchId);
        
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('branch-detail', {
                id: branchId,
                nonce: '<?php echo $ajax_nonce; ?>'
            });
        } else {
            console.error('[BRANCHES] SAWModal not defined');
        }
    });
});
</script>