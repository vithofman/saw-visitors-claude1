<?php
/**
 * Visitors Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @since       1.0.0
 * @version     4.0.0 - Production: translations, fixed current_status, removed debug logs
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}
if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_Visitors_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visitors_Model($this->config);
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_checkin', array($this, 'ajax_checkin'));
        add_action('wp_ajax_saw_checkout', array($this, 'ajax_checkout'));
        add_action('wp_ajax_saw_add_adhoc_visitor', array($this, 'ajax_add_adhoc_visitor'));
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Index action - display list view
     */
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    /**
     * Enqueue module assets
     */
    public function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('visitors');
        
        wp_enqueue_style('dashicons');
        
        wp_localize_script('saw-visitors', 'sawVisitorsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
        ));
    }

    /**
     * Prepare form data from POST
     * 
     * @param array $post POST data
     * @return array Sanitized form data
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $sanitize = $field_config['sanitize'] ?? 'sanitize_text_field';
                $data[$field_name] = $sanitize($post[$field_name]);
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        return $data;
    }
    
    /**
     * After save hook - save certificates
     * 
     * @param int $id Visitor ID
     */
    protected function after_save($id) {
        if (isset($_POST['certificates']) && is_array($_POST['certificates'])) {
            $this->model->save_certificates($id, $_POST['certificates']);
        }
    }

    /**
     * Format detail data for sidebar
     *
     * Eager loads visit data, certificates, daily logs.
     * Computes current_status based on today's check-in/out log.
     *
     * @param array $item Visitor data
     * @return array Formatted visitor data
     */
    protected function format_detail_data($item) {
        if (empty($item) || empty($item['id'])) {
            return $item;
        }
        
        global $wpdb;
        $visitor_id = $item['id'];
        $visit_id = $item['visit_id'] ?? 0;
        
        // Load visit data with company in one query
        if ($visit_id) {
            $visit_query = $wpdb->prepare(
                "SELECT 
                    v.id as visit_id,
                    v.visit_type,
                    v.status as visit_status,
                    v.company_id,
                    v.branch_id,
                    c.name as company_name,
                    c.ico as company_ico,
                    b.name as branch_name
                FROM {$wpdb->prefix}saw_visits v
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                WHERE v.id = %d",
                $visit_id
            );
            
            $visit_data = $wpdb->get_row($visit_query, ARRAY_A);
            
            if ($visit_data) {
                // Load hosts using GROUP_CONCAT for MySQL 5.7 compatibility
                $hosts_query = $wpdb->prepare(
                    "SELECT 
                        GROUP_CONCAT(
                            CONCAT_WS(':', u.id, u.first_name, u.last_name, u.email)
                            ORDER BY u.last_name
                            SEPARATOR '|'
                        ) as hosts_data
                    FROM {$wpdb->prefix}saw_visit_hosts vh
                    INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
                    WHERE vh.visit_id = %d",
                    $visit_id
                );
                
                $hosts_result = $wpdb->get_row($hosts_query, ARRAY_A);
                
                // Parse hosts
                $hosts = array();
                if (!empty($hosts_result['hosts_data'])) {
                    foreach (explode('|', $hosts_result['hosts_data']) as $host_str) {
                        $parts = explode(':', $host_str);
                        if (count($parts) >= 4) {
                            $hosts[] = array(
                                'id' => $parts[0],
                                'first_name' => $parts[1],
                                'last_name' => $parts[2],
                                'email' => $parts[3],
                            );
                        }
                    }
                }
                
                $visit_data['hosts'] = $hosts;
                $item['visit_data'] = $visit_data;
            }
        }
        
        // Load certificates
        if (method_exists($this->model, 'get_certificates')) {
            $item['certificates'] = $this->model->get_certificates($visitor_id);
        }
        
        // Load daily logs
        if (method_exists($this->model, 'get_daily_logs')) {
            $item['daily_logs'] = $this->model->get_daily_logs($visitor_id);
        }
        
        // Compute current status based on today's log
        $today = current_time('Y-m-d');
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visitor_id = %d AND log_date = %s
             ORDER BY checked_in_at DESC
             LIMIT 1",
            $visitor_id,
            $today
        ), ARRAY_A);
        
        if ($log) {
            // Has today's log
            if (!empty($log['checked_in_at']) && empty($log['checked_out_at'])) {
                // Checked in but not out = present
                $item['current_status'] = 'present';
                $item['checked_in_at'] = $log['checked_in_at'];
            } else {
                // Has check-out = checked out
                $item['current_status'] = 'checked_out';
                $item['checked_out_at'] = $log['checked_out_at'];
            }
        } else {
            // No today's log - use participation_status from database
            // Valid values: planned, confirmed, no_show
            $participation_status = $item['participation_status'] ?? 'planned';
            
            // Map participation_status to current_status
            // (they use same values, but we ensure only valid ones)
            $valid_statuses = array('planned', 'confirmed', 'no_show');
            if (in_array($participation_status, $valid_statuses)) {
                $item['current_status'] = $participation_status;
            } else {
                $item['current_status'] = 'planned';
            }
        }
        
        return $item;
    }
    
    /**
     * Get display name for detail header
     * 
     * @param array $item Item data
     * @return string Display name
     */
    public function get_display_name($item) {
        if (empty($item)) {
            return '';
        }
        
        $name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        if (!empty($name)) {
            return $name;
        }
        
        return '#' . ($item['id'] ?? '');
    }
    
    /**
     * Get header meta badges for detail sidebar
     * 
     * Shows: current status badge, position badge
     * 
     * @param array $item Item data
     * @return string HTML with badges
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        // Load translations
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'visitors') 
            : [];
        
        $tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        $meta_parts = array();
        
        // Current status badge
        if (!empty($item['current_status'])) {
            $status_labels = array(
                'present' => $tr('status_present', '✅ Přítomen'),
                'checked_out' => $tr('status_checked_out', '🚪 Odhlášen'),
                'confirmed' => $tr('status_confirmed', '⏳ Potvrzený'),
                'planned' => $tr('status_planned', '📅 Plánovaný'),
                'no_show' => $tr('status_no_show', '❌ Nedostavil se'),
            );
            $status_classes = array(
                'present' => 'saw-badge-success',
                'checked_out' => 'saw-badge-secondary',
                'confirmed' => 'saw-badge-warning',
                'planned' => 'saw-badge-info',
                'no_show' => 'saw-badge-danger',
            );
            
            $status_label = $status_labels[$item['current_status']] ?? $item['current_status'];
            $status_class = $status_classes[$item['current_status']] ?? 'saw-badge-secondary';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        }
        
        // Position badge
        if (!empty($item['position'])) {
            $meta_parts[] = '<span class="saw-badge-transparent">💼 ' . esc_html($item['position']) . '</span>';
        }
        
        return implode('', $meta_parts);
    }
    
    /**
     * Get list data with current_status filtering
     * 
     * current_status is computed dynamically, not stored in DB.
     * We load all data, then filter by current_status in PHP.
     * 
     * @return array List data with computed current_status
     */
    protected function get_list_data() {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'vis.id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Check if infinite scroll is enabled
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        $per_page = $infinite_scroll_enabled 
            ? ($this->config['infinite_scroll']['per_page'] ?? 50)
            : ($this->config['list_config']['per_page'] ?? 20);
        
        // Build filters - exclude current_status (not in DB)
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => 1,
            'per_page' => 9999,
        );
        
        // Get tab parameter name
        $tab_param = null;
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
        }
        
        // Apply list_config filters (except tab_param)
        if (!empty($this->config['list_config']['filters'])) {
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                if ($filter_key === $tab_param) {
                    continue;
                }
                if ($enabled && isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                    $filters[$filter_key] = sanitize_text_field(wp_unslash($_GET[$filter_key]));
                }
            }
        }
        
        // Load all data from model
        $data = $this->model->get_all($filters);
        $all_items = $data['items'] ?? array();
        
        // Handle TAB filtering by current_status in PHP
        $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
        $url_value = null;
        
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            $url_value = isset($_GET[$tab_param]) ? sanitize_text_field(wp_unslash($_GET[$tab_param])) : null;
            
            if ($url_value === null || $url_value === '') {
                $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
            } else {
                $tab_found = false;
                foreach ($this->config['tabs']['tabs'] as $tab_key => $tab_config) {
                    if ($tab_config['filter_value'] !== null && 
                        (string)$tab_config['filter_value'] === (string)$url_value) {
                        $current_tab = (string)$tab_key;
                        $tab_found = true;
                        break;
                    }
                }
                
                if (!$tab_found) {
                    $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
                }
            }
            
            // Filter items by current_status
            if ($url_value !== null && $url_value !== '') {
                $filtered_items = array();
                foreach ($all_items as $item) {
                    if ($item['current_status'] === $url_value) {
                        $filtered_items[] = $item;
                    }
                }
                $all_items = $filtered_items;
            }
        }
        
        // Apply pagination in PHP
        $total_items = count($all_items);
        $total_pages = ceil($total_items / $per_page);
        $offset = ($page - 1) * $per_page;
        $items = array_slice($all_items, $offset, $per_page);
        
        $result = array(
            'items' => $items,
            'total' => $total_items,
            'page' => $page,
            'total_pages' => $total_pages,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
        );
        
        // Add tab data
        if (!empty($this->config['tabs']['enabled'])) {
            $result['current_tab'] = (isset($current_tab) && $current_tab !== null && $current_tab !== '') 
                ? (string)$current_tab 
                : (string)($this->config['tabs']['default_tab'] ?? 'all');
            $result['tab_counts'] = $this->get_tab_counts();
        }
        
        // Pass through other GET parameters
        foreach ($_GET as $key => $value) {
            if (!in_array($key, array('s', 'orderby', 'order', 'paged'))) {
                $result[$key] = sanitize_text_field(wp_unslash($value));
            }
        }
        
        return $result;
    }
    
    /**
     * Get tab counts with current_status counting in PHP
     * 
     * @return array Tab key => count
     */
    protected function get_tab_counts() {
        if (empty($this->config['tabs']['enabled'])) {
            return array();
        }
        
        $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
        $tabs = $this->config['tabs']['tabs'] ?? array();
        $counts = array();
        
        // Build filters - exclude current_status
        $filters = array(
            'page' => 1,
            'per_page' => 9999,
        );
        
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        // Apply list_config filters (except tab_param)
        if (!empty($this->config['list_config']['filters'])) {
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                if ($filter_key === $tab_param) {
                    continue;
                }
                if ($enabled && isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                    $filters[$filter_key] = sanitize_text_field(wp_unslash($_GET[$filter_key]));
                }
            }
        }
        
        // Load all data
        $data = $this->model->get_all($filters);
        $all_items = $data['items'] ?? array();
        
        // Count items for each tab
        foreach ($tabs as $tab_key => $tab_config) {
            if (empty($tab_config['count_query'])) {
                $counts[$tab_key] = 0;
                continue;
            }
            
            $filter_value = $tab_config['filter_value'];
            $count = 0;
            
            foreach ($all_items as $item) {
                if ($filter_value === null || $filter_value === '') {
                    $count++;
                } elseif ($item['current_status'] === (string)$filter_value) {
                    $count++;
                }
            }
            
            $counts[$tab_key] = $count;
        }
        
        return $counts;
    }
    
    /**
     * Get table columns configuration
     * 
     * @return array Column configuration
     */
    public function get_table_columns() {
        // Load translations
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'visitors') 
            : [];
        
        $tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        return array(
            'first_name' => array(
                'label' => $tr('col_first_name', 'Jméno'),
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
                'sortable' => true,
            ),
            'last_name' => array(
                'label' => $tr('col_last_name', 'Příjmení'),
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
                'sortable' => true,
            ),
            'company_name' => array(
                'label' => $tr('col_company', 'Firma'),
                'type' => 'text',
            ),
            'branch_name' => array(
                'label' => $tr('col_branch', 'Pobočka'),
                'type' => 'text',
            ),
            'current_status' => array(
                'label' => $tr('col_current_status', 'Aktuální stav'),
                'type' => 'badge',
                'sortable' => false,
                'map' => array(
                    'present' => 'success',
                    'checked_out' => 'secondary',
                    'confirmed' => 'warning',
                    'planned' => 'info',
                    'no_show' => 'danger',
                ),
                'labels' => array(
                    'present' => $tr('status_present', '✅ Přítomen'),
                    'checked_out' => $tr('status_checked_out', '🚪 Odhlášen'),
                    'confirmed' => $tr('status_confirmed', '⏳ Potvrzený'),
                    'planned' => $tr('status_planned', '📅 Plánovaný'),
                    'no_show' => $tr('status_no_show', '❌ Nedostavil se'),
                ),
            ),
            'first_checkin_at' => array(
                'label' => $tr('col_first_checkin', 'První check-in'),
                'type' => 'callback',
                'callback' => function($value) {
                    return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
                },
            ),
            'last_checkout_at' => array(
                'label' => $tr('col_last_checkout', 'Poslední check-out'),
                'type' => 'callback',
                'callback' => function($value) {
                    return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
                },
            ),
            'training_status' => array(
                'label' => $tr('col_training', 'Školení'),
                'type' => 'badge',
                'map' => array(
                    'completed' => 'success',
                    'in_progress' => 'info',
                    'skipped' => 'warning',
                    'not_started' => 'secondary',
                ),
                'labels' => array(
                    'completed' => $tr('training_completed', '✅ Dokončeno'),
                    'in_progress' => $tr('training_in_progress', '🔄 Probíhá'),
                    'skipped' => $tr('training_skipped', '⏭️ Přeskočeno'),
                    'not_started' => $tr('training_not_started', '⚪ Nespuštěno'),
                ),
            ),
        );
    }
    
    /**
     * AJAX handler for infinite scroll
     * 
     * Overrides parent to handle current_status filtering in PHP.
     * 
     * @return void Outputs JSON
     */
    public function ajax_get_items_infinite() {
        saw_verify_ajax_unified();
        
        if (!$this->can('list')) {
            wp_send_json_error(array(
                'message' => 'Nemáte oprávnění zobrazit záznamy'
            ));
        }
        
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        
        // Respect initial_load config for first page
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        if ($infinite_scroll_enabled && $page === 1) {
            $per_page = $this->config['infinite_scroll']['initial_load'] ?? 100;
        } else {
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        }
        
        $per_page = max(1, min(100, $per_page));
        
        // Build filters - exclude current_status (not in DB)
        $filters = array(
            'page' => 1,
            'per_page' => 9999,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'vis.id',
            'order' => isset($_POST['order']) ? strtoupper(sanitize_text_field($_POST['order'])) : 'DESC',
        );
        
        // Extract current_status filter for PHP filtering
        $current_status_filter = null;
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            if (isset($_POST[$tab_param]) && $_POST[$tab_param] !== '') {
                $current_status_filter = sanitize_text_field($_POST[$tab_param]);
            }
        }
        
        // Add other filters from POST (except tab_param)
        $tab_param = !empty($this->config['tabs']['enabled']) ? ($this->config['tabs']['tab_param'] ?? 'tab') : null;
        foreach ($_POST as $key => $value) {
            if (!in_array($key, array('action', 'nonce', 'page', 'per_page', 'search', 'orderby', 'order', 'columns'))) {
                if ($key === $tab_param) {
                    continue;
                }
                if (!empty($this->config['list_config']['filters'][$key])) {
                    $filters[$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Cache key for filtered data
        $cache_key_parts = array(
            'entity' => $this->entity,
            'filters' => $filters,
            'current_status' => $current_status_filter,
        );
        $cache_key = 'visitors_infinite_' . md5(serialize($cache_key_parts));
        
        // Get filtered data with cache
        $cached_all_items = wp_cache_get($cache_key, 'saw_visitors');
        
        if ($cached_all_items === false) {
            $data = $this->model->get_all($filters);
            $all_items = $data['items'] ?? array();
            
            // Filter by current_status in PHP
            if ($current_status_filter !== null && $current_status_filter !== '') {
                $filtered_items = array();
                foreach ($all_items as $item) {
                    if (($item['current_status'] ?? '') === $current_status_filter) {
                        $filtered_items[] = $item;
                    }
                }
                $all_items = $filtered_items;
            }
            
            wp_cache_set($cache_key, $all_items, 'saw_visitors', 300);
        } else {
            $all_items = $cached_all_items;
        }
        
        // Apply pagination in PHP
        $total_items = count($all_items);
        $offset = ($page - 1) * $per_page;
        $items = array_slice($all_items, $offset, $per_page);
        
        $data['items'] = $items;
        $data['total'] = $total_items;
        
        // Get columns config
        // ⭐ FIX: Always prioritize get_table_columns() if it exists, because it contains callbacks
        // JSON columns don't have callbacks (can't be serialized), so we need the full config
        $columns = array();
        
        if (method_exists($this, 'get_table_columns')) {
            // Priority: use get_table_columns() - it contains callbacks and full config
            $columns = $this->get_table_columns();
        } else {
            // Fallback: use columns from JSON (without callbacks)
            $columns_json = isset($_POST['columns']) ? $_POST['columns'] : '';
            if (!empty($columns_json)) {
                $decoded = json_decode(stripslashes($columns_json), true);
                if (is_array($decoded)) {
                    $columns = $decoded;
                }
            }
        }
        
        if (!is_array($columns)) {
            $columns = array();
        }
        
        // Load admin table component
        if (!class_exists('SAW_Component_Admin_Table')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';
        }
        
        // Build table config for rendering
        $base_url = home_url('/admin/' . ($this->config['route'] ?? $this->entity));
        $edit_url = $base_url . '/{id}/edit';
        $detail_url = $this->get_detail_url();
        $actions = $this->config['actions'] ?? array();
        
        $table_config = array(
            'columns' => $columns,
            'actions' => $actions,
            'detail_url' => $detail_url,
            'edit_url' => $edit_url,
            'rows' => $data['items'],
            'module_config' => $this->config,
            'entity' => $this->entity,
        );
        
        $table = new SAW_Component_Admin_Table($this->entity, $table_config);
        
        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Render rows HTML
        $rows_html = '';
        if (!empty($data['items'])) {
            ob_start();
            foreach ($data['items'] as $row) {
                $row_detail_url = $this->get_detail_url();
                if (!empty($row_detail_url) && !empty($row['id'])) {
                    $row_detail_url = str_replace('{id}', intval($row['id']), $row_detail_url);
                } else {
                    $row_detail_url = '';
                }
                
                $row_class = 'saw-table-row';
                if (!empty($row_detail_url)) {
                    $row_class .= ' saw-clickable-row';
                }
                ?>
                <tr class="<?php echo esc_attr($row_class); ?>" 
                    data-id="<?php echo esc_attr($row['id'] ?? ''); ?>"
                    <?php if (!empty($row_detail_url)): ?>
                        data-detail-url="<?php echo esc_url($row_detail_url); ?>"
                    <?php endif; ?>>
                    
                    <?php foreach ($columns as $key => $column): ?>
                        <?php $table->render_table_cell_for_template($row, $key, $column); ?>
                    <?php endforeach; ?>
                </tr>
                <?php
            }
            $rows_html = ob_get_clean();
        }
        
        // Calculate has_more
        $has_more = ($page * $per_page) < $total_items;
        
        wp_send_json_success(array(
            'html' => $rows_html,
            'has_more' => $has_more,
            'page' => $page,
            'total' => $total_items,
            'loaded' => count($data['items'])
        ));
    }
    
    // ============================================
    // AJAX HANDLERS
    // ============================================
    
    /**
     * AJAX: Check-in visitor
     */
    public function ajax_checkin() {
        saw_verify_ajax_unified();
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $visitor_id = isset($_POST['visitor_id']) ? intval($_POST['visitor_id']) : 0;
        $log_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : current_time('Y-m-d');
        
        if (!$visitor_id) {
            wp_send_json_error(array('message' => 'Neplatný návštěvník'));
            return;
        }
        
        $result = $this->model->daily_checkin($visitor_id, $log_date);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Check-in úspěšný',
            'checked_in_at' => current_time('Y-m-d H:i:s'),
        ));
    }
    
    /**
     * AJAX: Check-out visitor
     */
    public function ajax_checkout() {
    saw_verify_ajax_unified();
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        return;
    }
    
    $visitor_id = isset($_POST['visitor_id']) ? intval($_POST['visitor_id']) : 0;
    $manual = isset($_POST['manual']) ? (bool) $_POST['manual'] : false;
    $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : null;
    
    // ✅ FIXED v5.6.1: For manual checkout, use NULL to find ANY active log
    // This supports overnight visitors whose log_date is from previous day
    if ($manual) {
        $log_date = null;
    } else {
        $log_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : current_time('Y-m-d');
    }
    
    if (!$visitor_id) {
        wp_send_json_error(array('message' => 'Neplatný návštěvník'));
        return;
    }
    
    global $wpdb;
    $visitor_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    
    if (!$visitor_exists) {
        wp_send_json_error(array('message' => 'Návštěvník nenalezen'));
        return;
    }
    
    $admin_id = null;
    if ($manual) {
        $admin_id = get_current_user_id();
    }
    
    $result = $this->model->daily_checkout($visitor_id, $log_date, $manual, $admin_id, $reason);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }
    
    wp_send_json_success(array(
        'message' => 'Check-out úspěšný',
        'checked_out_at' => current_time('Y-m-d H:i:s'),
    ));
}
    
    /**
     * AJAX: Add ad-hoc visitor
     */
    public function ajax_add_adhoc_visitor() {
        saw_verify_ajax_unified();
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $visit_id = isset($_POST['visit_id']) ? intval($_POST['visit_id']) : 0;
        
        if (!$visit_id) {
            wp_send_json_error(array('message' => 'Neplatná návštěva'));
            return;
        }
        
        $visitor_data = array(
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '',
            'position' => isset($_POST['position']) ? sanitize_text_field($_POST['position']) : '',
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'training_skipped' => isset($_POST['training_skipped']) ? 1 : 0,
        );
        
        if (empty($visitor_data['first_name']) || empty($visitor_data['last_name'])) {
            wp_send_json_error(array('message' => 'Jméno a příjmení jsou povinné'));
            return;
        }
        
        $visitor_id = $this->model->add_adhoc_visitor($visit_id, $visitor_data);
        
        if (is_wp_error($visitor_id)) {
            wp_send_json_error(array('message' => $visitor_id->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'visitor_id' => $visitor_id,
            'message' => 'Návštěvník přidán',
        ));
    }
    
    /**
     * AJAX: Get adjacent visitor ID for navigation
     * 
     * Visitors don't have customer_id/branch_id directly - need to JOIN with visits.
     */
    public function ajax_get_adjacent_id() {
        try {
            saw_verify_ajax_unified();
            
            if (!isset($this->model) || !$this->model) {
                wp_send_json_error(array(
                    'message' => 'Chyba: Model není inicializován'
                ));
                return;
            }
            
            if (!isset($this->config) || empty($this->config)) {
                wp_send_json_error(array(
                    'message' => 'Chyba: Konfigurace není inicializována'
                ));
                return;
            }
            
            if (method_exists($this, 'can') && !$this->can('view')) {
                wp_send_json_error(array(
                    'message' => 'Nemáte oprávnění zobrazit záznamy'
                ));
                return;
            }
            
            $current_id = intval($_POST['id'] ?? 0);
            $direction = sanitize_text_field($_POST['direction'] ?? 'next');
            
            if (!$current_id) {
                wp_send_json_error(array(
                    'message' => 'Chybí ID záznamu'
                ));
                return;
            }
            
            if (!in_array($direction, array('next', 'prev'))) {
                wp_send_json_error(array(
                    'message' => 'Neplatný směr navigace'
                ));
                return;
            }
            
            // Get context filters
            $customer_id = 0;
            $branch_id = 0;
            
            if (class_exists('SAW_Context')) {
                if (method_exists('SAW_Context', 'get_customer_id')) {
                    $customer_id = SAW_Context::get_customer_id();
                }
                if (method_exists('SAW_Context', 'get_branch_id')) {
                    $branch_id = SAW_Context::get_branch_id();
                }
            }
            
            if (!method_exists($this->model, 'get_by_id')) {
                wp_send_json_error(array(
                    'message' => 'Chyba: Model nemá metodu get_by_id'
                ));
                return;
            }
            
            $current_item = $this->model->get_by_id($current_id);
            if (!$current_item) {
                wp_send_json_error(array(
                    'message' => 'Záznam nenalezen'
                ));
                return;
            }
            
            // Build query - visitors need JOIN with visits for customer/branch filtering
            global $wpdb;
            
            $visitors_table = $this->model->table;
            $visits_table = $wpdb->prefix . 'saw_visits';
            
            if (empty($visitors_table) || empty($visits_table)) {
                wp_send_json_error(array(
                    'message' => 'Chyba: Nelze určit tabulky databáze'
                ));
                return;
            }
            
            $where = array('1=1');
            $where_values = array();
            
            if ($customer_id) {
                $where[] = "v.customer_id = %d";
                $where_values[] = $customer_id;
            }
            
            if ($branch_id) {
                $where[] = "v.branch_id = %d";
                $where_values[] = $branch_id;
            }
            
            $where_clause = implode(' AND ', $where);
            
            $query = "SELECT vis.id 
                      FROM {$visitors_table} vis
                      INNER JOIN {$visits_table} v ON vis.visit_id = v.id
                      WHERE {$where_clause}
                      ORDER BY vis.id ASC";
            
            if (!empty($where_values)) {
                $query = $wpdb->prepare($query, $where_values);
            }
            
            $ids = $wpdb->get_col($query);
            
            if ($wpdb->last_error) {
                wp_send_json_error(array(
                    'message' => 'Chyba při načítání záznamů'
                ));
                return;
            }
            
            $ids = array_map('intval', $ids);
            $current_id = intval($current_id);
            
            if (empty($ids)) {
                wp_send_json_error(array(
                    'message' => 'Žádné záznamy nenalezeny'
                ));
                return;
            }
            
            $current_index = array_search($current_id, $ids, true);
            
            if ($current_index === false) {
                wp_send_json_error(array(
                    'message' => 'Aktuální záznam není v seznamu'
                ));
                return;
            }
            
            // Get adjacent ID with circular navigation
            if ($direction === 'next') {
                $adjacent_index = ($current_index + 1) % count($ids);
            } else {
                $adjacent_index = ($current_index - 1 + count($ids)) % count($ids);
            }
            
            $adjacent_id = $ids[$adjacent_index];
            
            if (!$adjacent_id) {
                wp_send_json_error(array(
                    'message' => 'Nepodařilo se najít sousední záznam'
                ));
                return;
            }
            
            $route = $this->config['route'] ?? $this->entity;
            $detail_url = home_url('/admin/' . $route . '/' . $adjacent_id . '/');
            
            wp_send_json_success(array(
                'id' => $adjacent_id,
                'url' => $detail_url
            ));
            
        } catch (Throwable $e) {
            wp_send_json_error(array(
                'message' => 'Chyba při načítání sousedního záznamu'
            ));
        }
    }
}