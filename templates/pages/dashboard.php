<div class="saw-dashboard">
    <div class="saw-page-header">
        <h1>Dashboard</h1>
        <p class="saw-page-subtitle">Přehled aktuálního stavu návštěv</p>
    </div>
    
    <div class="saw-stats-grid">
        <div class="saw-stat-card">
            <div class="saw-stat-icon" style="background: #ef4444;">👥</div>
            <div class="saw-stat-content">
                <div class="saw-stat-value"><?php echo esc_html($stats['active_visits']); ?></div>
                <div class="saw-stat-label">Aktivní návštěvy</div>
            </div>
        </div>
        
        <div class="saw-stat-card">
            <div class="saw-stat-icon" style="background: #10b981;">📅</div>
            <div class="saw-stat-content">
                <div class="saw-stat-value"><?php echo esc_html($stats['today_visits']); ?></div>
                <div class="saw-stat-label">Návštěv dnes</div>
            </div>
        </div>
        
        <div class="saw-stat-card">
            <div class="saw-stat-icon" style="background: #2563eb;">📊</div>
            <div class="saw-stat-content">
                <div class="saw-stat-value"><?php echo esc_html($stats['month_visits']); ?></div>
                <div class="saw-stat-label">Návštěv tento měsíc</div>
            </div>
        </div>
        
        <div class="saw-stat-card">
            <div class="saw-stat-icon" style="background: #f59e0b;">✉️</div>
            <div class="saw-stat-content">
                <div class="saw-stat-value"><?php echo esc_html($stats['pending_invitations']); ?></div>
                <div class="saw-stat-label">Čekající pozvánky</div>
            </div>
        </div>
    </div>
    
    <div class="saw-dashboard-row">
        <div class="saw-card" style="flex: 2;">
            <div class="saw-card-header">
                <h2 class="saw-card-title">Compliance Rate</h2>
            </div>
            <div class="saw-card-body">
                <div class="saw-compliance-chart">
                    <div class="saw-compliance-circle">
                        <svg viewBox="0 0 36 36" class="saw-circular-chart">
                            <path class="saw-circle-bg"
                                d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="#e5e7eb"
                                stroke-width="3"
                            />
                            <path class="saw-circle"
                                stroke-dasharray="<?php echo esc_attr($stats['compliance_rate']); ?>, 100"
                                d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="#10b981"
                                stroke-width="3"
                            />
                            <text x="18" y="20.35" class="saw-percentage"><?php echo esc_html($stats['compliance_rate']); ?>%</text>
                        </svg>
                    </div>
                    <p class="saw-compliance-description">
                        Procento návštěvníků, kteří absolvovali povinné školení za posledních 30 dní.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="saw-card" style="flex: 1;">
            <div class="saw-card-header">
                <h2 class="saw-card-title">Rychlé akce</h2>
            </div>
            <div class="saw-card-body">
                <div class="saw-quick-actions">
                    <a href="/admin/invitations/create" class="saw-quick-action-btn saw-btn-primary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 5v10M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Vytvořit pozvánku
                    </a>
                    
                    <a href="/admin/visits" class="saw-quick-action-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/>
                        </svg>
                        Přehled návštěv
                    </a>
                    
                    <a href="/admin/statistics" class="saw-quick-action-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 2v16h16M6 14v-4M10 14V6M14 14v-2"/>
                        </svg>
                        Statistiky
                    </a>
                    
                    <a href="/admin/settings/content" class="saw-quick-action-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 4h12v12H4z"/>
                        </svg>
                        Školící obsah
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="saw-card">
        <div class="saw-card-header">
            <h2 class="saw-card-title">Poslední návštěvy</h2>
            <a href="/admin/visits" class="saw-card-action">Zobrazit vše →</a>
        </div>
        <div class="saw-card-body">
            <?php
            global $wpdb;
            $recent_visits = $wpdb->get_results($wpdb->prepare(
                "SELECT v.*, vr.first_name, vr.last_name, vr.email, c.name as company_name
                FROM {$wpdb->prefix}saw_visits v
                INNER JOIN {$wpdb->prefix}saw_visitors vr ON v.visitor_id = vr.id
                LEFT JOIN {$wpdb->prefix}saw_companies c ON vr.company_id = c.id
                WHERE v.customer_id = %d
                ORDER BY v.checked_in_at DESC
                LIMIT 10",
                $customer['id']
            ));
            ?>
            
            <?php if (empty($recent_visits)): ?>
                <div class="saw-empty-state">
                    <p>Zatím žádné návštěvy</p>
                </div>
            <?php else: ?>
                <table class="saw-table">
                    <thead>
                        <tr>
                            <th>Návštěvník</th>
                            <th>Firma</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_visits as $visit): ?>
                            <tr>
                                <td>
                                    <div class="saw-visitor-name">
                                        <?php echo esc_html($visit->first_name . ' ' . $visit->last_name); ?>
                                    </div>
                                    <div class="saw-visitor-email"><?php echo esc_html($visit->email); ?></div>
                                </td>
                                <td><?php echo esc_html($visit->company_name ?: '—'); ?></td>
                                <td><?php echo esc_html(date('d.m.Y H:i', strtotime($visit->checked_in_at))); ?></td>
                                <td>
                                    <?php if ($visit->checked_out_at): ?>
                                        <?php echo esc_html(date('d.m.Y H:i', strtotime($visit->checked_out_at))); ?>
                                    <?php else: ?>
                                        <span class="saw-badge saw-badge-success">Aktivní</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($visit->checked_out_at): ?>
                                        <span class="saw-badge saw-badge-gray">Ukončena</span>
                                    <?php else: ?>
                                        <span class="saw-badge saw-badge-success">Aktivní</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
