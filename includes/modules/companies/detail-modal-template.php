<?php
/**
 * Companies Detail Sidebar Template - BENTO DESIGN
 *
 * Moderní Bento Box design systém pro zobrazení detailu firmy.
 * Asymetrický grid s kartami různých velikostí, firemní barvy.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @version     20.0.0 - BENTO DESIGN SYSTEM
 * @since       4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'companies') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="sa-alert sa-alert--danger">' . esc_html($tr('error_not_found', 'Firma nebyla nalezena')) . '</div>';
    return;
}

// ============================================
// LOAD RELATED DATA
// ============================================
global $wpdb;

$visits_count = 0;
$visitors_count = 0;
$visits_in_progress = 0;
$visits = array();
$all_companies = array();

if (!empty($item['id'])) {
    $company_id = intval($item['id']);
    
    // Count visits
    $visits_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE company_id = %d",
        $company_id
    ));
    
    // Count unique visitors
    $visitors_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT vis.id) 
         FROM {$wpdb->prefix}saw_visitors vis
         INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
         WHERE v.company_id = %d",
        $company_id
    ));
    
    // Count in-progress visits
    $visits_in_progress = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits 
         WHERE company_id = %d AND status = 'in_progress'",
        $company_id
    ));
    
    // Get last 5 visits
    $visits = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            v.id,
            v.visit_type,
            v.status,
            v.created_at,
            COUNT(vis.id) as visitor_count
         FROM {$wpdb->prefix}saw_visits v
         LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
         WHERE v.company_id = %d 
         GROUP BY v.id
         ORDER BY v.created_at DESC
         LIMIT 5",
        $company_id
    ), ARRAY_A) ?: array();
    
    // Get companies for manual merge selection
    if (!empty($item['branch_id'])) {
        $all_companies = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, ico,
             (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE company_id = c.id) as visit_count
             FROM {$wpdb->prefix}saw_companies c
             WHERE branch_id = %d AND id != %d AND is_archived = 0
             ORDER BY name ASC",
            intval($item['branch_id']),
            $company_id
        ), ARRAY_A) ?: array();
    }
}

// Status labels
$status_labels = array(
    'draft' => $tr('visit_status_draft', 'Koncept'),
    'pending' => $tr('visit_status_pending', 'Čeká'),
    'confirmed' => $tr('visit_status_confirmed', 'Potvrzeno'),
    'in_progress' => $tr('visit_status_in_progress', 'Probíhá'),
    'completed' => $tr('visit_status_completed', 'Dokončeno'),
    'cancelled' => $tr('visit_status_cancelled', 'Zrušeno'),
);

$type_labels = array(
    'planned' => $tr('visit_type_planned', 'Plánovaná'),
    'walk_in' => $tr('visit_type_walk_in', 'Walk-in'),
);

// ============================================
// BUILD HEADER BADGES
// ============================================
$badges = [];

if (!empty($item['ico'])) {
    $badges[] = [
        'label' => $tr('ico_label', 'IČO') . ': ' . $item['ico'],
        'variant' => 'neutral',
    ];
}

if (!empty($item['is_archived'])) {
    $badges[] = [
        'label' => $tr('status_archived', 'Archivováno'),
        'variant' => 'warning',
        'icon' => '📦',
    ];
} else {
    $badges[] = [
        'label' => $tr('status_active', 'Aktivní'),
        'variant' => 'success',
        'dot' => true,
    ];
}

// ============================================
// RENDER BENTO GRID
// ============================================
?>

<div class="sa-detail-wrapper bento-wrapper">
    <?php 
    // Initialize Bento
    if (function_exists('saw_bento_start')) {
        
        // Start Bento Grid
        saw_bento_start('bento-companies');
        
        // ================================
        // HEADER
        // ================================
        saw_bento_header([
            'icon' => 'briefcase',
            'module' => 'companies',
            'module_label' => $tr('module_name', 'Firmy'),
            'id' => $item['id'],
            'title' => $item['name'] ?? $tr('unnamed', 'Bez názvu'),
            'subtitle' => !empty($item['branch_name']) ? $item['branch_name'] : $tr('no_branch', 'Bez pobočky'),
            'badges' => $badges,
            'nav_enabled' => true,
            'stripe' => true,
            'close_url' => $close_url ?? '',
        ]);
        
        // ================================
        // STATISTICS (3 stat cards)
        // ================================
        saw_bento_stat([
            'icon' => 'clipboard-list',
            'value' => $visits_count,
            'label' => $tr('stat_visits', 'Návštěv'),
            'variant' => 'default',
            'link' => home_url('/admin/visits/?company_id=' . intval($item['id'])),
        ]);
        
        saw_bento_stat([
            'icon' => 'users',
            'value' => $visitors_count,
            'label' => $tr('stat_visitors', 'Návštěvníků'),
            'variant' => 'default',
            'link' => home_url('/admin/visitors/?company_id=' . intval($item['id'])),
        ]);
        
        saw_bento_stat([
            'icon' => 'clock',
            'value' => $visits_in_progress,
            'label' => $tr('stat_in_progress', 'Probíhá'),
            'variant' => $visits_in_progress > 0 ? 'light-blue' : 'default',
        ]);
        
        // ================================
        // ADDRESS (if exists)
        // ================================
        $has_address = !empty($item['street']) || !empty($item['city']) || !empty($item['zip']);
        if ($has_address) {
            saw_bento_address([
                'icon' => 'map-pin',
                'title' => $tr('section_address', 'Adresa sídla'),
                'street' => $item['street'] ?? '',
                'city' => $item['city'] ?? '',
                'zip' => $item['zip'] ?? '',
                'country' => $item['country'] ?? '',
                'highlight_city' => true,
                'colspan' => 2,
                'show_map_link' => true,
            ]);
        }
        
        // ================================
        // CONTACT (if exists)
        // ================================
        $has_contact = !empty($item['phone']) || !empty($item['email']) || !empty($item['website']);
        if ($has_contact) {
            saw_bento_contact([
                'icon' => 'phone',
                'title' => $tr('section_contact', 'Kontakt'),
                'phone' => $item['phone'] ?? '',
                'email' => $item['email'] ?? '',
                'website' => $item['website'] ?? '',
                'variant' => 'dark',
                'colspan' => 1,
            ]);
        }
        
        // ================================
        // INFO
        // ================================
        $info_fields = [
            [
                'label' => 'ID',
                'value' => $item['id'],
                'type' => 'code',
            ],
        ];
        
        if (!empty($item['ico'])) {
            $info_fields[] = [
                'label' => $tr('ico_label', 'IČO'),
                'value' => $item['ico'],
                'type' => 'code',
                'copyable' => true,
            ];
        }
        
        if (!empty($item['branch_name'])) {
            $info_fields[] = [
                'label' => $tr('field_branch', 'Pobočka'),
                'value' => $item['branch_name'],
                'type' => 'text',
            ];
        }
        
        $info_fields[] = [
            'label' => $tr('field_status', 'Status'),
            'value' => !empty($item['is_archived']) ? $tr('status_archived', 'Archivováno') : $tr('status_active', 'Aktivní'),
            'type' => 'status',
            'status' => !empty($item['is_archived']) ? 'warning' : 'success',
            'dot' => true,
        ];
        
        saw_bento_info([
            'icon' => 'info',
            'title' => $tr('section_info', 'Informace'),
            'fields' => $info_fields,
            'colspan' => 1,
        ]);
        
        // ================================
        // MERGE SECTION
        // ================================
        if (function_exists('saw_bento_merge')) {
            saw_bento_merge([
                'icon' => 'git-merge',
                'title' => $tr('merge_title', 'Kontrola duplicit'),
                'warning_title' => $tr('merge_possible_duplicates', 'Možné duplicity'),
                'warning_text' => $tr('merge_check_description', 'Zkontrolujte, zda neexistují podobné firmy'),
                'check_btn_label' => $tr('merge_check_btn', 'Zkontrolovat'),
                'entity_id' => $item['id'],
                'entity_type' => 'companies',
                'ajax_action' => 'saw_show_merge_modal_companies',
                'merge_ajax_action' => 'saw_merge_companies',
                'manual_companies' => $all_companies,
                'translations' => $t,
                'colspan' => 2,
            ]);
        }
        
        // ================================
        // VISITS LIST (if exists)
        // ================================
        if (!empty($visits)) {
            $visit_items = array_map(function($visit) use ($status_labels, $type_labels) {
                $status = $status_labels[$visit['status']] ?? $visit['status'];
                return [
                    'icon' => 'clipboard-list',
                    'name' => $status,
                    'meta' => ($type_labels[$visit['visit_type']] ?? $visit['visit_type']) . ' • ' . date_i18n('j.n.Y', strtotime($visit['created_at'])),
                    'url' => home_url('/admin/visits/' . intval($visit['id']) . '/'),
                    'badge' => $visit['visitor_count'] . ' 👥',
                    'active' => $visit['status'] === 'in_progress',
                ];
            }, $visits);
            
            saw_bento_list([
                'icon' => 'clipboard-list',
                'title' => $tr('section_visits', 'Návštěvy'),
                'badge_count' => $visits_count,
                'items' => $visit_items,
                'show_all_url' => home_url('/admin/visits/?company_id=' . intval($item['id'])),
                'show_all_label' => $tr('show_all', 'Zobrazit všechny'),
                'max_items' => 5,
                'colspan' => 2,
            ]);
        }
        
        // ================================
        // METADATA (always last, full width)
        // ================================
        saw_bento_meta([
            'icon' => 'clock',
            'title' => $tr('section_metadata', 'Metadata'),
            'created_at' => $item['created_at_formatted'] ?? null,
            'updated_at' => $item['updated_at_formatted'] ?? null,
            'colspan' => 'full',
            'compact' => true,
        ]);
        
        // End Bento Grid
        saw_bento_end();
        
    } else {
        // Fallback - show error if Bento not loaded
        echo '<div class="sa-alert sa-alert--warning">';
        echo 'Bento design system není načten. ';
        echo '</div>';
    }
    ?>
</div>

<script>
// ============================================
// MERGE UI JAVASCRIPT
// ============================================
(function($) {
    'use strict';
    
    // Define AJAX configuration
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var mergeNonce = '<?php echo esc_js(wp_create_nonce('saw_ajax_nonce')); ?>';
    
    // Show merge container
    window.showMerge = function() {
        var $container = $('#sawMergeContainer');
        $container.addClass('active');
        loadAutoDetection();
    };
    
    // Close merge container
    window.closeMerge = function() {
        var $container = $('#sawMergeContainer');
        $container.removeClass('active');
    };
    
    // Switch tabs
    window.switchTab = function(tab) {
        $('.bento-merge-tab').removeClass('active');
        $('.bento-merge-tab[data-tab="' + tab + '"]').addClass('active');
        
        $('.bento-merge-content').removeClass('active');
        $('#sawMerge' + tab.charAt(0).toUpperCase() + tab.slice(1)).addClass('active');
        
        if (tab === 'auto') {
            loadAutoDetection();
        }
    };
    
    // Load auto detection
    function loadAutoDetection() {
        var $content = $('#sawMergeAutoContent');
        var entityId = $('.bento-merge').data('entity-id');
        
        $content.html('<div class="bento-merge-loading"><span>⏳ <?php echo esc_js($tr('loading', 'Načítání...')); ?></span></div>');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'saw_show_merge_modal',
                id: entityId,
                nonce: mergeNonce
            },
            success: function(response) {
                $content.html(response);
            },
            error: function() {
                $content.html('<div class="bento-merge-error">❌ <?php echo esc_js($tr('error_loading', 'Chyba při načítání')); ?></div>');
            }
        });
    }
    
    // Filter manual list
    window.filterManualList = function() {
        var search = $('#sawManualSearch').val().toLowerCase();
        $('#sawManualList .bento-merge-item').each(function() {
            var name = $(this).data('name');
            $(this).toggle(name.indexOf(search) !== -1);
        });
    };
    
    // Update merge button state
    window.updateMergeButton = function() {
        var checked = $('input[name="duplicate_ids[]"]:checked, input[name="manual_ids[]"]:checked').length;
        $('#sawMergeButton').prop('disabled', checked === 0);
    };
    
    // Confirm merge
    window.confirmMerge = function() {
        var entityId = $('.bento-merge').data('entity-id');
        var duplicateIds = [];
        
        $('input[name="duplicate_ids[]"]:checked, input[name="manual_ids[]"]:checked').each(function() {
            duplicateIds.push($(this).val());
        });
        
        if (duplicateIds.length === 0) {
            alert('<?php echo esc_js($tr('merge_select_warning', 'Vyberte alespoň jednu firmu ke sloučení')); ?>');
            return;
        }
        
        if (!confirm('<?php echo esc_js($tr('merge_confirm', 'Opravdu chcete sloučit vybrané firmy? Tato akce je nevratná!')); ?>')) {
            return;
        }
        
        var $btn = $('#sawMergeButton');
        $btn.prop('disabled', true).text('<?php echo esc_js($tr('merging', 'Slučuji...')); ?>');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'saw_merge_companies',
                master_id: entityId,
                duplicate_ids: JSON.stringify(duplicateIds),
                nonce: mergeNonce
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', '<?php echo esc_js($tr('merge_success_title', 'Sloučení dokončeno')); ?>', response.data.message);
                    closeMerge();
                    // Reload page after short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('error', '<?php echo esc_js($tr('merge_error_title', 'Chyba')); ?>', response.data.message);
                    $btn.prop('disabled', false).text('<?php echo esc_js($tr('merge_selected_btn', 'Sloučit vybrané')); ?>');
                }
            },
            error: function() {
                showAlert('error', '<?php echo esc_js($tr('merge_error_title', 'Chyba')); ?>', '<?php echo esc_js($tr('merge_error_network', 'Síťová chyba')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js($tr('merge_selected_btn', 'Sloučit vybrané')); ?>');
            }
        });
    };
    
    // Show alert notification
    function showAlert(type, title, message) {
        var icon = type === 'success' ? '✅' : '❌';
        var $alert = $('<div class="saw-elegant-alert saw-alert-' + type + '">' +
            '<div class="saw-alert-icon">' + icon + '</div>' +
            '<div class="saw-alert-content"><strong>' + title + '</strong><p>' + message + '</p></div>' +
            '<button class="saw-alert-close" onclick="$(this).parent().remove()">×</button>' +
        '</div>');
        
        $('body').append($alert);
        
        setTimeout(function() {
            $alert.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        // Bind merge button click (fallback if warning box is shown)
        $(document).on('click', '#sawMergeBtn', function() {
            showMerge();
        });
        
        // Auto-load duplicate detection since merge is auto-expanded
        if ($('#sawMergeContainer').hasClass('active')) {
            loadAutoDetection();
        }
    });
    
})(jQuery);
</script>