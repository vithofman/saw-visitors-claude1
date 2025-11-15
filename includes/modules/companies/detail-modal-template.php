<?php
/**
 * Companies Detail Modal Template
 * 
 * Displays detailed information about a company in a modal window.
 * Loaded via AJAX when user clicks on the view icon in the list.
 * 
 * Available Variables:
 * @var array $item Company data with the following keys:
 *  - id: Company ID
 *  - name: Company name
 *  - ico: IČO (optional)
 *  - street: Street address (optional)
 *  - city: City (optional)
 *  - zip: Postal code (optional)
 *  - country: Country (optional)
 *  - email: Email address (optional)
 *  - phone: Phone number (optional)
 *  - website: Website URL (optional)
 *  - is_archived: Archived status (1/0)
 *  - is_archived_label: Formatted status label
 *  - is_archived_badge_class: CSS class for status badge
 *  - branch_name: Name of parent branch
 *  - created_at_formatted: Formatted creation date
 *  - updated_at_formatted: Formatted update date
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Companies
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if item data exists
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Firma nebyla nalezena</div>';
    return;
}

// Prepare full address
$address_parts = array_filter(array(
    $item['street'] ?? '',
    $item['city'] ?? '',
    $item['zip'] ?? '',
));
$full_address = !empty($address_parts) ? implode(', ', $address_parts) : null;
?>

<!-- Detail Header -->
<div class="saw-detail-header">
    <div class="saw-detail-header-info">
        <h2 class="saw-detail-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <!-- Badges Row -->
        <div class="saw-detail-badges">
            <?php if (!empty($item['ico'])): ?>
                <span class="saw-code-badge">IČO: <?php echo esc_html($item['ico']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_archived'])): ?>
                <span class="saw-badge saw-badge-secondary">Archivováno</span>
            <?php else: ?>
                <span class="saw-badge saw-badge-success">Aktivní</span>
            <?php endif; ?>
        </div>
        
        <!-- Branch Info -->
        <?php if (!empty($item['branch_name'])): ?>
            <div class="saw-detail-subtitle">
                <span class="dashicons dashicons-building"></span>
                <?php echo esc_html($item['branch_name']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail Sections -->
<div class="saw-detail-sections">
    
    <!-- Address Section -->
    <?php if ($full_address || !empty($item['country'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-location"></span>
            Adresa sídla
        </h3>
        <dl class="saw-detail-list">
            
            <?php if (!empty($item['street'])): ?>
                <dt class="saw-detail-label">Ulice</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['street']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['city']) || !empty($item['zip'])): ?>
                <dt class="saw-detail-label">Město a PSČ</dt>
                <dd class="saw-detail-value">
                    <?php 
                    $city_zip = array_filter(array($item['city'] ?? '', $item['zip'] ?? ''));
                    echo esc_html(implode(', ', $city_zip)); 
                    ?>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['country'])): ?>
                <dt class="saw-detail-label">Země</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['country']); ?></dd>
            <?php endif; ?>
            
            <?php if ($full_address): ?>
                <dt class="saw-detail-label">Celá adresa</dt>
                <dd class="saw-detail-value">
                    <?php echo esc_html($full_address); ?>
                    <?php if (!empty($item['country'])): ?>
                        <br><?php echo esc_html($item['country']); ?>
                    <?php endif; ?>
                </dd>
            <?php endif; ?>
            
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- Contact Section -->
    <?php if (!empty($item['email']) || !empty($item['phone']) || !empty($item['website'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-email"></span>
            Kontaktní údaje
        </h3>
        <dl class="saw-detail-list">
            
            <?php if (!empty($item['email'])): ?>
                <dt class="saw-detail-label">Email</dt>
                <dd class="saw-detail-value">
                    <a href="mailto:<?php echo esc_attr($item['email']); ?>" class="saw-link">
                        <?php echo esc_html($item['email']); ?>
                    </a>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['phone'])): ?>
                <dt class="saw-detail-label">Telefon</dt>
                <dd class="saw-detail-value">
                    <a href="tel:<?php echo esc_attr(str_replace(' ', '', $item['phone'])); ?>" class="saw-link">
                        <?php echo esc_html($item['phone']); ?>
                    </a>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['website'])): ?>
                <dt class="saw-detail-label">Web</dt>
                <dd class="saw-detail-value">
                    <a href="<?php echo esc_url($item['website']); ?>" target="_blank" rel="noopener" class="saw-link">
                        <?php echo esc_html($item['website']); ?>
                        <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>
                    </a>
                </dd>
            <?php endif; ?>
            
        </dl>
    </div>
    <?php endif; ?>
    
    <!-- Information Section -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-info"></span>
            Informace
        </h3>
        <dl class="saw-detail-list">
            
            <!-- Branch -->
            <?php if (!empty($item['branch_name'])): ?>
                <dt class="saw-detail-label">Pobočka</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['branch_name']); ?></dd>
            <?php endif; ?>
            
            <!-- IČO -->
            <?php if (!empty($item['ico'])): ?>
                <dt class="saw-detail-label">IČO</dt>
                <dd class="saw-detail-value">
                    <code class="saw-code"><?php echo esc_html($item['ico']); ?></code>
                </dd>
            <?php endif; ?>
            
            <!-- Status -->
            <dt class="saw-detail-label">Status</dt>
            <dd class="saw-detail-value">
                <span class="<?php echo esc_attr($item['is_archived_badge_class']); ?>">
                    <?php echo esc_html($item['is_archived_label']); ?>
                </span>
            </dd>
            
            <!-- Created At -->
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt class="saw-detail-label">Vytvořeno</dt>
                <dd class="saw-detail-value saw-detail-date">
                    <?php echo esc_html($item['created_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
            <!-- Updated At -->
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt class="saw-detail-label">Aktualizováno</dt>
                <dd class="saw-detail-value saw-detail-date">
                    <?php echo esc_html($item['updated_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
        </dl>
    </div>
    
</div>
