<?php
/**
 * Companies Detail Sidebar Template - v18.0.0 FINAL
 * @version 18.0.0 - Elegantní alerty + manuální výběr
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Firma nebyla nalezena</div>';
    return;
}

global $wpdb;
$visits = array();
if (!empty($item['id'])) {
    $visits = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            v.id,
            v.visit_type,
            v.status,
            v.created_at,
            v.started_at,
            v.completed_at,
            COUNT(vis.id) as visitor_count
         FROM {$wpdb->prefix}saw_visits v
         LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
         WHERE v.company_id = %d 
         GROUP BY v.id
         ORDER BY v.created_at DESC",
        $item['id']
    ), ARRAY_A);
}

function get_initials($first_name, $last_name) {
    $first = mb_substr($first_name, 0, 1);
    $last = mb_substr($last_name, 0, 1);
    return strtoupper($first . $last);
}

$avatar_colors = array('#0077B5', '#005A8C', '#004466', '#003A57', '#0088CC', '#006699');

// Get all companies for manual selection
$all_companies = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, ico,
     (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE company_id = c.id) as visit_count
     FROM {$wpdb->prefix}saw_companies c
     WHERE branch_id = %d AND id != %d AND is_archived = 0
     ORDER BY name ASC",
    $item['branch_id'],
    $item['id']
), ARRAY_A);
?>


<div class="saw-detail-sidebar-content" data-company-id="<?php echo intval($item['id']); ?>">
    <!-- Header is now rendered by admin-table component (detail-sidebar.php) -->
    <!-- Module only provides content -->
    <!-- Header meta (badges) is set via controller->get_detail_header_meta() -->
    
    <div class="saw-industrial-section saw-warning-section">
        <div class="saw-section-body saw-warning-section-body">
            <div class="saw-warning-content">
                <div class="saw-warning-text">
                    <strong class="saw-warning-title">⚠️ Možné duplicity</strong>
                    <p class="saw-warning-description">Zkontrolujte, zda neexistují podobné firmy</p>
                </div>
                <button id="sawMergeBtn" class="saw-detail-visit-btn" type="button">
                    🔗 Zkontrolovat
                </button>
            </div>
            
            <div id="sawMergeContainer">
                <div class="saw-merge-header">
                    <h4>🔗 Sloučit duplicity</h4>
                    <button class="saw-merge-close" onclick="closeMerge()">×</button>
                </div>
                <div class="saw-merge-body">
                    <div class="saw-merge-tabs">
                        <button class="saw-merge-tab active" onclick="switchTab('auto')">🤖 Auto detekce</button>
                        <button class="saw-merge-tab" onclick="switchTab('manual')">✋ Manuální výběr</button>
                    </div>
                    
                    <div id="sawMergeAuto" class="saw-merge-content active">
                        <div id="sawMergeAutoContent">
                            <div class="saw-loading-state">⏳ Načítání...</div>
                        </div>
                    </div>
                    
                    <div id="sawMergeManual" class="saw-merge-content">
                        <div class="saw-help-text">
                            💡 Vyhledejte a vyberte firmy, které chcete sloučit pod aktuální firmu
                        </div>
                        <div class="saw-manual-search">
                            <input type="text" id="sawManualSearch" placeholder="🔍 Hledat firmu..." onkeyup="filterManualList()">
                        </div>
                        <div class="saw-duplicate-list" id="sawManualList">
                            <?php foreach ($all_companies as $company): ?>
                            <label class="saw-duplicate-item" data-name="<?php echo esc_attr(strtolower($company['name'])); ?>">
                                <input type="checkbox" name="manual_ids[]" value="<?php echo intval($company['id']); ?>" onchange="updateMergeButton()">
                                <div class="saw-dup-info">
                                    <strong><?php echo esc_html($company['name']); ?></strong>
                                    <div class="saw-dup-meta">
                                        <span class="saw-visit-count">📋 <?php echo intval($company['visit_count']); ?> návštěv</span>
                                        <?php if (!empty($company['ico'])): ?>
                                        <span class="saw-ico-text">IČO: <?php echo esc_html($company['ico']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="saw-merge-actions">
                            <button class="saw-btn saw-btn-primary" id="sawMergeButton" onclick="confirmMerge()" disabled>
                                Sloučit vybrané
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="saw-industrial-section">
        <div class="saw-section-head"><h4 class="saw-section-title saw-section-title-accent">📍 Adresa sídla</h4></div>
        <div class="saw-section-body">
            <div class="saw-info-grid">
                <?php if (!empty($item['street'])): ?>
                <div class="saw-info-item"><label>Ulice</label><span><?php echo esc_html($item['street']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($item['city']) || !empty($item['zip'])): ?>
                <div class="saw-info-item"><label>Město a PSČ</label><span><?php echo esc_html(implode(' ', array_filter(array($item['zip'] ?? '', $item['city'] ?? '')))); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($item['country'])): ?>
                <div class="saw-info-item"><label>Země</label><span><?php echo esc_html($item['country']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($item['email']) || !empty($item['phone']) || !empty($item['website'])): ?>
    <div class="saw-industrial-section">
        <div class="saw-section-head"><h4 class="saw-section-title saw-section-title-accent">📞 Kontakt</h4></div>
        <div class="saw-section-body">
            <div class="saw-info-grid">
                <?php if (!empty($item['email'])): ?>
                <div class="saw-info-item"><label>Email</label><span><a href="mailto:<?php echo esc_attr($item['email']); ?>" class="saw-link"><?php echo esc_html($item['email']); ?></a></span></div>
                <?php endif; ?>
                <?php if (!empty($item['phone'])): ?>
                <div class="saw-info-item"><label>Telefon</label><span><a href="tel:<?php echo esc_attr(str_replace(' ', '', $item['phone'])); ?>" class="saw-link"><?php echo esc_html($item['phone']); ?></a></span></div>
                <?php endif; ?>
                <?php if (!empty($item['website'])): ?>
                <div class="saw-info-item"><label>Web</label><span><a href="<?php echo esc_url($item['website']); ?>" target="_blank" class="saw-link"><?php echo esc_html($item['website']); ?> ↗</a></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="saw-industrial-section">
        <div class="saw-section-head"><h4 class="saw-section-title saw-section-title-accent">ℹ️ Info</h4></div>
        <div class="saw-section-body">
            <div class="saw-info-grid">
                <?php if (!empty($item['branch_name'])): ?>
                <div class="saw-info-item"><label>Pobočka</label><span><?php echo esc_html($item['branch_name']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($item['created_at_formatted'])): ?>
                <div class="saw-info-item"><label>Vytvořeno</label><span><?php echo esc_html($item['created_at_formatted']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($item['updated_at_formatted'])): ?>
                <div class="saw-info-item"><label>Změněno</label><span><?php echo esc_html($item['updated_at_formatted']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($visits)): ?>
    <div class="saw-industrial-section">
        <div class="saw-section-head">
            <h4 class="saw-section-title saw-section-title-accent">📋 Návštěvy <span class="saw-visit-badge-count"><?php echo count($visits); ?></span></h4>
        </div>
        <div class="saw-section-body saw-visit-section-body">
            <?php foreach ($visits as $idx => $visit): 
                $status_labels = array('draft' => 'Koncept', 'pending' => 'Čeká', 'confirmed' => 'Potvrzeno', 'in_progress' => 'Probíhá', 'completed' => 'Dokončeno', 'cancelled' => 'Zrušeno');
                $type_labels = array('planned' => 'Plánovaná', 'walk_in' => 'Walk-in');
                $border_class = 'status-' . $visit['status'];
                $ts = strtotime($visit['created_at']);
                $day = date_i18n('d', $ts);
                $month = date_i18n('M', $ts);
                $visitors = $wpdb->get_results($wpdb->prepare("SELECT v.id,v.first_name,v.last_name,v.email,v.phone,v.position,CASE WHEN EXISTS(SELECT 1 FROM {$wpdb->prefix}saw_visit_daily_logs dl WHERE dl.visitor_id=v.id)THEN 1 ELSE 0 END as has_attended FROM {$wpdb->prefix}saw_visitors v WHERE v.visit_id=%d ORDER BY v.last_name,v.first_name",$visit['id']),ARRAY_A);
                $visit_detail_url = home_url('/admin/visits/' . $visit['id'] . '/');
            ?>
            <div class="saw-visit-agenda-card <?php echo $border_class; ?>">
                <div class="saw-visit-trigger" onclick="toggleVisit(<?php echo $visit['id']; ?>)">
                    <div class="saw-visit-date-box"><span class="saw-date-day"><?php echo $day; ?></span><span class="saw-date-month"><?php echo $month; ?></span></div>
                    <div class="saw-visit-info-box">
                        <div class="saw-visit-top">
                            <span class="saw-visit-id">ID: <?php echo intval($visit['id']); ?></span>
                            <span class="saw-visit-badge"><?php echo esc_html($type_labels[$visit['visit_type']] ?? $visit['visit_type']); ?></span>
                        </div>
                        <div class="saw-visit-main-title"><?php echo esc_html($status_labels[$visit['status']] ?? $visit['status']); ?> <span class="saw-visit-count">👥 <?php echo intval($visit['visitor_count']); ?></span></div>
                    </div>
                    <div id="icon-<?php echo $visit['id']; ?>" class="saw-toggle-arrow">▶</div>
                </div>
                <div id="visit-<?php echo $visit['id']; ?>" class="saw-visitors-wrap">
                    <div class="saw-visitors-header">
                        <div class="saw-visitors-title">👥 Návštěvníci (<?php echo count($visitors); ?>)</div>
                        <a href="<?php echo esc_url($visit_detail_url); ?>" class="saw-detail-visit-btn" onclick="event.stopPropagation();">Detail →</a>
                    </div>
                    <?php if (!empty($visitors)): foreach ($visitors as $v_idx => $visitor): 
                        $initials = get_initials($visitor['first_name'], $visitor['last_name']);
                        $avatar_color = $avatar_colors[($idx + $v_idx) % count($avatar_colors)];
                        $visitor_url = home_url('/admin/visitors/' . $visitor['id'] . '/');
                        $did_not_attend = !(int)$visitor['has_attended'] && $visit['status'] === 'completed';
                    ?>
                    <a href="<?php echo esc_url($visitor_url); ?>" class="saw-visitor-row <?php echo $did_not_attend ? 'not-attended' : ''; ?>">
                        <div class="saw-v-avatar" style="background-color:<?php echo esc_attr($avatar_color); ?>"><?php echo esc_html($initials); ?></div>
                        <div class="saw-v-info">
                            <h5><?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?><?php if ($did_not_attend): ?> <span class="saw-not-attended-badge">Nezúčastnil se</span><?php endif; ?></h5>
                            <?php if(!empty($visitor['position'])): ?><p><?php echo esc_html($visitor['position']); ?></p><?php endif; ?>
                            <?php if(!empty($visitor['email']) || !empty($visitor['phone'])): ?>
                                <p class="saw-v-info-meta"><?php if(!empty($visitor['email'])): ?>📧 <?php echo esc_html($visitor['email']); ?><?php endif; ?><?php if(!empty($visitor['phone'])): ?><?php if(!empty($visitor['email'])) echo ' • '; ?>📱 <?php echo esc_html($visitor['phone']); ?><?php endif; ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; else: ?><p class="saw-empty-visitors">Žádní návštěvníci</p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript moved to assets/js/modules/companies/companies-detail.js -->
<!-- Asset is enqueued automatically by SAW_Asset_Loader -->