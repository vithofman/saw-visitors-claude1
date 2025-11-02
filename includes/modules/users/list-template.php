<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Search')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/search/class-saw-component-search.php';
}

$current_is_active = $_GET['is_active'] ?? '';
$current_role = $_GET['role'] ?? '';

function build_filter_url($params = []) {
    $base_params = [];
    
    if (!empty($_GET['s'])) {
        $base_params['s'] = sanitize_text_field($_GET['s']);
    }
    
    if (!empty($_GET['is_active']) && !isset($params['is_active'])) {
        $base_params['is_active'] = sanitize_text_field($_GET['is_active']);
    }
    
    if (!empty($_GET['role']) && !isset($params['role'])) {
        $base_params['role'] = sanitize_text_field($_GET['role']);
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
            👤 Uživatelé
        </h1>
        <a href="<?php echo home_url('/admin/users/new/'); ?>" class="saw-button saw-button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <span>Nový uživatel</span>
        </a>
    </div>
</div>

<div class="saw-list-container">
    
    <div class="saw-table-controls">
        
        <div class="saw-search-form">
            <?php
            $search_component = new SAW_Component_Search('users', array(
                'placeholder' => 'Hledat uživatele...',
                'search_value' => $search,
                'ajax_enabled' => false,
                'ajax_action' => 'saw_search_users',
                'show_button' => true,
                'show_info_banner' => true,
                'info_banner_label' => 'Vyhledávání:',
                'clear_url' => home_url('/admin/users/'),
            ));
            $search_component->render();
            ?>
        </div>
        
        <div class="saw-filters">
            <select name="role" class="saw-select-responsive" onchange="window.location.href='<?php echo build_filter_url(['role' => '']); ?>'.replace('role=', 'role=' + this.value)">
                <option value="">Všechny role</option>
                <option value="admin" <?php selected($current_role, 'admin'); ?>>Admin</option>
                <option value="super_manager" <?php selected($current_role, 'super_manager'); ?>>Super Manager</option>
                <option value="manager" <?php selected($current_role, 'manager'); ?>>Manager</option>
                <option value="terminal" <?php selected($current_role, 'terminal'); ?>>Terminál</option>
            </select>
            
            <select name="is_active" class="saw-select-responsive" onchange="window.location.href='<?php echo build_filter_url(['is_active' => '']); ?>'.replace('is_active=', 'is_active=' + this.value)">
                <option value="">Všechny statusy</option>
                <option value="1" <?php selected($current_is_active, '1'); ?>>Aktivní</option>
                <option value="0" <?php selected($current_is_active, '0'); ?>>Neaktivní</option>
            </select>
        </div>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-admin-users"></span>
            <p>Žádní uživatelé nenalezeni</p>
            <a href="<?php echo home_url('/admin/users/new/'); ?>" class="saw-button saw-button-primary">
                Vytvořit prvního uživatele
            </a>
        </div>
    <?php else: ?>
        
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table saw-users-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo build_filter_url(['orderby' => 'first_name', 'order' => ($orderby === 'first_name' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                Jméno
                                <?php if ($orderby === 'first_name'): ?>
                                    <span class="dashicons dashicons-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Email</th>
                        <th style="width: 150px;">Role</th>
                        <th style="width: 150px;">Pobočka</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 150px;">Poslední přihlášení</th>
                        <th style="width: 120px; text-align: center;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    global $wpdb;
                    foreach ($items as $item): 
                        $branch_name = '—';
                        if (!empty($item['branch_id'])) {
                            $branch = $wpdb->get_row($wpdb->prepare(
                                "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                                $item['branch_id']
                            ), ARRAY_A);
                            if ($branch) {
                                $branch_name = $branch['name'];
                            }
                        }
                        
                        $role_labels = [
                            'admin' => 'Admin',
                            'super_manager' => 'Super Manager',
                            'manager' => 'Manager',
                            'terminal' => 'Terminál'
                        ];
                        $role_label = $role_labels[$item['role']] ?? $item['role'];
                        
                        $wp_user = get_userdata($item['wp_user_id']);
                        $email = $wp_user ? $wp_user->user_email : 'N/A';
                    ?>
                        <tr class="saw-user-row" data-id="<?php echo esc_attr($item['id']); ?>" style="cursor: pointer;">
                            <td class="saw-user-name">
                                <span class="saw-user-icon">👤</span>
                                <strong><?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?></strong>
                            </td>
                            
                            <td><?php echo esc_html($email); ?></td>
                            
                            <td>
                                <span class="saw-role-badge saw-role-<?php echo esc_attr($item['role']); ?>">
                                    <?php echo esc_html($role_label); ?>
                                </span>
                            </td>
                            
                            <td><?php echo esc_html($branch_name); ?></td>
                            
                            <td style="text-align: center;">
                                <?php if (!empty($item['is_active'])): ?>
                                    <span class="saw-badge saw-badge-success">Aktivní</span>
                                <?php else: ?>
                                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($item['last_login'])): ?>
                                    <?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['last_login']))); ?>
                                <?php else: ?>
                                    <span class="saw-text-muted">Nikdy</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <div class="saw-action-buttons">
                                    <a href="<?php echo home_url('/admin/users/edit/' . $item['id'] . '/'); ?>" 
                                       class="saw-action-btn saw-action-edit" 
                                       title="Upravit" 
                                       onclick="event.stopPropagation();">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <button type="button" 
                                            class="saw-action-btn saw-action-delete saw-delete-btn" 
                                            data-id="<?php echo esc_attr($item['id']); ?>" 
                                            data-name="<?php echo esc_attr($item['first_name'] . ' ' . $item['last_name']); ?>" 
                                            data-entity="users" 
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

$user_modal = new SAW_Component_Modal('user-detail', array(
    'title' => 'Detail uživatele',
    'ajax_enabled' => true,
    'ajax_action' => 'saw_get_users_detail',
    'size' => 'large',
    'show_close' => true,
    'close_on_backdrop' => true,
    'close_on_escape' => true,
    'header_actions' => array(
        array(
            'type' => 'edit',
            'label' => '',
            'icon' => 'dashicons-edit',
            'url' => home_url('/admin/users/edit/{id}/'),
        ),
        array(
            'type' => 'delete',
            'label' => '',
            'icon' => 'dashicons-trash',
            'confirm' => true,
            'confirm_message' => 'Opravdu chcete smazat tohoto uživatele?',
            'ajax_action' => 'saw_delete_users',
        ),
    ),
));
$user_modal->render();
?>

<script>
jQuery(document).ready(function($) {
    $('.saw-user-row').on('click', function(e) {
        if ($(e.target).closest('button, a, .saw-action-buttons').length > 0) {
            return;
        }
        
        const userId = $(this).data('id');
        
        if (!userId) {
            return;
        }
        
        if (typeof SAWModal !== 'undefined') {
            SAWModal.open('user-detail', {
                id: userId,
                nonce: '<?php echo $ajax_nonce; ?>'
            });
        }
    });
});
</script>
