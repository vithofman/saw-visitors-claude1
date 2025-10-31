<?php
/**
 * Customers List Template
 * 
 * Zobrazuje tabulku zákazníků + modal pro detail
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">Zákazníci</h1>
        <a href="<?php echo home_url('/admin/settings/customers/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            Nový zákazník
        </a>
    </div>
</div>

<div class="saw-list-container">
    <?php if (!empty($search)): ?>
        <div class="saw-search-info">
            Vyhledávání: <strong><?php echo esc_html($search); ?></strong>
            <a href="<?php echo home_url('/admin/settings/customers/'); ?>">Zrušit</a>
        </div>
    <?php endif; ?>
    
    <div class="saw-table-controls">
        <form method="get" action="" class="saw-search-form">
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Hledat zákazníka...">
            <button type="submit" class="saw-button">Hledat</button>
        </form>
        
        <?php if (!empty($this->config['list_config']['filters']['status'])): ?>
            <div class="saw-filters">
                <select 
                    name="status" 
                    class="saw-filter-select" 
                    onchange="window.location.href='?status=' + this.value"
                    style="min-width: 200px; max-width: 200px; height: 44px; padding: 0 44px 0 18px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-weight: 500; color: #1f2937; background-color: #ffffff; background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2720%27 height=%2720%27 viewBox=%270 0 20 20%27%3E%3Cpath fill=%27%232563eb%27 d=%27M10 12.5l-5-5h10z%27/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 14px center; background-size: 18px; cursor: pointer; appearance: none; -webkit-appearance: none; -moz-appearance: none; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);"
                >
                    <option value="">Všechny statusy</option>
                    <option value="potential" <?php selected($_GET['status'] ?? '', 'potential'); ?>>Potenciální</option>
                    <option value="active" <?php selected($_GET['status'] ?? '', 'active'); ?>>Aktivní</option>
                    <option value="inactive" <?php selected($_GET['status'] ?? '', 'inactive'); ?>>Neaktivní</option>
                </select>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-info"></span>
            <p>Žádní zákazníci nenalezeni</p>
            <a href="<?php echo home_url('/admin/settings/customers/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit prvního zákazníka
            </a>
        </div>
    <?php else: ?>
        <div class="saw-admin-table saw-customers-table">
            <table>
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
                            <td class="saw-customer-name">
                                <strong><?php echo esc_html($item['name']); ?></strong>
                            </td>
                            <td><?php echo esc_html($item['ico'] ?? '-'); ?></td>
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
                            <td style="width: 80px; text-align: center;">
                                <?php if (!empty($item['primary_color'])): ?>
                                    <span class="saw-color-badge" style="background-color: <?php echo esc_attr($item['primary_color']); ?>;" title="<?php echo esc_attr($item['primary_color']); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo !empty($item['created_at']) ? date_i18n('d.m.Y', strtotime($item['created_at'])) : '-'; ?>
                            </td>
                            <td style="width: 180px; text-align: center;">
                                <a href="<?php echo home_url('/admin/settings/customers/edit/' . $item['id'] . '/'); ?>" class="saw-action-btn saw-action-edit" title="Upravit" onclick="event.stopPropagation();">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                <button type="button" class="saw-action-btn saw-action-delete saw-delete-btn" data-id="<?php echo esc_attr($item['id']); ?>" data-name="<?php echo esc_attr($item['name']); ?>" data-entity="customers" title="Smazat" onclick="event.stopPropagation();">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
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

<!-- ===== CUSTOMER DETAIL MODAL ===== -->
<div id="saw-customer-modal" class="saw-modal">
    <div class="saw-modal-overlay"></div>
    <div class="saw-modal-panel">
        <div class="saw-modal-header">
            <h2 class="saw-modal-title">Detail zákazníka</h2>
            <button type="button" class="saw-modal-close" data-modal-close>
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="saw-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<style>
/* Inline critical styles pro select - přepíše WP defaults */
.saw-filters select.saw-filter-select {
    min-width: 200px !important;
    max-width: 200px !important;
    height: 44px !important;
    padding: 0 44px 0 18px !important;
    margin: 0 !important;
    border: 2px solid #e5e7eb !important;
    border-radius: 10px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    line-height: normal !important;
    color: #1f2937 !important;
    background-color: #ffffff !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20'%3E%3Cpath fill='%232563eb' d='M10 12.5l-5-5h10z'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 18px !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
    box-sizing: border-box !important;
}

.saw-filters select.saw-filter-select:hover {
    border-color: #2563eb !important;
    background-color: #f9fafb !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15) !important;
}

.saw-filters select.saw-filter-select:focus {
    outline: none !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1) !important;
    background-color: #ffffff !important;
}
</style>