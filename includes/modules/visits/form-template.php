<?php
if (!defined('ABSPATH')) exit;

// Load Select-Create component
if (!class_exists('SAW_Component_Select_Create')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/select-create/class-saw-component-select-create.php';
}

$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_edit = !empty($item);
$item = $item ?? array();

$customer_id = SAW_Context::get_customer_id();
$context_branch_id = SAW_Context::get_branch_id();
$branches = $branches ?? array();
$companies = $companies ?? array();

if (empty($branches) && $customer_id) {
    global $wpdb;
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches', $customer_id
    ), ARRAY_A);
    foreach ($branches_data as $branch) {
        $branches[$branch['id']] = $branch['name'];
    }
}

if (empty($companies) && $customer_id) {
    $sql = $wpdb->prepare(
        "SELECT id, name FROM %i WHERE customer_id = %d",
        $wpdb->prefix . 'saw_companies', $customer_id
    );
    if ($context_branch_id) {
        $sql .= $wpdb->prepare(" AND branch_id = %d", $context_branch_id);
    }
    $sql .= " ORDER BY name ASC";
    $companies_data = $wpdb->get_results($sql, ARRAY_A);
    $companies = array();
    foreach ($companies_data as $company) {
        $companies[$company['id']] = $company['name'];
    }
}

$selected_branch_id = null;
if ($is_edit && !empty($item['branch_id'])) {
    $selected_branch_id = $item['branch_id'];
} elseif (!$is_edit && $context_branch_id) {
    $selected_branch_id = $context_branch_id;
}

// Načtení existujících hostů pro edit mode
$existing_host_ids = array();
if ($is_edit && !empty($item['id'])) {
    $existing_host_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM %i WHERE visit_id = %d",
        $wpdb->prefix . 'saw_visit_hosts',
        $item['id']
    ));
}

$form_action = $is_edit 
    ? home_url('/admin/visits/' . $item['id'] . '/edit')
    : home_url('/admin/visits/create');
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit návštěvu' : 'Nová návštěva'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-visits">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="saw-visit-form">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_visits' : 'saw_create_visits';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="branch_id" class="saw-label saw-required">Pobočka</label>
                        <select name="branch_id" id="branch_id" class="saw-input" required>
                            <option value="">-- Vyberte pobočku --</option>
                            <?php foreach ($branches as $branch_id => $branch_name): ?>
                                <option value="<?php echo esc_attr($branch_id); ?>" <?php selected($selected_branch_id, $branch_id); ?>>
                                    <?php echo esc_html($branch_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        // ✅ NOVÝ: Select-Create komponenta pro firmy
                        $company_select = new SAW_Component_Select_Create('company_id', array(
                            'label' => 'Firma',
                            'options' => $companies,
                            'selected' => $item['company_id'] ?? '',
                            'required' => true,
                            'placeholder' => '-- Vyberte firmu --',
                            'inline_create' => array(
                                'enabled' => true,
                                'target_module' => 'companies',
                                'button_text' => '+ Nová firma',
                                'prefill' => array(
                                    'branch_id' => $selected_branch_id,
                                    'customer_id' => $customer_id,
                                ),
                            ),
                        ));
                        $company_select->render();
                        ?>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="visit_type" class="saw-label saw-required">Typ návštěvy</label>
                        <select name="visit_type" id="visit_type" class="saw-input" required>
                            <option value="planned" <?php selected($item['visit_type'] ?? 'planned', 'planned'); ?>>Plánovaná</option>
                            <option value="walk_in" <?php selected($item['visit_type'] ?? '', 'walk_in'); ?>>Walk-in</option>
                        </select>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="status" class="saw-label saw-required">Stav</label>
                        <select name="status" id="status" class="saw-input" required>
                            <option value="draft" <?php selected($item['status'] ?? '', 'draft'); ?>>Koncept</option>
                            <option value="pending" <?php selected($item['status'] ?? 'pending', 'pending'); ?>>Čekající</option>
                            <option value="confirmed" <?php selected($item['status'] ?? '', 'confirmed'); ?>>Potvrzená</option>
                            <option value="in_progress" <?php selected($item['status'] ?? '', 'in_progress'); ?>>Probíhající</option>
                            <option value="completed" <?php selected($item['status'] ?? '', 'completed'); ?>>Dokončená</option>
                            <option value="cancelled" <?php selected($item['status'] ?? '', 'cancelled'); ?>>Zrušená</option>
                        </select>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="planned_date_from" class="saw-label">Datum a čas od</label>
                        <input type="datetime-local" name="planned_date_from" id="planned_date_from" class="saw-input" value="<?php echo esc_attr($item['planned_date_from'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="planned_date_to" class="saw-label">Datum a čas do</label>
                        <input type="datetime-local" name="planned_date_to" id="planned_date_to" class="saw-input" value="<?php echo esc_attr($item['planned_date_to'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="invitation_email" class="saw-label">Email pro pozvánku</label>
                        <input type="email" name="invitation_email" id="invitation_email" class="saw-input" value="<?php echo esc_attr($item['invitation_email'] ?? ''); ?>" placeholder="email@example.com">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="purpose" class="saw-label">Účel návštěvy</label>
                        <textarea name="purpose" id="purpose" class="saw-input" rows="3" placeholder="Stručný popis účelu návštěvy..."><?php echo esc_textarea($item['purpose'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- ✅ NOVÝ: Moderní checkbox výběr hostů -->
                <div class="saw-form-row field-hosts-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required">
                            Koho navštěvují
                            <span id="host-counter" style="display: inline-block; margin-left: 12px; padding: 4px 12px; background: #0073aa; color: white; border-radius: 4px; font-size: 13px; font-weight: 600;">
                                <span id="host-selected">0</span> / <span id="host-total">0</span>
                            </span>
                        </label>
                        
                        <div class="saw-host-controls" style="display: none; margin-bottom: 12px; gap: 12px; align-items: center;">
                            <input type="text" id="host-search" class="saw-input" placeholder="🔍 Hledat uživatele..." style="flex: 1; max-width: 300px;">
                            <label style="display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer; user-select: none;">
                                <input type="checkbox" id="select-all-host" style="margin: 0; cursor: pointer;">
                                <span style="font-weight: 600; color: #2c3338;">Vybrat vše</span>
                            </label>
                        </div>
                        
                        <div id="hosts-list" style="border: 2px solid #dcdcde; border-radius: 6px; max-height: 320px; overflow-y: auto; background: #fff;">
                            <p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">
                                Nejprve vyberte pobočku výše
                            </p>
                        </div>
                    </div>
                </div>
                
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit návštěvu'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/visits/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>

<style>
/* Profesionální styly pro host checkboxy - identické s Users → Oddělení */
.saw-host-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.15s ease;
    cursor: pointer;
    background: #fff;
}
.saw-host-item:hover {
    background: #f6f7f7;
}
.saw-host-item.selected {
    background: #e7f5ff;
    border-left: 3px solid #0073aa;
}
.saw-host-item:last-child {
    border-bottom: none;
}
.saw-host-item input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}
.saw-host-item label {
    flex: 1;
    cursor: pointer;
    margin: 0;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    pointer-events: none;
}
.saw-host-name {
    font-weight: 600;
    color: #2c3338;
}
.saw-host-role {
    color: #666;
    font-size: 13px;
    font-weight: 400;
}
.saw-host-controls {
    display: flex !important;
}
#hosts-list::-webkit-scrollbar {
    width: 8px;
}
#hosts-list::-webkit-scrollbar-track {
    background: #f1f1f1;
}
#hosts-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    const branchSelect = $('#branch_id');
    const hostList = $('#hosts-list');
    const hostControls = $('.saw-host-controls');
    const searchInput = $('#host-search');
    const selectAllCb = $('#select-all-host');
    const selectedSpan = $('#host-selected');
    const totalSpan = $('#host-total');
    const counterDiv = $('#host-counter');
    
    let allHosts = [];
    
    // ✅ Existující hostitelé (při editaci)
    let existingIds = <?php echo json_encode(array_map('intval', $existing_host_ids)); ?>;
    
    const ajaxUrl = (window.sawGlobal && window.sawGlobal.ajaxurl) || '/wp-admin/admin-ajax.php';
    const ajaxNonce = (window.sawGlobal && window.sawGlobal.nonce) || '<?php echo wp_create_nonce("saw_ajax_nonce"); ?>';
    
    branchSelect.on('change', loadHosts);
    searchInput.on('input', filterHosts);
    selectAllCb.on('change', toggleAll);
    
    // Načtení při prvním zobrazení (pokud je branch_id již vybraná)
    if (branchSelect.val()) {
        loadHosts();
    }
    
    function loadHosts() {
        const branchId = branchSelect.val();
        
        if (!branchId) {
            hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Nejprve vyberte pobočku výše</p>');
            hostControls.hide();
            return;
        }
        
        hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;"><span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span> Načítám uživatele...</p>');
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'saw_get_hosts_by_branch',
                nonce: ajaxNonce,
                branch_id: branchId
            },
            success: function(response) {
                if (response.success && response.data.hosts) {
                    allHosts = response.data.hosts;
                    renderHosts();
                    hostControls.show();
                } else {
                    hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center;">Žádní uživatelé nenalezeni</p>');
                    hostControls.hide();
                }
            },
            error: function() {
                hostList.html('<p class="saw-text-muted" style="padding: 20px; margin: 0; text-align: center; color: #d63638;">❌ Chyba při načítání uživatelů</p>');
                hostControls.hide();
            }
        });
    }
    
    function renderHosts() {
        let html = '';
        
        $.each(allHosts, function(index, h) {
            const hostId = parseInt(h.id);
            const checked = existingIds.includes(hostId);
            
            const label = `<span class="saw-host-name">${h.first_name} ${h.last_name}</span><span class="saw-host-role">(${h.role})</span>`;
            
            html += `<div class="saw-host-item ${checked ? 'selected' : ''}" data-id="${hostId}" data-name="${(h.first_name + ' ' + h.last_name).toLowerCase()}" data-role="${h.role.toLowerCase()}">
                <input type="checkbox" name="hosts[]" value="${hostId}" ${checked ? 'checked' : ''} id="host-${hostId}">
                <label for="host-${hostId}">${label}</label>
            </div>`;
        });
        
        hostList.html(html);
        
        // Klik na celý řádek přepne checkbox
        $('.saw-host-item').on('click', function(e) {
            if (e.target.type !== 'checkbox') {
                const cb = $(this).find('input[type="checkbox"]');
                cb.prop('checked', !cb.prop('checked')).trigger('change');
            }
        });
        
        hostList.on('change', 'input[type="checkbox"]', function() {
            $(this).closest('.saw-host-item').toggleClass('selected', this.checked);
            updateCounter();
            updateSelectAllState();
        });
        
        updateCounter();
        updateSelectAllState();
    }
    
    function filterHosts() {
        const term = searchInput.val().toLowerCase().trim();
        
        $('.saw-host-item').each(function() {
            const $item = $(this);
            const name = $item.data('name');
            const role = $item.data('role');
            
            const matches = name.includes(term) || role.includes(term);
            $item.toggle(matches);
        });
        
        updateCounter();
    }
    
    function toggleAll() {
        const checked = selectAllCb.prop('checked');
        $('.saw-host-item:visible input[type="checkbox"]').prop('checked', checked).trigger('change');
    }
    
    function updateCounter() {
        const visible = $('.saw-host-item:visible').length;
        const selected = $('.saw-host-item:visible input[type="checkbox"]:checked').length;
        
        selectedSpan.text(selected);
        totalSpan.text(visible);
        
        // Změna barvy podle počtu vybraných
        if (selected === 0) {
            counterDiv.css('background', '#d63638'); // červená
        } else if (selected === visible) {
            counterDiv.css('background', '#00a32a'); // zelená
        } else {
            counterDiv.css('background', '#0073aa'); // modrá
        }
    }
    
    function updateSelectAllState() {
        const visible = $('.saw-host-item:visible').length;
        const selected = $('.saw-host-item:visible input[type="checkbox"]:checked').length;
        
        selectAllCb.prop('checked', visible > 0 && selected === visible);
    }
});
</script>

<style>
@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>