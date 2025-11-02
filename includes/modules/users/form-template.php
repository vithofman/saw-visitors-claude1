<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item['id']);
$page_title = $is_edit ? 'Upravit uživatele' : 'Nový uživatel';

global $wpdb;
$customer_id = 0;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['saw_current_customer_id'])) {
    $customer_id = intval($_SESSION['saw_current_customer_id']);
}

$branches = [];
if ($customer_id > 0) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code, city 
         FROM {$wpdb->prefix}saw_branches 
         WHERE customer_id = %d AND is_active = 1 
         ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
}

$customers = [];
if (current_user_can('manage_options')) {
    $customers = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}saw_customers ORDER BY name ASC",
        ARRAY_A
    );
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            👤 <?php echo esc_html($page_title); ?>
        </h1>
        <a href="<?php echo home_url('/admin/users/'); ?>" class="saw-button saw-button-secondary">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <span>Zpět na seznam</span>
        </a>
    </div>
</div>

<div class="saw-form-container saw-form-modern">
    <form method="post" action="" class="saw-user-form">
        <?php wp_nonce_field('saw_users_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <div class="saw-form-card">
            <div class="saw-form-card-header">
                <h2>Základní informace</h2>
                <p>Nastavte role a osobní údaje uživatele</p>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-field">
                    <label for="role" class="saw-label">
                        Role <span class="saw-required-mark">*</span>
                    </label>
                    <select id="role" 
                            name="role" 
                            class="saw-select"
                            required>
                        <option value="">Vyberte roli</option>
                        <?php if (current_user_can('manage_options')): ?>
                            <option value="super_admin" <?php selected($item['role'] ?? '', 'super_admin'); ?>>Super Admin (vše)</option>
                        <?php endif; ?>
                        <option value="admin" <?php selected($item['role'] ?? '', 'admin'); ?>>Admin (všechny pobočky zákazníka)</option>
                        <option value="super_manager" <?php selected($item['role'] ?? '', 'super_manager'); ?>>Super Manager (jedna pobočka)</option>
                        <option value="manager" <?php selected($item['role'] ?? '', 'manager'); ?>>Manager (vybraná oddělení)</option>
                        <option value="terminal" <?php selected($item['role'] ?? '', 'terminal'); ?>>Terminál (check-in/out)</option>
                    </select>
                    <span class="saw-field-hint">Role určuje úroveň přístupu v systému</span>
                </div>
                
                <div class="saw-form-grid">
                    <div class="saw-form-field">
                        <label for="first_name" class="saw-label">
                            Jméno <span class="saw-required-mark">*</span>
                        </label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               value="<?php echo esc_attr($item['first_name'] ?? ''); ?>" 
                               class="saw-input"
                               required>
                    </div>
                    
                    <div class="saw-form-field">
                        <label for="last_name" class="saw-label">
                            Příjmení <span class="saw-required-mark">*</span>
                        </label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               value="<?php echo esc_attr($item['last_name'] ?? ''); ?>" 
                               class="saw-input"
                               required>
                    </div>
                </div>
                
                <div class="saw-form-field">
                    <label for="email" class="saw-label">
                        Email <span class="saw-required-mark">*</span>
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo esc_attr($item['email'] ?? ''); ?>" 
                           class="saw-input"
                           required
                           <?php echo $is_edit ? 'readonly' : ''; ?>>
                    <span class="saw-field-hint">Email slouží jako přihlašovací jméno</span>
                </div>
            </div>
        </div>
        
        <?php if (current_user_can('manage_options')): ?>
        <div class="saw-form-field field-customer" style="display:none;">
            <div class="saw-form-card">
                <div class="saw-form-card-header">
                    <h2>Zákazník</h2>
                </div>
                <div class="saw-form-card-body">
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
        <?php else: ?>
            <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        <?php endif; ?>
        
        <div class="saw-form-card field-branch" style="display:none;">
            <div class="saw-form-card-header">
                <h2>Pobočka</h2>
                <p>Přiřazení k pobočce</p>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-field">
                    <label for="branch_id" class="saw-label">
                        Pobočka <span class="saw-required-mark">*</span>
                    </label>
                    <select id="branch_id" 
                            name="branch_id" 
                            class="saw-select">
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
                    <span class="saw-field-hint">Uživatel uvidí data pouze z této pobočky</span>
                </div>
            </div>
        </div>
        
        <div class="saw-form-card field-departments" style="display:none;">
            <div class="saw-form-card-header">
                <h2>Oddělení</h2>
                <p>Přiřazení manažera k oddělením</p>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-field">
                    <label class="saw-label">
                        Oddělení <span class="saw-required-mark">*</span>
                    </label>
                    <div id="departments-list">
                        <p class="saw-text-muted">Nejprve vyberte pobočku</p>
                    </div>
                    <span class="saw-field-hint">Vyberte oddělení, která manager uvidí</span>
                </div>
            </div>
        </div>
        
        <div class="saw-form-card field-pin" style="display:none;">
            <div class="saw-form-card-header">
                <h2>PIN</h2>
                <p>PIN pro přihlášení na terminál</p>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-field">
                    <label for="pin" class="saw-label">PIN (4 čísla)</label>
                    <input type="text" 
                           id="pin" 
                           name="pin" 
                           maxlength="4" 
                           pattern="[0-9]{4}" 
                           class="saw-input"
                           placeholder="0000">
                    <span class="saw-field-hint">4místný PIN pro přihlášení na terminál</span>
                </div>
            </div>
        </div>
        
        <div class="saw-form-card">
            <div class="saw-form-card-header">
                <h2>Status</h2>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-field">
                    <div class="saw-checkbox-card">
                        <label class="saw-checkbox-label">
                            <input type="checkbox" 
                                   id="is_active" 
                                   name="is_active" 
                                   value="1" 
                                   class="saw-checkbox"
                                   <?php checked(!empty($item['is_active']), true); ?>>
                            <div class="saw-checkbox-content">
                                <span class="saw-checkbox-title">Aktivní uživatel</span>
                                <span class="saw-checkbox-desc">Pouze aktivní uživatelé se mohou přihlásit</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-btn saw-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                Uložit uživatele
            </button>
            <a href="<?php echo home_url('/admin/users/'); ?>" class="saw-btn saw-btn-secondary">
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
                        html += `<label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" name="department_ids[]" value="${dept.id}" ${checked ? 'checked' : ''}>
                            ${dept.name}
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
