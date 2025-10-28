<?php
/**
 * SAW Data Table - FIXED with proper custom cell rendering
 * 
 * OPRAVY v4.6.2:
 * 1. render_cell_value() nyní správně volá custom_render callback
 * 2. Opravené předávání celého řádku do custom rendereru
 * 3. Debug výpisy pro sledování renderování
 * 4. Lepší error handling
 * 
 * @package SAW_Visitors
 * @version 4.6.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Data_Table {
    
    private $table_id;
    private $config;
    private $model;
    private $columns = array();
    
    /**
     * Constructor
     * 
     * @param string $table_id Unique table identifier (e.g., 'customers', 'users')
     * @param array $config Configuration array
     */
    public function __construct($table_id, $config = array()) {
        $this->table_id = sanitize_key($table_id);
        
        // Default config
        $defaults = array(
            'model_class' => '',
            'columns' => array(),
            'ajax_action' => 'saw_search_' . $this->table_id,
            'per_page' => 20,
            'default_orderby' => 'id',
            'default_order' => 'DESC',
            'search_placeholder' => 'Hledat...',
            'empty_title' => 'Žádné záznamy',
            'empty_message' => 'Zatím nemáte žádné záznamy.',
            'empty_icon' => 'dashicons-list-view',
            'show_search' => true,
            'show_pagination' => true,
            'actions' => array(), // ['edit', 'delete', 'view']
            'add_button' => null, // ['label' => '...', 'url' => '...', 'icon' => '...']
            'row_actions_callback' => null, // Custom row actions function
            'custom_cell_callback' => null, // DEPRECATED - use column-specific custom_render
        );
        
        $this->config = wp_parse_args($config, $defaults);
        
        // 🔍 DEBUG: Log config
        error_log('SAW_Data_Table created: ' . $table_id);
        error_log('Model class: ' . $this->config['model_class']);
        
        // Initialize model
        if (!empty($this->config['model_class']) && class_exists($this->config['model_class'])) {
            $this->model = new $this->config['model_class']();
            error_log('Model initialized: ' . get_class($this->model));
        } else {
            error_log('ERROR: Model class not found: ' . $this->config['model_class']);
        }
        
        // Initialize columns
        $this->init_columns();
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
    }
    
    /**
     * Initialize columns from config
     */
    private function init_columns() {
        foreach ($this->config['columns'] as $key => $column_config) {
            $this->columns[$key] = new SAW_Data_Table_Column($key, $column_config);
        }
        
        error_log('Initialized ' . count($this->columns) . ' columns');
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_' . $this->config['ajax_action'], array($this, 'ajax_search'));
        error_log('Registered AJAX action: ' . $this->config['ajax_action']);
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Universal tables CSS
        wp_enqueue_style(
            'saw-visitors-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-tables.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        // Universal data tables JS
        wp_enqueue_script(
            'saw-visitors-data-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-data-tables.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'saw-visitors-data-tables',
            'sawDataTables',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_data_tables_nonce')
            )
        );
        
        error_log('Data Table assets enqueued');
    }
    
    /**
     * Get data from model
     */
    public function get_data($args = array()) {
        if (!$this->model) {
            error_log('ERROR: No model available for data fetching');
            return array(
                'items' => array(),
                'total' => 0,
                'total_pages' => 0
            );
        }
        
        $defaults = array(
            'search' => '',
            'orderby' => $this->config['default_orderby'],
            'order' => $this->config['default_order'],
            'page' => 1,
            'per_page' => $this->config['per_page']
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Calculate offset
        $args['offset'] = ($args['page'] - 1) * $args['per_page'];
        $args['limit'] = $args['per_page'];
        
        // 🔍 DEBUG: Log query params
        error_log('Fetching data with args: ' . print_r($args, true));
        
        // Get items
        $items = $this->model->get_all($args);
        error_log('Got ' . count($items) . ' items from model');
        
        // Get total count
        $total = $this->model->count($args['search']);
        error_log('Total count: ' . $total);
        
        // Calculate pages
        $total_pages = $total > 0 ? ceil($total / $args['per_page']) : 1;
        
        return array(
            'items' => $items,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $args['page']
        );
    }
    
    /**
     * Render table
     */
    public function render($data = null) {
        // Get data if not provided
        if ($data === null) {
            $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : $this->config['default_orderby'];
            $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : $this->config['default_order'];
            
            $data = $this->get_data(array(
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'page' => $page
            ));
        }
        
        // Wrapper
        echo '<div class="saw-data-table-wrapper" id="saw-' . esc_attr($this->table_id) . '-wrapper">';
        
        // Header (search + add button)
        $this->render_table_header($data);
        
        // Loading overlay
        echo '<div class="saw-loading-overlay" id="saw-' . esc_attr($this->table_id) . '-loading" style="display: none;">';
        echo '<div class="saw-spinner"></div>';
        echo '</div>';
        
        // Table container
        echo '<div class="saw-table-container" id="saw-' . esc_attr($this->table_id) . '-container">';
        
        if (empty($data['items'])) {
            $this->render_empty_state(!empty($search));
        } else {
            $this->render_table_html($data);
        }
        
        echo '</div>';
        
        // Pagination
        if ($this->config['show_pagination'] && !empty($data['items'])) {
            $this->render_pagination($data['current_page'], $data['total_pages']);
        }
        
        echo '</div>'; // .saw-data-table-wrapper
        
        // JavaScript initialization
        $this->render_js_init($search ?? '', $orderby ?? $this->config['default_orderby'], $order ?? $this->config['default_order']);
    }
    
    /**
     * Render table header (search + add button)
     */
    private function render_table_header($data) {
        echo '<div class="saw-table-header">';
        
        // Left side - Search
        if ($this->config['show_search']) {
            echo '<div class="saw-search-box">';
            echo '<input type="search" id="saw-' . esc_attr($this->table_id) . '-search" class="saw-search-input" placeholder="' . esc_attr($this->config['search_placeholder']) . '" value="' . esc_attr($data['search'] ?? '') . '">';
            echo '<button type="button" class="saw-search-clear" id="saw-' . esc_attr($this->table_id) . '-search-clear" style="display: none;">';
            echo '<span class="dashicons dashicons-no-alt"></span>';
            echo '</button>';
            echo '<span class="saw-search-spinner" style="display: none;"><span class="dashicons dashicons-update"></span></span>';
            echo '</div>';
        }
        
        // Right side - Add button
        if (!empty($this->config['add_button'])) {
            $button = $this->config['add_button'];
            $icon = !empty($button['icon']) ? $button['icon'] : 'dashicons-plus-alt';
            
            echo '<div class="saw-table-actions-right">';
            echo '<a href="' . esc_url($button['url']) . '" class="saw-btn saw-btn-primary">';
            echo '<span class="dashicons ' . esc_attr($icon) . '"></span> ';
            echo esc_html($button['label']);
            echo '</a>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Count info
        if (!empty($data['items'])) {
            echo '<div class="saw-table-info">';
            echo '<span class="saw-table-name">' . esc_html(ucfirst($this->table_id)) . '</span> ';
            echo '(<span id="saw-' . esc_attr($this->table_id) . '-count">' . intval($data['total']) . '</span>)';
            echo '</div>';
        }
    }
    
    /**
     * Render empty state
     */
    private function render_empty_state($is_search_result = false) {
        echo '<div class="saw-empty-state">';
        echo '<span class="dashicons ' . esc_attr($this->config['empty_icon']) . '"></span>';
        echo '<h3>' . esc_html($this->config['empty_title']) . '</h3>';
        echo '<p>';
        if ($is_search_result) {
            echo 'Nebyli nalezeni žádné záznamy odpovídající hledanému výrazu.';
        } else {
            echo esc_html($this->config['empty_message']);
        }
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Render table HTML
     */
    private function render_table_html($data) {
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : $this->config['default_orderby'];
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : $this->config['default_order'];
        
        echo '<div class="saw-table-responsive">';
        echo '<table class="saw-table saw-table-sortable">';
        
        // Header
        echo '<thead><tr>';
        foreach ($this->columns as $column) {
            $column->render_header($orderby, $order);
        }
        
        // Actions column
        if (!empty($this->config['actions']) || !empty($this->config['row_actions_callback'])) {
            echo '<th style="width: 140px;" class="saw-text-center">Akce</th>';
        }
        
        echo '</tr></thead>';
        
        // Body
        echo '<tbody id="saw-' . esc_attr($this->table_id) . '-tbody">';
        foreach ($data['items'] as $item) {
            $this->render_row($item);
        }
        echo '</tbody>';
        
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Render single row
     */
    private function render_row($item) {
        echo '<tr>';
        
        foreach ($this->columns as $key => $column) {
            echo '<td';
            if ($column->get_width()) {
                echo ' style="width: ' . esc_attr($column->get_width()) . ';"';
            }
            if ($column->get_label()) {
                echo ' data-label="' . esc_attr($column->get_label()) . '"';
            }
            echo '>';
            
            // ✅ OPRAVENO: Použití render_cell_value s celým řádkem
            echo $this->render_cell_value($column, $key, $item);
            
            echo '</td>';
        }
        
        // Actions column
        if (!empty($this->config['actions']) || !empty($this->config['row_actions_callback'])) {
            echo '<td class="saw-text-center" data-label="Akce">';
            
            if (!empty($this->config['row_actions_callback']) && is_callable($this->config['row_actions_callback'])) {
                // Custom row actions
                echo call_user_func($this->config['row_actions_callback'], $item);
            } else {
                // Default actions
                echo $this->render_default_actions($item);
            }
            
            echo '</td>';
        }
        
        echo '</tr>';
    }
    
    /**
     * ✅ OPRAVENO: Render cell value with proper custom callback support
     * 
     * @param SAW_Data_Table_Column $column Column object
     * @param string $key Column key
     * @param array $row Complete row data
     * @return string HTML
     */
    private function render_cell_value($column, $key, $row) {
        $value = isset($row[$key]) ? $row[$key] : '';
        
        // 🔍 DEBUG: Log rendering
        if ($column->get_type() === 'custom') {
            error_log("Rendering custom cell: $key");
        }
        
        // ✅ OPRAVENO: Check for column-specific custom renderer FIRST
        if ($column->get_type() === 'custom' && $column->get_custom_render()) {
            $custom_render = $column->get_custom_render();
            
            if (is_callable($custom_render)) {
                error_log("Calling custom render for: $key");
                // Pass both value AND complete row
                return call_user_func($custom_render, $value, $row);
            } else {
                error_log("ERROR: Custom render for $key is not callable");
                return '<em style="color: #ef4444;">Error: Invalid custom renderer</em>';
            }
        }
        
        // Default rendering based on type
        switch ($column->get_type()) {
            case 'image':
                if ($value) {
                    return '<img src="' . esc_url($value) . '" alt="" style="max-width: 60px; max-height: 60px;">';
                }
                return '';
                
            case 'badge':
                return '<span class="saw-badge">' . esc_html($value) . '</span>';
                
            case 'date':
                if ($value) {
                    return date_i18n(get_option('date_format'), strtotime($value));
                }
                return '';
                
            case 'text':
            default:
                return esc_html($value);
        }
    }
    
    /**
     * Render default actions
     */
    private function render_default_actions($item) {
        $html = '<div class="saw-table-actions">';
        
        foreach ($this->config['actions'] as $action) {
            switch ($action) {
                case 'edit':
                    $html .= '<button type="button" class="saw-btn saw-btn-sm saw-btn-secondary" data-action="edit" data-id="' . esc_attr($item['id']) . '" title="Upravit">';
                    $html .= '<span class="dashicons dashicons-edit"></span>';
                    $html .= '</button>';
                    break;
                    
                case 'delete':
                    $html .= '<button type="button" class="saw-btn saw-btn-sm saw-btn-danger" data-action="delete" data-id="' . esc_attr($item['id']) . '" title="Smazat">';
                    $html .= '<span class="dashicons dashicons-trash"></span>';
                    $html .= '</button>';
                    break;
                    
                case 'view':
                    $html .= '<button type="button" class="saw-btn saw-btn-sm saw-btn-secondary" data-action="view" data-id="' . esc_attr($item['id']) . '" title="Zobrazit">';
                    $html .= '<span class="dashicons dashicons-visibility"></span>';
                    $html .= '</button>';
                    break;
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render pagination
     */
    private function render_pagination($current_page, $total_pages) {
        echo '<div class="saw-pagination" id="saw-' . esc_attr($this->table_id) . '-pagination">';
        
        // Previous
        $prev_disabled = $current_page <= 1 ? 'disabled' : '';
        echo '<button type="button" class="saw-pagination-btn saw-pagination-prev" ' . $prev_disabled . ' data-page="' . ($current_page - 1) . '">';
        echo '<span class="dashicons dashicons-arrow-left-alt2"></span> Předchozí';
        echo '</button>';
        
        // Pages
        echo '<div class="saw-pagination-pages">';
        
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        if ($start > 1) {
            echo '<button type="button" class="saw-pagination-btn" data-page="1">1</button>';
            if ($start > 2) {
                echo '<span class="saw-pagination-dots">...</span>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $current_page ? 'active' : '';
            echo '<button type="button" class="saw-pagination-btn ' . $active . '" data-page="' . $i . '">' . $i . '</button>';
        }
        
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                echo '<span class="saw-pagination-dots">...</span>';
            }
            echo '<button type="button" class="saw-pagination-btn" data-page="' . $total_pages . '">' . $total_pages . '</button>';
        }
        
        echo '</div>';
        
        // Next
        $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
        echo '<button type="button" class="saw-pagination-btn saw-pagination-next" ' . $next_disabled . ' data-page="' . ($current_page + 1) . '">';
        echo 'Další <span class="dashicons dashicons-arrow-right-alt2"></span>';
        echo '</button>';
        
        echo '</div>';
    }
    
    /**
     * Render JavaScript initialization
     */
    private function render_js_init($search, $orderby, $order) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof SAWDataTable !== 'undefined') {
                console.log('Initializing SAWDataTable for: <?php echo esc_js($this->table_id); ?>');
                new SAWDataTable('<?php echo esc_js($this->table_id); ?>', {
                    ajaxAction: '<?php echo esc_js($this->config['ajax_action']); ?>',
                    perPage: <?php echo intval($this->config['per_page']); ?>,
                    currentSearch: '<?php echo esc_js($search); ?>',
                    currentOrderBy: '<?php echo esc_js($orderby); ?>',
                    currentOrder: '<?php echo esc_js($order); ?>'
                });
            } else {
                console.error('SAWDataTable class not found!');
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX search handler
     */
    public function ajax_search() {
        error_log('=== AJAX Search Called ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Security check
        if (!check_ajax_referer('saw_data_tables_nonce', 'nonce', false)) {
            error_log('ERROR: Invalid nonce');
            wp_send_json_error(array('message' => 'Neplatný bezpečnostní token'));
        }
        
        // Get parameters
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : $this->config['default_orderby'];
        $order = isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : $this->config['default_order'];
        
        error_log("Search params: search=$search, page=$page, orderby=$orderby, order=$order");
        
        // Get data
        $data = $this->get_data(array(
            'search' => $search,
            'page' => $page,
            'orderby' => $orderby,
            'order' => $order
        ));
        
        error_log('Returning ' . count($data['items']) . ' items');
        
        // Return JSON response
        wp_send_json_success($data);
    }
}