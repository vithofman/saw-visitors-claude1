<?php
/**
 * SAW Bento List Card
 * 
 * Seznam odkazovaných položek s ikonou a meta info.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_List extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'list',
        'title' => '',
        'badge_count' => null,
        'items' => [],
        'show_all_url' => '',
        'show_all_label' => 'Zobrazit všechny',
        'max_items' => 5,
        'colspan' => 1,
        'variant' => 'default',
        'empty_message' => 'Žádné položky',
    ];
    
    /**
     * Render the list card
     */
    public function render() {
        $args = $this->args;
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-list',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
        ]);
        
        // Limit items
        $items = array_slice($args['items'], 0, $args['max_items']);
        $total_count = $args['badge_count'] ?? count($args['items']);
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php $this->render_card_header($args['icon'], $args['title'], ['badge_count' => $total_count]); ?>
            
            <div class="bento-list-content">
                <?php if (empty($args['items'])): ?>
                <p class="bento-list-empty"><?php echo esc_html($args['empty_message']); ?></p>
                <?php else: ?>
                <div class="bento-list-items">
                    <?php foreach ($items as $item): ?>
                    <?php
                    $item_icon = $item['icon'] ?? 'file';
                    $is_active = $item['active'] ?? true;
                    $icon_class = $is_active ? 'bento-list-item-icon--active' : 'bento-list-item-icon--inactive';
                    ?>
                    <a href="<?php echo esc_url($item['url'] ?? '#'); ?>" class="bento-list-item">
                        <span class="bento-list-item-icon <?php echo esc_attr($icon_class); ?>">
                            <?php $this->render_icon($item_icon); ?>
                        </span>
                        <span class="bento-list-item-info">
                            <span class="bento-list-item-name"><?php echo esc_html($item['name']); ?></span>
                            <?php if (!empty($item['meta'])): ?>
                            <span class="bento-list-item-meta"><?php echo esc_html($item['meta']); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="bento-list-item-arrow">
                            <?php $this->render_icon('chevron-right'); ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($args['show_all_url']) && count($args['items']) > $args['max_items']): ?>
                <a href="<?php echo esc_url($args['show_all_url']); ?>" class="bento-list-show-all">
                    <?php echo esc_html($args['show_all_label']); ?> (<?php echo intval($total_count); ?>)
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

