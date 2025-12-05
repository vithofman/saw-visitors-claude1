<?php
/**
 * OOPP Detail Modal Template
 * 
 * Displays detailed information about an OOPP in sidebar.
 * Modern card-based design matching visits module style.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     2.0.0 - REDESIGNED: Modern card-based layout like visits
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if item data exists
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">OOPP nebyl nalezen</div>';
    return;
}

// Prepare data
$has_image = !empty($item['image_url']);
$is_active = !empty($item['is_active']);
$group_code = $item['group_code'] ?? '';
$group_name = $item['group_name'] ?? '';

// Count branches and departments
$branches_count = !empty($item['branches']) ? count($item['branches']) : 0;
$departments_count = !empty($item['departments']) ? count($item['departments']) : 0;
$branches_all = !empty($item['branches_all']);
$departments_all = !empty($item['departments_all']);

// Check for technical info
$has_technical = !empty($item['standards']) || !empty($item['risk_description']) || !empty($item['protective_properties']);
$has_instructions = !empty($item['usage_instructions']) || !empty($item['maintenance_instructions']) || !empty($item['storage_instructions']);
?>

<div class="saw-oopp-detail">

    <!-- ================================================
         HEADER CARD - Image + Basic Info
         ================================================ -->
    <div class="saw-oopp-header-card">
        <div class="saw-oopp-header-inner">
            <!-- Image Section -->
            <div class="saw-oopp-image-section">
                <?php if ($has_image): ?>
                    <img src="<?php echo esc_url($item['image_url']); ?>" 
                         alt="<?php echo esc_attr($item['name']); ?>" 
                         class="saw-oopp-image">
                <?php else: ?>
                    <div class="saw-oopp-image-placeholder">
                        <span>🦺</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Info Section -->
            <div class="saw-oopp-header-info">
                <h2 class="saw-oopp-title"><?php echo esc_html($item['name']); ?></h2>
                
                <div class="saw-oopp-badges">
                    <!-- Group Badge -->
                    <?php if (!empty($group_code)): ?>
                    <div class="saw-oopp-group-badge">
                        <span class="saw-oopp-group-code"><?php echo esc_html($group_code); ?></span>
                        <span class="saw-oopp-group-name"><?php echo esc_html($group_name); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="saw-oopp-status-badge <?php echo $is_active ? 'saw-status-active' : 'saw-status-inactive'; ?>">
                        <?php if ($is_active): ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Aktivní
                        <?php else: ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            Neaktivní
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================
         VALIDITY CARD - Branches & Departments
         ================================================ -->
    <div class="saw-oopp-validity-card">
        <div class="saw-oopp-validity-inner">
            <div class="saw-oopp-validity-header">
                <div class="saw-oopp-validity-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="saw-oopp-validity-title">
                    <span class="saw-oopp-validity-label">Platnost OOPP</span>
                    <span class="saw-oopp-validity-subtitle">Kde se tento OOPP vyžaduje</span>
                </div>
            </div>
            
            <div class="saw-oopp-validity-body">
                <!-- Branches -->
                <div class="saw-oopp-validity-row">
                    <div class="saw-oopp-validity-row-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 21h18"/>
                            <path d="M5 21V7l8-4v18"/>
                            <path d="M19 21V11l-6-4"/>
                            <path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>
                        </svg>
                    </div>
                    <div class="saw-oopp-validity-row-content">
                        <span class="saw-oopp-validity-row-label">Pobočky</span>
                        <div class="saw-oopp-validity-row-value">
                            <?php if ($branches_all): ?>
                                <span class="saw-oopp-tag saw-oopp-tag-success">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Všechny pobočky
                                </span>
                            <?php elseif (!empty($item['branches'])): ?>
                                <?php foreach ($item['branches'] as $branch): ?>
                                    <span class="saw-oopp-tag saw-oopp-tag-blue">
                                        <?php echo esc_html($branch['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="saw-oopp-tag saw-oopp-tag-muted">Nenastaveno</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Departments -->
                <div class="saw-oopp-validity-row">
                    <div class="saw-oopp-validity-row-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <div class="saw-oopp-validity-row-content">
                        <span class="saw-oopp-validity-row-label">Oddělení</span>
                        <div class="saw-oopp-validity-row-value">
                            <?php if ($departments_all): ?>
                                <span class="saw-oopp-tag saw-oopp-tag-success">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Všechna oddělení
                                </span>
                            <?php elseif (!empty($item['departments'])): ?>
                                <?php foreach ($item['departments'] as $dept): ?>
                                    <span class="saw-oopp-tag saw-oopp-tag-amber">
                                        <?php echo esc_html($dept['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="saw-oopp-tag saw-oopp-tag-muted">Nenastaveno</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================
         STANDARDS CARD - Normy a předpisy
         ================================================ -->
    <?php if (!empty($item['standards'])): ?>
    <div class="saw-oopp-info-card saw-oopp-card-standards">
        <div class="saw-oopp-info-inner">
            <div class="saw-oopp-info-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <line x1="10" y1="9" x2="8" y2="9"/>
                </svg>
            </div>
            <div class="saw-oopp-info-content">
                <div class="saw-oopp-info-label">Související předpisy / normy</div>
                <div class="saw-oopp-info-text"><?php echo nl2br(esc_html($item['standards'])); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================
         RISKS CARD - Popis rizik
         ================================================ -->
    <?php if (!empty($item['risk_description'])): ?>
    <div class="saw-oopp-info-card saw-oopp-card-risks">
        <div class="saw-oopp-info-inner">
            <div class="saw-oopp-info-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="saw-oopp-info-content">
                <div class="saw-oopp-info-label">Popis rizik, proti kterým OOPP chrání</div>
                <div class="saw-oopp-info-text"><?php echo nl2br(esc_html($item['risk_description'])); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================
         PROPERTIES CARD - Ochranné vlastnosti
         ================================================ -->
    <?php if (!empty($item['protective_properties'])): ?>
    <div class="saw-oopp-info-card saw-oopp-card-properties">
        <div class="saw-oopp-info-inner">
            <div class="saw-oopp-info-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
            </div>
            <div class="saw-oopp-info-content">
                <div class="saw-oopp-info-label">Ochranné vlastnosti</div>
                <div class="saw-oopp-info-text"><?php echo nl2br(esc_html($item['protective_properties'])); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================
         INSTRUCTIONS SECTION
         ================================================ -->
    <?php if ($has_instructions): ?>
    <div class="saw-oopp-instructions-section">
        <div class="saw-oopp-section-header">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 16v-4"/>
                <path d="M12 8h.01"/>
            </svg>
            <span>Pokyny pro používání</span>
        </div>
        
        <!-- Usage Instructions -->
        <?php if (!empty($item['usage_instructions'])): ?>
        <div class="saw-oopp-instruction-card saw-oopp-instruction-usage">
            <div class="saw-oopp-instruction-header">
                <div class="saw-oopp-instruction-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </div>
                <span class="saw-oopp-instruction-title">Pokyny pro použití</span>
            </div>
            <div class="saw-oopp-instruction-body">
                <?php echo nl2br(esc_html($item['usage_instructions'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Maintenance Instructions -->
        <?php if (!empty($item['maintenance_instructions'])): ?>
        <div class="saw-oopp-instruction-card saw-oopp-instruction-maintenance">
            <div class="saw-oopp-instruction-header">
                <div class="saw-oopp-instruction-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </div>
                <span class="saw-oopp-instruction-title">Pokyny pro údržbu</span>
            </div>
            <div class="saw-oopp-instruction-body">
                <?php echo nl2br(esc_html($item['maintenance_instructions'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Storage Instructions -->
        <?php if (!empty($item['storage_instructions'])): ?>
        <div class="saw-oopp-instruction-card saw-oopp-instruction-storage">
            <div class="saw-oopp-instruction-header">
                <div class="saw-oopp-instruction-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <span class="saw-oopp-instruction-title">Pokyny pro skladování</span>
            </div>
            <div class="saw-oopp-instruction-body">
                <?php echo nl2br(esc_html($item['storage_instructions'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================
         META INFO CARD - Dates & Order
         ================================================ -->
    <div class="saw-oopp-meta-card">
        <div class="saw-oopp-meta-inner">
            <?php if (isset($item['display_order'])): ?>
            <div class="saw-oopp-meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
                <span class="saw-oopp-meta-label">Pořadí:</span>
                <span class="saw-oopp-meta-value"><?php echo esc_html($item['display_order']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
            <div class="saw-oopp-meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <span class="saw-oopp-meta-label">Vytvořeno:</span>
                <span class="saw-oopp-meta-value"><?php echo esc_html($item['created_at_formatted']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
            <div class="saw-oopp-meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <span class="saw-oopp-meta-label">Aktualizováno:</span>
                <span class="saw-oopp-meta-value"><?php echo esc_html($item['updated_at_formatted']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ================================================
     OOPP DETAIL STYLES
     ================================================ -->
<style>
/* ================================================
   BASE CONTAINER
   ================================================ */
.saw-oopp-detail {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 16px;
}

/* ================================================
   HEADER CARD
   ================================================ */
.saw-oopp-header-card {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    border-radius: 16px;
    padding: 3px;
}

.saw-oopp-header-inner {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.saw-oopp-image-section {
    flex-shrink: 0;
}

.saw-oopp-image {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 12px;
    border: 3px solid #fed7aa;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
}

.saw-oopp-image-placeholder {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    border-radius: 12px;
    border: 3px dashed #fdba74;
    display: flex;
    align-items: center;
    justify-content: center;
}

.saw-oopp-image-placeholder span {
    font-size: 56px;
}

.saw-oopp-header-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saw-oopp-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    line-height: 1.3;
}

.saw-oopp-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.saw-oopp-group-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    border: 1px solid #fdba74;
    border-radius: 8px;
}

.saw-oopp-group-code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: white;
    font-size: 14px;
    font-weight: 800;
    border-radius: 6px;
}

.saw-oopp-group-name {
    font-size: 13px;
    font-weight: 600;
    color: #9a3412;
}

.saw-oopp-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.saw-oopp-status-badge svg {
    flex-shrink: 0;
}

.saw-status-active {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #15803d;
}

.saw-status-inactive {
    background: #f1f5f9;
    color: #64748b;
}

/* ================================================
   VALIDITY CARD
   ================================================ */
.saw-oopp-validity-card {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 16px;
    padding: 3px;
}

.saw-oopp-validity-inner {
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
}

.saw-oopp-validity-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-oopp-validity-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-oopp-validity-title {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.saw-oopp-validity-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-oopp-validity-subtitle {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

.saw-oopp-validity-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.saw-oopp-validity-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.saw-oopp-validity-row-icon {
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    flex-shrink: 0;
}

.saw-oopp-validity-row-content {
    flex: 1;
    min-width: 0;
}

.saw-oopp-validity-row-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 8px;
}

.saw-oopp-validity-row-value {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

/* Tags */
.saw-oopp-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.saw-oopp-tag svg {
    flex-shrink: 0;
}

.saw-oopp-tag-success {
    background: #dcfce7;
    color: #15803d;
}

.saw-oopp-tag-blue {
    background: #dbeafe;
    color: #1d4ed8;
}

.saw-oopp-tag-amber {
    background: #fef3c7;
    color: #b45309;
}

.saw-oopp-tag-muted {
    background: #f1f5f9;
    color: #94a3b8;
}

/* ================================================
   INFO CARDS (Standards, Risks, Properties)
   ================================================ */
.saw-oopp-info-card {
    border-radius: 16px;
    padding: 3px;
}

.saw-oopp-card-standards {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.saw-oopp-card-risks {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.saw-oopp-card-properties {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.saw-oopp-info-inner {
    background: #ffffff;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.saw-oopp-info-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-oopp-card-standards .saw-oopp-info-icon {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.saw-oopp-card-risks .saw-oopp-info-icon {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.saw-oopp-card-properties .saw-oopp-info-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.saw-oopp-info-content {
    flex: 1;
    min-width: 0;
}

.saw-oopp-info-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.saw-oopp-info-text {
    font-size: 14px;
    line-height: 1.7;
    color: #374151;
}

/* ================================================
   INSTRUCTIONS SECTION
   ================================================ */
.saw-oopp-instructions-section {
    background: #f8fafc;
    border-radius: 16px;
    padding: 16px;
}

.saw-oopp-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}

.saw-oopp-section-header svg {
    color: #64748b;
}

.saw-oopp-instruction-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 12px;
}

.saw-oopp-instruction-card:last-child {
    margin-bottom: 0;
}

.saw-oopp-instruction-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-oopp-instruction-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-oopp-instruction-usage .saw-oopp-instruction-icon {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}

.saw-oopp-instruction-maintenance .saw-oopp-instruction-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.saw-oopp-instruction-storage .saw-oopp-instruction-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.saw-oopp-instruction-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-oopp-instruction-body {
    padding: 16px;
    font-size: 14px;
    line-height: 1.7;
    color: #374151;
}

/* ================================================
   META CARD
   ================================================ */
.saw-oopp-meta-card {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 2px;
}

.saw-oopp-meta-inner {
    background: white;
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.saw-oopp-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.saw-oopp-meta-item svg {
    color: #94a3b8;
    flex-shrink: 0;
}

.saw-oopp-meta-label {
    color: #64748b;
}

.saw-oopp-meta-value {
    font-weight: 600;
    color: #1e293b;
}

/* ================================================
   RESPONSIVE
   ================================================ */
@media (max-width: 480px) {
    .saw-oopp-header-inner {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .saw-oopp-badges {
        justify-content: center;
    }
    
    .saw-oopp-image,
    .saw-oopp-image-placeholder {
        width: 100px;
        height: 100px;
    }
    
    .saw-oopp-image-placeholder span {
        font-size: 48px;
    }
    
    .saw-oopp-validity-row {
        flex-direction: column;
    }
    
    .saw-oopp-info-inner {
        flex-direction: column;
    }
    
    .saw-oopp-meta-inner {
        flex-direction: column;
        gap: 8px;
    }
}

/* ================================================
   DARK MODE SUPPORT (if needed)
   ================================================ */
[data-theme="dark"] .saw-oopp-detail {
    /* Add dark mode overrides here if needed */
}
</style>
