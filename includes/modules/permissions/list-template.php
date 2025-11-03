<?php
/**
 * Permissions Module - Matrix View Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 4.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$ajax_nonce = wp_create_nonce('saw_ajax_nonce');
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            🔐 Správa oprávnění
        </h1>
    </div>
</div>

<div class="saw-permissions-container">
    
    <div class="saw-permissions-controls">
        <div class="saw-role-selector">
            <label for="role-select">Vyberte roli:</label>
            <select id="role-select" class="saw-select">
                <?php foreach ($roles as $role_key => $role_name): ?>
                    <option value="<?php echo esc_attr($role_key); ?>" <?php selected($selected_role, $role_key); ?>>
                        <?php echo esc_html($role_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="saw-quick-actions">
            <button type="button" class="saw-btn saw-btn-secondary" id="btn-allow-all">
                <span class="dashicons dashicons-unlock"></span>
                Vše povolit
            </button>
            <button type="button" class="saw-btn saw-btn-secondary" id="btn-deny-all">
                <span class="dashicons dashicons-lock"></span>
                Vše zakázat
            </button>
            <button type="button" class="saw-btn saw-btn-secondary" id="btn-reset">
                <span class="dashicons dashicons-image-rotate"></span>
                Reset na výchozí
            </button>
        </div>
    </div>
    
    <div class="saw-permissions-info">
        <p class="saw-info-text">
            <span class="dashicons dashicons-info"></span>
            Změny se ukládají automaticky po kliknutí na checkbox.
        </p>
    </div>
    
    <div class="saw-permissions-matrix">
        <table class="saw-permissions-table">
            <thead>
                <tr>
                    <th class="col-module">Modul</th>
                    <th class="col-action">👁️ Zobrazit</th>
                    <th class="col-action">📄 Detail</th>
                    <th class="col-action">➕ Vytvořit</th>
                    <th class="col-action">✏️ Upravit</th>
                    <th class="col-action">🗑️ Smazat</th>
                    <th class="col-scope">📊 Rozsah dat</th>
                </tr>
            </thead>
            <tbody id="permissions-tbody">
                <?php foreach ($modules as $module_slug => $module_config): ?>
                    <tr data-module="<?php echo esc_attr($module_slug); ?>">
                        <td class="module-name">
                            <span class="module-icon"><?php echo $module_config['icon'] ?? '📁'; ?></span>
                            <strong><?php echo esc_html($module_config['plural'] ?? $module_slug); ?></strong>
                        </td>
                        
                        <?php foreach ($actions as $action): ?>
                            <?php
                            $is_checked = isset($permissions[$module_slug][$action]['allowed']) && $permissions[$module_slug][$action]['allowed'];
                            $current_scope = $permissions[$module_slug][$action]['scope'] ?? 'all';
                            ?>
                            <td class="permission-cell">
                                <label class="permission-toggle">
                                    <input type="checkbox" 
                                           class="permission-checkbox"
                                           data-module="<?php echo esc_attr($module_slug); ?>"
                                           data-action="<?php echo esc_attr($action); ?>"
                                           <?php checked($is_checked); ?>>
                                    <span class="toggle-indicator <?php echo $is_checked ? 'active' : ''; ?>"></span>
                                </label>
                            </td>
                        <?php endforeach; ?>
                        
                        <td class="scope-cell">
                            <select class="scope-select" 
                                    data-module="<?php echo esc_attr($module_slug); ?>"
                                    data-action="list">
                                <option value="all" <?php selected($current_scope, 'all'); ?>>🌍 Všechno</option>
                                <option value="customer" <?php selected($current_scope, 'customer'); ?>>🏢 Zákazník</option>
                                <option value="branch" <?php selected($current_scope, 'branch'); ?>>🏪 Pobočka</option>
                                <option value="department" <?php selected($current_scope, 'department'); ?>>📁 Oddělení</option>
                                <option value="own" <?php selected($current_scope, 'own'); ?>>👤 Jen já</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
</div>

<div id="save-indicator" class="saw-save-indicator" style="display: none;">
    <span class="dashicons dashicons-saved"></span>
    <span class="save-text">Uloženo</span>
</div>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo $ajax_nonce; ?>';
    let saveTimeout = null;
    
    function getCurrentRole() {
        return $('#role-select').val();
    }
    
    function showSaveIndicator() {
        clearTimeout(saveTimeout);
        $('#save-indicator').fadeIn(200);
        
        saveTimeout = setTimeout(function() {
            $('#save-indicator').fadeOut(200);
        }, 2000);
    }
    
    function updatePermission(module, action, allowed, scope) {
        const role = getCurrentRole();
        
        $.post(ajaxurl, {
            action: 'saw_update_permission',
            nonce: nonce,
            role: role,
            module: module,
            permission_action: action,
            allowed: allowed ? 1 : 0,
            scope: scope || 'all'
        }, function(response) {
            if (response.success) {
                showSaveIndicator();
            } else {
                alert('Chyba při ukládání: ' + (response.data.message || 'Neznámá chyba'));
            }
        });
    }
    
    $('.permission-checkbox').on('change', function() {
        const $checkbox = $(this);
        const module = $checkbox.data('module');
        const action = $checkbox.data('action');
        const allowed = $checkbox.is(':checked');
        const $indicator = $checkbox.siblings('.toggle-indicator');
        
        if (allowed) {
            $indicator.addClass('active');
        } else {
            $indicator.removeClass('active');
        }
        
        const $scopeSelect = $checkbox.closest('tr').find('.scope-select');
        const scope = $scopeSelect.val();
        
        updatePermission(module, action, allowed, scope);
    });
    
    $('.scope-select').on('change', function() {
        const $select = $(this);
        const module = $select.data('module');
        const action = $select.data('action');
        const scope = $select.val();
        
        const $checkbox = $select.closest('tr').find('.permission-checkbox[data-action="' + action + '"]');
        const allowed = $checkbox.is(':checked');
        
        updatePermission(module, action, allowed, scope);
    });
    
    $('#role-select').on('change', function() {
        window.location.href = '<?php echo home_url('/admin/settings/permissions/'); ?>?role=' + $(this).val();
    });
    
    $('#btn-allow-all').on('click', function() {
        if (!confirm('Opravdu chcete povolit všechna oprávnění pro tuto roli?')) {
            return;
        }
        
        $('.permission-checkbox').each(function() {
            if (!$(this).is(':checked')) {
                $(this).prop('checked', true).trigger('change');
            }
        });
    });
    
    $('#btn-deny-all').on('click', function() {
        if (!confirm('Opravdu chcete zakázat všechna oprávnění pro tuto roli?')) {
            return;
        }
        
        $('.permission-checkbox').each(function() {
            if ($(this).is(':checked')) {
                $(this).prop('checked', false).trigger('change');
            }
        });
    });
    
    $('#btn-reset').on('click', function() {
        if (!confirm('Opravdu chcete resetovat oprávnění na výchozí hodnoty?')) {
            return;
        }
        
        const role = getCurrentRole();
        
        $.post(ajaxurl, {
            action: 'saw_reset_permissions',
            nonce: nonce,
            role: role
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Chyba při resetování: ' + (response.data.message || 'Neznámá chyba'));
            }
        });
    });
});
</script>
