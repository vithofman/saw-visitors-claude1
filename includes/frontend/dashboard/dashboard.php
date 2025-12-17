<?php
/**
 * Frontend Dashboard Page v8.0.0
 * 
 * Modern design with full-color background
 * Hero section touches edges (no padding top/sides)
 * 
 * @package SAW_Visitors
 * @version 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Frontend_Dashboard {
    
    const VERSION = '8.0.0';
    
    public static function init() {}
    
    public static function enqueue_assets() {
        wp_enqueue_script(
            'saw-dashboard',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/dashboard/dashboard.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        wp_localize_script('saw-dashboard', 'sawDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce')
        ));
    }
    
    public static function render_dashboard() {
        global $wpdb;
        
        $branch_id = SAW_Context::get_branch_id();
        $customer_id = SAW_Context::get_customer_id();
        $current_user = wp_get_current_user();
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/widgets/visitors/current-visitors/widget-current-visitors.php';
        
        self::enqueue_assets();
        SAW_Widget_Current_Visitors::enqueue_assets();
        
        $user_data = array(
            'id' => $current_user->ID,
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'role' => 'admin',
        );
        
        $customer_data = array('id' => 0, 'name' => 'Žádný zákazník', 'logo_url' => '');
        
        if ($customer_id) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, ico, logo_url FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                $customer_data = array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'logo_url' => $customer['logo_url'] ?? '',
                );
            }
        }
        
        $branch_name = '';
        if ($branch_id) {
            $branch_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                $branch_id
            ));
        }
        
        // Initialize
        $stats = array('present' => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'year' => 0);
        $present_visitors = array();
        $planned_visits = array();
        $recent_activity = array();
        $top_visitors = array();
        $top_companies = array();
        $longest_visits = array();
        $avg_duration = 0;
        $training_stats = array('completed' => 0, 'pending' => 0, 'skipped' => 0);
        $hourly_data = array();
        $chart_labels = array();
        $chart_values = array();
        
        if ($branch_id) {
            // STATS
            $stats['present'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.checked_in_at IS NOT NULL AND dl.checked_out_at IS NULL",
                $branch_id
            ));
            
            $stats['today'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.log_date = CURDATE()",
                $branch_id
            ));
            
            $stats['week'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND YEARWEEK(dl.log_date, 1) = YEARWEEK(CURDATE(), 1)",
                $branch_id
            ));
            
            $stats['month'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND MONTH(dl.log_date) = MONTH(CURDATE()) AND YEAR(dl.log_date) = YEAR(CURDATE())",
                $branch_id
            ));
            
            $stats['year'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND YEAR(dl.log_date) = YEAR(CURDATE())",
                $branch_id
            ));
            
            $avg_duration = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, dl.checked_in_at, dl.checked_out_at))
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND dl.checked_out_at IS NOT NULL",
                $branch_id
            ));
            
            // PRESENT VISITORS
            $present_visitors = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    vis.id as visitor_id, vis.first_name, vis.last_name, vis.phone, vis.position,
                    v.id as visit_id, v.company_id, c.name as company_name,
                    dl.checked_in_at, dl.log_date,
                    TIMESTAMPDIFF(MINUTE, dl.checked_in_at, NOW()) as minutes_inside
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visitors vis ON dl.visitor_id = vis.id
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d AND dl.checked_in_at IS NOT NULL AND dl.checked_out_at IS NULL
                 ORDER BY dl.checked_in_at DESC LIMIT 15",
                $branch_id
            ), ARRAY_A);
            
            // PLANNED VISITS
            $planned_visits = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    v.id, v.status, v.company_id, v.planned_date_from, v.planned_date_to,
                    c.name as company_name,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors vis WHERE vis.visit_id = v.id) as visitor_count,
                    (SELECT CONCAT(vis2.first_name, ' ', vis2.last_name) 
                     FROM {$wpdb->prefix}saw_visitors vis2 WHERE vis2.visit_id = v.id ORDER BY vis2.id ASC LIMIT 1) as first_visitor_name
                 FROM {$wpdb->prefix}saw_visits v
                 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d 
                 AND v.status IN ('pending', 'confirmed')
                 AND (v.planned_date_to >= CURDATE() OR v.planned_date_from >= CURDATE())
                 ORDER BY v.planned_date_from ASC LIMIT 8",
                $branch_id
            ), ARRAY_A);
            
            // RECENT ACTIVITY
            $recent_activity = $wpdb->get_results($wpdb->prepare(
                "SELECT dl.id, dl.log_date, dl.checked_in_at, dl.checked_out_at,
                        vis.first_name, vis.last_name, c.name as company_name, v.company_id
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visitors vis ON dl.visitor_id = vis.id
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d
                 ORDER BY COALESCE(dl.checked_out_at, dl.checked_in_at) DESC LIMIT 10",
                $branch_id
            ), ARRAY_A);
            
            // 7-DAY CHART
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $chart_labels[] = date_i18n('D', strtotime($date));
                
                $count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT dl.visitor_id)
                     FROM {$wpdb->prefix}saw_visit_daily_logs dl
                     INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                     WHERE v.branch_id = %d AND dl.log_date = %s",
                    $branch_id, $date
                ));
                
                $chart_values[] = $count;
            }
            
            // HOURLY CHART
            $hourly_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT HOUR(dl.checked_in_at) as hour, COUNT(*) as count
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.log_date = CURDATE()
                 GROUP BY HOUR(dl.checked_in_at) ORDER BY hour",
                $branch_id
            ), ARRAY_A);
            
            for ($h = 0; $h <= 23; $h++) { $hourly_data[$h] = 0; }
            foreach ($hourly_raw as $row) {
                $h = (int) $row['hour'];
                if ($h >= 0 && $h <= 23) { $hourly_data[$h] = (int) $row['count']; }
            }
            
            // TRAINING STATS
            $training_stats['completed'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors vis
                 INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
                 WHERE v.branch_id = %d AND vis.training_status = 'completed' AND vis.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                $branch_id
            ));
            
            $training_stats['pending'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors vis
                 INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
                 WHERE v.branch_id = %d AND vis.training_status IN ('pending', 'in_progress') AND vis.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                $branch_id
            ));
            
            $training_stats['skipped'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors vis
                 INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
                 WHERE v.branch_id = %d AND vis.training_status IN ('skipped', 'not_available') AND vis.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                $branch_id
            ));
            
            // TOP VISITORS
            $top_visitors = $wpdb->get_results($wpdb->prepare(
                "SELECT vis.id, vis.first_name, vis.last_name, vis.position, c.name as company_name, v.company_id, COUNT(dl.id) as visit_count
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visitors vis ON dl.visitor_id = vis.id
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY vis.id ORDER BY visit_count DESC LIMIT 5",
                $branch_id
            ), ARRAY_A);
            
            // TOP COMPANIES
            $top_companies = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id, c.name, COUNT(DISTINCT dl.visitor_id) as visitor_count, COUNT(dl.id) as visit_count
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 INNER JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY c.id ORDER BY visit_count DESC LIMIT 5",
                $branch_id
            ), ARRAY_A);
            
            // LONGEST VISITS
            $longest_visits = $wpdb->get_results($wpdb->prepare(
                "SELECT vis.first_name, vis.last_name, vis.position, c.name as company_name, v.company_id,
                        TIMESTAMPDIFF(MINUTE, dl.checked_in_at, dl.checked_out_at) as duration_min,
                        dl.log_date
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visitors vis ON dl.visitor_id = vis.id
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND dl.checked_out_at IS NOT NULL
                 ORDER BY duration_min DESC LIMIT 5",
                $branch_id
            ), ARRAY_A);
        }
        
        // Training percentages
        $training_total = $training_stats['completed'] + $training_stats['pending'] + $training_stats['skipped'];
        $pct_completed = $training_total > 0 ? round(($training_stats['completed'] / $training_total) * 100) : 0;
        $pct_pending = $training_total > 0 ? round(($training_stats['pending'] / $training_total) * 100) : 0;
        
        // Greeting
        $hour = (int) current_time('G');
        if ($hour >= 5 && $hour < 12) $greeting = 'Dobré ráno';
        elseif ($hour >= 12 && $hour < 18) $greeting = 'Dobré odpoledne';
        else $greeting = 'Dobrý večer';
        
        ob_start();
        ?>
        <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>includes/frontend/dashboard/dashboard.css?v=<?php echo self::VERSION; ?>&t=<?php echo time(); ?>">
        
        <div class="saw-dash">
            <div class="saw-dash-scroll">
                
                <!-- HERO - Edge to edge -->
                <div class="saw-hero">
                    <div class="saw-hero-content">
                        <div class="saw-hero-header">
                            <div class="saw-hero-text">
                                <h1><?php echo $greeting; ?>, <?php echo esc_html($current_user->display_name); ?>! 👋</h1>
                                <p><?php echo date_i18n('l, d. F Y'); ?><?php if ($customer_data['name'] !== 'Žádný zákazník'): ?> · <?php echo esc_html($customer_data['name']); ?><?php endif; ?></p>
                            </div>
                            <div class="saw-hero-actions">
                                <a href="<?php echo home_url('/admin/visits/create'); ?>" class="saw-btn-new">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    <span>Nová návštěva</span>
                                </a>
                                <?php if ($branch_id && $stats['present'] > 0): ?>
                                <button type="button" class="saw-emergency" onclick="printEmergency()">
                                    <span class="saw-em-count"><?php echo $stats['present']; ?></span>
                                    <span class="saw-em-text">Evakuace</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($branch_id): ?>
                        <div class="saw-hero-stats">
                            <div class="saw-hero-stat saw-hero-stat--highlight">
                                <?php if ($stats['present'] > 0): ?><span class="saw-live" id="sawLiveBadge">LIVE</span><?php endif; ?>
                                <span class="saw-hero-stat-icon">🔥</span>
                                <span class="saw-hero-stat-value" id="sawPresentCount"><?php echo $stats['present']; ?></span>
                                <span class="saw-hero-stat-label">Uvnitř</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">📅</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['today']; ?></span>
                                <span class="saw-hero-stat-label">Dnes</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">📊</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['week']; ?></span>
                                <span class="saw-hero-stat-label">Týden</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">📈</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['month']; ?></span>
                                <span class="saw-hero-stat-label">Měsíc</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">🎯</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['year']; ?></span>
                                <span class="saw-hero-stat-label">Rok</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">⏱️</span>
                                <span class="saw-hero-stat-value"><?php echo $avg_duration ? self::format_duration($avg_duration) : '-'; ?></span>
                                <span class="saw-hero-stat-label">Ø Doba</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$branch_id): ?>
                <div class="saw-alert">⚠️ Vyberte pobočku pro zobrazení dat</div>
                <?php else: ?>
                
                <!-- CONTENT AREA - Cards -->
                <div class="saw-content-area">
                    
                    <!-- Section: Primary -->
                    <div class="saw-section-label"><span>Aktuální stav</span></div>
                    
                    <div class="saw-grid-3">
                        <!-- Present -->
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>🔥 Přítomní <?php if ($stats['present'] > 0): ?><span class="saw-badge" id="sawPresentBadge"><?php echo $stats['present']; ?></span><?php endif; ?></h3>
                                <button onclick="location.reload()" class="saw-refresh" title="Obnovit">↻</button>
                            </div>
                            <div class="saw-card-b saw-scroll" id="sawPresentList">
                                <?php if (empty($present_visitors)): ?>
                                <div class="saw-empty"><span class="saw-empty-icon">✅</span><p>Nikdo uvnitř</p></div>
                                <?php else: ?>
                                <?php foreach ($present_visitors as $v): 
                                    $overnight = $v['log_date'] !== date('Y-m-d');
                                    $dur = self::format_duration($v['minutes_inside']);
                                ?>
                                <div class="saw-person" data-visitor-id="<?php echo $v['visitor_id']; ?>" id="sawPerson-<?php echo $v['visitor_id']; ?>">
                                    <a href="<?php echo home_url('/admin/visitors/' . $v['visitor_id']); ?>" class="saw-person-link">
                                        <span class="saw-avatar"><?php echo mb_strtoupper(mb_substr($v['first_name'], 0, 1) . mb_substr($v['last_name'], 0, 1)); ?></span>
                                        <span class="saw-person-info">
                                            <strong><?php echo esc_html($v['first_name'] . ' ' . $v['last_name']); ?></strong>
                                            <em><?php if (!empty($v['position'])): ?><?php echo esc_html($v['position']); ?> · <?php endif; ?><?php echo $v['company_id'] ? esc_html($v['company_name']) : 'Fyz. osoba'; ?></em>
                                        </span>
                                    </a>
                                    <span class="saw-time <?php echo $overnight ? 'overnight' : ''; ?>"><?php if ($overnight): ?>🌙 <?php endif; ?><?php echo $dur; ?></span>
                                    <button type="button" class="saw-checkout saw-checkout-btn" data-visitor-id="<?php echo $v['visitor_id']; ?>" title="Check-out">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        <span class="saw-checkout-text">Out</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Planned -->
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>📆 Plánované</h3>
                                <a href="<?php echo home_url('/admin/visits'); ?>">Vše →</a>
                            </div>
                            <div class="saw-card-b saw-scroll">
                                <?php if (empty($planned_visits)): ?>
                                <div class="saw-empty"><span class="saw-empty-icon">📭</span><p>Žádné plánované</p></div>
                                <?php else: ?>
                                <?php foreach ($planned_visits as $pv): 
                                    $nm = empty($pv['company_id']) ? ($pv['first_visitor_name'] ?: 'Fyzická osoba') : $pv['company_name'];
                                    $dt = self::format_date_range($pv['planned_date_from'], $pv['planned_date_to']);
                                    $is_today = $pv['planned_date_from'] && date('Y-m-d', strtotime($pv['planned_date_from'])) === date('Y-m-d');
                                ?>
                                <a href="<?php echo home_url('/admin/visits/' . $pv['id']); ?>" class="saw-item">
                                    <span class="saw-item-icon"><?php echo empty($pv['company_id']) ? '👤' : '🏢'; ?></span>
                                    <span class="saw-item-text">
                                        <strong><?php echo esc_html($nm); ?></strong>
                                        <em><?php echo $dt; ?> · <?php echo $pv['visitor_count']; ?> os.</em>
                                    </span>
                                    <span class="saw-pill <?php echo $pv['status']; ?> <?php echo $is_today ? 'today' : ''; ?>"><?php echo $pv['status'] === 'confirmed' ? '✓' : '…'; ?></span>
                                </a>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Activity -->
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>🕐 Aktivita</h3></div>
                            <div class="saw-card-b saw-scroll">
                                <?php if (empty($recent_activity)): ?>
                                <div class="saw-empty"><span class="saw-empty-icon">📝</span><p>Žádná aktivita</p></div>
                                <?php else: ?>
                                <?php foreach ($recent_activity as $a): 
                                    $is_out = !empty($a['checked_out_at']);
                                    $time = $is_out ? date_i18n('H:i', strtotime($a['checked_out_at'])) : date_i18n('H:i', strtotime($a['checked_in_at']));
                                ?>
                                <div class="saw-act">
                                    <span class="saw-act-icon <?php echo $is_out ? 'out' : 'in'; ?>"><?php echo $is_out ? '←' : '→'; ?></span>
                                    <span class="saw-act-text"><strong><?php echo esc_html($a['first_name'] . ' ' . $a['last_name']); ?></strong> <?php echo $is_out ? 'odešel' : 'přišel'; ?></span>
                                    <span class="saw-act-time"><?php echo $time; ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section: Analytics -->
                    <div class="saw-section-label"><span>Statistiky</span></div>
                    
                    <div class="saw-grid-3">
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>📊 7 dní</h3></div>
                            <div class="saw-card-b"><div class="saw-chart"><canvas id="chart-week"></canvas></div></div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>⏰ Příchody</h3></div>
                            <div class="saw-card-b"><div class="saw-chart"><canvas id="chart-hour"></canvas></div></div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>🎓 Školení</h3><span class="saw-tag">30 dní</span></div>
                            <div class="saw-card-b">
                                <div class="saw-training">
                                    <div class="saw-ring" style="--c:<?php echo $pct_completed; ?>;--p:<?php echo $pct_pending; ?>"><span><?php echo $training_total; ?></span></div>
                                    <div class="saw-legend">
                                        <div class="saw-leg"><span class="ok"></span>Hotovo <b><?php echo $training_stats['completed']; ?></b></div>
                                        <div class="saw-leg"><span class="wait"></span>Čeká <b><?php echo $training_stats['pending']; ?></b></div>
                                        <div class="saw-leg"><span class="skip"></span>Přeskočeno <b><?php echo $training_stats['skipped']; ?></b></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section: Rankings -->
                    <div class="saw-section-label"><span>Žebříčky</span></div>
                    
                    <div class="saw-grid-3">
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>⭐ Top návštěvníci</h3><span class="saw-tag">30 dní</span></div>
                            <div class="saw-card-b">
                                <?php if (empty($top_visitors)): ?><div class="saw-empty-sm">Žádná data</div>
                                <?php else: ?>
                                <?php $r = 1; foreach ($top_visitors as $tv): ?>
                                <div class="saw-rank-item">
                                    <span class="saw-rank r<?php echo $r; ?>"><?php echo $r; ?></span>
                                    <span class="saw-rank-text">
                                        <strong><?php echo esc_html($tv['first_name'] . ' ' . $tv['last_name']); ?></strong>
                                        <em><?php echo $tv['company_id'] ? esc_html($tv['company_name']) : 'Fyz. osoba'; ?></em>
                                    </span>
                                    <span class="saw-rank-count"><?php echo $tv['visit_count']; ?>×</span>
                                </div>
                                <?php $r++; endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>🏆 Top firmy</h3><span class="saw-tag">30 dní</span></div>
                            <div class="saw-card-b">
                                <?php if (empty($top_companies)): ?><div class="saw-empty-sm">Žádná data</div>
                                <?php else: ?>
                                <?php $r = 1; foreach ($top_companies as $tc): ?>
                                <a href="<?php echo home_url('/admin/companies/' . $tc['id']); ?>" class="saw-rank-item saw-rank-link">
                                    <span class="saw-rank r<?php echo $r; ?>"><?php echo $r; ?></span>
                                    <span class="saw-rank-text"><strong><?php echo esc_html($tc['name']); ?></strong><em><?php echo $tc['visitor_count']; ?> osob</em></span>
                                    <span class="saw-rank-count"><?php echo $tc['visit_count']; ?>×</span>
                                </a>
                                <?php $r++; endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h"><h3>⏳ Nejdelší</h3><span class="saw-tag">7 dní</span></div>
                            <div class="saw-card-b">
                                <?php if (empty($longest_visits)): ?><div class="saw-empty-sm">Žádná data</div>
                                <?php else: ?>
                                <?php $r = 1; foreach ($longest_visits as $lv): ?>
                                <div class="saw-rank-item">
                                    <span class="saw-rank r<?php echo $r; ?>"><?php echo $r; ?></span>
                                    <span class="saw-rank-text">
                                        <strong><?php echo esc_html($lv['first_name'] . ' ' . $lv['last_name']); ?></strong>
                                        <em><?php echo date_i18n('d.m.', strtotime($lv['log_date'])); ?></em>
                                    </span>
                                    <span class="saw-rank-dur"><?php echo self::format_duration($lv['duration_min']); ?></span>
                                </div>
                                <?php $r++; endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <?php endif; ?>
                
            </div>
        </div>
        
        <!-- PRINT -->
        <div id="print-tpl" style="display:none">
            <div class="p-page">
                <div class="p-head">
                    <h1>EVAKUAČNÍ SEZNAM</h1>
                    <p><?php echo esc_html($customer_data['name']); ?><?php if ($branch_name): ?> · <?php echo esc_html($branch_name); ?><?php endif; ?></p>
                    <p class="p-time">Vytištěno: <?php echo date_i18n('d.m.Y H:i:s'); ?></p>
                </div>
                <div class="p-count"><?php echo count($present_visitors); ?> <?php echo self::person_label(count($present_visitors)); ?> uvnitř</div>
                <table><thead><tr><th>#</th><th>Jméno</th><th>Firma</th><th>Příchod</th><th>Telefon</th><th>✓</th></tr></thead><tbody>
                <?php $i = 1; foreach ($present_visitors as $pv): ?>
                <tr><td><?php echo $i++; ?></td><td><b><?php echo esc_html($pv['first_name'] . ' ' . $pv['last_name']); ?></b></td><td><?php echo $pv['company_id'] ? esc_html($pv['company_name']) : 'Fyz.'; ?></td><td><?php echo date_i18n('H:i', strtotime($pv['checked_in_at'])); ?></td><td><?php echo esc_html($pv['phone'] ?: '—'); ?></td><td class="chk">☐</td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <div class="p-foot"><div><p>Podpis:</p><div class="line"></div></div><div><p>Poznámky:</p><div class="line"></div></div></div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark' || document.body.getAttribute('data-theme') === 'dark';
            
            var colors = {
                bar: isDark ? 'rgba(59, 158, 202, 0.85)' : 'rgba(0, 90, 140, 0.85)',
                line: isDark ? '#34d399' : '#10b981',
                lineBg: isDark ? 'rgba(52, 211, 153, 0.15)' : 'rgba(16, 185, 129, 0.1)',
                grid: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 90, 140, 0.08)',
                tick: isDark ? '#94a3b8' : '#64748b'
            };
            
            var w = document.getElementById('chart-week');
            if (w) new Chart(w, {
                type: 'bar',
                data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ data: <?php echo json_encode($chart_values); ?>, backgroundColor: colors.bar, borderRadius: 6, borderSkipped: false }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, color: colors.tick }, grid: { color: colors.grid }, border: { display: false } }, x: { ticks: { color: colors.tick }, grid: { display: false }, border: { display: false } } } }
            });
            
            var h = document.getElementById('chart-hour');
            if (h) {
                var hL = [], hV = [];
                <?php foreach ($hourly_data as $hr => $cnt): ?>hL.push('<?php echo $hr; ?>h'); hV.push(<?php echo $cnt; ?>);<?php endforeach; ?>
                new Chart(h, {
                    type: 'line',
                    data: { labels: hL, datasets: [{ data: hV, borderColor: colors.line, backgroundColor: colors.lineBg, borderWidth: 2, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 4 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, interaction: { intersect: false, mode: 'index' }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, color: colors.tick }, grid: { color: colors.grid }, border: { display: false } }, x: { ticks: { color: colors.tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }, grid: { display: false }, border: { display: false } } } }
                });
            }
            
            // Checkout
            document.querySelectorAll('.saw-checkout-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var visitorId = this.getAttribute('data-visitor-id');
                    var personEl = document.getElementById('sawPerson-' + visitorId);
                    var btnEl = this;
                    
                    if (!visitorId || !personEl) return;
                    if (!confirm('Odhlásit návštěvníka?')) return;
                    
                    var reason = prompt('Důvod (volitelné):') || 'Ruční odhlášení';
                    
                    btnEl.disabled = true;
                    personEl.style.opacity = '0.5';
                    
                    var nonce = (typeof sawDashboard !== 'undefined' && sawDashboard.nonce) ? sawDashboard.nonce : ((typeof sawGlobal !== 'undefined' && sawGlobal.nonce) ? sawGlobal.nonce : '');
                    var ajaxurl = (typeof sawDashboard !== 'undefined' && sawDashboard.ajaxurl) ? sawDashboard.ajaxurl : '/wp-admin/admin-ajax.php';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'saw_checkout', nonce: nonce, visitor_id: visitorId, log_date: new Date().toISOString().split('T')[0], manual: '1', reason: reason })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            personEl.style.transition = 'all 0.3s ease';
                            personEl.style.transform = 'translateX(100%)';
                            personEl.style.opacity = '0';
                            setTimeout(function() { personEl.remove(); updateCounts(-1); }, 300);
                        } else {
                            personEl.style.opacity = '1';
                            btnEl.disabled = false;
                            alert('Chyba: ' + (data.data?.message || 'Nepodařilo se odhlásit'));
                        }
                    })
                    .catch(function() {
                        personEl.style.opacity = '1';
                        btnEl.disabled = false;
                        alert('Chyba při odhlašování');
                    });
                });
            });
            
            function updateCounts(delta) {
                ['sawPresentCount', 'sawPresentBadge'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) {
                        var n = Math.max(0, (parseInt(el.textContent) || 0) + delta);
                        if (n > 0) el.textContent = n;
                        else if (id === 'sawPresentBadge') el.style.display = 'none';
                    }
                });
                var emCount = document.querySelector('.saw-em-count');
                if (emCount) {
                    var n = Math.max(0, (parseInt(emCount.textContent) || 0) + delta);
                    if (n > 0) emCount.textContent = n;
                    else document.querySelector('.saw-emergency')?.remove();
                }
                if (document.getElementById('sawPresentCount')?.textContent === '0') {
                    document.getElementById('sawLiveBadge')?.remove();
                    var list = document.getElementById('sawPresentList');
                    if (list && !list.querySelector('.saw-person')) {
                        list.innerHTML = '<div class="saw-empty"><span class="saw-empty-icon">✅</span><p>Nikdo uvnitř</p></div>';
                    }
                }
            }
        });
        
        function printEmergency() {
            var c = document.getElementById('print-tpl').innerHTML;
            var w = window.open('', '_blank', 'width=800,height=600');
            w.document.write('<!DOCTYPE html><html><head><title>Evakuace</title><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;padding:20px;font-size:12px}.p-page{max-width:700px;margin:0 auto}.p-head{text-align:center;padding-bottom:12px;border-bottom:2px solid #dc2626;margin-bottom:12px}.p-head h1{font-size:18px;color:#dc2626}.p-count{text-align:center;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:10px;margin-bottom:14px;font-weight:700;color:#dc2626}table{width:100%;border-collapse:collapse;margin-bottom:16px}th{background:#f1f5f9;padding:8px;text-align:left;border:1px solid #e2e8f0;font-size:11px}td{padding:8px;border:1px solid #e2e8f0;font-size:11px}tr:nth-child(even){background:#f8fafc}.chk{text-align:center;font-size:16px;width:30px}.p-foot{display:flex;gap:24px;padding-top:12px;border-top:1px solid #e2e8f0}.p-foot>div{flex:1}.p-foot p{font-size:10px;color:#64748b;margin-bottom:4px}.line{border-bottom:1px solid #94a3b8;height:24px}@media print{body{padding:0}}</style></head><body>'+c+'</body></html>');
            w.document.close();
            setTimeout(function() { w.print(); setTimeout(function() { if (!w.closed) w.close(); }, 500); }, 150);
        }
        </script>
        
        <?php
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, 'Dashboard', 'dashboard', $user_data, $customer_data);
        } else {
            echo $content;
        }
    }
    
    private static function format_duration($m) {
        $m = (int) $m;
        if ($m < 60) return $m . 'm';
        $h = floor($m / 60);
        if ($h < 24) return $h . 'h ' . ($m % 60) . 'm';
        return floor($h / 24) . 'd ' . ($h % 24) . 'h';
    }
    
    private static function format_date_range($f, $t) {
        if (empty($f) && empty($t)) return '-';
        $today = date('Y-m-d'); $tom = date('Y-m-d', strtotime('+1 day'));
        $fy = $f ? date('Y-m-d', strtotime($f)) : '';
        $ty = $t ? date('Y-m-d', strtotime($t)) : '';
        if ($fy === $ty || empty($ty)) {
            if ($fy === $today) return 'Dnes';
            if ($fy === $tom) return 'Zítra';
            return date_i18n('d.m', strtotime($f));
        }
        return date_i18n('d.m', strtotime($f)) . ' – ' . date_i18n('d.m', strtotime($t));
    }
    
    private static function person_label($c) {
        $c = (int) $c;
        if ($c === 1) return 'osoba';
        if ($c >= 2 && $c <= 4) return 'osoby';
        return 'osob';
    }
}

SAW_Frontend_Dashboard::init();