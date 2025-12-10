<?php
/**
 * SAW Table - Section Renderer
 * 
 * Renders industrial sections with sawt- prefix classes.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Section_Renderer {
    
    /**
     * Section types
     */
    const TYPE_INFO_ROWS = 'info_rows';
    const TYPE_RELATED_LIST = 'related_list';
    const TYPE_TEXT_BLOCK = 'text_block';
    const TYPE_METADATA = 'metadata';
    const TYPE_SPECIAL = 'special';
    const TYPE_STAT_GRID = 'stat_grid';
    const TYPE_TIMELINE = 'timeline';
    const TYPE_FEATURE_LIST = 'feature_list';
    
    /**
     * Translation function
     * @var callable
     */
    private static $translator = null;
    
    /**
     * Set translator
     */
    public static function set_translator($translator) {
        self::$translator = $translator;
    }
    
    /**
     * Translate
     */
    private static function tr($key, $fallback = null) {
        if (self::$translator && is_callable(self::$translator)) {
            return call_user_func(self::$translator, $key, $fallback);
        }
        return $fallback ?? $key;
    }
    
    /**
     * Render section
     */
    public static function render($config, $item, $related_data = [], $entity = '') {
        // Check condition
        if (!empty($config['condition'])) {
            if (!self::evaluate_condition($config['condition'], $item)) {
                return '';
            }
        }
        
        // Check permission
        if (!empty($config['permission'])) {
            if (!self::check_permission($config['permission'])) {
                return '';
            }
        }
        
        $type = $config['type'] ?? self::TYPE_INFO_ROWS;
        $title = $config['title'] ?? '';
        $icon = $config['icon'] ?? '📋';
        $is_compact = !empty($config['compact']);
        $no_header = !empty($config['no_header']);
        
        $section_class = 'sawt-section';
        if (!empty($config['class'])) $section_class .= ' ' . $config['class'];
        if ($is_compact) $section_class .= ' is-compact';
        if ($no_header || ($type === self::TYPE_METADATA && empty($title))) $section_class .= ' no-header';
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($section_class); ?>">
            <?php if (!$no_header && !($type === self::TYPE_METADATA && empty($title))): ?>
            <div class="sawt-section-head">
                <h4 class="sawt-section-title">
                    <span class="sawt-section-title-icon"><?php echo esc_html($icon); ?></span>
                    <?php echo esc_html($title); ?>
                    <?php if (!empty($config['show_count'])): ?>
                        <?php 
                        $data_key = $config['data_key'] ?? '';
                        $count = $config['count'] ?? (isset($related_data[$data_key]) ? count($related_data[$data_key]) : 0);
                        ?>
                        <span class="sawt-section-count"><?php echo intval($count); ?></span>
                    <?php endif; ?>
                </h4>
                <?php if (!empty($config['action'])): ?>
                    <a href="<?php echo esc_url($config['action']['url'] ?? '#'); ?>" class="sawt-section-action">
                        <?php echo esc_html($config['action']['label'] ?? ''); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="sawt-section-body">
                <?php
                switch ($type) {
                    case self::TYPE_INFO_ROWS:
                        self::render_info_rows($config['rows'] ?? [], $item);
                        break;
                    case self::TYPE_RELATED_LIST:
                        self::render_related_list($config, $related_data[$config['data_key']] ?? [], $item);
                        break;
                    case self::TYPE_TEXT_BLOCK:
                        self::render_text_block($config, $item);
                        break;
                    case self::TYPE_METADATA:
                        self::render_metadata($item);
                        break;
                    case self::TYPE_STAT_GRID:
                        self::render_stat_grid($config, $item);
                        break;
                    case self::TYPE_TIMELINE:
                        self::render_timeline($config, $related_data[$config['data_key']] ?? [], $item);
                        break;
                    case self::TYPE_FEATURE_LIST:
                        self::render_feature_list($config, $item);
                        break;
                    case self::TYPE_SPECIAL:
                        self::render_special($config, $item, $related_data, $entity);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render info rows
     */
    private static function render_info_rows($rows, $item) {
        foreach ($rows as $row) {
            $field = $row['field'] ?? '';
            $value = self::get_field_value($item, $field);
            
            // Skip empty
            if (empty($value) && $value !== '0' && empty($row['show_empty'])) {
                continue;
            }
            
            // Check row condition
            if (!empty($row['condition'])) {
                if (!self::evaluate_condition($row['condition'], $item)) {
                    continue;
                }
            }
            
            $label = $row['label'] ?? ucfirst(str_replace('_', ' ', $field));
            $format = $row['format'] ?? 'text';
            $bold = !empty($row['bold']);
            $stacked = !empty($row['stacked']);
            
            // Format value
            $formatted = self::format_value($value, $format, $row, $item);
            
            // Suffix
            if (!empty($row['suffix_field'])) {
                $suffix = $item[$row['suffix_field']] ?? '';
                if (!empty($suffix)) {
                    $formatted .= ' <small class="sawt-text-muted">' . esc_html($suffix) . '</small>';
                }
            }
            
            $row_class = 'sawt-info-row';
            if ($stacked) $row_class .= ' is-stacked';
            
            $val_class = 'sawt-info-val';
            if ($bold) $val_class .= ' sawt-info-val-bold';
            if (!empty($row['highlight'])) $val_class .= ' sawt-info-val-highlight';
            ?>
            <div class="<?php echo esc_attr($row_class); ?>">
                <span class="sawt-info-label"><?php echo esc_html($label); ?></span>
                <span class="<?php echo esc_attr($val_class); ?>"><?php echo $formatted; ?></span>
            </div>
            <?php
        }
    }
    
    /**
     * Render metadata section
     */
    private static function render_metadata($item) {
        $rows = [];
        
        if (!empty($item['created_at_formatted'])) {
            $rows[] = ['label' => self::tr('field_created_at', 'Vytvořeno'), 'value' => $item['created_at_formatted']];
        } elseif (!empty($item['created_at'])) {
            $rows[] = ['label' => self::tr('field_created_at', 'Vytvořeno'), 'value' => date_i18n('j. n. Y H:i', strtotime($item['created_at']))];
        }
        
        if (!empty($item['updated_at_formatted'])) {
            $rows[] = ['label' => self::tr('field_updated_at', 'Aktualizováno'), 'value' => $item['updated_at_formatted']];
        } elseif (!empty($item['updated_at'])) {
            $rows[] = ['label' => self::tr('field_updated_at', 'Aktualizováno'), 'value' => date_i18n('j. n. Y H:i', strtotime($item['updated_at']))];
        }
        
        foreach ($rows as $row) {
            ?>
            <div class="sawt-info-row sawt-info-row-meta">
                <span class="sawt-info-label"><?php echo esc_html($row['label']); ?></span>
                <span class="sawt-info-val"><?php echo esc_html($row['value']); ?></span>
            </div>
            <?php
        }
    }
    
    /**
     * Render related list
     */
    private static function render_related_list($config, $items, $parent_item) {
        if (empty($items)) {
            $empty_text = $config['empty_text'] ?? self::tr('no_records', 'Žádné záznamy');
            ?>
            <div class="sawt-section-empty">
                <span class="sawt-section-empty-icon"><?php echo esc_html($config['empty_icon'] ?? '📭'); ?></span>
                <span class="sawt-section-empty-text"><?php echo esc_html($empty_text); ?></span>
            </div>
            <?php
            return;
        }
        
        $max_items = $config['max_items'] ?? 5;
        $displayed = array_slice($items, 0, $max_items);
        $item_config = $config['item'] ?? [];
        
        echo '<div class="sawt-related-list">';
        
        foreach ($displayed as $rel_item) {
            // Determine icon
            $icon = '';
            if (!empty($item_config['icon_field']) && !empty($item_config['icon_map'])) {
                $icon_value = $rel_item[$item_config['icon_field']] ?? '';
                $icon = $item_config['icon_map'][(string)$icon_value] ?? ($item_config['icon_map']['default'] ?? '•');
            } elseif (!empty($item_config['icon'])) {
                $icon = $item_config['icon'];
            } else {
                $icon = '•';
            }
            
            // Get name and subtitle
            $name_field = $item_config['name_field'] ?? 'name';
            $name = $rel_item[$name_field] ?? '';
            
            $subtitle = '';
            if (!empty($item_config['subtitle_field'])) {
                $subtitle_prefix = $item_config['subtitle_prefix'] ?? '';
                $subtitle = $subtitle_prefix . ($rel_item[$item_config['subtitle_field']] ?? '');
            }
            
            // Link
            $link = '';
            if (!empty($item_config['link'])) {
                $link = str_replace('{id}', $rel_item['id'] ?? '', $item_config['link']);
                $link = home_url($link);
            }
            
            if ($link) {
                ?>
                <a href="<?php echo esc_url($link); ?>" class="sawt-related-item">
                    <div class="sawt-related-item-left">
                        <span class="sawt-related-item-icon"><?php echo esc_html($icon); ?></span>
                        <div class="sawt-related-item-text">
                            <span class="sawt-related-item-name"><?php echo esc_html($name); ?></span>
                            <?php if ($subtitle): ?>
                                <span class="sawt-related-item-subtitle"><?php echo esc_html($subtitle); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="sawt-related-item-arrow">›</span>
                </a>
                <?php
            } else {
                ?>
                <div class="sawt-related-item">
                    <div class="sawt-related-item-left">
                        <span class="sawt-related-item-icon"><?php echo esc_html($icon); ?></span>
                        <div class="sawt-related-item-text">
                            <span class="sawt-related-item-name"><?php echo esc_html($name); ?></span>
                            <?php if ($subtitle): ?>
                                <span class="sawt-related-item-subtitle"><?php echo esc_html($subtitle); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        
        // Show all link
        if (count($items) > $max_items && !empty($config['show_all_link'])) {
            $show_all_url = str_replace('{id}', $parent_item['id'] ?? '', $config['show_all_link']);
            $show_all_url = home_url($show_all_url);
            ?>
            <a href="<?php echo esc_url($show_all_url); ?>" class="sawt-related-show-all">
                <?php echo esc_html(self::tr('show_all', 'Zobrazit vše')); ?> (<?php echo count($items); ?>)
            </a>
            <?php
        }
        
        echo '</div>';
    }
    
    /**
     * Render text block
     */
    private static function render_text_block($config, $item) {
        $field = $config['field'] ?? '';
        $value = self::get_field_value($item, $field);
        
        if (empty($value)) {
            $empty_text = $config['empty_text'] ?? '—';
            echo '<p class="sawt-text-muted">' . esc_html($empty_text) . '</p>';
            return;
        }
        
        $allow_html = !empty($config['allow_html']);
        
        echo '<div class="sawt-text-block">';
        if ($allow_html) {
            echo wp_kses_post($value);
        } else {
            echo nl2br(esc_html($value));
        }
        echo '</div>';
    }
    
    /**
     * Render stat grid
     */
    private static function render_stat_grid($config, $item) {
        $stats = $config['stats'] ?? [];
        
        echo '<div class="sawt-stat-grid">';
        
        foreach ($stats as $stat) {
            $field = $stat['field'] ?? '';
            $value = self::get_field_value($item, $field);
            $label = $stat['label'] ?? ucfirst($field);
            $color = $stat['color'] ?? '';
            
            $card_class = 'sawt-stat-card';
            if ($color) $card_class .= ' is-' . $color;
            ?>
            <div class="<?php echo esc_attr($card_class); ?>">
                <div class="sawt-stat-value"><?php echo esc_html($value ?: '0'); ?></div>
                <div class="sawt-stat-label"><?php echo esc_html($label); ?></div>
            </div>
            <?php
        }
        
        echo '</div>';
    }
    
    /**
     * Render timeline
     */
    private static function render_timeline($config, $items, $parent_item) {
        if (empty($items)) {
            echo '<p class="sawt-text-muted">' . esc_html(self::tr('no_events', 'Žádné události')) . '</p>';
            return;
        }
        
        $item_config = $config['item'] ?? [];
        
        echo '<div class="sawt-timeline">';
        
        foreach ($items as $index => $event) {
            $time_field = $item_config['time_field'] ?? 'created_at';
            $title_field = $item_config['title_field'] ?? 'title';
            $status_field = $item_config['status_field'] ?? 'status';
            
            $time = $event[$time_field] ?? '';
            $title = $event[$title_field] ?? '';
            $status = $event[$status_field] ?? '';
            
            $item_class = 'sawt-timeline-item';
            if ($index === 0) $item_class .= ' is-active';
            if ($status) $item_class .= ' is-' . $status;
            ?>
            <div class="<?php echo esc_attr($item_class); ?>">
                <div class="sawt-timeline-dot"></div>
                <?php if ($time): ?>
                    <div class="sawt-timeline-time"><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($time))); ?></div>
                <?php endif; ?>
                <div class="sawt-timeline-content">
                    <span class="sawt-timeline-title"><?php echo esc_html($title); ?></span>
                </div>
            </div>
            <?php
        }
        
        echo '</div>';
    }
    
    /**
     * Render feature list
     */
    private static function render_feature_list($config, $item) {
        $features = $config['features'] ?? [];
        
        echo '<div class="sawt-feature-list">';
        
        foreach ($features as $feature) {
            $field = $feature['field'] ?? '';
            $enabled = !empty($item[$field]);
            $label = $feature['label'] ?? ucfirst($field);
            
            $item_class = 'sawt-feature-item';
            if (!$enabled) $item_class .= ' is-disabled';
            ?>
            <div class="<?php echo esc_attr($item_class); ?>">
                <span class="sawt-feature-item-icon"><?php echo $enabled ? '✓' : '✗'; ?></span>
                <span class="sawt-feature-item-text"><?php echo esc_html($label); ?></span>
            </div>
            <?php
        }
        
        echo '</div>';
    }
    
    /**
     * Render special section (custom template)
     */
    private static function render_special($config, $item, $related_data, $entity) {
        $template = $config['template'] ?? '';
        
        if (empty($template)) {
            echo '<p class="sawt-text-muted">Special template not configured</p>';
            return;
        }
        
        // Try specifics directory
        $template_path = SAW_VISITORS_PLUGIN_DIR . "includes/components/saw-table/specifics/{$entity}/{$template}.php";
        
        if (!file_exists($template_path)) {
            // Fallback - module directory
            $module_slug = str_replace('_', '-', $entity);
            $template_path = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/specifics/{$template}.php";
        }
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p class="sawt-text-muted">Template not found: ' . esc_html($template) . '</p>';
        }
    }
    
    /**
     * Get field value (supports dot notation)
     */
    private static function get_field_value($item, $field) {
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $item;
            foreach ($parts as $part) {
                $value = $value[$part] ?? null;
                if ($value === null) break;
            }
            return $value;
        }
        return $item[$field] ?? '';
    }
    
    /**
     * Format value based on format type
     */
    private static function format_value($value, $format, $row = [], $item = []) {
        switch ($format) {
            case 'date':
                if (!empty($value)) {
                    $date_format = $row['date_format'] ?? 'j. n. Y';
                    return esc_html(date_i18n($date_format, strtotime($value)));
                }
                return '<span class="sawt-text-muted">—</span>';
                
            case 'datetime':
                if (!empty($value)) {
                    $date_format = $row['date_format'] ?? 'j. n. Y H:i';
                    return esc_html(date_i18n($date_format, strtotime($value)));
                }
                return '<span class="sawt-text-muted">—</span>';
                
            case 'email':
                if (!empty($value)) {
                    return '<a href="mailto:' . esc_attr($value) . '" class="sawt-info-link">' . esc_html($value) . '</a>';
                }
                return '<span class="sawt-text-muted">—</span>';
                
            case 'phone':
                if (!empty($value)) {
                    $clean = preg_replace('/[^0-9+]/', '', $value);
                    return '<a href="tel:' . esc_attr($clean) . '" class="sawt-info-link">' . esc_html($value) . '</a>';
                }
                return '<span class="sawt-text-muted">—</span>';
                
            case 'url':
                if (!empty($value)) {
                    $display = $row['url_display'] ?? parse_url($value, PHP_URL_HOST);
                    return '<a href="' . esc_url($value) . '" target="_blank" class="sawt-info-link">' . esc_html($display) . '</a>';
                }
                return '<span class="sawt-text-muted">—</span>';
                
            case 'color':
                if (!empty($value)) {
                    return sprintf(
                        '<span class="sawt-color-inline">
                            <span class="sawt-color-swatch-sm" style="background-color: %s;"></span>
                            <code class="sawt-code-sm">%s</code>
                        </span>',
                        esc_attr($value),
                        esc_html(strtoupper($value))
                    );
                }
                return '<span class="sawt-text-muted">—</span>';
                
            case 'highlight':
                $color = $row['highlight_color'] ?? 'var(--sawt-success-dark)';
                return sprintf(
                    '<strong class="sawt-info-val-highlight" style="color: %s;">%s</strong>',
                    esc_attr($color),
                    esc_html($value)
                );
                
            case 'boolean':
                $true_label = $row['true_label'] ?? self::tr('yes', 'Ano');
                $false_label = $row['false_label'] ?? self::tr('no', 'Ne');
                return esc_html($value ? $true_label : $false_label);
                
            case 'badge':
                $map = $row['map'] ?? [];
                $badge = $map[$value] ?? null;
                if ($badge) {
                    $color = $badge['color'] ?? 'secondary';
                    $label = $badge['label'] ?? $value;
                    return sprintf(
                        '<span class="sawt-badge sawt-badge-%s">%s</span>',
                        esc_attr($color),
                        esc_html($label)
                    );
                }
                return esc_html($value);
                
            case 'code':
                return '<code class="sawt-code">' . esc_html($value) . '</code>';
                
            case 'currency':
                $currency = $row['currency'] ?? 'Kč';
                $decimals = $row['decimals'] ?? 0;
                return esc_html(number_format(floatval($value), $decimals, ',', ' ') . ' ' . $currency);
                
            case 'number':
                $decimals = $row['decimals'] ?? 0;
                return esc_html(number_format(floatval($value), $decimals, ',', ' '));
                
            default:
                if (empty($value) && $value !== '0') {
                    return '<span class="sawt-text-muted">—</span>';
                }
                return esc_html($value);
        }
    }
    
    /**
     * Evaluate condition
     */
    private static function evaluate_condition($condition, $item) {
        $condition = preg_replace_callback(
            '/\$item\[([\'"])(.+?)\1\]/',
            function($matches) use ($item) {
                $field = $matches[2];
                $value = $item[$field] ?? null;
                if (is_null($value)) return 'null';
                if (is_bool($value)) return $value ? 'true' : 'false';
                if (is_string($value)) return "'" . addslashes($value) . "'";
                if (is_array($value)) return count($value) > 0 ? 'true' : 'false';
                return $value;
            },
            $condition
        );
        
        try {
            return eval("return {$condition};");
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }
    
    /**
     * Check permission
     */
    private static function check_permission($permission) {
        if (!class_exists('SAW_Table_Permissions')) {
            return true;
        }
        
        $parts = explode(':', $permission);
        $action = $parts[0] ?? 'view';
        $module = $parts[1] ?? '';
        
        return SAW_Table_Permissions::can($module, $action);
    }
}
