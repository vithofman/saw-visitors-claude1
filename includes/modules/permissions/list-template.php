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
 * @var array $translations Translation strings from controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Permissions
 * @since       4.10.0
 * @version     2.0.0 - ADDED: Translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP (fallback if not passed)
// ============================================
if (!isset($translations) || empty($translations)) {
    $lang = 'cs';
    if (class_exists('SAW_Component_Language_Switcher')) {
        $lang = SAW_Component_Language_Switcher::get_user_language();
    }
    
    $t = function_exists('saw_get_translations') 
        ? saw_get_translations($lang, 'admin', 'permissions') 
        : array();
    
    $tr = function($key, $fallback = null) use ($t) {
        return $t[$key] ?? $fallback ?? $key;
    };
    
    $translations = array(
        'page_title' => $tr('page_title', 'Správa oprávnění'),
        'label_select_role' => $tr('label_select_role', 'Vyberte roli:'),
        'btn_allow_all' => $tr('btn_allow_all', 'Vše povolit'),
        'btn_deny_all' => $tr('btn_deny_all', 'Vše zakázat'),
        'btn_reset' => $tr('btn_reset', 'Reset na výchozí'),
        'info_auto_save' => $tr('info_auto_save', 'Změny se ukládají automaticky po kliknutí na checkbox.'),
        'col_module' => $tr('col_module', 'Modul'),
        'col_list' => $tr('col_list', 'Zobrazit'),
        'col_view' => $tr('col_view', 'Detail'),
        'col_create' => $tr('col_create', 'Vytvořit'),
        'col_edit' => $tr('col_edit', 'Upravit'),
        'col_delete' => $tr('col_delete', 'Smazat'),
        'col_scope' => $tr('col_scope', 'Rozsah dat'),
        'scope_all' => $tr('scope_all', 'Všechno'),
        'scope_customer' => $tr('scope_customer', 'Zákazník'),
        'scope_branch' => $tr('scope_branch', 'Pobočka'),
        'scope_department' => $tr('scope_department', 'Oddělení'),
        'scope_own' => $tr('scope_own', 'Jen já'),
        'msg_saved' => $tr('msg_saved', 'Uloženo'),
    );
}

// Helper function for translations
$_t = function($key) use ($translations) {
    return $translations[$key] ?? $key;
};
?>

<!-- Page Header -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            🔐 <?php echo esc_html($_t('page_title')); ?>
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
            <label for="role-select"><?php echo esc_html($_t('label_select_role')); ?></label>
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
                <?php echo esc_html($_t('btn_allow_all')); ?>
            </button>
            <button type="button" class="saw-btn saw-btn-secondary" id="btn-deny-all">
                <span class="dashicons dashicons-lock"></span>
                <?php echo esc_html($_t('btn_deny_all')); ?>
            </button>
            <button type="button" class="saw-btn saw-btn-secondary" id="btn-reset">
                <span class="dashicons dashicons-image-rotate"></span>
                <?php echo esc_html($_t('btn_reset')); ?>
            </button>
        </div>
    </div>
    
    <!-- ================================================ -->
    <!-- INFO BANNER -->
    <!-- ================================================ -->
    <div class="saw-permissions-info">
        <p class="saw-info-text">
            <span class="dashicons dashicons-info"></span>
            <?php echo esc_html($_t('info_auto_save')); ?>
        </p>
    </div>
    
    <!-- ================================================ -->
    <!-- PERMISSIONS MATRIX TABLE -->
    <!-- ================================================ -->
    <div class="saw-permissions-matrix">
        <table class="saw-permissions-table">
            <thead>
                <tr>
                    <th class="col-module"><?php echo esc_html($_t('col_module')); ?></th>
                    <th class="col-action">👁️ <?php echo esc_html($_t('col_list')); ?></th>
                    <th class="col-action">📄 <?php echo esc_html($_t('col_view')); ?></th>
                    <th class="col-action">➕ <?php echo esc_html($_t('col_create')); ?></th>
                    <th class="col-action">✏️ <?php echo esc_html($_t('col_edit')); ?></th>
                    <th class="col-action">🗑️ <?php echo esc_html($_t('col_delete')); ?></th>
                    <th class="col-scope">📊 <?php echo esc_html($_t('col_scope')); ?></th>
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
                                <option value="all" <?php selected($module_scope, 'all'); ?>>🌐 <?php echo esc_html($_t('scope_all')); ?></option>
                                <option value="customer" <?php selected($module_scope, 'customer'); ?>>🏢 <?php echo esc_html($_t('scope_customer')); ?></option>
                                <option value="branch" <?php selected($module_scope, 'branch'); ?>>🏪 <?php echo esc_html($_t('scope_branch')); ?></option>
                                <option value="department" <?php selected($module_scope, 'department'); ?>>🏭 <?php echo esc_html($_t('scope_department')); ?></option>
                                <option value="own" <?php selected($module_scope, 'own'); ?>>👤 <?php echo esc_html($_t('scope_own')); ?></option>
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
    <span class="save-text"><?php echo esc_html($_t('msg_saved')); ?></span>
</div>

<!-- ================================================ -->
<!-- PASS PHP DATA TO JAVASCRIPT -->
<!-- ================================================ -->
<script>
// Pass PHP data to external JS
var sawPermissionsData = {
    nonce: '<?php echo esc_js($ajax_nonce); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    homeUrl: '<?php echo esc_js(home_url('/admin/permissions/')); ?>',
    translations: <?php echo json_encode($translations); ?>
};
</script>