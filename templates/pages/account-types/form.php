<?php
if (!defined('ABSPATH')) exit;

$is_edit = isset($account_type) && $account_type;
?>

<div class="saw-page-header" style="margin-bottom: 24px;">
    <h1 class="saw-page-title"><?php echo $is_edit ? 'Upravit Account Type' : 'Nový Account Type'; ?></h1>
    <a href="<?php echo home_url('/admin/settings/account-types/'); ?>" class="saw-button saw-button-secondary">
        <span class="dashicons dashicons-arrow-left-alt2"></span>
        Zpět na seznam
    </a>
</div>

<div class="saw-card">
    <div class="saw-card-body">
        <form id="account-type-form" class="saw-form">
            <input type="hidden" name="id" value="<?php echo $is_edit ? esc_attr($account_type->id) : ''; ?>">
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label for="display_name" class="saw-form-label">
                        Zobrazovaný název <span class="required">*</span>
                    </label>
                    <input type="text" id="display_name" name="display_name" class="saw-form-input" 
                           value="<?php echo $is_edit ? esc_attr($account_type->display_name) : ''; ?>" 
                           required maxlength="100">
                    <span class="saw-form-help">Název zobrazený uživatelům</span>
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label for="name" class="saw-form-label">
                        Interní název <span class="required">*</span>
                    </label>
                    <input type="text" id="name" name="name" class="saw-form-input" 
                           value="<?php echo $is_edit ? esc_attr($account_type->name) : ''; ?>" 
                           required maxlength="50">
                    <span class="saw-form-help">Unikátní identifikátor (malá písmena, bez mezer)</span>
                </div>
            </div>
            
            <div class="saw-form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div class="saw-form-group">
                    <label for="color" class="saw-form-label">Barva</label>
                    <input type="color" id="color" name="color" class="saw-form-input" 
                           value="<?php echo $is_edit ? esc_attr($account_type->color) : '#6b7280'; ?>" 
                           style="width: 100px; height: 40px;">
                </div>
                
                <div class="saw-form-group">
                    <label for="price" class="saw-form-label">Cena</label>
                    <input type="number" id="price" name="price" class="saw-form-input" 
                           value="<?php echo $is_edit ? esc_attr($account_type->price) : '0.00'; ?>" 
                           step="0.01" min="0">
                </div>
                
                <div class="saw-form-group">
                    <label for="sort_order" class="saw-form-label">Pořadí</label>
                    <input type="number" id="sort_order" name="sort_order" class="saw-form-input" 
                           value="<?php echo $is_edit ? esc_attr($account_type->sort_order) : '0'; ?>" 
                           min="0">
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label for="features" class="saw-form-label">Funkce</label>
                    <textarea id="features" name="features" class="saw-form-textarea" rows="5"><?php echo $is_edit ? esc_textarea($account_type->features) : ''; ?></textarea>
                    <span class="saw-form-help">Jedna funkce na řádek</span>
                </div>
            </div>
            
            <div class="saw-form-row">
                <div class="saw-form-group">
                    <label class="saw-checkbox-label">
                        <input type="checkbox" id="is_active" name="is_active" value="1" 
                               <?php echo (!$is_edit || $account_type->is_active) ? 'checked' : ''; ?>>
                        <span>Aktivní</span>
                    </label>
                </div>
            </div>
            
            <div class="saw-form-actions" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd;">
                <button type="submit" class="button button-primary">
                    <?php echo $is_edit ? 'Aktualizovat' : 'Vytvořit'; ?>
                </button>
                <a href="<?php echo home_url('/admin/settings/account-types/'); ?>" class="button">Zrušit</a>
            </div>
        </form>
    </div>
</div>