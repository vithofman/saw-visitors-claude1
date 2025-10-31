<?php
/**
 * Account Types List Template
 * 
 * Tabulkový výpis typů účtů s:
 * - Vyhledáváním (v name a display_name)
 * - Filtrem aktivní/neaktivní
 * - Řazením (klik na header)
 * - Paginací
 * - Modal detailem (klik na řádek)
 * 
 * @package SAW_Visitors
 * @version 1.0.1
 * @since   4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load required components
if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

if (!class_exists('SAW_Component_Selectbox')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
}

if (!class_exists('SAW_Component_Modal')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
}
?>

<!-- ========================================
     PAGE HEADER
     ======================================== -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">Typy účtů</h1>
        <a href="<?php echo home_url('/admin/settings/account-types/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nový typ účtu</span>
        </a>
    </div>
</div>

<!-- ========================================
     LIST CONTAINER
     ======================================== -->
<div class="saw-list-container">
    
    <!-- TABLE CONTROLS (Search + Filters) -->
    <div class="saw-table-controls">
        
        <!-- SEARCH -->
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
        
        <!-- FILTERS -->
        <?php if (!empty($this->config['list_config']['filters']['is_active'])): ?>
            <div class="saw-filters">
                <?php
                $active_filter = new SAW_Component_Selectbox('is_active-filter', array(
                    'options' => array(
                        '' => 'Všechny stavy',
                        '1' => 'Aktivní',
                        '0' => 'Neaktivní',
                    ),
                    'selected' => $_GET['is_active'] ?? '',
                    'on_change' => 'redirect',
                    'allow_empty' => true,
                    'custom_class' => 'saw-filter-select',
                    'name' => 'is_active',
                ));
                $active_filter->render();
                ?>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- ========================================
         EMPTY STATE (žádné položky)
         ======================================== -->
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-info"></span>
            <p>Žádné typy účtů nenalezeny</p>
            <a href="<?php echo home_url('/admin/settings/account-types/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit první typ účtu
            </a>
        </div>
        
    <!-- ========================================
         TABLE (jsou položky)
         ======================================== -->
    <?php else: ?>
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-account-types-table">
                <thead>
                    <tr>
                        <!-- COLOR BADGE -->
                        <th style="width: 60px; text-align: center;">
                            Barva
                        </th>
                        
                        <!-- DISPLAY NAME (sortable) -->
                        <th>
                            <a href="?orderby=display_name&order=<?php echo ($orderby === 'display_name' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                                Název
                                <?php if ($orderby === 'display_name'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <!-- INTERNAL NAME -->
                        <th>
                            <a href="?orderby=name&order=<?php echo ($orderby === 'name' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                                Interní název
                                <?php if ($orderby === 'name'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <!-- PRICE (sortable) -->
                        <th style="text-align: right;">
                            <a href="?orderby=price&order=<?php echo ($orderby === 'price' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                                Cena
                                <?php if ($orderby === 'price'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <!-- FEATURES COUNT -->
                        <th style="text-align: center;">
                            Funkce
                        </th>
                        
                        <!-- SORT ORDER (sortable) -->
                        <th style="text-align: center;">
                            <a href="?orderby=sort_order&order=<?php echo ($orderby === 'sort_order' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                                Pořadí
                                <?php if ($orderby === 'sort_order'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <!-- STATUS -->
                        <th style="text-align: center;">
                            Status
                        </th>
                        
                        <!-- ACTIONS -->
                        <th style="width: 120px; text-align: center;">
                            Akce
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        // Připrav features count
                        $features_array = !empty($item['features']) ? json_decode($item['features'], true) : [];
                        $features_count = is_array($features_array) ? count($features_array) : 0;
                    ?>
                        <tr class="saw-account-type-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            
                            <!-- COLOR BADGE -->
                            <td style="width: 60px; text-align: center; padding: 8px;">
                                <span 
                                    class="saw-color-badge" 
                                    style="background-color: <?php echo esc_attr($item['color'] ?? '#6b7280'); ?>;" 
                                    title="<?php echo esc_attr($item['color'] ?? '#6b7280'); ?>"
                                ></span>
                            </td>
                            
                            <!-- DISPLAY NAME -->
                            <td class="saw-account-type-name">
                                <strong><?php echo esc_html($item['display_name']); ?></strong>
                            </td>
                            
                            <!-- INTERNAL NAME (slug) -->
                            <td>
                                <code class="saw-code-badge"><?php echo esc_html($item['name']); ?></code>
                            </td>
                            
                            <!-- PRICE -->
                            <td style="text-align: right;">
                                <?php if ($item['price'] > 0): ?>
                                    <strong><?php echo number_format($item['price'], 0, ',', ' '); ?> Kč</strong>/měsíc
                                <?php else: ?>
                                    <span class="saw-text-muted">Zdarma</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- FEATURES COUNT -->
                            <td style="text-align: center;">
                                <?php if ($features_count > 0): ?>
                                    <span class="saw-badge saw-badge-info">
                                        <?php echo $features_count; ?> <?php echo $features_count === 1 ? 'funkce' : 'funkcí'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- SORT ORDER -->
                            <td style="text-align: center;">
                                <span class="saw-sort-order-badge">
                                    <?php echo intval($item['sort_order'] ?? 0); ?>
                                </span>
                            </td>
                            
                            <!-- STATUS (Active/Inactive) -->
                            <td style="text-align: center;">
                                <?php if (!empty($item['is_active'])): ?>
                                    <span class="saw-badge saw-badge-success">Aktivní</span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- ACTIONS (Edit + Delete) -->
                            <td style="width: 120px; text-align: center;">
                                <div class="saw-action-buttons">
                                    <!-- Edit button -->
                                    <a href="<?php echo home_url('/admin/settings/account-types/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    
                                    <!-- Delete button -->
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
        
        <!-- ========================================
             PAGINATION
             ======================================== -->
        <?php if ($total_pages > 1): ?>
            <div class="saw-pagination">
                <!-- Previous -->
                <?php if ($page > 1): ?>
                    <a href="?paged=<?php echo ($page - 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                        « Předchozí
                    </a>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="saw-pagination-link current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?paged=<?php echo $i; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <!-- Next -->
                <?php if ($page < $total_pages): ?>
                    <a href="?paged=<?php echo ($page + 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                        Další »
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
    
</div>

<!-- ========================================
     MODAL DETAIL
     ======================================== -->
<?php
// ✅ OPRAVA: Použij standardní SAW ajax nonce místo custom nonce
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Render Account Type Detail Modal with HEADER ACTIONS
$account_type_modal = new SAW_Component_Modal('account-type-detail', array(
    'title' => 'Detail typu účtu',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_account_types_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    
    // Header akce (Edit + Delete) - jen ikony
    'header_actions' => array(
        // Edit button - přesměruje na edit stránku
        array(
            'type' => 'edit',
            'label' => '', // Prázdný label = jen ikona
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/settings/account-types/edit/{id}/'), // {id} bude nahrazeno
        ),
        // Delete button - smaže přes AJAX
        array(
            'type' => 'delete',
            'label' => '', // Prázdný label = jen ikona
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tento typ účtu?',
            'ajax_action' => 'saw_delete_account_types',
        ),
    ),
));
$account_type_modal->render();
?>

<!-- ========================================
     JAVASCRIPT
     ======================================== -->
<script>
jQuery(document).ready(function($) {
    // Account type row click handler - otevře modal detail
    $('.saw-account-type-row').on('click', function(e) {
        // Don't open modal if clicking on action buttons
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const accountTypeId = $(this).data('id');
        
        if (!accountTypeId) {
            console.error('Account Type ID not found');
            return;
        }
        
        console.log('Opening account type detail modal for ID:', accountTypeId);
        
        // ✅ OPRAVA: Použij standardní SAW nonce
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('account-type-detail', {
                id: accountTypeId,
                nonce: '<?php echo $ajax_nonce; ?>'  // ← Standardní SAW nonce
            });
        } else {
            console.error('SAWModal is not defined');
        }
    });
});
</script>