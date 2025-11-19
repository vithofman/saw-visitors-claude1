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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
:root {--p307-primary:#005A8C;--p307-accent:#0077B5;--p307-dark:#1a1a1a;--p307-gray:#f4f6f8;--p307-border:#dce1e5;--status-draft:#95a5a6;--status-pending:#f39c12;--status-confirmed:#3498db;--status-in-progress:#9b59b6;--status-completed:#27ae60;--status-cancelled:#e74c3c}
.saw-detail-sidebar-content{font-family:'Roboto',sans-serif;color:var(--p307-dark);box-sizing:border-box}
.saw-detail-sidebar-content *{box-sizing:border-box}
.saw-industrial-stripe{height:8px;width:100%;background-image:repeating-linear-gradient(-45deg,var(--p307-primary),var(--p307-primary) 10px,#fff 10px,#fff 20px);border-bottom:1px solid rgba(0,0,0,.1)}
.saw-industrial-header{background:var(--p307-primary);color:#fff;border-radius:4px 4px 0 0;overflow:hidden;margin-bottom:12px!important;box-shadow:0 4px 15px rgba(0,90,140,.2)}
.saw-header-inner{padding:16px 24px 12px 24px!important}
.saw-industrial-header h3{font-family:'Oswald',sans-serif;font-weight:700;font-size:28px;text-transform:uppercase;margin:0 0 6px 0!important;color:#fff!important;letter-spacing:1px;line-height:1.1}
.saw-badge-transparent{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);padding:4px 10px;font-size:12px;border-radius:2px;font-weight:500;color:#fff}
.saw-industrial-section{background:#fff;border:1px solid var(--p307-border);margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.saw-section-head{background:#fff;padding:15px 20px;border-bottom:2px solid var(--p307-gray)}
.saw-section-title{font-family:'Oswald',sans-serif;font-size:18px;font-weight:700;text-transform:uppercase;color:var(--p307-primary);margin:0;display:flex;justify-content:space-between;align-items:center}
.saw-section-body{padding:20px}
.saw-info-grid{display:grid;grid-template-columns:1fr;gap:16px}
@media (min-width:400px){.saw-info-grid{grid-template-columns:1fr 1fr}}
.saw-info-item label{display:block;font-size:11px;text-transform:uppercase;color:#888;font-weight:700;margin-bottom:4px}
.saw-info-item span{font-size:14px;font-weight:500;color:var(--p307-dark)}
.saw-link{color:var(--p307-accent);text-decoration:none;font-weight:600}
.saw-link:hover{text-decoration:underline}
.saw-visit-agenda-card{background:#fff;border:1px solid var(--p307-border);margin-bottom:12px;display:flex;flex-direction:column;transition:all .2s ease;border-left:5px solid transparent}
.saw-visit-agenda-card:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.08)}
.status-draft{border-left-color:var(--status-draft)!important}
.status-pending{border-left-color:var(--status-pending)!important}
.status-confirmed{border-left-color:var(--status-confirmed)!important}
.status-in-progress{border-left-color:var(--status-in-progress)!important}
.status-completed{border-left-color:var(--status-completed)!important}
.status-cancelled{border-left-color:var(--status-cancelled)!important}
.saw-visit-trigger{display:flex;cursor:pointer;min-height:70px}
.saw-visit-date-box{background:var(--p307-gray);width:70px;display:flex;flex-direction:column;align-items:center;justify-content:center;border-right:1px solid var(--p307-border);padding:10px;flex-shrink:0}
.saw-date-day{font-family:'Oswald',sans-serif;font-size:22px;font-weight:700;color:var(--p307-primary);line-height:1}
.saw-date-month{font-size:11px;text-transform:uppercase;color:#666;margin-top:2px}
.saw-visit-info-box{flex:1;padding:12px 16px;display:flex;flex-direction:column;justify-content:center}
.saw-visit-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.saw-visit-id{font-size:11px;color:#999}
.saw-visit-badge{font-size:10px;text-transform:uppercase;font-weight:700;padding:2px 6px;border-radius:2px;background:#eee;color:#555}
.saw-visit-main-title{font-family:'Roboto',sans-serif;font-weight:700;font-size:15px;color:var(--p307-dark);display:flex;align-items:center;gap:8px}
.saw-toggle-arrow{padding:0 15px;display:flex;align-items:center;color:var(--p307-accent);font-size:14px;transition:transform .3s}
.saw-toggle-arrow.expanded{transform:rotate(90deg)}
.saw-visitors-wrap{background:#fcfcfc;border-top:1px solid var(--p307-border);padding:15px;display:none}
.saw-visitors-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--p307-border)}
.saw-visitors-title{font-size:12px;font-weight:700;color:#888;text-transform:uppercase}
.saw-detail-visit-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:var(--p307-accent);color:#fff;border:none;border-radius:3px;font-size:12px;font-weight:600;text-decoration:none;text-transform:uppercase;transition:all .3s ease;cursor:pointer}
.saw-detail-visit-btn:hover{background:var(--p307-primary);color:#fff;transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,119,181,.3)}
.saw-visitor-row{display:flex;align-items:center;background:#fff;border:1px solid var(--p307-border);padding:10px;border-radius:4px;margin-bottom:8px;text-decoration:none;color:inherit;transition:all .2s}
.saw-visitor-row:hover{background:#f0f8ff;border-color:var(--p307-accent)}
.saw-visitor-row.not-attended{background:#f5f5f5;opacity:.6;border-color:#d0d0d0}
.saw-visitor-row.not-attended:hover{background:#ececec;border-color:#b0b0b0;opacity:.8}
.saw-visitor-row.not-attended .saw-v-info h5{color:#888}
.saw-v-avatar{width:40px;height:40px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;margin-right:12px;flex-shrink:0}
.saw-visitor-row.not-attended .saw-v-avatar{background:#95a5a6!important}
.saw-v-info h5{margin:0;font-size:14px;color:var(--p307-dark);display:flex;align-items:center;gap:6px}
.saw-v-info p{margin:2px 0 0 0;font-size:12px;color:#777}
.saw-not-attended-badge{display:inline-block;background:#95a5a6;color:#fff;font-size:9px;font-weight:700;text-transform:uppercase;padding:2px 6px;border-radius:3px;letter-spacing:.5px}
@media (max-width:480px){.saw-visit-trigger{flex-wrap:wrap}.saw-visit-date-box{width:60px}}

/* INLINE MERGE UI */
#sawMergeContainer{display:none;margin-top:16px;padding:0;background:#fff;border:2px solid var(--p307-accent);border-radius:8px;box-shadow:0 4px 12px rgba(0,119,181,0.15)}
#sawMergeContainer.active{display:block}
.saw-merge-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:linear-gradient(135deg,var(--p307-accent) 0%,var(--p307-primary) 100%);border-radius:6px 6px 0 0}
.saw-merge-header h4{margin:0;font-family:'Oswald',sans-serif;font-size:16px;color:#fff;text-transform:uppercase;letter-spacing:0.5px}
.saw-merge-close{background:rgba(255,255,255,0.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;font-size:18px;transition:all 0.3s}
.saw-merge-close:hover{background:rgba(255,255,255,0.3);transform:rotate(90deg)}
.saw-merge-body{padding:20px}
.saw-merge-tabs{display:flex;gap:8px;margin-bottom:16px;border-bottom:2px solid var(--p307-gray)}
.saw-merge-tab{padding:10px 20px;background:none;border:none;color:#666;font-weight:600;font-size:13px;cursor:pointer;position:relative;transition:all 0.3s}
.saw-merge-tab:hover{color:var(--p307-accent)}
.saw-merge-tab.active{color:var(--p307-accent)}
.saw-merge-tab.active::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:var(--p307-accent)}
.saw-merge-content{display:none}
.saw-merge-content.active{display:block}
.saw-help-text{background:#fff9e6;border-left:3px solid #ffc107;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#666;border-radius:4px}
.saw-merge-warning{background:#fff3e0;border:2px solid #ffb74d;border-radius:6px;padding:12px;margin-bottom:16px;display:flex;gap:10px;font-size:12px}
.saw-merge-warning strong{display:block;color:#e65100;margin-bottom:4px;font-size:13px}
.saw-duplicate-list{display:flex;flex-direction:column;gap:10px;margin-bottom:16px;max-height:350px;overflow-y:auto;padding-right:4px}
.saw-duplicate-item{display:flex;align-items:center;gap:10px;padding:12px;border:2px solid var(--p307-border);border-radius:6px;background:#fff;cursor:pointer;transition:all 0.2s}
.saw-duplicate-item:hover{border-color:var(--p307-accent);background:#f0f8ff;transform:translateX(2px)}
.saw-duplicate-item input[type="checkbox"]{width:18px;height:18px;cursor:pointer;flex-shrink:0;accent-color:var(--p307-accent)}
.saw-dup-info{flex:1}
.saw-dup-info strong{font-size:14px;color:var(--p307-dark);display:block;margin-bottom:4px}
.saw-dup-meta{display:flex;gap:8px;flex-wrap:wrap;font-size:11px}
.saw-similarity-badge{background:#e8f5e9;color:#2e7d32;padding:3px 8px;border-radius:4px;font-weight:700}
.saw-visit-count{background:var(--p307-gray);color:#666;padding:3px 8px;border-radius:4px;font-weight:600}
.saw-merge-actions{display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:2px solid var(--p307-gray)}
.saw-btn{padding:10px 20px;border:none;border-radius:6px;font-size:12px;font-weight:700;text-transform:uppercase;cursor:pointer;transition:all 0.3s}
.saw-btn-primary{background:var(--p307-accent);color:#fff}
.saw-btn-primary:hover{background:var(--p307-primary);transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,119,181,0.3)}
.saw-btn-primary:disabled{background:#ccc;cursor:not-allowed;transform:none}
.saw-no-duplicates{text-align:center;padding:30px 20px;color:#28a745;font-size:15px;font-weight:600}
.saw-manual-search{margin-bottom:12px}
.saw-manual-search input{width:100%;padding:10px 12px;border:2px solid var(--p307-border);border-radius:6px;font-size:14px}
.saw-manual-search input:focus{outline:none;border-color:var(--p307-accent)}

/* ELEGANT ALERTS */
.saw-elegant-alert{position:fixed;top:80px;right:20px;z-index:999999;background:#fff;padding:20px 24px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.15);display:flex;align-items:center;gap:16px;min-width:320px;max-width:400px;animation:slideInRight 0.4s cubic-bezier(0.68,-0.55,0.265,1.55)}
@keyframes slideInRight{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}
.saw-alert-icon{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.saw-alert-success .saw-alert-icon{background:#e8f5e9;color:#2e7d32}
.saw-alert-error .saw-alert-icon{background:#ffebee;color:#c62828}
.saw-alert-content{flex:1}
.saw-alert-content strong{display:block;font-size:15px;margin-bottom:4px;color:#1a1a1a}
.saw-alert-content p{margin:0;font-size:13px;color:#666}
.saw-alert-close{background:none;border:none;color:#999;cursor:pointer;padding:4px;font-size:20px;line-height:1}
.saw-alert-close:hover{color:#333}
</style>

<div class="saw-detail-sidebar-content">
    <div class="saw-industrial-header">
        <div class="saw-header-inner">
            <h3><?php echo esc_html($item['name']); ?></h3>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <?php if (!empty($item['ico'])): ?>
                    <span class="saw-badge-transparent">IČO: <?php echo esc_html($item['ico']); ?></span>
                <?php endif; ?>
                <?php if (!empty($item['is_archived'])): ?>
                    <span class="saw-badge-transparent" style="background: rgba(0,0,0,0.4);">Archivováno</span>
                <?php else: ?>
                    <span class="saw-badge-transparent">✓ Aktivní</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="saw-industrial-stripe"></div>
    </div>
    
    <div class="saw-industrial-section" style="background: #fff9e6; border-left: 4px solid #ffc107;">
        <div class="saw-section-body" style="padding: 16px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <strong style="color: #f57f17; font-size: 14px;">⚠️ Možné duplicity</strong>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #666;">Zkontrolujte, zda neexistují podobné firmy</p>
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
                            <div style="text-align:center;padding:20px;color:#999">⏳ Načítání...</div>
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
                                        <span style="color:#999;font-size:11px">IČO: <?php echo esc_html($company['ico']); ?></span>
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
        <div class="saw-section-head"><h4 class="saw-section-title">📍 Adresa sídla</h4></div>
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
        <div class="saw-section-head"><h4 class="saw-section-title">📞 Kontakt</h4></div>
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
        <div class="saw-section-head"><h4 class="saw-section-title">ℹ️ Info</h4></div>
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
            <h4 class="saw-section-title">📋 Návštěvy <span style="background:var(--p307-accent);color:#fff;padding:2px 8px;border-radius:10px;font-size:12px"><?php echo count($visits); ?></span></h4>
        </div>
        <div class="saw-section-body" style="background: #fafafa;">
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
                        <div class="saw-visit-main-title"><?php echo esc_html($status_labels[$visit['status']] ?? $visit['status']); ?> <span style="font-weight:400;color:#888;font-size:13px;margin-left:auto">👥 <?php echo intval($visit['visitor_count']); ?></span></div>
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
                        <div class="saw-v-avatar" style="background:<?php echo $avatar_color; ?>"><?php echo esc_html($initials); ?></div>
                        <div class="saw-v-info">
                            <h5><?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?><?php if ($did_not_attend): ?> <span class="saw-not-attended-badge">Nezúčastnil se</span><?php endif; ?></h5>
                            <?php if(!empty($visitor['position'])): ?><p><?php echo esc_html($visitor['position']); ?></p><?php endif; ?>
                            <?php if(!empty($visitor['email']) || !empty($visitor['phone'])): ?>
                                <p style="font-size:11px;color:#999"><?php if(!empty($visitor['email'])): ?>📧 <?php echo esc_html($visitor['email']); ?><?php endif; ?><?php if(!empty($visitor['phone'])): ?><?php if(!empty($visitor['email'])) echo ' • '; ?>📱 <?php echo esc_html($visitor['phone']); ?><?php endif; ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; else: ?><p style="text-align:center;color:#999;font-style:italic;margin:0">Žádní návštěvníci</p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const companyId = <?php echo intval($item['id']); ?>;
let currentTab = 'auto';

function toggleVisit(visitId) {
    const content = document.getElementById('visit-' + visitId);
    const icon = document.getElementById('icon-' + visitId);
    if (content.style.display === 'block') {
        content.style.display = 'none';
        icon.classList.remove('expanded');
    } else {
        content.style.display = 'block';
        icon.classList.add('expanded');
    }
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.saw-merge-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.saw-merge-content').forEach(c => c.classList.remove('active'));
    
    if (tab === 'auto') {
        document.querySelector('.saw-merge-tab:first-child').classList.add('active');
        document.getElementById('sawMergeAuto').classList.add('active');
    } else {
        document.querySelector('.saw-merge-tab:last-child').classList.add('active');
        document.getElementById('sawMergeManual').classList.add('active');
    }
}

function filterManualList() {
    const search = document.getElementById('sawManualSearch').value.toLowerCase();
    document.querySelectorAll('#sawManualList .saw-duplicate-item').forEach(item => {
        const name = item.getAttribute('data-name');
        item.style.display = name.includes(search) ? 'flex' : 'none';
    });
}

document.getElementById('sawMergeBtn').addEventListener('click', function() {
    const container = document.getElementById('sawMergeContainer');
    container.classList.add('active');
    
    if (currentTab === 'auto') {
        const content = document.getElementById('sawMergeAutoContent');
        content.innerHTML = '<div style="text-align:center;padding:20px;color:#999">⏳ Načítání...</div>';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'saw_show_merge_modal',
                nonce: '<?php echo wp_create_nonce('saw_admin_nonce'); ?>',
                id: companyId
            })
        })
        .then(r => r.text())
        .then(html => {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const body = temp.querySelector('.saw-modal-body');
            content.innerHTML = body ? body.innerHTML : html;
        })
        .catch(e => {
            content.innerHTML = '<div style="color:#dc3545;padding:20px">❌ ' + e.message + '</div>';
        });
    }
});

function closeMerge() {
    document.getElementById('sawMergeContainer').classList.remove('active');
}

function updateMergeButton() {
    const selector = currentTab === 'auto' ? 'input[name="duplicate_ids[]"]:checked' : 'input[name="manual_ids[]"]:checked';
    const selected = document.querySelectorAll(selector);
    const button = document.getElementById('sawMergeButton');
    if (button) {
        button.disabled = selected.length === 0;
        button.textContent = selected.length > 0 ? `Sloučit ${selected.length}` : 'Sloučit vybrané';
    }
}

function confirmMerge() {
    const selector = currentTab === 'auto' ? 'input[name="duplicate_ids[]"]:checked' : 'input[name="manual_ids[]"]:checked';
    const selected = document.querySelectorAll(selector);
    
    if (selected.length === 0) return;
    
    const count = selected.length;
    if (!confirm(`Sloučit ${count} ${count === 1 ? 'firmu' : count < 5 ? 'firmy' : 'firem'}?\n\nTATO AKCE JE NEVRATNÁ!`)) return;
    
    const button = document.getElementById('sawMergeButton');
    button.disabled = true;
    button.textContent = 'Slučuji...';
    
    const duplicateIds = Array.from(selected).map(cb => cb.value);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'saw_merge_companies',
            nonce: '<?php echo wp_create_nonce('saw_admin_nonce'); ?>',
            master_id: companyId,
            duplicate_ids: JSON.stringify(duplicateIds)
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showElegantAlert(true, 'Úspěšně sloučeno!', data.data.message);
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showElegantAlert(false, 'Chyba při sloučení', data.data.message || 'Neznámá chyba');
            button.disabled = false;
            button.textContent = `Sloučit ${count}`;
        }
    })
    .catch(e => {
        showElegantAlert(false, 'Chyba připojení', e.message);
        button.disabled = false;
    });
}

function showElegantAlert(isSuccess, title, message) {
    const alert = document.createElement('div');
    alert.className = `saw-elegant-alert ${isSuccess ? 'saw-alert-success' : 'saw-alert-error'}`;
    alert.innerHTML = `
        <div class="saw-alert-icon">${isSuccess ? '✓' : '✕'}</div>
        <div class="saw-alert-content">
            <strong>${title}</strong>
            <p>${message}</p>
        </div>
        <button class="saw-alert-close" onclick="this.parentElement.remove()">×</button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}
</script>