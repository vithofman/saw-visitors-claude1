<?php
/**
 * SAW Bento Meta Card
 * 
 * Metadata (vytvořeno/změněno) - obvykle předposlední karta.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Meta extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'clock',
        'title' => 'Metadata',
        'created_at' => null,
        'updated_at' => null,
        'created_by' => null,
        'updated_by' => null,
        'colspan' => 1,
        'variant' => 'default',
        'compact' => false,
    ];
    
    /**
     * Render the meta card
     */
    public function render() {
        $args = $this->args;
        
        // Check if we have any meta data
        $has_meta = !empty($args['created_at']) || !empty($args['updated_at']);
        if (!$has_meta) {
            return;
        }
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-meta',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
            $args['compact'] ? 'bento-meta--compact' : '',
        ]);
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php if (!$args['compact']): ?>
            <?php $this->render_card_header($args['icon'], $args['title']); ?>
            <?php endif; ?>
            
            <div class="bento-meta-content">
                <?php if (!empty($args['created_at'])): ?>
                <div class="bento-meta-item">
                    <span class="bento-meta-icon">
                        <?php $this->render_icon('plus'); ?>
                    </span>
                    <div class="bento-meta-info">
                        <span class="bento-meta-label">Vytvořeno</span>
                        <span class="bento-meta-value"><?php echo esc_html($args['created_at']); ?></span>
                        <?php if (!empty($args['created_by'])): ?>
                        <span class="bento-meta-by"><?php echo esc_html($args['created_by']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($args['updated_at'])): ?>
                <div class="bento-meta-item">
                    <span class="bento-meta-icon">
                        <?php $this->render_icon('pencil'); ?>
                    </span>
                    <div class="bento-meta-info">
                        <span class="bento-meta-label">Změněno</span>
                        <span class="bento-meta-value"><?php echo esc_html($args['updated_at']); ?></span>
                        <?php if (!empty($args['updated_by'])): ?>
                        <span class="bento-meta-by"><?php echo esc_html($args['updated_by']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

