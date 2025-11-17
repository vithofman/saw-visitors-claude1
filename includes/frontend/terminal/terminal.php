<?php
/**
 * Terminal Frontend Controller
 *
 * Main routing and session management for visitor terminal.
 * Handles multi-step check-in/out flow with language support.
 *
 * Flow:
 * 1. Language selection (cs/en/uk)
 * 2. Action choice (check-in/check-out)
 * 3. Check-in paths:
 *    - Planned visit (PIN entry)
 *    - Walk-in visit (registration form)
 * 4. Check-out paths:
 *    - PIN based (list all visitors for that visit)
 *    - Search based (find by name)
 *
 * @package    SAW_Visitors
 * @subpackage Frontend/Terminal
 * @since      1.0.0
 * @version    1.0.0
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
     * Available languages
     *
     * @var array
     */
    private $languages = [
        'cs' => 'Čeština',
        'en' => 'English',
        'uk' => 'Українська',
    ];
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->session = SAW_Session_Manager::instance();
        $this->init_terminal_session();
        $this->current_step = $this->get_current_step();
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
        $flow['step'] = $action;
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/' . $action . '/'));
        exit;
    }
    
    /**
     * Handle check-in type selection (planned/walkin)
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
        $this->session->set('terminal_flow', $flow);
        
        if ($type === 'planned') {
            $flow['step'] = 'pin-entry';
            $this->session->set('terminal_flow', $flow);
            wp_redirect(home_url('/terminal/checkin/pin-entry/'));
        } else {
            $flow['step'] = 'register';
            $this->session->set('terminal_flow', $flow);
            wp_redirect(home_url('/terminal/checkin/register/'));
        }
        exit;
    }
    
    /**
     * Handle PIN verification
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_pin_verification() {
        $pin = sanitize_text_field($_POST['pin'] ?? '');
        
        if (empty($pin)) {
            $this->set_error(__('Zadejte PIN kód', 'saw-visitors'));
            $this->render_pin_entry();
            return;
        }
        
        // TODO: Verify PIN against database
        // For now, mock success
        $flow = $this->session->get('terminal_flow');
        $flow['pin'] = $pin;
        $flow['visit_id'] = 123; // TODO: Get from DB
        $flow['step'] = 'success';
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    /**
     * Handle registration form submission
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_registration_submission() {
        // TODO: Validate and save registration data
        
        $flow = $this->session->get('terminal_flow');
        $flow['step'] = 'success';
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    /**
     * Handle checkout via PIN
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkout_pin() {
        // TODO: Process checkout
        
        $flow = $this->session->get('terminal_flow');
        $flow['step'] = 'success';
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/success/'));
        exit;
    }
    
    /**
     * Handle checkout via search
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_checkout_search() {
        // TODO: Process search and checkout
        
        $flow = $this->session->get('terminal_flow');
        $flow['step'] = 'success';
        $this->session->set('terminal_flow', $flow);
        
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
        $visitor_id = $this->session->get('terminal_flow')['visitor_id'] ?? null;
        
        if ($visitor_id) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_video' => 1],
                ['id' => $visitor_id],
                ['%d'],
                ['%d']
            );
        }
        
        wp_redirect(home_url('/terminal/training-map/'));
        exit;
    }
    
    /**
     * Handle training map completion
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_training_map_complete() {
        $visitor_id = $this->session->get('terminal_flow')['visitor_id'] ?? null;
        
        if ($visitor_id) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_map' => 1],
                ['id' => $visitor_id],
                ['%d'],
                ['%d']
            );
        }
        
        wp_redirect(home_url('/terminal/training-risks/'));
        exit;
    }
    
    /**
     * Handle training risks completion
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_training_risks_complete() {
        $visitor_id = $this->session->get('terminal_flow')['visitor_id'] ?? null;
        
        if ($visitor_id) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_risks' => 1],
                ['id' => $visitor_id],
                ['%d'],
                ['%d']
            );
        }
        
        wp_redirect(home_url('/terminal/training-department/'));
        exit;
    }
    
    /**
     * Handle training department completion
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_training_department_complete() {
        $visitor_id = $this->session->get('terminal_flow')['visitor_id'] ?? null;
        
        if ($visitor_id) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                ['training_step_department' => 1],
                ['id' => $visitor_id],
                ['%d'],
                ['%d']
            );
        }
        
        wp_redirect(home_url('/terminal/training-additional/'));
        exit;
    }
    
    /**
     * Handle training additional completion (final step)
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_training_additional_complete() {
        $visitor_id = $this->session->get('terminal_flow')['visitor_id'] ?? null;
        
        if ($visitor_id) {
            global $wpdb;
            
            // Mark final step complete
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                [
                    'training_step_additional' => 1,
                    'training_completed_at' => current_time('mysql'),
                ],
                ['id' => $visitor_id],
                ['%d', '%s'],
                ['%d']
            );
        }
        
        // Redirect to success
        $flow = $this->session->get('terminal_flow');
        $flow['step'] = 'success';
        $this->session->set('terminal_flow', $flow);
        
        wp_redirect(home_url('/terminal/success/'));
        exit;
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
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal/steps/4-register.php';
        $this->render_template($template, []);
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
     * Render template with terminal layout
     *
     * @since 1.0.0
     * @param string $template Template file path
     * @param array  $data     Data to pass to template
     * @return void
     */
    private function render_template($template, $data = []) {
        if (!file_exists($template)) {
            wp_die('Terminal template not found: ' . $template);
        }
        
        $flow = $this->session->get('terminal_flow');
        $error = $this->session->get('terminal_error');
        
        // Clear error after displaying
        if ($error) {
            $this->session->unset('terminal_error');
        }
        
        // Extract data for template
        extract($data);
        
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
