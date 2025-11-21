<?php
/**
 * Visits Module Controller
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.1.0 - FIXED: Simplified to match Departments pattern
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
        
        // Register custom AJAX
        add_action('wp_ajax_saw_get_hosts_by_branch', array($this, 'ajax_get_hosts_by_branch'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    public function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('visits');
        
        // Pass existing hosts to JS if editing
        $existing_hosts = array();
        if (isset($_GET['id'])) {
            global $wpdb;
            $existing_hosts = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                intval($_GET['id'])
            ));
            $existing_hosts = array_map('intval', $existing_hosts);
        }
        
        wp_localize_script('saw-visits', 'sawVisits', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'existing_hosts' => $existing_hosts
        ));
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

    protected function format_detail_data($item) {
        if (empty($item)) return $item;
        
        global $wpdb;
        
        // Load company data and name
        if (!empty($item['company_id'])) {
            $company = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_companies WHERE id = %d", $item['company_id']), ARRAY_A);
            $item['company_data'] = $company;
            if ($company) {
                $item['company_name'] = $company['name'];
            }
        }
        
        // Load branch name
        if (!empty($item['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d", $item['branch_id']), ARRAY_A);
            if ($branch) $item['branch_name'] = $branch['name'];
        }
        
        // Load visitor count
        if (!empty($item['id'])) {
            $item['visitor_count'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d",
                $item['id']
            ));
        }
        
        // Load first visitor name for physical persons
        if (empty($item['company_id']) && !empty($item['id'])) {
            $first_visitor = $wpdb->get_row($wpdb->prepare(
                "SELECT CONCAT(first_name, ' ', last_name) as name FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d ORDER BY id ASC LIMIT 1",
                $item['id']
            ), ARRAY_A);
            if ($first_visitor && !empty($first_visitor['name'])) {
                $item['first_visitor_name'] = $first_visitor['name'];
            }
        }
        
        // Load hosts
        if (!empty($item['id'])) {
            $hosts = $wpdb->get_results($wpdb->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.role 
                 FROM {$wpdb->prefix}saw_visit_hosts vh
                 INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
                 WHERE vh.visit_id = %d
                 ORDER BY u.last_name, u.first_name",
                $item['id']
            ), ARRAY_A);
            $item['hosts'] = $hosts;
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
     * Returns HTML for badges displayed in universal detail header.
     * Shows: visitor count, visit type, status
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // Visitor count badge - always show
        $visitor_count = intval($item['visitor_count'] ?? 0);
        $meta_parts[] = '<span class="saw-badge-transparent">👥 ' . $visitor_count . ' ' . ($visitor_count === 1 ? 'osoba' : ($visitor_count < 5 ? 'osoby' : 'osob')) . '</span>';
        
        // Visit type badge
        if (!empty($item['visit_type'])) {
            $type_labels = array(
                'planned' => 'Plánovaná',
                'walk_in' => 'Walk-in',
            );
            $type_label = $type_labels[$item['visit_type']] ?? $item['visit_type'];
            $type_class = $item['visit_type'] === 'walk_in' ? 'saw-badge-warning' : 'saw-badge-info';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($type_class) . '">' . esc_html($type_label) . '</span>';
        }
        
        // Status badge
        if (!empty($item['status'])) {
            $status_labels = array(
                'draft' => 'Koncept',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzená',
                'in_progress' => 'Probíhající',
                'completed' => 'Dokončená',
                'cancelled' => 'Zrušená',
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
    
    public function ajax_get_hosts_by_branch() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        global $wpdb;
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, role FROM {$wpdb->prefix}saw_users WHERE branch_id = %d AND is_active = 1 ORDER BY last_name, first_name",
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(array('hosts' => $hosts));
    }
    
    /**
     * Get table columns configuration for infinite scroll
     * 
     * @since 7.0.0
     * @return array Columns configuration
     */
    protected function get_table_columns() {
        // Return columns as defined in list-template.php
        // ID column is removed, company_person, branch_name, visit_type, visitor_count, status, created_at
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
