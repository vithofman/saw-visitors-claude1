<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

function build_filter_url($params = []) {
    $base_params = [];
    
    if (!empty($_GET['s'])) {
        $base_params['s'] = sanitize_text_field($_GET['s']);
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
            🌐 Jazyky školení
        </h1>
        <a href="<?php echo home_url('/admin/training-languages/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nový jazyk</span>
        </a>
    </div>
</div>

<div class="saw-list-container">
    
    <div class="saw-table-controls">
        
        <div class="saw-search-form">
            <?php
            $search_component = new SAW_Component_Search('training_languages', array(
                'placeholder' => 'Hledat jazyk...',
                'search_value' => $search,
                'ajax_enabled' => false,
                'ajax_action' => 'saw_search_training_languages',
                'show_button' => true,
                'show_info_banner' => true,
                'info_banner_label' => 'Vyhledávání:',
                'clear_url' => home_url('/admin/training-languages/'),
            ));
            $search_component->render();
            ?>
        </div>
        
    </div>
    
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-translation"></span>
            <p>Žádné jazyky nenalezeny</p>
            <a href="<?php echo home_url('/admin/training-languages/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit první jazyk
            </a>
        </div>
    <?php else: ?>
        
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-languages-table">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">Vlajka</th>
                        <th>
                            <a href="<?php echo build_filter_url(['orderby' => 'language_name', 'order' => ($orderby === 'language_name' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Název
                                <?php if ($orderby === 'language_name'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 100px;">
                            <a href="<?php echo build_filter_url(['orderby' => 'language_code', 'order' => ($orderby === 'language_code' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Kód
                                <?php if ($orderby === 'language_code'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 150px; text-align: center;">Aktivní pobočky</th>
                        <th style="width: 150px;">
                            <a href="<?php echo build_filter_url(['orderby' => 'created_at', 'order' => ($orderby === 'created_at' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Vytvořeno
                                <?php if ($orderby === 'created_at'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th style="width: 120px; text-align: center;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php 
                        $branches_count = isset($item['branches_count']) ? intval($item['branches_count']) : 0;
                        $is_protected = ($item['language_code'] === 'cs');
                        ?>
                        <tr class="saw-language-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            <td style="text-align: center;">
                                <span class="saw-language-flag"><?php echo esc_html($item['flag_emoji']); ?></span>
                            </td>
                            
                            <td class="saw-language-name">
                                <strong><?php echo esc_html($item['language_name']); ?></strong>
                                <?php if ($is_protected): ?>
                                    <span class="saw-badge saw-badge-info saw-badge-sm">Povinný</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="saw-code-badge"><?php echo esc_html($item['language_code']); ?></span>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if ($branches_count > 0): ?>
                                    <span class="saw-badge saw-badge-success"><?php echo $branches_count; ?> <?php echo $branches_count === 1 ? 'pobočka' : 'poboček'; ?></span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">0 poboček</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($item['created_at'])): ?>
                                    <span class="saw-text-muted"><?php echo date('d.m.Y', strtotime($item['created_at'])); ?></span>
                                <?php else: ?>
                                    <span class="saw-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <div class="saw-action-buttons">
                                    <a href="<?php echo home_url('/admin/training-languages/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <?php if (!$is_protected): ?>
                                        <button type="button" 
                                                class="saw-action-btn saw-action-delete saw-delete-btn" 
                                                data-id="<?php echo esc_attr($item['id']); ?>" 
                                                data-name="<?php echo esc_attr($item['language_name']); ?>" 
                                                data-entity="training_languages" 
                                                title="Smazat" 
                                                onclick="event.stopPropagation();">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="saw-action-btn saw-action-delete" 
                                                title="Čeština nemůže být smazána" 
                                                disabled
                                                onclick="event.stopPropagation();">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    <?php endif; ?>
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

$language_modal = new SAW_Component_Modal('training-language-detail', array(
    'title' => 'Detail jazyka',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_training_languages_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/training-languages/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tento jazyk?',
            'ajax_action' => 'saw_delete_training_languages',
        ),
    ),
));
$language_modal->render();
?>

<script>
jQuery(document).ready(function($) {
    $('.saw-language-row').on('click', function(e) {
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const languageId = $(this).data('id');
        
        if (!languageId) {
            return;
        }
        
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('training-language-detail', {
                id: languageId,
                nonce: '<?php echo $ajax_nonce; ?>'
            });
        }
    });
});
</script>
