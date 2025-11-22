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
            SAW_Logger::warning("[SAW Terminal] WARNING: No languages found for customer #{$this->customer_id}, branch #{$this->branch_id}");
            
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
        
        SAW_Logger::debug("=== TERMINAL RENDER DEBUG ===");
        SAW_Logger::debug("REQUEST_URI: {$request_uri}");
        SAW_Logger::debug("saw_path query var: '{$path}'");
        SAW_Logger::debug("current_step: {$this->current_step}");
        
        // Get current flow data
        $flow = $this->session->get('terminal_flow');
        SAW_Logger::debug("Flow data", ['flow' => $flow]);
        
        // ✅ Reset flow when returning to language step via home button
        if ($this->current_step === 'language') {
            // If we have progress but we're back at language, reset
            if (!empty($flow['language']) || !empty($flow['action'])) {
                SAW_Logger::debug("RESETTING FLOW - user returned to language step");
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
        SAW_Logger::debug("=== ROUTING TO STEP: {$this->current_step} ===");
        
        switch ($this->current_step) {
            case 'language':
                SAW_Logger::debug("Rendering language selection");
                $this->render_language_selection();
                break;
                
            case 'action':
                SAW_Logger::debug("Rendering action choice");
                $this->render_action_choice();
                break;
                
            case 'checkin':
                SAW_Logger::debug("Rendering checkin type");
                $this->render_checkin_type();
                break;
                
            case 'checkout':
                SAW_Logger::debug("Rendering checkout method");
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

	    case 'checkout-select':
                error_log("Rendering checkout visitor selection");
                $this->render_checkout_select();
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

	    case 'select-visitors':
                error_log("Redirecting select-visitors to register");
                wp_redirect(home_url('/terminal/register/'));
                exit;
                
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
    global $wpdb;
    
    // ✅ Load hosts from database
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
    
    // ✅ OPRAVA: Reload visitors from DB if visit_id exists
    $flow = $this->session->get('terminal_flow');
    $visit_id = $flow['visit_id'] ?? null;
    
    if ($visit_id) {
        // ✅ FRESH DATA z DB (ne ze session)
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT vis.*, 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM {$wpdb->prefix}saw_visit_daily_logs dl2
                            WHERE dl2.visitor_id = vis.id 
                            AND dl2.checked_in_at IS NOT NULL 
                            AND dl2.checked_out_at IS NULL
                        ) THEN 1
                        ELSE 0
                    END as is_currently_inside
             FROM {$wpdb->prefix}saw_visitors vis
             WHERE vis.visit_id = %d 
             AND vis.participation_status IN ('planned', 'confirmed')
             ORDER BY vis.last_name, vis.first_name",
            $visit_id
        ), ARRAY_A);
        
        // ✅ UPDATE SESSION s fresh data
        $flow['visitors'] = $visitors;
        $this->session->set('terminal_flow', $flow);
    }
    
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/4-register.php';
    $this->render_template($template, [
        'hosts' => $hosts,
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
     * Render checkout visitor selection
     */
    private function render_checkout_select() {
    global $wpdb;
    
    $flow = $this->session->get('terminal_flow');
    $visit_id = $flow['checkout_visit_id'] ?? null;
    
    // ✅ RELOAD FRESH DATA from DB (ne ze session)
    if ($visit_id) {
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
                vis.id,
                vis.first_name,
                vis.last_name,
                vis.position,
                vis.email,
                vis.phone,
                vis.participation_status,
                dl.log_date,
                dl.checked_in_at,
                dl.id as log_id
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visit_daily_logs dl 
                ON vis.id = dl.visitor_id
             WHERE vis.visit_id = %d
               AND dl.checked_in_at IS NOT NULL
               AND dl.checked_out_at IS NULL
             ORDER BY dl.checked_in_at DESC, vis.last_name, vis.first_name",
            $visit_id
        ), ARRAY_A);
        
        // ✅ UPDATE SESSION s fresh data
        $flow['checkout_visitors'] = $visitors;
        $this->session->set('terminal_flow', $flow);
        
        error_log(sprintf(
            "[SAW Checkout Select RELOAD] Visit #%d - Reloaded %d visitors from DB",
            $visit_id,
            count($visitors)
        ));
    } else {
        $visitors = $flow['checkout_visitors'] ?? [];
    }
    
    $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/checkout/select.php';
    $this->render_template($template, [
        'visitors' => $visitors,
    ]);
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
	error_log("[SAW Terminal] handle_post() CALLED - POST data: " . print_r($_POST, true));
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

            case 'checkout_pin_verify':
                $this->handle_checkout_pin_verify();
                break;
                
            case 'checkout_complete':
                $this->handle_checkout_complete();
                break;

	    case 'submit_unified_registration':
                $this->handle_unified_registration();
                break;
                
            case 'submit_registration':
                // Backward compatibility - redirect to new handler
                $this->handle_unified_registration();
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
    global $wpdb;
    
    $pin = sanitize_text_field($_POST['pin'] ?? '');
    
    if (empty($pin) || strlen($pin) !== 6) {
        $this->set_error(__('Neplatný PIN kód', 'saw-visitors'));
        $this->render_pin_entry();
        return;
    }
    
    // ✅ UPRAVENO: Kontrola PIN platnosti včetně pin_expires_at
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits 
         WHERE pin_code = %s 
           AND customer_id = %d 
           AND visit_type = 'planned'
           AND status != 'cancelled'
           AND (pin_expires_at IS NULL OR pin_expires_at >= NOW())
         LIMIT 1",
        $pin,
        $this->customer_id
    ), ARRAY_A);
    
    if (!$visit) {
        $this->set_error(__('PIN kód je neplatný nebo již vypršel', 'saw-visitors'));
        $this->render_pin_entry();
        return;
    }
    
    // ✅ PŘIDÁNO: Re-aktivace completed visit
    if ($visit['status'] === 'completed') {
        $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            [
                'status' => 'in_progress',
                'completed_at' => null,
            ],
            ['id' => $visit['id']],
            ['%s', '%s'],
            ['%d']
        );
        error_log("[SAW Terminal] Visit #{$visit['id']} re-activated");
    }
    
    // ✅ PŘIDÁNO: Automaticky prodloužit PIN o 24h
    $new_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        ['pin_expires_at' => $new_expiry],
        ['id' => $visit['id']],
        ['%s'],
        ['%d']
    );
    error_log("[SAW Terminal] PIN extended to {$new_expiry}");   
    
    
    // ✅ OPRAVENO: Načti VŠECHNY návštěvníky + zjisti kdo je uvnitř
$visitors = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        vis.*,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM {$wpdb->prefix}saw_visit_daily_logs dl2
                WHERE dl2.visitor_id = vis.id 
                AND dl2.checked_in_at IS NOT NULL 
                AND dl2.checked_out_at IS NULL
            ) THEN 1
            ELSE 0
        END as is_currently_inside
     FROM {$wpdb->prefix}saw_visitors vis
     WHERE vis.visit_id = %d 
     AND vis.participation_status IN ('planned', 'confirmed')
     ORDER BY vis.last_name, vis.first_name",
    $visit['id']
), ARRAY_A);
    
    // Ulož do session
    $flow = $this->session->get('terminal_flow');
    $flow['visit_id'] = $visit['id'];
    $flow['pin'] = $pin;
    $flow['type'] = 'planned'; // ✅ Označit jako plánovanou
    
    // ✅ Ulož visitors do session (ať už jsou nebo ne)
    $flow['visitors'] = $visitors; // může být prázdné pole
    $this->session->set('terminal_flow', $flow);
    
    // ✅ VŽDY jdi na registrační formulář (univerzální)
    wp_redirect(home_url('/terminal/register/'));
    exit;
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

	error_log("[DEBUG get_training_steps] customer_id={$this->customer_id}, branch_id={$this->branch_id}, language_code={$language_code}, visit_id={$visit_id}");
    
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
     * Zpracuje registraci:
     * - Walk-in: vytvoří novou visit + visitors
     * - Planned: použije existující visit z PIN + přidá visitors
     * 
     * Podporuje více osob a profesní průkazy
     *
     * @since 3.0.0
     * @return void
     */
    private function handle_unified_registration() {
        global $wpdb;

	error_log("[SAW Terminal] handle_unified_registration() STARTED");

	error_log("[SAW Terminal] Getting flow from session...");
	$flow = $this->session->get('terminal_flow');
	error_log("[SAW Terminal] Flow retrieved: " . print_r($flow, true));

	$is_planned = ($flow['type'] ?? '') === 'planned';
	error_log("[SAW Terminal] is_planned: " . ($is_planned ? 'YES' : 'NO'));

	$visit_id = $flow['visit_id'] ?? null;
	error_log("[SAW Terminal] visit_id: " . ($visit_id ?? 'NULL'));

	error_log("[SAW Terminal] Starting validation...");
        
        // ===================================
        // 1. VALIDACE FORMULÁŘE
        // ===================================
        
        $errors = [];
        
        // Zjisti zda jsou existing nebo new visitors
	$existing_visitor_ids = $_POST['existing_visitor_ids'] ?? [];
	$new_visitors = $_POST['new_visitors'] ?? [];

	error_log("[SAW Terminal] existing_visitor_ids: " . print_r($existing_visitor_ids, true));
	error_log("[SAW Terminal] new_visitors count: " . count($new_visitors));

	// Musí být alespoň jeden visitor (existing NEBO new)
	if (empty($existing_visitor_ids) && empty($new_visitors)) {
	    error_log("[SAW Terminal] VALIDATION ERROR: No visitors");
	    $errors[] = __('Musíte vybrat nebo zadat alespoň jednoho návštěvníka', 'saw-visitors');
	}

	error_log("[SAW Terminal] Validating new visitors...");
        
        // Musí být alespoň jeden visitor (existing NEBO new)
        if (empty($existing_visitor_ids) && empty($new_visitors)) {
            $errors[] = __('Musíte vybrat nebo zadat alespoň jednoho návštěvníka', 'saw-visitors');
        }
        
        // Validace new visitors
        if (!empty($new_visitors) && is_array($new_visitors)) {
	    error_log("[SAW Terminal] Entering new_visitors validation loop");
	    foreach ($new_visitors as $idx => $visitor) {
	        error_log("[SAW Terminal] Validating visitor #{$idx}: " . print_r($visitor, true));
                // Přeskoč prázdné řádky
                $is_empty = empty($visitor['first_name']) && empty($visitor['last_name']) && empty($visitor['position']) && empty($visitor['email']) && empty($visitor['phone']);
                
                if ($is_empty) {
                    continue; // Přeskoč kompletně prázdný formulář
                }
                
                // Pokud NENÍ prázdný, vyžaduj jméno a příjmení
                if (empty($visitor['first_name'])) {
                    $errors[] = sprintf(__('Jméno je povinné pro návštěvníka %d', 'saw-visitors'), $idx + 1);
                }
                if (empty($visitor['last_name'])) {
                    $errors[] = sprintf(__('Příjmení je povinné pro návštěvníka %d', 'saw-visitors'), $idx + 1);
                }
                if (!empty($visitor['email']) && !is_email($visitor['email'])) {
                    $errors[] = sprintf(__('Neplatný email pro návštěvníka %d', 'saw-visitors'), $idx + 1);
                }
            }
        }
        
        // Pro walk-in: validace company a hosts

	error_log("[SAW Terminal] New visitors validation complete");

        if (!$is_planned) {
		error_log("[SAW Terminal] Validating walk-in specific data...");

            $is_individual = isset($_POST['is_individual']) && $_POST['is_individual'] == '1';
            
            if (!$is_individual && empty($_POST['company_name'])) {
                $errors[] = __('Název firmy je povinný', 'saw-visitors');
            }
            
            if (empty($_POST['host_ids']) || !is_array($_POST['host_ids'])) {
                $errors[] = __('Musíte vybrat alespoň jednoho hostitele', 'saw-visitors');
            }
        }
        
        if (!empty($errors)) {
	
		error_log("[SAW Terminal] VALIDATION FAILED: " . implode(', ', $errors));

            $this->set_error(implode('<br>', $errors));
            $this->render_registration_form();
            return;
        }

	error_log("[SAW Terminal] Validation passed, creating visit...");
        
        // ===================================
        // 2. VYTVOŘENÍ/POUŽITÍ VISIT
        // ===================================
        
        if ($is_planned) {
            // ✅ PLANNED: Použij existující visit_id z PIN
            if (!$visit_id) {
                $this->set_error(__('Chyba: Návštěva nebyla nalezena', 'saw-visitors'));
                $this->render_registration_form();
                return;
            }
            
            // Mark visit as started
            $wpdb->update(
                $wpdb->prefix . 'saw_visits',
                [
                    'status' => 'in_progress',
                    'started_at' => current_time('mysql'),
                ],
                ['id' => $visit_id],
                ['%s', '%s'],
                ['%d']
            );
            
            error_log("[SAW Terminal] Planned visit #{$visit_id} started");
            
        } else {
            // ✅ WALK-IN: Vytvoř novou visit
            
            // Company
            $company_id = null;
            $is_individual = isset($_POST['is_individual']) && $_POST['is_individual'] == '1';

		error_log("[SAW Terminal] is_individual: " . ($is_individual ? 'YES' : 'NO'));
            
            if (!$is_individual) {

		error_log("[SAW Terminal] Creating/finding company...");
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
		error_log("[SAW Terminal] Visits model.php loaded");

		$visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
		error_log("[SAW Terminal] Visits config loaded");

		$visits_model = new SAW_Module_Visits_Model($visits_config);
		error_log("[SAW Terminal] Visits model instantiated");

		$company_name = sanitize_text_field($_POST['company_name']);
		error_log("[SAW Terminal] Company name: {$company_name}, branch_id: {$this->branch_id}");

		$company_id = $visits_model->find_or_create_company($this->branch_id, $company_name, $this->customer_id);
		error_log("[SAW Terminal] Company ID returned: " . ($company_id ?: 'NULL'));
                
                if (is_wp_error($company_id)) {
                    $this->set_error(__('Chyba při vytváření firmy: ', 'saw-visitors') . $company_id->get_error_message());
                    $this->render_registration_form();
                    return;
                }
            }
            
            // Create visit
            $visit_data = [
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'company_id' => $company_id,
                'visit_type' => 'walk_in',
                'status' => 'in_progress',
                'started_at' => current_time('mysql'),
                'purpose' => null,
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
            
            error_log("[SAW Terminal] Walk-in visit #{$visit_id} created");
            
            // Save hosts
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
        }
        
        // ===================================
        // 3. ZPRACOVÁNÍ NÁVŠTĚVNÍKŮ
        // ===================================
        // ✅ PŘIDÁNO: Zjisti zda má firma školící obsah
        $language = $flow['language'] ?? 'cs';
        $has_training_content = false;
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id, $language
        ));
        if ($language_id) {
            $content = $wpdb->get_row($wpdb->prepare(
                "SELECT id, video_url, pdf_map_path, risks_text, additional_text 
                 FROM {$wpdb->prefix}saw_training_content 
                 WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
                $this->customer_id, $this->branch_id, $language_id
            ), ARRAY_A);
            
            if ($content) {
                // Zkontroluj základní obsah
                $has_training_content = (
                    !empty($content['video_url']) ||
                    !empty($content['pdf_map_path']) ||
                    !empty($content['risks_text']) ||
                    !empty($content['additional_text'])
                );
                
                // Pokud základní není, zkontroluj department content
                if (!$has_training_content) {
                    $dept_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_department_content
                         WHERE training_content_id = %d 
                           AND (text_content IS NOT NULL AND text_content != '')",
                        $content['id']
                    ));
                    $has_training_content = ($dept_count > 0);
                }
            }
        }
        error_log("[SAW Terminal] Training content available: " . ($has_training_content ? 'YES' : 'NO'));
        
        $visitor_ids = [];
        $any_needs_training = false;
        
        // ===================================
        // 3a. EXISTING VISITORS
        // ===================================
        if (!empty($existing_visitor_ids) && is_array($existing_visitor_ids)) {
            error_log("[SAW Terminal] Processing " . count($existing_visitor_ids) . " existing visitors");
            
            foreach ($existing_visitor_ids as $visitor_id) {
                $visitor_id = intval($visitor_id);
                
                // Zkontroluj dokončené školení
                $has_completed_training = !empty($wpdb->get_var($wpdb->prepare(
                    "SELECT training_completed_at FROM {$wpdb->prefix}saw_visitors 
                     WHERE id = %d AND training_completed_at IS NOT NULL",
                    $visitor_id
                )));
                
                // Training skip checkbox
                $training_skip = isset($_POST['existing_training_skip'][$visitor_id]) ? 1 : 0;
                
                // ✅ URČIT training_status
                $training_status = 'pending';
                if ($training_skip) {
                    $training_status = 'skipped';
                } elseif ($has_completed_training) {
                    $training_status = 'completed';
                } elseif (!$has_training_content) {
                    $training_status = 'not_available';
                } else {
                    $training_status = 'in_progress';
                    $any_needs_training = true;
                }
                
                // ✅ UPDATE s NOVÝMI sloupci
                if (!$training_skip && !$has_completed_training && $has_training_content) {
                    // Potřebuje školení - RESET progress
                    $wpdb->update(
                        $wpdb->prefix . 'saw_visitors',
                        [
                            'participation_status' => 'confirmed',
                            'current_status' => 'present',        // ✅ NOVÝ
                            'training_status' => $training_status, // ✅ NOVÝ
                            'training_skipped' => 0,
                            'training_started_at' => current_time('mysql'),
                            'training_step_video' => 0,
                            'training_step_map' => 0,
                            'training_step_risks' => 0,
                            'training_step_additional' => 0,
                            'training_step_department' => 0,
                        ],
                        ['id' => $visitor_id],
                        ['%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d'],
                        ['%d']
                    );
                } else {
                    // Školení přeskočeno nebo hotové
                    $wpdb->update(
                        $wpdb->prefix . 'saw_visitors',
                        [
                            'participation_status' => 'confirmed',
                            'current_status' => 'present',        // ✅ NOVÝ
                            'training_status' => $training_status, // ✅ NOVÝ
                            'training_skipped' => $training_skip,
                        ],
                        ['id' => $visitor_id],
                        ['%s', '%s', '%s', '%d'],
                        ['%d']
                    );
                }
                
                $visitor_ids[] = $visitor_id;
        
                
                // ✅ INSERT daily log s customer_id a branch_id
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_daily_logs',
                    [
                        'customer_id' => $this->customer_id,  // ✅ NOVÝ
                        'branch_id' => $this->branch_id,      // ✅ NOVÝ
                        'visit_id' => $visit_id,
                        'visitor_id' => $visitor_id,
                        'log_date' => current_time('Y-m-d'),
                        'checked_in_at' => current_time('mysql'),
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
                );
                
                error_log("[SAW Terminal] Existing visitor #{$visitor_id} checked-in, training_status={$training_status}");
            }
        }
        
        // ===================================
        // 3b. NEW VISITORS
        // ===================================
        if (!empty($new_visitors) && is_array($new_visitors)) {
            error_log("[SAW Terminal] Processing " . count($new_visitors) . " new visitors");
            
            foreach ($new_visitors as $idx => $visitor_data) {
                // ✅ SKIP prázdné řádky
                if (empty($visitor_data['first_name']) && empty($visitor_data['last_name'])) {
                    continue;
                }
                
                $training_skipped = isset($visitor_data['training_skipped']) && $visitor_data['training_skipped'] == '1' ? 1 : 0;
                
                // ✅ URČIT training_status
                $training_status = 'pending';
                if ($training_skipped) {
                    $training_status = 'skipped';
                } elseif (!$has_training_content) {
                    $training_status = 'not_available';
                } else {
                    $training_status = 'in_progress';
                    $any_needs_training = true;
                }
                
                // ✅ INSERT s NOVÝMI sloupci
                $visitor_insert = [
                    'customer_id' => $this->customer_id,      // ✅ NOVÝ
                    'branch_id' => $this->branch_id,          // ✅ NOVÝ
                    'visit_id' => $visit_id,
                    'first_name' => sanitize_text_field($visitor_data['first_name']),
                    'last_name' => sanitize_text_field($visitor_data['last_name']),
                    'position' => !empty($visitor_data['position']) ? sanitize_text_field($visitor_data['position']) : null,
                    'email' => !empty($visitor_data['email']) ? sanitize_email($visitor_data['email']) : null,
                    'phone' => !empty($visitor_data['phone']) ? sanitize_text_field($visitor_data['phone']) : null,
                    'participation_status' => 'confirmed',
                    'current_status' => 'present',            // ✅ NOVÝ
                    'training_status' => $training_status,    // ✅ NOVÝ
                    'training_skipped' => $training_skipped,
                    'training_required' => (!$training_skipped && $has_training_content) ? 1 : 0,
                    'training_started_at' => (!$training_skipped && $has_training_content) ? current_time('mysql') : null,
                    'created_at' => current_time('mysql'),
                ];
                
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visitors',
                    $visitor_insert,
                    ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
                );
                
                $visitor_id = $wpdb->insert_id;
                $visitor_ids[] = $visitor_id;
                
                // Daily log s customer/branch
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_daily_logs',
                    [
                        'customer_id' => $this->customer_id,  // ✅ NOVÝ
                        'branch_id' => $this->branch_id,      // ✅ NOVÝ
                        'visit_id' => $visit_id,
                        'visitor_id' => $visitor_id,
                        'log_date' => current_time('Y-m-d'),
                        'checked_in_at' => current_time('mysql'),
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
                );
                
                error_log("[SAW Terminal] New visitor #{$visitor_id} created, training_status={$training_status}");
            }
        }
        
        if (empty($visitor_ids)) {
            $this->set_error(__('Chyba: Nepodařilo se vytvořit žádné návštěvníky', 'saw-visitors'));
            $this->render_registration_form();
            return;
        }
        
        // ===================================
        // 4. ULOŽENÍ DO SESSION
        // ===================================
        
        $flow['visit_id'] = $visit_id;
        $flow['visitor_id'] = $visitor_ids[0]; // První pro compatibility
        $flow['visitor_ids'] = $visitor_ids;
        $flow['training_required'] = $any_needs_training;
        
        $this->session->set('terminal_flow', $flow);
        
        error_log("[SAW Terminal] Session updated - visit #{$visit_id}, visitors: " . implode(',', $visitor_ids));
        
        // ===================================
        // 5. REDIRECT
        // ===================================
        
        if (!$any_needs_training) {
            error_log("[SAW Terminal] No training required - redirecting to success");
            wp_redirect(home_url('/terminal/success/'));
        } else {
            error_log("[SAW Terminal DEBUG] About to call get_training_steps with visit_id={$visit_id}, language={$flow['language']}");
    $training_steps = $this->get_training_steps($visit_id, $flow['language']);
    
    error_log("[SAW Terminal] Training required - " . count($training_steps) . " steps found");
    error_log("[SAW Terminal DEBUG] Training steps: " . print_r($training_steps, true));
            
            if (!empty($training_steps) && isset($training_steps[0])) {
                $first_step_url = $training_steps[0]['url'];
                error_log("[SAW Terminal] Redirecting to first training step: {$first_step_url}");
                wp_redirect(home_url('/terminal/' . $first_step_url . '/'));
            } else {
                error_log("[SAW Terminal] No training steps - redirecting to success");
                wp_redirect(home_url('/terminal/success/'));
            }
        }
        
        exit;
    }


    
    /**
     * Handle checkout PIN verification
     * Loads visitors who are currently checked in
     */
    private function handle_checkout_pin_verify() {
    global $wpdb;
    
    $pin = sanitize_text_field($_POST['pin'] ?? '');
    
    if (empty($pin) || strlen($pin) !== 6) {
        $this->set_error(__('Neplatný PIN kód', 'saw-visitors'));
        $this->render_checkout_pin();
        return;
    }
    
    // Find visit by PIN
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits 
         WHERE pin_code = %s 
         AND customer_id = %d 
         AND status IN ('in_progress', 'confirmed')",
        $pin,
        $this->customer_id
    ), ARRAY_A);
    
    if (!$visit) {
        $this->set_error(__('PIN kód nenalezen nebo návštěva již ukončena', 'saw-visitors'));
        $this->render_checkout_pin();
        return;
    }
    
    // ✅ OPRAVENO: Načti JEN visitors s AKTIVNÍM neukončeným check-in
    $visitors = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT 
            vis.id,
            vis.first_name,
            vis.last_name,
            vis.position,
            vis.email,
            vis.phone,
            vis.participation_status,
            dl.log_date,
            dl.checked_in_at,
            dl.id as log_id
         FROM {$wpdb->prefix}saw_visitors vis
         INNER JOIN {$wpdb->prefix}saw_visit_daily_logs dl 
            ON vis.id = dl.visitor_id
         WHERE vis.visit_id = %d
           AND dl.checked_in_at IS NOT NULL
           AND dl.checked_out_at IS NULL
         ORDER BY dl.checked_in_at DESC, vis.last_name, vis.first_name",
        $visit['id']
    ), ARRAY_A);
    
    // ✅ DEBUG
    error_log(sprintf(
        "[SAW Checkout PIN] Visit #%d - Found %d visitors with active check-in",
        $visit['id'],
        count($visitors)
    ));
    
    if (!empty($visitors)) {
        foreach ($visitors as $v) {
            error_log(sprintf(
                "  - %s %s (log_date: %s, checked_in: %s)",
                $v['first_name'],
                $v['last_name'],
                $v['log_date'],
                $v['checked_in_at']
            ));
        }
    }
    
    if (empty($visitors)) {
        $this->set_error(__('Nikdo z této návštěvy není momentálně přítomen', 'saw-visitors'));
        $this->render_checkout_pin();
        return;
    }
    
    // Save to session
    $flow = $this->session->get('terminal_flow');
    $flow['checkout_visit_id'] = $visit['id'];
    $flow['checkout_visitors'] = $visitors;
    $flow['step'] = 'checkout-select';
    $this->session->set('terminal_flow', $flow);
    
    // Redirect to selection
    wp_redirect(home_url('/terminal/checkout-select/'));
    exit;
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
 * Handle checkout completion
 * Updates daily_logs and completes visit if needed
 */
private function handle_checkout_complete() {
    global $wpdb;
    
    $flow = $this->session->get('terminal_flow');
    
    // ✅ OPRAVENO: Support both PIN and SEARCH checkout
    $visitor_ids_input = $_POST['visitor_ids'] ?? [];
    
    // Handle comma-separated string (from search) OR array (from PIN)
    if (is_string($visitor_ids_input)) {
        $visitor_ids = array_map('intval', explode(',', $visitor_ids_input));
    } else {
        $visitor_ids = $visitor_ids_input;
    }
    
    if (empty($visitor_ids)) {
        $this->set_error(__('Musíte vybrat alespoň jednoho návštěvníka', 'saw-visitors'));
        
        // Redirect back based on method
        if (!empty($flow['checkout_visit_id'])) {
            $this->render_checkout_select();
        } else {
            $this->render_checkout_search();
        }
        return;
    }
    
    $checkout_time = current_time('mysql');
    
    // ✅ OPRAVENO: Checkout BEZ visit_id (hledej podle visitor_id)
    foreach ($visitor_ids as $visitor_id) {
        $visitor_id = intval($visitor_id);
        
        // 1. NAJDI poslední aktivní log (BEZ visit_id filtru)
        $log_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visit_daily_logs
             WHERE visitor_id = %d
             AND checked_in_at IS NOT NULL
             AND checked_out_at IS NULL
             ORDER BY checked_in_at DESC
             LIMIT 1",
            $visitor_id
        ));
        
        if (!$log_id) {
            error_log("[SAW Terminal] WARNING: No active log found for visitor #{$visitor_id}");
            continue;
        }
        
        // 2. UPDATE log
        $wpdb->update(
            $wpdb->prefix . 'saw_visit_daily_logs',
            ['checked_out_at' => $checkout_time],
            ['id' => $log_id],
            ['%s'],
            ['%d']
        );
        
        // 3. ✅ UPDATE visitor current_status
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            [
                'current_status' => 'checked_out',  // ✅ NOVÝ
                'last_checkout_at' => $checkout_time,
            ],
            ['id' => $visitor_id],
            ['%s', '%s'],
            ['%d']
        );
        
        error_log("[SAW Terminal] Visitor #{$visitor_id} checked out");
    }
    
    // ✅ Check if visits should be completed (pokud máme visit_id z PIN checkout)
    if (!empty($flow['checkout_visit_id'])) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
        $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
        $visits_model = new SAW_Module_Visits_Model($visits_config);
        
        $completed = $visits_model->check_and_complete_visit($flow['checkout_visit_id']);
        
        if ($completed) {
            error_log("[SAW Terminal] Visit #{$flow['checkout_visit_id']} automatically completed");
        }
    }
    
    // Clear session
    $this->reset_flow();
    
    // Redirect to success
    wp_redirect(home_url('/terminal/success/?action=checkout'));
    exit;
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
    $visitor_ids = $flow['visitor_ids'] ?? [];
    
    if (!empty($visitor_ids)) {
        global $wpdb;
        foreach ($visitor_ids as $vid) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                [
                    'training_completed_at' => current_time('mysql'),
                    'training_status' => 'completed',  // ✅ NOVÝ
                ],
                ['id' => intval($vid)],
                ['%s', '%s'],
                ['%d']
            );
        }
        error_log("[SAW Terminal] Training completed for " . count($visitor_ids) . " visitors");
    }
    
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
    $visitor_ids = $flow['visitor_ids'] ?? [];
    
    if (!empty($visitor_ids)) {
        global $wpdb;
        foreach ($visitor_ids as $vid) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_video' => 1],
                ['id' => intval($vid)],
                ['%d'],
                ['%d']
            );
        }
        error_log("[SAW Terminal] Marked video training as complete for " . count($visitor_ids) . " visitors");
    }
    $this->move_to_next_training_step();
}

private function handle_training_map_complete() {
    $flow = $this->session->get('terminal_flow');
    $visitor_ids = $flow['visitor_ids'] ?? [];
    
    if (!empty($visitor_ids)) {
        global $wpdb;
        foreach ($visitor_ids as $vid) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_map' => 1],
                ['id' => intval($vid)],
                ['%d'],
                ['%d']
            );
        }
        error_log("[SAW Terminal] Marked map training as complete for " . count($visitor_ids) . " visitors");
    }
    $this->move_to_next_training_step();
}

private function handle_training_risks_complete() {
    $flow = $this->session->get('terminal_flow');
    $visitor_ids = $flow['visitor_ids'] ?? [];
    
    if (!empty($visitor_ids)) {
        global $wpdb;
        foreach ($visitor_ids as $vid) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_risks' => 1],
                ['id' => intval($vid)],
                ['%d'],
                ['%d']
            );
        }
        error_log("[SAW Terminal] Marked risks training as complete for " . count($visitor_ids) . " visitors");
    }
    $this->move_to_next_training_step();
}

private function handle_training_department_complete() {
    $flow = $this->session->get('terminal_flow');
    $visitor_ids = $flow['visitor_ids'] ?? [];
    
    if (!empty($visitor_ids)) {
        global $wpdb;
        foreach ($visitor_ids as $vid) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_department' => 1],
                ['id' => intval($vid)],
                ['%d'],
                ['%d']
            );
        }
        error_log("[SAW Terminal] Marked department training as complete for " . count($visitor_ids) . " visitors");
    }
    $this->move_to_next_training_step();
}

private function handle_training_additional_complete() {
    $flow = $this->session->get('terminal_flow');
    $visitor_ids = $flow['visitor_ids'] ?? [];
    
    if (!empty($visitor_ids)) {
        global $wpdb;
        foreach ($visitor_ids as $vid) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                [
                    'training_completed_at' => current_time('mysql'),
                    'training_status' => 'completed',  // ✅ NOVÝ
                ],
                ['id' => intval($vid)],
                ['%s', '%s'],
                ['%d']
            );
        }
        error_log("[SAW Terminal] Training completed for " . count($visitor_ids) . " visitors");
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
    $css_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/css/';
    $js_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/';
    
    // CSS - Base (first, contains variables)
    wp_enqueue_style(
        'saw-terminal-base',
        $css_dir . 'terminal/base.css',
        array(),
        '3.0.0'
    );
    
    // CSS - Layout (depends on base)
    wp_enqueue_style(
        'saw-terminal-layout',
        $css_dir . 'terminal/layout.css',
        array('saw-terminal-base'),
        '3.0.0'
    );
    
    // CSS - Components (depends on base)
    wp_enqueue_style(
        'saw-terminal-components',
        $css_dir . 'terminal/components.css',
        array('saw-terminal-base'),
        '3.0.0'
    );
    
    // CSS - Training (depends on all)
    wp_enqueue_style(
        'saw-terminal-training',
        $css_dir . 'terminal/training.css',
        array('saw-terminal-base', 'saw-terminal-layout', 'saw-terminal-components'),
        '3.0.0'
    );
    
    // CSS - Old terminal.css (fallback compatibility - check if exists)
    $terminal_css_legacy = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/terminal.css';
    if (file_exists($terminal_css_legacy)) {
        wp_enqueue_style(
            'saw-terminal',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/terminal.css',
            array(),
            SAW_VISITORS_VERSION
        );
    }
    
    // JavaScript
    wp_enqueue_script('jquery');
    
    // Touch gestures (dependency for PDF viewer)
    wp_enqueue_script(
        'saw-touch-gestures',
        $js_dir . 'terminal/touch-gestures.js',
        array(),
        '3.0.0',
        true
    );
    
    // PDF viewer (depends on touch-gestures)
    wp_enqueue_script(
        'saw-pdf-viewer',
        $js_dir . 'terminal/pdf-viewer.js',
        array('saw-touch-gestures'),
        '3.0.0',
        true
    );

    //YOUTUBE / VIMEO VIDEO
	wp_enqueue_script('saw-video-player', $js_dir . 'terminal/video-player.js', array(), '3.0.0', true);
    
    // Old terminal.js (legacy.js in new structure)
    $terminal_js_legacy = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/assets/js/terminal/legacy.js';
    if (file_exists($terminal_js_legacy)) {
        wp_enqueue_script(
            'saw-terminal',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/terminal/legacy.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    } elseif (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/terminal.js')) {
        // Fallback to old location if legacy.js doesn't exist
        wp_enqueue_script(
            'saw-terminal',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/terminal.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
}
    

}