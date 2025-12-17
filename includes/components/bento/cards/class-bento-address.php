<?php
/**
 * SAW Bento Address Card
 * 
 * Strukturovaná adresa s ikonou.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Address extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'map-pin',
        'title' => 'Adresa',
        'subtitle' => '',
        'street' => '',
        'city' => '',
        'zip' => '',
        'country' => '',
        'highlight_city' => true,  // Changed: highlight city instead of zip
        'colspan' => 2,
        'variant' => 'default',
        'show_map_link' => false,
    ];
    
    /**
     * Render the address card
     */
    public function render() {
        $args = $this->args;
        
        // Check if we have any address data
        $has_address = !empty($args['street']) || !empty($args['city']) || !empty($args['zip']);
        if (!$has_address) {
            return;
        }
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-address',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
        ]);
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php $this->render_card_header($args['icon'], $args['title'], ['subtitle' => $args['subtitle']]); ?>
            
            <div class="bento-address-content">
                <?php if (!empty($args['street'])): ?>
                <div class="bento-address-field">
                    <span class="bento-address-label">Ulice</span>
                    <span class="bento-address-value"><?php echo esc_html($args['street']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($args['zip']) || !empty($args['city'])): ?>
                <div class="bento-address-row">
                    <?php if (!empty($args['city'])): ?>
                    <div class="bento-address-field <?php echo !empty($args['highlight_city']) ? 'bento-address-field--highlight' : ''; ?>">
                        <span class="bento-address-label">Město</span>
                        <span class="bento-address-value"><?php echo esc_html($args['city']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($args['zip'])): ?>
                    <div class="bento-address-field">
                        <span class="bento-address-label">PSČ</span>
                        <span class="bento-address-value"><?php echo esc_html($args['zip']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($args['country']) && $args['country'] !== 'CZ' && $args['country'] !== 'Česká republika'): ?>
                <div class="bento-address-field">
                    <span class="bento-address-label">Země</span>
                    <span class="bento-address-value"><?php echo esc_html($args['country']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($args['show_map_link'] && ($args['street'] || $args['city'])): ?>
                <?php
                $address_parts = array_filter([$args['street'], $args['zip'], $args['city'], $args['country']]);
                $map_query = urlencode(implode(', ', $address_parts));
                $map_url = 'https://www.google.com/maps/search/?api=1&query=' . $map_query;
                ?>
                <a href="<?php echo esc_url($map_url); ?>" 
                   class="bento-address-map-link" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    <?php $this->render_icon('external-link', 'bento-map-icon'); ?>
                    Zobrazit na mapě
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

