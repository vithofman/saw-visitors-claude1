<?php
/**
 * Visitors Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Component_Select_Create')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/select-create/class-saw-component-select-create.php';
}

$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_edit = !empty($item);
$item = $item ?? array();

$customer_id = SAW_Context::get_customer_id();
$visits = $visits ?? array();

// Load visits if not provided
if (empty($visits) && $customer_id) {
    global $wpdb;
    $visits_data = $wpdb->get_results($wpdb->prepare(
        "SELECT v.id, c.name as company_name, v.status
         FROM %i v
         LEFT JOIN %i c ON v.company_id = c.id
         WHERE v.customer_id = %d
         ORDER BY v.created_at DESC
         LIMIT 100",
        $wpdb->prefix . 'saw_visits',
        $wpdb->prefix . 'saw_companies',
        $customer_id
    ), ARRAY_A);
    
    foreach ($visits_data as $visit) {
        $visits[$visit['id']] = sprintf(
            '%s (#%d)',
            $visit['company_name'] ?? 'N/A',
            $visit['id']
        );
    }
}

// Load existing certificates
$existing_certificates = array();
if ($is_edit && !empty($item['id'])) {
    global $wpdb;
    $existing_certificates = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM %i WHERE visitor_id = %d ORDER BY created_at ASC",
        $wpdb->prefix . 'saw_visitor_certificates',
        $item['id']
    ), ARRAY_A);
}

$form_action = $is_edit 
    ? home_url('/admin/visitors/' . $item['id'] . '/edit')
    : home_url('/admin/visitors/create');
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit návštěvníka' : 'Nový návštěvník'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/visitors/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-visitors">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-visitor-form">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_visitors' : 'saw_create_visitors';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- BASIC INFORMATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-users"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Visit Select-Create -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required">Návštěva</label>
                        <?php
                        if (class_exists('SAW_Component_Select_Create')) {
                            $select_create = new SAW_Component_Select_Create('visit_id', array(
                                'label' => 'Návštěva',
                                'options' => $visits,
                                'selected' => $item['visit_id'] ?? '',
                                'required' => true,
                                'inline_create' => array(
                                    'enabled' => true,
                                    'target_module' => 'visits',
                                    'button_text' => '+ Nová návštěva',
                                    'prefill' => array(
                                        'customer_id' => $customer_id,
                                    ),
                                ),
                            ));
                            $select_create->render();
                        } else {
                            echo '<p style="color:red;font-weight:bold;">ERROR: SAW_Component_Select_Create class not found!</p>';
                            echo '<select name="visit_id" class="saw-input" required>';
                            echo '<option value="">-- Vyberte návštěvu --</option>';
                            foreach ($visits as $id => $name) {
                                echo '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
                            }
                            echo '</select>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="first_name" class="saw-label saw-required">Jméno</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="saw-input" 
                               value="<?php echo esc_attr($item['first_name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="last_name" class="saw-label saw-required">Příjmení</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               class="saw-input" 
                               value="<?php echo esc_attr($item['last_name'] ?? ''); ?>" 
                               required>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="position" class="saw-label">Pozice/profese</label>
                        <input type="text" 
                               id="position" 
                               name="position" 
                               class="saw-input" 
                               value="<?php echo esc_attr($item['position'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label">Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="saw-input" 
                               value="<?php echo esc_attr($item['email'] ?? ''); ?>" 
                               placeholder="email@example.com">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="phone" class="saw-label">Telefon</label>
                        <input type="text" 
                               id="phone" 
                               name="phone" 
                               class="saw-input" 
                               value="<?php echo esc_attr($item['phone'] ?? ''); ?>" 
                               placeholder="+420 123 456 789">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="participation_status" class="saw-label saw-required">Stav účasti</label>
                        <select id="participation_status" name="participation_status" class="saw-input" required>
                            <option value="planned" <?php selected($item['participation_status'] ?? 'planned', 'planned'); ?>>Plánovaný</option>
                            <option value="confirmed" <?php selected($item['participation_status'] ?? '', 'confirmed'); ?>>Potvrzený</option>
                            <option value="no_show" <?php selected($item['participation_status'] ?? '', 'no_show'); ?>>Nedostavil se</option>
                        </select>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label class="saw-label" style="display: block; margin-bottom: 8px;">&nbsp;</label>
                        <label class="saw-checkbox-label">
                            <input type="checkbox" 
                                   name="training_skipped" 
                                   value="1" 
                                   <?php checked($item['training_skipped'] ?? 0, 1); ?>>
                            Školení absolvováno do 1 roku
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- CERTIFICATES -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-awards"></span>
                <strong>Profesní průkazy</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div id="certificates-container">
                    <?php if (!empty($existing_certificates)): ?>
                        <?php foreach ($existing_certificates as $index => $cert): ?>
                        <div class="saw-certificate-row" data-index="<?php echo $index; ?>">
                            <div class="saw-form-row" style="margin-bottom: 0;">
                                <div class="saw-form-group saw-col-4">
                                    <label class="saw-label">Název průkazu</label>
                                    <input type="text" 
                                           name="certificates[<?php echo $index; ?>][certificate_name]" 
                                           class="saw-input" 
                                           value="<?php echo esc_attr($cert['certificate_name']); ?>" 
                                           placeholder="např. Svářečský průkaz">
                                </div>
                                
                                <div class="saw-form-group saw-col-3">
                                    <label class="saw-label">Číslo průkazu</label>
                                    <input type="text" 
                                           name="certificates[<?php echo $index; ?>][certificate_number]" 
                                           class="saw-input" 
                                           value="<?php echo esc_attr($cert['certificate_number'] ?? ''); ?>" 
                                           placeholder="ABC123456">
                                </div>
                                
                                <div class="saw-form-group saw-col-3">
                                    <label class="saw-label">Platnost do</label>
                                    <input type="date" 
                                           name="certificates[<?php echo $index; ?>][valid_until]" 
                                           class="saw-input" 
                                           value="<?php echo esc_attr($cert['valid_until'] ?? ''); ?>">
                                </div>
                                
                                <div class="saw-form-group saw-col-2" style="display: flex; align-items: flex-end;">
                                    <button type="button" class="saw-button saw-button-danger saw-remove-certificate" style="width: 100%;">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="add-certificate-btn" class="saw-button saw-button-secondary" style="margin-top: 12px;">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Přidat průkaz
                </button>
                
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit návštěvníka'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/visitors/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>

<style>
.saw-certificate-row {
    padding: 16px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 12px;
    border-left: 3px solid #0073aa;
}

.saw-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px 12px;
    background: #f0f0f1;
    border-radius: 4px;
    font-weight: 500;
}

.saw-checkbox-label input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
}
</style>

<script>
(function($) {
    'use strict';
    
    let certificateIndex = <?php echo !empty($existing_certificates) ? count($existing_certificates) : 0; ?>;
    
    // Add certificate
    $('#add-certificate-btn').on('click', function() {
        const html = `
            <div class="saw-certificate-row" data-index="${certificateIndex}">
                <div class="saw-form-row" style="margin-bottom: 0;">
                    <div class="saw-form-group saw-col-4">
                        <label class="saw-label">Název průkazu</label>
                        <input type="text" 
                               name="certificates[${certificateIndex}][certificate_name]" 
                               class="saw-input" 
                               placeholder="např. Svářečský průkaz">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label class="saw-label">Číslo průkazu</label>
                        <input type="text" 
                               name="certificates[${certificateIndex}][certificate_number]" 
                               class="saw-input" 
                               placeholder="ABC123456">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label class="saw-label">Platnost do</label>
                        <input type="date" 
                               name="certificates[${certificateIndex}][valid_until]" 
                               class="saw-input">
                    </div>
                    
                    <div class="saw-form-group saw-col-2" style="display: flex; align-items: flex-end;">
                        <button type="button" class="saw-button saw-button-danger saw-remove-certificate" style="width: 100%;">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('#certificates-container').append(html);
        certificateIndex++;
    });
    
    // Remove certificate
    $(document).on('click', '.saw-remove-certificate', function() {
        $(this).closest('.saw-certificate-row').remove();
    });
    
})(jQuery);
</script>
