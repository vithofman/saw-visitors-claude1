<?php
/**
 * Terminal Frontend Controller
 *
 * Main routing and session management for visitor terminal.
 * Handles multi-step check-in/out flow with language support.
 *
 * CHANGES v3.0:
 * - ✅ Implementována handle_registration_submission() - kompletní walk-in registrace
 * - ✅ Vytváření visit + visitor + hosts + daily_log
 * - ✅ find_or_create_company() integrace
 *
 * @package    SAW_Visitors
 * @subpackage Frontend/Terminal
 * @since      1.0.0
 * @version    3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Terminal Controller Class
 *
 * @since 1.0.0
 */
class SAW_Terminal_Controller {
    
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
     * Available languages (loaded from DB)
     *
     * @var array
     */
    private $languages = [];
    
    /**
     * Current customer ID
     *
     * @var int
     */
    private $customer_id;
    
    /**
     * Current branch ID
     *
     * @var int
     */
    private $branch_id;
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->session = SAW_Session_Manager::instance();
        
        // ✅ Získání customer_id a branch_id
        $this->load_context();
        
        // ✅ Načtení jazyků z DB
        $this->load_languages();
        
        $this->init_terminal_session();
        $this->current_step = $this->get_current_step();
    }
    
    /**
     * Load customer and branch context
     *
     * Logika:
     * 1. Terminal role → má pevné branch_id a customer_id
     * 2. Super admin → použije context_customer_id a context_branch_id
     * 3. Admin/Manager → použije své branch_id a customer_id
     *
     * @since 2.0.0
     * @return void
     */
    private function load_context() {
        global $wpdb;
        
        if (!is_user_logged_in()) {
            wp_die('Musíte být přihlášeni pro přístup k terminálu.', 'Přístup odepřen', ['response' => 403]);
        }
        
        $wp_user_id = get_current_user_id();
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                customer_id,
                branch_id,
                context_customer_id,
                context_branch_id,
                role
             FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d",
            $wp_user_id
        ));
        
        if (!$saw_user) {
            wp_die('SAW uživatel nenalezen.', 'Chyba', ['response' => 500]);
        }
        
        // ✅ Logika podle role
        switch ($saw_user->role) {
            case 'super_admin':
                // Super admin používá context (customer switcher)
                $this->customer_id = $saw_user->context_customer_id ?? $saw_user->customer_id;
                $this->branch_id = $saw_user->context_branch_id ?? $saw_user->branch_id;
                break;
                
            case 'terminal':
                // Terminal má pevné branch_id
                $this->customer_id = $saw_user->customer_id;
                $this->branch_id = $saw_user->branch_id;
                break;
                
            default:
                // Admin, super_manager, manager
                $this->customer_id = $saw_user->customer_id;
                $this->branch_id = $saw_user->context_branch_id ?? $saw_user->branch_id;
                break;
        }
        
        if (!$this->customer_id || !$this->branch_id) {
            wp_die(
                'Chybí kontext zákazníka nebo pobočky. Ujistěte se, že máte vybraný zákazník (super admin) nebo přiřazenou pobočku.',
                'Chyba konfigurace',
                ['response' => 500]
            );
        }
    }
    
    /**
     * Load available languages from database
     *
     * Načte pouze jazyky:
     * 1. Pro aktuálního zákazníka (customer_id)
     * 2. Aktivní pro aktuální pobočku (branch_id)
     * 3. Seřazené podle display_order
     *
     * @since 2.0.0
     * @return void
     */
    private function load_languages() {
        global $wpdb;
        
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
        
        // Vytvoření pole pro template: ['cs' => 'Čeština', 'en' => 'English']
        $this->languages = [];
        foreach ($results as $row) {
            $this->languages[$row->language_code] = [
                'name' => $row->language_name,
                'flag' => $row->flag_emoji,
                'is_default' => (bool) $row->is_default,
            ];
        }
        
        // ✅ Fallback pokud nejsou žádné jazyky
        if (empty($this->languages)) {
            error_log("[SAW Terminal] WARNING: No languages found for customer #{$this->customer_id}, branch #{$this->branch_id}");
            
            // Hardcoded fallback - čeština
            $this->languages = [
                'cs' => [
                    'name' => 'Čeština',
                    'flag' => '🇨🇿',
                    'is_default' => true,
                ],
            ];
        }
    }
    
    /**
     * Initialize terminal session structure
     *
     * Creates session variables if they don't exist
     *
     * @since 1.0.0
     * @return void
     */
    private function init_terminal_session() {
        if (!$this->session->has('terminal_flow')) {
            $this->session->set('terminal_flow', [
                'step' => 'language',
                'language' => null,
                'action' => null, // checkin|checkout
                'type' => null, // planned|walkin (for checkin)
                'pin' => null,
                'visit_id' => null,
                'visitor_ids' => [],
                'data' => [],
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
            ]);
        }
    }
    
    /**
     * Get current step from session and URL
     *
     * @since 1.0.0
     * @return string Current step identifier
     */
    private function get_current_step() {
        $path = get_query_var('saw_path');
        
        // ✅ If URL is exactly /terminal/ (home button), always return 'language'
        if (empty($path) || $path === '/' || $path === 'terminal') {
            return 'language';
        }
        
        // Parse step from URL: /terminal/checkin/register -> step = 'register'
        if (!empty($path)) {
            $segments = explode('/', trim($path, '/'));
            
            // /terminal/checkin -> step = 'checkin'
            if (count($segments) >= 1) {
                return $segments[count($segments) - 1];
            }
        }
        
        // Default: language selection
        $flow = $this->session->get('terminal_flow');
        return $flow['step'] ?? 'language';
    }
    
    /**
     * Main render method
     *
     * Routes to appropriate step handler
     *
     * @since 1.0.0
     * @return void
     */
    public function render() {
        // ✅ DEBUG - zjistíme co se děje
        $path = get_query_var('saw_path');
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        error_log("=== TERMINAL RENDER DEBUG ===");
        error_log("REQUEST_URI: {$request_uri}");
        error_log("saw_path query var: '{$path}'");
        error_log("current_step: {$this->current_step}");
        
        $flow = $this->session->get('terminal_flow');
        error_log("Flow data: " . print_r($flow, true));
        
        // ✅ Reset flow when returning to language step via home button
        if ($this->current_step === 'language') {
            // If we have progress but we're back at language, reset
            if (!empty($flow['language']) || !empty($flow['action'])) {
                error_log("RESETTING FLOW - user returned to language step");
                $this->reset_flow();
            }
        }
        
        // Enqueue terminal assets
        $this->enqueue_assets();
        
        // Handle POST submissions first
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_post();
            return;
        }
        
        // Render appropriate step
        error_log("=== ROUTING TO STEP: {$this->current_step} ===");
        
        switch ($this->current_step) {
            case 'language':
                error_log("Rendering language selection");
                $this->render_language_selection();
                break;
                
            case 'action':
                error_log("Rendering action choice");
                $this->render_action_choice();
                break;
                
            case 'checkin':
                error_log("Rendering checkin type");
                $this->render_checkin_type();
                break;
                
            case 'checkout':
                error_log("Rendering checkout method");
                $this->render_checkout_method();
                break;
                
            case 'pin-entry':
                error_log("Rendering PIN entry");
                $this->render_pin_entry();
                break;
                
            case 'register':
                error_log("Rendering registration form");
                $this->render_registration_form();
                break;
                
            case 'checkout-pin':
                error_log("Rendering checkout PIN");
                $this->render_checkout_pin();
                break;
                
            case 'checkout-search':
                error_log("Rendering checkout search");
                $this->render_checkout_search();
                break;
                
            case 'success':
                error_log("Rendering success page");
                $this->render_success();
                break;
                
            case 'training-video':
                error_log("Rendering training video");
                $this->render_training_video();
                break;
                
            case 'training-map':
                error_log("Rendering training map");
                $this->render_training_map();
                break;
                
            case 'training-risks':
                error_log("Rendering training risks");
                $this->render_training_risks();
                break;
                
            case 'training-department':
                error_log("Rendering training department");
                $this->render_training_department();
                break;
                
            case 'training-additional':
                error_log("Rendering training additional");
                $this->render_training_additional();
                break;
                
            default:
                error_log("DEFAULT CASE - unknown step: {$this->current_step}");
                $this->reset_flow();
                $this->render_language_selection();
                break;
        }
        
        error_log("=== RENDER COMPLETE ===");
    }
    
    /**
     * Render language selection step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_language_selection() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/1-language.php';
        $this->render_template($template, [
            'languages' => $this->languages, // ✅ Nyní z DB
        ]);
    }
    
    /**
     * Render action choice step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_action_choice() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/2-action.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render check-in type selection
     *
     * @since 1.0.0
     * @return void
     */
    private function render_checkin_type() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/3-type.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render checkout method selection
     *
     * @since 1.0.0
     * @return void
     */
    private function render_checkout_method() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/checkout-method.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render PIN entry form
     *
     * @since 1.0.0
     * @return void
     */
    private function render_pin_entry() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/pin-entry.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render registration form
     *
     * @since 1.0.0
     * @return void
     */
    private function render_registration_form() {
        // ✅ Load hosts from database
        global $wpdb;
        
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, position, role
             FROM {$wpdb->prefix}saw_users
             WHERE customer_id = %d 
               AND branch_id = %d
               AND role IN ('admin', 'super_manager', 'manager')
               AND is_active = 1
             ORDER BY last_name ASC, first_name ASC",
            $this->customer_id,
            $this->branch_id
        ), ARRAY_A);
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/4-register.php';
        $this->render_template($template, [
            'hosts' => $hosts, // ✅ Pass hosts to template
        ]);
    }
    
    /**
     * Render checkout PIN form
     *
     * @since 1.0.0
     * @return void
     */
    private function render_checkout_pin() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/checkout/pin.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render checkout search form
     *
     * @since 1.0.0
     * @return void
     */
    private function render_checkout_search() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/checkout/search.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render success page
     *
     * @since 1.0.0
     * @return void
     */
    private function render_success() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/success.php';
        $flow = $this->session->get('terminal_flow');
        $this->render_template($template, [
            'action' => $flow['action'] ?? 'checkin',
        ]);
    }
    
    /**
     * Render training video step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_video() {
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['visit_id'] ?? null;
        $language = $flow['language'] ?? 'cs';
        
        // ✅ OPRAVENO: Načíst fresh training steps z DB (ne ze session)
        $training_steps = $this->get_training_steps($visit_id, $language);
        
        error_log("[SAW Terminal Video] Loaded fresh training steps: " . count($training_steps));
        
        // Najdi aktuální video step
        $step_data = null;
        foreach ($training_steps as $step) {
            if ($step['type'] == 'video') {
                $step_data = $step['data'];
                error_log("[SAW Terminal Video] Found video step with URL: " . $step_data['video_url']);
                break;
            }
        }
        
        if (!$step_data) {
            error_log("[SAW Terminal Video] ERROR: No video step found");
            // Fallback - přejdi na další krok
            $this->move_to_next_training_step();
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/video.php';
        
        $this->render_template($template, [
            'video_url' => $step_data['video_url'],
        ]);
    }
    
    /**
     * Render training map step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_map() {
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['visit_id'] ?? null;
        $language = $flow['language'] ?? 'cs';
        
        // ✅ OPRAVENO: Načíst fresh training steps z DB (ne ze session)
        $training_steps = $this->get_training_steps($visit_id, $language);
        
        error_log("[SAW Terminal Map] Loaded fresh training steps: " . count($training_steps));
        
        // Najdi aktuální map step
        $step_data = null;
        foreach ($training_steps as $step) {
            if ($step['type'] == 'map') {
                $step_data = $step['data'];
                error_log("[SAW Terminal Map] Found map step with PDF: " . $step_data['pdf_path']);
                break;
            }
        }
        
        if (!$step_data) {
            error_log("[SAW Terminal Map] ERROR: No map step found");
            // Fallback - přejdi na další krok
            $this->move_to_next_training_step();
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/map.php';
        
        $this->render_template($template, [
            'pdf_path' => $step_data['pdf_path'],
        ]);
    }
    
    /**
     * Render training risks step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_risks() {
        global $wpdb;
        
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['visit_id'] ?? null;
        $language = $flow['language'] ?? 'cs';
        
        // ✅ Načíst fresh training steps z DB
        $training_steps = $this->get_training_steps($visit_id, $language);
        
        // Najdi risks step
        $step_data = null;
        foreach ($training_steps as $step) {
            if ($step['type'] == 'risks') {
                $step_data = $step['data'];
                break;
            }
        }
        
        if (!$step_data) {
            $this->move_to_next_training_step();
            return;
        }
        
        // ✅ Načíst training_content.id pro načtení dokumentů
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $language
        ));
        
        $content_id = null;
        if ($language_id) {
            $content_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $this->customer_id,
                $this->branch_id,
                $language_id
            ));
        }
        
        // ✅ Načíst dokumenty k risks
        $documents = [];
        if ($content_id) {
            $documents = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                 WHERE document_type = 'risks' AND reference_id = %d 
                 ORDER BY uploaded_at ASC",
                $content_id
            ), ARRAY_A);
        }
        
        error_log("[SAW Terminal Risks] Found " . count($documents) . " documents");
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/risks.php';
        $this->render_template($template, [
            'risks_text' => $step_data['risks_text'],
            'documents' => $documents,
        ]);
    }
    
    /**
     * Render training department step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_department() {
    global $wpdb;
    
    $flow = $this->session->get('terminal_flow');
    $visit_id = $flow['visit_id'] ?? null;
    $language = $flow['language'] ?? 'cs';
    
    // ✅ Načíst fresh training steps z DB
    $training_steps = $this->get_training_steps($visit_id, $language);
    
    // ✅ Najdi VŠECHNY department steps
    $dept_steps = [];
    foreach ($training_steps as $step) {
        if ($step['type'] == 'department') {
            $dept_steps[] = $step['data'];
        }
    }
    
    if (empty($dept_steps)) {
        $this->move_to_next_training_step();
        return;
    }
    
    // ✅ Pro každé oddělení načíst dokumenty
    foreach ($dept_steps as &$dept) {
        $dept_content_id = $dept['department_content_id'] ?? null;
        
        if ($dept_content_id) {
            $documents = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                 WHERE document_type = 'department' AND reference_id = %d 
                 ORDER BY uploaded_at ASC",
                $dept_content_id
            ), ARRAY_A);
            
            $dept['documents'] = $documents;
        } else {
            $dept['documents'] = [];
        }
    }
    
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/department.php';
    $this->render_template($template, [
        'departments' => $dept_steps,
    ]);
}
    
    /**
     * Render training additional step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_additional() {
        global $wpdb;
        
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['visit_id'] ?? null;
        $language = $flow['language'] ?? 'cs';
        
        // ✅ Načíst fresh training steps z DB
        $training_steps = $this->get_training_steps($visit_id, $language);
        
        // Najdi additional step
        $step_data = null;
        foreach ($training_steps as $step) {
            if ($step['type'] == 'additional') {
                $step_data = $step['data'];
                break;
            }
        }
        
        if (!$step_data) {
            $this->move_to_next_training_step();
            return;
        }
        
        // ✅ Načíst training_content.id pro načtení dokumentů
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $language
        ));
        
        $content_id = null;
        if ($language_id) {
            $content_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $this->customer_id,
                $this->branch_id,
                $language_id
            ));
        }
        
        // ✅ Načíst dokumenty pro additional
        $documents = [];
        if ($content_id) {
            $documents = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                 WHERE document_type = 'additional' AND reference_id = %d 
                 ORDER BY uploaded_at ASC",
                $content_id
            ), ARRAY_A);
        }
        
        error_log("[SAW Terminal Additional] Found " . count($documents) . " documents");
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/additional.php';
        $this->render_template($template, [
            'additional_text' => $step_data['additional_text'],
            'documents' => $documents,
        ]);
    }
    
    /**
     * Handle POST submissions
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_post() {
        // Verify nonce
        if (!isset($_POST['terminal_nonce']) || !wp_verify_nonce($_POST['terminal_nonce'], 'saw_terminal_step')) {
            $this->set_error(__('Bezpečnostní kontrola selhala', 'saw-visitors'));
            $this->render();
            return;
        }
        
        $action = $_POST['terminal_action'] ?? '';
        
        switch ($action) {
            case 'set_language':
                $this->handle_language_selection();
                break;
                
            case 'set_action':
                $this->handle_action_choice();
                break;
                
            case 'set_checkin_type':
                $this->handle_checkin_type();
                break;
                
            case 'verify_pin':
                $this->handle_pin_verification();
                break;
                
            case 'submit_registration':
                $this->handle_registration_submission();
                break;
                
            case 'checkout_pin':
                $this->handle_checkout_pin();
                break;
                
            case 'checkout_search':
                $this->handle_checkout_search();
                break;
                
            case 'complete_training_video':
                $this->handle_training_video_complete();
                break;
                
            case 'complete_training_map':
                $this->handle_training_map_complete();
                break;
                
            case 'complete_training_risks':
                $this->handle_training_risks_complete();
                break;
                
            case 'complete_training_department':
                $this->handle_training_department_complete();
                break;
                
            case 'complete_training_additional':
                $this->handle_training_additional_complete();
                break;
                
            default:
                $this->set_error(__('Neplatná akce', 'saw-visitors'));
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
    private function handle_language_selection() {
        $language = sanitize_text_field($_POST['language'] ?? '');
        
        if (!array_key_exists($language, $this->languages)) {
            $this->set_error(__('Neplatný jazyk', 'saw-visitors'));
            $this->render_language_selection();
            return;
        }
        
        $flow = $this->session->get('terminal_flow');
        $flow['language'] = $language;
        $flow['step'] = 'action';
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/action/'));
        exit;
    }
    
    /**
     * Handle action choice (checkin/checkout)
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_action_choice() {
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        if (!in_array($action, ['checkin', 'checkout'])) {
            $this->set_error(__('Neplatná akce', 'saw-visitors'));
            $this->render_action_choice();
            return;
        }
        
        $flow = $this->session->get('terminal_flow');
        $flow['action'] = $action;
        
        if ($action === 'checkin') {
            $flow['step'] = 'checkin';
            wp_redirect(home_url('/terminal/checkin/'));
        } else {
            $flow['step'] = 'checkout';
            wp_redirect(home_url('/terminal/checkout/'));
        }
        
        $this->session->set('terminal_flow', $flow);
        exit;
    }
    
    /**
     * Handle checkin type selection
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkin_type() {
        $type = sanitize_text_field($_POST['checkin_type'] ?? '');
        
        if (!in_array($type, ['planned', 'walkin'])) {
            $this->set_error(__('Neplatný typ návštěvy', 'saw-visitors'));
            $this->render_checkin_type();
            return;
        }
        
        $flow = $this->session->get('terminal_flow');
        $flow['type'] = $type;
        
        if ($type === 'planned') {
            $flow['step'] = 'pin-entry';
            wp_redirect(home_url('/terminal/pin-entry/'));
        } else {
            $flow['step'] = 'register';
            wp_redirect(home_url('/terminal/register/'));
        }
        
        $this->session->set('terminal_flow', $flow);
        exit;
    }
    
    /**
     * Handle PIN verification
     *
     * TODO: Implement actual DB lookup
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_pin_verification() {
        $pin = sanitize_text_field($_POST['pin'] ?? '');
        
        // TODO: Lookup PIN in database
        // $visit = find_visit_by_pin($pin, $this->customer_id);
        
        $this->set_error(__('PIN verifikace ještě není implementována', 'saw-visitors'));
        $this->render_pin_entry();
    }
    
    /**
     * Convert YouTube/Vimeo URL to embed format
     *
     * @param string $url Original URL
     * @return string Embed URL
     */
    private function get_video_embed_url($url) {
        // YouTube - jednoduchá konverze
        if (strpos($url, 'youtube.com') !== false) {
            // https://www.youtube.com/watch?v=VIDEO_ID
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            if (isset($params['v'])) {
                return 'https://www.youtube.com/embed/' . $params['v'];
            }
        }
        
        if (strpos($url, 'youtu.be') !== false) {
            // https://youtu.be/VIDEO_ID
            $path = parse_url($url, PHP_URL_PATH);
            $video_id = trim($path, '/');
            return 'https://www.youtube.com/embed/' . $video_id;
        }
        
        // Vimeo
        if (strpos($url, 'vimeo.com') !== false) {
            $path = parse_url($url, PHP_URL_PATH);
            $video_id = trim($path, '/');
            return 'https://player.vimeo.com/video/' . $video_id;
        }
        
        // Fallback - vrátit původní URL
        return $url;
    }
    
    /**
     * Get training steps for visitor
     * 
     * Načte školící obsah a určí které kroky zobrazit:
     * 1. Video (pokud video_url vyplněno)
     * 2. PDF Mapa (pokud pdf_map_path vyplněno)
     * 3. Rizika (pokud risks_text vyplněno)
     * 4. Další info (pokud additional_text vyplněno)
     * 5. Specifika oddělení (pokud má host oddělení A existuje obsah)
     *
     * @since 3.0.0
     * @param int $visit_id Visit ID
     * @param int $language_code Language code (cs/en/uk)
     * @return array Training steps
     */
    private function get_training_steps($visit_id, $language_code) {
    global $wpdb;
    
    $steps = [];
    
    // 1. Language ID
    $language_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}saw_training_languages 
         WHERE customer_id = %d AND language_code = %s",
        $this->customer_id, $language_code
    ));
    
    if (!$language_id) return $steps;
    
    // 2. Training content
    $content = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_training_content 
         WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
        $this->customer_id, $this->branch_id, $language_id
    ), ARRAY_A);
    
    if (!$content) return $steps;
    
    $content_id = $content['id'];
    
    // 3. Video
    if (!empty($content['video_url'])) {
        $video_url = $content['video_url'];
        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
            preg_match('/(?:v=|youtu\.be\/)([^&]+)/', $video_url, $matches);
            if (!empty($matches[1])) $video_url = 'https://www.youtube.com/embed/' . $matches[1];
        } elseif (strpos($video_url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
            if (!empty($matches[1])) $video_url = 'https://player.vimeo.com/video/' . $matches[1];
        }
        
        $steps[] = ['type' => 'video', 'url' => 'training-video', 'data' => ['video_url' => $video_url]];
    }
    
    // 4. Map
    if (!empty($content['pdf_map_path'])) {
        $steps[] = ['type' => 'map', 'url' => 'training-map', 'data' => ['pdf_path' => $content['pdf_map_path']]];
    }
    
    // 5. Risks
    if (!empty($content['risks_text'])) {
        $steps[] = ['type' => 'risks', 'url' => 'training-risks', 'data' => ['risks_text' => $content['risks_text']]];
    }
    
    // 6. DEPARTMENTS - odvozené z hostů (PŘEDPOSLEDNÍ)
$host_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d", $visit_id
));

if (!empty($host_ids)) {
    $department_ids = [];
    
    foreach ($host_ids as $host_id) {
        $host_dept_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d", $host_id
        ));
        
        // Pokud NULL (admin/super_manager) → všechna oddělení pobočky
        if (empty($host_dept_ids)) {
            $all_dept_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_departments 
                 WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                $this->customer_id, $this->branch_id
            ));
            $department_ids = array_merge($department_ids, $all_dept_ids);
        } else {
            $department_ids = array_merge($department_ids, $host_dept_ids);
        }
    }
    
    $department_ids = array_unique($department_ids);
    
    // Načti content pro každé oddělení
    foreach ($department_ids as $dept_id) {
        $dept_content = $wpdb->get_row($wpdb->prepare(
            "SELECT dc.id as department_content_id, dc.text_content, d.name as department_name
             FROM {$wpdb->prefix}saw_training_department_content dc
             INNER JOIN {$wpdb->prefix}saw_departments d ON dc.department_id = d.id
             WHERE dc.training_content_id = %d AND dc.department_id = %d",
            $content_id, $dept_id
        ), ARRAY_A);
        
        if ($dept_content) {
            $steps[] = [
                'type' => 'department',
                'url' => 'training-department',
                'data' => [
                    'department_id' => $dept_id,
                    'department_name' => $dept_content['department_name'],
                    'text_content' => $dept_content['text_content'] ?? '',
                    'department_content_id' => $dept_content['department_content_id']
                ]
            ];
        }
    }
}

// 7. Additional (POSLEDNÍ KROK)
if (!empty($content['additional_text'])) {
    $steps[] = ['type' => 'additional', 'url' => 'training-additional', 'data' => ['additional_text' => $content['additional_text']]];
}

return $steps;
}
    
    /**
     * Handle registration submission
     *
     * Zpracuje walk-in registraci:
     * 1. Validace formuláře
     * 2. Vytvoření/nalezení company (pokud není fyzická osoba)
     * 3. Vytvoření visit (walk-in, status='in_progress', started_at=NOW)
     * 4. Vytvoření visitor záznamu
     * 5. Přiřazení hostů (visit_hosts)
     * 6. Okamžitý check-in (visit_daily_logs)
     * 7. Načtení školících kroků
     * 8. Redirect na školení nebo success
     *
     * @since 3.0.0
     * @return void
     */
    private function handle_registration_submission() {
        global $wpdb;
        
        // ===================================
        // 1. VALIDACE FORMULÁŘE
        // ===================================
        
        $errors = [];
        
        // Povinná pole
        if (empty($_POST['first_name'])) {
            $errors[] = __('Jméno je povinné', 'saw-visitors');
        }
        
        if (empty($_POST['last_name'])) {
            $errors[] = __('Příjmení je povinné', 'saw-visitors');
        }
        
        // Pokud není fyzická osoba, musí zadat firmu
        $is_individual = isset($_POST['is_individual']) && $_POST['is_individual'] == '1';
        
        if (!$is_individual && empty($_POST['company_name'])) {
            $errors[] = __('Název firmy je povinný', 'saw-visitors');
        }
        
        // Musí vybrat alespoň jednoho hostitele
        if (empty($_POST['host_ids']) || !is_array($_POST['host_ids'])) {
            $errors[] = __('Musíte vybrat alespoň jednoho hostitele', 'saw-visitors');
        }
        
        // Email validace (pokud vyplněn)
        if (!empty($_POST['email']) && !is_email($_POST['email'])) {
            $errors[] = __('Neplatný formát emailu', 'saw-visitors');
        }
        
        if (!empty($errors)) {
            $this->set_error(implode('<br>', $errors));
            $this->render_registration_form();
            return;
        }
        
        // ===================================
        // 2. NAČTENÍ/VYTVOŘENÍ COMPANY
        // ===================================
        
        $company_id = null;
        
        if (!$is_individual) {
            // Load visits model
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
            $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
            $visits_model = new SAW_Module_Visits_Model($visits_config);
            
            $company_name = sanitize_text_field($_POST['company_name']);
            
            // Najdi nebo vytvoř firmu
            $company_id = $visits_model->find_or_create_company($this->branch_id, $company_name);
            
            if (is_wp_error($company_id)) {
                $this->set_error(__('Chyba při vytváření firmy: ', 'saw-visitors') . $company_id->get_error_message());
                $this->render_registration_form();
                return;
            }
        }
        
        // ===================================
        // 3. VYTVOŘENÍ VISIT (WALK-IN)
        // ===================================
        
        $visit_data = [
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'company_id' => $company_id, // NULL pro fyzickou osobu
            'visit_type' => 'walk_in',
            'status' => 'in_progress',
            'started_at' => current_time('mysql'),
            'purpose' => null, // Walk-in nemá purpose
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_visits',
            $visit_data,
            ['%d', '%d', $company_id ? '%d' : '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            error_log('[SAW Terminal] Failed to create visit: ' . $wpdb->last_error);
            $this->set_error(__('Chyba při vytváření návštěvy', 'saw-visitors'));
            $this->render_registration_form();
            return;
        }
        
        $visit_id = $wpdb->insert_id;
        
        // ===================================
        // 4. VYTVOŘENÍ VISITOR
        // ===================================
        
        $training_skipped = isset($_POST['training_skipped']) && $_POST['training_skipped'] == '1' ? 1 : 0;

$visitor_data = [
    'visit_id' => $visit_id,
    'first_name' => sanitize_text_field($_POST['first_name']),
    'last_name' => sanitize_text_field($_POST['last_name']),
    'position' => !empty($_POST['position']) ? sanitize_text_field($_POST['position']) : null,
    'email' => !empty($_POST['email']) ? sanitize_email($_POST['email']) : null,
    'phone' => !empty($_POST['phone']) ? sanitize_text_field($_POST['phone']) : null,
    'participation_status' => 'confirmed',
    'training_skipped' => $training_skipped,
    'training_started_at' => $training_skipped ? null : current_time('mysql'), // ✅ PŘIDAT
    'first_checkin_at' => current_time('mysql'),
    'created_at' => current_time('mysql'),
];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_visitors',
            $visitor_data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
        
        if (!$result) {
            error_log('[SAW Terminal] Failed to create visitor: ' . $wpdb->last_error);
            
            // Rollback - smaž visit
            $wpdb->delete($wpdb->prefix . 'saw_visits', ['id' => $visit_id], ['%d']);
            
            $this->set_error(__('Chyba při vytváření návštěvníka', 'saw-visitors'));
            $this->render_registration_form();
            return;
        }
        
        $visitor_id = $wpdb->insert_id;
        
        // ===================================
        // 5. PŘIŘAZENÍ HOSTŮ (M:N)
        // ===================================
        
        $host_ids = array_map('intval', $_POST['host_ids']);
        
        foreach ($host_ids as $host_id) {
            $wpdb->insert(
                $wpdb->prefix . 'saw_visit_hosts',
                [
                    'visit_id' => $visit_id,
                    'user_id' => $host_id,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s']
            );
        }
        
        // ===================================
        // 6. OKAMŽITÝ CHECK-IN (daily_log)
        // ===================================
        
        $log_date = current_time('Y-m-d');
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_visit_daily_logs',
            [
                'visit_id' => $visit_id,
                'visitor_id' => $visitor_id,
                'log_date' => $log_date,
                'checked_in_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        
        if (!$result) {
            error_log('[SAW Terminal] Failed to create daily log: ' . $wpdb->last_error);
            // Nepřerušujeme flow - check-in selhal ale návštěva existuje
        }
        
        // ===================================
        // 7. ULOŽENÍ DO SESSION + TRAINING STEPS
        // ===================================
        
        $flow = $this->session->get('terminal_flow');
        $flow['visit_id'] = $visit_id;
        $flow['visitor_id'] = $visitor_id; // ✅ Singular pro compatibility s templates
        $flow['visitor_ids'] = [$visitor_id]; // Array pro budoucí rozšíření
        $flow['training_required'] = !$training_skipped;
        
        // Načíst školící kroky
        if (!$training_skipped) {
            $training_steps = $this->get_training_steps($visit_id, $flow['language']);
            // $flow['training_steps'] = $training_steps;
            $flow['training_current_index'] = 0;
        }
        
        $this->session->set('terminal_flow', $flow);
        
        // ===================================
        // 8. REDIRECT
        // ===================================
        
        if ($training_skipped) {
            // Přeskočit školení → přímo na success
            wp_redirect(home_url('/terminal/success/'));
        } else {
            // Pokud jsou kroky, jdi na první krok
            if (!empty($training_steps) && isset($training_steps[0])) {
                $first_step_url = $training_steps[0]['url'];
                wp_redirect(home_url('/terminal/' . $first_step_url . '/'));
            } else {
                // Žádné kroky → success
                wp_redirect(home_url('/terminal/success/'));
            }
        }
        
        exit;
    }
    
    /**
     * Handle checkout via PIN
     *
     * TODO: Implement actual DB lookup
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkout_pin() {
        // TODO: Implement
        $this->set_error(__('Check-out přes PIN ještě není implementován', 'saw-visitors'));
        $this->render_checkout_pin();
    }
    
    /**
     * Handle checkout via search
     *
     * TODO: Implement actual DB search
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkout_search() {
        // TODO: Implement
        $this->set_error(__('Vyhledávání ještě není implementováno', 'saw-visitors'));
        $this->render_checkout_search();
    }
    
    /**
     * Move to next training step
     * 
     * @since 3.0.0
     * @return void
     */
    private function move_to_next_training_step() {
    $flow = $this->session->get('terminal_flow');
    $visit_id = $flow['visit_id'] ?? null;
    $language = $flow['language'] ?? 'cs';
    
    // ✅ Načíst fresh training steps z DB
    $steps = $this->get_training_steps($visit_id, $language);
    
    error_log("[MOVE_TO_NEXT] Total steps: " . count($steps));
    
    // Určit aktuální krok podle URL
    $path = get_query_var('saw_path');
    $current_step_type = null;
    
    if (strpos($path, 'training-video') !== false) {
        $current_step_type = 'video';
    } elseif (strpos($path, 'training-map') !== false) {
        $current_step_type = 'map';
    } elseif (strpos($path, 'training-risks') !== false) {
        $current_step_type = 'risks';
    } elseif (strpos($path, 'training-department') !== false) {
        $current_step_type = 'department';
    } elseif (strpos($path, 'training-additional') !== false) {
        $current_step_type = 'additional';
    }
    
    error_log("[MOVE_TO_NEXT] Current step type: {$current_step_type}");
    
    // ✅ JEDNODUCHÁ LOGIKA - najdi aktuální krok a jdi na další
    $current_index = -1;
    
    // Pro department - najdi PRVNÍ department (všechny se počítají jako JEDEN krok)
    if ($current_step_type === 'department') {
        foreach ($steps as $index => $step) {
            if ($step['type'] === 'department') {
                $current_index = $index;
                break; // ✅ První department = aktuální pozice
            }
        }
        
        // Přeskoč VŠECHNY departments najednou
        $next_index = $current_index + 1;
        while ($next_index < count($steps) && $steps[$next_index]['type'] === 'department') {
            $next_index++;
        }
    } else {
        // Pro ostatní kroky
        foreach ($steps as $index => $step) {
            if ($step['type'] === $current_step_type) {
                $current_index = $index;
                break;
            }
        }
        $next_index = $current_index + 1;
    }
    
    error_log("[MOVE_TO_NEXT] Current index: {$current_index}, next: {$next_index}");
    
    if ($next_index < count($steps)) {
        $next_step = $steps[$next_index];
        error_log("[MOVE_TO_NEXT] Redirecting to: {$next_step['url']}");
        wp_redirect(home_url('/terminal/' . $next_step['url'] . '/'));
        exit;
    } else {
        // Všechny kroky hotové
        $this->complete_training($flow);
    }
}

/**
 * Complete training and mark visitor
 */
private function complete_training($flow) {
    if (!empty($flow['visitor_id'])) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            ['training_completed_at' => current_time('mysql')],
            ['id' => $flow['visitor_id']],
            ['%s'],
            ['%d']
        );
    }
    
    error_log("[MOVE_TO_NEXT] Training completed - redirecting to success");
    wp_redirect(home_url('/terminal/success/'));
    exit;
}
    
    /**
     * Training step completion handlers (stubs for now)
     */
    /**
     * Training step completion handlers
     */
    private function handle_training_video_complete() {
    $flow = $this->session->get('terminal_flow');
    if (!empty($flow['visitor_id'])) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            ['training_step_video' => 1],
            ['id' => $flow['visitor_id']],
            ['%d'],
            ['%d']
        );
        error_log("[SAW Terminal] Marked video training as complete for visitor {$flow['visitor_id']}");
    }
    $this->move_to_next_training_step();
}
    
    private function handle_training_map_complete() {
    $flow = $this->session->get('terminal_flow');
    if (!empty($flow['visitor_id'])) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            ['training_step_map' => 1],
            ['id' => $flow['visitor_id']],
            ['%d'],
            ['%d']
        );
        error_log("[SAW Terminal] Marked map training as complete for visitor {$flow['visitor_id']}");
    }
    $this->move_to_next_training_step();
}
    
    private function handle_training_risks_complete() {
    $flow = $this->session->get('terminal_flow');
    if (!empty($flow['visitor_id'])) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            ['training_step_risks' => 1],
            ['id' => $flow['visitor_id']],
            ['%d'],
            ['%d']
        );
        error_log("[SAW Terminal] Marked risks training as complete for visitor {$flow['visitor_id']}");
    }
    $this->move_to_next_training_step();
}
    
    private function handle_training_department_complete() {
    $flow = $this->session->get('terminal_flow');
    $visitor_id = $flow['visitor_id'] ?? null;
    
    if ($visitor_id) {
        global $wpdb;
        
        // Mark department training as completed
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            ['training_step_department' => 1],
            ['id' => $visitor_id],
            ['%d'],
            ['%d']
        );
        
        error_log("[SAW Terminal] Marked department training as complete for visitor {$visitor_id}");
    }
    
    // Move to next training step or success
    $this->move_to_next_training_step();
}
    
    private function handle_training_additional_complete() {
    $flow = $this->session->get('terminal_flow');
    if (!empty($flow['visitor_id'])) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            ['training_step_additional' => 1],
            ['id' => $flow['visitor_id']],
            ['%d'],
            ['%d']
        );
        error_log("[SAW Terminal] Marked additional training as complete for visitor {$flow['visitor_id']}");
    }
    $this->move_to_next_training_step();
}
    
    /**
     * Render template with layout
     *
     * @since 1.0.0
     * @param string $template Template path
     * @param array $data Data to pass to template
     * @return void
     */
    private function render_template($template, $data = []) {
        error_log("[RENDER_TEMPLATE] Starting render");
        error_log("[RENDER_TEMPLATE] Template: {$template}");
        error_log("[RENDER_TEMPLATE] Data keys: " . implode(', ', array_keys($data)));
        
        // Check if template exists
        if (!file_exists($template)) {
            error_log("[RENDER_TEMPLATE] ERROR: Template file does not exist!");
            wp_die("Template not found: {$template}");
        }
        
        // Extract data for template
        extract($data);
        
        // Get error message if any
        $error = $this->session->get('terminal_error');
        $this->session->unset('terminal_error');
        
        // ✅ Provide flow to layouts (header/footer need it)
        $flow = $this->session->get('terminal_flow');
        
        error_log("[RENDER_TEMPLATE] Including layout-header");
        // Start terminal layout
        include SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/layout-header.php';
        
        error_log("[RENDER_TEMPLATE] Including step template");
        // Include step template
        ob_start();
        include $template;
        $template_output = ob_get_clean();
        
        if (empty($template_output)) {
            error_log("[RENDER_TEMPLATE] WARNING: Template produced no output!");
        } else {
            error_log("[RENDER_TEMPLATE] Template output length: " . strlen($template_output) . " bytes");
        }
        
        echo $template_output;
        
        error_log("[RENDER_TEMPLATE] Including layout-footer");
        // End terminal layout
        include SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/layout-footer.php';
        
        error_log("[RENDER_TEMPLATE] Render complete - exiting");
        exit;
    }
    
    /**
     * Reset terminal flow
     *
     * @since 1.0.0
     * @return void
     */
    private function reset_flow() {
        $this->session->set('terminal_flow', [
            'step' => 'language',
            'language' => null,
            'action' => null,
            'type' => null,
            'pin' => null,
            'visit_id' => null,
            'visitor_ids' => [],
            'data' => [],
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
        ]);
    }
    
    /**
     * Set error message
     *
     * @since 1.0.0
     * @param string $message Error message
     * @return void
     */
    private function set_error($message) {
        $this->session->set('terminal_error', $message);
    }
    
    /**
     * Enqueue terminal assets
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-terminal',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/terminal.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-terminal',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/terminal.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
    }
}