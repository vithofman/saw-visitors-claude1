<?php
/**
 * Admin Table Component
 *
 * Modern table component with OOP structure, modal support, sidebar support, and permissions
 *
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     5.1.0 - REFACTORED: Header with inline controls, no duplicates
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Component Admin Table
 *
 * Handles rendering of admin data tables with sorting, filtering,
 * pagination, modal integration, sidebar integration, and permission checks
 *
 * @since 1.0.0
 */
class SAW_Component_Admin_Table {
    
    /**
     * Entity name
     *
     * @since 1.0.0
     * @var string
     */
    private $entity;
    
    /**
     * Table configuration
     *
     * @since 1.0.0
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     *
     * @since 1.0.0
     * @param string $entity Entity name (e.g. 'customer', 'branch')
     * @param array  $config Table configuration
     */
    public function __construct($entity, $config = array()) {
        $this->entity = sanitize_key($entity);
        
        // CRITICAL: Auto-generate columns from fields if not explicitly defined
        if (empty($config['columns']) && !empty($config['module_config']['fields'])) {
            $config['columns'] = $this->generate_columns_from_fields($config['module_config']['fields']);
        }
        
        $this->config = $this->parse_config($config);
    }
    
    /**
     * Generate columns from config fields if not explicitly defined
     * 
     * @since 5.0.0
     * @param array $fields Fields configuration from module config
     * @return array Columns configuration
     */
    private function generate_columns_from_fields($fields) {
        $columns = array();
        
        foreach ($fields as $key => $field) {
            // Skip hidden fields
            if (isset($field['hidden']) && $field['hidden']) {
                continue;
            }
            
            // Skip deprecated fields
            if (isset($field['deprecated']) && $field['deprecated']) {
                continue;
            }
            
            // Basic column config
            $column = array(
                'label' => $field['label'] ?? ucfirst($key),
                'type' => $this->map_field_type_to_column_type($field['type']),
            );
            
            // Auto-detect searchable fields
            if (in_array($field['type'], array('text', 'email', 'textarea'))) {
                $column['searchable'] = true;
            }
            
            // Auto-detect sortable fields
            if (in_array($field['type'], array('text', 'email', 'select', 'date', 'number'))) {
                $column['sortable'] = true;
            }
            
            $columns[$key] = $column;
        }
        
        return $columns;
    }
    
    /**
     * Map field type to column display type
     * 
     * @since 5.0.0
     * @param string $field_type Field type from config
     * @return string Column type
     */
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
    
    /**
     * Parse and merge configuration with defaults
     *
     * @since 1.0.0
     * @param array $config User-provided configuration
     * @return array Merged configuration
     */
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
        );
        
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Render complete table with all components
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
    public function render() {
        $this->enqueue_assets();
        
        $has_sidebar = !empty($this->config['sidebar_mode']);
        
        if ($has_sidebar) {
            $this->render_split_layout();
        } else {
            $this->render_header();
            $this->render_table_or_empty();
            $this->render_pagination();
            $this->render_modal();
            $this->render_floating_button();
            $this->render_delete_script();
        }
    }
    
    /**
     * Render split layout with sidebar
     *
     * @since 4.0.0
     * @return void Outputs HTML directly
     */
    private function render_split_layout() {
    $has_sidebar = !empty($this->config['sidebar_mode']);
    $sidebar_class = $has_sidebar ? ' has-sidebar' : '';
    ?>
    <div class="saw-admin-table-split<?php echo $sidebar_class; ?>">
        <div class="saw-table-panel">
            <?php
            $this->render_header();
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
    
    /**
     * Render sidebar content
     *
     * @since 4.0.0
     * @return void Outputs HTML directly
     */
    private function render_sidebar() {
    $mode = $this->config['sidebar_mode'];
    $entity = $this->entity;
    
    // CRITICAL: Pass module config, not Admin Table config!
    $module_config = $this->config['module_config'] ?? $this->config;
    $config = $module_config;
    
    if ($mode === 'detail') {
        $item = $this->config['detail_item'];
        $tab = $this->config['detail_tab'];
        $related_data = $this->config['related_data'] ?? null; // ✅ PŘIDÁNO
        require __DIR__ . '/detail-sidebar.php';
    } 
    elseif ($mode === 'create' || $mode === 'edit') {
        $item = $this->config['form_item'];
        $is_edit = ($mode === 'edit');
        require __DIR__ . '/form-sidebar.php';
    }
}
    
    /**
     * Enqueue component CSS and JS assets
     *
     * @since 1.0.0
     * @return void
     */
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
        
        // Sidebar assets
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
    
    /**
     * Render page header with title and inline controls
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
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
            
            <div class="saw-page-header-controls">
                <?php if (!empty($this->config['filters'])): ?>
                    <div class="saw-filters-wrapper">
                        <?php echo $this->config['filters']; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($this->config['search'])): ?>
                    <div class="saw-search-wrapper">
                        <?php echo $this->config['search']; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render table or empty message
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
    private function render_table_or_empty() {
        if (empty($this->config['rows'])) {
            $this->render_empty_state();
        } else {
            $this->render_table();
        }
    }
    
    /**
     * Render empty state message
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
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
    
    /**
     * Render data table
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
    private function render_table() {
        ?>
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
        <?php
    }
    
    /**
     * Render table cell based on column type
     *
     * @since 1.0.0
     * @param array  $row    Row data
     * @param string $key    Column key
     * @param mixed  $column Column configuration
     * @return void Outputs HTML directly
     */
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
    if ($value !== '' && $value !== null) {  // OPRAVA: Povolit 0!
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
                
            default:
                echo esc_html($value);
                break;
        }
        
        echo '</td>';
    }
    
    /**
     * Render action buttons for row
     *
     * @since 1.0.0
     * @param array $row Row data
     * @return void Outputs HTML directly
     */
    private function render_action_buttons($row) {
        ?>
        <td class="saw-actions-cell">
            <div class="saw-action-buttons">
                <?php
                $can_edit = function_exists('saw_can') ? saw_can('edit', $this->entity) : true;
                $can_delete = function_exists('saw_can') ? saw_can('delete', $this->entity) : true;
                
                if (is_array($this->config['actions'])) {
                    foreach ($this->config['actions'] as $action) {
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
    
    /**
     * Render pagination controls
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
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
    
    /**
     * Render floating action button
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
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
           class="saw-floating-btn" 
           title="<?php echo esc_attr($this->config['add_new']); ?>">
            <span class="dashicons dashicons-plus-alt"></span>
        </a>
        <?php
    }
    
    /**
     * Render modal component if enabled
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
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
    
    /**
     * Render inline delete script
     *
     * Only renders once per page to avoid duplicates.
     *
     * @since 1.0.0
     * @return void Outputs HTML directly
     */
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
    
    /**
     * Get sort URL for column
     *
     * @since 1.0.0
     * @param string $column          Column key
     * @param string $current_orderby Current order by column
     * @param string $current_order   Current order direction
     * @return string Sort URL
     */
    private function get_sort_url($column, $current_orderby, $current_order) {
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
            if (!in_array($key, array('orderby', 'order', 's', 'paged'))) {
                $query_args[$key] = sanitize_text_field($value);
            }
        }
        
        return add_query_arg($query_args);
    }
    
    /**
     * Get sort icon HTML for column
     *
     * @since 1.0.0
     * @param string $column          Column key
     * @param string $current_orderby Current order by column
     * @param string $current_order   Current order direction
     * @return string Icon HTML
     */
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