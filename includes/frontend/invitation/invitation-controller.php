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
        
        // ✅ NOVÉ: Pokud session neexistuje nebo token se změnil, reload
        if (empty($flow) || ($flow['token'] ?? '') !== $this->token) {
            $this->reload_visit_from_token();
            
            // ✅ NOVÉ: Inicializuj session S historií
            $flow = [
                'token' => $this->token,
                'visit_id' => $this->visit_id,
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'company_id' => $this->company_id,
                'step' => 'language',
                'history' => [],                    // ✅ prázdná historie
                'completed_steps' => [],            // ✅ žádné dokončené kroky
                'language_locked' => false,         // ✅ jazyk není zamčený
                'created_at' => time()              // ✅ timestamp vytvoření
            ];
            
            $this->session->set('invitation_flow', $flow);
        } else {
            // Session existuje, načti z ní
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

error_log("=== RELOAD_VISIT_FROM_TOKEN DEBUG ===");
    error_log("Token: " . $this->token);
    error_log("URL: " . $_SERVER['REQUEST_URI']);
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits 
             WHERE invitation_token = %s 
             AND invitation_token_expires_at > NOW()
             AND status IN ('pending', 'draft', 'confirmed', 'in_progress')",
            $this->token
        ), ARRAY_A);
        
error_log("Visit found: " . ($visit ? "YES (ID: {$visit['id']})" : "NO"));
    if ($visit) {
        error_log("Expires at: " . $visit['invitation_token_expires_at']);
        error_log("Current time: " . current_time('mysql'));
        error_log("Status: " . $visit['status']);
    } else {
        error_log("Last SQL: " . $wpdb->last_query);
        error_log("SQL Error: " . $wpdb->last_error);
    }


        if (!$visit) {
            wp_die('Visit not found or expired', 'Error', ['response' => 404]);
        }
        
        $this->visit_id = $visit['id'];
        $this->customer_id = $visit['customer_id'];
        $this->branch_id = $visit['branch_id'];
        $this->company_id = $visit['company_id'] ?? null;
        
        // Flow se inicializuje v init_invitation_session() s historií
        // Tato metoda se volá pouze když je potřeba reload z tokenu
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
    
    // 1. Pokud není jazyk, vždy jdi na language
    if (empty($flow['language'])) {
        return 'language';
    }
    
    // 2. Pokud je ?step= v URL, použij ho (má přednost)
    $step = $_GET['step'] ?? '';
    if (!empty($step)) {
        $steps_requiring_language = ['risks', 'visitors', 'training-video', 'training-map', 'training-risks', 'training-department', 'training-oopp', 'training-additional', 'success'];
        if (in_array($step, $steps_requiring_language) && empty($flow['language'])) {
            return 'language';
        }
        return $step;
    }
    
    // 3. ✅ OPRAVA: Načti ze session, ale ověř že je validní
    $session_step = $flow['step'] ?? 'language';
    
    // Debug log
    error_log("[get_current_step] Session step: {$session_step}, URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
    
    return $session_step;
    }
    
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
    
    // ✅ PŘIDAT: pages.css
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
    
    // ✅ NOVÉ: Načti video player pro training kroky
    $video_player_js = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/js/video-player.js';
    if (file_exists($video_player_js)) {
        wp_enqueue_script(
            'saw-video-player',
            $js_dir . 'video-player.js',
            ['jquery'],
            '3.0.0',
            true  // V footeru
        );
    }
    
    // ✅ Load invitation autosave script
    $autosave_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/invitation/invitation-autosave.js';
    if (file_exists($autosave_js)) {
        wp_enqueue_script(
            'saw-invitation-autosave',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/invitation/invitation-autosave.js',
            ['jquery'],
            filemtime($autosave_js),
            true  // V footeru
        );
    }
    
    // ✅ Localize script with autosave nonce and current step
    wp_localize_script('jquery', 'sawInvitation', [
        'token' => $this->token,
        'ajaxurl' => admin_url('admin-ajax.php'),
        'clearNonce' => wp_create_nonce('saw_clear_invitation_session'),
        'autosaveNonce' => wp_create_nonce('saw_invitation_autosave'),  // ✅ PŘIDÁNO
        'currentStep' => $this->current_step,  // ✅ PŘIDÁNO - pro autosave logiku
    ]);
}
    
    public function render() {
    $flow = $this->session->get('invitation_flow', []);
    $this->current_step = $this->get_current_step();
    
    // ✅ KRITICKÝ DEBUG
    error_log("=== RENDER DEBUG ===");
    error_log("Session step: " . ($flow['step'] ?? 'N/A'));
    error_log("Current step: " . $this->current_step);
    error_log("Language: " . ($flow['language'] ?? 'N/A'));
    error_log("URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
    
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
    
    // ✅ DEBUG PŘED SWITCH
    error_log("About to render step: " . $this->current_step);
    
    switch ($this->current_step) {
        case 'language':
            error_log("Rendering: language");
            $this->render_language_selection();
            break;
        case 'risks':
            error_log("Rendering: risks");
            $this->render_risks_upload();
            break;
        case 'visitors':
            error_log("Rendering: visitors");
            $this->render_visitors_registration();
            break;
        case 'training-video':
            error_log("Rendering: training-video");
            $this->render_training_video();
            break;
        case 'training-map':
            error_log("Rendering: training-map");
            $this->render_training_map();
            break;
        case 'training-risks':
            error_log("Rendering: training-risks");
            $this->render_training_risks();
            break;
        case 'training-department':
            error_log("Rendering: training-department");
            $this->render_training_department();
            break;
        case 'training-oopp':
            error_log("Rendering: training-oopp");
            $this->render_training_oopp();
            break;
        case 'training-additional':
            error_log("Rendering: training-additional");
            $this->render_training_additional();
            break;
        case 'summary':
            error_log("Rendering: summary");
            $this->render_summary();
            break;
        case 'success':
            error_log("Rendering: success");
            $this->render_pin_success();
            break;
        default:
            error_log("Rendering: default (language)");
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
            case 'go_back':  // ✅ NOVÝ case
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
     * 
     * Vrátí uživatele na předchozí krok v historii.
     * Neruší žádná data, pouze mění current_step.
     * 
     * @since 2.0.0
     */
    private function handle_go_back() {
        // Verify nonce
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $flow = $this->session->get('invitation_flow');
        $history = $flow['history'] ?? [];
        
        // Nelze jít zpět pokud historie má méně než 2 kroky
        if (count($history) < 2) {
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/'));
            exit;
        }
        
        // Odeber poslední krok z historie (current step)
        array_pop($history);
        
        // Poslední krok v historii je předchozí krok
        $previous_step = end($history);
        
        // Update session
        $flow['step'] = $previous_step;
        $flow['history'] = $history;
        $this->session->set('invitation_flow', $flow);
        
        // Log
        if (class_exists('SAW_Logger')) {
            SAW_Logger::debug(
                "Back navigation: {$this->current_step} → {$previous_step}, visit #{$this->visit_id}"
            );
        }
        
        // Redirect
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $previous_step));
        exit;
    }
    
    /**
     * Check if user can navigate back
     * 
     * @since 2.0.0
     * @return bool True pokud lze jít zpět
     */
    private function can_go_back() {
        $flow = $this->session->get('invitation_flow');
        $history = $flow['history'] ?? [];
        
        // Nelze zpět z language (první krok) nebo pokud historie je prázdná
        return count($history) > 1 && $this->current_step !== 'language';
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
        
        // ✅ NOVÉ: Přidat do historie (pokud tam ještě není)
        if (!in_array('language', $flow['history'] ?? [])) {
            $flow['history'][] = 'language';
        }
        
        // ✅ NOVÉ: Označit jako dokončený
        if (!in_array('language', $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = 'language';
        }
        
        // ✅ NOVÉ: Zamknout jazyk po prvním výběru
        $flow['language_locked'] = true;
        
        // Existující kód
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
            
            // ✅ NOVÉ: Přidat do historie i při skip (ale ne do completed_steps)
            if (!in_array('risks', $flow['history'] ?? [])) {
                $flow['history'][] = 'risks';
            }
            // Skip NEPŘIDÁVÁ do completed_steps
            
            $flow['step'] = 'visitors';
            $this->session->set('invitation_flow', $flow);
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
            exit;
        }
        
        $risks_text = wp_kses_post($_POST['risks_text'] ?? '');
        $delete_files = $_POST['delete_files'] ?? [];
        
        if (!empty($risks_text)) {
    // ✅ Zkontroluj jestli už existuje text pro tento visit
    $existing_text_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}saw_visit_invitation_materials 
         WHERE visit_id = %d AND material_type = 'text'
         ORDER BY uploaded_at DESC LIMIT 1",
        $this->visit_id
    ));
    
    if ($existing_text_id) {
        // ✅ UPDATE existujícího záznamu
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
        // ✅ INSERT nového záznamu (první save)
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
    'customer_id' => $this->customer_id,  // ✅ PŘIDÁNO
    'branch_id' => $this->branch_id,      // ✅ PŘIDÁNO
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
        
        // ✅ NOVÉ: Přidat do historie (pokud tam ještě není)
        if (!in_array('risks', $flow['history'] ?? [])) {
            $flow['history'][] = 'risks';
        }
        
        // ✅ NOVÉ: Označit jako dokončený
        if (!in_array('risks', $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = 'risks';
        }
        
        $flow['step'] = 'visitors';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
        exit;
    }
    
    private function handle_save_visitors() {
    // 1. Verify nonce
    if (!isset($_POST['invitation_nonce']) || 
        !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
        wp_die('Invalid nonce', 'Error', ['response' => 403]);
    }
    
    global $wpdb;
    
    // ✅ DEBUG
    error_log("=== HANDLE_SAVE_VISITORS DEBUG ===");
    error_log("POST data: " . json_encode($_POST));
    
    // ✅ OPRAVA: Čti správná pole z formuláře
    $existing_visitor_ids = $_POST['existing_visitor_ids'] ?? [];
    $new_visitors = $_POST['new_visitors'] ?? [];
    $training_skip = $_POST['training_skip'] ?? [];
    
    error_log("Existing IDs: " . json_encode($existing_visitor_ids));
    error_log("New visitors: " . json_encode($new_visitors));
    
    // ✅ OPRAVA: Validace - musí být alespoň JEDEN visitor
    if (empty($existing_visitor_ids) && empty($new_visitors)) {
        $flow = $this->session->get('invitation_flow');
        $flow['error'] = 'Please add at least one visitor';
        $this->session->set('invitation_flow', $flow);
        
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=visitors'));
        exit;
    }
    
    // ✅ ZMĚNA: NEMAŽ visitors, jen přidej nové!
    // (Existing visitors už v DB jsou, nepřevytvářej je)
    
    $visitor_ids = [];
    
    // ✅ 3. Existing visitors - POUZE přidej jejich IDs do listu
    if (!empty($existing_visitor_ids)) {
        foreach ($existing_visitor_ids as $id) {
            $id = intval($id);
            if ($id > 0) {
                $visitor_ids[] = $id;
                
                // ✅ Update training_skipped pokud je v POST
                $training_skipped = isset($training_skip[$id]) && $training_skip[$id] === '1';
                
                $wpdb->update(
                    $wpdb->prefix . 'saw_visitors',
                    [
                        'training_skipped' => $training_skipped ? 1 : 0,
                        'training_status' => $training_skipped ? 'skipped' : 'pending',
                    ],
                    ['id' => $id],
                    ['%d', '%s'],
                    ['%d']
                );
            }
        }
    }
    
    // ✅ 4. Přidej POUZE nové visitors
    if (!empty($new_visitors) && is_array($new_visitors)) {
        foreach ($new_visitors as $visitor) {
            // Validace - musí mít alespoň jméno a příjmení
            if (empty($visitor['first_name']) || empty($visitor['last_name'])) {
                continue; // Přeskoč neúplné visitors
            }
            
            $training_skipped = isset($visitor['training_skip']) && $visitor['training_skip'] === '1';
            
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
                    'training_skipped' => $training_skipped ? 1 : 0,
                    'training_status' => $training_skipped ? 'skipped' : 'pending',
                ]
            );
            
            if ($wpdb->insert_id) {
                $visitor_ids[] = $wpdb->insert_id;
                error_log("Inserted new visitor ID: " . $wpdb->insert_id);
            }
        }
    }
    
    // ✅ 5. Update visit status
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        [
            'status' => 'pending',
            'invitation_confirmed_at' => current_time('mysql')
        ],
        ['id' => $this->visit_id]
    );
    
    // ✅ 6. Session tracking
    $flow = $this->session->get('invitation_flow');
    
    // Přidej do historie
    if (!in_array('visitors', $flow['history'] ?? [])) {
        $flow['history'][] = 'visitors';
    }
    
    // Označit jako dokončený
    if (!in_array('visitors', $flow['completed_steps'] ?? [])) {
        $flow['completed_steps'][] = 'visitors';
    }
    
    // ✅ 7. Rozhodnutí kam dál - zkontroluj training
    $has_training = $this->has_training_content();
    $needs_training = false;
    
    error_log("Has training: " . ($has_training ? 'YES' : 'NO'));
    
    if ($has_training) {
        // Zkontroluj jestli NĚKDO z visitors potřebuje training
        foreach ($visitor_ids as $id) {
            $visitor = $wpdb->get_row($wpdb->prepare(
                "SELECT training_skipped FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
                $id
            ));
            
            if ($visitor && !$visitor->training_skipped) {
                $needs_training = true;
                error_log("Visitor #{$id} needs training");
                break;
            }
        }
    }
    
    error_log("Needs training: " . ($needs_training ? 'YES' : 'NO'));
    
    // ✅ 8. Invalidate cache
    if (class_exists('SAW_Cache')) {
        SAW_Cache::delete('invitation_visit_' . $this->visit_id, 'invitations');
    }
    
    // ✅ 9. Redirect
    if ($needs_training) {
        // Získej dostupné kroky
        $available_steps = $this->get_available_training_steps();
        
        if (!empty($available_steps)) {
            // Přejdi na PRVNÍ dostupný krok
            $first_step = $available_steps[0]['step'];
            $flow['step'] = $first_step;
            $flow['available_training_steps'] = $available_steps; // Ulož pro pozdější použití
            $this->session->set('invitation_flow', $flow);
            error_log("[Invitation] Redirecting to first available training step: {$first_step}");
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $first_step));
        } else {
            // Žádný training obsah - přejdi na summary
            $flow['step'] = 'summary';
            $this->session->set('invitation_flow', $flow);
            error_log("[Invitation] No training content - redirecting to summary");
            wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=summary'));
        }
    } else {
        $flow['step'] = 'summary';
        $this->session->set('invitation_flow', $flow);
        error_log("Redirecting to: summary");
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=summary'));
    }
    
    exit;
}
    
    /**
 * Get available training steps based on actual content
 * 
 * Returns only steps that have content in database for the SELECTED language.
 * NO FALLBACK - if content doesn't exist for selected language, step is skipped.
 *
 * @since 2.1.0
 * @return array Array of available training steps with their step names
 */
private function get_available_training_steps() {
    global $wpdb;
    
    $steps = [];
    
    // ✅ OPRAVA: Session není dostupná přes $this v šabloně
// Jazyk získáme z $visit nebo z globální session
if (!class_exists('SAW_Session_Manager')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
}
$session = SAW_Session_Manager::instance();
$flow = $session->get('invitation_flow');
$lang = $flow['language'] ?? 'cs';
    
    error_log("[Invitation] get_available_training_steps() - Language: {$lang}, Customer: {$this->customer_id}, Branch: {$this->branch_id}");
    
    // 1. Získej language_id pro ZVOLENÝ jazyk
    $language_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}saw_training_languages 
         WHERE customer_id = %d AND language_code = %s",
        $this->customer_id,
        $lang
    ));
    
    if (!$language_id) {
        error_log("[Invitation] No language_id found for: {$lang} - skipping all training");
        return $steps;  // ✅ Prázdné = přeskoč všechno training
    }
    
    error_log("[Invitation] Found language_id: {$language_id}");
    
    // 2. Načti training content pro ZVOLENÝ jazyk
    $content = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_training_content 
         WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
        $this->customer_id,
        $this->branch_id,
        $language_id
    ), ARRAY_A);
    
    if (!$content) {
        error_log("[Invitation] No training content for language: {$lang} - skipping all training");
        return $steps;  // ✅ Prázdné = přeskoč všechno training
    }
    
    $content_id = $content['id'];
    error_log("[Invitation] Found training content ID: {$content_id}");
    
    // 3. Video - kontroluj video_url
    if (!empty($content['video_url'])) {
        $steps[] = [
            'type' => 'video',
            'step' => 'training-video',
            'has_content' => true
        ];
        error_log("[Invitation] + Video step available");
    }
    
    // 4. Map - kontroluj pdf_map_path
    if (!empty($content['pdf_map_path'])) {
        $steps[] = [
            'type' => 'map', 
            'step' => 'training-map',
            'has_content' => true
        ];
        error_log("[Invitation] + Map step available");
    }
    
    // 5. Risks - kontroluj risks_text
    if (!empty($content['risks_text'])) {
        $steps[] = [
            'type' => 'risks',
            'step' => 'training-risks', 
            'has_content' => true
        ];
        error_log("[Invitation] + Risks step available");
    }
    
    // 6. Department - kontroluj training_department_content tabulku
    $dept_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_department_content 
         WHERE training_content_id = %d AND (text_content IS NOT NULL AND text_content != '')",
        $content_id
    ));
    
    // Nebo zkontroluj dokumenty pro departments
    $dept_docs = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents 
         WHERE document_type = 'department' 
         AND customer_id = %d AND branch_id = %d",
        $this->customer_id,
        $this->branch_id
    ));
    
    if ($dept_count > 0 || $dept_docs > 0) {
        $steps[] = [
            'type' => 'department',
            'step' => 'training-department',
            'has_content' => true
        ];
        error_log("[Invitation] + Department step available");
    }
    
    // 6.5 OOPP - kontroluj zda existují OOPP pro hosty/branch
    if (class_exists('SAW_OOPP_Public')) {
        $has_oopp = SAW_OOPP_Public::has_oopp($this->customer_id, $this->branch_id, $this->visit_id);
        if ($has_oopp) {
            $steps[] = [
                'type' => 'oopp',
                'step' => 'training-oopp',
                'has_content' => true
            ];
            error_log("[Invitation] + OOPP step available");
        }
    }
    
    // 7. Additional - kontroluj additional_text NEBO dokumenty
    $has_additional_text = !empty($content['additional_text']);
    $additional_docs = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents 
         WHERE document_type = 'additional' AND reference_id = %d",
        $content_id
    ));
    
    if ($has_additional_text || $additional_docs > 0) {
        $steps[] = [
            'type' => 'additional',
            'step' => 'training-additional',
            'has_content' => true
        ];
        error_log("[Invitation] + Additional step available");
    }
    
    error_log("[Invitation] Total available training steps: " . count($steps));
    
    return $steps;
}
    
    /**
     * Get best available language for training content
     * 
     * Fallback logika: zvolený jazyk → EN → CS
     * 
     * @since 2.0.0
     * @param string $preferred_lang Preferovaný jazyk (např. 'es', 'de')
     * @return string Nejlepší dostupný jazyk ('es', 'en', nebo 'cs')
     */
    private function get_best_available_language($preferred_lang) {
        global $wpdb;
        
        if (!$this->customer_id || !$this->branch_id) {
            return 'cs'; // Fallback
        }
        
        // Pokus 1: Existuje obsah v preferovaném jazyce?
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT tc.id 
             FROM {$wpdb->prefix}saw_training_content tc
             INNER JOIN {$wpdb->prefix}saw_training_languages tl ON tc.language_id = tl.id
             WHERE tc.customer_id = %d 
             AND tc.branch_id = %d 
             AND tl.language_code = %s
             LIMIT 1",
            $this->customer_id,
            $this->branch_id,
            $preferred_lang
        ));
        
        if ($content) {
            return $preferred_lang; // Našli jsme preferovaný
        }
        
        // Pokus 2: Fallback na angličtinu
        if ($preferred_lang !== 'en') {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT tc.id 
                 FROM {$wpdb->prefix}saw_training_content tc
                 INNER JOIN {$wpdb->prefix}saw_training_languages tl ON tc.language_id = tl.id
                 WHERE tc.customer_id = %d 
                 AND tc.branch_id = %d 
                 AND tl.language_code = 'en'
                 LIMIT 1",
                $this->customer_id,
                $this->branch_id
            ));
            
            if ($content) {
                return 'en'; // Našli jsme angličtinu
            }
        }
        
        // Pokus 3: Fallback na češtinu (poslední možnost)
        return 'cs';
    }
    
    /**
     * Check if any training content exists
     * 
     * @return bool
     */
    private function has_training_content() {
        // Použij novou metodu - pokud vrátí alespoň 1 krok, máme obsah
        $available_steps = $this->get_available_training_steps();
        return !empty($available_steps);
    }
    
    private function render_header() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/layout-header.php';
        if (file_exists($template)) {
            require $template;
        }
        
        // ✅ NOVÉ: Přidat progress indicator (zobrazit i na success page pro navigaci zpět)
        // Progress indicator se zobrazuje na všech stránkách včetně success
        // ✅ OPRAVA: Vypočítej všechny potřebné hodnoty TADY (kde máme $this)
        $has_training = $this->has_training_content();
        $flow = $this->session->get('invitation_flow');
        $current_step = $this->current_step;
        $token = $this->token;
        
        // ✅ NOVÉ: Předej seznam dostupných training kroků
        $available_training_steps = $has_training ? $this->get_available_training_steps() : [];
        
        // Předej jako proměnné do template
        $progress = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/components/progress-indicator.php';
        if (file_exists($progress)) {
            require $progress;  // $has_training, $flow, $current_step, $token, $available_training_steps jsou teď dostupné v template
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
    // ✅ Zkontroluj zda tento krok má obsah
    $available_steps = $this->get_available_training_steps();
    $has_video_step = false;
    foreach ($available_steps as $step) {
        if ($step['type'] === 'video') {
            $has_video_step = true;
            break;
        }
    }
    
    if (!$has_video_step) {
        // Tento krok nemá obsah - přeskoč na další
        error_log("[Invitation] Video step has no content, skipping...");
        $this->skip_to_next_available_step('training-video');
        return;
    }
    
    // ✅ ŽÁDNÝ FALLBACK - použij přímo zvolený jazyk
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/video.php';
    if (file_exists($template)) {
        $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
    }
}
    
    private function render_training_map() {
    // ✅ Zkontroluj zda tento krok má obsah
    $available_steps = $this->get_available_training_steps();
    $has_map_step = false;
    foreach ($available_steps as $step) {
        if ($step['type'] === 'map') {
            $has_map_step = true;
            break;
        }
    }
    
    if (!$has_map_step) {
        // Tento krok nemá obsah - přeskoč na další
        error_log("[Invitation] Map step has no content, skipping...");
        $this->skip_to_next_available_step('training-map');
        return;
    }
    
    // ✅ ŽÁDNÝ FALLBACK - použij přímo zvolený jazyk
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/map.php';
    if (file_exists($template)) {
        $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
    }
}
    
    private function render_training_risks() {
    // ✅ Zkontroluj zda tento krok má obsah
    $available_steps = $this->get_available_training_steps();
    $has_risks_step = false;
    foreach ($available_steps as $step) {
        if ($step['type'] === 'risks') {
            $has_risks_step = true;
            break;
        }
    }
    
    if (!$has_risks_step) {
        // Tento krok nemá obsah - přeskoč na další
        error_log("[Invitation] Risks step has no content, skipping...");
        $this->skip_to_next_available_step('training-risks');
        return;
    }
    
    // ✅ ŽÁDNÝ FALLBACK - použij přímo zvolený jazyk
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/risks.php';
    if (file_exists($template)) {
        $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
    }
}
    
    private function render_training_department() {
    // ✅ Zkontroluj zda tento krok má obsah
    $available_steps = $this->get_available_training_steps();
    $has_department_step = false;
    foreach ($available_steps as $step) {
        if ($step['type'] === 'department') {
            $has_department_step = true;
            break;
        }
    }
    
    if (!$has_department_step) {
        // Tento krok nemá obsah - přeskoč na další
        error_log("[Invitation] Department step has no content, skipping...");
        $this->skip_to_next_available_step('training-department');
        return;
    }
    
    // ✅ ŽÁDNÝ FALLBACK - použij přímo zvolený jazyk
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/department.php';
    if (file_exists($template)) {
        $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
    }
}
    
    /**
     * Render training OOPP step
     * 
     * @since 3.0.0
     */
    private function render_training_oopp() {
        // Zkontroluj zda tento krok má obsah
        $available_steps = $this->get_available_training_steps();
        $has_oopp_step = false;
        foreach ($available_steps as $step) {
            if ($step['type'] === 'oopp') {
                $has_oopp_step = true;
                break;
            }
        }
        
        if (!$has_oopp_step) {
            error_log("[Invitation] OOPP step has no content, skipping...");
            $this->skip_to_next_available_step('training-oopp');
            return;
        }
        
        // Načti OOPP items
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
    
    private function render_training_additional() {
    // ✅ Zkontroluj zda tento krok má obsah
    $available_steps = $this->get_available_training_steps();
    $has_additional_step = false;
    foreach ($available_steps as $step) {
        if ($step['type'] === 'additional') {
            $has_additional_step = true;
            break;
        }
    }
    
    if (!$has_additional_step) {
        // Tento krok nemá obsah - přeskoč na další
        error_log("[Invitation] Additional step has no content, skipping...");
        $this->skip_to_next_available_step('training-additional');
        return;
    }
    
    // ✅ ŽÁDNÝ FALLBACK - použij přímo zvolený jazyk
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/additional.php';
    if (file_exists($template)) {
        $this->render_template($template, ['token' => $this->token, 'is_invitation' => true]);
    }
}
    
    /**
     * Skip to next available training step or summary
     * 
     * @param string $current_step Current step being skipped
     */
    private function skip_to_next_available_step($current_step) {
        $flow = $this->session->get('invitation_flow');
        $available_steps = $this->get_available_training_steps();
        
        // Najdi index aktuálního kroku (pokud existuje)
        $current_index = -1;
        foreach ($available_steps as $index => $step) {
            if ($step['step'] === $current_step) {
                $current_index = $index;
                break;
            }
        }
        
        // Pokud aktuální krok není v seznamu, najdi první dostupný po něm
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
            $flow['history'][] = $current_step; // Označ jako navštívený (přeskočený)
        }
        
        $this->session->set('invitation_flow', $flow);
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $next_step));
        exit;
    }
    
    /**
     * Handle complete training action
     * Redirects to next training step or summary
     */
    private function handle_complete_training() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        global $wpdb;
        $flow = $this->session->get('invitation_flow');
        $current = $this->current_step;
        
        // Mark current step as completed
        if (!in_array($current, $flow['completed_steps'] ?? [])) {
            $flow['completed_steps'][] = $current;
        }
        if (!in_array($current, $flow['history'] ?? [])) {
            $flow['history'][] = $current;
        }
        
        // ✅ NOVÁ LOGIKA: Získej dostupné kroky a najdi další
        $available_steps = $this->get_available_training_steps();
        
        // Najdi aktuální krok v seznamu dostupných
        $current_index = -1;
        foreach ($available_steps as $index => $step) {
            if ($step['step'] === $current) {
                $current_index = $index;
                break;
            }
        }
        
        // Přejdi na další dostupný krok, nebo na summary
        if ($current_index !== -1 && isset($available_steps[$current_index + 1])) {
            $next_step = $available_steps[$current_index + 1]['step'];
            $flow['step'] = $next_step;
            error_log("[Invitation] Moving to next training step: {$next_step}");
        } else {
            // Všechny kroky hotové nebo aktuální nebyl nalezen
            $flow['step'] = 'summary';
            error_log("[Invitation] Training complete, moving to summary");
        }
        
        $this->session->set('invitation_flow', $flow);
        wp_redirect(home_url('/visitor-invitation/' . $this->token . '/?step=' . $flow['step']));
        exit;
    }
    
    /**
     * Handle skip training action
     * Redirects to summary
     */
    private function handle_skip_training() {
        if (!isset($_POST['invitation_nonce']) || 
            !wp_verify_nonce($_POST['invitation_nonce'], 'saw_invitation_step')) {
            wp_die('Invalid nonce', 'Error', ['response' => 403]);
        }
        
        $flow = $this->session->get('invitation_flow');
        
        // Mark all training steps as skipped
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
    
    // Načíst visit data
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $this->visit_id
    ), ARRAY_A);
    
    // Načíst branch data (adresa)
    $branch = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
        $this->branch_id
    ), ARRAY_A);
    
    // Načíst company data (navštěvovaná firma)
    $company = null;
    if (!empty($visit['company_id'])) {
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_companies WHERE id = %d",
            $visit['company_id']
        ), ARRAY_A);
    }
    
    // ✅ NOVÉ: Načíst schedule data (datum a čas)
    // ✅ OPRAVA: Načíst VŠECHNY schedule záznamy
$schedules = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}saw_visit_schedules 
     WHERE visit_id = %d 
     ORDER BY sort_order ASC, date ASC",
    $this->visit_id
), ARRAY_A);
    
    // Načíst hosty (kontaktní osoby) - z SAW users tabulky
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
    
    // Načíst visitors
    $visitors = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visitors 
         WHERE visit_id = %d AND participation_status != 'cancelled'
         ORDER BY id ASC",
        $this->visit_id
    ), ARRAY_A);
    
    // Načíst risks materials
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
    
    // PIN kód
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
        
        // Přidat summary do completed
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
    
    private function render_pin_success() {
    global $wpdb;
    
    error_log("=== RENDER_PIN_SUCCESS DEBUG ===");
    error_log("Visit ID: " . $this->visit_id);
    
    // ✅ Update status to confirmed when invitation is completed
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
    
    error_log("[Invitation] Visit #{$this->visit_id} status updated to 'confirmed'");
    
    $visit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d", $this->visit_id), ARRAY_A);
    
    error_log("Visit found: " . ($visit ? 'YES' : 'NO'));
    if ($visit) {
        error_log("PIN code: " . ($visit['pin_code'] ?? 'N/A'));
        error_log("Visit data: " . json_encode($visit));
    }
    
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/9-pin-success.php';
    
    error_log("Template path: " . $template);
    error_log("Template exists: " . (file_exists($template) ? 'YES' : 'NO'));
    
    if (file_exists($template)) {
        error_log("About to render template with PIN: " . ($visit['pin_code'] ?? 'N/A'));
        $this->render_template($template, ['visit' => $visit, 'pin' => $visit['pin_code'] ?? '', 'token' => $this->token]);
    } else {
        error_log("ERROR: Template does NOT exist!");
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