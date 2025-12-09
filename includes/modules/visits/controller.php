<?php
/**
 * Visits Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     5.1.1 - FIXED: Risks editor routing via custom router
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
if (!trait_exists('SAW_AJAX_Handlers')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';

class SAW_Module_Visits_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visits_Model($this->config);
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 999);
    }
    
    public function index() {
        // ===== EDIT-RISKS DETECTION (v5.1.1) =====
        // URL: /admin/visits/{id}/edit-risks/
        // Router parses this as: mode=detail, id={id}, tab=edit-risks
        $context = $this->get_sidebar_context();
        if (isset($context['tab']) && $context['tab'] === 'edit-risks' && !empty($context['id'])) {
            $this->handle_edit_risks(intval($context['id']));
            return;
        }
        // ===== END EDIT-RISKS DETECTION =====
        
        // Standard permission check and list view
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    /**
     * Handle risks editing action
     * 
     * Loads and initializes the risks controller for editing visitor risk information.
     * 
     * @since 5.1.0
     * @since 5.1.1 FIXED: Now accepts visit_id as parameter from router context
     * @param int|null $visit_id Visit ID (from router context or fallback)
     */
    private function handle_edit_risks($visit_id = null) {
        // Get visit_id from parameter (primary) or context (fallback)
        if ($visit_id === null) {
            $context = $this->get_sidebar_context();
            $visit_id = !empty($context['id']) ? intval($context['id']) : 0;
        }
        
        if (!$visit_id) {
            wp_die('Neplatná návštěva.', 'Chyba', ['response' => 400]);
        }
        
        // Permission check - same roles as defined in detail-modal-template.php
        $can_edit_risks = false;
        if (current_user_can('manage_options')) {
            $can_edit_risks = true;
        } elseif (function_exists('saw_get_current_role')) {
            $user_role = saw_get_current_role();
            $can_edit_risks = in_array($user_role, ['super_admin', 'admin', 'super_manager', 'manager']);
        } elseif (current_user_can('edit_posts')) {
            $can_edit_risks = true;
        }
        
        if (!$can_edit_risks) {
            wp_die('Nemáte oprávnění upravovat informace o rizicích.', 'Přístup zamítnut', ['response' => 403]);
        }
        
        // Load risks controller
        $risks_controller_file = __DIR__ . '/risks/risks-controller.php';
        
        if (!file_exists($risks_controller_file)) {
            wp_die('Risks controller not found.', 'Error', ['response' => 500]);
        }
        
        require_once $risks_controller_file;
        
        $controller = new SAW_Visit_Risks_Controller($visit_id);
        $controller->init();
    }
    
    public function enqueue_assets() {
        wp_enqueue_style('dashicons');
        SAW_Asset_Loader::enqueue_module('visits');
        
        $visit_id = $this->detect_visit_id();
        
        $existing_hosts = array();
        if ($visit_id > 0) {
            global $wpdb;
            $existing_hosts = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                $visit_id
            ));
            $existing_hosts = array_map('intval', $existing_hosts);
        }
        
        $script_handle = 'saw-module-visits';
        
        if (wp_script_is($script_handle, 'registered') || wp_script_is($script_handle, 'enqueued')) {
            wp_localize_script($script_handle, 'sawVisitsData', array(
                'existing_hosts' => $existing_hosts,
                'visit_id' => $visit_id
            ));
        }
    }
    
    /**
     * Detect visit ID from multiple sources with fallbacks
     * 
     * @since 3.2.0
     * @return int Visit ID or 0 if not found
     */
    private function detect_visit_id() {
        $visit_id = 0;
        
        // Method 1: Try get_sidebar_context() (primary - set by router)
        $context = $this->get_sidebar_context();
        if (!empty($context['id']) && ($context['mode'] === 'edit' || $context['mode'] === 'detail')) {
            return intval($context['id']);
        }
        
        // Method 2: Parse URL from REQUEST_URI as fallback
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = sanitize_text_field($_SERVER['REQUEST_URI']);
            if (preg_match('#/admin/visits/(\d+)(?:/edit|/)?#', $request_uri, $matches)) {
                return intval($matches[1]);
            }
        }
        
        // Method 3: Fallback to $_GET['id']
        if (isset($_GET['id'])) {
            return intval($_GET['id']);
        }
        
        // Method 4: Try $_GET['saw_path'] as last resort
        if (isset($_GET['saw_path'])) {
            $path = sanitize_text_field($_GET['saw_path']);
            if (preg_match('/visits\/(\d+)/', $path, $matches)) {
                return intval($matches[1]);
            }
        }
        
        return 0;
    }

    protected function prepare_form_data($post) {
        $data = array();
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $sanitize = $field_config['sanitize'] ?? 'sanitize_text_field';
                $data[$field_name] = $sanitize($post[$field_name]);
            }
        }
        return $data;
    }
    
    protected function before_save($data) {
        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        return $data;
    }
    
    /**
     * Save visit schedules and hosts after main visit is saved
     * 
     * @since 7.0.0
     * @param int $visit_id Visit ID
     * @return void
     */
    protected function after_save($visit_id) {
        global $wpdb;
        
        if (!$visit_id || !isset($_POST)) {
            return;
        }
        
        // Get customer_id and branch_id for schedules
        $visit_data = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, branch_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);

        if (!$visit_data || empty($visit_data['customer_id']) || empty($visit_data['branch_id'])) {
            return;
        }

        $customer_id = intval($visit_data['customer_id']);
        $branch_id = intval($visit_data['branch_id']);
        
        // Save visit schedules (days and times)
        $schedule_table = $wpdb->prefix . 'saw_visit_schedules';
        
        // Delete existing schedules
        $wpdb->delete($schedule_table, array('visit_id' => $visit_id), array('%d'));
        
        // Insert new schedules
        $schedule_dates = isset($_POST['schedule_dates']) && is_array($_POST['schedule_dates']) ? $_POST['schedule_dates'] : array();
        $schedule_times_from = isset($_POST['schedule_times_from']) && is_array($_POST['schedule_times_from']) ? $_POST['schedule_times_from'] : array();
        $schedule_times_to = isset($_POST['schedule_times_to']) && is_array($_POST['schedule_times_to']) ? $_POST['schedule_times_to'] : array();
        $schedule_notes = isset($_POST['schedule_notes']) && is_array($_POST['schedule_notes']) ? $_POST['schedule_notes'] : array();
        
        if (!empty($schedule_dates)) {
            foreach ($schedule_dates as $index => $date) {
                $date = trim($date);
                if (empty($date)) {
                    continue;
                }
                
                $date = sanitize_text_field($date);
                $time_from = isset($schedule_times_from[$index]) ? sanitize_text_field(trim($schedule_times_from[$index])) : '';
                $time_to = isset($schedule_times_to[$index]) ? sanitize_text_field(trim($schedule_times_to[$index])) : '';
                $notes = isset($schedule_notes[$index]) ? sanitize_textarea_field(trim($schedule_notes[$index])) : '';
                
                $wpdb->insert(
                    $schedule_table,
                    array(
                        'visit_id' => $visit_id,
                        'customer_id' => $customer_id,
                        'branch_id' => $branch_id,
                        'date' => $date,
                        'time_from' => $time_from,
                        'time_to' => $time_to,
                        'notes' => $notes,
                        'sort_order' => intval($index)
                    ),
                    array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }
        
        // Calculate planned_date_from and planned_date_to
        $planned_date_from = null;
        $planned_date_to = null;
        
        // First, try to get from schedule_dates
        if (!empty($schedule_dates)) {
            $dates = array();
            foreach ($schedule_dates as $date) {
                $date = trim($date);
                if (!empty($date)) {
                    $dates[] = $date;
                }
            }
            
            if (!empty($dates)) {
                sort($dates);
                $planned_date_from = $dates[0];
                $planned_date_to = end($dates);
            }
        }
        
        // Fallback: If schedule_dates is empty, try to get directly from POST
        if (empty($planned_date_from) && isset($_POST['planned_date_from'])) {
            $planned_date_from = sanitize_text_field($_POST['planned_date_from']);
        }
        
        if (empty($planned_date_to) && isset($_POST['planned_date_to'])) {
            $planned_date_to = sanitize_text_field($_POST['planned_date_to']);
        }
        
        // Update main table if we have dates
        if (!empty($planned_date_from) || !empty($planned_date_to)) {
            $update_data = array();
            $update_format = array();
            
            if (!empty($planned_date_from)) {
                $update_data['planned_date_from'] = $planned_date_from;
                $update_format[] = '%s';
            }
            
            if (!empty($planned_date_to)) {
                $update_data['planned_date_to'] = $planned_date_to;
                $update_format[] = '%s';
            }
            
            if (!empty($update_data)) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_visits',
                    $update_data,
                    array('id' => $visit_id),
                    $update_format,
                    array('%d')
                );
            }
        }
        
        // Save visit hosts
        $hosts_table = $wpdb->prefix . 'saw_visit_hosts';
        
        // Delete existing hosts
        $wpdb->delete($hosts_table, array('visit_id' => $visit_id), array('%d'));
        
        // Insert new hosts
        $hosts = isset($_POST['hosts']) && is_array($_POST['hosts']) ? $_POST['hosts'] : array();
        
        if (!empty($hosts)) {
            foreach ($hosts as $user_id) {
                $user_id = intval($user_id);
                if ($user_id > 0) {
                    $wpdb->insert(
                        $hosts_table,
                        array(
                            'visit_id' => $visit_id,
                            'user_id' => $user_id
                        ),
                        array('%d', '%d')
                    );
                }
            }
        }
        
        // Update PIN expiration when dates change
        if (method_exists($this->model, 'update_pin_expiration')) {
            $this->model->update_pin_expiration($visit_id);
        }
    }

    /**
     * Format detail data for sidebar
     *
     * @param array $item Visit data
     * @return array Formatted visit data
     */
    protected function format_detail_data($item) {
        if (empty($item) || empty($item['id'])) {
            return $item;
        }
        
        global $wpdb;
        
        // Single query: Load visit, company, branch, visitor count
        $query = $wpdb->prepare(
            "SELECT 
                v.*,
                c.name as company_name,
                c.ico as company_ico,
                c.street as company_street,
                c.city as company_city,
                c.zip as company_zip,
                b.name as branch_name,
                COUNT(DISTINCT vis.id) as visitor_count,
                (
                    SELECT CONCAT(first_name, ' ', last_name)
                    FROM {$wpdb->prefix}saw_visitors
                    WHERE visit_id = v.id
                    ORDER BY id ASC
                    LIMIT 1
                ) as first_visitor_name
            FROM {$wpdb->prefix}saw_visits v
            LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
            LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
            LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
            WHERE v.id = %d
            GROUP BY v.id",
            $item['id']
        );
        
        $enriched = $wpdb->get_row($query, ARRAY_A);
        
        if (!$enriched) {
            return $item;
        }
        
        $item = array_merge($item, $enriched);
        
        // Load hosts (separate query due to many-to-many)
        $hosts_query = $wpdb->prepare(
            "SELECT 
                GROUP_CONCAT(
                    CONCAT_WS(':', u.id, u.first_name, u.last_name, u.email, u.role)
                    ORDER BY u.last_name, u.first_name
                    SEPARATOR '|'
                ) as hosts_data
             FROM {$wpdb->prefix}saw_visit_hosts vh
             INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
             WHERE vh.visit_id = %d",
            $item['id']
        );
        
        $hosts_result = $wpdb->get_row($hosts_query, ARRAY_A);
        
        // Parse hosts data
        $hosts = array();
        if (!empty($hosts_result['hosts_data'])) {
            foreach (explode('|', $hosts_result['hosts_data']) as $host_str) {
                $parts = explode(':', $host_str);
                if (count($parts) >= 5) {
                    $hosts[] = array(
                        'id' => $parts[0],
                        'first_name' => $parts[1],
                        'last_name' => $parts[2],
                        'email' => $parts[3],
                        'role' => $parts[4],
                    );
                }
            }
        }
        
        $item['hosts'] = $hosts;
        
        // Format company data object (for backward compatibility)
        if (!empty($item['company_id'])) {
            $item['company_data'] = array(
                'id' => $item['company_id'],
                'name' => $item['company_name'] ?? '',
                'ico' => $item['company_ico'] ?? '',
                'street' => $item['company_street'] ?? '',
                'city' => $item['company_city'] ?? '',
                'zip' => $item['company_zip'] ?? '',
            );
        }
        
        return $item;
    }
    
    /**
     * Get display name for detail header
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string Display name
     */
    public function get_display_name($item) {
        if (empty($item)) return '';
        
        $is_physical_person = empty($item['company_id']);
        
        if ($is_physical_person) {
            if (!empty($item['first_visitor_name'])) {
                return $item['first_visitor_name'];
            }
            return 'Fyzická osoba';
        } else {
            return $item['company_name'] ?? 'Firma #' . $item['company_id'];
        }
    }
    
    /**
     * Get detail header meta (badges)
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item = null) {
        if (empty($item)) {
            return '';
        }
        
        // Load translations
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'visits') 
            : [];
        
        $tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        $meta_parts = array();
        
        // Visitor count badge
        $visitor_count = intval($item['visitor_count'] ?? 0);
        
        if ($visitor_count === 1) {
            $person_word = $tr('person_singular', 'osoba');
        } elseif ($visitor_count >= 2 && $visitor_count <= 4) {
            $person_word = $tr('person_few', 'osoby');
        } else {
            $person_word = $tr('person_many', 'osob');
        }
        
        $meta_parts[] = '<span class="saw-badge-transparent">👥 ' . $visitor_count . ' ' . esc_html($person_word) . '</span>';
        
        // Visit type badge
        if (!empty($item['visit_type'])) {
            $type_labels = array(
                'planned' => $tr('type_planned', 'Plánovaná'),
                'walk_in' => $tr('type_walk_in', 'Walk-in'),
            );
            $type_label = $type_labels[$item['visit_type']] ?? $item['visit_type'];
            $type_class = $item['visit_type'] === 'walk_in' ? 'saw-badge-warning' : 'saw-badge-info';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($type_class) . '">' . esc_html($type_label) . '</span>';
        }
        
        // Status badge
        if (!empty($item['status'])) {
            $status_labels = array(
                'draft' => $tr('status_draft', 'Koncept'),
                'pending' => $tr('status_pending', 'Čekající'),
                'confirmed' => $tr('status_confirmed', 'Potvrzená'),
                'in_progress' => $tr('status_in_progress', 'Probíhající'),
                'completed' => $tr('status_completed', 'Dokončená'),
                'cancelled' => $tr('status_cancelled', 'Zrušená'),
            );
            $status_classes = array(
                'draft' => 'saw-badge-secondary',
                'pending' => 'saw-badge-warning',
                'confirmed' => 'saw-badge-info',
                'in_progress' => 'saw-badge-primary',
                'completed' => 'saw-badge-success',
                'cancelled' => 'saw-badge-danger',
            );
            $status_label = $status_labels[$item['status']] ?? $item['status'];
            $status_class = $status_classes[$item['status']] ?? 'saw-badge-secondary';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        }
        
        return implode('', $meta_parts);
    }
    
    // ============================================
    // AJAX HANDLERS
    // ============================================
    
    /**
     * AJAX: Change visit status
     * 
     * @since 3.4.0
     */
    public function ajax_change_visit_status() {
        saw_verify_ajax_unified();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění měnit stav návštěvy']);
        }
        
        $valid_statuses = ['draft', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Neplatný stav: ' . $new_status]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_visits';
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, started_at, completed_at FROM {$table} WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_send_json_error(['message' => 'Návštěva nenalezena']);
        }
        
        if ($visit['status'] === $new_status) {
            wp_send_json_error(['message' => 'Návštěva již má tento stav']);
        }
        
        $update_data = ['status' => $new_status];
        $update_format = ['%s'];
        
        $now = current_time('mysql');
        
        if ($new_status === 'in_progress' && empty($visit['started_at'])) {
            $update_data['started_at'] = $now;
            $update_format[] = '%s';
        }
        
        if ($new_status === 'completed') {
            $update_data['completed_at'] = $now;
            $update_format[] = '%s';
        }
        
        if ($visit['status'] === 'completed' && $new_status !== 'completed') {
            $update_data['completed_at'] = null;
            $update_format[] = null;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $visit_id],
            $update_format,
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba databáze: ' . $wpdb->last_error]);
        }
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::flush('visits');
        }
        
        $status_labels = [
            'draft' => 'Koncept',
            'pending' => 'Čeká',
            'confirmed' => 'Potvrzeno',
            'in_progress' => 'Probíhá',
            'completed' => 'Dokončeno',
            'cancelled' => 'Zrušeno',
        ];
        
        wp_send_json_success([
            'message' => 'Stav byl úspěšně změněn',
            'new_status' => $new_status,
            'new_status_label' => $status_labels[$new_status] ?? $new_status,
        ]);
    }
    
    /**
     * AJAX: Extend PIN expiry
     * 
     * @since 4.8.0
     */
    public function ajax_extend_pin() {
        saw_verify_ajax_unified();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $hours = intval($_POST['hours'] ?? 24);
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
        }
        
        global $wpdb;
        
        if ($hours === 999 && !empty($_POST['exact_expiry'])) {
            $new_expiry_input = sanitize_text_field($_POST['exact_expiry']);
            
            try {
                $tz_prague = new DateTimeZone('Europe/Prague');
                $dt = new DateTime($new_expiry_input, $tz_prague);
                $new_expiry = $dt->format('Y-m-d H:i:s');
                
                $now = new DateTime('now', $tz_prague);
                if ($dt <= $now) {
                    wp_send_json_error(['message' => 'Čas musí být v budoucnosti']);
                }
            } catch (Exception $e) {
                wp_send_json_error(['message' => 'Neplatný formát data']);
            }
        } else {
            if ($hours < 1 || $hours > 720) {
                wp_send_json_error(['message' => 'Neplatné parametry (1-720 hodin)']);
            }
            
            $current_expiry = $wpdb->get_var($wpdb->prepare(
                "SELECT pin_expires_at FROM {$wpdb->prefix}saw_visits WHERE id = %d",
                $visit_id
            ));
            
            if ($current_expiry && strtotime($current_expiry) > time()) {
                $new_expiry = date('Y-m-d H:i:s', strtotime($current_expiry . " +{$hours} hours"));
            } else {
                $new_expiry = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
            }
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            ['pin_expires_at' => $new_expiry],
            ['id' => $visit_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba databáze']);
        }
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::flush('visits');
        }
        
        wp_send_json_success([
            'new_expiry' => date('d.m.Y H:i', strtotime($new_expiry)),
            'new_expiry_raw' => $new_expiry,
            'hours' => $hours === 999 ? 'exact' : $hours,
            'message' => 'PIN úspěšně nastaven'
        ]);
    }

    /**
     * AJAX: Generate PIN code for visit
     * 
     * @since 4.8.0
     */
    public function ajax_generate_pin() {
        saw_verify_ajax_unified();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
        }
        
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, pin_code, visit_type, status FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_send_json_error(['message' => 'Návštěva nenalezena']);
        }
        
        if (!empty($visit['pin_code'])) {
            wp_send_json_error(['message' => 'PIN již existuje']);
        }
        
        if ($visit['visit_type'] !== 'planned') {
            wp_send_json_error(['message' => 'PIN lze vygenerovat pouze pro plánované návštěvy']);
        }
        
        $pin_code = $this->model->generate_pin($visit_id);
        
        if (!$pin_code) {
            wp_send_json_error(['message' => 'Nepodařilo se vygenerovat PIN. Zkuste to znovu.']);
        }
        
        $updated_visit = $wpdb->get_row($wpdb->prepare(
            "SELECT pin_code, pin_expires_at FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$updated_visit) {
            wp_send_json_error(['message' => 'Chyba při načítání vygenerovaných dat']);
        }
        
        $expiry_formatted = 'N/A';
        if (!empty($updated_visit['pin_expires_at'])) {
            $expiry_timestamp = strtotime($updated_visit['pin_expires_at']);
            if ($expiry_timestamp !== false) {
                $expiry_formatted = date('d.m.Y H:i', $expiry_timestamp);
            }
        }
        
        wp_send_json_success([
            'pin_code' => $pin_code,
            'pin_expires_at' => $expiry_formatted,
            'pin_expires_at_raw' => $updated_visit['pin_expires_at'],
            'message' => 'PIN byl úspěšně vygenerován'
        ]);
    }
    
    /**
     * AJAX: Send invitation email
     * 
     * @since 1.0.0
     */
    public function ajax_send_invitation() {
        saw_verify_ajax_unified();
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
        }
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        $visit = $this->model->get_by_id($visit_id);
        
        if (!$visit) {
            wp_send_json_error(['message' => 'Návštěva nenalezena']);
        }
        
        if (empty($visit['invitation_email'])) {
            wp_send_json_error(['message' => 'Email pro pozvánku není vyplněn']);
        }
        
        if (empty($visit['pin_code'])) {
            $pin = $this->model->generate_pin($visit_id);
            if (!$pin) {
                wp_send_json_error(['message' => 'Nepodařilo se vygenerovat PIN']);
            }
            $visit = $this->model->get_by_id($visit_id);
        }
        
        $token = $this->model->ensure_unique_token($visit['customer_id']);
        
        if (!empty($visit['planned_date_to'])) {
            $expires = date('Y-m-d H:i:s', strtotime($visit['planned_date_to'] . ' +1 day'));
        } else {
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            [
                'invitation_token' => $token,
                'invitation_token_expires_at' => $expires,
                'invitation_sent_at' => current_time('mysql'),
            ],
            ['id' => $visit_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba při ukládání tokenu']);
        }
        
        $branch_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $visit['branch_id']
        ));
        
        $date_from = !empty($visit['planned_date_from']) ? date('d.m.Y', strtotime($visit['planned_date_from'])) : 'N/A';
        $date_to = !empty($visit['planned_date_to']) ? date('d.m.Y', strtotime($visit['planned_date_to'])) : 'N/A';
        
        $link = home_url('/visitor-invitation/' . $token . '/');
        
        $subject = 'Pozvánka k návštěvě - ' . get_bloginfo('name');
        
        $message = "
Dobrý den,

Byl/a jste pozván/a k návštěvě na pobočce {$branch_name}.

Termín: {$date_from} - {$date_to}

🔢 VÁŠ PIN KÓD PRO CHECK-IN: {$visit['pin_code']}

Prosím vyplňte informace o návštěvě na tomto odkazu:

{$link}

Odkaz je platný 30 dní.

Po vyplnění vám PIN připomeneme emailem.

DŮLEŽITÉ: Tento PIN kód si poznamenejte, budete ho potřebovat 
při příchodu na recepci.

S pozdravem,

" . get_bloginfo('name');
        
        $sent = wp_mail($visit['invitation_email'], $subject, $message);
        
        if (!$sent) {
            wp_send_json_error(['message' => 'Chyba při odesílání emailu']);
        }
        
        wp_send_json_success([
            'message' => 'Pozvánka byla úspěšně odeslána',
            'link' => $link,
            'sent_at' => current_time('d.m.Y H:i')
        ]);
    }
    
    /**
     * AJAX: Get hosts by branch
     * 
     * @since 1.0.0
     */
    public function ajax_get_hosts_by_branch() {
        saw_verify_ajax_unified();
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        $customer_id = 0;
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Nelze určit zákazníka'));
            return;
        }
        
        global $wpdb;
        
        $branch_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches 
             WHERE id = %d AND customer_id = %d AND is_active = 1",
            $branch_id,
            $customer_id
        ));
        
        if (!$branch_exists) {
            wp_send_json_error(array('message' => 'Pobočka nenalezena nebo nemáte oprávnění'));
            return;
        }
        
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, role, position 
             FROM {$wpdb->prefix}saw_users 
             WHERE customer_id = %d 
               AND branch_id = %d 
               AND role IN ('admin', 'super_manager', 'manager')
               AND is_active = 1 
             ORDER BY last_name, first_name",
            $customer_id,
            $branch_id
        ), ARRAY_A);
        
        if (is_array($hosts)) {
            foreach ($hosts as &$host) {
                $host['position'] = isset($host['position']) ? $host['position'] : '';
            }
            unset($host);
        }
        
        wp_send_json_success(array('hosts' => $hosts ? $hosts : array()));
    }
    
    /**
     * Get table columns configuration for infinite scroll
     * 
     * @since 7.0.0
     * @return array Columns configuration
     */
    protected function get_table_columns() {
        return array(
            'company_person' => array(
                'label' => 'Návštěvník',
                'type' => 'custom',
                'sortable' => false,
                'class' => 'saw-table-cell-bold',
                'callback' => function($value, $item) {
                    if (!empty($item['company_id'])) {
                        echo '<div style="display: flex; align-items: center; gap: 8px;">';
                        echo '<strong>' . esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']) . '</strong>';
                        echo '<span class="saw-badge saw-badge-info" style="font-size: 11px;">🏢 Firma</span>';
                        echo '</div>';
                    } else {
                        echo '<div style="display: flex; align-items: center; gap: 8px;">';
                        if (!empty($item['first_visitor_name'])) {
                            echo '<strong style="color: #6366f1;">' . esc_html($item['first_visitor_name']) . '</strong>';
                        } else {
                            echo '<strong style="color: #6366f1;">Fyzická osoba</strong>';
                        }
                        echo '<span class="saw-badge" style="background: #6366f1; color: white; font-size: 11px;">👤 Fyzická</span>';
                        echo '</div>';
                    }
                },
            ),
            'branch_name' => array(
                'label' => 'Pobočka',
                'type' => 'text',
                'sortable' => false,
            ),
            'visit_type' => array(
                'label' => 'Typ',
                'type' => 'badge',
                'width' => '120px',
                'map' => array(
                    'planned' => 'info',
                    'walk_in' => 'warning',
                ),
                'labels' => array(
                    'planned' => 'Plánovaná',
                    'walk_in' => 'Walk-in',
                ),
            ),
            'visitor_count' => array(
                'label' => 'Počet',
                'type' => 'custom',
                'width' => '100px',
                'align' => 'center',
                'callback' => function($value, $item) {
                    $count = intval($item['visitor_count'] ?? 0);
                    if ($count === 0) {
                        echo '<span style="color: #999;">—</span>';
                    } else {
                        echo '<strong style="color: #0066cc;">👥 ' . $count . '</strong>';
                    }
                },
            ),
            'status' => array(
                'label' => 'Stav',
                'type' => 'badge',
                'sortable' => true,
                'width' => '140px',
                'map' => array(
                    'draft' => 'secondary',
                    'pending' => 'warning',
                    'confirmed' => 'info',
                    'in_progress' => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                ),
                'labels' => array(
                    'draft' => 'Koncept',
                    'pending' => 'Čekající',
                    'confirmed' => 'Potvrzená',
                    'in_progress' => 'Probíhající',
                    'completed' => 'Dokončená',
                    'cancelled' => 'Zrušená',
                ),
            ),
            'created_at' => array(
                'label' => 'Vytvořeno',
                'type' => 'date',
                'sortable' => true,
                'width' => '120px',
                'format' => 'd.m.Y',
            ),
        );
    }
}