<?php
/**
 * Users Form Template - REFACTORED v4.0.0
 * 
 * ✅ Uses <details> sections (like branches)
 * ✅ .saw-form-row + .saw-col-* grid
 * ✅ saw-back-button
 * ✅ Professional styling
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item['id']);
$page_title = $is_edit ? 'Upravit uživatele' : 'Nový uživatel';

global $wpdb;

$customer_id = SAW_Context::get_customer_id();

$branches = [];
if ($customer_id > 0) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code, city 
         FROM %i
         WHERE customer_id = %d AND is_active = 1 
         ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
}

$customers = [];
if (current_user_can('manage_options')) {
    $customers = $wpdb->get_results(
        $wpdb->prepare("SELECT id, name FROM %i ORDER BY name ASC", $wpdb->prefix . 'saw_customers'),
        ARRAY_A
    );
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo esc_html($page_title); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/users/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" action="" class="saw-user-form">
        <?php wp_nonce_field('saw_users_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-users"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="role" class="saw-label">
                            Role <span class="saw-required">*</span>
                        </label>
                        <select id="role" name="role" class="saw-select" required>
                            <option value="">Vyberte roli</option>
                            <?php if (current_user_can('manage_options')): ?>
                                <option value="super_admin" <?php selected($item['role'] ?? '', 'super_admin'); ?>>Super Admin (vše)</option>
                            <?php endif; ?>
                            <option value="admin" <?php selected($item['role'] ?? '', 'admin'); ?>>Admin (všechny pobočky zákazníka)</option>
                            <option value="super_manager" <?php selected($item['role'] ?? '', 'super_manager'); ?>>Super Manager (jedna pobočka)</option>
                            <option value="manager" <?php selected($item['role'] ?? '', 'manager'); ?>>Manager (vybraná oddělení)</option>
                            <option value="terminal" <?php selected($item['role'] ?? '', 'terminal'); ?>>Terminál (check-in/out)</option>
                        </select>
                        <span class="saw-help-text">Role určuje úroveň přístupu v systému</span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="first_name" class="saw-label">
                            Jméno <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="last_name" class="saw-label">
                            Příjmení <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="email" class="saw-label">
                            Email <span class="saw-required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['email'] ?? ''); ?>"
                            required
                            <?php echo $is_edit ? 'readonly' : ''; ?>
                        >
                        <span class="saw-help-text">Email slouží jako přihlašovací jméno</span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <?php if (current_user_can('manage_options')): ?>
        <details class="saw-form-section field-customer" style="display:none;">
            <summary>
                <span class="dashicons dashicons-building"></span>
                <strong>Zákazník</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="customer-select" class="saw-label">
                            Zákazník
                        </label>
                        <select name="customer_id" id="customer-select" class="saw-select">
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo esc_attr($customer['id']); ?>"
                                        <?php selected($customer['id'], $customer_id); ?>>
                                    <?php echo esc_html($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </details>
        <?php else: ?>
            <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        <?php endif; ?>
        
        <details class="saw-form-section field-branch" style="display:none;">
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong>Pobočka</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label">
                            Pobočka <span class="saw-required">*</span>
                        </label>
                        <select id="branch_id" name="branch_id" class="saw-select">
                            <option value="">-- Vyberte pobočku --</option>
                            <?php foreach ($branches as $branch): 
                                $label = $branch['name'];
                                if (!empty($branch['code'])) {
                                    $label .= ' (' . $branch['code'] . ')';
                                }
                                if (!empty($branch['city'])) {
                                    $label .= ' - ' . $branch['city'];
                                }
                            ?>
                                <option value="<?php echo esc_attr($branch['id']); ?>" 
                                        data-customer="<?php echo esc_attr($customer_id); ?>"
                                        <?php selected($item['branch_id'] ?? '', $branch['id']); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="saw-help-text">Uživatel uvidí data pouze z této pobočky</span>
                    </div>
                </div>
            </div>
        </details>
        
        <details class="saw-form-section field-departments" style="display:none;">
            <summary>
                <span class="dashicons dashicons-groups"></span>
                <strong>Oddělení</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            Oddělení <span class="saw-required">*</span>
                        </label>
                        <div id="departments-list">
                            <p class="saw-text-muted">Nejprve vyberte pobočku</p>
                        </div>
                        <span class="saw-help-text">Vyberte oddělení, která manager uvidí</span>
                    </div>
                </div>
            </div>
        </details>
        
        <details class="saw-form-section field-pin" style="display:none;">
            <summary>
                <span class="dashicons dashicons-lock"></span>
                <strong>PIN</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="pin" class="saw-label">
                            PIN (4 čísla)
                        </label>
                        <input 
                            type="text" 
                            id="pin" 
                            name="pin" 
                            class="saw-input"
                            maxlength="4" 
                            pattern="[0-9]{4}" 
                            placeholder="0000"
                        >
                        <span class="saw-help-text">4místný PIN pro přihlášení na terminál</span>
                    </div>
                </div>
            </div>
        </details>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Status</strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                name="is_active" 
                                value="1"
                                <?php checked(!empty($item['is_active']), true); ?>
                            >
                            <span class="saw-checkbox-text">Aktivní uživatel</span>
                        </label>
                        <span class="saw-help-text">
                            Pouze aktivní uživatelé se mohou přihlásit
                        </span>
                    </div>
                </div>
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit uživatele'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/users/')); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
        
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    const roleSelect = $('#role');
    const branchSelect = $('#branch_id');
    
    roleSelect.on('change', updateFields);
    branchSelect.on('change', loadDepartments);
    
    function updateFields() {
        const role = roleSelect.val();
        
        $('.field-customer').toggle(role === 'super_admin');
        $('.field-branch').toggle(role !== 'super_admin' && role !== 'admin' && role !== '');
        $('.field-departments').toggle(role === 'manager');
        $('.field-pin').toggle(role === 'terminal');
        
        if (role === 'manager' && branchSelect.val()) {
            loadDepartments();
        }
    }
    
    function loadDepartments() {
        const branchId = branchSelect.val();
        if (!branchId) return;
        
        $.post(ajaxurl, {
            action: 'saw_get_departments_by_branch',
            branch_id: branchId,
            nonce: '<?php echo wp_create_nonce("saw_departments"); ?>'
        }, function(response) {
            if (response.success) {
                let html = '';
                if (response.data.departments.length === 0) {
                    html = '<p class="saw-text-muted">Pobočka nemá žádná oddělení</p>';
                } else {
                    response.data.departments.forEach(dept => {
                        const checked = <?php echo json_encode($item['department_ids'] ?? []); ?>.includes(dept.id);
                        html += `<label class="saw-checkbox-label" style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" name="department_ids[]" value="${dept.id}" ${checked ? 'checked' : ''}>
                            <span class="saw-checkbox-text">${dept.name}</span>
                        </label>`;
                    });
                }
                $('#departments-list').html(html);
            }
        });
    }
    
    updateFields();
    
    if (roleSelect.val() === 'manager' && branchSelect.val()) {
        loadDepartments();
    }
});
</script>