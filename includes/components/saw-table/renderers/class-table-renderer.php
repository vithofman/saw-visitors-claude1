<?php
/**
 * SAW Table - Table Renderer
 * 
 * Renders the main data table with sortable columns, clickable rows,
 * and action buttons. Uses sawt- CSS prefix.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable/Renderers
 * @version     2.0.0 - Updated to sawt- prefix
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Table Renderer Class
 *
 * @since 3.0.0
 */
class SAW_Table_Renderer {
    
    /**
     * Column types
     */
    const TYPE_TEXT = 'text';
    const TYPE_BADGE = 'badge';
    const TYPE_IMAGE = 'image';
    const TYPE_CODE = 'code';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_NUMBER = 'number';
    const TYPE_CURRENCY = 'currency';
    const TYPE_CUSTOM = 'custom';
    const TYPE_COLOR = 'color';
    
    /**
     * Translation function
     * @var callable|null
     */
    private static $translator = null;
    
    /**
     * Set translator function
     */
    public static function set_translator($translator) {
        self::$translator = $translator;
    }
    
    /**
     * Translate key
     */
    private static function tr($key, $fallback = null) {
        if (self::$translator && is_callable(self::$translator)) {
            return call_user_func(self::$translator, $key, $fallback);
        }
        return $fallback ?? $key;
    }
    
    /**
     * Render complete table
     *
     * @param array $config Table configuration
     * @param array $items  Data items
     * @param array $options Additional options
     * @return string HTML
     */
    public static function render($config, $items, $options = []) {
        $entity = $config['entity'] ?? 'items';
        $columns = $config['columns'] ?? [];
        $actions = $config['actions'] ?? [];
        $detail_url = $config['detail_url'] ?? '';
        $edit_url = $config['edit_url'] ?? '';
        $orderby = $options['orderby'] ?? ($config['default_order'] ?? 'id');
        $order = $options['order'] ?? ($config['default_order_dir'] ?? 'DESC');
        $base_url = $options['base_url'] ?? '';
        
        if (empty($columns)) {
            return '<div class="sawt-alert sawt-alert-warning">' . 
                   self::tr('no_columns', 'Žádné sloupce definovány') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="sawt-table-container" data-entity="<?php echo esc_attr($entity); ?>">
            <div class="sawt-table-scroll">
                <table class="sawt-table" data-entity="<?php echo esc_attr($entity); ?>">
                    <?php self::render_thead($columns, $actions, $orderby, $order, $base_url); ?>
                    <?php self::render_tbody($columns, $items, $actions, $detail_url, $edit_url, $entity); ?>
                </table>
            </div>
            
            <?php if (empty($items)): ?>
                <?php self::render_empty_state($config); ?>
            <?php endif; ?>
            
            <?php self::render_infinite_scroll_loader($config); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render table header
     */
    private static function render_thead($columns, $actions, $orderby, $order, $base_url) {
        ?>
        <thead class="sawt-table-header">
            <tr class="sawt-table-header-row">
                <?php foreach ($columns as $key => $column): ?>
                    <?php
                    $label = $column['label'] ?? ucfirst(str_replace('_', ' ', $key));
                    $sortable = !empty($column['sortable']);
                    $width = $column['width'] ?? '';
                    $align = $column['align'] ?? 'left';
                    
                    $th_class = 'sawt-table-th';
                    if ($sortable) $th_class .= ' is-sortable';
                    if ($key === $orderby) $th_class .= ' is-sorted';
                    if ($align !== 'left') $th_class .= ' sawt-text-' . $align;
                    
                    $th_style = $width ? "width: {$width};" : '';
                    
                    // Sort URL
                    $sort_url = '';
                    if ($sortable && $base_url) {
                        $new_order = ($key === $orderby && $order === 'ASC') ? 'DESC' : 'ASC';
                        $sort_url = add_query_arg(['orderby' => $key, 'order' => $new_order], $base_url);
                    }
                    ?>
                    <th class="<?php echo esc_attr($th_class); ?>" 
                        data-column="<?php echo esc_attr($key); ?>"
                        <?php if ($th_style): ?>style="<?php echo esc_attr($th_style); ?>"<?php endif; ?>>
                        <?php if ($sortable && $sort_url): ?>
                            <a href="<?php echo esc_url($sort_url); ?>" class="sawt-table-sort-link">
                                <span class="sawt-table-th-label"><?php echo esc_html($label); ?></span>
                                <?php echo self::get_sort_icon($key, $orderby, $order); ?>
                            </a>
                        <?php else: ?>
                            <span class="sawt-table-th-label"><?php echo esc_html($label); ?></span>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                
                <?php if (!empty($actions)): ?>
                    <th class="sawt-table-th sawt-table-th-actions" style="width: 60px;"></th>
                <?php endif; ?>
            </tr>
        </thead>
        <?php
    }
    
    /**
     * Render table body
     */
    private static function render_tbody($columns, $items, $actions, $detail_url, $edit_url, $entity) {
        ?>
        <tbody class="sawt-table-body" data-entity="<?php echo esc_attr($entity); ?>">
            <?php foreach ($items as $item): ?>
                <?php self::render_row($columns, $item, $actions, $detail_url, $edit_url, $entity); ?>
            <?php endforeach; ?>
        </tbody>
        <?php
    }
    
    /**
     * Render single table row
     */
    public static function render_row($columns, $item, $actions, $detail_url, $edit_url, $entity) {
        $id = $item['id'] ?? 0;
        
        // Build detail URL
        $row_detail_url = '';
        if ($detail_url && $id) {
            $row_detail_url = str_replace('{id}', $id, $detail_url);
        }
        
        // Row classes
        $row_class = 'sawt-table-row';
        if ($row_detail_url) {
            $row_class .= ' is-clickable';
        }
        
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>" 
            data-id="<?php echo esc_attr($id); ?>"
            <?php if ($row_detail_url): ?>
            data-detail-url="<?php echo esc_url($row_detail_url); ?>"
            <?php endif; ?>>
            
            <?php foreach ($columns as $key => $column): ?>
                <?php self::render_cell($key, $column, $item); ?>
            <?php endforeach; ?>
            
            <?php if (!empty($actions)): ?>
                <td class="sawt-table-td sawt-table-td-actions">
                    <?php self::render_actions($actions, $item, $edit_url, $entity); ?>
                </td>
            <?php endif; ?>
        </tr>
        <?php
    }
    
    /**
     * Render table cell
     */
    private static function render_cell($key, $column, $item) {
        $type = $column['type'] ?? self::TYPE_TEXT;
        $align = $column['align'] ?? 'left';
        $class = $column['class'] ?? '';
        $bold = !empty($column['bold']);
        
        $td_class = 'sawt-table-td';
        if ($align !== 'left') $td_class .= ' sawt-text-' . $align;
        if ($class) $td_class .= ' ' . $class;
        if ($bold) $td_class .= ' sawt-font-semibold';
        
        $value = $item[$key] ?? '';
        
        ?>
        <td class="<?php echo esc_attr($td_class); ?>" data-column="<?php echo esc_attr($key); ?>">
            <?php echo self::format_cell_value($value, $type, $column, $item); ?>
        </td>
        <?php
    }
    
    /**
     * Format cell value based on type
     */
    private static function format_cell_value($value, $type, $column, $item) {
        switch ($type) {
            case self::TYPE_IMAGE:
                return self::format_image($value, $column);
            case self::TYPE_BADGE:
                return self::format_badge($value, $column);
            case self::TYPE_CODE:
                return self::format_code($value);
            case self::TYPE_DATE:
                return self::format_date($value);
            case self::TYPE_DATETIME:
                return self::format_datetime($value);
            case self::TYPE_BOOLEAN:
                return self::format_boolean($value, $column);
            case self::TYPE_NUMBER:
                return self::format_number($value, $column);
            case self::TYPE_CURRENCY:
                return self::format_currency($value, $column);
            case self::TYPE_COLOR:
                return self::format_color($value);
            case self::TYPE_CUSTOM:
                return self::format_custom($value, $column, $item);
            default:
                if (empty($value) && $value !== '0') {
                    return '<span class="sawt-text-muted">—</span>';
                }
                return esc_html($value);
        }
    }
    
    /**
     * Format image cell
     */
    private static function format_image($value, $column) {
        if (empty($value)) {
            $fallback = $column['fallback'] ?? '';
            if ($fallback) {
                return '<span class="sawt-table-image-fallback">' . esc_html($fallback) . '</span>';
            }
            return '<span class="sawt-text-muted">—</span>';
        }
        
        $size = $column['size'] ?? '40px';
        $rounded = !empty($column['rounded']);
        $style = "width: {$size}; height: {$size}; object-fit: cover;";
        $style .= $rounded ? ' border-radius: 50%;' : ' border-radius: 4px;';
        
        return sprintf(
            '<img src="%s" alt="" class="sawt-table-image" style="%s">',
            esc_url($value),
            esc_attr($style)
        );
    }
    
    /**
     * Format badge cell
     */
    private static function format_badge($value, $column) {
        $map = $column['map'] ?? [];
        $badge_config = $map[$value] ?? null;
        
        if ($badge_config) {
            $label = $badge_config['label'] ?? $value;
            $color = $badge_config['color'] ?? 'secondary';
            $icon = $badge_config['icon'] ?? '';
            
            return sprintf(
                '<span class="sawt-badge sawt-badge-%s">%s%s</span>',
                esc_attr($color),
                $icon ? esc_html($icon) . ' ' : '',
                esc_html($label)
            );
        }
        
        if (empty($value) && $value !== '0') {
            return '<span class="sawt-text-muted">—</span>';
        }
        
        return '<span class="sawt-badge sawt-badge-secondary">' . esc_html($value) . '</span>';
    }
    
    /**
     * Format code cell
     */
    private static function format_code($value) {
        if (empty($value) && $value !== '0') {
            return '<span class="sawt-text-muted">—</span>';
        }
        return '<code class="sawt-code">' . esc_html($value) . '</code>';
    }
    
    /**
     * Format date cell
     */
    private static function format_date($value) {
        if (empty($value)) {
            return '<span class="sawt-text-muted">—</span>';
        }
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        if (!$timestamp) {
            return '<span class="sawt-text-muted">—</span>';
        }
        return esc_html(date_i18n('j. n. Y', $timestamp));
    }
    
    /**
     * Format datetime cell
     */
    private static function format_datetime($value) {
        if (empty($value)) {
            return '<span class="sawt-text-muted">—</span>';
        }
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        if (!$timestamp) {
            return '<span class="sawt-text-muted">—</span>';
        }
        return esc_html(date_i18n('j. n. Y H:i', $timestamp));
    }
    
    /**
     * Format boolean cell
     */
    private static function format_boolean($value, $column) {
        $true_label = $column['true_label'] ?? '✓';
        $false_label = $column['false_label'] ?? '✗';
        $true_color = $column['true_color'] ?? 'success';
        $false_color = $column['false_color'] ?? 'secondary';
        
        if ($value) {
            return '<span class="sawt-badge sawt-badge-' . esc_attr($true_color) . '">' . esc_html($true_label) . '</span>';
        }
        return '<span class="sawt-badge sawt-badge-' . esc_attr($false_color) . '">' . esc_html($false_label) . '</span>';
    }
    
    /**
     * Format number cell
     */
    private static function format_number($value, $column) {
        if ($value === '' || $value === null) {
            return '<span class="sawt-text-muted">—</span>';
        }
        $decimals = $column['decimals'] ?? 0;
        return esc_html(number_format(floatval($value), $decimals, ',', ' '));
    }
    
    /**
     * Format currency cell
     */
    private static function format_currency($value, $column) {
        if ($value === '' || $value === null) {
            return '<span class="sawt-text-muted">—</span>';
        }
        $currency = $column['currency'] ?? 'Kč';
        $decimals = $column['decimals'] ?? 0;
        $formatted = number_format(floatval($value), $decimals, ',', ' ');
        return '<span class="sawt-currency">' . esc_html($formatted . ' ' . $currency) . '</span>';
    }
    
    /**
     * Format color cell
     */
    private static function format_color($value) {
        if (empty($value)) {
            return '<span class="sawt-text-muted">—</span>';
        }
        return sprintf(
            '<div class="sawt-color-swatch" style="background-color: %s;" title="%s"></div>',
            esc_attr($value),
            esc_attr($value)
        );
    }
    
    /**
     * Format custom cell (callback)
     */
    private static function format_custom($value, $column, $item) {
        $callback = $column['callback'] ?? null;
        if ($callback && is_callable($callback)) {
            return call_user_func($callback, $value, $item, $column);
        }
        if (empty($value) && $value !== '0') {
            return '<span class="sawt-text-muted">—</span>';
        }
        return esc_html($value);
    }
    
    /**
     * Render action buttons
     */
    private static function render_actions($actions, $item, $edit_url, $entity) {
        $id = $item['id'] ?? 0;
        ?>
        <div class="sawt-table-actions">
            <button type="button" class="sawt-table-action-toggle" aria-label="<?php echo esc_attr(self::tr('actions', 'Akce')); ?>">
                <span class="sawt-table-action-dots">⋮</span>
            </button>
            <div class="sawt-table-action-menu">
                <?php foreach ($actions as $action): ?>
                    <?php
                    $action_type = is_string($action) ? $action : ($action['type'] ?? $action);
                    
                    switch ($action_type) {
                        case 'view':
                            ?>
                            <button type="button" class="sawt-table-action-item" data-action="view" data-id="<?php echo esc_attr($id); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php echo esc_html(self::tr('action_view', 'Zobrazit')); ?>
                            </button>
                            <?php
                            break;
                            
                        case 'edit':
                            $item_edit_url = $edit_url ? str_replace('{id}', $id, $edit_url) : '#';
                            ?>
                            <a href="<?php echo esc_url($item_edit_url); ?>" class="sawt-table-action-item" data-action="edit" data-id="<?php echo esc_attr($id); ?>">
                                <span class="dashicons dashicons-edit"></span>
                                <?php echo esc_html(self::tr('action_edit', 'Upravit')); ?>
                            </a>
                            <?php
                            break;
                            
                        case 'delete':
                            ?>
                            <button type="button" class="sawt-table-action-item sawt-table-action-delete" 
                                    data-action="delete" 
                                    data-id="<?php echo esc_attr($id); ?>"
                                    data-entity="<?php echo esc_attr($entity); ?>"
                                    data-confirm="<?php echo esc_attr(self::tr('confirm_delete', 'Opravdu chcete smazat tento záznam?')); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                <?php echo esc_html(self::tr('action_delete', 'Smazat')); ?>
                            </button>
                            <?php
                            break;
                    }
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get sort icon
     */
    private static function get_sort_icon($column, $orderby, $order) {
        if ($column !== $orderby) {
            return '<span class="sawt-table-sort-icon">⇅</span>';
        }
        if ($order === 'ASC') {
            return '<span class="sawt-table-sort-icon is-asc">↑</span>';
        }
        return '<span class="sawt-table-sort-icon is-desc">↓</span>';
    }
    
    /**
     * Render empty state
     */
    private static function render_empty_state($config) {
        $message = $config['empty_message'] ?? self::tr('empty_message', 'Žádné záznamy nenalezeny');
        $icon = $config['empty_icon'] ?? '📭';
        ?>
        <div class="sawt-empty-state">
            <div class="sawt-empty-state-icon"><?php echo esc_html($icon); ?></div>
            <div class="sawt-empty-state-title"><?php echo esc_html($message); ?></div>
        </div>
        <?php
    }
    
    /**
     * Render infinite scroll loader
     */
    private static function render_infinite_scroll_loader($config) {
        $infinite_scroll = $config['infinite_scroll'] ?? [];
        if (empty($infinite_scroll['enabled'])) return;
        ?>
        <div class="sawt-infinite-scroll-loader sawt-hidden">
            <div class="sawt-spinner"></div>
            <span><?php echo esc_html(self::tr('loading', 'Načítání...')); ?></span>
        </div>
        <div class="sawt-infinite-scroll-trigger"></div>
        <?php
    }
    
    /**
     * Render rows only (for AJAX infinite scroll)
     */
    public static function render_rows($columns, $items, $actions, $detail_url, $edit_url, $entity) {
        ob_start();
        foreach ($items as $item) {
            self::render_row($columns, $item, $actions, $detail_url, $edit_url, $entity);
        }
        return ob_get_clean();
    }
}
