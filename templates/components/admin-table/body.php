<?php
/**
 * Admin Table - Body Template
 * Table rows and columns
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 * @version ENHANCED - Clickable rows + Modal detail
 */

if (!defined('ABSPATH')) {
    exit;
}

$columns = $config['columns'] ?? array();
$rows = $config['rows'] ?? array();
$actions = $config['actions'] ?? array();
$edit_url = $config['edit_url'] ?? '';
$orderby = $config['orderby'] ?? '';
$order = $config['order'] ?? 'ASC';

// ✨ NOVÉ: Callback funkce pro custom class a style na řádcích
$row_class_callback = $config['row_class_callback'] ?? null;
$row_style_callback = $config['row_style_callback'] ?? null;

// ✨ NOVÉ: Detail modal enable flag
$enable_detail_modal = $config['enable_detail_modal'] ?? false;
?>

<div class="saw-table-responsive" id="saw-<?php echo esc_attr($entity); ?>-table-wrapper">
    <table class="saw-table saw-table-sortable">
        <thead>
            <tr>
                <?php foreach ($columns as $column_key => $column_config): ?>
                    <?php
                    $sortable = $column_config['sortable'] ?? false;
                    $label = $column_config['label'] ?? ucfirst($column_key);
                    $align = $column_config['align'] ?? 'left';
                    $class = 'saw-th-' . esc_attr($column_key);
                    if ($sortable) {
                        $class .= ' saw-sortable';
                    }
                    if ($align === 'center') {
                        $class .= ' saw-text-center';
                    }
                    ?>
                    <th class="<?php echo esc_attr($class); ?>">
                        <?php if ($sortable): ?>
                            <a href="<?php echo esc_url(SAW_Component_Admin_Table::get_sort_url($column_key, $orderby, $order)); ?>" 
                               data-column="<?php echo esc_attr($column_key); ?>">
                                <?php echo esc_html($label); ?>
                                <?php echo SAW_Component_Admin_Table::get_sort_icon($column_key, $orderby, $order); ?>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($label); ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                
                <?php if (!empty($actions)): ?>
                    <th class="saw-th-actions saw-text-center">Akce</th>
                <?php endif; ?>
            </tr>
        </thead>
        
        <tbody id="saw-<?php echo esc_attr($entity); ?>-tbody">
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?php echo count($columns) + (empty($actions) ? 0 : 1); ?>">
                        <div class="saw-empty-state">
                            <span class="dashicons dashicons-info"></span>
                            <h3>Žádné záznamy nenalezeny</h3>
                            <p>
                                <?php if (!empty($config['search_value'])): ?>
                                    Pro hledaný výraz nebyly nalezeny žádné výsledky.
                                <?php else: ?>
                                    V systému zatím nejsou žádné <?php echo esc_html(strtolower($config['plural'] ?? $entity)); ?>.
                                <?php endif; ?>
                            </p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    // ✨ NOVÉ: Aplikace custom class a style na řádek
                    $row_class = 'saw-table-row';
                    $row_style = '';
                    
                    // ✨ NOVÉ: Pokud je zapnutý modal detail, přidej clickable class
                    if ($enable_detail_modal) {
                        $row_class .= ' saw-row-clickable';
                    }
                    
                    if (is_callable($row_class_callback)) {
                        $custom_class = call_user_func($row_class_callback, $row);
                        if ($custom_class) {
                            $row_class .= ' ' . $custom_class;
                        }
                    }
                    
                    if (is_callable($row_style_callback)) {
                        $row_style = call_user_func($row_style_callback, $row);
                    }
                    ?>
                    <tr class="<?php echo esc_attr($row_class); ?>" 
                        style="<?php echo esc_attr($row_style); ?>"
                        data-id="<?php echo esc_attr($row['id'] ?? ''); ?>"
                        data-entity="<?php echo esc_attr($entity); ?>"
                        <?php if ($enable_detail_modal): ?>
                            data-row-data="<?php echo esc_attr(json_encode($row)); ?>"
                        <?php endif; ?>>
                        
                        <?php foreach ($columns as $column_key => $column_config): ?>
                            <?php
                            $value = $row[$column_key] ?? '';
                            $type = $column_config['type'] ?? 'text';
                            $align = $column_config['align'] ?? 'left';
                            $cell_class = 'saw-td-' . esc_attr($column_key);
                            if ($align === 'center') {
                                $cell_class .= ' saw-text-center';
                            }
                            ?>
                            <td class="<?php echo esc_attr($cell_class); ?>">
                                <?php
                                // Render podle typu
                                switch ($type) {
                                    case 'logo':
                                        if (!empty($value)) {
                                            $alt = $column_config['alt'] ?? 'Logo';
                                            echo '<img src="' . esc_url($value) . '" alt="' . esc_attr($alt) . '" class="saw-table-logo">';
                                        } else {
                                            echo '<span class="saw-text-muted">—</span>';
                                        }
                                        break;
                                        
                                    case 'color':
                                        if (!empty($value)) {
                                            echo '<span class="saw-color-badge" style="background-color: ' . esc_attr($value) . ';" title="' . esc_attr($value) . '"></span>';
                                        } else {
                                            echo '<span class="saw-text-muted">—</span>';
                                        }
                                        break;
                                        
                                    case 'custom':
                                        if (isset($column_config['render']) && is_callable($column_config['render'])) {
                                            echo call_user_func($column_config['render'], $value, $row);
                                        } else {
                                            echo esc_html($value);
                                        }
                                        break;
                                        
                                    default:
                                        echo esc_html($value);
                                        break;
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($actions)): ?>
                            <td class="saw-td-actions saw-text-center">
                                <div class="saw-action-buttons">
                                    <?php foreach ($actions as $action): ?>
                                        <?php if ($action === 'edit' && !empty($edit_url)): ?>
                                            <a href="<?php echo esc_url(str_replace('{id}', $row['id'] ?? '', $edit_url)); ?>" 
                                               class="saw-btn saw-btn-sm saw-btn-icon saw-action-edit" 
                                               title="Upravit"
                                               onclick="event.stopPropagation();">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                        <?php elseif ($action === 'delete'): ?>
                                            <button type="button" 
                                                    class="saw-btn saw-btn-sm saw-btn-icon saw-btn-danger saw-delete-btn saw-action-delete" 
                                                    data-id="<?php echo esc_attr($row['id'] ?? ''); ?>"
                                                    data-name="<?php echo esc_attr($row['name'] ?? ''); ?>"
                                                    title="Smazat"
                                                    onclick="event.stopPropagation();">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>