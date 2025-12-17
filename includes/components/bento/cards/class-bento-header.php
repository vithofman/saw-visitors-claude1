<?php
/**
 * SAW Bento Header Card
 * 
 * Hlavní header karta s navigací, názvem a badges.
 * Vždy zabírá celou šířku gridu (colspan: full).
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Header extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'building-2',
        'module' => '',
        'module_label' => '',
        'id' => 0,
        'title' => '',
        'subtitle' => '',
        'badges' => [],
        'nav_prev' => null,
        'nav_next' => null,
        'nav_enabled' => true,
        'close_url' => '',
        'stripe' => true,
        'image_url' => '',  // Optional image/logo URL
    ];
    
    /**
     * Render the header card
     */
    public function render() {
        $args = $this->args;
        
        // Build breadcrumb
        $breadcrumb = '';
        if (!empty($args['module_label'])) {
            $breadcrumb = mb_strtoupper($args['module_label'], 'UTF-8');
            if (!empty($args['id'])) {
                $breadcrumb .= ' / #' . intval($args['id']);
            }
        }
        ?>
        <div class="bento-header bento-colspan-full">
            <!-- Background shapes -->
            <div class="bento-header-shapes"></div>
            
            <!-- Navigation bar -->
            <div class="bento-header-nav">
                <div class="bento-header-nav-left">
                    <?php if ($args['nav_enabled']): ?>
                    <button type="button" 
                            class="bento-nav-btn sa-sidebar-prev" 
                            data-nav="prev"
                            title="Předchozí">
                        <?php $this->render_icon('chevron-left'); ?>
                    </button>
                    <button type="button" 
                            class="bento-nav-btn sa-sidebar-next" 
                            data-nav="next"
                            title="Další">
                        <?php $this->render_icon('chevron-right'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($breadcrumb): ?>
                <span class="bento-header-breadcrumb"><?php echo esc_html($breadcrumb); ?></span>
                <?php endif; ?>
                
                <?php if (!empty($args['close_url'])): ?>
                <a href="<?php echo esc_url($args['close_url']); ?>" class="bento-nav-btn bento-nav-close sa-sidebar-close" title="Zavřít">
                    <?php $this->render_icon('x'); ?>
                </a>
                <?php else: ?>
                <button type="button" class="bento-nav-btn bento-nav-close sa-sidebar-close" title="Zavřít">
                    <?php $this->render_icon('x'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Content -->
            <div class="bento-header-content">
                <div class="bento-header-main">
                    <?php if (!empty($args['image_url'])): ?>
                    <div class="bento-header-image">
                        <img src="<?php echo esc_url($args['image_url']); ?>" alt="<?php echo esc_attr($args['title']); ?>">
                    </div>
                    <?php elseif (!empty($args['icon'])): ?>
                    <div class="bento-header-icon">
                        <?php $this->render_icon($args['icon'], '', 32); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bento-header-info">
                        <?php if (!empty($args['subtitle'])): ?>
                        <span class="bento-header-module"><?php echo esc_html($args['subtitle']); ?></span>
                        <?php endif; ?>
                        
                        <h1 class="bento-header-title"><?php echo esc_html($args['title']); ?></h1>
                        
                        <?php if (!empty($args['badges'])): ?>
                        <div class="bento-header-badges">
                            <?php foreach ($args['badges'] as $badge): ?>
                            <?php
                            $variant = $badge['variant'] ?? 'primary';
                            $has_dot = !empty($badge['dot']);
                            $icon = $badge['icon'] ?? '';
                            ?>
                            <span class="bento-badge bento-badge--<?php echo esc_attr($variant); ?>">
                                <?php if ($has_dot): ?>
                                <span class="bento-badge-dot"></span>
                                <?php endif; ?>
                                <?php if ($icon): ?>
                                <span class="bento-badge-icon"><?php echo esc_html($icon); ?></span>
                                <?php endif; ?>
                                <?php echo esc_html($badge['label']); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stripe (šrafování) -->
            <?php if ($args['stripe']): ?>
            <div class="bento-header-stripe"></div>
            <?php endif; ?>
        </div>
        <?php
    }
}

