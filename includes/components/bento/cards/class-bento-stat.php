<?php
/**
 * SAW Bento Stat Card
 * 
 * Zobrazení jedné statistiky (číslo + label).
 * 
 * Varianty:
 * - default: světlá karta
 * - light-blue: světle modrá (brand-50)
 * - blue: modrá (brand-600) s bílým textem
 * - dark: tmavá (brand-900) s bílým textem
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Stat extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'bar-chart-3',
        'value' => 0,
        'label' => '',
        'variant' => 'default',
        'trend' => null,
        'trend_up' => true,
        'colspan' => 1,
        'link' => '',
    ];
    
    /**
     * Render the stat card
     */
    public function render() {
        $args = $this->args;
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-stat',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
        ]);
        
        $tag = !empty($args['link']) ? 'a' : 'div';
        $link_attr = !empty($args['link']) ? ' href="' . esc_url($args['link']) . '"' : '';
        ?>
        <<?php echo $tag; ?> class="<?php echo esc_attr($classes); ?>"<?php echo $link_attr; ?>>
            <?php if (!empty($args['icon'])): ?>
            <div class="bento-stat-icon">
                <?php $this->render_icon($args['icon']); ?>
            </div>
            <?php endif; ?>
            
            <div class="bento-stat-value"><?php echo esc_html($args['value']); ?></div>
            
            <div class="bento-stat-label"><?php echo esc_html($args['label']); ?></div>
            
            <?php if ($args['trend'] !== null): ?>
            <div class="bento-stat-trend bento-stat-trend--<?php echo $args['trend_up'] ? 'up' : 'down'; ?>">
                <?php $this->render_icon($args['trend_up'] ? 'trending-up' : 'trending-down', 'bento-trend-icon'); ?>
                <?php echo esc_html($args['trend']); ?>
            </div>
            <?php endif; ?>
        </<?php echo $tag; ?>>
        <?php
    }
}

