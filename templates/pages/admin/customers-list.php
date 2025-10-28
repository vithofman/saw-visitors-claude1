<?php
/**
 * Template: Seznam zákazníků - FIXED
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
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
                Zákazníci (<?php echo esc_html($total_customers); ?>)
            </h2>
        </div>
        <div class="saw-card-header-right">
            <form method="get" action="" class="saw-search-form">
                <div class="saw-search-input-wrapper">
                    <input 
                        type="text" 
                        name="s" 
                        value="<?php echo esc_attr($search); ?>" 
                        placeholder="Hledat zákazníka..."
                        class="saw-search-input"
                    >
                    <button type="submit" class="saw-search-btn">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="saw-card-body">
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
                <table class="saw-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Logo</th>
                            <th>Název</th>
                            <th style="width: 120px;">IČO</th>
                            <th>Adresa</th>
                            <th style="width: 100px;">Barva</th>
                            <th style="width: 140px;" class="saw-text-center">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
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
                <div class="saw-pagination">
                    <?php
                    $base_url = remove_query_arg('paged');
                    
                    if ($page > 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $page - 1, $base_url)) . '" class="saw-pagination-link">&laquo; Předchozí</a>';
                    }
                    
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == $page) {
                            echo '<span class="saw-pagination-link saw-pagination-active">' . $i . '</span>';
                        } else {
                            echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="saw-pagination-link">' . $i . '</a>';
                        }
                    }
                    
                    if ($page < $total_pages) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $page + 1, $base_url)) . '" class="saw-pagination-link">Další &raquo;</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.saw-delete-customer').on('click', function(e) {
        e.preventDefault();
        
        var customerId = $(this).data('customer-id');
        var customerName = $(this).data('customer-name');
        var $row = $(this).closest('tr');
        
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
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        location.reload();
                    });
                } else {
                    alert('Chyba: ' + response.data.message);
                }
            },
            error: function() {
                alert('Došlo k chybě při mazání zákazníka.');
            }
        });
    });
    
    $(document).on('click', '.saw-alert-close', function() {
        $(this).closest('.saw-alert').fadeOut(300, function() {
            $(this).remove();
        });
    });
});
</script>