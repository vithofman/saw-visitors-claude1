<?php
/**
 * Invitation Controller
 * 
 * @package SAW_Visitors
 * @version 1.4.1 - Fixed media gallery with richtext-editor component
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
        
        $this->session = SAW_Session_Manager::instance();
        $this->init_invitation_session();
        $this->load_languages();
        $this->current_step = $this->get_current_step();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Register AJAX handlers for invitation media library
     * 
     * Called from plugin initialization, not from instance constructor.
     * 
     * @since 5.4.4
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_nopriv_query-attachments', array(__CLASS__, 'handle_media_query'));
        add_action('wp_ajax_nopriv_upload-attachment', array(__CLASS__, 'handle_media_upload'));
    }
    
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
    
    private function reload_visit_from_token() {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits 
             WHERE invitation_token = %s 
             AND invitation_token_expires_at > NOW()
             AND status IN ('pending', 'draft', 'confirmed')",
            $this->token
        ), ARRAY_A);
        
        if (!$visit) {
            wp_die('Visit not found or expired', 'Error', ['response' => 404]);
        }
        
        $this->visit_id = $visit['id'];
        $this->customer_id = $visit['customer_id'];
        $this->branch_id = $visit['branch_id'];
        $this->company_id = $visit['company_id'] ?? null;
        
        $flow = [
            'token' => $this->token,
            'visit_id' => $this->visit_id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'company_id' => $this->company_id,
            'step' => 'language',
        ];
        $this->session->set('invitation_flow', $flow);
    }
    
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
    
    private function get_current_step() {
        $flow = $this->session->get('invitation_flow', []);
        
        if (empty($flow['language'])) {
            return 'language';
        }
        
        $step = $_GET['step'] ?? '';
        
        if (!empty($step)) {
            $steps_requiring_language = ['risks', 'visitors', 'training-video', 'training-map', 'training-risks', 'training-department', 'training-additional', 'success'];
            if (in_array($step, $steps_requiring_language) && empty($flow['language'])) {
                return 'language';
            }
            return $step;
        }
        
        return $flow['step'] ?? 'language';
    }
    
    public function enqueue_assets() {
        $css_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/css/';
        
        $base_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/base.css';
        if (file_exists($base_css)) {
            wp_enqueue_style('saw-terminal-base', $css_dir . 'terminal/base.css', [], '3.0.0');
        }
        
        $components_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/components.css';
        if (file_exists($components_css)) {
            wp_enqueue_style('saw-terminal-components', $css_dir . 'terminal/components.css', ['saw-terminal-base'], '3.0.0');
        }
        
        $layout_css = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/css/terminal/layout.css';
        if (file_exists($layout_css)) {
            wp_enqueue_style('saw-terminal-layout', $css_dir . 'terminal/layout.css', ['saw-terminal-base'], '3.0.0');
        }
        
        wp_enqueue_script('jquery');
        
        wp_localize_script('jquery', 'sawInvitation', [
            'token' => $this->token,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'clearNonce' => wp_create_nonce('saw_clear_invitation_session'),
        ]);
    }
    
    public function render() {
        $flow = $this->session->get('invitation_flow', []);
        $this->current_step = $this->get_current_step();
        
        if ($this->current_step !== 'language' && empty($flow['language'])) {
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=language'));
            exit;
        }
        
        // ✅ Initialize richtext editor BEFORE render_header() for risks step
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
            case 'training-additional':
                $this->render_training_additional();
                break;
            case 'success':
                $this->render_pin_success();
                break;
            default:
                $this->render_language_selection();
        }
        
        $this->render_footer();
    }
    
    private function handle_post_actions() {
        $action = $_POST['invitation_action'] ?? '';
        
        if (empty($action)) {
            return;
        }
        
        switch ($action) {
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
        }
    }
    
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
        $flow['language'] = $language;
        $flow['step'] = 'risks';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=risks'));
        exit;
    }
    
    private function handle_save_risks() {
        if (!isset($_POST['invitation_nonce']) || !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        global $wpdb;
        
        $action_type = sanitize_text_field($_POST['action'] ?? 'save');
        
        if ($action_type === 'skip') {
            $flow = $this->session->get('invitation_flow');
            $flow['step'] = 'visitors';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
            exit;
        }
        
        $risks_text = wp_kses_post($_POST['risks_text'] ?? '');
        $delete_files = $_POST['delete_files'] ?? [];
        
        if (!empty($risks_text)) {
            $wpdb->delete($wpdb->prefix . 'saw_visit_invitation_materials', ['visit_id' => $this->visit_id, 'material_type' => 'text']);
            
            $wpdb->insert($wpdb->prefix . 'saw_visit_invitation_materials', [
                'visit_id' => $this->visit_id,
                'material_type' => 'text',
                'text_content' => $risks_text,
                'uploaded_at' => current_time('mysql'),
            ]);
        }
        
        if (!empty($delete_files)) {
            foreach ($delete_files as $file_id) {
                $file = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials WHERE id = %d AND visit_id = %d", $file_id, $this->visit_id));
                if ($file && !empty($file->file_path)) {
                    $upload_dir = wp_upload_dir();
                    $file_path = $upload_dir['basedir'] . $file->file_path;
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
                $wpdb->delete($wpdb->prefix . 'saw_visit_invitation_materials', ['id' => $file_id, 'visit_id' => $this->visit_id]);
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
        $flow['step'] = 'visitors';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
        exit;
    }
    
    private function handle_save_visitors() {
        if (!isset($_POST['invitation_nonce']) || !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        global $wpdb;
        
        $visitors = $_POST['visitors'] ?? [];
        if (empty($visitors)) {
            $this->set_error('Please add at least one visitor');
            return;
        }
        
        $wpdb->delete($wpdb->prefix . 'saw_visitors', ['visit_id' => $this->visit_id]);
        
        $visitor_ids = [];
        foreach ($visitors as $visitor) {
            $training_skipped = isset($visitor['skip_training']) && $visitor['skip_training'] === '1';
            
            $wpdb->insert($wpdb->prefix . 'saw_visitors', [
                'visit_id' => $this->visit_id,
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'first_name' => sanitize_text_field($visitor['first_name'] ?? ''),
                'last_name' => sanitize_text_field($visitor['last_name'] ?? ''),
                'email' => sanitize_email($visitor['email'] ?? ''),
                'phone' => sanitize_text_field($visitor['phone'] ?? ''),
                'participation_status' => 'confirmed',
                'current_status' => 'confirmed',
                'training_skipped' => $training_skipped,
                'training_status' => $training_skipped ? 'skipped' : 'pending',
            ]);
            
            $visitor_ids[] = $wpdb->insert_id;
        }
        
        $wpdb->update($wpdb->prefix . 'saw_visits', ['status' => 'confirmed', 'invitation_confirmed_at' => current_time('mysql')], ['id' => $this->visit_id]);
        
        $has_training = $this->has_training_content();
        $needs_training = false;
        
        foreach ($visitor_ids as $id) {
            $visitor = $wpdb->get_row($wpdb->prepare("SELECT training_skipped FROM {$wpdb->prefix}saw_visitors WHERE id = %d", $id));
            if ($visitor && !$visitor->training_skipped) {
                $needs_training = true;
                break;
            }
        }
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::delete('invitation_visit_' . $this->visit_id, 'invitations');
            SAW_Cache::flush('invitations');
        }
        
        $flow = $this->session->get('invitation_flow');
        
        if ($has_training && $needs_training) {
            $flow['step'] = 'training-video';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=training-video'));
        } else {
            $flow['step'] = 'success';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=success'));
        }
        
        exit;
    }
    
    private function has_training_content() {
        global $wpdb;
        
        $flow = $this->session->get('invitation_flow');
        $language = $flow['language'] ?? 'cs';
        
        if (class_exists('SAW_Cache')) {
            $cache_key = 'training_company_' . $this->company_id . '_' . $language;
            $cached = SAW_Cache::get($cache_key, 'training');
            if ($cached !== false) {
                return !empty($cached);
            }
        }
        
        $content = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_training_content WHERE company_id = %d AND language = %s", $this->company_id, $language), ARRAY_A);
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::set($cache_key, $content, 600, 'training');
        }
        
        return !empty($content);
    }
    
    private function handle_skip_training() {
        $flow = $this->session->get('invitation_flow');
        $flow['training_skipped'] = true;
        $flow['step'] = 'success';
        $this->session->set('invitation_flow', $flow);
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=success'));
        exit;
    }
    
    private function handle_complete_training() {
        $this->move_to_next_training_step();
    }
    
    private function move_to_next_training_step() {
        $flow = $this->session->get('invitation_flow');
        $current = $this->current_step;
        
        $steps = ['training-video', 'training-map', 'training-risks', 'training-department', 'training-additional'];
        $current_index = array_search($current, $steps);
        
        if ($current_index !== false && $current_index < count($steps) - 1) {
            $next_step = $steps[$current_index + 1];
            $flow['step'] = $next_step;
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $next_step));
        } else {
            $flow['step'] = 'success';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=success'));
        }
        exit;
    }
    
    private function render_header() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-header.php';
        if (file_exists($template)) {
            require $template;
        }
    }
    
    private function render_footer() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-footer.php';
        if (file_exists($template)) {
            require $template;
        }
    }
    
    private function render_template($template, $data = []) {
        extract($data);
        require $template;
    }
    
    private function render_language_selection() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/1-language.php';
        if (file_exists($template)) {
            $this->render_template($template, ['languages' => $this->languages, 'token' => $this->token]);
        }
    }
    
    private function render_risks_upload() {
        global $wpdb;
        
        $existing_text = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM {$wpdb->prefix}saw_visit_invitation_materials WHERE visit_id = %d AND material_type = 'text' ORDER BY uploaded_at DESC LIMIT 1", $this->visit_id));
        $existing_docs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials WHERE visit_id = %d AND material_type = 'document' ORDER BY uploaded_at ASC", $this->visit_id), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/2-risks-upload.php';
        if (file_exists($template)) {
            $this->render_template($template, ['existing_text' => $existing_text, 'existing_docs' => $existing_docs, 'token' => $this->token]);
        }
    }
    
    private function render_visitors_registration() {
        global $wpdb;
        
        $flow = $this->session->get('invitation_flow');
        $lang = $flow['language'] ?? 'cs';
        $existing_visitors = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d ORDER BY created_at ASC", $this->visit_id), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/3-visitors-register.php';
        if (file_exists($template)) {
            $this->render_template($template, ['existing_visitors' => $existing_visitors, 'lang' => $lang, 'token' => $this->token]);
        }
    }
    
    private function render_training_video() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/video.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    private function render_training_map() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/map.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    private function render_training_risks() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/risks.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    private function render_training_department() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/department.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    private function render_training_additional() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/additional.php';
        if (file_exists($template)) {
            $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
        }
    }
    
    private function render_pin_success() {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d", $this->visit_id), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/9-pin-success.php';
        if (file_exists($template)) {
            $this->render_template($template, ['visit' => $visit, 'pin' => $visit['pin_code'] ?? '', 'token' => $this->token]);
        }
    }
    
    private function set_error($message) {
        $flow = $this->session->get('invitation_flow');
        $flow['error'] = $message;
        $this->session->set('invitation_flow', $flow);
    }
    
    /**
     * Handle WordPress media library AJAX query
     * 
     * Allows unprivileged users to query attachments, but only shows
     * images uploaded for their specific visit_id.
     * 
     * @since 5.4.4
     */
    public static function handle_media_query() {
        // Verify invitation context
        $context = self::verify_invitation_ajax_context();
        if (!$context) {
            wp_send_json_error(array('message' => 'Invalid invitation context'));
        }
        
        // Get query parameters
        $query = isset($_REQUEST['query']) ? (array) $_REQUEST['query'] : array();
        
        // Limit to images only
        $query['post_mime_type'] = 'image';
        
        // Limit to images uploaded for this visit
        $query['meta_query'] = array(
            array(
                'key' => 'saw_visit_id',
                'value' => $context['visit_id'],
                'compare' => '='
            )
        );
        
        // Query attachments
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
        
        // Prepare attachments for JSON
        $response = array();
        foreach ($attachments as $attachment) {
            $response[] = wp_prepare_attachment_for_js($attachment->ID);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle WordPress media library AJAX upload
     * 
     * Allows unprivileged users to upload images, with restrictions:
     * - Only image files (jpg, png, gif, webp)
     * - Max 5MB per file
     * - Tagged with visit_id for isolation
     * 
     * @since 5.4.4
     */
    public static function handle_media_upload() {
        // Verify invitation context
        $context = self::verify_invitation_ajax_context();
        if (!$context) {
            wp_send_json_error(array('message' => 'Invalid invitation context'));
        }
        
        // Check if file was uploaded
        if (empty($_FILES['async-upload'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $file_type = $_FILES['async-upload']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error(array('message' => 'Only images are allowed (JPG, PNG, GIF, WEBP)'));
        }
        
        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['async-upload']['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File too large (max 5MB)'));
        }
        
        // Load WordPress file handling functions
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        // Upload file
        $attachment_id = media_handle_upload('async-upload', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }
        
        // Tag attachment with visit_id for isolation
        update_post_meta($attachment_id, 'saw_visit_id', $context['visit_id']);
        update_post_meta($attachment_id, 'saw_invitation_upload', true);
        update_post_meta($attachment_id, 'saw_customer_id', $context['customer_id']);
        update_post_meta($attachment_id, 'saw_branch_id', $context['branch_id']);
        
        // Return attachment data
        $attachment = wp_prepare_attachment_for_js($attachment_id);
        wp_send_json_success($attachment);
    }
    
    /**
     * Verify invitation AJAX context
     * 
     * Checks if the current AJAX request has valid invitation session.
     * 
     * @since 5.4.4
     * @return array|false Context array with visit_id, customer_id, branch_id, token or false on failure
     */
    private static function verify_invitation_ajax_context() {
        // Get session manager
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
        
        // Verify visit still exists and is valid
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