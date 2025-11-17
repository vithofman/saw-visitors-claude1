<?php
/**
 * Frontend Dashboard Page
 * 
 * Modern, responsive dashboard with full-width layout
 * 
 * @package     SAW_Visitors
 * @subpackage  Frontend/Dashboard
 * @version     2.0.0 - REDESIGNED
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Frontend_Dashboard {
    
    /**
     * Initialize dashboard
     */
    public static function init() {
        // Register route (handled by router)
    }
    
    /**
     * Enqueue dashboard assets
     */
    public static function enqueue_assets() {
        // Widget assets will be enqueued by widget class
    }
    
    /**
     * Render dashboard page
     */
    public static function render_dashboard() {
        $branch_id = SAW_Context::get_branch_id();
        $customer_id = SAW_Context::get_customer_id();
        
        // Get current user
        $current_user = wp_get_current_user();
        
        // Load widget class
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/widgets/visitors/current-visitors/widget-current-visitors.php';
        
        // Enqueue widget assets
        SAW_Widget_Current_Visitors::enqueue_assets();
        
        // Get user and customer data for layout
        global $wpdb;
        $user_data = array(
            'id' => $current_user->ID,
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'role' => 'admin',
        );
        
        // Load FULL customer data including logo
        $customer_data = array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'logo_url' => '',
            'logo_url_full' => '',
        );
        
        if ($customer_id) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, ico, logo_url FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_customers',
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                $logo_url_full = '';
                
                if (!empty($customer['logo_url'])) {
                    // Check if already full URL (starts with http)
                    if (strpos($customer['logo_url'], 'http') === 0) {
                        $logo_url_full = $customer['logo_url'];
                    } else {
                        // Relative path - build full URL
                        $logo_url_full = wp_get_upload_dir()['baseurl'] . '/' . ltrim($customer['logo_url'], '/');
                    }
                }
                
                $customer_data = array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'logo_url' => $customer['logo_url'] ?? '',
                    'logo_url_full' => $logo_url_full,
                );
            }
        }
        
        // Build page content
        ob_start();
        ?>
        <div class="saw-dashboard-wrapper">
            
            <!-- Top Stats Row -->
            <div class="saw-stats-row">
                <?php if ($branch_id): ?>
                    <?php
                    // Get stats
                    $today_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT visitor_id) FROM %i dl
                         INNER JOIN %i v ON dl.visit_id = v.id
                         WHERE v.branch_id = %d AND dl.log_date = CURDATE()",
                        $wpdb->prefix . 'saw_visit_daily_logs',
                        $wpdb->prefix . 'saw_visits',
                        $branch_id
                    ));
                    
                    $week_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT visitor_id) FROM %i dl
                         INNER JOIN %i v ON dl.visit_id = v.id
                         WHERE v.branch_id = %d 
                         AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)",
                        $wpdb->prefix . 'saw_visit_daily_logs',
                        $wpdb->prefix . 'saw_visits',
                        $branch_id
                    ));
                    
                    $month_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT visitor_id) FROM %i dl
                         INNER JOIN %i v ON dl.visit_id = v.id
                         WHERE v.branch_id = %d 
                         AND MONTH(dl.log_date) = MONTH(CURDATE())
                         AND YEAR(dl.log_date) = YEAR(CURDATE())",
                        $wpdb->prefix . 'saw_visit_daily_logs',
                        $wpdb->prefix . 'saw_visits',
                        $branch_id
                    ));
                    
                    $present = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT visitor_id) FROM %i dl
                         INNER JOIN %i v ON dl.visit_id = v.id
                         WHERE v.branch_id = %d AND dl.log_date = CURDATE()
                         AND dl.checked_in_at IS NOT NULL AND dl.checked_out_at IS NULL",
                        $wpdb->prefix . 'saw_visit_daily_logs',
                        $wpdb->prefix . 'saw_visits',
                        $branch_id
                    ));
                    ?>
                    
                    <div class="saw-stat-card saw-stat-primary">
                        <div class="saw-stat-icon-bg">
                            <span class="saw-stat-icon">🔥</span>
                        </div>
                        <div class="saw-stat-info">
                            <div class="saw-stat-label">Aktuálně uvnitř</div>
                            <div class="saw-stat-value"><?php echo intval($present); ?></div>
                        </div>
                    </div>
                    
                    <div class="saw-stat-card">
                        <div class="saw-stat-icon-bg">
                            <span class="saw-stat-icon">📅</span>
                        </div>
                        <div class="saw-stat-info">
                            <div class="saw-stat-label">Dnes celkem</div>
                            <div class="saw-stat-value"><?php echo intval($today_count); ?></div>
                        </div>
                    </div>
                    
                    <div class="saw-stat-card">
                        <div class="saw-stat-icon-bg">
                            <span class="saw-stat-icon">📊</span>
                        </div>
                        <div class="saw-stat-info">
                            <div class="saw-stat-label">Tento týden</div>
                            <div class="saw-stat-value"><?php echo intval($week_count); ?></div>
                        </div>
                    </div>
                    
                    <div class="saw-stat-card">
                        <div class="saw-stat-icon-bg">
                            <span class="saw-stat-icon">📈</span>
                        </div>
                        <div class="saw-stat-info">
                            <div class="saw-stat-label">Tento měsíc</div>
                            <div class="saw-stat-value"><?php echo intval($month_count); ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="saw-alert saw-alert-warning" style="grid-column: 1/-1;">
                        ⚠️ Vyberte pobočku pro zobrazení statistik
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Main Grid -->
            <div class="saw-dashboard-grid">
                
                <!-- Current Visitors -->
                <div class="saw-widget saw-widget-large">
                    <div class="saw-widget-header">
                        <h2 class="saw-widget-title">
                            🔥 Aktuálně přítomní
                        </h2>
                        <button class="saw-btn-refresh" onclick="location.reload()">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                    <div class="saw-widget-body">
                        <?php SAW_Widget_Current_Visitors::render($branch_id); ?>
                    </div>
                </div>
                
                <!-- Upcoming Visits -->
                <div class="saw-widget">
                    <div class="saw-widget-header">
                        <h2 class="saw-widget-title">
                            📆 Nadcházející návštěvy
                        </h2>
                        <a href="<?php echo home_url('/admin/visits'); ?>" class="saw-link-all">
                            Vše →
                        </a>
                    </div>
                    <div class="saw-widget-body-list">
                        <?php if ($branch_id):
                            $upcoming = $wpdb->get_results($wpdb->prepare(
                                "SELECT v.id, v.status, v.company_id, c.name as company_name,
                                        MIN(s.date) as first_date, MAX(s.date) as last_date,
                                        COUNT(DISTINCT vis.id) as visitor_count
                                 FROM %i v
                                 LEFT JOIN %i c ON v.company_id = c.id
                                 INNER JOIN %i s ON v.id = s.visit_id
                                 LEFT JOIN %i vis ON v.id = vis.visit_id
                                 WHERE v.branch_id = %d
                                 AND v.status IN ('pending', 'confirmed')
                                 AND s.date >= CURDATE()
                                 GROUP BY v.id
                                 ORDER BY MIN(s.date) ASC
                                 LIMIT 10",
                                $wpdb->prefix . 'saw_visits',
                                $wpdb->prefix . 'saw_companies',
                                $wpdb->prefix . 'saw_visit_schedules',
                                $wpdb->prefix . 'saw_visitors',
                                $branch_id
                            ), ARRAY_A);
                            
                            if (!empty($upcoming)):
                        ?>
                        <div class="saw-list">
                            <?php foreach ($upcoming as $v):
                                $is_physical = empty($v['company_id']);
                                $date = date('d.m', strtotime($v['first_date']));
                                if ($v['first_date'] != $v['last_date']) {
                                    $date .= ' - ' . date('d.m', strtotime($v['last_date']));
                                }
                            ?>
                            <a href="<?php echo home_url('/admin/visits/' . $v['id']); ?>" class="saw-list-item">
                                <span class="saw-list-icon"><?php echo $is_physical ? '👤' : '🏢'; ?></span>
                                <div class="saw-list-content">
                                    <div class="saw-list-title">
                                        <?php echo $is_physical ? 'Fyzická osoba' : esc_html($v['company_name']); ?>
                                    </div>
                                    <div class="saw-list-meta">
                                        <?php echo $date; ?> • <?php echo $v['visitor_count']; ?> osob
                                    </div>
                                </div>
                                <span class="saw-badge saw-badge-sm saw-badge-success">
                                    <?php echo $v['status'] == 'confirmed' ? '✓' : '○'; ?>
                                </span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="saw-empty">
                            <span class="saw-empty-icon">✅</span>
                            <p>Žádné nadcházející návštěvy</p>
                        </div>
                        <?php endif; else: ?>
                        <div class="saw-empty">
                            <p>Vyberte pobočku</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="saw-widget">
                    <div class="saw-widget-header">
                        <h2 class="saw-widget-title">⚡ Rychlé akce</h2>
                    </div>
                    <div class="saw-widget-body">
                        <div class="saw-actions">
                            <a href="<?php echo home_url('/admin/visits/create'); ?>" class="saw-action">
                                <span class="saw-action-icon">➕</span>
                                <span class="saw-action-text">Nová návštěva</span>
                            </a>
                            <a href="<?php echo home_url('/admin/visits'); ?>" class="saw-action">
                                <span class="saw-action-icon">📋</span>
                                <span class="saw-action-text">Návštěvy</span>
                            </a>
                            <a href="<?php echo home_url('/admin/visitors'); ?>" class="saw-action">
                                <span class="saw-action-icon">👥</span>
                                <span class="saw-action-text">Návštěvníci</span>
                            </a>
                            <a href="<?php echo home_url('/admin/companies'); ?>" class="saw-action">
                                <span class="saw-action-icon">🏢</span>
                                <span class="saw-action-text">Firmy</span>
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <style>
        .saw-dashboard-wrapper { padding: 0; }
        .saw-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; padding: 24px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 1px solid #e5e7eb; }
        .saw-stat-card { background: white; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; transition: all 0.3s; }
        .saw-stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .saw-stat-primary { background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: white; border: none; }
        .saw-stat-icon-bg { width: 56px; height: 56px; background: rgba(34,113,177,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .saw-stat-primary .saw-stat-icon-bg { background: rgba(255,255,255,0.2); }
        .saw-stat-info { flex: 1; }
        .saw-stat-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px; }
        .saw-stat-primary .saw-stat-label { color: rgba(255,255,255,0.9); }
        .saw-stat-value { font-size: 32px; font-weight: 700; color: #1e293b; line-height: 1; }
        .saw-stat-primary .saw-stat-value { color: white; }
        .saw-dashboard-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; padding: 24px; }
        .saw-widget { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; overflow: hidden; }
        .saw-widget-large { grid-row: span 2; }
        .saw-widget-header { padding: 20px 24px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .saw-widget-title { margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }
        .saw-link-all { font-size: 13px; color: #2271b1; text-decoration: none; font-weight: 500; }
        .saw-link-all:hover { color: #135e96; }
        .saw-btn-refresh { background: white; border: 1px solid #e5e7eb; cursor: pointer; padding: 8px; color: #64748b; border-radius: 6px; display: flex; align-items: center; }
        .saw-btn-refresh:hover { color: #2271b1; transform: rotate(90deg); }
        .saw-widget-body { padding: 24px; }
        .saw-widget-body-list { padding: 0; }
        .saw-list { display: flex; flex-direction: column; }
        .saw-list-item { display: flex; align-items: center; gap: 16px; padding: 16px 24px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: inherit; }
        .saw-list-item:hover { background: #f8fafc; }
        .saw-list-icon { width: 48px; height: 48px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .saw-list-content { flex: 1; min-width: 0; }
        .saw-list-title { font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .saw-list-meta { font-size: 12px; color: #64748b; margin-top: 2px; }
        .saw-badge-sm { padding: 4px 8px; background: #ecfdf5; color: #059669; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .saw-empty { text-align: center; padding: 40px 20px; }
        .saw-empty-icon { font-size: 48px; display: block; margin-bottom: 12px; }
        .saw-empty p { color: #64748b; margin: 0; }
        .saw-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .saw-action { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 24px 16px; background: linear-gradient(135deg, #f8fafc 0%, #fff 100%); border: 1px solid #e5e7eb; border-radius: 12px; text-decoration: none; color: #1e293b; transition: all 0.3s; }
        .saw-action:hover { background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: white; transform: translateY(-4px); box-shadow: 0 8px 20px rgba(34,113,177,0.3); }
        .saw-action-icon { font-size: 32px; }
        .saw-action-text { font-size: 13px; font-weight: 600; }
        @media (max-width: 1400px) { .saw-dashboard-grid { grid-template-columns: 1fr; } .saw-widget-large { grid-row: span 1; } }
        @media (max-width: 768px) { .saw-stats-row, .saw-dashboard-grid { padding: 16px; gap: 12px; } .saw-actions { grid-template-columns: 1fr; } }
        </style>
        <?php
        $content = ob_get_clean();
        
        // Render with SAW_App_Layout
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, 'Dashboard', 'dashboard', $user_data, $customer_data);
        } else {
            echo $content;
        }
    }
}

// Initialize
SAW_Frontend_Dashboard::init();