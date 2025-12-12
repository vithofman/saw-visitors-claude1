<?php
/**
 * Terminal Frontend Controller
 *
 * Main routing and session management for visitor terminal.
 * Handles multi-step check-in/out flow with language support.
 *
 * @package    SAW_Visitors
 * @subpackage Frontend/Terminal
 * @since      1.0.0
 * @version    3.2.0 - Added Info Portal email integration, production-ready
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
        if (!class_exists('SAW_Session_Manager')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/class-saw-oopp-public.php';
        
        $this->session = SAW_Session_Manager::instance();
        
        $this->load_context();
        $this->load_languages();
        $this->init_terminal_session();
        
        $this->current_step = $this->get_current_step();
    }
    
    /**
     * Load customer and branch context
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
        
        switch ($saw_user->role) {
            case 'super_admin':
                $this->customer_id = $saw_user->context_customer_id ?? $saw_user->customer_id;
                $this->branch_id = $saw_user->context_branch_id ?? $saw_user->branch_id;
                break;
                
            case 'terminal':
                $this->customer_id = $saw_user->customer_id;
                $this->branch_id = $saw_user->branch_id;
                break;
                
            default:
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
     * @since 2.0.0
     * @return void
     */
    private function load_languages() {
        global $wpdb;
        
        if (!$this->customer_id || !$this->branch_id) {
            $this->languages = [];
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
        
        $this->languages = [];
        foreach ($results as $row) {
            $this->languages[$row->language_code] = [
                'name' => $row->language_name,
                'flag' => $row->flag_emoji,
                'is_default' => (bool) $row->is_default,
            ];
        }
        
        if (empty($this->languages)) {
            if (class_exists('SAW_Logger')) {
                SAW_Logger::warning("No languages found for customer #{$this->customer_id}, branch #{$this->branch_id}");
            }
            
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
     * @since 1.0.0
     * @return void
     */
    private function init_terminal_session() {
        if (!$this->session->has('terminal_flow')) {
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
    }
    
    /**
     * Get current step from session and URL
     *
     * @since 1.0.0
     * @return string Current step identifier
     */
    private function get_current_step() {
        $path = get_query_var('saw_path');
        
        if (empty($path) || $path === '/' || $path === 'terminal') {
            return 'language';
        }
        
        if (!empty($path)) {
            $segments = explode('/', trim($path, '/'));
            
            if (count($segments) >= 1) {
                $step = $segments[count($segments) - 1];
                return $step;
            }
        }
        
        $flow = $this->session->get('terminal_flow');
        return $flow['step'] ?? 'language';
    }
    
    /**
     * Main render method
     *
     * @since 1.0.0
     * @return void
     */
    public function render() {
        $flow = $this->session->get('terminal_flow');
        
        if ($this->current_step === 'language') {
            if (!empty($flow['language']) || !empty($flow['action'])) {
                $this->reset_flow();
            }
        }
        
        $this->enqueue_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_post();
            return;
        }
        
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

            case 'checkout-select':
                $this->render_checkout_select();
                break;
                
            case 'checkout-search':
                $this->render_checkout_search();
                break;
            
            case 'checkout-confirm':
                $this->render_checkout_confirmation();
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
                
            case 'training-oopp':
                $this->render_training_oopp();
                break;
                
            case 'training-additional':
                $this->render_training_additional();
                break;

            case 'select-visitors':
                wp_redirect(home_url('/terminal/register/'));
                exit;
                
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
            'languages' => $this->languages,
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
        
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['visit_id'] ?? null;
        
        if ($visit_id) {
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
     *
     * @since 1.0.0
     * @return void
     */
    private function render_checkout_select() {
        global $wpdb;
        
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['checkout_visit_id'] ?? null;
        
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
            
            $flow['checkout_visitors'] = $visitors;
            $this->session->set('terminal_flow', $flow);
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
     * Render checkout confirmation dialog
     * 
     * @since 3.1.0
     * @return void
     */
    private function render_checkout_confirmation() {
        $flow = $this->session->get('terminal_flow');
        
        $visit_id = $flow['checkout_visit_id'] ?? null;
        $visitor_ids = $flow['pending_checkout_visitor_ids'] ?? [];
        
        if (!$visit_id || empty($visitor_ids)) {
            wp_redirect(home_url('/terminal/'));
            exit;
        }
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
        $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
        $visits_model = new SAW_Module_Visits_Model($visits_config);
        
        $visit_info = $visits_model->get_visit_info_for_checkout($visit_id);
        
        if (!$visit_info) {
            wp_redirect(home_url('/terminal/'));
            exit;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/checkout/confirmation-dialog.php';
        $this->render_template($template, [
            'visit_info' => $visit_info,
            'visitor_ids' => $visitor_ids,
            'flow' => $flow,
        ]);
    }

    /**
     * Render success page
     *
     * @since 1.0.0
     * @return void
     */
    private function render_success() {
        $flow = $this->session->get('terminal_flow');
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/success.php';
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
        
        $training_steps = $this->get_training_steps($visit_id, $language);
        
        $step_data = null;
        foreach ($training_steps as $step) {
            if ($step['type'] == 'video') {
                $step_data = $step['data'];
                break;
            }
        }
        
        if (!$step_data) {
            $this->move_to_next_training_step();
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/video.php';
        
        $this->render_template($template, [
            'video_url' => $step_data['video_url'],
            'is_invitation' => false,
            'flow' => $flow,
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
        
        $training_steps = $this->get_training_steps($visit_id, $language);
        
        $step_data = null;
        foreach ($training_steps as $step) {
            if ($step['type'] == 'map') {
                $step_data = $step['data'];
                break;
            }
        }
        
        if (!$step_data) {
            $this->move_to_next_training_step();
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/map.php';
        
        $this->render_template($template, [
            'pdf_path' => $step_data['pdf_path'],
            'is_invitation' => false,
            'flow' => $flow,
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
        
        $training_steps = $this->get_training_steps($visit_id, $language);
        
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
        
        $documents = [];
        if ($content_id) {
            $documents = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                 WHERE document_type = 'risks' AND reference_id = %d 
                 ORDER BY uploaded_at ASC",
                $content_id
            ), ARRAY_A);
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/risks.php';
        $this->render_template($template, [
            'risks_text' => $step_data['risks_text'],
            'documents' => $documents,
            'is_invitation' => false,
            'flow' => $flow,
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
        
        $training_steps = $this->get_training_steps($visit_id, $language);
        
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
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/department.php';
        $this->render_template($template, [
            'departments' => $dept_steps,
            'is_invitation' => false,
            'flow' => $flow,
        ]);
    }
    
    /**
     * Render training OOPP step
     *
     * @since 3.0.0
     * @return void
     */
    private function render_training_oopp() {
        $flow = $this->session->get('terminal_flow');
        $visit_id = $flow['visit_id'] ?? null;
        
        if (!$visit_id) {
            $this->move_to_next_training_step();
            return;
        }
        
        $oopp_items = [];
        if (class_exists('SAW_OOPP_Public')) {
            $oopp_items = SAW_OOPP_Public::get_for_visitor(
                $this->customer_id, 
                $this->branch_id, 
                $visit_id
            );
        }
        
        if (empty($oopp_items)) {
            $this->move_to_next_training_step();
            return;
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/oopp.php';
        $this->render_template($template, [
            'oopp_items' => $oopp_items,
            'is_invitation' => false,
            'flow' => $flow,
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
        
        $training_steps = $this->get_training_steps($visit_id, $language);
        
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
        
        $documents = [];
        if ($content_id) {
            $documents = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_training_documents 
                 WHERE document_type = 'additional' AND reference_id = %d 
                 ORDER BY uploaded_at ASC",
                $content_id
            ), ARRAY_A);
        }
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/additional.php';
        $this->render_template($template, [
            'additional_text' => $step_data['additional_text'],
            'documents' => $documents,
            'is_invitation' => false,
            'flow' => $flow,
        ]);
    }
    
    /**
     * Handle POST submissions
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_post() {
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
            
            case 'checkout_confirm':
                $this->handle_checkout_confirm();
                break;

            case 'submit_unified_registration':
                $this->handle_unified_registration();
                break;
                
            case 'submit_registration':
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
                
            case 'complete_training_oopp':
                $this->handle_training_oopp_complete();
                break;
                
            case 'complete_training_additional':
                $this->handle_training_additional_complete();
                break;
                
            case 'skip_training':
                $this->handle_skip_training();
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
     * @since 1.0.0
     * @return void
     */
    private function handle_pin_verification() {
    global $wpdb;
    
    // Rate limit check
    $rate_check = $this->check_pin_rate_limit();
    if (is_wp_error($rate_check)) {
        $this->set_error($rate_check->get_error_message());
        $this->render_pin_entry();
        return;
    }
    
    $pin = sanitize_text_field($_POST['pin'] ?? '');
        
        if (empty($pin) || strlen($pin) !== 6) {
            $this->set_error(__('Neplatný PIN kód', 'saw-visitors'));
            $this->render_pin_entry();
            return;
        }
        
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
    $this->record_failed_pin_attempt();
    $this->set_error(__('PIN kód je neplatný nebo již vypršel', 'saw-visitors'));
    $this->render_pin_entry();
    return;
}

// Success - clear attempts
$this->clear_pin_attempts();
        
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
        }
        
        // Smart extend - prodluž pouze pokud NOW+24h > současná expirace
$current_expiry_timestamp = !empty($visit['pin_expires_at']) 
    ? strtotime($visit['pin_expires_at']) 
    : 0;
$extended_timestamp = strtotime('+24 hours');

if ($extended_timestamp > $current_expiry_timestamp) {
    $new_expiry = date('Y-m-d H:i:s', $extended_timestamp);
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        ['pin_expires_at' => $new_expiry],
        ['id' => $visit['id']],
        ['%s'],
        ['%d']
    );
    error_log("[Terminal] PIN expiry extended from " . 
              ($visit['pin_expires_at'] ?? 'NULL') . " to {$new_expiry} for visit #{$visit['id']}");
}
        
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
        
        $flow = $this->session->get('terminal_flow');
        $flow['visit_id'] = $visit['id'];
        $flow['pin'] = $pin;
        $flow['type'] = 'planned';
        $flow['visitors'] = $visitors;
        $this->session->set('terminal_flow', $flow);
        
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
        if (strpos($url, 'youtube.com') !== false) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            if (isset($params['v'])) {
                return 'https://www.youtube.com/embed/' . $params['v'];
            }
        }
        
        if (strpos($url, 'youtu.be') !== false) {
            $path = parse_url($url, PHP_URL_PATH);
            $video_id = trim($path, '/');
            return 'https://www.youtube.com/embed/' . $video_id;
        }
        
        if (strpos($url, 'vimeo.com') !== false) {
            $path = parse_url($url, PHP_URL_PATH);
            $video_id = trim($path, '/');
            return 'https://player.vimeo.com/video/' . $video_id;
        }
        
        return $url;
    }
    
    /**
     * Get training steps for visitor
     *
     * @since 3.0.0
     * @param int $visit_id Visit ID
     * @param string $language_code Language code
     * @return array Training steps
     */
    private function get_training_steps($visit_id, $language_code) {
        global $wpdb;
        
        $steps = [];
        
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id, $language_code
        ));
        
        if (!$language_id) return $steps;
        
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_training_content 
             WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
            $this->customer_id, $this->branch_id, $language_id
        ), ARRAY_A);
        
        if (!$content) return $steps;
        
        $content_id = $content['id'];
        
        // Video
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
        
        // Map
        if (!empty($content['pdf_map_path'])) {
            $steps[] = ['type' => 'map', 'url' => 'training-map', 'data' => ['pdf_path' => $content['pdf_map_path']]];
        }
        
        // Risks
        if (!empty($content['risks_text'])) {
            $steps[] = ['type' => 'risks', 'url' => 'training-risks', 'data' => ['risks_text' => $content['risks_text']]];
        }
        
        // Departments
        $host_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d", $visit_id
        ));

        if (!empty($host_ids)) {
            $department_ids = [];
            
            foreach ($host_ids as $host_id) {
                $host_dept_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d", $host_id
                ));
                
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

        // OOPP
        if (class_exists('SAW_OOPP_Public')) {
            $has_oopp = SAW_OOPP_Public::has_oopp($this->customer_id, $this->branch_id, $visit_id);
            if ($has_oopp) {
                $steps[] = [
                    'type' => 'oopp',
                    'url' => 'training-oopp',
                    'data' => []
                ];
            }
        }

        // Additional
        if (!empty($content['additional_text'])) {
            $steps[] = ['type' => 'additional', 'url' => 'training-additional', 'data' => ['additional_text' => $content['additional_text']]];
        }

        return $steps;
    }
    
    /**
     * Handle unified registration submission
     *
     * @since 3.0.0
     * @updated 3.2.0 - Added Info Portal email integration
     * @return void
     */
    /**
 * Handle unified registration submission
 *
 * @since 3.0.0
 * @updated 3.2.0 - Added Info Portal email integration
 * @updated 5.1.1 - Fixed visit status update to in_progress with fallback mechanism
 * @return void
 */
private function handle_unified_registration() {
    global $wpdb;

    $flow = $this->session->get('terminal_flow');
    $is_planned = ($flow['type'] ?? '') === 'planned';
    $visit_id = $flow['visit_id'] ?? null;
    
    // ===================================
    // 1. VALIDACE FORMULÁŘE
    // ===================================
    
    $errors = [];
    
    $existing_visitor_ids = $_POST['existing_visitor_ids'] ?? [];
    $new_visitors = $_POST['new_visitors'] ?? [];

    if (empty($existing_visitor_ids) && empty($new_visitors)) {
        $errors[] = __('Musíte vybrat nebo zadat alespoň jednoho návštěvníka', 'saw-visitors');
    }
    
    if (!empty($new_visitors) && is_array($new_visitors)) {
        foreach ($new_visitors as $idx => $visitor) {
            $is_empty = empty($visitor['first_name']) && empty($visitor['last_name']) && empty($visitor['position']) && empty($visitor['email']) && empty($visitor['phone']);
            
            if ($is_empty) {
                continue;
            }
            
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

    if (!$is_planned) {
        $is_individual = isset($_POST['is_individual']) && $_POST['is_individual'] == '1';
        
        if (!$is_individual && empty($_POST['company_name'])) {
            $errors[] = __('Název firmy je povinný', 'saw-visitors');
        }
        
        if (empty($_POST['host_ids']) || !is_array($_POST['host_ids'])) {
            $errors[] = __('Musíte vybrat alespoň jednoho hostitele', 'saw-visitors');
        }
    }
    
    if (!empty($errors)) {
        $this->set_error(implode('<br>', $errors));
        $this->render_registration_form();
        return;
    }
    
    // ===================================
    // 2. VYTVOŘENÍ/POUŽITÍ VISIT
    // ===================================
    
    if ($is_planned) {
        if (!$visit_id) {
            $this->set_error(__('Chyba: Návštěva nebyla nalezena', 'saw-visitors'));
            $this->render_registration_form();
            return;
        }
        
        // ✅ OPRAVA v5.1.1: Kontrola aktuálního stavu a logování
        $current_visit = $wpdb->get_row($wpdb->prepare(
            "SELECT status, started_at FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        $update_data = [
            'status' => 'in_progress',
        ];
        
        // Nastav started_at pouze pokud ještě není nastaveno
        if (empty($current_visit['started_at'])) {
            $update_data['started_at'] = current_time('mysql');
        }
        
        $update_result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            $update_data,
            ['id' => $visit_id],
            empty($current_visit['started_at']) ? ['%s', '%s'] : ['%s'],
            ['%d']
        );
        
        // Logování pro debugging
        if ($update_result === false) {
            error_log("[SAW Terminal] CRITICAL: Failed to update visit #{$visit_id} to in_progress. DB Error: " . $wpdb->last_error);
        } elseif ($update_result === 0) {
            error_log("[SAW Terminal] INFO: Visit #{$visit_id} update affected 0 rows (was: {$current_visit['status']}, target: in_progress)");
        } else {
            error_log("[SAW Terminal] SUCCESS: Visit #{$visit_id} status changed from '{$current_visit['status']}' to 'in_progress'");
        }
        
    } else {
        // WALK-IN: Vytvoř novou visit
        
        $company_id = null;
        $is_individual = isset($_POST['is_individual']) && $_POST['is_individual'] == '1';
        
        if (!$is_individual) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
            $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
            $visits_model = new SAW_Module_Visits_Model($visits_config);

            $company_name = sanitize_text_field($_POST['company_name']);
            $company_id = $visits_model->find_or_create_company($this->branch_id, $company_name, $this->customer_id);
            
            if (is_wp_error($company_id)) {
                $this->set_error(__('Chyba při vytváření firmy: ', 'saw-visitors') . $company_id->get_error_message());
                $this->render_registration_form();
                return;
            }
        }
        
        $today = current_time('Y-m-d');
        
        $visit_data = [
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'company_id' => $company_id,
            'visit_type' => 'walk_in',
            'status' => 'in_progress',
            'planned_date_from' => $today,
            'planned_date_to' => $today,
            'started_at' => current_time('mysql'),
            'purpose' => null,
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_visits',
            $visit_data,
            ['%d', '%d', $company_id ? '%d' : '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            $this->set_error(__('Chyba při vytváření návštěvy', 'saw-visitors'));
            $this->render_registration_form();
            return;
        }
        
        $visit_id = $wpdb->insert_id;
        
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
        
        // ========================================
        // NOTIFICATION TRIGGER: walkin_host
        // Notifikace hostiteli o walk-in návštěvníkovi
        // ========================================
        foreach ($host_ids as $host_id) {
            if ($host_id > 0) {
                // Získej základní visitor data pro notifikaci (pokud je dostupné)
                $visitor_data = [];
                if (!empty($_POST['first_name']) && !empty($_POST['last_name'])) {
                    $visitor_data = [
                        'first_name' => sanitize_text_field($_POST['first_name']),
                        'last_name' => sanitize_text_field($_POST['last_name']),
                    ];
                }
                
                do_action('saw_walkin_host_notification', $visit_id, $host_id, $visitor_data);
            }
        }
    }
    
    // ===================================
    // 3. ZPRACOVÁNÍ NÁVŠTĚVNÍKŮ
    // ===================================
    
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
            $has_training_content = (
                !empty($content['video_url']) ||
                !empty($content['pdf_map_path']) ||
                !empty($content['risks_text']) ||
                !empty($content['additional_text'])
            );
            
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
    
    $visitor_ids = [];
    $any_needs_training = false;
    
    // ===================================
    // 3a. EXISTING VISITORS
    // ===================================
    if (!empty($existing_visitor_ids) && is_array($existing_visitor_ids)) {
        foreach ($existing_visitor_ids as $visitor_id) {
            $visitor_id = intval($visitor_id);
            
            $has_completed_training = !empty($wpdb->get_var($wpdb->prepare(
                "SELECT training_completed_at FROM {$wpdb->prefix}saw_visitors 
                 WHERE id = %d AND training_completed_at IS NOT NULL",
                $visitor_id
            )));
            
            $training_skip = isset($_POST['existing_training_skip'][$visitor_id]) ? 1 : 0;
            
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
            
            if (!$training_skip && !$has_completed_training && $has_training_content) {
                $update_data = [
                    'participation_status' => 'confirmed',
                    'current_status' => 'present',
                    'training_status' => $training_status,
                    'training_skipped' => 0,
                    'training_started_at' => current_time('mysql'),
                    'training_step_video' => 0,
                    'training_step_map' => 0,
                    'training_step_risks' => 0,
                    'training_step_additional' => 0,
                    'training_step_department' => 0,
                ];
                
                $wpdb->update(
                    $wpdb->prefix . 'saw_visitors',
                    $update_data,
                    ['id' => $visitor_id],
                    ['%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d'],
                    ['%d']
                );
            } else {
                $update_data = [
                    'participation_status' => 'confirmed',
                    'current_status' => 'present',
                    'training_status' => $training_status,
                    'training_skipped' => $training_skip,
                ];
                
                $wpdb->update(
                    $wpdb->prefix . 'saw_visitors',
                    $update_data,
                    ['id' => $visitor_id],
                    ['%s', '%s', '%s', '%d'],
                    ['%d']
                );
            }
            
            $visitor_ids[] = $visitor_id;
            
            $wpdb->insert(
                $wpdb->prefix . 'saw_visit_daily_logs',
                [
                    'customer_id' => $this->customer_id,
                    'branch_id' => $this->branch_id,
                    'visit_id' => $visit_id,
                    'visitor_id' => $visitor_id,
                    'log_date' => current_time('Y-m-d'),
                    'checked_in_at' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
            );
        }
    }
    
    // ===================================
    // 3b. NEW VISITORS
    // ===================================
    if (!empty($new_visitors) && is_array($new_visitors)) {
        foreach ($new_visitors as $idx => $visitor_data) {
            if (empty($visitor_data['first_name']) && empty($visitor_data['last_name'])) {
                continue;
            }
            
            $training_skipped = isset($visitor_data['training_skipped']) && $visitor_data['training_skipped'] == '1' ? 1 : 0;
            
            $training_status = 'pending';
            if ($training_skipped) {
                $training_status = 'skipped';
            } elseif (!$has_training_content) {
                $training_status = 'not_available';
            } else {
                $training_status = 'in_progress';
                $any_needs_training = true;
            }
            
            $visitor_insert = [
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'visit_id' => $visit_id,
                'first_name' => sanitize_text_field($visitor_data['first_name']),
                'last_name' => sanitize_text_field($visitor_data['last_name']),
                'position' => !empty($visitor_data['position']) ? sanitize_text_field($visitor_data['position']) : null,
                'email' => !empty($visitor_data['email']) ? sanitize_email($visitor_data['email']) : null,
                'phone' => !empty($visitor_data['phone']) ? sanitize_text_field($visitor_data['phone']) : null,
                'participation_status' => 'confirmed',
                'current_status' => 'present',
                'training_status' => $training_status,
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
            
            $wpdb->insert(
                $wpdb->prefix . 'saw_visit_daily_logs',
                [
                    'customer_id' => $this->customer_id,
                    'branch_id' => $this->branch_id,
                    'visit_id' => $visit_id,
                    'visitor_id' => $visitor_id,
                    'log_date' => current_time('Y-m-d'),
                    'checked_in_at' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
            );
        }
    }
    
    if (empty($visitor_ids)) {
        $this->set_error(__('Chyba: Nepodařilo se vytvořit žádné návštěvníky', 'saw-visitors'));
        $this->render_registration_form();
        return;
    }
    
    // ===================================
    // 3c. ✅ OPRAVA v5.1.1: ZÁLOŽNÍ MECHANISMUS PRO STATUS
    // ===================================
    // Pokud byly vytvořeny daily_logs, ale status stále není in_progress,
    // oprav to zde jako fallback
    $final_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $visit_id
    ));
    
    if (in_array($final_status, ['draft', 'pending', 'confirmed'])) {
        $fallback_result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            [
                'status' => 'in_progress',
                'started_at' => current_time('mysql'),
            ],
            ['id' => $visit_id],
            ['%s', '%s'],
            ['%d']
        );
        
        error_log("[SAW Terminal] FALLBACK: Force-updated visit #{$visit_id} to 'in_progress' (was: '{$final_status}', result: " . ($fallback_result !== false ? 'success' : 'failed') . ")");
    }
    // ===================================
    // END FALLBACK
    // ===================================
    
    // ===================================
    // 3d. ✅ OPRAVA v5.2.0: VYTVOŘENÍ SCHEDULE ZÁZNAMU
    // ===================================
    // Pokud návštěva nemá žádný schedule záznam, vytvoř ho s aktuálním datem a časem
    // Toto zajistí správné zobrazení v kalendáři
    $existing_schedule_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_schedules WHERE visit_id = %d",
        $visit_id
    ));
    
    if ($existing_schedule_count == 0) {
        $schedule_result = $wpdb->insert(
            $wpdb->prefix . 'saw_visit_schedules',
            [
                'visit_id' => $visit_id,
                'customer_id' => $this->customer_id,
                'branch_id' => $this->branch_id,
                'date' => current_time('Y-m-d'),
                'time_from' => current_time('H:i:s'),
                'time_to' => null,
                'sort_order' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );
        
        if ($schedule_result) {
            error_log("[SAW Terminal] Created schedule record for visit #{$visit_id} with check-in time " . current_time('H:i:s'));
        }
    }
    // ===================================
    // END SCHEDULE CREATION
    // ===================================
    
    // ===================================
    // 4. INFO PORTAL: Send emails (v3.2.0)
    // ===================================
    if (function_exists('saw_email')) {
    foreach ($visitor_ids as $vid) {
        $vid = intval($vid);
        
        // Získat nebo vygenerovat info_portal_token
        $token = $wpdb->get_var($wpdb->prepare(
            "SELECT info_portal_token FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
            $vid
        ));
        
        // Pokud token neexistuje, vygenerovat
        if (empty($token)) {
            $token = wp_generate_password(32, false);
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['info_portal_token' => $token],
                ['id' => $vid],
                ['%s'],
                ['%d']
            );
        }
        
        // Odeslat Info Portal email (pokud má visitor email)
        $visitor_email = $wpdb->get_var($wpdb->prepare(
            "SELECT email FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
            $vid
        ));
        
        if (!empty($visitor_email) && is_email($visitor_email)) {
            saw_email()->send_info_portal($vid, $token);
        }
    }
}
    // ===================================
    // END INFO PORTAL
    // ===================================
    
    // ===================================
    // 5. ULOŽENÍ DO SESSION
    // ===================================
    
    $flow['visit_id'] = $visit_id;
    $flow['visitor_id'] = $visitor_ids[0];
    $flow['visitor_ids'] = $visitor_ids;
    $flow['training_required'] = $any_needs_training;
    
    $this->session->set('terminal_flow', $flow);
    
    // ===================================
    // 6. REDIRECT
    // ===================================
    
    if (!$any_needs_training) {
        wp_redirect(home_url('/terminal/success/'));
    } else {
        $training_steps = $this->get_training_steps($visit_id, $flow['language']);
        
        if (!empty($training_steps) && isset($training_steps[0])) {
            $first_step_url = $training_steps[0]['url'];
            wp_redirect(home_url('/terminal/' . $first_step_url . '/'));
        } else {
            wp_redirect(home_url('/terminal/success/'));
        }
    }
    
    exit;
}

    /**
     * Handle checkout PIN verification
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkout_pin_verify() {
        global $wpdb;
        
        $pin = sanitize_text_field($_POST['pin'] ?? '');
        
        if (empty($pin) || strlen($pin) !== 6) {
            $this->set_error(__('Neplatný PIN kód', 'saw-visitors'));
            $this->render_checkout_pin();
            return;
        }
        
        $visit = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}saw_visits 
     WHERE pin_code = %s 
       AND customer_id = %d 
       AND branch_id = %d
       AND visit_type = 'planned'
       AND status != 'cancelled'
       AND (pin_expires_at IS NULL OR pin_expires_at >= NOW())
     LIMIT 1",
    $pin,
    $this->customer_id,
    $this->branch_id
), ARRAY_A);
        
        if (!$visit) {
            $this->set_error(__('PIN kód nenalezen nebo návštěva již ukončena', 'saw-visitors'));
            $this->render_checkout_pin();
            return;
        }
        
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
        
        if (empty($visitors)) {
            $this->set_error(__('Nikdo z této návštěvy není momentálně přítomen', 'saw-visitors'));
            $this->render_checkout_pin();
            return;
        }
        
        $flow = $this->session->get('terminal_flow');
        $flow['checkout_visit_id'] = $visit['id'];
        $flow['checkout_visitors'] = $visitors;
        $flow['step'] = 'checkout-select';
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/checkout-select/'));
        exit;
    }
    
    /**
     * Handle checkout via search
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkout_search() {
        $this->set_error(__('Vyhledávání ještě není implementováno', 'saw-visitors'));
        $this->render_checkout_search();
    }

    /**
     * Handle checkout completion
     * 
     * @since 1.0.0
     * @updated 3.1.0 - Added will_be_last_checkout check
     * @return void
     */
    private function handle_checkout_complete() {
        global $wpdb;
        
        $flow = $this->session->get('terminal_flow');
        
        $visitor_ids_input = $_POST['visitor_ids'] ?? [];
        
        if (is_string($visitor_ids_input)) {
            $visitor_ids = array_map('intval', explode(',', $visitor_ids_input));
        } else {
            $visitor_ids = array_map('intval', $visitor_ids_input);
        }
        
        $visitor_ids = array_filter($visitor_ids);
        
        if (empty($visitor_ids)) {
            $this->set_error(__('Musíte vybrat alespoň jednoho návštěvníka', 'saw-visitors'));
            
            if (!empty($flow['checkout_visit_id'])) {
                $this->render_checkout_select();
            } else {
                $this->render_checkout_search();
            }
            return;
        }
        
        $visit_id = $flow['checkout_visit_id'] ?? null;
        
        if (!$visit_id && !empty($visitor_ids[0])) {
            $visit_id = $wpdb->get_var($wpdb->prepare(
                "SELECT visit_id FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
                $visitor_ids[0]
            ));
        }
        
        if ($visit_id) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/model.php';
            $visitors_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/config.php';
            $visitors_model = new SAW_Module_Visitors_Model($visitors_config);
            
            $is_last_checkout = $visitors_model->will_be_last_checkout($visit_id, $visitor_ids);
            
            $visit_has_pin = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT pin_code FROM {$wpdb->prefix}saw_visits WHERE id = %d AND pin_code IS NOT NULL AND pin_code != ''",
                $visit_id
            ));
            
            if ($is_last_checkout && $visit_has_pin) {
                $flow['pending_checkout_visitor_ids'] = $visitor_ids;
                $flow['checkout_visit_id'] = $visit_id;
                $flow['step'] = 'checkout-confirm';
                $this->session->set('terminal_flow', $flow);
                
                wp_redirect(home_url('/terminal/checkout-confirm/'));
                exit;
            }
            
            if ($is_last_checkout && !$visit_has_pin) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_visits',
                    [
                        'status' => 'completed',
                        'completed_at' => current_time('mysql')
                    ],
                    ['id' => $visit_id],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
        
        $this->perform_checkout($visitor_ids);
        
        $this->reset_flow();
        wp_redirect(home_url('/terminal/success/?action=checkout'));
        exit;
    }
    
    /**
     * Handle checkout confirmation form submission
     * 
     * @since 3.1.0
     * @return void
     */
    private function handle_checkout_confirm() {
        $action = sanitize_text_field($_POST['checkout_action'] ?? '');
        $visitor_ids_input = $_POST['visitor_ids'] ?? '';
        $visit_id = intval($_POST['visit_id'] ?? 0);
        
        $visitor_ids = array_map('intval', explode(',', $visitor_ids_input));
        $visitor_ids = array_filter($visitor_ids);
        
        if (empty($visitor_ids) || !$visit_id) {
            $this->set_error(__('Chybějící data', 'saw-visitors'));
            wp_redirect(home_url('/terminal/'));
            exit;
        }
        
        $this->perform_checkout($visitor_ids);
        
        if ($action === 'complete') {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
            $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
            $visits_model = new SAW_Module_Visits_Model($visits_config);
            
            $visits_model->complete_visit($visit_id);
        }
        
        $this->reset_flow();
        wp_redirect(home_url('/terminal/success/?action=checkout'));
        exit;
    }
    
    /**
     * Perform actual checkout for visitors
     * 
     * @since 3.1.0
     * @param array $visitor_ids Array of visitor IDs to check out
     * @return void
     */
    private function perform_checkout($visitor_ids) {
        global $wpdb;
        
        $checkout_time = current_time('mysql');
        
        foreach ($visitor_ids as $visitor_id) {
            $visitor_id = intval($visitor_id);
            
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
                continue;
            }
            
            $wpdb->update(
                $wpdb->prefix . 'saw_visit_daily_logs',
                ['checked_out_at' => $checkout_time],
                ['id' => $log_id],
                ['%s'],
                ['%d']
            );
            
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                [
                    'current_status' => 'checked_out',
                    'last_checkout_at' => $checkout_time,
                ],
                ['id' => $visitor_id],
                ['%s', '%s'],
                ['%d']
            );
        }
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
        
        $steps = $this->get_training_steps($visit_id, $language);
        
        $path = get_query_var('saw_path');
        $current_step_type = null;
        
        if (strpos($path, 'training-video') !== false) {
            $current_step_type = 'video';
        } elseif (strpos($path, 'training-map') !== false) {
            $current_step_type = 'map';
        } elseif (strpos($path, 'training-risks') !== false) {
            $current_step_type = 'risks';
        } else if (strpos($path, 'training-department') !== false) {
            $current_step_type = 'department';
        } elseif (strpos($path, 'training-oopp') !== false) {
            $current_step_type = 'oopp';
        } elseif (strpos($path, 'training-additional') !== false) {
            $current_step_type = 'additional';
        }
        
        $current_index = -1;
        
        if ($current_step_type === 'department') {
            foreach ($steps as $index => $step) {
                if ($step['type'] === 'department') {
                    $current_index = $index;
                    break;
                }
            }
            
            $next_index = $current_index + 1;
            while ($next_index < count($steps) && $steps[$next_index]['type'] === 'department') {
                $next_index++;
            }
        } else {
            foreach ($steps as $index => $step) {
                if ($step['type'] === $current_step_type) {
                    $current_index = $index;
                    break;
                }
            }
            $next_index = $current_index + 1;
        }
        
        if ($next_index < count($steps)) {
            $next_step = $steps[$next_index];
            wp_redirect(home_url('/terminal/' . $next_step['url'] . '/'));
            exit;
        } else {
            $this->complete_training($flow);
        }
    }

    /**
     * Complete training and mark visitor
     *
     * @since 3.0.0
     * @param array $flow Session flow data
     * @return void
     */
    private function complete_training($flow) {
        $visitor_ids = $flow['visitor_ids'] ?? [];
        
        if (!empty($visitor_ids)) {
            global $wpdb;
            foreach ($visitor_ids as $vid) {
                $vid = intval($vid);
                
                $wpdb->update(
                    $wpdb->prefix . 'saw_visitors',
                    [
                        'training_completed_at' => current_time('mysql'),
                        'training_status' => 'completed',
                    ],
                    ['id' => $vid],
                    ['%s', '%s'],
                    ['%d']
                );
                
                // ========================================
                // NOTIFICATION TRIGGER: training_completed
                // Notifikace o dokončení školení návštěvníkem
                // ========================================
                $visitor = $wpdb->get_row($wpdb->prepare(
                    "SELECT visit_id FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
                    $vid
                ), ARRAY_A);
                
                if ($visitor && !empty($visitor['visit_id'])) {
                    // Parametry: $visitor_id, $training_id (null), $visit_id
                    do_action('saw_training_completed', $vid, null, intval($visitor['visit_id']));
                }
            }
        }
        
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }    
    
    /**
     * Handle training video completion
     *
     * @since 1.0.0
     * @return void
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
        }
        $this->move_to_next_training_step();
    }

    /**
     * Handle training map completion
     *
     * @since 1.0.0
     * @return void
     */
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
        }
        $this->move_to_next_training_step();
    }

    /**
     * Handle training risks completion
     *
     * @since 1.0.0
     * @return void
     */
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
        }
        $this->move_to_next_training_step();
    }

    /**
     * Handle training department completion
     *
     * @since 1.0.0
     * @return void
     */
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
        }
        $this->move_to_next_training_step();
    }

    /**
     * Handle training OOPP completion
     *
     * @since 3.0.0
     * @return void
     */
    private function handle_training_oopp_complete() {
        $this->move_to_next_training_step();
    }

    /**
     * Handle training additional completion
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_training_additional_complete() {
        $flow = $this->session->get('terminal_flow');
        $visitor_ids = $flow['visitor_ids'] ?? [];
        
        if (!empty($visitor_ids)) {
            global $wpdb;
            foreach ($visitor_ids as $vid) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_visitors',
                    [
                        'training_step_additional' => 1,
                        'training_completed_at' => current_time('mysql'),
                        'training_status' => 'completed',
                    ],
                    ['id' => intval($vid)],
                    ['%s', '%s'],
                    ['%d']
                );
            }
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
        if (!file_exists($template)) {
            wp_die("Template not found: {$template}");
        }
        
        extract($data);
        
        $error = $this->session->get('terminal_error');
        $this->session->unset('terminal_error');
        
        $flow = $this->session->get('terminal_flow');
        
        include SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/layout-header.php';
        
        include $template;
        
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
        $css_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/css/';
        $js_dir = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/';
        
        wp_enqueue_style(
            'saw-terminal-base',
            $css_dir . 'terminal/base.css',
            array(),
            '3.0.0'
        );
        
        wp_enqueue_style(
            'saw-terminal-layout',
            $css_dir . 'terminal/layout.css',
            array('saw-terminal-base'),
            '3.0.0'
        );
        
        wp_enqueue_style(
            'saw-terminal-components',
            $css_dir . 'terminal/components.css',
            array('saw-terminal-base'),
            '3.0.0'
        );
        
        wp_enqueue_style(
            'saw-terminal-pages',
            $css_dir . 'terminal/pages.css',
            array('saw-terminal-base', 'saw-terminal-layout', 'saw-terminal-components'),
            '4.0.5'
        );
        
        $terminal_css_legacy = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/terminal.css';
        if (file_exists($terminal_css_legacy)) {
            wp_enqueue_style(
                'saw-terminal',
                SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/terminal.css',
                array(),
                SAW_VISITORS_VERSION
            );
        }
        
        wp_enqueue_script('jquery');
        
        wp_enqueue_script(
            'saw-touch-gestures',
            $js_dir . 'terminal/touch-gestures.js',
            array(),
            '3.0.0',
            true
        );
        
        wp_enqueue_script(
            'saw-pdf-viewer',
            $js_dir . 'terminal/pdf-viewer.js',
            array('saw-touch-gestures'),
            '3.0.0',
            true
        );

        wp_enqueue_script('saw-video-player', $js_dir . 'terminal/video-player.js', array(), '3.0.0', true);
        
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
            wp_enqueue_script(
                'saw-terminal',
                SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/terminal.js',
                array('jquery'),
                SAW_VISITORS_VERSION,
                true
            );
        }

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
     * Handle skip training
     * 
     * @since 1.0.0
     * @return void
     */
    private function handle_skip_training() {
        $flow = $this->session->get('terminal_flow');
        $flow['training_viewed'] = true;
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }

    /**
 * Check PIN attempt rate limiting
 * 
 * @since 3.6.0
 * @return true|WP_Error
 */
private function check_pin_rate_limit() {
    $ip = $this->get_client_ip();
    $cache_key = 'saw_pin_attempts_' . $this->customer_id . '_' . md5($ip);
    
    $attempts = get_transient($cache_key);
    
    if ($attempts === false) {
        return true;
    }
    
    $max_attempts = 5;
    
    if (intval($attempts) >= $max_attempts) {
        $remaining = $this->get_transient_ttl($cache_key);
        $minutes = max(1, ceil($remaining / 60));
        
        return new WP_Error(
            'rate_limited',
            sprintf('Příliš mnoho neúspěšných pokusů. Zkuste to za %d minut.', $minutes)
        );
    }
    
    return true;
}

/**
 * Record failed PIN attempt
 * 
 * @since 3.6.0
 */
private function record_failed_pin_attempt() {
    $ip = $this->get_client_ip();
    $cache_key = 'saw_pin_attempts_' . $this->customer_id . '_' . md5($ip);
    
    $attempts = get_transient($cache_key);
    $attempts = ($attempts === false) ? 1 : intval($attempts) + 1;
    
    set_transient($cache_key, $attempts, 15 * MINUTE_IN_SECONDS);
}

/**
 * Clear PIN attempts on success
 * 
 * @since 3.6.0
 */
private function clear_pin_attempts() {
    $ip = $this->get_client_ip();
    $cache_key = 'saw_pin_attempts_' . $this->customer_id . '_' . md5($ip);
    delete_transient($cache_key);
}

/**
 * Get client IP address
 * 
 * @since 3.6.0
 * @return string
 */
private function get_client_ip() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Get transient TTL
 * 
 * @since 3.6.0
 * @param string $key
 * @return int
 */
private function get_transient_ttl($key) {
    $timeout = get_option('_transient_timeout_' . $key);
    return $timeout ? max(0, intval($timeout) - time()) : 0;
}
}