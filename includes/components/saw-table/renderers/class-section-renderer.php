<?php
/**
 * SAW Table - Section Renderer
 * 
 * Renders industrial sections with saw-table- prefix classes.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     1.1.0
 * @since       5.2.0
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
     * 
     * @param array  $config       Section config
     * @param array  $item         Item data
     * @param array  $related_data Related data
     * @param string $entity       Entity name
     * @return string HTML
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
        
        ob_start();
        ?>
        <div class="saw-table-section<?php echo !empty($config['class']) ? ' ' . esc_attr($config['class']) : ''; ?>">
            <?php if ($type !== self::TYPE_METADATA || !empty($title)): ?>
            <div class="saw-table-section-head">
                <h4 class="saw-table-section-title">
                    <?php echo esc_html($icon); ?>
                    <?php echo esc_html($title); ?>
                    <?php if (!empty($config['show_count'])): ?>
                        <?php 
                        $count = $config['count'] ?? 
                                 (isset($related_data[$config['data_key']]) ? count($related_data[$config['data_key']]) : 0);
                        ?>
                        <span class="saw-table-section-count"><?php echo intval($count); ?></span>
                    <?php endif; ?>
                </h4>
            </div>
            <?php endif; ?>
            <div class="saw-table-section-body">
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
            
            $label = $row['label'] ?? ucfirst($field);
            $format = $row['format'] ?? 'text';
            $bold = !empty($row['bold']);
            
            // Format value
            $formatted = self::format_value($value, $format, $row, $item);
            
            // Suffix
            if (!empty($row['suffix_field'])) {
                $suffix = $item[$row['suffix_field']] ?? '';
                if (!empty($suffix)) {
                    $formatted .= ' <small style="color: #64748b;">' . esc_html($suffix) . '</small>';
                }
            }
            ?>
            <div class="saw-table-info-row">
                <span class="saw-table-info-label"><?php echo esc_html($label); ?></span>
                <span class="saw-table-info-val<?php echo $bold ? ' saw-table-info-val-bold' : ''; ?>">
                    <?php echo $formatted; ?>
                </span>
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
            <div class="saw-table-info-row saw-table-info-row-meta">
                <span class="saw-table-info-label"><?php echo esc_html($row['label']); ?></span>
                <span class="saw-table-info-val"><?php echo esc_html($row['value']); ?></span>
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
            echo '<p class="saw-table-text-muted">' . esc_html($empty_text) . '</p>';
            return;
        }
        
        $max_items = $config['max_items'] ?? 5;
        $displayed = array_slice($items, 0, $max_items);
        $item_config = $config['item'] ?? [];
        
        echo '<div class="saw-table-related-list">';
        
        foreach ($displayed as $rel_item) {
            $icon = '';
            if (!empty($item_config['icon_field']) && !empty($item_config['icon_map'])) {
                $icon_value = $rel_item[$item_config['icon_field']] ?? '';
                $icon = $item_config['icon_map'][$icon_value] ?? ($item_config['icon_map']['default'] ?? '•');
            } elseif (!empty($item_config['icon'])) {
                $icon = $item_config['icon'];
            } else {
                $icon = '•';
            }
            
            $name = $rel_item[$item_config['name_field'] ?? 'name'] ?? '';
            $subtitle = '';
            
            if (!empty($item_config['subtitle_field'])) {
                $subtitle = $rel_item[$item_config['subtitle_field']] ?? '';
                if (!empty($subtitle) && !empty($item_config['subtitle_prefix'])) {
                    $subtitle = $item_config['subtitle_prefix'] . $subtitle;
                }
            }
            
            $link = '';
            if (!empty($item_config['link'])) {
                $link = str_replace('{id}', $rel_item['id'] ?? '', $item_config['link']);
                $link = home_url($link);
            }
            
            if ($link) {
                ?>
                <a href="<?php echo esc_url($link); ?>" class="saw-table-related-item">
                    <span class="saw-table-related-item-icon"><?php echo esc_html($icon); ?></span>
                    <span class="saw-table-related-item-text">
                        <?php echo esc_html($name); ?>
                        <?php if ($subtitle): ?>
                            <small class="saw-table-related-item-subtitle"><?php echo esc_html($subtitle); ?></small>
                        <?php endif; ?>
                    </span>
                    <span class="saw-table-related-item-arrow">→</span>
                </a>
                <?php
            } else {
                ?>
                <div class="saw-table-related-item">
                    <span class="saw-table-related-item-icon"><?php echo esc_html($icon); ?></span>
                    <span class="saw-table-related-item-text">
                        <?php echo esc_html($name); ?>
                        <?php if ($subtitle): ?>
                            <small class="saw-table-related-item-subtitle"><?php echo esc_html($subtitle); ?></small>
                        <?php endif; ?>
                    </span>
                </div>
                <?php
            }
        }
        
        // Show all link
        if (count($items) > $max_items && !empty($config['show_all_link'])) {
            $show_all_url = str_replace('{id}', $parent_item['id'] ?? '', $config['show_all_link']);
            $show_all_url = home_url($show_all_url);
            ?>
            <a href="<?php echo esc_url($show_all_url); ?>" class="saw-table-related-show-all">
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
            echo '<p class="saw-table-text-muted">' . esc_html($empty_text) . '</p>';
            return;
        }
        
        $allow_html = !empty($config['allow_html']);
        
        echo '<div class="saw-table-text-block">';
        if ($allow_html) {
            echo wp_kses_post($value);
        } else {
            echo nl2br(esc_html($value));
        }
        echo '</div>';
    }
    
    /**
     * Render special section (custom template)
     */
    private static function render_special($config, $item, $related_data, $entity) {
        $template = $config['template'] ?? '';
        
        if (empty($template)) {
            echo '<p class="saw-table-text-muted">Special template not configured</p>';
            return;
        }
        
        // Try to find template in specifics directory
        $template_path = SAW_VISITORS_PLUGIN_DIR . "includes/components/saw-table/specifics/{$entity}/{$template}.php";
        
        if (!file_exists($template_path)) {
            // Fallback - try module directory
            $module_slug = str_replace('_', '-', $entity);
            $template_path = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module_slug}/specifics/{$template}.php";
        }
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p class="saw-table-text-muted">Template not found: ' . esc_html($template) . '</p>';
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
                return '—';
                
            case 'datetime':
                if (!empty($value)) {
                    $date_format = $row['date_format'] ?? 'j. n. Y H:i';
                    return esc_html(date_i18n($date_format, strtotime($value)));
                }
                return '—';
                
            case 'email':
                if (!empty($value)) {
                    return '<a href="mailto:' . esc_attr($value) . '" class="saw-table-info-link">' . esc_html($value) . '</a>';
                }
                return '—';
                
            case 'phone':
                if (!empty($value)) {
                    $clean = preg_replace('/[^0-9+]/', '', $value);
                    return '<a href="tel:' . esc_attr($clean) . '" class="saw-table-info-link">' . esc_html($value) . '</a>';
                }
                return '—';
                
            case 'url':
                if (!empty($value)) {
                    $display = $row['url_display'] ?? parse_url($value, PHP_URL_HOST);
                    return '<a href="' . esc_url($value) . '" target="_blank" class="saw-table-info-link">' . esc_html($display) . '</a>';
                }
                return '—';
                
            case 'color':
                if (!empty($value)) {
                    return sprintf(
                        '<span style="display: inline-flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 20px; height: 20px; border-radius: 4px; background-color: %s; border: 2px solid #e5e7eb;"></span>
                            <code style="font-family: monospace; font-size: 12px; color: #64748b;">%s</code>
                        </span>',
                        esc_attr($value),
                        esc_html(strtoupper($value))
                    );
                }
                return '<span class="saw-table-text-muted">—</span>';
                
            case 'highlight':
                $color = $row['highlight_color'] ?? '#059669';
                return sprintf(
                    '<strong style="font-size: 18px; color: %s;">%s</strong>',
                    esc_attr($color),
                    esc_html($value)
                );
                
            case 'boolean':
                $true_label = $row['true_label'] ?? 'Ano';
                $false_label = $row['false_label'] ?? 'Ne';
                return esc_html($value ? $true_label : $false_label);
                
            case 'badge':
                $map = $row['map'] ?? [];
                $badge = $map[$value] ?? null;
                if ($badge) {
                    $color = $badge['color'] ?? 'secondary';
                    $label = $badge['label'] ?? $value;
                    return sprintf(
                        '<span class="saw-table-badge saw-table-badge-%s">%s</span>',
                        esc_attr($color),
                        esc_html($label)
                    );
                }
                return esc_html($value);
                
            case 'code':
                return '<code class="saw-table-info-code">' . esc_html($value) . '</code>';
                
            default:
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