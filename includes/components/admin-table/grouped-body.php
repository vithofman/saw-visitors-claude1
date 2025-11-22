<?php
/**
 * Admin Table - Grouped Body Template
 * Table with collapsible group headers
 * 
 * @package SAW_Visitors
 * @since 7.0.0
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$columns = $config['columns'] ?? array();
$grouped_data = $config['grouped_data'] ?? array();
$grouping_config = $config['grouping'] ?? array();
$show_count = $grouping_config['show_count'] ?? true;
$default_collapsed = $grouping_config['default_collapsed'] ?? true;
$orderby = $config['orderby'] ?? '';
$order = $config['order'] ?? 'ASC';
$base_url = $config['base_url'] ?? '';
$current_params = $config['current_params'] ?? array();

// Get table instance for helper methods
$table_instance = isset($config['_table_instance']) ? $config['_table_instance'] : null;

// Calculate colspan
$colspan = count($columns);

// Helper function to get sort URL
$get_sort_url = function($column) use ($table_instance, $orderby, $order, $base_url, $current_params) {
    if ($table_instance && method_exists($table_instance, 'get_sort_url_for_template')) {
        return $table_instance->get_sort_url_for_template($column);
    }
    
    // Fallback: build URL manually
    $new_order = 'ASC';
    if ($column === $orderby) {
        $new_order = ($order === 'ASC') ? 'DESC' : 'ASC';
    }
    
    $sort_params = array_merge($current_params, array(
        'orderby' => $column,
        'order' => $new_order,
    ));
    unset($sort_params['paged']);
    
    if (empty($sort_params)) {
        return $base_url;
    }
    return $base_url . '?' . http_build_query($sort_params);
};

// Helper function to get sort icon
$get_sort_icon = function($column) use ($table_instance, $orderby, $order) {
    if ($table_instance && method_exists($table_instance, 'get_sort_icon_for_template')) {
        return $table_instance->get_sort_icon_for_template($column);
    }
    
    // Fallback
    if ($column !== $orderby) {
        return '<span class="dashicons dashicons-sort saw-sort-icon"></span>';
    }
    if ($order === 'ASC') {
        return '<span class="dashicons dashicons-arrow-up saw-sort-icon"></span>';
    }
    return '<span class="dashicons dashicons-arrow-down saw-sort-icon"></span>';
};

// Helper function to render cell
$render_cell = function($row, $key, $column) use ($table_instance) {
    if ($table_instance && method_exists($table_instance, 'render_table_cell_for_template')) {
        $table_instance->render_table_cell_for_template($row, $key, $column);
        return;
    }
    
    // Fallback: basic rendering
    $value = $row[$key] ?? '';
    $type = is_array($column) ? ($column['type'] ?? 'text') : 'text';
    $align = is_array($column) && isset($column['align']) ? $column['align'] : 'left';
    $class = is_array($column) && isset($column['class']) ? $column['class'] : '';
    
    $td_class = $class ? ' class="' . esc_attr($class) . '"' : '';
    echo '<td' . $td_class . ' style="text-align: ' . esc_attr($align) . ';">';
    
    // Basic type handling
    switch ($type) {
        case 'custom':
            if (is_array($column) && isset($column['callback']) && is_callable($column['callback'])) {
                echo $column['callback']($value, $row);
            } else {
                echo esc_html($value);
            }
            break;
        case 'badge':
            if ($value !== '' && $value !== null) {
                $badge_class = 'saw-badge';
                if (is_array($column) && isset($column['map'][$value])) {
                    $badge_class .= ' saw-badge-' . $column['map'][$value];
                }
                $label = isset($column['labels'][$value]) ? $column['labels'][$value] : $value;
                echo '<span class="' . esc_attr($badge_class) . '">' . esc_html($label) . '</span>';
            }
            break;
        case 'date':
            if (!empty($value) && $value !== '0000-00-00' && $value !== '0000-00-00 00:00:00') {
                $format = is_array($column) && isset($column['format']) ? $column['format'] : 'd.m.Y';
                echo esc_html(date_i18n($format, strtotime($value)));
            }
            break;
        case 'boolean':
            echo $value ? '<span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>' 
                        : '<span class="dashicons dashicons-dismiss" style="color: #ef4444;"></span>';
            break;
        case 'email':
            if (!empty($value)) {
                echo '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
            }
            break;
        case 'image':
            if (!empty($value)) {
                echo '<img src="' . esc_url($value) . '" alt="" class="saw-table-image">';
            }
            break;
        default:
            echo esc_html($value);
            break;
    }
    
    echo '</td>';
};

?>

<div class="saw-table-responsive-wrapper saw-table-grouped">
    <table class="saw-admin-table">
        <thead>
            <tr>
                <?php foreach ($columns as $column_key => $column_config): ?>
                    <?php
                    $sortable = is_array($column_config) && isset($column_config['sortable']) ? $column_config['sortable'] : false;
                    $label = is_array($column_config) ? ($column_config['label'] ?? ucfirst($column_key)) : $column_config;
                    $width = is_array($column_config) && isset($column_config['width']) ? $column_config['width'] : '';
                    $align = is_array($column_config) && isset($column_config['align']) ? $column_config['align'] : 'left';
                    $class = 'saw-th-' . esc_attr($column_key);
                    if ($sortable) {
                        $class .= ' saw-sortable';
                    }
                    ?>
                    <th class="<?php echo esc_attr($class); ?>" style="<?php echo $width ? 'width: ' . esc_attr($width) . ';' : ''; ?> text-align: <?php echo esc_attr($align); ?>;">
                        <?php if ($sortable): ?>
                            <a href="<?php echo esc_url($get_sort_url($column_key)); ?>" class="saw-sortable">
                                <?php echo esc_html($label); ?>
                                <?php echo $get_sort_icon($column_key); ?>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($label); ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        
        <tbody id="saw-<?php echo esc_attr($entity); ?>-tbody">
            <?php 
            $group_index = 0;
            foreach ($grouped_data as $group_id => $group): 
                $is_first = ($group_index === 0);
                $is_expanded = $is_first && !$default_collapsed;
                $group_class = $is_expanded ? 'saw-group-expanded' : 'saw-group-collapsed';
                $row_class = $is_expanded ? '' : 'saw-group-hidden';
                $group_index++;
            ?>
                <!-- GROUP HEADER -->
                <tr class="saw-group-header <?php echo esc_attr($group_class); ?>" 
                    data-group-id="group_<?php echo esc_attr($group_id); ?>"
                    data-group-count="<?php echo esc_attr($group['count']); ?>">
                    <td colspan="<?php echo esc_attr($colspan); ?>">
                        <div class="saw-group-title">
                            <span class="saw-group-toggle">
                                <svg class="saw-group-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="saw-group-label"><?php echo esc_html($group['label']); ?></span>
                            <?php if ($show_count): ?>
                                <span class="saw-group-count"><?php echo esc_html($group['count']); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                
                <!-- GROUP ROWS -->
                <?php foreach ($group['items'] as $item): ?>
                    <?php
                    $row_id = $item['id'] ?? 0;
                    $detail_link = $detail_url ? str_replace('{id}', $row_id, $detail_url) : '';
                    
                    $row_class_full = 'saw-group-row ' . $row_class;
                    if (!empty($detail_link)) {
                        $row_class_full .= ' saw-clickable-row';
                    }
                    ?>
                    <tr class="<?php echo esc_attr($row_class_full); ?>" 
                        data-group-id="group_<?php echo esc_attr($group_id); ?>"
                        data-id="<?php echo esc_attr($row_id); ?>"
                        <?php if ($detail_link): ?>
                            data-detail-url="<?php echo esc_url($detail_link); ?>"
                        <?php endif; ?>>
                        
                        <?php foreach ($columns as $column_key => $column_config): ?>
                            <?php $render_cell($item, $column_key, $column_config); ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

