<?php
if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěvník nebyl nalezen</div>';
    return;
}
?>

<style>
/* FORCE INLINE CSS */
.saw-detail-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 20px !important;
    padding: 20px !important;
}

.saw-detail-card {
    background: #fff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    overflow: hidden !important;
}

.saw-detail-card-header {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 16px !important;
    background: #f8fafc !important;
    border-bottom: 1px solid #e5e7eb !important;
}

.saw-detail-card-icon {
    font-size: 24px !important;
}

.saw-detail-card-title {
    margin: 0 !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    color: #1e293b !important;
}

.saw-detail-card-body {
    padding: 16px !important;
}

.saw-detail-list {
    display: grid !important;
    gap: 12px !important;
    margin: 0 !important;
}

.saw-detail-label {
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    margin: 0 !important;
}

.saw-detail-value {
    font-size: 14px !important;
    color: #1e293b !important;
    margin: 0 0 12px 0 !important;
}

.saw-certificates-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
}

.saw-certificate-item {
    display: flex !important;
    gap: 12px !important;
    padding: 12px !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 6px !important;
}

.saw-cert-icon {
    font-size: 20px !important;
}

.saw-cert-name {
    font-weight: 600 !important;
    color: #1e293b !important;
    margin-bottom: 4px !important;
}

.saw-cert-meta {
    font-size: 13px !important;
    color: #64748b !important;
}

.saw-hosts-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
}

.saw-host-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 8px 12px !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 6px !important;
}

.saw-host-item .dashicons {
    color: #2271b1 !important;
    font-size: 20px !important;
    width: 20px !important;
    height: 20px !important;
}
</style>

<div class="saw-detail-grid">
    
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">👤</span>
            <h3 class="saw-detail-card-title">Základní informace</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Jméno a příjmení</dt>
                <dd class="saw-detail-value"><strong><?php echo esc_html($item['first_name'] . ' ' . $item['last_name']); ?></strong></dd>
                
                <?php if (!empty($item['position'])): ?>
                <dt class="saw-detail-label">Pozice/profese</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['position']); ?></dd>
                <?php endif; ?>
                
                <dt class="saw-detail-label">Email</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['email'])): ?>
                        <a href="mailto:<?php echo esc_attr($item['email']); ?>"><?php echo esc_html($item['email']); ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                
                <dt class="saw-detail-label">Telefon</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['phone'])): ?>
                        <a href="tel:<?php echo esc_attr($item['phone']); ?>"><?php echo esc_html($item['phone']); ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">🏢</span>
            <h3 class="saw-detail-card-title">Návštěva</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Firma</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['visit_data']['company_name'] ?? '—'); ?></dd>
                
                <dt class="saw-detail-label">Pobočka</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['visit_data']['branch_name'] ?? '—'); ?></dd>
                
                <dt class="saw-detail-label">Koho navštěvuje</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['visit_data']['hosts'])): ?>
                        <div class="saw-hosts-list">
                            <?php foreach ($item['visit_data']['hosts'] as $host): ?>
                            <div class="saw-host-item">
                                <span class="dashicons dashicons-businessman"></span>
                                <div>
                                    <strong><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></strong>
                                    <?php if (!empty($host['email'])): ?>
                                    <div style="font-size:12px;color:#64748b;"><?php echo esc_html($host['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
                
                <dt class="saw-detail-label">Detail návštěvy</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['visit_id'])): ?>
                        <a href="<?php echo esc_url(home_url('/admin/visits/' . $item['visit_id'] . '/')); ?>">
                            Zobrazit návštěvu #<?php echo $item['visit_id']; ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">✓</span>
            <h3 class="saw-detail-card-title">Stav účasti</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Stav</dt>
                <dd class="saw-detail-value">
                    <?php
                    $status = $item['participation_status'] ?? 'planned';
                    $badges = array(
                        'planned' => '<span class="saw-badge saw-badge-info">Plánovaný</span>',
                        'confirmed' => '<span class="saw-badge saw-badge-success">Potvrzený</span>',
                        'no_show' => '<span class="saw-badge saw-badge-danger">Nedorazil</span>',
                    );
                    echo $badges[$status] ?? $status;
                    ?>
                </dd>
                
                <dt class="saw-detail-label">Školení</dt>
                <dd class="saw-detail-value">
                    <?php if (!empty($item['training_skipped'])): ?>
                        <span class="saw-badge saw-badge-warning">Do 1 roku</span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">🕒</span>
            <h3 class="saw-detail-card-title">Check-in / Check-out</h3>
        </div>
        <div class="saw-detail-card-body">
            <dl class="saw-detail-list">
                <dt class="saw-detail-label">Check-in</dt>
                <dd class="saw-detail-value"><?php echo !empty($item['checked_in_at']) ? esc_html($item['checked_in_at']) : '—'; ?></dd>
                
                <dt class="saw-detail-label">Check-out</dt>
                <dd class="saw-detail-value"><?php echo !empty($item['checked_out_at']) ? esc_html($item['checked_out_at']) : '—'; ?></dd>
            </dl>
        </div>
    </div>
    
    <div class="saw-detail-card">
        <div class="saw-detail-card-header">
            <span class="saw-detail-card-icon">🎓</span>
            <h3 class="saw-detail-card-title">Profesní průkazy</h3>
        </div>
        <div class="saw-detail-card-body">
            <?php if (!empty($item['certificates'])): ?>
                <div class="saw-certificates-list">
                    <?php foreach ($item['certificates'] as $cert): ?>
                    <div class="saw-certificate-item">
                        <div class="saw-cert-icon">📄</div>
                        <div class="saw-cert-content">
                            <div class="saw-cert-name"><?php echo esc_html($cert['certificate_name']); ?></div>
                            <?php if (!empty($cert['certificate_number'])): ?>
                            <div class="saw-cert-meta">Číslo: <?php echo esc_html($cert['certificate_number']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($cert['valid_until'])): ?>
                            <div class="saw-cert-meta">Platný do: <?php echo esc_html($cert['valid_until']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Žádné průkazy.</p>
            <?php endif; ?>
        </div>
    </div>
    
</div>
