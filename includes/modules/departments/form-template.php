<?php
/**
 * Departments Form Template
 * 
 * @package SAW_Visitors
 * @version 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item['id']);
$page_title = $is_edit ? 'Upravit oddělení' : 'Nové oddělení';

global $wpdb;

// ✅ OPRAVENO: SAW_Context místo session
$customer_id = SAW_Context::get_customer_id();

$branches = [];
if ($customer_id) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code, city, is_headquarters 
         FROM %i 
         WHERE customer_id = %d AND is_active = 1 
         ORDER BY is_headquarters DESC, name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
}
?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            🏢 <?php echo esc_html($page_title); ?>
        </h1>
        <a href="<?php echo home_url('/admin/departments/'); ?>" class="saw-button saw-button-secondary">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <span>Zpět na seznam</span>
        </a>
    </div>
</div>

<div class="saw-form-container saw-form-modern">
    <form method="post" action="" class="saw-department-form">
        <?php wp_nonce_field('saw_departments_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <div class="saw-form-card">
            <div class="saw-form-card-header">
                <h2>Základní informace</h2>
                <p>Nastavte základní údaje o oddělení</p>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-grid">
                    <div class="saw-form-field">
                        <label for="branch_id" class="saw-label">
                            Pobočka <span class="saw-required-mark">*</span>
                        </label>
                        <select id="branch_id" 
                                name="branch_id" 
                                class="saw-select"
                                required>
                            <option value="">Vyberte pobočku</option>
                            <?php foreach ($branches as $branch): 
                                $label = $branch['name'];
                                if (!empty($branch['code'])) {
                                    $label .= ' (' . $branch['code'] . ')';
                                }
                                if (!empty($branch['city'])) {
                                    $label .= ' - ' . $branch['city'];
                                }
                                if (!empty($branch['is_headquarters'])) {
                                    $label .= ' [HQ]';
                                }
                            ?>
                                <option value="<?php echo esc_attr($branch['id']); ?>" 
                                        <?php selected($item['branch_id'] ?? '', $branch['id']); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="saw-field-hint">Pod kterou pobočku oddělení spadá</span>
                    </div>
                    
                    <div class="saw-form-field">
                        <label for="department_number" class="saw-label">Číslo oddělení</label>
                        <input type="text" 
                               id="department_number" 
                               name="department_number" 
                               value="<?php echo esc_attr($item['department_number'] ?? ''); ?>" 
                               class="saw-input"
                               placeholder="IT-001, MKT-01">
                        <span class="saw-field-hint">Interní číslo (volitelné, unikátní v rámci pobočky)</span>
                    </div>
                </div>
                
                <div class="saw-form-field">
                    <label for="name" class="saw-label">
                        Název oddělení <span class="saw-required-mark">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                           class="saw-input"
                           required
                           placeholder="IT, Marketing, HR">
                    <span class="saw-field-hint">Název oddělení</span>
                </div>
                
                <div class="saw-form-field">
                    <label for="description" class="saw-label">Popis</label>
                    <textarea id="description" 
                              name="description" 
                              rows="4" 
                              class="saw-textarea"
                              placeholder="Popište náplň práce a odpovědnosti oddělení..."><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                    <span class="saw-field-hint">Popis oddělení a jeho náplně práce</span>
                </div>
            </div>
        </div>
        
        <div class="saw-form-card">
            <div class="saw-form-card-header">
                <h2>Školení a nastavení</h2>
                <p>Verze školení a aktivace oddělení</p>
            </div>
            
            <div class="saw-form-card-body">
                <div class="saw-form-grid">
                    <div class="saw-form-field">
                        <label for="training_version" class="saw-label">
                            Verze školení <span class="saw-required-mark">*</span>
                        </label>
                        <input type="number" 
                               id="training_version" 
                               name="training_version" 
                               value="<?php echo esc_attr($item['training_version'] ?? 1); ?>" 
                               class="saw-input"
                               min="1"
                               required>
                        <span class="saw-field-hint">Verze aktuálního bezpečnostního školení</span>
                    </div>
                    
                    <div class="saw-form-field">
                        <label class="saw-label">Status</label>
                        <div class="saw-checkbox-card">
                            <label class="saw-checkbox-label">
                                <input type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1" 
                                       class="saw-checkbox"
                                       <?php checked(!empty($item['is_active']), true); ?>>
                                <div class="saw-checkbox-content">
                                    <span class="saw-checkbox-title">Aktivní oddělení</span>
                                    <span class="saw-checkbox-desc">Pouze aktivní oddělení jsou viditelná</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-btn saw-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                Uložit oddělení
            </button>
            <a href="<?php echo home_url('/admin/departments/'); ?>" class="saw-btn saw-btn-secondary">
                Zrušit
            </a>
        </div>
    </form>
</div>