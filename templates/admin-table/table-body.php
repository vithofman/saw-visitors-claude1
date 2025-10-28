<?php
/**
 * Admin Table - Table Body Template
 * 
 * Tabulka s řádky a sloupci - kopíruje design z customers-list.php
 * 
 * @package SAW_Visitors
 * @version 4.6.1
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
                            <a href="<?php echo esc_url(SAW_Admin_Table::get_sort_url($column_key, $orderby, $order)); ?>" 
                               class="saw-sort-link" 
                               data-column="<?php echo esc_attr($column_key); ?>">
                                <?php echo esc_html($label); ?>
                                <?php echo SAW_Admin_Table::get_sort_icon($column_key, $orderby, $order); ?>
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
                                    V systému zatím nejsou žádné <?php echo esc_html(strtolower($config['plural'] ?? 'záznamy')); ?>.
                                <?php endif; ?>
                            </p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr data-id="<?php echo esc_attr($row['id']); ?>">
                        <?php foreach ($columns as $column_key => $column_config): ?>
                            <?php
                            $value = $row[$column_key] ?? '';
                            $type = $column_config['type'] ?? 'text';
                            $align = $column_config['align'] ?? 'left';
                            $td_class = 'saw-td-' . esc_attr($column_key);
                            if ($align === 'center') {
                                $td_class .= ' saw-text-center';
                            }
                            ?>
                            <td class="<?php echo esc_attr($td_class); ?>">
                                <?php
                                // Render value podle typu
                                switch ($type) {
                                    case 'date':
                                        echo !empty($value) ? esc_html(date('d.m.Y', strtotime($value))) : '—';
                                        break;
                                    
                                    case 'datetime':
                                        echo !empty($value) ? esc_html(date('d.m.Y H:i', strtotime($value))) : '—';
                                        break;
                                    
                                    case 'bool':
                                        echo $value ? '✅ Ano' : '❌ Ne';
                                        break;
                                    
                                    case 'color':
                                        if (!empty($value)) {
                                            echo '<div class="saw-color-preview" style="background-color: ' . esc_attr($value) . ';">';
                                            echo '<span>' . esc_html($value) . '</span>';
                                            echo '</div>';
                                        } else {
                                            echo '—';
                                        }
                                        break;
                                    
                                    case 'image':
                                    case 'logo':
                                        if (!empty($value)) {
                                            $alt = $column_config['alt'] ?? '';
                                            echo '<img src="' . esc_url($value) . '" alt="' . esc_attr($alt) . '" class="saw-' . esc_attr($type) . '">';
                                        } else {
                                            echo '<div class="saw-' . esc_attr($type) . '-placeholder">';
                                            echo '<span class="dashicons dashicons-format-image"></span>';
                                            echo '</div>';
                                        }
                                        break;
                                    
                                    case 'custom':
                                        // Allow custom rendering via callback
                                        if (isset($column_config['render']) && is_callable($column_config['render'])) {
                                            echo $column_config['render']($value, $row);
                                        } else {
                                            echo esc_html($value);
                                        }
                                        break;
                                    
                                    default:
                                        echo !empty($value) ? esc_html($value) : '—';
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($actions)): ?>
                            <td class="saw-td-actions saw-text-center">
                                <div class="saw-actions">
                                    <?php if (in_array('edit', $actions) && !empty($edit_url)): ?>
                                        <?php 
                                        $edit_link = str_replace('{id}', $row['id'], $edit_url);
                                        ?>
                                        <a 
                                            href="<?php echo esc_url($edit_link); ?>" 
                                            class="saw-btn saw-btn-sm saw-btn-secondary"
                                            title="Upravit"
                                        >
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array('delete', $actions)): ?>
                                        <button 
                                            type="button"
                                            class="saw-btn saw-btn-sm saw-btn-danger saw-delete-<?php echo esc_attr($entity); ?>"
                                            data-<?php echo esc_attr($entity); ?>-id="<?php echo esc_attr($row['id']); ?>"
                                            data-<?php echo esc_attr($entity); ?>-name="<?php echo esc_attr($row['name'] ?? $row['id']); ?>"
                                            title="Smazat"
                                        >
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Custom actions
                                    if (isset($column_config['custom_actions']) && is_array($column_config['custom_actions'])) {
                                        foreach ($column_config['custom_actions'] as $custom_action) {
                                            if (is_callable($custom_action)) {
                                                echo $custom_action($row);
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>