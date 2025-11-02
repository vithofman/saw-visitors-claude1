<?php
/**
 * Departments List Template
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

function build_filter_url($params = []) {
    $base_params = [];
    
    if (!empty($_GET['s'])) {
        $base_params['s'] = sanitize_text_field($_GET['s']);
    }
    
    if (!empty($_GET['is_active']) && !isset($params['is_active'])) {
        $base_params['is_active'] = sanitize_text_field($_GET['is_active']);
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
            🏢 Oddělení
        </h1>
        <a href="<?php echo home_url('/admin/departments/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nové oddělení</span>
        </a>
    </div>
</div>

<div class="saw-list-container">
    
    <div class="saw-table-controls">
        
        <div class="saw-search-form">
            <?php
            $search_component = new SAW_Component_Search('departments', array(
                'placeholder' => 'Hledat oddělení...',
                'search_value' => $search,
                'ajax_enabled' => false,
                'ajax_action' => 'saw_search_departments',
                'show_button' => true,
                'show_info_banner' => true,
                'info_banner_label' => 'Vyhledávání:',
                'clear_url' => home_url('/admin/departments/'),
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
        </div>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-building"></span>
            <p>Žádná oddělení nenalezena</p>
            <a href="<?php echo home_url('/admin/departments/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit první oddělení
            </a>
        </div>
    <?php else: ?>
        
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-departments-table">
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
                        <th style="width: 100px;">Číslo</th>
                        <th style="width: 150px;">Pobočka</th>
                        <th>Popis</th>
                        <th style="width: 120px; text-align: center;">
                            <a href="<?php echo build_filter_url(['orderby' => 'training_version', 'order' => ($orderby === 'training_version' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Verze školení
                                <?php if ($orderby === 'training_version'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 120px; text-align: center;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    global $wpdb;
                    foreach ($items as $item): 
                        $branch_name = '';
                        if (!empty($item['branch_id'])) {
                            $branch = $wpdb->get_row($wpdb->prepare(
                                "SELECT name, code FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                                $item['branch_id']
                            ), ARRAY_A);
                            if ($branch) {
                                $branch_name = $branch['name'];
                                if (!empty($branch['code'])) {
                                    $branch_name = $branch['code'];
                                }
                            }
                        }
                    ?>
                        <tr class="saw-department-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            <td class="saw-department-name">
                                <span class="saw-department-icon">🏢</span>
                                <strong><?php echo esc_html($item['name']); ?></strong>
                            </td>
                            
                            <td>
                                <?php if (!empty($item['department_number'])): ?>
                                    <span class="saw-code-badge"><?php echo esc_html($item['department_number']); ?></span>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($branch_name): ?>
                                    <?php echo esc_html($branch_name); ?>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="saw-department-description">
                                <?php if (!empty($item['description'])): ?>
                                    <?php echo esc_html(wp_trim_words($item['description'], 15, '...')); ?>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <span class="saw-version-badge">v<?php echo esc_html($item['training_version'] ?? 1); ?></span>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if (!empty($item['is_active'])): ?>
                                    <span class="saw-badge saw-badge-success">Aktivní</span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="width: 120px; text-align: center;">
                                <div class="saw-action-buttons">
                                    <a href="<?php echo home_url('/admin/departments/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <button type="button" 
                                            class="saw-action-btn saw-action-delete saw-delete-btn" 
                                            data-id="<?php echo esc_attr($item['id']); ?>" 
                                            data-name="<?php echo esc_attr($item['name']); ?>" 
                                            data-entity="departments" 
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

$department_modal = new SAW_Component_Modal('department-detail', array(
    'title' => 'Detail oddělení',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_departments_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/departments/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat toto oddělení?',
            'ajax_action' => 'saw_delete_departments',
        ),
    ),
));
$department_modal->render();
?>

<script>
jQuery(document).ready(function($) {
    $('.saw-department-row').on('click', function(e) {
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const departmentId = $(this).data('id');
        
        if (!departmentId) {
            return;
        }
        
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('department-detail', {
                id: departmentId,
                nonce: '<?php echo $ajax_nonce; ?>'
            });
        }
    });
});
</script>

<style>
.saw-code-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-family: 'SF Mono', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.5px;
}
</style>