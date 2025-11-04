<?php
/**
 * Admin Table Component - REFACTORED v3.1.0
 * 
 * @package SAW_Visitors
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Admin_Table {
    
    private $entity;
    private $config;
    
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        $this->config = $this->parse_config($config);
    }
    
    private function parse_config($config) {
        $defaults = array(
            'columns' => array(),
            'rows' => array(),
            'total_items' => 0,
            'current_page' => 1,
            'total_pages' => 1,
            'per_page' => 20,
            'orderby' => '',
            'order' => 'ASC',
            'search' => false,
            'search_value' => '',
            'filters' => array(),
            'actions' => array('edit', 'delete'),
            'create_url' => '',
            'edit_url' => '',
            'title' => '',
            'subtitle' => '',
            'singular' => '',
            'plural' => '',
            'add_new' => 'Přidat nový',
            'empty_message' => 'Žádné záznamy nenalezeny',
            'enable_modal' => false,
            'modal_id' => '',
            'modal_ajax_action' => '',
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    public function render() {
        $this->enqueue_assets();
        $this->render_header();
        $this->render_controls();
        $this->render_table_or_empty();
        $this->render_pagination();
        $this->render_modal();
        $this->render_floating_button();
    }
    
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/tables.css',
            array('saw-variables'),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-table-column-types',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/components/table-column-types.css',
            array('saw-variables'),
            SAW_VISITORS_VERSION
        );
        
        $component_css = __DIR__ . '/admin-table.css';
        if (file_exists($component_css)) {
            wp_enqueue_style(
                'saw-admin-table-component',
                str_replace(SAW_VISITORS_PLUGIN_DIR, SAW_VISITORS_PLUGIN_URL, $component_css),
                array('saw-tables'),
                filemtime($component_css)
            );
        }
        
        $component_js = __DIR__ . '/admin-table.js';
        if (file_exists($component_js)) {
            wp_enqueue_script(
                'saw-admin-table-component',
                str_replace(SAW_VISITORS_PLUGIN_DIR, SAW_VISITORS_PLUGIN_URL, $component_js),
                array('jquery', 'saw-app'),
                filemtime($component_js),
                true
            );
        }
    }
    
    private function render_header() {
    if (empty($this->config['title'])) {
        return;
    }
    
    $has_controls = !empty($this->config['search']) || !empty($this->config['filters']);
    
    ?>
    <div class="saw-page-header">
        <div class="saw-page-header-content">
            <h1 class="saw-page-title"><?php echo esc_html($this->config['title']); ?></h1>
            
            <?php if ($has_controls): ?>
                <div class="saw-table-controls">
                    <?php if (!empty($this->config['filters'])): ?>
                        <div class="saw-filters">
                            <?php echo $this->config['filters']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($this->config['search']): ?>
                        <div class="saw-search-form">
                            <?php echo $this->config['search']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

private function render_controls() {
    // Prázdná - už je v headeru
}

private function render_table_or_empty() {
    if (empty($this->config['rows'])) {
        $this->render_empty_state();
    } else {
        $this->render_table();
    }
    // ODSTRANIT closing </div> - už není wrapper .saw-list-container
}
    
    private function render_empty_state() {
        ?>
        <div class="saw-empty-state">
            <span class="dashicons dashicons-info"></span>
            <p><?php echo esc_html($this->config['empty_message']); ?></p>
        </div>
        <?php
    }
    
    private function render_table() {
        $modal_attrs = '';
        if ($this->config['enable_modal'] && !empty($this->config['modal_id'])) {
            $modal_attrs = ' data-clickable-row data-modal="' . esc_attr($this->config['modal_id']) . '"';
        }
        ?>
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table">
                <thead>
                    <tr>
                        <?php foreach ($this->config['columns'] as $key => $column): ?>
                            <?php $this->render_th($key, $column); ?>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($this->config['actions'])): ?>
                            <th style="width: 120px; text-align: center;">Akce</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->config['rows'] as $row): ?>
                        <tr<?php echo $modal_attrs; ?> data-id="<?php echo esc_attr($row['id'] ?? ''); ?>" style="cursor: pointer;">
                            <?php foreach ($this->config['columns'] as $key => $column): ?>
                                <?php $this->render_td($key, $column, $row); ?>
                            <?php endforeach; ?>
                            
                            <?php if (!empty($this->config['actions'])): ?>
                                <?php $this->render_actions($row); ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_th($key, $column) {
        $label = $column['label'] ?? ucfirst($key);
        $sortable = $column['sortable'] ?? false;
        $width = $column['width'] ?? '';
        $align = $column['align'] ?? 'left';
        
        $style = array();
        if ($width) {
            $style[] = 'width: ' . esc_attr($width);
        }
        if ($align === 'center') {
            $style[] = 'text-align: center';
        }
        
        $style_attr = !empty($style) ? ' style="' . implode('; ', $style) . '"' : '';
        
        echo '<th' . $style_attr . '>';
        
        if ($sortable) {
            $sort_url = self::get_sort_url($key, $this->config['orderby'], $this->config['order']);
            $sort_icon = self::get_sort_icon($key, $this->config['orderby'], $this->config['order']);
            echo '<a href="' . esc_url($sort_url) . '">';
            echo esc_html($label);
            echo ' ' . $sort_icon;
            echo '</a>';
        } else {
            echo esc_html($label);
        }
        
        echo '</th>';
    }
    
    private function render_td($key, $column, $row) {
        $value = $row[$key] ?? null;
        $type = $column['type'] ?? 'text';
        $width = $column['width'] ?? '';
        $align = $column['align'] ?? 'left';
        
        $style = array();
        if ($width) {
            $style[] = 'width: ' . esc_attr($width);
        }
        if ($align === 'center') {
            $style[] = 'text-align: center';
        }
        if ($type === 'image') {
            $style[] = 'padding: 8px';
        }
        
        $style_attr = !empty($style) ? ' style="' . implode('; ', $style) . '"' : '';
        
        echo '<td' . $style_attr . '>';
        
        require_once __DIR__ . '/column-types.php';
        echo SAW_Table_Column_Types::render($type, $value, $column, $row);
        
        echo '</td>';
    }
    
    private function render_actions($row) {
        ?>
        <td style="width: 120px; text-align: center;">
            <div class="saw-action-buttons">
                <?php if (in_array('edit', $this->config['actions']) && !empty($this->config['edit_url'])): ?>
                    <?php
                    $edit_url = str_replace('{id}', $row['id'] ?? '', $this->config['edit_url']);
                    ?>
                    <a href="<?php echo esc_url($edit_url); ?>" 
                       class="saw-action-btn saw-action-edit" 
                       title="Upravit" 
                       onclick="event.stopPropagation();">
                        <span class="dashicons dashicons-edit"></span>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array('delete', $this->config['actions'])): ?>
                    <button type="button" 
                            class="saw-action-btn saw-action-delete saw-delete-btn" 
                            data-id="<?php echo esc_attr($row['id'] ?? ''); ?>" 
                            data-name="<?php echo esc_attr($row['name'] ?? $row['title'] ?? 'záznam'); ?>" 
                            data-entity="<?php echo esc_attr($this->entity); ?>" 
                            title="Smazat" 
                            onclick="event.stopPropagation();">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                <?php endif; ?>
            </div>
        </td>
        <?php
    }
    
    private function render_pagination() {
        if ($this->config['total_pages'] <= 1) {
            return;
        }
        
        $page = $this->config['current_page'];
        $total_pages = $this->config['total_pages'];
        $search = $this->config['search_value'];
        ?>
        <div class="saw-pagination">
            <?php if ($page > 1): ?>
                <a href="?paged=<?php echo ($page - 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                    « Předchozí
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="saw-pagination-link current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?paged=<?php echo $i; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?paged=<?php echo ($page + 1); ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>" class="saw-pagination-link">
                    Další »
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_floating_button() {
        if (empty($this->config['create_url'])) {
            return;
        }
        ?>
        <a href="<?php echo esc_url($this->config['create_url']); ?>" 
           class="saw-floating-btn" 
           title="<?php echo esc_attr($this->config['add_new']); ?>">
            <span class="dashicons dashicons-plus-alt"></span>
        </a>
        <?php
    }
    
    private function render_modal() {
        if (!$this->config['enable_modal'] || empty($this->config['modal_id'])) {
            return;
        }
        
        if (!class_exists('SAW_Component_Modal')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/modal/class-saw-component-modal.php';
        }
        
        $modal_config = array(
            'title' => 'Detail ' . strtolower($this->config['singular'] ?? 'záznamu'),
            'ajax_enabled' => true,
            'ajax_action' => $this->config['modal_ajax_action'] ?? 'saw_get_' . $this->entity . '_detail',
            'size' => 'large',
            'show_close' => true,
            'close_on_backdrop' => true,
            'close_on_escape' => true,
        );
        
        if (!empty($this->config['edit_url'])) {
            $modal_config['header_actions'][] = array(
                'type' => 'edit',
                'label' => '',
                'icon' => 'dashicons-edit',
                'url' => $this->config['edit_url'],
            );
        }
        
        if (in_array('delete', $this->config['actions'])) {
            $modal_config['header_actions'][] = array(
                'type' => 'delete',
                'label' => '',
                'icon' => 'dashicons-trash',
                'confirm' => true,
                'confirm_message' => 'Opravdu chcete smazat tento záznam?',
                'ajax_action' => 'saw_delete_' . $this->entity,
            );
        }
        
        $modal = new SAW_Component_Modal($this->config['modal_id'], $modal_config);
        $modal->render();
    }
    
    public static function get_sort_url($column, $current_orderby, $current_order) {
        $new_order = 'ASC';
        
        if ($column === $current_orderby) {
            $new_order = ($current_order === 'ASC') ? 'DESC' : 'ASC';
        }
        
        $query_args = array(
            'orderby' => $column,
            'order' => $new_order,
        );
        
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $query_args['s'] = sanitize_text_field($_GET['s']);
        }
        
        if (isset($_GET['paged'])) {
            $query_args['paged'] = intval($_GET['paged']);
        }
        
        foreach ($_GET as $key => $value) {
            if (!in_array($key, ['orderby', 'order', 's', 'paged'])) {
                $query_args[$key] = sanitize_text_field($value);
            }
        }
        
        return add_query_arg($query_args);
    }
    
    public static function get_sort_icon($column, $current_orderby, $current_order) {
        if ($column !== $current_orderby) {
            return '<span class="dashicons dashicons-sort saw-sort-icon"></span>';
        }
        
        if ($current_order === 'ASC') {
            return '<span class="dashicons dashicons-arrow-up saw-sort-icon"></span>';
        } else {
            return '<span class="dashicons dashicons-arrow-down saw-sort-icon"></span>';
        }
    }
}