<?php
/**
 * Terminal Frontend Controller
 *
 * Main routing and session management for visitor terminal.
 * Handles multi-step check-in/out flow with language support.
 *
 * CHANGES v2.0:
 * - ✅ Načítá jazyky z DB podle customer_id a branch_id
 * - ✅ Podporuje terminal role (má pevné branch_id)
 * - ✅ Podporuje super_admin s context_customer_id/context_branch_id
 *
 * @package    SAW_Visitors
 * @subpackage Frontend/Terminal
 * @since      1.0.0
 * @version    2.0.0
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
        error_log("=== TERMINAL DEBUG ===");
        error_log("REQUEST_URI: {$request_uri}");
        error_log("saw_path query var: '{$path}'");
        error_log("current_step: {$this->current_step}");
        
        // ✅ Reset flow when returning to language step via home button
        if ($this->current_step === 'language') {
            $flow = $this->session->get('terminal_flow');
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
        switch ($this->current_step) {
            case 'language':
                $this->render_language_selection();
                break;
                
            case 'action':
                $this->render_action_choice();
                break;
                
            case 'checkin':
                $this->render_checkin_type();
                break;
                
            case 'checkout':
                $this->render_checkout_method();
                break;
                
            case 'pin-entry':
                $this->render_pin_entry();
                break;
                
            case 'register':
                $this->render_registration_form();
                break;
                
            case 'checkout-pin':
                $this->render_checkout_pin();
                break;
                
            case 'checkout-search':
                $this->render_checkout_search();
                break;
                
            case 'success':
                $this->render_success();
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
                
            default:
                $this->reset_flow();
                $this->render_language_selection();
                break;
        }
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
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/video.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render training map step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_map() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/map.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render training risks step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_risks() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/risks.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render training department step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_department() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/department.php';
        $this->render_template($template, []);
    }
    
    /**
     * Render training additional step
     *
     * @since 1.0.0
     * @return void
     */
    private function render_training_additional() {
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/training/additional.php';
        $this->render_template($template, []);
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
     * Handle registration submission
     *
     * TODO: Implement actual DB save
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_registration_submission() {
        // TODO: Save visitor to database
        
        $this->set_error(__('Registrace ještě není implementována', 'saw-visitors'));
        $this->render_registration_form();
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
     * Training step completion handlers (stubs for now)
     */
    private function handle_training_video_complete() {
        // TODO: Mark video as completed
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    private function handle_training_map_complete() {
        // TODO: Mark map as completed
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    private function handle_training_risks_complete() {
        // TODO: Mark risks as completed
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    private function handle_training_department_complete() {
        // TODO: Mark department as completed
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    private function handle_training_additional_complete() {
        // TODO: Mark additional as completed
        wp_redirect(home_url('/terminal/success/'));
        exit;
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
        // Extract data for template
        extract($data);
        
        // Get error message if any
        $error = $this->session->get('terminal_error');
        $this->session->unset('terminal_error');
        
        // ✅ Provide flow to layouts (header/footer need it)
        $flow = $this->session->get('terminal_flow');
        
        // Start terminal layout
        include SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/layout-header.php';
        
        // Include step template
        include $template;
        
        // End terminal layout
        include SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/layout-footer.php';
        
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