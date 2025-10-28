<?php
/**
 * Data Table Helper Functions
 * Reusable functions for rendering table elements
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render search box
 */
function saw_render_search_box($table_id, $placeholder = 'Hledat...', $current_value = '') {
    ?>
    <div class="saw-search-input-wrapper">
        <input 
            type="text" 
            id="saw-<?php echo esc_attr($table_id); ?>-search" 
            value="<?php echo esc_attr($current_value); ?>" 
            placeholder="<?php echo esc_attr($placeholder); ?>"
            class="saw-search-input"
            autocomplete="off"
        >
        <button 
            type="button" 
            id="saw-<?php echo esc_attr($table_id); ?>-search-clear" 
            class="saw-search-clear" 
            style="display: <?php echo !empty($current_value) ? 'flex' : 'none'; ?>;"
        >
            <span class="dashicons dashicons-no-alt"></span>
        </button>
        <div class="saw-search-spinner" style="display: none;">
            <span class="spinner is-active"></span>
        </div>
    </div>
    <?php
}

/**
 * Render sortable table header
 */
function saw_render_table_header($columns, $current_orderby = '', $current_order = 'ASC') {
    echo '<thead><tr>';
    
    foreach ($columns as $key => $column) {
        $classes = array();
        $styles = array();
        
        if (isset($column['sortable']) && $column['sortable']) {
            $classes[] = 'saw-sortable';
        }
        
        if (isset($column['width'])) {
            $styles[] = 'width: ' . esc_attr($column['width']);
        }
        
        if (isset($column['align']) && $column['align'] === 'center') {
            $classes[] = 'saw-text-center';
        }
        
        $class_attr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        $style_attr = !empty($styles) ? ' style="' . implode('; ', $styles) . '"' : '';
        
        echo '<th' . $class_attr . $style_attr;
        
        if (isset($column['sortable']) && $column['sortable']) {
            echo ' data-column="' . esc_attr($key) . '"';
        }
        
        if (isset($column['label'])) {
            echo ' data-label="' . esc_attr($column['label']) . '"';
        }
        
        echo '>';
        
        if (isset($column['sortable']) && $column['sortable']) {
            echo '<a href="#">';
            echo esc_html($column['label']);
            echo ' ' . saw_get_sort_icon($key, $current_orderby, $current_order);
            echo '</a>';
        } else {
            echo esc_html($column['label'] ?? '');
        }
        
        echo '</th>';
    }
    
    echo '</tr></thead>';
}

/**
 * Get sort icon based on current state
 */
function saw_get_sort_icon($column, $current_orderby, $current_order) {
    if ($current_orderby !== $column) {
        return '<span class="saw-sort-icon">⇅</span>';
    }
    
    return $current_order === 'ASC' 
        ? '<span class="saw-sort-icon saw-sort-asc">▲</span>' 
        : '<span class="saw-sort-icon saw-sort-desc">▼</span>';
}

/**
 * Render pagination
 */
function saw_render_pagination($table_id, $current_page, $total_pages) {
    if ($total_pages <= 1) {
        return;
    }
    
    ?>
    <div class="saw-pagination" id="saw-<?php echo esc_attr($table_id); ?>-pagination">
        <?php
        // Previous button
        $prev_disabled = $current_page <= 1 ? 'disabled' : '';
        ?>
        <button 
            type="button" 
            class="saw-pagination-btn saw-pagination-prev" 
            <?php echo $prev_disabled; ?> 
            data-page="<?php echo ($current_page - 1); ?>"
        >
            <span class="dashicons dashicons-arrow-left-alt2"></span> Předchozí
        </button>
        
        <div class="saw-pagination-pages">
            <?php
            $start = max(1, $current_page - 2);
            $end = min($total_pages, $current_page + 2);
            
            // First page
            if ($start > 1) {
                echo '<button type="button" class="saw-pagination-btn" data-page="1">1</button>';
                if ($start > 2) {
                    echo '<span class="saw-pagination-dots">...</span>';
                }
            }
            
            // Page range
            for ($i = $start; $i <= $end; $i++) {
                $active = $i === $current_page ? 'active' : '';
                echo '<button type="button" class="saw-pagination-btn ' . $active . '" data-page="' . $i . '">' . $i . '</button>';
            }
            
            // Last page
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) {
                    echo '<span class="saw-pagination-dots">...</span>';
                }
                echo '<button type="button" class="saw-pagination-btn" data-page="' . $total_pages . '">' . $total_pages . '</button>';
            }
            ?>
        </div>
        
        <?php
        // Next button
        $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
        ?>
        <button 
            type="button" 
            class="saw-pagination-btn saw-pagination-next" 
            <?php echo $next_disabled; ?> 
            data-page="<?php echo ($current_page + 1); ?>"
        >
            Další <span class="dashicons dashicons-arrow-right-alt2"></span>
        </button>
    </div>
    <?php
}

/**
 * Render empty state
 */
function saw_render_empty_state($icon = 'dashicons-list-view', $title = 'Žádné záznamy', $message = '', $is_search_result = false) {
    if ($is_search_result && empty($message)) {
        $message = 'Nebyli nalezeni žádné záznamy odpovídající hledanému výrazu.';
    }
    
    ?>
    <div class="saw-empty-state">
        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
        <h3><?php echo esc_html($title); ?></h3>
        <?php if ($message): ?>
            <p><?php echo esc_html($message); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render loading overlay
 */
function saw_render_loading_overlay($table_id, $message = 'Načítám data...') {
    ?>
    <div id="saw-<?php echo esc_attr($table_id); ?>-loading" class="saw-loading-overlay" style="display: none;">
        <div class="saw-loading-spinner">
            <span class="spinner is-active"></span>
            <p><?php echo esc_html($message); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Render badge
 */
function saw_render_badge($text, $type = 'secondary') {
    $valid_types = array('success', 'warning', 'danger', 'info', 'secondary');
    if (!in_array($type, $valid_types)) {
        $type = 'secondary';
    }
    
    echo '<span class="saw-badge saw-badge-' . esc_attr($type) . '">';
    echo esc_html($text);
    echo '</span>';
}

/**
 * Render avatar/logo
 */
function saw_render_avatar($url = '', $alt = '', $type = 'user') {
    if (empty($url)) {
        $icon = $type === 'company' ? 'dashicons-building' : 'dashicons-businessman';
        echo '<div class="saw-table-avatar saw-table-avatar-empty">';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '</div>';
    } else {
        echo '<div class="saw-table-avatar">';
        echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '">';
        echo '</div>';
    }
}

/**
 * Render action buttons
 */
function saw_render_action_buttons($item_id, $actions = array(), $table_name = '') {
    echo '<div class="saw-table-actions">';
    
    foreach ($actions as $action => $config) {
        if ($action === 'edit') {
            $url = isset($config['url']) ? $config['url'] : home_url('/admin/settings/' . $table_name . '/edit/?id=' . $item_id);
            echo '<a href="' . esc_url($url) . '" class="saw-btn saw-btn-sm saw-btn-secondary" title="Upravit">';
            echo '<span class="dashicons dashicons-edit"></span>';
            echo '</a>';
        } elseif ($action === 'delete') {
            echo '<button type="button" class="saw-btn saw-btn-sm saw-btn-danger saw-delete-item" ';
            echo 'data-id="' . esc_attr($item_id) . '" title="Smazat">';
            echo '<span class="dashicons dashicons-trash"></span>';
            echo '</button>';
        } elseif ($action === 'view') {
            $url = isset($config['url']) ? $config['url'] : home_url('/admin/' . $table_name . '/view/?id=' . $item_id);
            echo '<a href="' . esc_url($url) . '" class="saw-btn saw-btn-sm saw-btn-secondary" title="Zobrazit">';
            echo '<span class="dashicons dashicons-visibility"></span>';
            echo '</a>';
        }
    }
    
    echo '</div>';
}

/**
 * Format date for display
 */
function saw_format_date($date, $format = 'j.n.Y') {
    if (empty($date)) {
        return '—';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date_i18n($format, $timestamp);
    }
    
    return esc_html($date);
}

/**
 * Format datetime for display
 */
function saw_format_datetime($datetime, $format = 'j.n.Y H:i') {
    return saw_format_date($datetime, $format);
}