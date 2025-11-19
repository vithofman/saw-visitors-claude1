<?php
/**
 * Admin Table Component
 *
 * Modern table component with integrated search, filters, sorting, and pagination
 *
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     6.0.0 - REFACTORED: Integrated search & filters
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Component Admin Table
 *
 * @since 1.0.0
 */
class SAW_Component_Admin_Table {
    
    private $entity;
    private $config;
    
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        
        if (empty($config['columns']) && !empty($config['module_config']['fields'])) {
            $config['columns'] = $this->generate_columns_from_fields($config['module_config']['fields']);
        }
        
        $this->config = $this->parse_config($config);
    }
    
    private function generate_columns_from_fields($fields) {
        $columns = array();
        
        foreach ($fields as $key => $field) {
            if (isset($field['hidden']) && $field['hidden']) {
                continue;
            }
            
            if (isset($field['deprecated']) && $field['deprecated']) {
                continue;
            }
            
            $column = array(
                'label' => $field['label'] ?? ucfirst($key),
                'type' => $this->map_field_type_to_column_type($field['type']),
            );
            
            if (in_array($field['type'], array('text', 'email', 'textarea'))) {
                $column['searchable'] = true;
            }
            
            if (in_array($field['type'], array('text', 'email', 'select', 'date', 'number'))) {
                $column['sortable'] = true;
            }
            
            $columns[$key] = $column;
        }
        
        return $columns;
    }
    
    private function map_field_type_to_column_type($field_type) {
        $map = array(
            'text' => 'text',
            'email' => 'email',
            'textarea' => 'text',
            'select' => 'badge',
            'file' => 'image',
            'date' => 'date',
            'checkbox' => 'boolean',
            'number' => 'text',
        );
        
        return $map[$field_type] ?? 'text';
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
            
            // Search & Filters - NEW
            'enable_search' => false,
            'search_placeholder' => 'Hledat...',
            'search_value' => '',
            
            'enable_filters' => false,
            'filters' => array(),
            'active_filters' => array(),
            
            'actions' => array('edit', 'delete'),
            'create_url' => '',
            'edit_url' => '',
            'detail_url' => '',
            'title' => '',
            'subtitle' => '',
            'singular' => '',
            'plural' => '',
            'icon' => '📋',
            'route' => '',
            'add_new' => 'Přidat nový',
            'empty_message' => 'Žádné záznamy nenalezeny',
            'enable_modal' => false,
            'modal_id' => '',
            'modal_ajax_action' => '',
            'detail_item' => null,
            'detail_tab' => 'overview',
            'form_item' => null,
            'sidebar_mode' => null,
            'related_data' => null,
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    public function render() {
        $this->enqueue_assets();
        
        $has_sidebar = !empty($this->config['sidebar_mode']);
        
        if ($has_sidebar) {
            $this->render_split_layout();
        } else {
            $this->render_header();
            $this->render_controls();
            $this->render_table_or_empty();
            $this->render_pagination();
            $this->render_modal();
            $this->render_floating_button();
            $this->render_delete_script();
        }
    }
    
    private function render_split_layout() {
        $has_sidebar = !empty($this->config['sidebar_mode']);
        $sidebar_class = $has_sidebar ? ' has-sidebar' : '';
        ?>
        <div class="saw-admin-table-split<?php echo $sidebar_class; ?>">
            <div class="saw-table-panel">
                <?php
                $this->render_header();
                $this->render_controls();
                $this->render_table_or_empty();
                $this->render_pagination();
                $this->render_floating_button();
                ?>
            </div>
            
            <?php if ($has_sidebar): ?>
            <div class="saw-sidebar-wrapper active">
                <?php $this->render_sidebar(); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $this->render_delete_script();
    }
    
    private function render_sidebar() {
        $mode = $this->config['sidebar_mode'];
        $entity = $this->entity;
        
        $module_config = $this->config['module_config'] ?? $this->config;
        $config = $module_config;
        
        if ($mode === 'detail') {
            $item = $this->config['detail_item'];
            $tab = $this->config['detail_tab'];
            $related_data = $this->config['related_data'] ?? null;
            require __DIR__ . '/detail-sidebar.php';
        } 
        elseif ($mode === 'create' || $mode === 'edit') {
            $item = $this->config['form_item'];
            $is_edit = ($mode === 'edit');
            require __DIR__ . '/form-sidebar.php';
        }
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
        
        $sidebar_css = __DIR__ . '/sidebar.css';
        if (file_exists($sidebar_css)) {
            wp_enqueue_style(
                'saw-admin-table-sidebar',
                str_replace(SAW_VISITORS_PLUGIN_DIR, SAW_VISITORS_PLUGIN_URL, $sidebar_css),
                array('saw-admin-table-component'),
                filemtime($sidebar_css)
            );
        }
        
        $sidebar_js = __DIR__ . '/sidebar.js';
        if (file_exists($sidebar_js)) {
            wp_enqueue_script(
                'saw-admin-table-sidebar',
                str_replace(SAW_VISITORS_PLUGIN_DIR, SAW_VISITORS_PLUGIN_URL, $sidebar_js),
                array('jquery', 'saw-admin-table-component'),
                filemtime($sidebar_js),
                true
            );
        }
    }
    
    private function render_header() {
        if (empty($this->config['title'])) {
            return;
        }
        ?>
        <div class="saw-page-header">
            <div class="saw-page-header-content">
                <?php if (!empty($this->config['title'])): ?>
                    <h1 class="saw-page-title">
                        <?php if (!empty($this->config['icon'])): ?>
                            <span class="saw-page-icon"><?php echo esc_html($this->config['icon']); ?></span>
                        <?php endif; ?>
                        <?php echo esc_html($this->config['title']); ?>
                    </h1>
                <?php endif; ?>
                
                <?php if (!empty($this->config['subtitle'])): ?>
                    <p class="saw-page-subtitle"><?php echo esc_html($this->config['subtitle']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render search and filter controls
     * 
     * @since 6.0.0
     */
    private function render_controls() {
        if (!$this->config['enable_search'] && !$this->config['enable_filters']) {
            return;
        }
        
        $base_url = $this->get_base_url();
        $current_params = $this->get_current_params();
        
        ?>
        <div class="saw-table-controls">
            <?php if ($this->config['enable_search']): ?>
                <?php $this->render_search_form($base_url, $current_params); ?>
            <?php endif; ?>
            
            <?php if ($this->config['enable_filters']): ?>
                <?php $this->render_filters($base_url, $current_params); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render search form
     * 
     * @since 6.0.0
     */
    private function render_search_form($base_url, $current_params) {
        $search_value = $this->config['search_value'];
        $placeholder = $this->config['search_placeholder'];
        
        ?>
        <form method="GET" action="<?php echo esc_url($base_url); ?>" class="saw-search-form">
            <?php
            // Preserve filters
            foreach ($current_params as $key => $value) {
                if ($key !== 's' && $key !== 'paged') {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            
            <input type="search" 
                   name="s" 
                   value="<?php echo esc_attr($search_value); ?>" 
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="saw-search-input">
            
            <?php if (!empty($search_value)): ?>
                <a href="<?php echo esc_url($this->build_url($base_url, array_diff_key($current_params, array('s' => '', 'paged' => '')))); ?>" 
                   class="saw-search-clear" 
                   title="Zrušit vyhledávání">×</a>
            <?php endif; ?>
        </form>
        <?php
    }
    
    /**
     * Render filter controls
     * 
     * @since 6.0.0
     */
    private function render_filters($base_url, $current_params) {
        if (empty($this->config['filters'])) {
            return;
        }
        
        ?>
        <div class="saw-filters">
            <?php foreach ($this->config['filters'] as $filter_key => $filter_config): ?>
                <?php $this->render_single_filter($filter_key, $filter_config, $base_url, $current_params); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render single filter control
     * 
     * @since 6.0.0
     */
    private function render_single_filter($filter_key, $filter_config, $base_url, $current_params) {
        $filter_type = $filter_config['type'] ?? 'select';
        $filter_value = $current_params[$filter_key] ?? '';
        
        if ($filter_type === 'select') {
            $this->render_select_filter($filter_key, $filter_config, $filter_value, $base_url, $current_params);
        }
        // Můžeš přidat další typy filtrů: date_range, multiselect, atd.
    }
    
    /**
     * Render select filter
     * 
     * @since 6.0.0
     */
    private function render_select_filter($filter_key, $filter_config, $filter_value, $base_url, $current_params) {
        $options = $filter_config['options'] ?? array();
        $label = $filter_config['label'] ?? ucfirst($filter_key);
        
        ?>
        <div class="saw-filter-item">
            <form method="GET" action="<?php echo esc_url($base_url); ?>" class="saw-filter-form">
                <?php
                // Preserve other params
                foreach ($current_params as $key => $value) {
                    if ($key !== $filter_key && $key !== 'paged') {
                        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                    }
                }
                ?>
                
                <select name="<?php echo esc_attr($filter_key); ?>" 
                        class="saw-select saw-filter-select" 
                        onchange="this.form.submit()"
                        aria-label="<?php echo esc_attr($label); ?>">
                    <?php foreach ($options as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" 
                                <?php selected($filter_value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <?php if (!empty($filter_value)): ?>
                <a href="<?php echo esc_url($this->build_url($base_url, array_diff_key($current_params, array($filter_key => '', 'paged' => '')))); ?>" 
                   class="saw-filter-clear" 
                   title="Zrušit filtr">
                    <span class="dashicons dashicons-dismiss"></span>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get base URL without query params
     * 
     * @since 6.0.0
     * @return string
     */
    private function get_base_url() {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '?') !== false) {
            return substr($current_url, 0, strpos($current_url, '?'));
        }
        return $current_url;
    }
    
    /**
     * Get current URL parameters
     * 
     * @since 6.0.0
     * @return array
     */
    private function get_current_params() {
        $params = array();
        
        if (!empty($_GET['s'])) {
            $params['s'] = sanitize_text_field($_GET['s']);
        }
        
        // Get active filters
        foreach ($this->config['filters'] as $filter_key => $filter_config) {
            if (!empty($_GET[$filter_key])) {
                $params[$filter_key] = sanitize_text_field($_GET[$filter_key]);
            }
        }
        
        if (!empty($_GET['orderby'])) {
            $params['orderby'] = sanitize_text_field($_GET['orderby']);
        }
        
        if (!empty($_GET['order'])) {
            $params['order'] = sanitize_text_field($_GET['order']);
        }
        
        return $params;
    }
    
    /**
     * Build URL with parameters
     * 
     * @since 6.0.0
     * @param string $base_url Base URL
     * @param array $params Query parameters
     * @return string
     */
    private function build_url($base_url, $params) {
        if (empty($params)) {
            return $base_url;
        }
        
        return $base_url . '?' . http_build_query($params);
    }
    
    private function render_table_or_empty() {
        if (empty($this->config['rows'])) {
            $this->render_empty_state();
        } else {
            $this->render_table();
        }
    }
    
    private function render_empty_state() {
        ?>
        <div class="saw-empty-state">
            <div class="saw-empty-icon">
                <span class="dashicons dashicons-search"></span>
            </div>
            <h3><?php echo esc_html($this->config['empty_message']); ?></h3>
            <?php if (!empty($this->config['create_url'])): ?>
                <?php
                $can_create = function_exists('saw_can') ? saw_can('create', $this->entity) : true;
                if ($can_create):
                ?>
                <a href="<?php echo esc_url($this->config['create_url']); ?>" class="saw-button saw-button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php echo esc_html($this->config['add_new']); ?>
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_table() {
        ?>
        <div class="saw-table-responsive-wrapper">
            <table class="saw-admin-table">
                <thead>
                    <tr>
                        <?php foreach ($this->config['columns'] as $key => $column): ?>
                            <?php
                            $label = is_array($column) ? ($column['label'] ?? ucfirst($key)) : $column;
                            $sortable = is_array($column) && isset($column['sortable']) ? $column['sortable'] : false;
                            $width = is_array($column) && isset($column['width']) ? $column['width'] : '';
                            $align = is_array($column) && isset($column['align']) ? $column['align'] : 'left';
                            ?>
                            <th style="<?php echo $width ? 'width: ' . esc_attr($width) . ';' : ''; ?> text-align: <?php echo esc_attr($align); ?>;">
                                <?php if ($sortable): ?>
                                    <a href="<?php echo esc_url($this->get_sort_url($key, $this->config['orderby'], $this->config['order'])); ?>" class="saw-sortable">
                                        <?php echo esc_html($label); ?>
                                        <?php echo $this->get_sort_icon($key, $this->config['orderby'], $this->config['order']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo esc_html($label); ?>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($this->config['actions'])): ?>
                            <th class="saw-actions-column">Akce</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->config['rows'] as $row): ?>
                        <?php
                        $detail_url = !empty($this->config['detail_url']) 
                            ? str_replace('{id}', intval($row['id'] ?? 0), $this->config['detail_url'])
                            : '';
                        
                        $row_class = 'saw-table-row';
                        if (!empty($detail_url)) {
                            $row_class .= ' saw-clickable-row';
                        }
                        ?>
                        <tr class="<?php echo esc_attr($row_class); ?>" 
                            data-id="<?php echo esc_attr($row['id'] ?? ''); ?>"
                            <?php if (!empty($detail_url)): ?>
                                data-detail-url="<?php echo esc_url($detail_url); ?>"
                            <?php endif; ?>>
                            
                            <?php foreach ($this->config['columns'] as $key => $column): ?>
                                <?php $this->render_table_cell($row, $key, $column); ?>
                            <?php endforeach; ?>
                            
                            <?php if (!empty($this->config['actions'])): ?>
                                <?php $this->render_action_buttons($row); ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_table_cell($row, $key, $column) {
        $value = $row[$key] ?? '';
        $type = is_array($column) ? ($column['type'] ?? 'text') : 'text';
        $align = is_array($column) && isset($column['align']) ? $column['align'] : 'left';
        $class = is_array($column) && isset($column['class']) ? $column['class'] : '';
        
        $td_class = $class ? ' class="' . esc_attr($class) . '"' : '';
        echo '<td' . $td_class . ' style="text-align: ' . esc_attr($align) . ';">';
        
        switch ($type) {
            case 'image':
                if (!empty($value)) {
                    echo '<img src="' . esc_url($value) . '" alt="" class="saw-table-image">';
                }
                break;
                
            case 'badge':
                if ($value !== '' && $value !== null) {
                    $badge_class = 'saw-badge';
                    if (is_array($column) && isset($column['map'][$value])) {
                        $badge_class .= ' saw-badge-' . $column['map'][$value];
                    }
                    $label = isset($column['labels'][$value]) 
                        ? $column['labels'][$value] 
                        : $value;
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
                
            case 'custom':
                if (is_array($column) && isset($column['callback']) && is_callable($column['callback'])) {
                    echo $column['callback']($value, $row);
                } else {
                    echo esc_html($value);
                }
                break;
            
            case 'html_raw':
                echo $value;
                break;
                
            default:
                echo esc_html($value);
                break;
        }
        
        echo '</td>';
    }
    
    private function render_action_buttons($row) {
        ?>
        <td class="saw-actions-cell">
            <div class="saw-action-buttons">
                <?php
                $can_edit = function_exists('saw_can') ? saw_can('edit', $this->entity) : true;
                $can_delete = function_exists('saw_can') ? saw_can('delete', $this->entity) : true;
                
                if (is_array($this->config['actions'])) {
                    foreach ($this->config['actions'] as $action) {
                        if ($action === 'view' && !empty($this->config['detail_url'])) {
                            $view_url = str_replace('{id}', intval($row['id'] ?? 0), $this->config['detail_url']);
                            ?>
                            <a href="<?php echo esc_url($view_url); ?>" 
                               class="saw-action-btn saw-action-view" 
                               title="Zobrazit"
                               onclick="event.stopPropagation();">
                                <span class="dashicons dashicons-visibility"></span>
                            </a>
                            <?php
                        }
                        
                        if ($action === 'edit' && $can_edit && !empty($this->config['edit_url'])) {
                            $edit_url = str_replace('{id}', intval($row['id'] ?? 0), $this->config['edit_url']);
                            ?>
                            <a href="<?php echo esc_url($edit_url); ?>" 
                               class="saw-action-btn saw-action-edit" 
                               title="Upravit"
                               onclick="event.stopPropagation();">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <?php
                        }
                        
                        if ($action === 'delete' && $can_delete) {
                            ?>
                            <button type="button" 
                                    class="saw-action-btn saw-action-delete" 
                                    data-id="<?php echo esc_attr($row['id'] ?? ''); ?>" 
                                    data-ajax-action="saw_delete_<?php echo esc_attr(str_replace('-', '_', $this->entity)); ?>"
                                    title="Smazat" 
                                    onclick="event.stopPropagation(); sawAdminTableDelete(this); return false;">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                            <?php
                        }
                    }
                }
                ?>
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
        $base_url = $this->get_base_url();
        $current_params = $this->get_current_params();
        
        ?>
        <div class="saw-pagination">
            <?php if ($page > 1): ?>
                <?php
                $prev_params = array_merge($current_params, array('paged' => $page - 1));
                ?>
                <a href="<?php echo esc_url($this->build_url($base_url, $prev_params)); ?>" class="saw-pagination-link">
                    « Předchozí
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="saw-pagination-link current"><?php echo $i; ?></span>
                <?php else: ?>
                    <?php
                    $page_params = array_merge($current_params, array('paged' => $i));
                    ?>
                    <a href="<?php echo esc_url($this->build_url($base_url, $page_params)); ?>" class="saw-pagination-link">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <?php
                $next_params = array_merge($current_params, array('paged' => $page + 1));
                ?>
                <a href="<?php echo esc_url($this->build_url($base_url, $next_params)); ?>" class="saw-pagination-link">
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
        
        $can_create = function_exists('saw_can') ? saw_can('create', $this->entity) : true;
        
        if (!$can_create) {
            return;
        }
        
        ?>
        <a href="<?php echo esc_url($this->config['create_url']); ?>" 
           class="saw-floating-button" 
           title="<?php echo esc_attr($this->config['add_new']); ?>">
            <span class="dashicons dashicons-plus"></span>
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
                'ajax_action' => 'saw_delete_' . str_replace('-', '_', $this->entity),
            );
        }
        
        $modal = new SAW_Component_Modal($this->config['modal_id'], $modal_config);
        $modal->render();
    }
    
    private function render_delete_script() {
        static $script_added = false;
        
        if ($script_added) {
            return;
        }
        
        $script_added = true;
        
        ?>
        <script>
        if (typeof sawAdminTableDelete === 'undefined') {
            window.sawAdminTableDelete = function(btn) {
                const id = btn.dataset.id;
                const ajaxAction = btn.dataset.ajaxAction;
                
                if (!confirm('Opravdu chcete smazat tento záznam?')) {
                    return false;
                }
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        nonce: '<?php echo wp_create_nonce('saw_ajax_nonce'); ?>',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Chyba při mazání');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Chyba spojení se serverem');
                    }
                });
                
                return false;
            };
        }
        </script>
        <?php
    }
    
    private function get_sort_url($column, $current_orderby, $current_order) {
        $new_order = 'ASC';
        
        if ($column === $current_orderby) {
            $new_order = ($current_order === 'ASC') ? 'DESC' : 'ASC';
        }
        
        $base_url = $this->get_base_url();
        $current_params = $this->get_current_params();
        
        $sort_params = array_merge($current_params, array(
            'orderby' => $column,
            'order' => $new_order,
        ));
        
        // Remove paged when sorting
        unset($sort_params['paged']);
        
        return $this->build_url($base_url, $sort_params);
    }
    
    private function get_sort_icon($column, $current_orderby, $current_order) {
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