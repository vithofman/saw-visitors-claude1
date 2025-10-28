<?php
/**
 * SAW Data Table - Universal table system with AJAX, sorting, pagination
 * 
 * @package SAW_Visitors
 * @version 4.6.1
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
            'add_button' => null, // ['label' => '...', 'url' => '...']
            'row_actions_callback' => null, // Custom row actions function
            'custom_cell_callback' => null, // Custom cell rendering function
        );
        
        $this->config = wp_parse_args($config, $defaults);
        
        // Initialize model
        if (!empty($this->config['model_class']) && class_exists($this->config['model_class'])) {
            $this->model = new $this->config['model_class']();
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
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_' . $this->config['ajax_action'], array($this, 'ajax_search'));
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
    }
    
    /**
     * Get data from model
     */
    public function get_data($args = array()) {
        if (!$this->model) {
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
        
        // Get items
        $items = $this->model->get_all($args);
        
        // Get total count
        $total = $this->model->count($args['search']);
        
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
            $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : $this->config['default_order'];
            
            $data = $this->get_data(array(
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'page' => $page
            ));
        }
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Start output
        ob_start();
        
        // Card wrapper
        echo '<div class="saw-card">';
        
        // Card header with search
        $this->render_card_header($data['total'], isset($search) ? $search : '');
        
        // Card body
        echo '<div class="saw-card-body">';
        
        // Loading overlay
        $this->render_loading_overlay();
        
        // Container
        echo '<div id="saw-' . esc_attr($this->table_id) . '-container">';
        
        // Check if we have items
        if (empty($data['items'])) {
            $this->render_empty_state(isset($search) && !empty($search));
        } else {
            $this->render_table_html($data);
        }
        
        echo '</div>'; // container
        
        // Pagination
        if ($this->config['show_pagination'] && $data['total_pages'] > 1) {
            $this->render_pagination($data['current_page'], $data['total_pages']);
        }
        
        echo '</div>'; // card-body
        echo '</div>'; // card
        
        // Initialize JavaScript
        $this->render_js_init(isset($search) ? $search : '', isset($orderby) ? $orderby : '', isset($order) ? $order : '');
        
        return ob_get_clean();
    }
    
    /**
     * Render card header with search
     */
    private function render_card_header($total, $search) {
        echo '<div class="saw-card-header">';
        echo '<div class="saw-card-header-left">';
        echo '<h2 class="saw-card-title">';
        echo esc_html(ucfirst($this->table_id)) . ' (<span id="saw-' . esc_attr($this->table_id) . '-count">' . esc_html($total) . '</span>)';
        echo '</h2>';
        echo '</div>';
        
        echo '<div class="saw-card-header-right">';
        
        // Search box
        if ($this->config['show_search']) {
            echo '<div class="saw-search-input-wrapper">';
            echo '<input type="text" ';
            echo 'id="saw-' . esc_attr($this->table_id) . '-search" ';
            echo 'value="' . esc_attr($search) . '" ';
            echo 'placeholder="' . esc_attr($this->config['search_placeholder']) . '" ';
            echo 'class="saw-search-input" autocomplete="off">';
            
            $clear_display = !empty($search) ? 'flex' : 'none';
            echo '<button type="button" id="saw-' . esc_attr($this->table_id) . '-search-clear" ';
            echo 'class="saw-search-clear" style="display: ' . esc_attr($clear_display) . ';">';
            echo '<span class="dashicons dashicons-no-alt"></span>';
            echo '</button>';
            
            echo '<div class="saw-search-spinner" style="display: none;">';
            echo '<span class="spinner is-active"></span>';
            echo '</div>';
            
            echo '</div>'; // search-input-wrapper
        }
        
        // Add button
        if ($this->config['add_button']) {
            echo '<a href="' . esc_url($this->config['add_button']['url']) . '" class="saw-btn saw-btn-primary">';
            echo '<span class="dashicons dashicons-plus-alt"></span> ';
            echo esc_html($this->config['add_button']['label']);
            echo '</a>';
        }
        
        echo '</div>'; // card-header-right
        echo '</div>'; // card-header
    }
    
    /**
     * Render loading overlay
     */
    private function render_loading_overlay() {
        echo '<div id="saw-' . esc_attr($this->table_id) . '-loading" class="saw-loading-overlay" style="display: none;">';
        echo '<div class="saw-loading-spinner">';
        echo '<span class="spinner is-active"></span>';
        echo '<p>Načítám data...</p>';
        echo '</div>';
        echo '</div>';
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
        if (!empty($this->config['actions'])) {
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
            
            // Custom cell rendering
            if ($this->config['custom_cell_callback'] && is_callable($this->config['custom_cell_callback'])) {
                call_user_func($this->config['custom_cell_callback'], $key, $item, $column);
            } else {
                // Default rendering
                if (isset($item->{$key})) {
                    echo esc_html($item->{$key});
                }
            }
            
            echo '</td>';
        }
        
        // Actions
        if (!empty($this->config['actions'])) {
            echo '<td class="saw-text-center">';
            echo '<div class="saw-table-actions">';
            
            if ($this->config['row_actions_callback'] && is_callable($this->config['row_actions_callback'])) {
                call_user_func($this->config['row_actions_callback'], $item);
            } else {
                $this->render_default_actions($item);
            }
            
            echo '</div>';
            echo '</td>';
        }
        
        echo '</tr>';
    }
    
    /**
     * Render default action buttons
     */
    private function render_default_actions($item) {
        foreach ($this->config['actions'] as $action) {
            if ($action === 'edit') {
                $url = add_query_arg('id', $item->id, home_url('/admin/settings/' . $this->table_id . '/edit/'));
                echo '<a href="' . esc_url($url) . '" class="saw-btn saw-btn-sm saw-btn-secondary" title="Upravit">';
                echo '<span class="dashicons dashicons-edit"></span>';
                echo '</a>';
            } elseif ($action === 'delete') {
                echo '<button type="button" class="saw-btn saw-btn-sm saw-btn-danger saw-delete-item" ';
                echo 'data-id="' . esc_attr($item->id) . '" title="Smazat">';
                echo '<span class="dashicons dashicons-trash"></span>';
                echo '</button>';
            } elseif ($action === 'view') {
                $url = add_query_arg('id', $item->id, home_url('/admin/' . $this->table_id . '/view/'));
                echo '<a href="' . esc_url($url) . '" class="saw-btn saw-btn-sm saw-btn-secondary" title="Zobrazit">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '</a>';
            }
        }
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
                new SAWDataTable('<?php echo esc_js($this->table_id); ?>', {
                    ajaxAction: '<?php echo esc_js($this->config['ajax_action']); ?>',
                    perPage: <?php echo intval($this->config['per_page']); ?>,
                    currentSearch: '<?php echo esc_js($search); ?>',
                    currentOrderBy: '<?php echo esc_js($orderby); ?>',
                    currentOrder: '<?php echo esc_js($order); ?>'
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX search handler
     */
    public function ajax_search() {
        // Security check
        if (!check_ajax_referer('saw_data_tables_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Neplatný bezpečnostní token'));
        }
        
        // Get parameters
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : $this->config['default_orderby'];
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : $this->config['default_order'];
        
        // Validate order
        $order = strtoupper($order);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        try {
            // Get data
            $data = $this->get_data(array(
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'page' => $page
            ));
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Došlo k chybě: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get table ID
     */
    public function get_table_id() {
        return $this->table_id;
    }
    
    /**
     * Get config
     */
    public function get_config($key = null) {
        if ($key) {
            return isset($this->config[$key]) ? $this->config[$key] : null;
        }
        return $this->config;
    }
}