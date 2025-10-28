<?php
/**
 * Template: Seznam zákazníků - WITH AJAX SEARCH & SORTING & DEBUG
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper function for sortable column headers
function get_sort_url($column, $current_orderby, $current_order) {
    $new_order = 'ASC';
    if ($current_orderby === $column && $current_order === 'ASC') {
        $new_order = 'DESC';
    }
    
    $base_url = remove_query_arg(array('orderby', 'order'));
    return add_query_arg(array('orderby' => $column, 'order' => $new_order), $base_url);
}

function get_sort_icon($column, $current_orderby, $current_order) {
    if ($current_orderby !== $column) {
        return '<span class="saw-sort-icon">⇅</span>';
    }
    
    return $current_order === 'ASC' 
        ? '<span class="saw-sort-icon saw-sort-asc">▲</span>' 
        : '<span class="saw-sort-icon saw-sort-desc">▼</span>';
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">Správa zákazníků</h1>
        <p class="saw-page-subtitle">Zde můžete spravovat všechny zákazníky v systému</p>
    </div>
    <div class="saw-page-header-actions">
        <a href="<?php echo esc_url(home_url('/admin/settings/customers/new/')); ?>" class="saw-btn saw-btn-primary">
            <span class="dashicons dashicons-plus-alt"></span> Přidat zákazníka
        </a>
    </div>
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
                Zákazníci (<span id="saw-customers-count"><?php echo esc_html($total_customers); ?></span>)
            </h2>
        </div>
        <div class="saw-card-header-right">
            <div class="saw-search-input-wrapper">
                <input 
                    type="text" 
                    id="saw-customers-search" 
                    value="<?php echo esc_attr($search); ?>" 
                    placeholder="Hledat zákazníka..."
                    class="saw-search-input"
                    autocomplete="off"
                >
                <button type="button" id="saw-search-clear" class="saw-search-clear" style="display: <?php echo !empty($search) ? 'flex' : 'none'; ?>;">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <div class="saw-search-spinner" style="display: none;">
                    <span class="spinner is-active"></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="saw-card-body">
        <div id="saw-customers-loading" class="saw-loading-overlay" style="display: none;">
            <div class="saw-loading-spinner">
                <span class="spinner is-active"></span>
                <p>Načítám zákazníky...</p>
            </div>
        </div>
        
        <div id="saw-customers-container">
            <?php if (empty($customers)): ?>
                <div class="saw-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <h3>Žádní zákazníci</h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            Nebyli nalezeni žádní zákazníci odpovídající hledanému výrazu.
                        <?php else: ?>
                            Zatím nemáte žádné zákazníky. Klikněte na tlačítko výše pro přidání prvního zákazníka.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="saw-table-responsive">
                    <table class="saw-table saw-table-sortable">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Logo</th>
                                <th class="saw-sortable" data-column="name" data-label="Název">
                                    <a href="<?php echo esc_url(get_sort_url('name', $orderby, $order)); ?>">
                                        Název <?php echo get_sort_icon('name', $orderby, $order); ?>
                                    </a>
                                </th>
                                <th class="saw-sortable" data-column="ico" style="width: 120px;" data-label="IČO">
                                    <a href="<?php echo esc_url(get_sort_url('ico', $orderby, $order)); ?>">
                                        IČO <?php echo get_sort_icon('ico', $orderby, $order); ?>
                                    </a>
                                </th>
                                <th>Adresa</th>
                                <th style="width: 100px;">Barva</th>
                                <th style="width: 140px;" class="saw-text-center">Akce</th>
                            </tr>
                        </thead>
                        <tbody id="saw-customers-tbody">
                            <?php foreach ($customers as $customer): ?>
                                <tr data-customer-id="<?php echo esc_attr($customer['id']); ?>">
                                    <td>
                                        <?php if (!empty($customer['logo_url_full'])): ?>
                                            <img 
                                                src="<?php echo esc_url($customer['logo_url_full']); ?>" 
                                                alt="<?php echo esc_attr($customer['name']); ?>"
                                                class="saw-customer-logo"
                                            >
                                        <?php else: ?>
                                            <div class="saw-customer-logo-placeholder">
                                                <span class="dashicons dashicons-building"></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($customer['name']); ?></strong>
                                        <?php if (!empty($customer['notes'])): ?>
                                            <br>
                                            <small class="saw-text-muted">
                                                <?php echo esc_html(wp_trim_words($customer['notes'], 10)); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($customer['ico'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($customer['address'])): ?>
                                            <small><?php echo nl2br(esc_html($customer['address'])); ?></small>
                                        <?php else: ?>
                                            <span class="saw-text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="saw-color-preview" style="background-color: <?php echo esc_attr($customer['primary_color'] ?? '#1e40af'); ?>">
                                            <span><?php echo esc_html($customer['primary_color'] ?? '#1e40af'); ?></span>
                                        </div>
                                    </td>
                                    <td class="saw-text-center">
                                        <div class="saw-actions">
                                            <a 
                                                href="<?php echo esc_url(home_url('/admin/settings/customers/edit/' . $customer['id'] . '/')); ?>" 
                                                class="saw-btn saw-btn-sm saw-btn-secondary"
                                                title="Upravit"
                                            >
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                            <button 
                                                type="button"
                                                class="saw-btn saw-btn-sm saw-btn-danger saw-delete-customer"
                                                data-customer-id="<?php echo esc_attr($customer['id']); ?>"
                                                data-customer-name="<?php echo esc_attr($customer['name']); ?>"
                                                title="Smazat"
                                            >
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
                    <div class="saw-pagination" id="saw-customers-pagination">
                        <?php
                        $base_url = remove_query_arg('paged');
                        
                        if ($page > 1) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $page - 1, $base_url)) . '" class="saw-pagination-link saw-pagination-prev" data-page="' . ($page - 1) . '">&laquo; Předchozí</a>';
                        }
                        
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == $page) {
                                echo '<span class="saw-pagination-link saw-pagination-active" data-page="' . $i . '">' . $i . '</span>';
                            } else {
                                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="saw-pagination-link" data-page="' . $i . '">' . $i . '</a>';
                            }
                        }
                        
                        if ($page < $total_pages) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $page + 1, $base_url)) . '" class="saw-pagination-link saw-pagination-next" data-page="' . ($page + 1) . '">Další &raquo;</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 🔍 DEBUG: Log template variables
    console.log('SAW Template: Loaded with', <?php echo count($customers); ?>, 'customers');
    console.log('SAW Template: Search term:', '<?php echo esc_js($search); ?>');
    console.log('SAW Template: Order by:', '<?php echo esc_js($orderby); ?>', '<?php echo esc_js($order); ?>');
    console.log('SAW Template: Page:', <?php echo intval($page); ?>, 'of', <?php echo intval($total_pages); ?>);
    
    // Delete customer (unchanged from original)
    $('.saw-delete-customer').on('click', function(e) {
        e.preventDefault();
        
        var customerId = $(this).data('customer-id');
        var customerName = $(this).data('customer-name');
        var $row = $(this).closest('tr');
        
        console.log('SAW Template: Delete clicked for customer ID:', customerId);
        
        if (!confirm('Opravdu chcete smazat zákazníka "' + customerName + '"?\n\nTato akce je nevratná!')) {
            return;
        }
        
        $(this).prop('disabled', true).addClass('saw-loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'saw_delete_customer',
                customer_id: customerId,
                nonce: '<?php echo wp_create_nonce('saw_delete_customer'); ?>'
            },
            success: function(response) {
                console.log('SAW Template: Delete response:', response);
                
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        location.reload();
                    });
                } else {
                    alert('Chyba: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('SAW Template: Delete error:', error);
                alert('Došlo k chybě při mazání zákazníka.');
            }
        });
    });
    
    // Close alert
    $(document).on('click', '.saw-alert-close', function() {
        $(this).closest('.saw-alert').fadeOut(300, function() {
            $(this).remove();
        });
    });
});
</script>