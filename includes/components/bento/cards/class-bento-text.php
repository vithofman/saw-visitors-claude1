<?php
/**
 * SAW Bento Text Card
 * 
 * Dlouhý text (popis, poznámky) s volitelným "zobrazit více".
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Text extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'file-text',
        'title' => 'Popis',
        'content' => '',
        'variant' => 'default', // 'default', 'muted' (pro poznámky)
        'max_height' => null, // null = no limit, number = px height with "show more"
        'colspan' => 2,
        'allow_html' => false,
    ];
    
    /**
     * Render the text card
     */
    public function render() {
        $args = $this->args;
        
        if (empty($args['content'])) {
            return;
        }
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-text',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
        ]);
        
        $content_style = '';
        if ($args['max_height']) {
            $content_style = 'max-height: ' . intval($args['max_height']) . 'px;';
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php $this->render_card_header($args['icon'], $args['title']); ?>
            
            <div class="bento-text-content" <?php echo $content_style ? 'style="' . esc_attr($content_style) . '"' : ''; ?>>
                <?php if ($args['allow_html']): ?>
                    <?php echo wp_kses_post($args['content']); ?>
                <?php else: ?>
                    <p><?php echo nl2br(esc_html($args['content'])); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($args['max_height']): ?>
            <button type="button" class="bento-text-toggle" data-collapsed="true">
                <span class="bento-text-toggle-more">Zobrazit více</span>
                <span class="bento-text-toggle-less">Zobrazit méně</span>
                <?php $this->render_icon('chevron-down', 'bento-text-toggle-icon'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }
}

