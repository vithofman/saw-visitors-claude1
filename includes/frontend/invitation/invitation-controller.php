<?php
/**
 * Invitation Controller
 * 
 * Standalone system for online visitor pre-registration.
 * DOES NOT perform check-in/out - only saves data to DB.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class SAW_Invitation_Controller {
    
    /**
     * Session manager instance
     * 
     * @var SAW_Session_Manager
     */
    private $session;
    
    /**
     * Current step in flow
     * 
     * @var string
     */
    private $current_step;
    
    /**
     * Invitation token
     * 
     * @var string
     */
    private $token;
    
    /**
     * Visit ID
     * 
     * @var int
     */
    private $visit_id;
    
    /**
     * Customer ID
     * 
     * @var int
     */
    private $customer_id;
    
    /**
     * Branch ID
     * 
     * @var int
     */
    private $branch_id;
    
    /**
     * Company ID
     * 
     * @var int|null
     */
    private $company_id;
    
    /**
     * Available languages
     * 
     * @var array
     */
    private $languages = [];
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Load dependencies
        if (!class_exists('SAW_Session_Manager')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        if (!class_exists('SAW_Cache')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-cache.php';
        }
        
        $this->session = SAW_Session_Manager::instance();
        
        // Initialize
        $this->init_invitation_session();
        $this->load_languages();
        $this->current_step = $this->get_current_step();
    }
    
    /**
     * Initialize invitation session
     * 
     * @since 1.0.0
     * @return void
     */
    private function init_invitation_session() {
        // Get token from query var (set by router)
        $this->token = get_query_var('saw_invitation_token');
        
        if (empty($this->token)) {
            // Fallback: try from session
            $flow = $this->session->get('invitation_flow');
            $this->token = $flow['token'] ?? '';
        }
        
        if (empty($this->token)) {
            wp_die('Missing invitation token', 'Error', ['response' => 400]);
        }
        
        // Load visit from session
        $flow = $this->session->get('invitation_flow');
        
        if (empty($flow) || ($flow['token'] ?? '') !== $this->token) {
            // Session expired or token changed - reload from DB
            $this->reload_visit_from_token();
        } else {
            // Load from session
            $this->visit_id = $flow['visit_id'] ?? null;
            $this->customer_id = $flow['customer_id'] ?? null;
            $this->branch_id = $flow['branch_id'] ?? null;
            $this->company_id = $flow['company_id'] ?? null;
        }
        
        // Validate we have all required data
        if (!$this->visit_id || !$this->customer_id || !$this->branch_id) {
            wp_die('Invalid invitation session. Please use the invitation link again.', 'Error', ['response' => 400]);
        }
    }
    
    /**
     * Reload visit data from token
     * 
     * @since 1.0.0
     * @return void
     */
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
        
        // Update session
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
    
    /**
     * Load available languages from database
     * 
     * @since 1.0.0
     * @return void
     */
    private function load_languages() {
        global $wpdb;
        
        if (!$this->customer_id || !$this->branch_id) {
            // Fallback languages
            $this->languages = ['cs' => 'Čeština', 'en' => 'English'];
            return;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                tl.language_code,
                tl.language_name,
                tl.flag_emoji,
                tlb.is_default,
                tlb.display_order
             FROM {$wpdb->prefix}saw_training_languages tl
             INNER JOIN {$wpdb->prefix}saw_training_language_branches tlb 
                ON tl.id = tlb.language_id
             WHERE tl.customer_id = %d
               AND tlb.branch_id = %d
               AND tlb.is_active = 1
             ORDER BY tlb.display_order ASC, tl.language_name ASC",
            $this->customer_id,
            $this->branch_id
        ));
        
        // Create simple array: ['cs' => 'Čeština', 'en' => 'English']
        $this->languages = [];
        foreach ($results as $row) {
            $this->languages[$row->language_code] = $row->language_name;
        }
        
        // Fallback if no languages found
        if (empty($this->languages)) {
            $this->languages = ['cs' => 'Čeština', 'en' => 'English'];
        }
    }
    
    /**
     * Get current step from query parameter or session
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_current_step() {
        $step = $_GET['step'] ?? '';
        
        if (!empty($step)) {
            return $step;
        }
        
        $flow = $this->session->get('invitation_flow');
        return $flow['step'] ?? 'language';
    }
    
    /**
     * Main render method
     * 
     * @since 1.0.0
     * @return void
     */
    public function render() {
        // Enqueue assets
        $this->enqueue_assets();
        
        // Handle POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_post_actions();
        }
        
        // Render layout
        $this->render_header();
        
        // Render step
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
    
    /**
     * Enqueue CSS and JS assets
     * 
     * @since 1.0.0
     * @return void
     */
    private function enqueue_assets() {
        $css_dir = SAW_VISITORS_PLUGIN_URL . 'assets/css/';
        $js_dir = SAW_VISITORS_PLUGIN_URL . 'assets/js/';
        
        // CSS - Shared with terminal
        wp_enqueue_style('saw-terminal-base', $css_dir . 'terminal/base.css', [], '3.0.0');
        wp_enqueue_style('saw-terminal-components', $css_dir . 'terminal/components.css', ['saw-terminal-base'], '3.0.0');
        wp_enqueue_style('saw-terminal-training', $css_dir . 'terminal/training.css', ['saw-terminal-base'], '3.0.0');
        
        // CSS - Invitation specific
        wp_enqueue_style('saw-invitation-risks', $css_dir . 'invitation/invitation-risks.css', ['saw-terminal-base'], '1.0.0');
        wp_enqueue_style('saw-invitation-success', $css_dir . 'invitation/invitation-success.css', ['saw-terminal-base'], '1.0.0');
        
        // JS - Shared
        wp_enqueue_script('jquery');
        wp_enqueue_script('saw-touch-gestures', $js_dir . 'terminal/touch-gestures.js', [], '3.0.0', true);
        wp_enqueue_script('saw-pdf-viewer', $js_dir . 'terminal/pdf-viewer.js', ['saw-touch-gestures'], '3.0.0', true);
        wp_enqueue_script('saw-video-player', $js_dir . 'terminal/video-player.js', [], '3.0.0', true);
        
        // JS - Invitation specific
        wp_enqueue_script('saw-invitation-main', $js_dir . 'invitation/invitation-main.js', ['jquery'], '1.0.0', true);
        
        // Autosave (only on risks step)
        if ($this->current_step === 'risks') {
            wp_enqueue_editor();
            wp_enqueue_media();
            
            wp_enqueue_script(
                'saw-invitation-autosave',
                $js_dir . 'invitation/invitation-autosave.js',
                ['jquery'],
                '1.0.0',
                true
            );
            
            wp_localize_script('saw-invitation-autosave', 'sawInvitation', [
                'token' => $this->token,
                'ajaxurl' => admin_url('admin-ajax.php'),
                'autosaveNonce' => wp_create_nonce('saw_invitation_autosave'),
            ]);
        }
        
        // Localize for main JS
        wp_localize_script('saw-invitation-main', 'sawInvitation', [
            'token' => $this->token,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'clearNonce' => wp_create_nonce('saw_clear_invitation_session'),
        ]);
    }
    
    /**
     * Handle POST actions
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_post_actions() {
        $action = $_POST['invitation_action'] ?? '';
        
        // Verify nonce
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Security check failed', 'Error', ['response' => 403]);
        }
        
        switch ($action) {
            case 'set_language':
                $this->handle_set_language();
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
                
            default:
                $this->set_error('Neplatná akce');
                $this->render();
                break;
        }
    }
    
    /**
     * Handle language selection
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_set_language() {
        $language = sanitize_text_field($_POST['language'] ?? '');
        
        if (!array_key_exists($language, $this->languages)) {
            $this->set_error('Neplatný jazyk');
            $this->render_language_selection();
            return;
        }
        
        $flow = $this->session->get('invitation_flow');
        $flow['language'] = $language;
        $flow['step'] = 'risks';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=risks'));
        exit;
    }
    
    /**
     * Handle save risks
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_save_risks() {
        global $wpdb;
        
        $action = $_POST['action'] ?? 'save';
        
        // Skip pressed
        if ($action === 'skip') {
            $flow = $this->session->get('invitation_flow');
            $flow['step'] = 'visitors';
            $this->session->set('invitation_flow', $flow);
            
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
            exit;
        }
        
        // Save text
        $risks_text = wp_kses_post($_POST['risks_text'] ?? '');
        
        if (!empty(trim($risks_text))) {
            // Delete old text
            $wpdb->delete(
                $wpdb->prefix . 'saw_visit_invitation_materials',
                [
                    'visit_id' => $this->visit_id,
                    'material_type' => 'text'
                ]
            );
            
            // Insert new
            $wpdb->insert(
                $wpdb->prefix . 'saw_visit_invitation_materials',
                [
                    'visit_id' => $this->visit_id,
                    'customer_id' => $this->customer_id,
                    'branch_id' => $this->branch_id,
                    'company_id' => $this->company_id,
                    'material_type' => 'text',
                    'text_content' => $risks_text,
                ]
            );
        }
        
        // Handle file uploads
        $this->handle_file_uploads();
        
        // Update visit status
        $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            ['status' => 'draft'],
            ['id' => $this->visit_id]
        );
        
        // Invalidate cache
        if (class_exists('SAW_Cache')) {
            SAW_Cache::delete('invitation_visit_' . $this->visit_id, 'invitations');
        }
        
        // Redirect
        $flow = $this->session->get('invitation_flow');
        $flow['step'] = 'visitors';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
        exit;
    }
    
    /**
     * Handle file uploads
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_file_uploads() {
        global $wpdb;
        
        if (empty($_FILES['risks_documents']['name'][0])) {
            return;
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        
        $files = $_FILES['risks_documents'];
        $count = min(count($files['name']), 10);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            
            // Validate size (10MB max)
            if ($file['size'] > 10485760) continue;
            
            $uploaded = wp_handle_upload($file, [
                'test_form' => false,
                'mimes' => [
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ]
            ]);
            
            if (!isset($uploaded['error'])) {
                $upload_dir = wp_upload_dir();
                $relative_path = str_replace($upload_dir['basedir'], '', $uploaded['file']);
                
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'visit_id' => $this->visit_id,
                        'customer_id' => $this->customer_id,
                        'branch_id' => $this->branch_id,
                        'company_id' => $this->company_id,
                        'material_type' => 'document',
                        'file_path' => $relative_path,
                        'file_name' => basename($uploaded['file']),
                        'file_size' => $file['size'],
                        'mime_type' => $uploaded['type'],
                    ]
                );
            }
        }
    }
    
    /**
     * Handle save visitors
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_save_visitors() {
        global $wpdb;
        
        $existing_ids = $_POST['existing_visitor_ids'] ?? [];
        $new_visitors = $_POST['new_visitors'] ?? [];
        $training_skip = $_POST['training_skip'] ?? [];
        
        // Validate: musí být alespoň jeden visitor
        if (empty($existing_ids) && empty($new_visitors)) {
            $this->set_error('Musíte vybrat nebo zadat alespoň jednoho návštěvníka');
            $this->render_visitors_registration();
            return;
        }
        
        // Update existing visitors
        foreach ($existing_ids as $id) {
            $id = intval($id);
            
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                [
                    'participation_status' => 'confirmed',
                    'training_skipped' => isset($training_skip[$id]) ? 1 : 0,
                    'training_status' => isset($training_skip[$id]) ? 'skipped' : 'pending',
                ],
                ['id' => $id]
            );
        }
        
        // Create new visitors
        $visitor_ids = $existing_ids;
        
        foreach ($new_visitors as $visitor) {
            if (empty($visitor['first_name']) || empty($visitor['last_name'])) {
                continue;
            }
            
            $training_skipped = isset($visitor['training_skip']) ? 1 : 0;
            
            $wpdb->insert(
                $wpdb->prefix . 'saw_visitors',
                [
                    'visit_id' => $this->visit_id,
                    'customer_id' => $this->customer_id,
                    'branch_id' => $this->branch_id,
                    'first_name' => sanitize_text_field($visitor['first_name']),
                    'last_name' => sanitize_text_field($visitor['last_name']),
                    'position' => sanitize_text_field($visitor['position'] ?? ''),
                    'email' => sanitize_email($visitor['email'] ?? ''),
                    'phone' => sanitize_text_field($visitor['phone'] ?? ''),
                    'participation_status' => 'confirmed',
                    'current_status' => 'confirmed',
                    'training_skipped' => $training_skipped,
                    'training_status' => $training_skipped ? 'skipped' : 'pending',
                ]
            );
            
            $visitor_ids[] = $wpdb->insert_id;
        }
        
        // Update visit status
        $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            ['status' => 'confirmed', 'invitation_confirmed_at' => current_time('mysql')],
            ['id' => $this->visit_id]
        );
        
        // Check if training needed
        $has_training = $this->has_training_content();
        $needs_training = false;
        
        foreach ($visitor_ids as $id) {
            $visitor = $wpdb->get_row($wpdb->prepare(
                "SELECT training_skipped FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
                $id
            ));
            
            if ($visitor && !$visitor->training_skipped) {
                $needs_training = true;
                break;
            }
        }
        
        // Invalidate cache
        if (class_exists('SAW_Cache')) {
            SAW_Cache::delete('invitation_visit_' . $this->visit_id, 'invitations');
            SAW_Cache::flush('invitations');
        }
        
        // Redirect
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
    
    /**
     * Check if training content exists
     * 
     * @since 1.0.0
     * @return bool
     */
    private function has_training_content() {
        global $wpdb;
        
        $flow = $this->session->get('invitation_flow');
        $language = $flow['language'] ?? 'cs';
        
        // Check cache
        if (class_exists('SAW_Cache')) {
            $cache_key = 'training_company_' . $this->company_id . '_' . $language;
            $cached = SAW_Cache::get($cache_key, 'training');
            
            if ($cached !== false) {
                return !empty($cached);
            }
        }
        
        // Query DB
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_training_content 
             WHERE company_id = %d AND language = %s",
            $this->company_id,
            $language
        ), ARRAY_A);
        
        // Cache result
        if (class_exists('SAW_Cache')) {
            SAW_Cache::set($cache_key, $content, 600, 'training');
        }
        
        return !empty($content);
    }
    
    /**
     * Handle skip training
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_skip_training() {
        $flow = $this->session->get('invitation_flow');
        $flow['training_skipped'] = true;
        $flow['step'] = 'success';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=success'));
        exit;
    }
    
    /**
     * Handle complete training
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_complete_training() {
        // Move to next training step or success
        $this->move_to_next_training_step();
    }
    
    /**
     * Move to next training step
     * 
     * @since 1.0.0
     * @return void
     */
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
    
    /**
     * Render header
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_header() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-header.php';
        if (file_exists($template)) {
            require $template;
        } else {
            // Fallback minimal header
            ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
        }
    }
    
    /**
     * Render footer
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_footer() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-footer.php';
        if (file_exists($template)) {
            require $template;
        } else {
            // Fallback minimal footer
            wp_footer(); ?>
</body>
</html>
<?php
        }
    }
    
    /**
     * Render template with data
     * 
     * @since 1.0.0
     * @param string $template Template path
     * @param array $data Data to extract
     * @return void
     */
    private function render_template($template, $data = []) {
        extract($data);
        require $template;
    }
    
    /**
     * Render language selection step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_language_selection() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/1-language.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'languages' => $this->languages,
                'token' => $this->token,
            ]);
        } else {
            echo '<p>Language selection template not found</p>';
        }
    }
    
    /**
     * Render risks upload step
     * 
     * @since 1.0.0
     * @return void
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
                'token' => $this->token,
            ]);
        } else {
            echo '<p>Risks upload template not found</p>';
        }
    }
    
    /**
     * Render visitors registration step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_visitors_registration() {
        global $wpdb;
        
        $flow = $this->session->get('invitation_flow');
        $lang = $flow['language'] ?? 'cs';
        
        // Load existing visitors
        $existing_visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d 
             ORDER BY created_at ASC",
            $this->visit_id
        ), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/3-visitors-register.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'existing_visitors' => $existing_visitors,
                'lang' => $lang,
                'token' => $this->token,
            ]);
        } else {
            echo '<p>Visitors registration template not found</p>';
        }
    }
    
    /**
     * Render training video step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_training_video() {
        // Use shared template
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/video.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'token' => $this->token,
                'is_invitation' => true,
            ]);
        } else {
            echo '<p>Training video template not found</p>';
        }
    }
    
    /**
     * Render training map step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_training_map() {
        // Use shared template
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/map.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'token' => $this->token,
                'is_invitation' => true,
            ]);
        } else {
            echo '<p>Training map template not found</p>';
        }
    }
    
    /**
     * Render training risks step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_training_risks() {
        // Use shared template
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/risks.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'token' => $this->token,
                'is_invitation' => true,
            ]);
        } else {
            echo '<p>Training risks template not found</p>';
        }
    }
    
    /**
     * Render training department step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_training_department() {
        // Use shared template
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/department.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'token' => $this->token,
                'is_invitation' => true,
            ]);
        } else {
            echo '<p>Training department template not found</p>';
        }
    }
    
    /**
     * Render training additional step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_training_additional() {
        // Use shared template
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/additional.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'token' => $this->token,
                'is_invitation' => true,
            ]);
        } else {
            echo '<p>Training additional template not found</p>';
        }
    }
    
    /**
     * Render PIN success step
     * 
     * @since 1.0.0
     * @return void
     */
    private function render_pin_success() {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $this->visit_id
        ), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/9-pin-success.php';
        if (file_exists($template)) {
            $this->render_template($template, [
                'visit' => $visit,
                'pin' => $visit['pin_code'] ?? '',
                'token' => $this->token,
            ]);
        } else {
            echo '<p>PIN success template not found</p>';
        }
    }
    
    /**
     * Set error message
     * 
     * @since 1.0.0
     * @param string $message Error message
     * @return void
     */
    private function set_error($message) {
        $flow = $this->session->get('invitation_flow');
        $flow['error'] = $message;
        $this->session->set('invitation_flow', $flow);
    }
}

