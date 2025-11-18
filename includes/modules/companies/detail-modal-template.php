<?php
/**
 * Companies Detail Sidebar Template
 * @version 3.0.0 - FIXED: Use 'display' field like other modules
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Firma nebyla nalezena</div>';
    return;
}

$address_parts = array_filter(array(
    $item['street'] ?? '',
    $item['city'] ?? '',
    $item['zip'] ?? '',
));
$full_address = !empty($address_parts) ? implode(', ', $address_parts) : null;
?>

<div class="saw-detail-sidebar-content">
    
    <!-- Header -->
    <div class="saw-detail-header" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700;">
            <?php echo esc_html($item['name']); ?>
        </h3>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($item['ico'])): ?>
                <span class="saw-badge saw-badge-secondary">IČO: <?php echo esc_html($item['ico']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_archived'])): ?>
                <span class="saw-badge saw-badge-secondary">Archivováno</span>
            <?php else: ?>
                <span class="saw-badge saw-badge-success">Aktivní</span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Address -->
    <?php if ($full_address || !empty($item['country'])): ?>
    <div class="saw-detail-section">
        <h4 class="saw-detail-section-title">Adresa sídla</h4>
        <dl class="saw-detail-meta">
            <?php if (!empty($item['street'])): ?>
            <dt>Ulice</dt>
            <dd><?php echo esc_html($item['street']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['city']) || !empty($item['zip'])): ?>
            <dt>Město a PSČ</dt>
            <dd>
                <?php 
                $city_zip = array_filter(array($item['city'] ?? '', $item['zip'] ?? ''));
                echo esc_html(implode(', ', $city_zip)); 
                ?>
            </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['country'])): ?>
            <dt>Země</dt>
            <dd><?php echo esc_html($item['country']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- Contact -->
    <?php if (!empty($item['email']) || !empty($item['phone']) || !empty($item['website'])): ?>
    <div class="saw-detail-section">
        <h4 class="saw-detail-section-title">Kontaktní údaje</h4>
        <dl class="saw-detail-meta">
            <?php if (!empty($item['email'])): ?>
            <dt>Email</dt>
            <dd>
                <a href="mailto:<?php echo esc_attr($item['email']); ?>" class="saw-link">
                    <?php echo esc_html($item['email']); ?>
                </a>
            </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['phone'])): ?>
            <dt>Telefon</dt>
            <dd>
                <a href="tel:<?php echo esc_attr(str_replace(' ', '', $item['phone'])); ?>" class="saw-link">
                    <?php echo esc_html($item['phone']); ?>
                </a>
            </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['website'])): ?>
            <dt>Web</dt>
            <dd>
                <a href="<?php echo esc_url($item['website']); ?>" target="_blank" rel="noopener" class="saw-link">
                    <?php echo esc_html($item['website']); ?> ↗
                </a>
            </dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- Info -->
    <div class="saw-detail-section">
        <h4 class="saw-detail-section-title">Informace</h4>
        <dl class="saw-detail-meta">
            <?php if (!empty($item['branch_name'])): ?>
            <dt>Pobočka</dt>
            <dd><?php echo esc_html($item['branch_name']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
            <dt>Vytvořeno</dt>
            <dd style="font-family: monospace; font-size: 13px;"><?php echo esc_html($item['created_at_formatted']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
            <dt>Aktualizováno</dt>
            <dd style="font-family: monospace; font-size: 13px;"><?php echo esc_html($item['updated_at_formatted']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>

<!-- ✅ Relations are rendered by AdminTable component in detail-sidebar.php -->
<!-- No need to duplicate here - AdminTable handles it automatically -->