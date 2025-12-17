<?php
/**
 * SAW Bento Contact Card
 * 
 * Kontaktní údaje (telefon, email, web).
 * Podporuje tmavou variantu.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Contact extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'phone',
        'title' => 'Kontakt',
        'phone' => '',
        'email' => '',
        'website' => '',
        'variant' => 'dark',
        'colspan' => 1,
        'show_button' => false,
        'button_label' => 'Kontaktovat',
    ];
    
    /**
     * Render the contact card
     */
    public function render() {
        $args = $this->args;
        
        // Check if we have any contact data
        $has_contact = !empty($args['phone']) || !empty($args['email']) || !empty($args['website']);
        if (!$has_contact) {
            return;
        }
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-contact',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
        ]);
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php $this->render_card_header($args['icon'], $args['title']); ?>
            
            <div class="bento-contact-content">
                <?php if (!empty($args['phone'])): ?>
                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $args['phone'])); ?>" 
                   class="bento-contact-item">
                    <span class="bento-contact-icon">
                        <?php $this->render_icon('phone'); ?>
                    </span>
                    <span class="bento-contact-value"><?php echo esc_html($args['phone']); ?></span>
                </a>
                <?php endif; ?>
                
                <?php if (!empty($args['email'])): ?>
                <a href="mailto:<?php echo esc_attr($args['email']); ?>" 
                   class="bento-contact-item">
                    <span class="bento-contact-icon">
                        <?php $this->render_icon('mail'); ?>
                    </span>
                    <span class="bento-contact-value"><?php echo esc_html($args['email']); ?></span>
                </a>
                <?php endif; ?>
                
                <?php if (!empty($args['website'])): ?>
                <a href="<?php echo esc_url($args['website']); ?>" 
                   class="bento-contact-item"
                   target="_blank"
                   rel="noopener noreferrer">
                    <span class="bento-contact-icon">
                        <?php $this->render_icon('globe'); ?>
                    </span>
                    <span class="bento-contact-value">
                        <?php echo esc_html(preg_replace('#^https?://#', '', $args['website'])); ?>
                    </span>
                </a>
                <?php endif; ?>
                
                <?php if ($args['show_button'] && !empty($args['email'])): ?>
                <a href="mailto:<?php echo esc_attr($args['email']); ?>" 
                   class="bento-contact-button">
                    <?php echo esc_html($args['button_label']); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

