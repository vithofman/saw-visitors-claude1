<?php
/**
 * Frontend Dashboard Page
 * 
 * Modern, responsive dashboard with full-width layout
 * 
 * @package     SAW_Visitors
 * @subpackage  Frontend/Dashboard
 * @version     3.0.0 - REDESIGNED: Modern styling, year stats, animations
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
        // Enqueue dashboard JS
        wp_enqueue_script(
            'saw-dashboard',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/dashboard/dashboard.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('saw-dashboard', 'sawDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce')
        ));
        
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
        
        // Enqueue dashboard assets
        self::enqueue_assets();

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
                    
                    $year_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT visitor_id) FROM %i dl
                         INNER JOIN %i v ON dl.visit_id = v.id
                         WHERE v.branch_id = %d 
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
                    
                    <div class="saw-stat-card">
                        <div class="saw-stat-icon-bg">
                            <span class="saw-stat-icon">🎯</span>
                        </div>
                        <div class="saw-stat-info">
                            <div class="saw-stat-label">Tento rok</div>
                            <div class="saw-stat-value"><?php echo intval($year_count); ?></div>
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
                            <span class="saw-widget-icon">🔥</span>
                            Aktuálně přítomní
                        </h2>
                        <button class="saw-btn-refresh" onclick="location.reload()" title="Obnovit">
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
                            <span class="saw-widget-icon">📆</span>
                            Nadcházející návštěvy
                        </h2>
                        <a href="<?php echo home_url('/admin/visits'); ?>" class="saw-link-all">
                            Zobrazit vše →
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
                                <span class="saw-badge saw-badge-sm <?php echo $v['status'] == 'confirmed' ? 'saw-badge-success' : 'saw-badge-pending'; ?>">
                                    <?php echo $v['status'] == 'confirmed' ? '✓ Potvrzeno' : '○ Čeká'; ?>
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
                            <span class="saw-empty-icon">📍</span>
                            <p>Vyberte pobočku</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="saw-widget">
                    <div class="saw-widget-header">
                        <h2 class="saw-widget-title">
                            <span class="saw-widget-icon">⚡</span>
                            Rychlé akce
                        </h2>
                    </div>
                    <div class="saw-widget-body">
                        <div class="saw-actions">
                            <a href="<?php echo home_url('/admin/visits/create'); ?>" class="saw-action saw-action-primary">
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
/* Dashboard Wrapper */
.saw-dashboard-wrapper { 
    padding: 0;
    min-height: calc(100vh - 60px); /* Footer space */
    display: flex;
    flex-direction: column;
}

/* Stats Row - KOMPAKTNĚJŠÍ */
.saw-stats-row { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
    gap: 16px; 
    padding: 20px; 
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); 
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.saw-stat-card { 
    background: white; 
    border-radius: 12px; 
    padding: 16px; 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
    border: 1px solid #e5e7eb; 
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.saw-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #2271b1, #6366f1);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.saw-stat-card:hover::before {
    transform: scaleX(1);
}

.saw-stat-card:hover { 
    transform: translateY(-3px); 
    box-shadow: 0 8px 20px rgba(0,0,0,0.12); 
}

.saw-stat-primary { 
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); 
    color: white; 
    border: none; 
}

.saw-stat-primary::before {
    background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.5));
}

.saw-stat-icon-bg { 
    width: 48px; 
    height: 48px; 
    background: rgba(34,113,177,0.1); 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 24px;
    flex-shrink: 0;
}

.saw-stat-primary .saw-stat-icon-bg { 
    background: rgba(255,255,255,0.2); 
}

.saw-stat-info { flex: 1; min-width: 0; }

.saw-stat-label { 
    font-size: 11px; 
    font-weight: 600; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
    color: #64748b; 
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.saw-stat-primary .saw-stat-label { 
    color: rgba(255,255,255,0.9); 
}

.saw-stat-value { 
    font-size: 28px; 
    font-weight: 700; 
    color: #1e293b; 
    line-height: 1;
}

.saw-stat-primary .saw-stat-value { 
    color: white; 
}

/* Dashboard Grid - FIT TO SCREEN */
.saw-dashboard-grid { 
    display: grid; 
    grid-template-columns: 1.5fr 1fr; 
    gap: 20px; 
    padding: 20px;
    flex: 1;
    align-items: start;
    max-height: calc(100vh - 240px); /* Stats + Footer space */
    overflow: hidden;
}

.saw-widget { 
    background: white; 
    border-radius: 12px; 
    box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
    border: 1px solid #e5e7eb; 
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 100%;
}

.saw-widget-large { 
    grid-row: span 2;
    max-height: calc(100vh - 260px);
}

.saw-widget-header { 
    padding: 16px 20px; 
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); 
    border-bottom: 1px solid #e5e7eb; 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    flex-shrink: 0;
}

.saw-widget-title { 
    margin: 0; 
    font-size: 15px; 
    font-weight: 600; 
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.saw-widget-icon {
    font-size: 18px;
}

.saw-link-all { 
    font-size: 13px; 
    color: #2271b1; 
    text-decoration: none; 
    font-weight: 600;
    transition: all 0.3s ease;
}

.saw-link-all:hover { 
    color: #135e96;
}

.saw-btn-refresh { 
    background: white; 
    border: 1px solid #e5e7eb; 
    cursor: pointer; 
    padding: 8px; 
    color: #64748b; 
    border-radius: 6px; 
    display: flex; 
    align-items: center;
    transition: all 0.3s ease;
}

.saw-btn-refresh:hover { 
    color: #2271b1;
    background: #f8fafc;
    transform: rotate(180deg);
}

.saw-widget-body { 
    padding: 20px;
    overflow-y: auto;
    flex: 1;
    max-height: 100%;
}

/* CUSTOM SCROLLBAR */
.saw-widget-body::-webkit-scrollbar,
.saw-widget-body-list::-webkit-scrollbar {
    width: 8px;
}

.saw-widget-body::-webkit-scrollbar-track,
.saw-widget-body-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.saw-widget-body::-webkit-scrollbar-thumb,
.saw-widget-body-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.saw-widget-body::-webkit-scrollbar-thumb:hover,
.saw-widget-body-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.saw-widget-body-list { 
    padding: 0;
    overflow-y: auto;
    flex: 1;
    max-height: 100%;
}

/* List Items */
.saw-list { 
    display: flex; 
    flex-direction: column; 
}

.saw-list-item { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 12px 20px; 
    border-bottom: 1px solid #f1f5f9; 
    text-decoration: none; 
    color: inherit;
    transition: all 0.3s ease;
}

.saw-list-item:last-child {
    border-bottom: none;
}

.saw-list-item:hover { 
    background: linear-gradient(90deg, #f8fafc, transparent);
}

.saw-list-icon { 
    width: 40px; 
    height: 40px; 
    background: linear-gradient(135deg, #f8fafc, #f1f5f9); 
    border-radius: 10px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 20px;
    flex-shrink: 0;
}

.saw-list-content { 
    flex: 1; 
    min-width: 0; 
}

.saw-list-title { 
    font-weight: 600; 
    color: #1e293b; 
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis; 
    font-size: 14px;
}

.saw-list-meta { 
    font-size: 12px; 
    color: #64748b; 
    margin-top: 2px; 
}

.saw-badge-sm { 
    padding: 4px 10px; 
    border-radius: 6px; 
    font-size: 11px; 
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}

.saw-badge-success {
    background: #ecfdf5; 
    color: #059669;
}

.saw-badge-pending {
    background: #fef3c7;
    color: #d97706;
}

/* Empty State */
.saw-empty { 
    text-align: center; 
    padding: 40px 20px; 
}

.saw-empty-icon { 
    font-size: 48px; 
    display: block; 
    margin-bottom: 12px;
    opacity: 0.8;
}

.saw-empty p { 
    color: #64748b; 
    margin: 0;
    font-size: 14px;
}

/* Quick Actions */
.saw-actions { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 10px; 
}

.saw-action { 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    gap: 10px; 
    padding: 20px 12px; 
    background: linear-gradient(135deg, #f8fafc 0%, #fff 100%); 
    border: 1px solid #e5e7eb; 
    border-radius: 10px; 
    text-decoration: none; 
    color: #1e293b; 
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.saw-action::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.saw-action:hover::before {
    opacity: 1;
}

.saw-action:hover { 
    color: white; 
    transform: translateY(-3px); 
    box-shadow: 0 8px 20px rgba(34,113,177,0.3); 
}

.saw-action-primary {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    color: white;
    border: none;
}

.saw-action-icon { 
    font-size: 32px;
    position: relative;
    z-index: 1;
}

.saw-action-text { 
    font-size: 13px; 
    font-weight: 600;
    position: relative;
    z-index: 1;
}

/* Responsive */
@media (max-width: 1400px) { 
    .saw-dashboard-grid { 
        grid-template-columns: 1fr;
        max-height: none;
    } 
    .saw-widget-large { 
        grid-row: span 1;
        max-height: 500px;
    } 
}

@media (max-width: 768px) { 
    .saw-stats-row { 
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 16px;
    }
    
    .saw-dashboard-grid { 
        padding: 16px; 
        gap: 16px; 
    } 
    
    .saw-actions { 
        grid-template-columns: 1fr; 
    }
    
    .saw-stat-card {
        padding: 12px;
    }
    
    .saw-stat-value {
        font-size: 24px;
    }
    
    .saw-stat-icon-bg {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }
}
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