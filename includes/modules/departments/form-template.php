<?php
/**
 * Departments Form Template - SIDEBAR VERSION
 * 
 * Form for creating/editing departments.
 * Optimized for use in sidebar with compact layout.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     2.0.0 - REFACTORED for sidebar usage
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if we're in sidebar mode
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Determine if this is edit mode
$is_edit = !empty($item);
$item = $item ?? array();

// Get current customer context
$customer_id = SAW_Context::get_customer_id();

// Get branches from parent scope (passed from list-template or controller)
$branches = $branches ?? array();

// If branches not provided, fetch them
if (empty($branches) && $customer_id) {
    global $wpdb;
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
    
    $branches = array();
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

// Form action URL
$form_action = $is_edit 
    ? home_url('/admin/departments/' . $item['id'] . '/edit')
    : home_url('/admin/departments/create');
?>

<?php if (!$in_sidebar): ?>
<!-- Page Header (only when NOT in sidebar) -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? __('Upravit oddělení', 'saw-visitors') : __('Nové oddělení', 'saw-visitors'); ?>
        </h1>
    </div>
    <div class="saw-page-header-actions">
        <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-button saw-button-secondary">
            <?php _e('Zpět na seznam', 'saw-visitors'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Form -->
<form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-form" id="department-form">
    <?php wp_nonce_field('saw_departments_form', 'saw_nonce'); ?>
    
    <!-- Hidden Fields -->
    <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
    
    <!-- Form Card -->
    <div class="saw-form-card">
        <?php if (!$in_sidebar): ?>
        <div class="saw-form-card-header">
            <h2 class="saw-form-card-title"><?php _e('Základní informace', 'saw-visitors'); ?></h2>
        </div>
        <?php endif; ?>
        
        <div class="saw-form-card-body">
            <!-- Branch Selection -->
            <div class="saw-form-group">
                <label for="branch_id" class="saw-label saw-label-required">
                    <?php _e('Pobočka', 'saw-visitors'); ?>
                </label>
                <select 
                    name="branch_id" 
                    id="branch_id" 
                    class="saw-select" 
                    required
                    <?php echo $is_edit ? 'disabled' : ''; ?>
                >
                    <option value=""><?php _e('-- Vyberte pobočku --', 'saw-visitors'); ?></option>
                    <?php foreach ($branches as $branch_id => $branch_name): ?>
                        <option 
                            value="<?php echo esc_attr($branch_id); ?>"
                            <?php selected(isset($item['branch_id']) ? $item['branch_id'] : '', $branch_id); ?>
                        >
                            <?php echo esc_html($branch_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($is_edit): ?>
                    <!-- Hidden field to submit branch_id when disabled -->
                    <input type="hidden" name="branch_id" value="<?php echo esc_attr($item['branch_id'] ?? ''); ?>">
                <?php endif; ?>
                <p class="saw-help-text"><?php _e('Pobočka ke které oddělení patří', 'saw-visitors'); ?></p>
            </div>
            
            <!-- Department Number -->
            <div class="saw-form-group">
                <label for="department_number" class="saw-label">
                    <?php _e('Číslo oddělení', 'saw-visitors'); ?>
                </label>
                <input 
                    type="text" 
                    name="department_number" 
                    id="department_number" 
                    class="saw-input"
                    value="<?php echo esc_attr($item['department_number'] ?? ''); ?>"
                    placeholder="<?php _e('např. 001', 'saw-visitors'); ?>"
                >
                <p class="saw-help-text"><?php _e('Interní číslo oddělení (volitelné)', 'saw-visitors'); ?></p>
            </div>
            
            <!-- Department Name -->
            <div class="saw-form-group">
                <label for="name" class="saw-label saw-label-required">
                    <?php _e('Název oddělení', 'saw-visitors'); ?>
                </label>
                <input 
                    type="text" 
                    name="name" 
                    id="name" 
                    class="saw-input"
                    value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                    placeholder="<?php _e('např. Výroba, Sklad, IT', 'saw-visitors'); ?>"
                    required
                >
                <p class="saw-help-text"><?php _e('Název oddělení', 'saw-visitors'); ?></p>
            </div>
            
            <!-- Description -->
            <div class="saw-form-group">
                <label for="description" class="saw-label">
                    <?php _e('Popis', 'saw-visitors'); ?>
                </label>
                <textarea 
                    name="description" 
                    id="description" 
                    class="saw-textarea"
                    rows="3"
                    placeholder="<?php _e('Volitelný popis oddělení...', 'saw-visitors'); ?>"
                ><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                <p class="saw-help-text"><?php _e('Volitelný popis oddělení', 'saw-visitors'); ?></p>
            </div>
            
            <!-- Training Version -->
            <div class="saw-form-group">
                <label for="training_version" class="saw-label">
                    <?php _e('Verze školení', 'saw-visitors'); ?>
                </label>
                <input 
                    type="number" 
                    name="training_version" 
                    id="training_version" 
                    class="saw-input"
                    value="<?php echo esc_attr($item['training_version'] ?? 1); ?>"
                    min="1"
                    max="999"
                    step="1"
                >
                <p class="saw-help-text"><?php _e('Aktuální verze školení pro oddělení', 'saw-visitors'); ?></p>
            </div>
            
            <!-- Active Status -->
            <div class="saw-form-group">
                <label class="saw-checkbox-label">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        id="is_active" 
                        value="1"
                        <?php checked(isset($item['is_active']) ? $item['is_active'] : 1, 1); ?>
                    >
                    <span class="saw-checkbox-text"><?php _e('Aktivní oddělení', 'saw-visitors'); ?></span>
                </label>
                <p class="saw-help-text"><?php _e('Pouze aktivní oddělení jsou dostupná pro výběr', 'saw-visitors'); ?></p>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="saw-form-card-footer">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? __('Uložit změny', 'saw-visitors') : __('Vytvořit oddělení', 'saw-visitors'); ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
            <a href="<?php echo esc_url(home_url('/admin/departments/')); ?>" class="saw-button saw-button-secondary">
                <?php _e('Zrušit', 'saw-visitors'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    console.log('[Departments] Form loaded, edit mode:', <?php echo $is_edit ? 'true' : 'false'; ?>);
    
    // Form validation
    $('#department-form').on('submit', function(e) {
        const branchId = $('#branch_id').val();
        const name = $('#name').val().trim();
        
        if (!branchId) {
            alert('<?php _e('Vyberte prosím pobočku', 'saw-visitors'); ?>');
            e.preventDefault();
            return false;
        }
        
        if (!name) {
            alert('<?php _e('Vyplňte prosím název oddělení', 'saw-visitors'); ?>');
            e.preventDefault();
            return false;
        }
    });
});
</script>