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
            
            // Search & Filters - NEW FORMAT
            'search' => array(
                'enabled' => false,
                'placeholder' => 'Hledat...',
                'fields' => array(), // Fields to search in
                'show_info_banner' => true,
            ),
            'search_value' => '', // Legacy support
            
            'filters' => array(), // New format: array('filter_key' => array('type' => 'select', 'label' => '...', 'options' => array()))
            'active_filters' => array(),
            
            // Legacy support
            'enable_search' => false,
            'enable_filters' => false,
            
            // Tabs support (replaces grouping)
            'tabs' => array(
                'enabled' => false,
                'tab_param' => 'tab',
                'tabs' => array(),
                'default_tab' => 'all',
            ),
            'current_tab' => null,
            'tab_counts' => array(),
            
            // Grouping support (legacy - deprecated in favor of tabs)
            'grouping' => array(
                'enabled' => false,
                'group_by' => '',
                'group_label_callback' => null,
                'default_collapsed' => true,
                'sort_groups_by' => 'label', // 'label', 'count', 'value'
                'show_count' => true,
            ),
            
            // Infinite scroll support
            'infinite_scroll' => array(
                'enabled' => false,
                'initial_load' => 100, // First load
                'per_page' => 50,
                'threshold' => 0.7, // 70% scroll (changed from 300px)
            ),
            
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
        
        $parsed = wp_parse_args($config, $defaults);
        
        // Legacy support: convert old format to new format
        if (!empty($parsed['enable_search']) && empty($parsed['search']['enabled'])) {
            $parsed['search']['enabled'] = true;
            if (!empty($parsed['search_placeholder'])) {
                $parsed['search']['placeholder'] = $parsed['search_placeholder'];
            }
        }
        
        return $parsed;
    }
    
    public function render() {
        $this->enqueue_assets();
        $this->output_js_config();
        
        $has_sidebar = !empty($this->config['sidebar_mode']);
        
        if ($has_sidebar) {
            $this->render_split_layout();
        } else {
            ?>
            <div class="saw-table-panel<?php echo !empty($this->config['infinite_scroll']['enabled']) ? ' saw-table-infinite-scroll-enabled' : ''; ?>">
                <?php
                $this->render_header();
                $this->render_controls();
                ?>
                
                <!-- Scrollovací oblast -->
                <div class="saw-table-scroll-area">
                    <?php $this->render_table_or_empty(); ?>
                </div>
                
                <!-- Pagination vždy dole -->
                <?php $this->render_pagination(); ?>
                
                <?php
                $this->render_modal();
                $this->render_floating_button();
                $this->render_delete_script();
                ?>
            </div>
            <?php
        }
    }
    
    private function render_split_layout() {
        $has_sidebar = !empty($this->config['sidebar_mode']);
        $sidebar_class = $has_sidebar ? ' has-sidebar' : '';
        ?>
        <div class="saw-admin-table-split<?php echo $sidebar_class; ?>">
            <div class="saw-table-panel<?php echo !empty($this->config['infinite_scroll']['enabled']) ? ' saw-table-infinite-scroll-enabled' : ''; ?>">
                <?php
                $this->render_header();
                $this->render_controls();
                ?>
                
                <!-- Scrollovací oblast -->
                <div class="saw-table-scroll-area">
                    <?php $this->render_table_or_empty(); ?>
                </div>
                
                <!-- Pagination vždy dole -->
                <?php $this->render_pagination(); ?>
                
                <?php $this->render_floating_button(); ?>
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
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
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
        $search_config = $this->config['search'] ?? array();
        $filters_config = $this->config['filters'] ?? array();
        $tabs_enabled = !empty($this->config['tabs']['enabled']);
        
        // Legacy support
        $search_enabled = !empty($search_config['enabled']) || !empty($this->config['enable_search']);
        $filters_enabled = !empty($filters_config) || !empty($this->config['enable_filters']);
        
        $base_url = $this->get_base_url();
        $current_params = $this->get_current_params();
        
        // If tabs are enabled, render tabs with search/filters in the same row
        if ($tabs_enabled) {
            $this->render_tabs_navigation_with_controls($search_enabled, $filters_enabled, $search_config, $filters_config, $base_url, $current_params);
        } else {
            // Render search and filters only if at least one is enabled
            if (!$search_enabled && !$filters_enabled) {
                return;
            }
            ?>
            <div class="saw-table-controls">
                <?php if ($search_enabled): ?>
                    <div class="saw-table-search">
                        <?php $this->render_search_form($base_url, $current_params, $search_config); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($filters_enabled): ?>
                    <div class="saw-table-filters">
                        <?php $this->render_filters($base_url, $current_params); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    /**
     * Render tabs navigation
     * 
     * @since 7.1.0
     */
    private function render_tabs_navigation() {
        // Prepare config for tabs template with base_url and current_params
        $config = $this->config;
        $config['base_url'] = $this->get_base_url();
        $config['current_params'] = $this->get_current_params();
        
        $entity = $this->entity;
        
        require __DIR__ . '/tabs-navigation.php';
    }
    
    /**
     * Render tabs navigation with search and filters in the same row
     * 
     * @since 7.1.0
     */
    private function render_tabs_navigation_with_controls($search_enabled, $filters_enabled, $search_config, $filters_config, $base_url, $current_params) {
        // Render search and filters HTML
        $search_html = '';
        $filters_html = '';
        
        if ($search_enabled) {
            ob_start();
            $this->render_search_form($base_url, $current_params, $search_config);
            $search_html = ob_get_clean();
        }
        
        if ($filters_enabled) {
            ob_start();
            $this->render_filters($base_url, $current_params);
            $filters_html = ob_get_clean();
        }
        
        // Prepare config for tabs template with base_url and current_params
        $config = $this->config;
        $config['base_url'] = $base_url;
        $config['current_params'] = $current_params;
        $config['search_enabled'] = $search_enabled;
        $config['filters_enabled'] = $filters_enabled;
        $config['search_html'] = $search_html;
        $config['filters_html'] = $filters_html;
        
        $entity = $this->entity;
        
        require __DIR__ . '/tabs-navigation-with-controls.php';
    }
    
    /**
     * Render search form
     * 
     * @since 6.0.0
     */
    private function render_search_form($base_url, $current_params, $search_config = array()) {
        $search_value = $this->config['search_value'] ?? '';
        if (empty($search_value) && !empty($_GET['s'])) {
            $search_value = sanitize_text_field($_GET['s']);
        }
        
        $placeholder = $search_config['placeholder'] ?? $this->config['search_placeholder'] ?? 'Hledat...';
        $show_info_banner = $search_config['show_info_banner'] ?? true;
        
        ?>
        <form method="GET" action="<?php echo esc_url($base_url); ?>" class="saw-search-form">
            <?php
            // Preserve filters and other params
            foreach ($current_params as $key => $value) {
                if ($key !== 's' && $key !== 'paged') {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            
            <input type="text" 
                   name="s" 
                   value="<?php echo esc_attr($search_value); ?>" 
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="saw-search-input">
            
            <button type="submit" class="saw-search-button" title="Hledat">
                <span class="dashicons dashicons-search"></span>
            </button>
            
            <?php if (!empty($search_value)): ?>
                <a href="<?php echo esc_url($this->build_url($base_url, array_diff_key($current_params, array('s' => '', 'paged' => '')))); ?>" 
                   class="saw-search-clear" 
                   title="Zrušit vyhledávání">
                    <span class="dashicons dashicons-no-alt"></span>
                </a>
            <?php endif; ?>
        </form>
        
        <?php if (!empty($search_value) && $show_info_banner): ?>
            <div class="saw-search-info">
                Hledáte: <strong><?php echo esc_html($search_value); ?></strong>
                <a href="<?php echo esc_url($this->build_url($base_url, array_diff_key($current_params, array('s' => '', 'paged' => '')))); ?>">Zrušit</a>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render filter controls
     * 
     * @since 6.0.0
     */
    private function render_filters($base_url, $current_params) {
        $filters_config = $this->config['filters'] ?? array();
        if (empty($filters_config)) {
            return;
        }
        
        // Check if any filters are active
        $has_active_filters = false;
        $active_filters_count = 0;
        foreach ($filters_config as $filter_key => $filter_config) {
            if (!empty($_GET[$filter_key])) {
                $has_active_filters = true;
                $active_filters_count++;
            }
        }
        
        ?>
        <div class="saw-filters-dropdown-wrapper">
            <button type="button" 
                    class="saw-filters-trigger <?php echo $has_active_filters ? 'has-active' : ''; ?>"
                    onclick="toggleFiltersMenu(this, event)"
                    title="Filtry">
                <span class="dashicons dashicons-filter"></span>
                <?php if ($active_filters_count > 0): ?>
                    <span class="saw-filter-badge"><?php echo $active_filters_count; ?></span>
                <?php endif; ?>
            </button>
            
            <div class="saw-filters-dropdown-menu">
                <form method="GET" action="<?php echo esc_url($base_url); ?>" class="saw-filters-form">
                    <?php
                    // Preserve search
                    if (!empty($_GET['s'])) {
                        echo '<input type="hidden" name="s" value="' . esc_attr(sanitize_text_field($_GET['s'])) . '">';
                    }
                    
                    // Preserve orderby/order
                    if (!empty($_GET['orderby'])) {
                        echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field($_GET['orderby'])) . '">';
                    }
                    if (!empty($_GET['order'])) {
                        echo '<input type="hidden" name="order" value="' . esc_attr(sanitize_text_field($_GET['order'])) . '">';
                    }
                    
                    foreach ($filters_config as $filter_key => $filter_config):
                        $this->render_single_filter($filter_key, $filter_config, $current_params);
                    endforeach;
                    ?>
                    
                    <?php if ($has_active_filters): ?>
                    <div class="saw-filters-actions">
                        <a href="<?php echo esc_url($base_url); ?>" 
                           class="saw-filter-clear-all"
                           title="Vymazat všechny filtry">
                            Vymazat filtry
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render single filter control
     * 
     * @since 6.0.0
     */
    private function render_single_filter($filter_key, $filter_config, $current_params) {
        $filter_type = $filter_config['type'] ?? 'select';
        $filter_value = $current_params[$filter_key] ?? '';
        if (empty($filter_value) && !empty($_GET[$filter_key])) {
            $filter_value = sanitize_text_field($_GET[$filter_key]);
        }
        
        if ($filter_type === 'select') {
            $this->render_select_filter($filter_key, $filter_config, $filter_value);
        }
        // Můžeš přidat další typy filtrů: date_range, multiselect, atd.
    }
    
    /**
     * Render select filter
     * 
     * @since 6.0.0
     */
    private function render_select_filter($filter_key, $filter_config, $filter_value) {
        $options = $filter_config['options'] ?? array();
        $label = $filter_config['label'] ?? ucfirst($filter_key);
        $placeholder = $filter_config['placeholder'] ?? 'Vyberte ' . strtolower($label);
        
        // Zjistit, jestli je něco vybráno (včetně '0' jako validní hodnoty)
        // $filter_value může být '0', '1', nebo '' (prázdné)
        $has_selection = $filter_value !== '' && $filter_value !== null;
        
        ?>
        <div class="saw-filter-item">
            <label class="saw-filter-label"><?php echo esc_html($label); ?></label>
            <select name="<?php echo esc_attr($filter_key); ?>" 
                    class="saw-select saw-filter-select <?php echo $has_selection ? 'has-value' : ''; ?>"
                    onchange="this.classList.toggle('has-value', this.value !== ''); this.form.submit();"
                    aria-label="<?php echo esc_attr($label); ?>">
                <option value="" <?php echo !$has_selection ? 'selected' : ''; ?> disabled><?php echo esc_html($placeholder); ?></option>
                <?php foreach ($options as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" 
                            <?php selected($filter_value, $option_value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
        // Get current URL without query params
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '?') !== false) {
            $current_url = substr($current_url, 0, strpos($current_url, '?'));
        }
        // Return full URL with home_url for proper domain handling
        return home_url($current_url);
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
        
        // Get tab param if tabs are enabled - use sanitize_key to match get_list_data()
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            if (isset($_GET[$tab_param]) && $_GET[$tab_param] !== '') {
                $params[$tab_param] = sanitize_key($_GET[$tab_param]);
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
            return;
        }
        
        // Always render standard table (grouping removed, replaced by tabs)
        $this->render_table();
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
        $actions = $this->config['actions'] ?? array();
        if (empty($actions)) {
            return;
        }
        
        $item_id = intval($row['id'] ?? 0);
        $can_edit = function_exists('saw_can') ? saw_can('edit', $this->entity) : true;
        $can_delete = function_exists('saw_can') ? saw_can('delete', $this->entity) : true;
        
        // Check if we have any valid actions
        $has_valid_actions = false;
        if (in_array('view', $actions) && !empty($this->config['detail_url'])) {
            $has_valid_actions = true;
        }
        if (in_array('edit', $actions) && $can_edit && !empty($this->config['edit_url'])) {
            $has_valid_actions = true;
        }
        if (in_array('delete', $actions) && $can_delete) {
            $has_valid_actions = true;
        }
        
        if (!$has_valid_actions) {
            return;
        }
        
        ?>
        <td class="saw-actions-cell" onclick="event.stopPropagation();">
            <div class="saw-action-dropdown">
                <button type="button" 
                        class="saw-action-trigger" 
                        data-id="<?php echo esc_attr($item_id); ?>"
                        onclick="toggleActionMenu(this, event)"
                        title="Akce">
                    <span class="dashicons dashicons-ellipsis"></span>
                </button>
                
                <div class="saw-action-menu">
                    <?php if (in_array('view', $actions) && !empty($this->config['detail_url'])): ?>
                        <?php $view_url = str_replace('{id}', $item_id, $this->config['detail_url']); ?>
                        <a href="<?php echo esc_url($view_url); ?>" 
                           class="saw-action-item"
                           onclick="event.stopPropagation();">
                            <span class="dashicons dashicons-visibility"></span>
                            <span>Detail</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array('edit', $actions) && $can_edit && !empty($this->config['edit_url'])): ?>
                        <?php $edit_url = str_replace('{id}', $item_id, $this->config['edit_url']); ?>
                        <a href="<?php echo esc_url($edit_url); ?>" 
                           class="saw-action-item"
                           onclick="event.stopPropagation();">
                            <span class="dashicons dashicons-edit"></span>
                            <span>Upravit</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array('delete', $actions) && $can_delete): ?>
                        <button type="button" 
                                class="saw-action-item saw-action-delete"
                                data-id="<?php echo esc_attr($item_id); ?>"
                                data-ajax-action="saw_delete_<?php echo esc_attr(str_replace('-', '_', $this->entity)); ?>"
                                onclick="event.stopPropagation(); sawAdminTableDelete(this); return false;">
                            <span class="dashicons dashicons-trash"></span>
                            <span>Smazat</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <?php
    }
    
    private function render_pagination() {
        // Don't render pagination if infinite scroll is enabled
        if (!empty($this->config['infinite_scroll']['enabled'])) {
            return;
        }
        
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
    
    // REMOVED: prepare_grouped_data(), sort_groups(), render_grouped_table()
    // Grouping functionality replaced by tabs navigation
    
    /**
     * Get sort URL - public helper for templates
     * 
     * @since 7.0.0
     * @param string $column Column key
     * @return string Sort URL
     */
    public function get_sort_url_for_template($column) {
        return $this->get_sort_url($column, $this->config['orderby'], $this->config['order']);
    }
    
    /**
     * Get sort icon - public helper for templates
     * 
     * @since 7.0.0
     * @param string $column Column key
     * @return string Sort icon HTML
     */
    public function get_sort_icon_for_template($column) {
        return $this->get_sort_icon($column, $this->config['orderby'], $this->config['order']);
    }
    
    /**
     * Render table cell - public helper for templates
     * 
     * @since 7.0.0
     * @param array $row Row data
     * @param string $key Column key
     * @param array|string $column Column config
     */
    public function render_table_cell_for_template($row, $key, $column) {
        $this->render_table_cell($row, $key, $column);
    }
    
    /**
     * Render action buttons - public helper for templates
     * 
     * @since 7.0.0
     * @param array $row Row data
     */
    public function render_action_buttons_for_template($row) {
        $this->render_action_buttons($row);
    }
    
    /**
     * Output JS configuration for infinite scroll and tabs
     * 
     * @since 7.1.0
     */
    private function output_js_config() {
        $config = array(
            'entity' => $this->entity,
            'columns' => $this->config['columns'],
            'actions' => $this->config['actions'],
            'detail_url' => $this->config['detail_url'],
            'edit_url' => $this->config['edit_url'],
            'infinite_scroll' => $this->config['infinite_scroll'],
            'tabs' => $this->config['tabs'] ?? null,
            'current_tab' => $this->config['current_tab'] ?? null,
        );
        
        echo '<script>';
        echo 'window.sawInfiniteScrollConfig = ' . wp_json_encode($config) . ';';
        echo '</script>';
    }
}