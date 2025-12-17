<?php
/**
 * SAW Bento Info Card
 * 
 * Seznam polí label:value.
 * 
 * Typy hodnot:
 * - text: prostý text (default)
 * - code: monospace s pozadím
 * - badge: badge styl
 * - status: status badge s tečkou (success/warning/danger)
 * - link: odkaz
 * - email: mailto odkaz
 * - phone: tel odkaz
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Info extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'info',
        'title' => 'Informace',
        'subtitle' => '',
        'fields' => [],
        'colspan' => 1,
        'variant' => 'default',
    ];
    
    /**
     * Render the info card
     */
    public function render() {
        $args = $this->args;
        
        // Filter fields by condition
        $fields = array_filter($args['fields'], function($field) {
            return $this->check_condition($field['condition'] ?? true);
        });
        
        if (empty($fields)) {
            return; // Don't render empty cards
        }
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-info',
            $this->get_variant_class($args['variant']),
            $this->get_colspan_class($args['colspan']),
        ]);
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php $this->render_card_header($args['icon'], $args['title'], ['subtitle' => $args['subtitle']]); ?>
            
            <div class="bento-info-fields">
                <?php foreach ($fields as $field): ?>
                <?php
                $type = $field['type'] ?? 'text';
                $options = $field;
                ?>
                <div class="bento-info-field">
                    <span class="bento-info-label"><?php echo esc_html($field['label']); ?></span>
                    <span class="bento-info-value">
                        <?php echo $this->format_value($field['value'], $type, $options); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

