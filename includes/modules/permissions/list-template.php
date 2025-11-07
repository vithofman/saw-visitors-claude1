<?php
/**
 * Permissions Module - Matrix View Template
 * 
 * Interactive matrix for managing role-based permissions across all modules.
 * Features real-time AJAX updates, role selector, and bulk actions.
 * 
 * Available Variables:
 * @var array $roles Available roles (role_key => role_name)
 * @var string $selected_role Currently selected role
 * @var array $modules All available modules from SAW_Module_Loader
 * @var array $actions Available action types (list, view, create, edit, delete)
 * @var array $permissions Current permissions for selected role
 * @var string $ajax_nonce AJAX security nonce
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Permissions
 * @since       4.10.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Page Header -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            🔐 Správa oprávnění
        </h1>
    </div>
</div>

<!-- Permissions Container -->
<div class="saw-permissions-container">
    
    <!-- ================================================ -->
    <!-- CONTROLS BAR (Role Selector + Quick Actions) -->
    <!-- ================================================ -->
    <div class="saw-permissions-controls">
        
        <!-- Role Selector -->
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
        
        <!-- Quick Action Buttons -->
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
    
    <!-- ================================================ -->
    <!-- INFO BANNER -->
    <!-- ================================================ -->
    <div class="saw-permissions-info">
        <p class="saw-info-text">
            <span class="dashicons dashicons-info"></span>
            Změny se ukládají automaticky po kliknutí na checkbox.
        </p>
    </div>
    
    <!-- ================================================ -->
    <!-- PERMISSIONS MATRIX TABLE -->
    <!-- ================================================ -->
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
                        
                        <!-- Module Name -->
                        <td class="module-name">
                            <span class="module-icon"><?php echo $module_config['icon'] ?? '📦'; ?></span>
                            <strong><?php echo esc_html($module_config['plural'] ?? $module_slug); ?></strong>
                        </td>
                        
                        <!-- Action Checkboxes (list, view, create, edit, delete) -->
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
                        
                        <!-- Scope Selector -->
                        <td class="scope-cell">
                            <?php
                            // Scope applies to entire module, read from 'list' action
                            $module_scope = $permissions[$module_slug]['list']['scope'] ?? 'all';
                            ?>
                            <select class="scope-select" 
                                    data-module="<?php echo esc_attr($module_slug); ?>">
                                <option value="all" <?php selected($module_scope, 'all'); ?>>🌐 Všechno</option>
                                <option value="customer" <?php selected($module_scope, 'customer'); ?>>🏢 Zákazník</option>
                                <option value="branch" <?php selected($module_scope, 'branch'); ?>>🏪 Pobočka</option>
                                <option value="department" <?php selected($module_scope, 'department'); ?>>🏭 Oddělení</option>
                                <option value="own" <?php selected($module_scope, 'own'); ?>>👤 Jen já</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
</div>

<!-- ================================================ -->
<!-- SAVE INDICATOR (Toast Notification) -->
<!-- ================================================ -->
<div id="save-indicator" class="saw-save-indicator" style="display: none;">
    <span class="dashicons dashicons-saved"></span>
    <span class="save-text">Uloženo</span>
</div>

<!-- ================================================ -->
<!-- PASS PHP DATA TO JAVASCRIPT -->
<!-- ================================================ -->
<script>
// Pass PHP data to external JS
var sawPermissionsData = {
    nonce: '<?php echo esc_js($ajax_nonce); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    homeUrl: '<?php echo esc_js(home_url('/admin/permissions/')); ?>'
};
</script>