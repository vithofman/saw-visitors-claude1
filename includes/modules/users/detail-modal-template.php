<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<p>Uživatel nebyl nalezen.</p>';
    return;
}

global $wpdb;

$branch_name = '—';
if (!empty($item['branch_id'])) {
    $branch = $wpdb->get_row($wpdb->prepare(
        "SELECT name, code FROM {$wpdb->prefix}saw_branches WHERE id = %d",
        $item['branch_id']
    ), ARRAY_A);
    if ($branch) {
        $branch_name = $branch['name'];
        if (!empty($branch['code'])) {
            $branch_name .= ' (' . $branch['code'] . ')';
        }
    }
}

$role_labels = [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'super_manager' => 'Super Manager',
    'manager' => 'Manager',
    'terminal' => 'Terminál'
];
$role_label = $role_labels[$item['role']] ?? $item['role'];

$wp_user = get_userdata($item['wp_user_id']);
$email = $wp_user ? $wp_user->user_email : 'N/A';

$departments = [];
if ($item['role'] === 'manager') {
    $departments = $wpdb->get_results($wpdb->prepare(
        "SELECT d.name 
         FROM {$wpdb->prefix}saw_departments d
         INNER JOIN {$wpdb->prefix}saw_user_departments ud ON d.id = ud.department_id
         WHERE ud.user_id = %d
         ORDER BY d.name ASC",
        $item['id']
    ), ARRAY_A);
}
?>

<div class="saw-detail-header">
    <div class="saw-detail-logo-placeholder">
        <span class="dashicons dashicons-admin-users"></span>
    </div>
    
    <div class="saw-detail-header-info">
        <h2><?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?></h2>
        <p style="margin: 4px 0 8px 0; color: #6b7280; font-size: 14px;">
            <?php echo esc_html($email); ?>
        </p>
        <span class="saw-role-badge saw-role-<?php echo esc_attr($item['role']); ?>">
            <?php echo esc_html($role_label); ?>
        </span>
        <?php if (!empty($item['is_active'])): ?>
            <span class="saw-badge saw-badge-success">Aktivní</span>
        <?php else: ?>
            <span class="saw-badge saw-badge-secondary">Neaktivní</span>
        <?php endif; ?>
    </div>
</div>

<div class="saw-detail-sections">
    
    <div class="saw-detail-section">
        <h3>🏢 Přiřazení</h3>
        <dl>
            <dt>Pobočka</dt>
            <dd><?php echo esc_html($branch_name); ?></dd>
            
            <?php if (!empty($departments)): ?>
                <dt>Oddělení</dt>
                <dd>
                    <?php foreach ($departments as $dept): ?>
                        <span class="saw-badge saw-badge-info"><?php echo esc_html($dept['name']); ?></span>
                    <?php endforeach; ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
    
    <?php if ($item['role'] === 'terminal'): ?>
    <div class="saw-detail-section">
        <h3>🔢 Terminál</h3>
        <dl>
            <dt>PIN</dt>
            <dd><?php echo !empty($item['pin']) ? '<span class="saw-badge saw-badge-success">Nastaven</span>' : '<span class="saw-badge saw-badge-secondary">Nenastaven</span>'; ?></dd>
        </dl>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section">
        <h3>ℹ️ Informace</h3>
        <dl>
            <dt>Status</dt>
            <dd>
                <?php if (!empty($item['is_active'])): ?>
                    <span class="saw-badge saw-badge-success">Aktivní</span>
                <?php else: ?>
                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                <?php endif; ?>
            </dd>
            
            <?php if (!empty($item['last_login'])): ?>
                <dt>Poslední přihlášení</dt>
                <dd><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['last_login']))); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at'])): ?>
                <dt>Vytvořeno</dt>
                <dd><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['created_at']))); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at'])): ?>
                <dt>Naposledy upraveno</dt>
                <dd><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['updated_at']))); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>

<style>
.saw-detail-logo-placeholder {
    width: 88px;
    height: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 14px;
    border: 2px solid #e2e8f0;
}

.saw-detail-logo-placeholder .dashicons {
    font-size: 44px;
    width: 44px;
    height: 44px;
    color: #ffffff;
}

.saw-role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    margin-right: 8px;
}

.saw-role-admin {
    background: #dbeafe;
    color: #1e40af;
}

.saw-role-super_manager {
    background: #e0e7ff;
    color: #4338ca;
}

.saw-role-manager {
    background: #f3e8ff;
    color: #7c3aed;
}

.saw-role-terminal {
    background: #fef3c7;
    color: #92400e;
}

.saw-role-super_admin {
    background: #fee2e2;
    color: #991b1b;
}
</style>
