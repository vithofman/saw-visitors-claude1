<?php
/**
 * Customers List Template
 * 
 * Template pro výpis zákazníků s podporou modal detailu.
 * 
 * ✅ OPRAVA v4.9.0: Používá univerzální saw_ajax_nonce místo saw_customer_modal_nonce
 * 
 * @package SAW_Visitors
 * @version 4.9.0
 * @since   4.6.1
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
        <h1 class="saw-page-title">Zákazníci</h1>
        <a href="<?php echo home_url('/admin/settings/customers/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nový zákazník</span>
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
            $search_component = new SAW_Component_Search('customers', array(
                'placeholder' => 'Hledat zákazníka...',
                'search_value' => $search,
                'ajax_enabled' => false,
                'ajax_action' => 'saw_search_customers',
                'show_button' => true,
                'show_info_banner' => true,
                'info_banner_label' => 'Vyhledávání:',
                'clear_url' => home_url('/admin/settings/customers/'),
            ));
            $search_component->render();
            ?>
        </div>
        
        <!-- FILTERS -->
        <?php if (!empty($this->config['list_config']['filters']['status'])): ?>
            <div class="saw-filters">
                <?php
                $status_filter = new SAW_Component_Selectbox('status-filter', array(
                    'options' => array(
                        '' => 'Všechny statusy',
                        'potential' => 'Potenciální',
                        'active' => 'Aktivní',
                        'inactive' => 'Neaktivní',
                    ),
                    'selected' => $_GET['status'] ?? '',
                    'on_change' => 'redirect',
                    'allow_empty' => true,
                    'custom_class' => 'saw-filter-select',
                    'name' => 'status',
                ));
                $status_filter->render();
                ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- EMPTY STATE nebo TABLE -->
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-info"></span>
            <p>Žádní zákazníci nenalezeni</p>
            <a href="<?php echo home_url('/admin/settings/customers/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit prvního zákazníka
            </a>
        </div>
    <?php else: ?>
        
        <!-- TABLE -->
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-customers-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">Logo</th>
                        <th>
                            <a href="?orderby=name&order=<?php echo ($orderby === 'name' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                                Název
                                <?php if ($orderby === 'name'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>IČO</th>
                        <th>Status</th>
                        <th>Předplatné</th>
                        <th style="width: 80px; text-align: center;">Barva</th>
                        <th>Vytvořeno</th>
                        <th style="width: 120px; text-align: center;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="saw-customer-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            <!-- LOGO -->
                            <td style="width: 60px; text-align: center; padding: 8px;">
                                <?php if (!empty($item['logo_url'])): ?>
                                    <div class="saw-customer-logo">
                                        <img src="<?php echo esc_url($item['logo_url']); ?>" alt="<?php echo esc_attr($item['name']); ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="saw-customer-logo-placeholder">
                                        <span class="dashicons dashicons-building"></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <!-- NAME -->
                            <td class="saw-customer-name">
                                <strong><?php echo esc_html($item['name']); ?></strong>
                            </td>
                            
                            <!-- IČO -->
                            <td><?php echo esc_html($item['ico'] ?? '-'); ?></td>
                            
                            <!-- STATUS -->
                            <td>
                                <?php
                                $status_badges = [
                                    'potential' => '<span class="saw-badge saw-badge-warning">Potenciální</span>',
                                    'active' => '<span class="saw-badge saw-badge-success">Aktivní</span>',
                                    'inactive' => '<span class="saw-badge saw-badge-secondary">Neaktivní</span>',
                                ];
                                echo $status_badges[$item['status']] ?? esc_html($item['status']);
                                ?>
                            </td>
                            
                            <!-- SUBSCRIPTION -->
                            <td>
                                <?php
                                $sub_labels = [
                                    'free' => 'Zdarma',
                                    'basic' => 'Basic',
                                    'pro' => 'Pro',
                                    'enterprise' => 'Enterprise',
                                ];
                                echo $sub_labels[$item['subscription_type'] ?? 'free'] ?? 'Zdarma';
                                ?>
                            </td>
                            
                            <!-- PRIMARY COLOR -->
                            <td style="width: 80px; text-align: center;">
                                <?php if (!empty($item['primary_color'])): ?>
                                    <span class="saw-color-badge" style="background-color: <?php echo esc_attr($item['primary_color']); ?>;" title="<?php echo esc_attr($item['primary_color']); ?>"></span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- CREATED AT -->
                            <td>
                                <?php echo !empty($item['created_at']) ? date_i18n('d.m.Y', strtotime($item['created_at'])) : '-'; ?>
                            </td>
                            
                            <!-- ACTIONS -->
                            <td style="width: 120px; text-align: center;">
                                <div class="saw-action-buttons">
                                    <a href="<?php echo home_url('/admin/settings/customers/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <button type="button" 
                                            class="saw-action-btn saw-action-delete saw-delete-btn" 
                                            data-id="<?php echo esc_attr($item['id']); ?>" 
                                            data-name="<?php echo esc_attr($item['name']); ?>" 
                                            data-entity="customers" 
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
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="saw-pagination">
                <!-- Previous -->
                <?php if ($page > 1): ?>
                    <a href="?paged=<?php echo ($page - 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                        « Předchozí
                    </a>
                <?php endif; ?>
                
                <!-- Pages -->
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
// ✅ OPRAVA: Použij univerzální SAW nonce místo custom customer nonce
$ajax_nonce = wp_create_nonce('saw_ajax_nonce');

// Render Customer Detail Modal with HEADER ACTIONS
$customer_modal = new SAW_Component_Modal('customer-detail', array(
    'title' => 'Detail zákazníka',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_customers_detail',
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
            'url' => home_url('/admin/settings/customers/edit/{id}/'), // {id} bude nahrazeno
        ),
        // Delete button - smaže přes AJAX
        array(
            'type' => 'delete',
            'label' => '', // Prázdný label = jen ikona
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tohoto zákazníka?',
            'ajax_action' => 'saw_delete_customers',
        ),
    ),
));
$customer_modal->render();
?>

<!-- ========================================
     JAVASCRIPT
     ======================================== -->
<script>
jQuery(document).ready(function($) {
    // Customer row click handler - otevře modal detail
    $('.saw-customer-row').on('click', function(e) {
        // Don't open modal if clicking on action buttons
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const customerId = $(this).data('id');
        
        if (!customerId) {
            console.error('Customer ID not found');
            return;
        }
        
        console.log('Opening customer detail modal for ID:', customerId);
        
        // ✅ OPRAVA: Použij univerzální SAW nonce
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('customer-detail', {
                id: customerId,
                nonce: '<?php echo $ajax_nonce; ?>'  // ← Univerzální nonce
            });
        } else {
            console.error('SAWModal is not defined');
        }
    });
});
</script>