<?php
/**
 * Account Types List Template
 * 
 * @package SAW_Visitors
 * @version 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

$current_is_active = $_GET['is_active'] ?? '';
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">Typy účtů</h1>
        <a href="<?php echo home_url('/admin/settings/account-types/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nový typ účtu</span>
        </a>
    </div>
</div>

<div class="saw-list-container">
    
    <div class="saw-table-controls">
        
        <div class="saw-search-form">
            <?php
            $search_component = new SAW_Component_Search('account-types', array(
                'placeholder' => 'Hledat typ účtu...',
                'search_value' => $search,
                'ajax_enabled' => false,
                'ajax_action' => 'saw_search_account_types',
                'show_button' => true,
                'show_info_banner' => true,
                'info_banner_label' => 'Vyhledávání:',
                'clear_url' => home_url('/admin/settings/account-types/'),
            ));
            $search_component->render();
            ?>
        </div>
        
        <div class="saw-filters">
            <select name="is_active" class="saw-select-responsive" onchange="window.location.href='?is_active=' + this.value + '<?php echo $search ? '&s=' . urlencode($search) : ''; ?>'">
                <option value="">Všechny statusy</option>
                <option value="1" <?php selected($current_is_active, '1'); ?>>Aktivní</option>
                <option value="0" <?php selected($current_is_active, '0'); ?>>Neaktivní</option>
            </select>
        </div>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-info"></span>
            <p>Žádné typy účtů nenalezeny</p>
            <a href="<?php echo home_url('/admin/settings/account-types/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit první typ účtu
            </a>
        </div>
    <?php else: ?>
        
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-account-types-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">Barva</th>
                        <th>Název</th>
                        <th>Interní název</th>
                        <th style="width: 120px; text-align: right;">Cena</th>
                        <th style="width: 100px; text-align: center;">Funkce</th>
                        <th style="width: 100px; text-align: center;">Pořadí</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 120px; text-align: center;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="saw-account-type-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            <td style="width: 80px; text-align: center;">
                                <span class="saw-color-badge" 
                                      style="background-color: <?php echo esc_attr($item['color'] ?? '#6b7280'); ?>;" 
                                      title="<?php echo esc_attr($item['color'] ?? '#6b7280'); ?>">
                                </span>
                            </td>
                            
                            <td class="saw-account-type-name">
                                <strong><?php echo esc_html($item['display_name']); ?></strong>
                            </td>
                            
                            <td>
                                <span class="saw-code-badge"><?php echo esc_html($item['name']); ?></span>
                            </td>
                            
                            <td style="text-align: right;">
                                <?php 
                                $price = floatval($item['price'] ?? 0);
                                if ($price > 0) {
                                    echo number_format($price, 2, ',', ' ') . ' Kč/měsíc';
                                } else {
                                    echo '<span class="saw-text-muted">Zdarma</span>';
                                }
                                ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php
                                $features = !empty($item['features']) ? json_decode($item['features'], true) : [];
                                $count = is_array($features) ? count($features) : 0;
                                echo '<span class="saw-badge saw-badge-info">' . $count . ' funkcí</span>';
                                ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <span class="saw-sort-order-badge"><?php echo esc_html($item['sort_order'] ?? 0); ?></span>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if ($item['is_active'] == 1): ?>
                                    <span class="saw-badge saw-badge-success">Aktivní</span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="width: 120px; text-align: center;">
                                <div class="saw-action-buttons">
                                    <a href="<?php echo home_url('/admin/settings/account-types/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <button type="button" 
                                            class="saw-action-btn saw-action-delete saw-delete-btn" 
                                            data-id="<?php echo esc_attr($item['id']); ?>" 
                                            data-name="<?php echo esc_attr($item['display_name']); ?>" 
                                            data-entity="account-types" 
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
                    <a href="?paged=<?php echo ($page - 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?><?php echo $current_is_active !== '' ? '&is_active=' . $current_is_active : ''; ?>" class="saw-pagination-link">
                        « Předchozí
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="saw-pagination-link current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?paged=<?php echo $i; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?><?php echo $current_is_active !== '' ? '&is_active=' . $current_is_active : ''; ?>" class="saw-pagination-link">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?paged=<?php echo ($page + 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?><?php echo $current_is_active !== '' ? '&is_active=' . $current_is_active : ''; ?>" class="saw-pagination-link">
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

$account_type_modal = new SAW_Component_Modal('account-type-detail', array(
    'title' => 'Detail typu účtu',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_account_types_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/settings/account-types/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tento typ účtu?',
            'ajax_action' => 'saw_delete_account_types',
        ),
    ),
));
$account_type_modal->render();
?>

<script>
jQuery(document).ready(function($) {
    console.log('[ACCOUNT-TYPES] Initializing');
    
    $('.saw-account-type-row').on('click', function(e) {
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const accountTypeId = $(this).data('id');
        
        if (!accountTypeId) {
            console.error('[ACCOUNT-TYPES] Account type ID not found');
            return;
        }
        
        console.log('[ACCOUNT-TYPES] Opening modal for ID:', accountTypeId);
        
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('account-type-detail', {
                id: accountTypeId,
                nonce: '<?php echo $ajax_nonce; ?>'
            });
        } else {
            console.error('[ACCOUNT-TYPES] SAWModal not defined');
        }
    });
});
</script>