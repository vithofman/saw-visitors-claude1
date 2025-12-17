<?php
/**
 * Frontend Dashboard Page v7.0.0
 * 
 * Complete redesign with full-width layout and modern design
 * 
 * CHANGES in 7.0.0:
 * - FULL WIDTH: Dashboard always 100% width, even when empty
 * - HERO SECTION: Key stats prominently displayed in header
 * - VISUAL HIERARCHY: Clear separation between primary and secondary content
 * - MODERN DESIGN: Elegant gradients, refined shadows, smooth animations
 * - IMPROVED UX: Better organization of information
 * 
 * @package SAW_Visitors
 * @version 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Frontend_Dashboard {
    
    const VERSION = '7.0.0';
    
    /**
     * Initialize dashboard
     */
    public static function init() {}
    
    /**
     * Enqueue dashboard assets
     */
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
    
    /**
     * Render the dashboard
     */
    public static function render_dashboard() {
        global $wpdb;
        
        // Get context
        $branch_id = SAW_Context::get_branch_id();
        $customer_id = SAW_Context::get_customer_id();
        $current_user = wp_get_current_user();
        
        // Load dependencies
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/widgets/visitors/current-visitors/widget-current-visitors.php';
        
        // Enqueue assets
        self::enqueue_assets();
        SAW_Widget_Current_Visitors::enqueue_assets();
        
        // User data
        $user_data = array(
            'id' => $current_user->ID,
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'role' => 'admin',
        );
        
        // Customer data
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
        
        // Branch name
        $branch_name = '';
        if ($branch_id) {
            $branch_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                $branch_id
            ));
        }
        
        // Initialize data arrays
        $stats = array('present' => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'year' => 0);
        $present_visitors = array();
        $planned_visits = array();
        $recent_activity = array();
        $chart_data = array();
        $top_visitors = array();
        $top_companies = array();
        $longest_visits = array();
        $avg_duration = 0;
        $training_stats = array('completed' => 0, 'pending' => 0, 'skipped' => 0);
        $hourly_data = array();
        
        if ($branch_id) {
            // ========================================
            // STATISTICS
            // ========================================
            
            // Present visitors (currently inside)
            $stats['present'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.checked_in_at IS NOT NULL AND dl.checked_out_at IS NULL",
                $branch_id
            ));
            
            // Today's visitors
            $stats['today'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.log_date = CURDATE()",
                $branch_id
            ));
            
            // This week's visitors
            $stats['week'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND YEARWEEK(dl.log_date, 1) = YEARWEEK(CURDATE(), 1)",
                $branch_id
            ));
            
            // This month's visitors
            $stats['month'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND MONTH(dl.log_date) = MONTH(CURDATE()) AND YEAR(dl.log_date) = YEAR(CURDATE())",
                $branch_id
            ));
            
            // This year's visitors
            $stats['year'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT dl.visitor_id) 
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND YEAR(dl.log_date) = YEAR(CURDATE())",
                $branch_id
            ));
            
            // Average visit duration (last 7 days)
            $avg_duration = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, dl.checked_in_at, dl.checked_out_at))
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND dl.checked_out_at IS NOT NULL",
                $branch_id
            ));
            
            // ========================================
            // PRESENT VISITORS
            // ========================================
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
            
            // ========================================
            // PLANNED VISITS
            // ========================================
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
            
            // ========================================
            // RECENT ACTIVITY
            // ========================================
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
            
            // ========================================
            // 7-DAY CHART DATA
            // ========================================
            $chart_values = array();
            $chart_labels = array();
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $chart_labels[] = date_i18n('D', strtotime($date));
                
                $count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT dl.visitor_id)
                     FROM {$wpdb->prefix}saw_visit_daily_logs dl
                     INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                     WHERE v.branch_id = %d
                     AND (
                         dl.log_date = %s
                         OR
                         (
                             dl.log_date < %s
                             AND dl.checked_in_at IS NOT NULL
                             AND (
                                 dl.checked_out_at IS NULL
                                 OR DATE(dl.checked_out_at) >= %s
                             )
                         )
                     )",
                    $branch_id,
                    $date,
                    $date,
                    $date
                ));
                
                $chart_values[] = $count;
            }
            
            // ========================================
            // HOURLY CHART DATA (Full 24 hours)
            // ========================================
            $hourly_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT HOUR(dl.checked_in_at) as hour, COUNT(*) as count
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 WHERE v.branch_id = %d AND dl.log_date = CURDATE()
                 GROUP BY HOUR(dl.checked_in_at) ORDER BY hour",
                $branch_id
            ), ARRAY_A);
            
            for ($h = 0; $h <= 23; $h++) { 
                $hourly_data[$h] = 0; 
            }
            foreach ($hourly_raw as $row) {
                $h = (int) $row['hour'];
                if ($h >= 0 && $h <= 23) { 
                    $hourly_data[$h] = (int) $row['count']; 
                }
            }
            
            // ========================================
            // TRAINING STATS (Last 30 days)
            // ========================================
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
            
            // ========================================
            // TOP VISITORS (Last 30 days)
            // ========================================
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
            
            // ========================================
            // TOP COMPANIES (Last 30 days)
            // ========================================
            $top_companies = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id, c.name, COUNT(DISTINCT dl.visitor_id) as visitor_count, COUNT(dl.id) as visit_count
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 INNER JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY c.id ORDER BY visit_count DESC LIMIT 5",
                $branch_id
            ), ARRAY_A);
            
            // ========================================
            // LONGEST VISITS (Last 7 days)
            // ========================================
            $longest_visits = $wpdb->get_results($wpdb->prepare(
                "SELECT vis.first_name, vis.last_name, vis.position, c.name as company_name, v.company_id,
                        TIMESTAMPDIFF(MINUTE, dl.checked_in_at, dl.checked_out_at) as duration_min,
                        dl.log_date
                 FROM {$wpdb->prefix}saw_visit_daily_logs dl
                 INNER JOIN {$wpdb->prefix}saw_visitors vis ON dl.visitor_id = vis.id
                 INNER JOIN {$wpdb->prefix}saw_visits v ON dl.visit_id = v.id
                 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                 WHERE v.branch_id = %d AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                 AND dl.checked_out_at IS NOT NULL
                 ORDER BY duration_min DESC LIMIT 5",
                $branch_id
            ), ARRAY_A);
        }
        
        // Calculate training percentages
        $training_total = $training_stats['completed'] + $training_stats['pending'] + $training_stats['skipped'];
        $pct_completed = $training_total > 0 ? round(($training_stats['completed'] / $training_total) * 100) : 0;
        $pct_pending = $training_total > 0 ? round(($training_stats['pending'] / $training_total) * 100) : 0;
        
        // Get greeting based on time
        $hour = (int) current_time('G');
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Dobré ráno';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Dobré odpoledne';
        } else {
            $greeting = 'Dobrý večer';
        }
        
        // Start output buffering
        ob_start();
        ?>
        <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>includes/frontend/dashboard/dashboard.css?v=<?php echo self::VERSION; ?>&t=<?php echo time(); ?>">
        
        <div class="saw-dash">
            <!-- Scroll container - this is the scrollable area -->
            <div class="saw-dash-scroll">
                <div class="saw-dash-inner">
                
                <!-- ============================================
                     HERO SECTION - Welcome + Key Stats
                     ============================================ -->
                <div class="saw-hero">
                    <div class="saw-hero-content">
                        
                        <!-- Hero Header -->
                        <div class="saw-hero-header">
                            <div class="saw-hero-text">
                                <h1 class="saw-hero-title"><?php echo $greeting; ?>, <?php echo esc_html($current_user->display_name); ?>! 👋</h1>
                                <p class="saw-hero-subtitle">
                                    <?php echo date_i18n('l, d. F Y'); ?>
                                    <?php if ($customer_data['name'] !== 'Žádný zákazník'): ?>
                                        · <?php echo esc_html($customer_data['name']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <!-- Hero Actions -->
                            <div class="saw-hero-actions">
                                <a href="<?php echo home_url('/admin/visits/create'); ?>" class="saw-btn-new" title="Nová návštěva">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    <span>Nová návštěva</span>
                                </a>
                                
                                <?php if ($branch_id && $stats['present'] > 0): ?>
                                <button type="button" class="saw-emergency" onclick="printEmergency()">
                                    <span class="saw-em-count"><?php echo $stats['present']; ?></span>
                                    <span class="saw-em-text">Evakuační seznam</span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($branch_id): ?>
                        <!-- Hero Stats Grid -->
                        <div class="saw-hero-stats">
                            <div class="saw-hero-stat saw-hero-stat--highlight">
                                <?php if ($stats['present'] > 0): ?>
                                <span class="saw-live" id="sawLiveBadge">LIVE</span>
                                <?php endif; ?>
                                <span class="saw-hero-stat-icon">🔥</span>
                                <span class="saw-hero-stat-value" id="sawPresentCount"><?php echo $stats['present']; ?></span>
                                <span class="saw-hero-stat-label">Aktuálně uvnitř</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">📅</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['today']; ?></span>
                                <span class="saw-hero-stat-label">Dnes</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">📊</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['week']; ?></span>
                                <span class="saw-hero-stat-label">Tento týden</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">📈</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['month']; ?></span>
                                <span class="saw-hero-stat-label">Tento měsíc</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">🎯</span>
                                <span class="saw-hero-stat-value"><?php echo $stats['year']; ?></span>
                                <span class="saw-hero-stat-label">Tento rok</span>
                            </div>
                            <div class="saw-hero-stat">
                                <span class="saw-hero-stat-icon">⏱️</span>
                                <span class="saw-hero-stat-value"><?php echo $avg_duration ? self::format_duration($avg_duration) : '-'; ?></span>
                                <span class="saw-hero-stat-label">Ø Doba návštěvy</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <?php if (!$branch_id): ?>
                <!-- Alert: No branch selected -->
                <div class="saw-alert">
                    ⚠️ Vyberte pobočku pro zobrazení dat
                </div>
                <?php else: ?>
                
                <!-- ============================================
                     PRIMARY SECTION - Current Status
                     Present Visitors | Planned | Activity
                     ============================================ -->
                <div class="saw-section">
                    <div class="saw-row3">
                        
                        <!-- Present Visitors Card -->
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>
                                    🔥 Aktuálně přítomní
                                    <?php if ($stats['present'] > 0): ?>
                                    <span class="saw-badge" id="sawPresentBadge"><?php echo $stats['present']; ?></span>
                                    <?php endif; ?>
                                </h3>
                                <button onclick="location.reload()" class="saw-refresh" title="Obnovit">↻</button>
                            </div>
                            <div class="saw-card-b saw-scroll" id="sawPresentList">
                                <?php if (empty($present_visitors)): ?>
                                <div class="saw-empty" id="sawEmptyPresent">
                                    <span class="saw-empty-icon">✅</span>
                                    <p>Nikdo aktuálně uvnitř</p>
                                </div>
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
                                            <em>
                                                <?php if (!empty($v['position'])): ?><?php echo esc_html($v['position']); ?> · <?php endif; ?>
                                                <?php echo $v['company_id'] ? esc_html($v['company_name']) : 'Fyz. osoba'; ?>
                                            </em>
                                        </span>
                                    </a>
                                    <span class="saw-time <?php echo $overnight ? 'overnight' : ''; ?>">
                                        <?php if ($overnight): ?>🌙 <?php endif; ?><?php echo $dur; ?>
                                    </span>
                                    <button type="button" class="saw-checkout saw-checkout-btn" data-visitor-id="<?php echo $v['visitor_id']; ?>" title="Check-out">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                            <polyline points="16 17 21 12 16 7"></polyline>
                                            <line x1="21" y1="12" x2="9" y2="12"></line>
                                        </svg>
                                        <span class="saw-checkout-text">Odejít</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Planned Visits Card -->
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>📆 Plánované návštěvy</h3>
                                <a href="<?php echo home_url('/admin/visits'); ?>">Zobrazit vše →</a>
                            </div>
                            <div class="saw-card-b saw-scroll">
                                <?php if (empty($planned_visits)): ?>
                                <div class="saw-empty">
                                    <span class="saw-empty-icon">📭</span>
                                    <p>Žádné plánované návštěvy</p>
                                </div>
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
                                        <em><?php echo $dt; ?> · <?php echo $pv['visitor_count']; ?> <?php echo self::person_label($pv['visitor_count']); ?></em>
                                    </span>
                                    <span class="saw-pill <?php echo $pv['status']; ?> <?php echo $is_today ? 'today' : ''; ?>">
                                        <?php echo $pv['status'] === 'confirmed' ? 'Potvrzeno' : 'Čeká'; ?>
                                    </span>
                                </a>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Activity Card -->
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>🕐 Nedávná aktivita</h3>
                            </div>
                            <div class="saw-card-b saw-scroll">
                                <?php if (empty($recent_activity)): ?>
                                <div class="saw-empty">
                                    <span class="saw-empty-icon">📝</span>
                                    <p>Žádná aktivita</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($recent_activity as $a): 
                                    $is_out = !empty($a['checked_out_at']);
                                    $time = $is_out ? date_i18n('H:i', strtotime($a['checked_out_at'])) : date_i18n('H:i', strtotime($a['checked_in_at']));
                                ?>
                                <div class="saw-act">
                                    <span class="saw-act-icon <?php echo $is_out ? 'out' : 'in'; ?>">
                                        <?php echo $is_out ? '←' : '→'; ?>
                                    </span>
                                    <span class="saw-act-text">
                                        <strong><?php echo esc_html($a['first_name'] . ' ' . $a['last_name']); ?></strong> 
                                        <?php echo $is_out ? 'odešel' : 'přišel'; ?>
                                    </span>
                                    <span class="saw-act-time"><?php echo $time; ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- ============================================
                     SECONDARY SECTION - Analytics & Stats
                     Charts | Training | Rankings
                     ============================================ -->
                <div class="saw-section saw-section-secondary">
                    
                    <!-- Charts Row -->
                    <div class="saw-row3">
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>📊 Návštěvnost (7 dní)</h3>
                            </div>
                            <div class="saw-card-b">
                                <div class="saw-chart">
                                    <canvas id="chart-week"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>⏰ Příchody dnes</h3>
                            </div>
                            <div class="saw-card-b">
                                <div class="saw-chart">
                                    <canvas id="chart-hour"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>🎓 Školení</h3>
                                <span class="saw-tag">30 dní</span>
                            </div>
                            <div class="saw-card-b">
                                <div class="saw-training">
                                    <div class="saw-ring" style="--c:<?php echo $pct_completed; ?>;--p:<?php echo $pct_pending; ?>">
                                        <span><?php echo $training_total; ?></span>
                                    </div>
                                    <div class="saw-legend">
                                        <div class="saw-leg">
                                            <span class="ok"></span>
                                            Dokončeno 
                                            <b><?php echo $training_stats['completed']; ?></b>
                                        </div>
                                        <div class="saw-leg">
                                            <span class="wait"></span>
                                            Čeká 
                                            <b><?php echo $training_stats['pending']; ?></b>
                                        </div>
                                        <div class="saw-leg">
                                            <span class="skip"></span>
                                            Přeskočeno 
                                            <b><?php echo $training_stats['skipped']; ?></b>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rankings Row -->
                    <div class="saw-row3">
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>⭐ Top návštěvníci</h3>
                                <span class="saw-tag">30 dní</span>
                            </div>
                            <div class="saw-card-b saw-card-flush">
                                <?php if (empty($top_visitors)): ?>
                                <div class="saw-empty-sm">Žádná data</div>
                                <?php else: ?>
                                <?php $r = 1; foreach ($top_visitors as $tv): ?>
                                <div class="saw-rank-item">
                                    <span class="saw-rank r<?php echo $r; ?>"><?php echo $r; ?></span>
                                    <span class="saw-rank-text">
                                        <strong><?php echo esc_html($tv['first_name'] . ' ' . $tv['last_name']); ?></strong>
                                        <em><?php 
                                            $parts = array();
                                            if (!empty($tv['position'])) $parts[] = $tv['position'];
                                            $parts[] = $tv['company_id'] ? $tv['company_name'] : 'Fyz. osoba';
                                            echo esc_html(implode(' · ', $parts));
                                        ?></em>
                                    </span>
                                    <span class="saw-rank-count"><?php echo $tv['visit_count']; ?>×</span>
                                </div>
                                <?php $r++; endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>🏆 Top firmy</h3>
                                <span class="saw-tag">30 dní</span>
                            </div>
                            <div class="saw-card-b saw-card-flush">
                                <?php if (empty($top_companies)): ?>
                                <div class="saw-empty-sm">Žádná data</div>
                                <?php else: ?>
                                <?php $r = 1; foreach ($top_companies as $tc): ?>
                                <a href="<?php echo home_url('/admin/companies/' . $tc['id']); ?>" class="saw-rank-item saw-rank-link">
                                    <span class="saw-rank r<?php echo $r; ?>"><?php echo $r; ?></span>
                                    <span class="saw-rank-text">
                                        <strong><?php echo esc_html($tc['name']); ?></strong>
                                        <em><?php echo $tc['visitor_count']; ?> návštěvníků</em>
                                    </span>
                                    <span class="saw-rank-count"><?php echo $tc['visit_count']; ?>×</span>
                                </a>
                                <?php $r++; endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="saw-card">
                            <div class="saw-card-h">
                                <h3>⏳ Nejdelší návštěvy</h3>
                                <span class="saw-tag">7 dní</span>
                            </div>
                            <div class="saw-card-b saw-card-flush">
                                <?php if (empty($longest_visits)): ?>
                                <div class="saw-empty-sm">Žádná data</div>
                                <?php else: ?>
                                <?php $r = 1; foreach ($longest_visits as $lv): ?>
                                <div class="saw-rank-item">
                                    <span class="saw-rank r<?php echo $r; ?>"><?php echo $r; ?></span>
                                    <span class="saw-rank-text">
                                        <strong><?php echo esc_html($lv['first_name'] . ' ' . $lv['last_name']); ?></strong>
                                        <em>
                                            <?php echo date_i18n('d.m.', strtotime($lv['log_date'])); ?> · 
                                            <?php echo $lv['company_id'] ? esc_html($lv['company_name']) : 'Fyz. osoba'; ?>
                                        </em>
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
            <!-- end saw-dash-inner -->
        </div>
        <!-- end saw-dash-scroll -->
    </div>
    <!-- end saw-dash -->
        
        <!-- ============================================
             PRINT TEMPLATE - Emergency Evacuation List
             ============================================ -->
        <div id="print-tpl" style="display:none">
            <div class="p-page">
                <div class="p-head">
                    <h1>EVAKUAČNÍ SEZNAM</h1>
                    <p>
                        <?php echo esc_html($customer_data['name']); ?>
                        <?php if ($branch_name): ?> · <?php echo esc_html($branch_name); ?><?php endif; ?>
                    </p>
                    <p class="p-time">Vytištěno: <?php echo date_i18n('d.m.Y H:i:s'); ?></p>
                </div>
                <div class="p-count">
                    <?php echo count($present_visitors); ?> <?php echo self::person_label(count($present_visitors)); ?> uvnitř
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jméno</th>
                            <th>Firma / Pozice</th>
                            <th>Příchod</th>
                            <th>Telefon</th>
                            <th>✓</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($present_visitors as $pv): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><b><?php echo esc_html($pv['first_name'] . ' ' . $pv['last_name']); ?></b></td>
                            <td>
                                <?php echo $pv['company_id'] ? esc_html($pv['company_name']) : 'Fyz.'; ?>
                                <?php if (!empty($pv['position'])): ?>
                                <br><small><?php echo esc_html($pv['position']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date_i18n('H:i', strtotime($pv['checked_in_at'])); ?></td>
                            <td><?php echo esc_html($pv['phone'] ?: '—'); ?></td>
                            <td class="chk">☐</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="p-foot">
                    <div>
                        <p>Podpis:</p>
                        <div class="line"></div>
                    </div>
                    <div>
                        <p>Poznámky:</p>
                        <div class="line"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================
             SCRIPTS - Charts & Interactions
             ============================================ -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dark mode detection
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark' || 
                         document.body.getAttribute('data-theme') === 'dark';
            
            // Chart colors based on theme - using brand color #005A8C
            var chartColors = {
                barBg: isDark ? 'rgba(59, 158, 202, 0.85)' : 'rgba(0, 90, 140, 0.85)',
                barBgHover: isDark ? 'rgba(59, 158, 202, 1)' : 'rgba(0, 90, 140, 1)',
                lineBorder: isDark ? '#34d399' : '#059669',
                lineBg: isDark ? 'rgba(52, 211, 153, 0.15)' : 'rgba(5, 150, 105, 0.1)',
                gridColor: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 90, 140, 0.08)',
                tickColor: isDark ? '#94a3b8' : '#64748b',
                tickColorAlt: isDark ? '#64748b' : '#475569'
            };
            
            // WEEKLY CHART
            var w = document.getElementById('chart-week');
            if (w) new Chart(w, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($chart_values); ?>,
                        backgroundColor: chartColors.barBg,
                        hoverBackgroundColor: chartColors.barBgHover,
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1a2332' : '#ffffff',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor: isDark ? '#94a3b8' : '#475569',
                            borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,90,140,0.1)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                stepSize: 1, 
                                color: chartColors.tickColor,
                                font: { size: 12 }
                            },
                            grid: { 
                                color: chartColors.gridColor,
                                drawBorder: false
                            },
                            border: { display: false }
                        },
                        x: {
                            ticks: { 
                                color: chartColors.tickColorAlt,
                                font: { size: 12, weight: 500 }
                            },
                            grid: { display: false },
                            border: { display: false }
                        }
                    }
                }
            });
            
            // HOURLY CHART (24 hours)
            var h = document.getElementById('chart-hour');
            if (h) {
                var hL = [], hV = [];
                <?php foreach ($hourly_data as $hr => $cnt): ?>
                hL.push('<?php echo $hr; ?>h');
                hV.push(<?php echo $cnt; ?>);
                <?php endforeach; ?>
                
                new Chart(h, {
                    type: 'line',
                    data: {
                        labels: hL,
                        datasets: [{
                            data: hV,
                            borderColor: chartColors.lineBorder,
                            backgroundColor: chartColors.lineBg,
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHoverRadius: 6,
                            pointBackgroundColor: chartColors.lineBorder,
                            pointBorderColor: isDark ? '#1a2332' : '#ffffff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: isDark ? '#1a2332' : '#ffffff',
                                titleColor: isDark ? '#f1f5f9' : '#0f172a',
                                bodyColor: isDark ? '#94a3b8' : '#475569',
                                borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,90,140,0.1)',
                                borderWidth: 1,
                                cornerRadius: 8,
                                padding: 12,
                                intersect: false,
                                mode: 'index'
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { 
                                    stepSize: 1, 
                                    color: chartColors.tickColor,
                                    font: { size: 12 }
                                },
                                grid: { 
                                    color: chartColors.gridColor,
                                    drawBorder: false
                                },
                                border: { display: false }
                            },
                            x: {
                                ticks: {
                                    color: chartColors.tickColor,
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 12,
                                    font: { size: 11 }
                                },
                                grid: { display: false },
                                border: { display: false }
                            }
                        }
                    }
                });
            }
            
            // ============================================
            // CHECKOUT HANDLER
            // ============================================
            document.querySelectorAll('.saw-checkout-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var visitorId = this.getAttribute('data-visitor-id');
                    var personEl = document.getElementById('sawPerson-' + visitorId);
                    var btnEl = this;
                    
                    if (!visitorId || !personEl) return;
                    
                    if (!confirm('Opravdu chcete odhlásit tohoto návštěvníka?')) {
                        return;
                    }
                    
                    var reason = prompt('Důvod ručního odhlášení (volitelné):');
                    if (reason === null) return;
                    reason = reason || 'Ruční odhlášení';
                    
                    btnEl.disabled = true;
                    btnEl.style.opacity = '0.6';
                    personEl.style.opacity = '0.5';
                    
                    var nonce = '';
                    if (typeof sawDashboard !== 'undefined' && sawDashboard.nonce) {
                        nonce = sawDashboard.nonce;
                    } else if (typeof sawGlobal !== 'undefined' && sawGlobal.nonce) {
                        nonce = sawGlobal.nonce;
                    }
                    
                    var ajaxurl = '';
                    if (typeof sawDashboard !== 'undefined' && sawDashboard.ajaxurl) {
                        ajaxurl = sawDashboard.ajaxurl;
                    } else if (typeof sawGlobal !== 'undefined' && sawGlobal.ajaxurl) {
                        ajaxurl = sawGlobal.ajaxurl;
                    } else {
                        ajaxurl = '/wp-admin/admin-ajax.php';
                    }
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'saw_checkout',
                            nonce: nonce,
                            visitor_id: visitorId,
                            log_date: new Date().toISOString().split('T')[0],
                            manual: '1',
                            reason: reason
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            personEl.style.transition = 'all 0.3s ease';
                            personEl.style.transform = 'translateX(100%)';
                            personEl.style.opacity = '0';
                            personEl.style.maxHeight = personEl.offsetHeight + 'px';
                            
                            setTimeout(function() {
                                personEl.style.maxHeight = '0';
                                personEl.style.padding = '0';
                                personEl.style.margin = '0';
                                personEl.style.borderBottom = 'none';
                            }, 150);
                            
                            setTimeout(function() {
                                personEl.remove();
                                updatePresentCount(-1);
                                
                                var list = document.getElementById('sawPresentList');
                                if (list && list.querySelectorAll('.saw-person').length === 0) {
                                    list.innerHTML = '<div class="saw-empty" id="sawEmptyPresent"><span class="saw-empty-icon">✅</span><p>Nikdo aktuálně uvnitř</p></div>';
                                }
                            }, 350);
                        } else {
                            personEl.style.opacity = '1';
                            btnEl.disabled = false;
                            btnEl.style.opacity = '1';
                            alert('Chyba: ' + (data.data && data.data.message ? data.data.message : 'Nepodařilo se odhlásit'));
                        }
                    })
                    .catch(function(err) {
                        console.error('Checkout error:', err);
                        personEl.style.opacity = '1';
                        btnEl.disabled = false;
                        btnEl.style.opacity = '1';
                        alert('Chyba při odhlašování');
                    });
                });
            });
            
            /**
             * Update present count in multiple places
             */
            function updatePresentCount(delta) {
                // Hero stat count
                var countEl = document.getElementById('sawPresentCount');
                if (countEl) {
                    var current = parseInt(countEl.textContent) || 0;
                    var newCount = Math.max(0, current + delta);
                    countEl.textContent = newCount;
                    
                    if (newCount === 0) {
                        var liveBadge = document.getElementById('sawLiveBadge');
                        if (liveBadge) liveBadge.style.display = 'none';
                    }
                }
                
                // Card badge
                var badgeEl = document.getElementById('sawPresentBadge');
                if (badgeEl) {
                    var current = parseInt(badgeEl.textContent) || 0;
                    var newCount = Math.max(0, current + delta);
                    if (newCount > 0) {
                        badgeEl.textContent = newCount;
                    } else {
                        badgeEl.style.display = 'none';
                    }
                }
                
                // Emergency button count
                var emCount = document.querySelector('.saw-em-count');
                if (emCount) {
                    var current = parseInt(emCount.textContent) || 0;
                    var newCount = Math.max(0, current + delta);
                    if (newCount > 0) {
                        emCount.textContent = newCount;
                    } else {
                        var emBtn = document.querySelector('.saw-emergency');
                        if (emBtn) emBtn.style.display = 'none';
                    }
                }
            }
        });
        
        /**
         * Print emergency evacuation list
         */
        function printEmergency() {
            var c = document.getElementById('print-tpl').innerHTML;
            var w = window.open('', '_blank', 'width=800,height=600');
            w.document.write('<!DOCTYPE html><html><head><title>Evakuační seznam</title><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:20px;color:#0f172a;font-size:12px}.p-page{max-width:700px;margin:0 auto}.p-head{text-align:center;padding-bottom:12px;border-bottom:2px solid #dc2626;margin-bottom:12px}.p-head h1{font-size:18px;color:#dc2626;margin-bottom:4px;font-weight:700}.p-head p{font-size:12px;color:#475569}.p-time{margin-top:6px}.p-count{text-align:center;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:10px;margin-bottom:14px;font-weight:700;color:#dc2626;font-size:14px}table{width:100%;border-collapse:collapse;margin-bottom:16px}th{background:#f1f5f9;padding:8px;text-align:left;font-weight:600;border:1px solid #e2e8f0;font-size:11px}td{padding:8px;border:1px solid #e2e8f0;font-size:11px}tr:nth-child(even){background:#f8fafc}.chk{text-align:center;font-size:16px;width:30px}.p-foot{display:flex;gap:24px;padding-top:12px;border-top:1px solid #e2e8f0}.p-foot>div{flex:1}.p-foot p{font-size:10px;color:#64748b;margin-bottom:4px}.line{border-bottom:1px solid #94a3b8;height:24px}@media print{body{padding:0}}</style></head><body>'+c+'</body></html>');
            w.document.close();
            w.focus();
            w.onafterprint = function() { w.close(); };
            setTimeout(function() { w.print(); setTimeout(function() { if (!w.closed) w.close(); }, 500); }, 150);
        }
        </script>
        
        <?php
        $content = ob_get_clean();
        
        // Render with app layout if available
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, 'Dashboard', 'dashboard', $user_data, $customer_data);
        } else {
            echo $content;
        }
    }
    
    /**
     * Format duration in human-readable format
     * 
     * @param int $m Minutes
     * @return string Formatted duration
     */
    private static function format_duration($m) {
        $m = (int) $m;
        if ($m < 60) return $m . 'm';
        $h = floor($m / 60);
        if ($h < 24) return $h . 'h ' . ($m % 60) . 'm';
        return floor($h / 24) . 'd ' . ($h % 24) . 'h';
    }
    
    /**
     * Format date range in human-readable format
     * 
     * @param string $f Start date
     * @param string $t End date
     * @return string Formatted date range
     */
    private static function format_date_range($f, $t) {
        if (empty($f) && empty($t)) return '-';
        $today = date('Y-m-d');
        $tom = date('Y-m-d', strtotime('+1 day'));
        $fy = $f ? date('Y-m-d', strtotime($f)) : '';
        $ty = $t ? date('Y-m-d', strtotime($t)) : '';
        
        if ($fy === $ty || empty($ty)) {
            if ($fy === $today) return 'Dnes';
            if ($fy === $tom) return 'Zítra';
            return date_i18n('d.m', strtotime($f));
        }
        return date_i18n('d.m', strtotime($f)) . ' – ' . date_i18n('d.m', strtotime($t));
    }
    
    /**
     * Get Czech grammatical form for person count
     * 
     * @param int $c Count
     * @return string "osoba", "osoby", or "osob"
     */
    private static function person_label($c) {
        $c = (int) $c;
        if ($c === 1) return 'osoba';
        if ($c >= 2 && $c <= 4) return 'osoby';
        return 'osob';
    }
}

SAW_Frontend_Dashboard::init();