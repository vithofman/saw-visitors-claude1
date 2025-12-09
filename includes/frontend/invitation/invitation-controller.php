<?php
/**
 * Invitation Controller
 * 
 * Handles the complete invitation flow for visitor registration.
 * 
 * @package SAW_Visitors
 * @version 3.9.9 - Added certificates support, visitor editing and deletion
 */

if (!defined('ABSPATH')) exit;

class SAW_Invitation_Controller {
    
    private $session;
    private $current_step;
    private $token;
    private $visit_id;
    private $customer_id;
    private $branch_id;
    private $company_id;
    private $languages = [];
    
    public function __construct() {
        if (!class_exists('SAW_Session_Manager')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        if (!class_exists('SAW_Cache')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-cache.php';
        }
        
        // Load OOPP Public helper
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/class-saw-oopp-public.php';
        
        $this->session = SAW_Session_Manager::instance();
        $this->init_invitation_session();
        $this->load_languages();
        $this->current_step = $this->get_current_step();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Register AJAX handlers for invitation media library
     * 
     * @since 5.4.4
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_nopriv_query-attachments', array(__CLASS__, 'handle_media_query'));
        add_action('wp_ajax_nopriv_upload-attachment', array(__CLASS__, 'handle_media_upload'));
    }
    
    /**
     * Initialize invitation session from token
     */
    private function init_invitation_session() {
        $this->token = get_query_var('saw_invitation_token');
        
        if (empty($this->token)) {
            $flow = $this->session->get('invitation_flow');
            $this->token = $flow['token'] ?? '';
        }
        
        if (empty($this->token)) {
            wp_die('Missing invitation token', 'Error', ['response' => 400]);
        }
        
        $flow = $this->session->get('invitation_flow');
        
        if (empty($flow) || ($flow['token'] ?? '') !== $this->token) {
            $this->reload_visit_from_token();
            
            $flow = [
                'token' => $this->token,
                'visit_id' => $this->visit_id,
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'company_id' => $this->company_id,
                'step' => 'language',
                'history' => [],
                'completed_steps' => [],
                'language_locked' => false,
                'created_at' => time()
            ];
            
            $this->session->set('invitation_flow', $flow);
        } else {
            $this->visit_id = $flow['visit_id'] ?? null;
            $this->customer_id = $flow['customer_id'] ?? null;
            $this->branch_id = $flow['branch_id'] ?? null;
            $this->company_id = $flow['company_id'] ?? null;
        }
        
        if (!$this->visit_id || !$this->customer_id || !$this->branch_id) {
            wp_die('Invalid invitation session', 'Error', ['response' => 400]);
        }
    }
    
    /**
     * Reload visit data from token
     */
    private function reload_visit_from_token() {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits 
             WHERE invitation_token = %s 
             AND invitation_token_expires_at > NOW()
             AND status IN ('pending', 'draft', 'confirmed', 'in_progress')",
            $this->token
        ), ARRAY_A);

        if (!$visit) {
            wp_die('Visit not found or expired', 'Error', ['response' => 404]);
        }
        
        $this->visit_id = $visit['id'];
        $this->customer_id = $visit['customer_id'];
        $this->branch_id = $visit['branch_id'];
        $this->company_id = $visit['company_id'] ?? null;
    }
    
    /**
     * Load available languages for customer/branch
     */
    private function load_languages() {
        global $wpdb;
        
        if (!$this->customer_id || !$this->branch_id) {
            $this->languages = ['cs' => 'Čeština', 'en' => 'English'];
            return;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT tl.language_code, tl.language_name
             FROM {$wpdb->prefix}saw_training_languages tl
             INNER JOIN {$wpdb->prefix}saw_training_language_branches tlb 
                ON tl.id = tlb.language_id
             WHERE tl.customer_id = %d AND tlb.branch_id = %d AND tlb.is_active = 1
             ORDER BY tlb.display_order ASC, tl.language_name ASC",
            $this->customer_id,
            $this->branch_id
        ));
        
        $this->languages = [];
        foreach ($results as $row) {
            $this->languages[$row->language_code] = $row->language_name;
        }
        
        if (empty($this->languages)) {
            $this->languages = ['cs' => 'Čeština', 'en' => 'English'];
        }
    }
    
    /**
     * Get current step from session or URL
     */
    private function get_current_step() {
        $flow = $this->session->get('invitation_flow', []);
        
        if (empty($flow['language'])) {
            return 'language';
        }
        
        $step = $_GET['step'] ?? '';
        if (!empty($step)) {
            $steps_requiring_language = ['risks', 'visitors', 'training-video', 'training-map', 'training-risks', 'training-department', 'training-oopp', 'training-additional', 'success'];
            if (in_array($step, $steps_requiring_language) && empty($flow['language'])) {
                return 'language';
            }
            return $step;
        }
        
        return $flow['step'] ?? 'language';
    }
    
    /**
     * Enqueue CSS and JS assets
     */
    public function enqueue_assets() {
        $css_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/css/';
        $js_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/';
        
        $base_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/base.css';
        if (file_exists($base_css)) {
            wp_enqueue_style('saw-terminal-base', $css_dir . 'terminal/base.css', [], '4.0.0');
        }
        
        $components_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/components.css';
        if (file_exists($components_css)) {
            wp_enqueue_style('saw-terminal-components', $css_dir . 'terminal/components.css', ['saw-terminal-base'], '4.0.0');
        }
        
        $layout_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/layout.css';
        if (file_exists($layout_css)) {
            wp_enqueue_style('saw-terminal-layout', $css_dir . 'terminal/layout.css', ['saw-terminal-base'], '4.0.0');
        }
        
        $pages_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/pages.css';
        if (file_exists($pages_css)) {
            wp_enqueue_style(
                'saw-terminal-pages',
                $css_dir . 'terminal/pages.css',
                ['saw-terminal-base', 'saw-terminal-layout', 'saw-terminal-components'],
                '4.0.0'
            );
        }
        
        wp_enqueue_script('jquery');
        
        $video_player_js = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/js/video-player.js';
        if (file_exists($video_player_js)) {
            wp_enqueue_script(
                'saw-video-player',
                $js_dir . 'video-player.js',
                ['jquery'],
                '3.0.0',
                true
            );
        }
        
        $autosave_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/invitation/invitation-autosave.js';
        if (file_exists($autosave_js)) {
            wp_enqueue_script(
                'saw-invitation-autosave',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/invitation/invitation-autosave.js',
                ['jquery'],
                filemtime($autosave_js),
                true
            );
        }
        
        wp_localize_script('jquery', 'sawInvitation', [
            'token' => $this->token,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'clearNonce' => wp_create_nonce('saw_clear_invitation_session'),
            'autosaveNonce' => wp_create_nonce('saw_invitation_autosave'),
            'currentStep' => $this->current_step,
        ]);

// Hide toast notifications visually (v3.9.11)
        wp_add_inline_style('saw-terminal-base', '
            .saw-toast,
            .saw-toast-container,
            #autosave-indicator,
            .saw-save-indicator,
            .saw-success-notification {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }
        ');
    }
    
    /**
     * Main render method
     */
    public function render() {
        $flow = $this->session->get('invitation_flow', []);
        $this->current_step = $this->get_current_step();
        
        if ($this->current_step !== 'language' && empty($flow['language'])) {
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=language'));
            exit;
        }
        
        if ($this->current_step === 'risks') {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.php';
            saw_richtext_editor_init();
            saw_richtext_editor_enqueue_assets();
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_post_actions();
        }
        
        $this->render_header();
        
        switch ($this->current_step) {
            case 'language':
                $this->render_language_selection();
                break;
            case 'risks':
                $this->render_risks_upload();
                break;
            case 'visitors':
                $this->render_visitors_registration();
                break;
            case 'training-video':
                $this->render_training_video();
                break;
            case 'training-map':
                $this->render_training_map();
                break;
            case 'training-risks':
                $this->render_training_risks();
                break;
            case 'training-department':
                $this->render_training_department();
                break;
            case 'training-oopp':
                $this->render_training_oopp();
                break;
            case 'training-additional':
                $this->render_training_additional();
                break;
            case 'summary':
                $this->render_summary();
                break;
            case 'success':
                $this->render_pin_success();
                break;
            default:
                $this->render_language_selection();
        }
        
        $this->render_footer();
    }
    
    /**
     * Handle POST actions
     */
    private function handle_post_actions() {
        $action = $_POST['invitation_action'] ?? '';
        
        if (empty($action)) {
            return;
        }
        
        switch ($action) {
            case 'go_back':
                $this->handle_go_back();
                break;
            case 'select_language':
                $this->handle_language_selection();
                break;
            case 'save_risks':
                $this->handle_save_risks();
                break;
            case 'save_visitors':
                $this->handle_save_visitors();
                break;
            case 'skip_training':
                $this->handle_skip_training();
                break;
            case 'complete_training':
                $this->handle_complete_training();
                break;
            case 'confirm_summary':
                $this->handle_confirm_summary();
                break;
        }
    }
    
    /**
     * Handle back navigation
     */
    private function handle_go_back() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $flow = $this->session->get('invitation_flow');
        $history = $flow['history'] ?? [];
        
        if (count($history) < 2) {
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/'));
            exit;
        }
        
        array_pop($history);
        $previous_step = end($history);
        
        $flow['step'] = $previous_step;
        $flow['history'] = $history;
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $previous_step));
        exit;
    }
    
    /**
     * Check if user can navigate back
     */
    private function can_go_back() {
        $flow = $this->session->get('invitation_flow');
        $history = $flow['history'] ?? [];
        
        return count($history) > 1 && $this->current_step !== 'language';
    }
    
    /**
     * Handle language selection
     */
    private function handle_language_selection() {
        if (!isset($_POST['invitation_nonce']) || !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $language = sanitize_text_field($_POST['language'] ?? '');
        if (!in_array($language, array_keys($this->languages))) {
            $this->set_error('Invalid language selected');
            return;
        }
        
        $flow = $this->session->get('invitation_flow');
        
        if (!in_array('language', $flow['history'] ?? [])) {
            $flow['history'][] = 'language';
        }
        
        if (!in_array('language', $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = 'language';
        }
        
        $flow['language_locked'] = true;
        $flow['language'] = $language;
        $flow['step'] = 'risks';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=risks'));
        exit;
    }
    
    /**
     * Handle save risks
     */
    private function handle_save_risks() {
        if (!isset($_POST['invitation_nonce']) || !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        global $wpdb;
        
        $action_type = sanitize_text_field($_POST['action'] ?? 'save');
        
        if ($action_type === 'skip') {
            $flow = $this->session->get('invitation_flow');
            
            if (!in_array('risks', $flow['history'] ?? [])) {
                $flow['history'][] = 'risks';
            }
            
            $flow['step'] = 'visitors';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
            exit;
        }
        
        $risks_text = wp_kses_post($_POST['risks_text'] ?? '');
        $delete_files = $_POST['delete_files'] ?? [];
        
        if (!empty($risks_text)) {
            $existing_text_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_visit_invitation_materials 
                 WHERE visit_id = %d AND material_type = 'text'
                 ORDER BY uploaded_at DESC LIMIT 1",
                $this->visit_id
            ));
            
            if ($existing_text_id) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'text_content' => $risks_text,
                        'uploaded_at' => current_time('mysql')
                    ],
                    ['id' => $existing_text_id],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'visit_id' => $this->visit_id,
                        'customer_id' => $this->customer_id,
                        'branch_id' => $this->branch_id,
                        'material_type' => 'text',
                        'text_content' => $risks_text,
                        'uploaded_at' => current_time('mysql')
                    ]
                );
            }
        }
        
        if (!empty($delete_files)) {
            foreach ($delete_files as $file_id) {
                $file = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials WHERE id = %d AND visit_id = %d",
                    $file_id,
                    $this->visit_id
                ));
                if ($file && !empty($file->file_path)) {
                    $upload_dir = wp_upload_dir();
                    $file_path = $upload_dir['basedir'] . $file->file_path;
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
                $wpdb->delete(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    ['id' => $file_id, 'visit_id' => $this->visit_id]
                );
            }
        }
        
        if (!empty($_FILES['risks_documents']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            
            foreach ($_FILES['risks_documents']['name'] as $key => $name) {
                if (empty($name)) continue;
                
                $file = [
                    'name' => $_FILES['risks_documents']['name'][$key],
                    'type' => $_FILES['risks_documents']['type'][$key],
                    'tmp_name' => $_FILES['risks_documents']['tmp_name'][$key],
                    'error' => $_FILES['risks_documents']['error'][$key],
                    'size' => $_FILES['risks_documents']['size'][$key],
                ];
                
                $upload_overrides = ['test_form' => false];
                $movefile = wp_handle_upload($file, $upload_overrides);
                
                if ($movefile && !isset($movefile['error'])) {
                    $upload_dir = wp_upload_dir();
                    $relative_path = str_replace($upload_dir['basedir'], '', $movefile['file']);
                    
                    $wpdb->insert($wpdb->prefix . 'saw_visit_invitation_materials', [
                        'visit_id' => $this->visit_id,
                        'customer_id' => $this->customer_id,
                        'branch_id' => $this->branch_id,
                        'material_type' => 'document',
                        'file_name' => sanitize_file_name($file['name']),
                        'file_path' => $relative_path,
                        'file_size' => $file['size'],
                        'mime_type' => $movefile['type'],
                        'uploaded_at' => current_time('mysql'),
                    ]);
                }
            }
        }
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::delete('invitation_visit_' . $this->visit_id, 'invitations');
            SAW_Cache::flush('invitations');
        }
        
        $flow = $this->session->get('invitation_flow');
        
        if (!in_array('risks', $flow['history'] ?? [])) {
            $flow['history'][] = 'risks';
        }
        
        if (!in_array('risks', $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = 'risks';
        }
        
        $flow['step'] = 'visitors';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
        exit;
    }
    
    /**
     * Handle save visitors
     * 
     * Supports:
     * - Creating new visitors with certificates
     * - Updating existing visitors (name, position, email, phone, training_skip)
     * - Adding/updating/deleting certificates for existing visitors
     * - Deleting visitors (only if not checked-in)
     * 
     * @since 3.9.9 Added certificates and visitor editing/deletion
     */
    private function handle_save_visitors() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        global $wpdb;
        
        // Get POST data
        $deleted_visitor_ids = isset($_POST['deleted_visitor_ids']) ? array_map('intval', (array) $_POST['deleted_visitor_ids']) : [];
        $existing_visitors = $_POST['existing_visitors'] ?? [];
        $new_visitors = $_POST['new_visitors'] ?? [];
        
        // ===== 1. DELETE VISITORS =====
        // Only delete if visitor is not checked-in (no daily_log with checked_in_at)
        if (!empty($deleted_visitor_ids)) {
            foreach ($deleted_visitor_ids as $visitor_id) {
                if ($visitor_id <= 0) continue;
                
                // Verify visitor belongs to this visit
                $visitor = $wpdb->get_row($wpdb->prepare(
                    "SELECT v.id, v.visit_id 
                     FROM {$wpdb->prefix}saw_visitors v
                     WHERE v.id = %d AND v.visit_id = %d",
                    $visitor_id,
                    $this->visit_id
                ));
                
                if (!$visitor) continue;
                
                // Check if visitor has any check-in record
                $has_checkin = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_daily_logs 
                     WHERE visitor_id = %d AND checked_in_at IS NOT NULL",
                    $visitor_id
                ));
                
                if ($has_checkin > 0) {
                    // Cannot delete - visitor already checked in
                    continue;
                }
                
                // Delete certificates first (foreign key)
                $wpdb->delete(
                    $wpdb->prefix . 'saw_visitor_certificates',
                    ['visitor_id' => $visitor_id],
                    ['%d']
                );
                
                // Delete visitor
                $wpdb->delete(
                    $wpdb->prefix . 'saw_visitors',
                    ['id' => $visitor_id, 'visit_id' => $this->visit_id],
                    ['%d', '%d']
                );
            }
        }
        
        // ===== 2. UPDATE EXISTING VISITORS =====
        $visitor_ids = [];
        
        if (!empty($existing_visitors) && is_array($existing_visitors)) {
            foreach ($existing_visitors as $visitor_id => $visitor_data) {
                $visitor_id = intval($visitor_id);
                if ($visitor_id <= 0) continue;
                
                // Verify visitor belongs to this visit
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}saw_visitors 
                     WHERE id = %d AND visit_id = %d",
                    $visitor_id,
                    $this->visit_id
                ));
                
                if (!$existing) continue;
                
                // Check if visitor is selected (checkbox checked)
                $is_selected = isset($visitor_data['selected']) && $visitor_data['selected'] === '1';
                
                if (!$is_selected) {
                    // Visitor unchecked - set to planned (not confirmed)
                    $wpdb->update(
                        $wpdb->prefix . 'saw_visitors',
                        ['participation_status' => 'planned'],
                        ['id' => $visitor_id],
                        ['%s'],
                        ['%d']
                    );
                    continue;
                }
                
                // Visitor is selected - update data
                $visitor_ids[] = $visitor_id;
                
                $training_skipped = isset($visitor_data['training_skip']) && $visitor_data['training_skip'] === '1';
                
                // Update visitor data
                $update_data = [
                    'first_name' => sanitize_text_field($visitor_data['first_name'] ?? ''),
                    'last_name' => sanitize_text_field($visitor_data['last_name'] ?? ''),
                    'position' => sanitize_text_field($visitor_data['position'] ?? ''),
                    'email' => sanitize_email($visitor_data['email'] ?? ''),
                    'phone' => sanitize_text_field($visitor_data['phone'] ?? ''),
                    'participation_status' => 'confirmed',
                    'training_skipped' => $training_skipped ? 1 : 0,
                    'training_status' => $training_skipped ? 'skipped' : 'pending',
                ];
                
                // Only update if we have valid name
                if (!empty($update_data['first_name']) && !empty($update_data['last_name'])) {
                    $wpdb->update(
                        $wpdb->prefix . 'saw_visitors',
                        $update_data,
                        ['id' => $visitor_id],
                        ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
                        ['%d']
                    );
                }
                
                // Handle certificates for existing visitor
                $this->save_visitor_certificates($visitor_id, $visitor_data['certificates'] ?? []);
            }
        }
        
        // ===== 3. ADD NEW VISITORS =====
        if (!empty($new_visitors) && is_array($new_visitors)) {
            foreach ($new_visitors as $visitor_data) {
                if (empty($visitor_data['first_name']) || empty($visitor_data['last_name'])) {
                    continue;
                }
                
                $training_skipped = isset($visitor_data['training_skip']) && $visitor_data['training_skip'] === '1';
                
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visitors',
                    [
                        'visit_id' => $this->visit_id,
                        'customer_id' => $this->customer_id,
                        'branch_id' => $this->branch_id,
                        'first_name' => sanitize_text_field($visitor_data['first_name']),
                        'last_name' => sanitize_text_field($visitor_data['last_name']),
                        'position' => sanitize_text_field($visitor_data['position'] ?? ''),
                        'email' => sanitize_email($visitor_data['email'] ?? ''),
                        'phone' => sanitize_text_field($visitor_data['phone'] ?? ''),
                        'participation_status' => 'confirmed',
                        'current_status' => 'confirmed',
                        'training_skipped' => $training_skipped ? 1 : 0,
                        'training_status' => $training_skipped ? 'skipped' : 'pending',
                    ]
                );
                
                if ($wpdb->insert_id) {
                    $new_visitor_id = $wpdb->insert_id;
                    $visitor_ids[] = $new_visitor_id;
                    
                    // Save certificates for new visitor
                    $this->save_visitor_certificates($new_visitor_id, $visitor_data['certificates'] ?? []);
                }
            }
        }
        
        // ===== 4. VALIDATE - must have at least one visitor =====
        if (empty($visitor_ids)) {
            // Check if there are any confirmed visitors
            $confirmed_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors 
                 WHERE visit_id = %d AND participation_status = 'confirmed'",
                $this->visit_id
            ));
            
            if ($confirmed_count == 0) {
                $flow = $this->session->get('invitation_flow');
                $flow['error'] = 'Please add at least one visitor';
                $this->session->set('invitation_flow', $flow);
                
                wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
                exit;
            }
        }
        
        // Update visit status
        $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            [
                'status' => 'pending',
                'invitation_confirmed_at' => current_time('mysql')
            ],
            ['id' => $this->visit_id]
        );
        
        // Session tracking
        $flow = $this->session->get('invitation_flow');
        
        if (!in_array('visitors', $flow['history'] ?? [])) {
            $flow['history'][] = 'visitors';
        }
        
        if (!in_array('visitors', $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = 'visitors';
        }
        
        // Determine next step
        $has_training = $this->has_training_content();
        $needs_training = false;
        
        if ($has_training) {
            $confirmed_visitors = $wpdb->get_results($wpdb->prepare(
                "SELECT id, training_skipped FROM {$wpdb->prefix}saw_visitors 
                 WHERE visit_id = %d AND participation_status = 'confirmed'",
                $this->visit_id
            ));
            
            foreach ($confirmed_visitors as $v) {
                if (!$v->training_skipped) {
                    $needs_training = true;
                    break;
                }
            }
        }
        
        // Invalidate cache
        if (class_exists('SAW_Cache')) {
            SAW_Cache::delete('invitation_visit_' . $this->visit_id, 'invitations');
        }
        
        // Redirect
        if ($needs_training) {
            $available_steps = $this->get_available_training_steps();
            
            if (!empty($available_steps)) {
                $first_step = $available_steps[0]['step'];
                $flow['step'] = $first_step;
                $flow['available_training_steps'] = $available_steps;
                $this->session->set('invitation_flow', $flow);
                wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $first_step));
            } else {
                $flow['step'] = 'summary';
                $this->session->set('invitation_flow', $flow);
                wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=summary'));
            }
        } else {
            $flow['step'] = 'summary';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=summary'));
        }
        
        exit;
    }
    
    /**
     * Save certificates for a visitor
     * 
     * @since 3.9.9
     * @param int $visitor_id Visitor ID
     * @param array $certificates Array of certificate data
     */
    private function save_visitor_certificates($visitor_id, $certificates) {
        global $wpdb;
        
        if (empty($certificates) || !is_array($certificates)) {
            // Delete all existing certificates if none provided
            $wpdb->delete(
                $wpdb->prefix . 'saw_visitor_certificates',
                ['visitor_id' => $visitor_id],
                ['%d']
            );
            return;
        }
        
        // Get existing certificate IDs
        $existing_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visitor_certificates WHERE visitor_id = %d",
            $visitor_id
        ));
        
        $processed_ids = [];
        
        foreach ($certificates as $cert) {
            // Skip empty certificates
            if (empty($cert['name'])) {
                continue;
            }
            
            $cert_id = isset($cert['id']) ? intval($cert['id']) : 0;
            
            $cert_data = [
                'visitor_id' => $visitor_id,
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'certificate_name' => sanitize_text_field($cert['name']),
                'certificate_number' => !empty($cert['number']) ? sanitize_text_field($cert['number']) : null,
                'valid_until' => !empty($cert['valid_until']) ? sanitize_text_field($cert['valid_until']) : null,
            ];
            
            if ($cert_id > 0 && in_array($cert_id, $existing_ids)) {
                // Update existing certificate
                $wpdb->update(
                    $wpdb->prefix . 'saw_visitor_certificates',
                    $cert_data,
                    ['id' => $cert_id],
                    ['%d', '%d', '%d', '%s', '%s', '%s'],
                    ['%d']
                );
                $processed_ids[] = $cert_id;
            } else {
                // Insert new certificate
                $cert_data['created_at'] = current_time('mysql');
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visitor_certificates',
                    $cert_data
                );
                if ($wpdb->insert_id) {
                    $processed_ids[] = $wpdb->insert_id;
                }
            }
        }
        
        // Delete certificates that were not in the submitted data
        $to_delete = array_diff($existing_ids, $processed_ids);
        if (!empty($to_delete)) {
            $placeholders = implode(',', array_fill(0, count($to_delete), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}saw_visitor_certificates 
                 WHERE id IN ($placeholders) AND visitor_id = %d",
                array_merge($to_delete, [$visitor_id])
            ));
        }
    }
    
    /**
     * Check if visitor can be deleted
     * 
     * @since 3.9.9
     * @param int $visitor_id Visitor ID
     * @return bool True if can be deleted
     */
    private function can_delete_visitor($visitor_id) {
        global $wpdb;
        
        $has_checkin = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visitor_id = %d AND checked_in_at IS NOT NULL",
            $visitor_id
        ));
        
        return $has_checkin == 0;
    }
    
    /**
     * Get available training steps based on actual content
     */
    private function get_available_training_steps() {
        global $wpdb;
        
        $steps = [];
        
        if (!class_exists('SAW_Session_Manager')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        $session = SAW_Session_Manager::instance();
        $flow = $session->get('invitation_flow');
        $lang = $flow['language'] ?? 'cs';
        
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $lang
        ));
        
        if (!$language_id) {
            return $steps;
        }
        
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_training_content 
             WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
            $this->customer_id,
            $this->branch_id,
            $language_id
        ), ARRAY_A);
        
        if (!$content) {
            return $steps;
        }
        
        $content_id = $content['id'];
        
        // Video
        if (!empty($content['video_url'])) {
            $steps[] = ['type' => 'video', 'step' => 'training-video', 'has_content' => true];
        }
        
        // Map
        if (!empty($content['pdf_map_path'])) {
            $steps[] = ['type' => 'map', 'step' => 'training-map', 'has_content' => true];
        }
        
        // Risks
        if (!empty($content['risks_text'])) {
            $steps[] = ['type' => 'risks', 'step' => 'training-risks', 'has_content' => true];
        }
        
        // Department
        $dept_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_department_content 
             WHERE training_content_id = %d AND (text_content IS NOT NULL AND text_content != '')",
            $content_id
        ));
        
        $dept_docs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents 
             WHERE document_type = 'department' 
             AND customer_id = %d AND branch_id = %d",
            $this->customer_id,
            $this->branch_id
        ));
        
        if ($dept_count > 0 || $dept_docs > 0) {
            $steps[] = ['type' => 'department', 'step' => 'training-department', 'has_content' => true];
        }
        
        // OOPP
        if (class_exists('SAW_OOPP_Public')) {
            $has_oopp = SAW_OOPP_Public::has_oopp($this->customer_id, $this->branch_id, $this->visit_id);
            if ($has_oopp) {
                $steps[] = ['type' => 'oopp', 'step' => 'training-oopp', 'has_content' => true];
            }
        }
        
        // Additional
        $has_additional_text = !empty($content['additional_text']);
        $additional_docs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents 
             WHERE document_type = 'additional' AND reference_id = %d",
            $content_id
        ));
        
        if ($has_additional_text || $additional_docs > 0) {
            $steps[] = ['type' => 'additional', 'step' => 'training-additional', 'has_content' => true];
        }
        
        return $steps;
    }
    
    /**
     * Check if any training content exists
     */
    private function has_training_content() {
        $available_steps = $this->get_available_training_steps();
        return !empty($available_steps);
    }
    
    /**
     * Render page header
     */
    private function render_header() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-header.php';
        if (file_exists($template)) {
            require $template;
        }
        
        $has_training = $this->has_training_content();
        $flow = $this->session->get('invitation_flow');
        $current_step = $this->current_step;
        $token = $this->token;
        $available_training_steps = $has_training ? $this->get_available_training_steps() : [];
        
        $progress = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/components/progress-indicator.php';
        if (file_exists($progress)) {
            require $progress;
        }
    }
    
    /**
     * Render page footer
     */
    private function render_footer() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-footer.php';
        if (file_exists($template)) {
            require $template;
        }
    }
    
    /**
     * Render template with data
     */
    private function render_template($template, $data = []) {
        extract($data);
        require $template;
    }
    
    /**
     * Render language selection step
     */
    private function render_language_selection() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/1-language.php';
        if (file_exists($template)) {
            $this->render_template($template, ['languages' => $this->languages, 'token' => $this->token]);
        }
    }
    
    /**
     * Render risks upload step
     */
    private function render_risks_upload() {
        global $wpdb;
        
        $existing_text = $wpdb->get_var($wpdb->prepare(
            "SELECT text_content FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'text' 
             ORDER BY uploaded_at DESC LIMIT 1",
            $this->visit_id
        ));
        
        $existing_docs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'document' 
             ORDER BY uploaded_at ASC",
            $this->visit_id
        ), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/2-risks-upload.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'existing_text' => $existing_text,
                'existing_docs' => $existing_docs,
                'token' => $this->token
            ]);
        }
    }
    
    /**
     * Render visitors registration step
     */
    private function render_visitors_registration() {
        global $wpdb;
        
        $flow = $this->session->get('invitation_flow');
        $lang = $flow['language'] ?? 'cs';
        
        // Get visitors with their certificates
        $existing_visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, 
                    (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_daily_logs dl 
                     WHERE dl.visitor_id = v.id AND dl.checked_in_at IS NOT NULL) as has_checkin
             FROM {$wpdb->prefix}saw_visitors v
             WHERE v.visit_id = %d 
             ORDER BY v.created_at ASC",
            $this->visit_id
        ), ARRAY_A);
        
        // Load certificates for each visitor
        foreach ($existing_visitors as &$visitor) {
            $visitor['certificates'] = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_visitor_certificates 
                 WHERE visitor_id = %d 
                 ORDER BY created_at ASC",
                $visitor['id']
            ), ARRAY_A);
            
            // Can delete if no check-in
            $visitor['can_delete'] = ($visitor['has_checkin'] == 0);
            $visitor['can_edit'] = ($visitor['has_checkin'] == 0);
        }
        unset($visitor);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/3-visitors-register.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'existing_visitors' => $existing_visitors,
                'lang' => $lang,
                'token' => $this->token
            ]);
        }
    }
    
    /**
     * Render training video step
     */
    private function render_training_video() {
        $available_steps = $this->get_available_training_steps();
        $has_video_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'video') {
                $has_video_step = true;
                break;
            }
        }
        
        if (!$has_video_step) {
            $this->skip_to_next_available_step('training-video');
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/video.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    /**
     * Render training map step
     */
    private function render_training_map() {
        $available_steps = $this->get_available_training_steps();
        $has_map_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'map') {
                $has_map_step = true;
                break;
            }
        }
        
        if (!$has_map_step) {
            $this->skip_to_next_available_step('training-map');
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/map.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    /**
     * Render training risks step
     */
    private function render_training_risks() {
        $available_steps = $this->get_available_training_steps();
        $has_risks_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'risks') {
                $has_risks_step = true;
                break;
            }
        }
        
        if (!$has_risks_step) {
            $this->skip_to_next_available_step('training-risks');
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/risks.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    /**
     * Render training department step
     */
    private function render_training_department() {
        $available_steps = $this->get_available_training_steps();
        $has_department_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'department') {
                $has_department_step = true;
                break;
            }
        }
        
        if (!$has_department_step) {
            $this->skip_to_next_available_step('training-department');
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/department.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    /**
     * Render training OOPP step
     */
    private function render_training_oopp() {
        $available_steps = $this->get_available_training_steps();
        $has_oopp_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'oopp') {
                $has_oopp_step = true;
                break;
            }
        }
        
        if (!$has_oopp_step) {
            $this->skip_to_next_available_step('training-oopp');
            return;
        }
        
        $oopp_items = [];
        if (class_exists('SAW_OOPP_Public')) {
            $oopp_items = SAW_OOPP_Public::get_for_visitor(
                $this->customer_id, 
                $this->branch_id, 
                $this->visit_id
            );
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/oopp.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'oopp_items' => $oopp_items,
                'token' => $this->token,
                'is_invitation' => true,
            ]);
        }
    }
    
    /**
     * Render training additional step
     */
    private function render_training_additional() {
        $available_steps = $this->get_available_training_steps();
        $has_additional_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'additional') {
                $has_additional_step = true;
                break;
            }
        }
        
        if (!$has_additional_step) {
            $this->skip_to_next_available_step('training-additional');
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/additional.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    /**
     * Skip to next available training step or summary
     */
    private function skip_to_next_available_step($current_step) {
        $flow = $this->session->get('invitation_flow');
        $available_steps = $this->get_available_training_steps();
        
        $step_order = ['training-video', 'training-map', 'training-risks', 'training-department', 'training-oopp', 'training-additional'];
        $current_order_index = array_search($current_step, $step_order);
        
        $next_step = 'summary';
        
        foreach ($available_steps as $step) {
            $step_order_index = array_search($step['step'], $step_order);
            if ($step_order_index !== false && $step_order_index > $current_order_index) {
                $next_step = $step['step'];
                break;
            }
        }
        
        $flow['step'] = $next_step;
        if (!in_array($current_step, $flow['history'] ?? [])) {
            $flow['history'][] = $current_step;
        }
        
        $this->session->set('invitation_flow', $flow);
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $next_step));
        exit;
    }
    
    /**
     * Handle complete training action
     */
    private function handle_complete_training() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $flow = $this->session->get('invitation_flow');
        $current = $this->current_step;
        
        if (!in_array($current, $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = $current;
        }
        if (!in_array($current, $flow['history'] ?? [])) {
            $flow['history'][] = $current;
        }
        
        $available_steps = $this->get_available_training_steps();
        
        $current_index = -1;
        foreach ($available_steps as $index => $step) {
            if ($step['step'] === $current) {
                $current_index = $index;
                break;
            }
        }
        
        if ($current_index !== -1 && isset($available_steps[$current_index + 1])) {
            $next_step = $available_steps[$current_index + 1]['step'];
            $flow['step'] = $next_step;
        } else {
            $flow['step'] = 'summary';
        }
        
        $this->session->set('invitation_flow', $flow);
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $flow['step']));
        exit;
    }
    
    /**
     * Handle skip training action
     */
    private function handle_skip_training() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $flow = $this->session->get('invitation_flow');
        
        $training_steps = ['training-video', 'training-map', 'training-risks', 'training-department', 'training-oopp', 'training-additional'];
        foreach ($training_steps as $step) {
            if (!in_array($step, $flow['history'] ?? [])) {
                $flow['history'][] = $step;
            }
        }
        
        $flow['step'] = 'summary';
        $this->session->set('invitation_flow', $flow);
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=summary'));
        exit;
    }
    
    /**
     * Render summary page
     */
    private function render_summary() {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $this->visit_id
        ), ARRAY_A);
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $this->branch_id
        ), ARRAY_A);
        
        $company = null;
        if (!empty($visit['company_id'])) {
            $company = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_companies WHERE id = %d",
                $visit['company_id']
            ), ARRAY_A);
        }
        
        $schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_schedules 
             WHERE visit_id = %d 
             ORDER BY sort_order ASC, date ASC",
            $this->visit_id
        ), ARRAY_A);
        
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CONCAT(su.first_name, ' ', su.last_name) as display_name,
                su.email as user_email,
                su.position as phone
             FROM {$wpdb->prefix}saw_visit_hosts vh
             JOIN {$wpdb->prefix}saw_users su ON vh.user_id = su.id
             WHERE vh.visit_id = %d",
            $this->visit_id
        ), ARRAY_A);
        
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d AND participation_status != 'cancelled'
             ORDER BY id ASC",
            $this->visit_id
        ), ARRAY_A);
        
        $risks_text = $wpdb->get_var($wpdb->prepare(
            "SELECT text_content FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'text' 
             ORDER BY uploaded_at DESC LIMIT 1",
            $this->visit_id
        ));
        
        $risks_docs = $wpdb->get_results($wpdb->prepare(
            "SELECT file_name, file_size FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'document'",
            $this->visit_id
        ), ARRAY_A);
        
        $pin = $visit['pin_code'] ?? '';
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/8-summary.php';
        $this->render_template($template, [
            'visit' => $visit,
            'branch' => $branch,
            'company' => $company,
            'schedules' => $schedules,
            'hosts' => $hosts,
            'visitors' => $visitors,
            'risks_text' => $risks_text,
            'risks_docs' => $risks_docs,
            'pin' => $pin,
        ]);
    }

    /**
     * Handle confirm summary action
     */
    private function handle_confirm_summary() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $flow = $this->session->get('invitation_flow');
        
        if (!in_array('summary', $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = 'summary';
        }
        if (!in_array('summary', $flow['history'] ?? [])) {
            $flow['history'][] = 'summary';
        }
        
        $flow['step'] = 'success';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=success'));
        exit;
    }
    
    /**
     * Render PIN success page and send Info Portal emails
     */
    private function render_pin_success() {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            [
                'status' => 'confirmed',
                'invitation_confirmed_at' => current_time('mysql'),
            ],
            ['id' => $this->visit_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Send Info Portal emails
        $email_service_file = SAW_VISITORS_PLUGIN_DIR . 'includes/services/class-saw-visitor-info-email.php';
        if (file_exists($email_service_file)) {
            require_once $email_service_file;
            
            if (class_exists('SAW_Visitor_Info_Email')) {
                $flow = $this->session->get('invitation_flow');
                $language = $flow['language'] ?? 'cs';
                
                if (!in_array($language, ['cs', 'en', 'sk', 'uk', 'de', 'pl', 'hu', 'ro'])) {
                    $language = 'cs';
                }
                
                SAW_Visitor_Info_Email::send_to_visit($this->visit_id, $language);
            }
        }
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $this->visit_id
        ), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/9-pin-success.php';
        
        if (file_exists($template)) {
            $this->render_template($template, [
                'visit' => $visit,
                'pin' => $visit['pin_code'] ?? '',
                'token' => $this->token
            ]);
        }
    }
    
    /**
     * Set error message in session
     */
    private function set_error($message) {
        $flow = $this->session->get('invitation_flow');
        $flow['error'] = $message;
        $this->session->set('invitation_flow', $flow);
    }
    
    /**
     * Handle WordPress media library AJAX query
     */
    public static function handle_media_query() {
        $context = self::verify_invitation_ajax_context();
        if (!$context) {
            wp_send_json_error(array('message' => 'Invalid invitation context'));
        }
        
        $query = isset($_REQUEST['query']) ? (array) $_REQUEST['query'] : array();
        
        $query['post_mime_type'] = 'image';
        
        $query['meta_query'] = array(
            array(
                'key' => 'saw_visit_id',
                'value' => $context['visit_id'],
                'compare' => '='
            )
        );
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => isset($query['posts_per_page']) ? (int) $query['posts_per_page'] : 40,
            'paged' => isset($query['paged']) ? (int) $query['paged'] : 1,
            'post_mime_type' => $query['post_mime_type'],
            'meta_query' => $query['meta_query'],
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $response = array();
        foreach ($attachments as $attachment) {
            $response[] = wp_prepare_attachment_for_js($attachment->ID);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle WordPress media library AJAX upload
     */
    public static function handle_media_upload() {
        $context = self::verify_invitation_ajax_context();
        if (!$context) {
            wp_send_json_error(array('message' => 'Invalid invitation context'));
        }
        
        if (empty($_FILES['async-upload'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }
        
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $file_type = $_FILES['async-upload']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error(array('message' => 'Only images are allowed (JPG, PNG, GIF, WEBP)'));
        }
        
        $max_size = 5 * 1024 * 1024;
        if ($_FILES['async-upload']['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File too large (max 5MB)'));
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        $attachment_id = media_handle_upload('async-upload', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }
        
        update_post_meta($attachment_id, 'saw_visit_id', $context['visit_id']);
        update_post_meta($attachment_id, 'saw_invitation_upload', true);
        update_post_meta($attachment_id, 'saw_customer_id', $context['customer_id']);
        update_post_meta($attachment_id, 'saw_branch_id', $context['branch_id']);
        
        $attachment = wp_prepare_attachment_for_js($attachment_id);
        wp_send_json_success($attachment);
    }
    
    /**
     * Verify invitation AJAX context
     */
    private static function verify_invitation_ajax_context() {
        if (!class_exists('SAW_Session_Manager')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        $session = SAW_Session_Manager::instance();
        $flow = $session->get('invitation_flow');
        
        if (empty($flow['visit_id']) || empty($flow['token'])) {
            return false;
        }
        
        $visit_id = $flow['visit_id'];
        $customer_id = $flow['customer_id'];
        $branch_id = $flow['branch_id'];
        $token = $flow['token'];
        
        global $wpdb;
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visits 
             WHERE id = %d 
             AND invitation_token = %s 
             AND invitation_token_expires_at > NOW()",
            $visit_id,
            $token
        ));
        
        if (empty($visit)) {
            return false;
        }
        
        return array(
            'visit_id' => $visit_id,
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
            'token' => $token
        );
    }
}