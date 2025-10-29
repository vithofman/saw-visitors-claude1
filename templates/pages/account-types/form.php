<?php
/**
 * Account Type Add/Edit Form Template
 *
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

$account_type = isset($account_type) ? $account_type : null;
$form_action = isset($form_action) ? $form_action : 'add';
?>

<form id="account-type-form" class="saw-form">
    <?php wp_nonce_field('saw_account_types_nonce', 'saw_account_types_nonce'); ?>
    
    <input type="hidden" name="id" value="<?php echo $account_type ? esc_attr($account_type->id) : ''; ?>">
    <input type="hidden" name="action" value="saw_account_type_save">
    
    <div class="saw-form-row">
        <div class="saw-form-group saw-form-col-6">
            <label for="display_name" class="saw-form-label">
                Display Name <span class="required">*</span>
            </label>
            <input 
                type="text" 
                id="display_name" 
                name="display_name" 
                class="saw-form-input" 
                value="<?php echo $account_type ? esc_attr($account_type->display_name) : ''; ?>"
                required
                maxlength="100"
            >
            <span class="saw-form-help">User-friendly name shown in UI</span>
        </div>
        
        <div class="saw-form-group saw-form-col-6">
            <label for="name" class="saw-form-label">
                Internal Name <span class="required">*</span>
            </label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="saw-form-input" 
                value="<?php echo $account_type ? esc_attr($account_type->name) : ''; ?>"
                required
                maxlength="50"
            >
            <span class="saw-form-help">Unique identifier (lowercase, no spaces)</span>
        </div>
    </div>
    
    <div class="saw-form-row">
        <div class="saw-form-group saw-form-col-4">
            <label for="color" class="saw-form-label">
                Color <span class="required">*</span>
            </label>
            <div class="saw-color-picker-wrapper">
                <input 
                    type="color" 
                    id="color" 
                    name="color" 
                    class="saw-color-picker" 
                    value="<?php echo $account_type ? esc_attr($account_type->color) : '#6b7280'; ?>"
                    required
                >
                <input 
                    type="text" 
                    id="color-text" 
                    class="saw-form-input saw-color-text" 
                    value="<?php echo $account_type ? esc_attr($account_type->color) : '#6b7280'; ?>"
                    pattern="^#[0-9A-Fa-f]{6}$"
                    maxlength="7"
                >
            </div>
        </div>
        
        <div class="saw-form-group saw-form-col-4">
            <label for="price" class="saw-form-label">
                Price <span class="required">*</span>
            </label>
            <input 
                type="number" 
                id="price" 
                name="price" 
                class="saw-form-input" 
                value="<?php echo $account_type ? esc_attr($account_type->price) : '0.00'; ?>"
                step="0.01"
                min="0"
                required
            >
        </div>
        
        <div class="saw-form-group saw-form-col-4">
            <label for="sort_order" class="saw-form-label">
                Sort Order
            </label>
            <input 
                type="number" 
                id="sort_order" 
                name="sort_order" 
                class="saw-form-input" 
                value="<?php echo $account_type ? esc_attr($account_type->sort_order) : '0'; ?>"
                min="0"
            >
        </div>
    </div>
    
    <div class="saw-form-row">
        <div class="saw-form-group">
            <label for="features" class="saw-form-label">
                Features
            </label>
            <textarea 
                id="features" 
                name="features" 
                class="saw-form-textarea" 
                rows="5"
            ><?php echo $account_type ? esc_textarea($account_type->features) : ''; ?></textarea>
            <span class="saw-form-help">One feature per line or JSON format</span>
        </div>
    </div>
    
    <div class="saw-form-row">
        <div class="saw-form-group">
            <label class="saw-checkbox-label">
                <input 
                    type="checkbox" 
                    id="is_active" 
                    name="is_active" 
                    value="1"
                    <?php echo (!$account_type || $account_type->is_active) ? 'checked' : ''; ?>
                >
                <span>Active</span>
            </label>
        </div>
    </div>
    
    <div class="saw-form-actions">
        <button type="submit" class="button button-primary">
            <?php echo $form_action === 'edit' ? 'Update Account Type' : 'Add Account Type'; ?>
        </button>
        <button type="button" class="button saw-modal-close">Cancel</button>
    </div>
    
    <div class="saw-form-message" style="display: none;"></div>
</form>
