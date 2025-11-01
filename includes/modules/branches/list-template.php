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
            <select name="status" class="saw-select-responsive" onchange="window.location.href='?status=' + this.value">
                <option value="">Všechny statusy</option>
                <option value="active" <?php selected($_GET['status'] ?? '', 'active'); ?>>Aktivní</option>
                <option value="inactive" <?php selected($_GET['status'] ?? '', 'inactive'); ?>>Neaktivní</option>
            </select>
            
            <select name="headquarters" class="saw-select-responsive" onchange="window.location.href='?headquarters=' + this.value">
                <option value="">Všechny pobočky</option>
                <option value="yes" <?php selected($_GET['headquarters'] ?? '', 'yes'); ?>>Jen hlavní sídla</option>
                <option value="no" <?php selected($_GET['headquarters'] ?? '', 'no'); ?>>Bez hlavních sídel</option>
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
                        <th>Název</th>
                        <th style="width: 100px;">Kód</th>
                        <th style="width: 150px;">Město</th>
                        <th style="width: 120px;">Telefon</th>
                        <th style="width: 100px; text-align: center;">Hlavní</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 80px; text-align: center;">Pořadí</th>
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
                    <a href="?paged=<?php echo ($page - 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                        « Předchozí
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="saw-pagination-link current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?paged=<?php echo $i; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?paged=<?php echo ($page + 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
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
