<?php
/**
 * Visitor Info Portal Controller
 * 
 * Main controller handling all visitor info portal logic:
 * - Token validation
 * - Language selection
 * - Training flow (reuses shared templates)
 * - Summary view
 * - Progress tracking
 * 
 * @package SAW_Visitors
 * @since 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitor_Info_Controller {
    
    /**
     * Access token (64 chars)
     * @var string
     */
    private $token;
    
    /**
     * Current step (language, training-video, summary, etc.)
     * @var string
     */
    private $step;
    
    /**
     * Visitor data from database
     * @var array
     */
    private $visitor;
    
    /**
     * Customer ID
     * @var int
     */
    private $customer_id;
    
    /**
     * Branch ID
     * @var int
     */
    private $branch_id;
    
    /**
     * Visit ID
     * @var int
     */
    private $visit_id;
    
    /**
     * Current language code (cs, en, sk, uk)
     * @var string
     */
    private $language = 'cs';
    
    /**
     * Session data reference
     * @var array
     */
    private $session;
    
    /**
     * Visitors model instance
     * @var SAW_Module_Visitors_Model
     */
    private $model;
    
    /**
     * Constructor
     * 
     * @param string $token Access token
     * @param string $step Current step
     */
    public function __construct($token, $step = '') {
        $this->token = $token;
        $this->step = $step;
        
        // Initialize PHP session
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // Create unique session key for this token
        $session_key = 'saw_visitor_info_' . substr($token, 0, 16);
        
        if (!isset($_SESSION[$session_key]) || !is_array($_SESSION[$session_key])) {
            $_SESSION[$session_key] = array();
        }
        
        $this->session = &$_SESSION[$session_key];
    }
    
    /**
     * Initialize controller and handle request
     */
    public function init() {
        // Load model
        $this->load_model();
        
        // Get visitor by token
        $this->visitor = $this->model->get_visitor_by_info_token($this->token);
        
        // Validate visitor exists
        if (!$this->visitor) {
            $this->render_error('not_found');
            return;
        }
        
        // Validate token is still valid
        if (!$this->model->is_info_portal_token_valid($this->visitor)) {
            $this->render_error('expired');
            return;
        }
        
        // Set context IDs
        $this->customer_id = (int) $this->visitor['customer_id'];
        $this->branch_id = (int) $this->visitor['branch_id'];
        $this->visit_id = (int) $this->visitor['visit_id'];
        
        // Get language from URL parameter or session
        $this->resolve_language();
        
        // Enqueue styles
        $this->enqueue_assets();
        
        // Handle POST actions first
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_post_actions();
        }
        
        // Route to appropriate view
        $this->route();
    }
    
    /**
     * Load visitors model
     */
    private function load_model() {
        if (!class_exists('SAW_Module_Visitors_Model')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/model.php';
        }
        
        $config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/config.php';
        $this->model = new SAW_Module_Visitors_Model($config);
    }
    
    /**
     * Resolve current language from URL or session
     */
    private function resolve_language() {
        // Priority 1: URL parameter
        if (isset($_GET['lang']) && in_array($_GET['lang'], array('cs', 'en', 'sk', 'uk'))) {
            $this->language = sanitize_key($_GET['lang']);
            $this->session['language'] = $this->language;
            return;
        }
        
        // Priority 2: Session
        if (!empty($this->session['language'])) {
            $this->language = $this->session['language'];
            return;
        }
        
        // Default: cs
        $this->language = 'cs';
    }
    
    /**
     * Enqueue CSS and JS assets
     */
    private function enqueue_assets() {
        // Main CSS
        wp_enqueue_style(
            'saw-visitor-info',
            SAW_VISITORS_PLUGIN_URL . 'includes/frontend/visitor-info/visitor-info.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        // Terminal pages CSS (for shared templates)
        $pages_css = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/css/pages.css';
        wp_enqueue_style('saw-terminal-pages', $pages_css, array(), SAW_VISITORS_VERSION);
    }
    
    /**
     * Main routing logic
     */
    private function route() {
        // No language selected yet? Show language selection
        if (empty($this->session['language']) && $this->step !== 'language') {
            wp_redirect($this->get_url('language'));
            exit;
        }
        
        // Check training completion status
        $training_completed = !empty($this->visitor['training_completed_at']);
        
        // Route based on step
        switch ($this->step) {
            case 'language':
                $this->render_language_select();
                break;
                
            case 'summary':
                $this->render_summary();
                break;
                
            case 'training-video':
            case 'training-map':
            case 'training-risks':
            case 'training-department':
            case 'training-oopp':
            case 'training-additional':
                if ($training_completed) {
                    // Already completed - redirect to summary
                    wp_redirect($this->get_url('summary'));
                    exit;
                }
                $this->render_training_step();
                break;
                
            default:
                // Main entry point - decide where to go
                if ($training_completed) {
                    wp_redirect($this->get_url('summary'));
                } else {
                    // Start or continue training
                    $this->redirect_to_current_training_step();
                }
                exit;
        }
    }
    
    /**
     * Handle POST form submissions
     */
    private function handle_post_actions() {
        $action = isset($_POST['visitor_info_action']) ? sanitize_key($_POST['visitor_info_action']) : '';
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['visitor_info_nonce'] ?? '', 'saw_visitor_info_step')) {
            return;
        }
        
        switch ($action) {
            case 'select_language':
                $this->handle_language_selection();
                break;
                
            case 'complete_training_video':
                $this->handle_training_step_complete('video');
                break;
                
            case 'complete_training_map':
                $this->handle_training_step_complete('map');
                break;
                
            case 'complete_training_risks':
                $this->handle_training_step_complete('risks');
                break;
                
            case 'complete_training_department':
                $this->handle_training_step_complete('department');
                break;
                
            case 'complete_training_oopp':
                $this->handle_training_step_complete('oopp');
                break;
                
            case 'complete_training_additional':
                $this->handle_training_step_complete('additional');
                break;
                
            case 'skip_training':
                $this->handle_skip_training();
                break;
        }
    }
    
    /**
     * Handle language selection form
     */
    private function handle_language_selection() {
        $lang = isset($_POST['language']) ? sanitize_key($_POST['language']) : 'cs';
        
        // Validate language
        $available = $this->get_available_languages();
        $valid_codes = array_column($available, 'language_code');
        
        if (!in_array($lang, $valid_codes)) {
            $lang = !empty($valid_codes[0]) ? $valid_codes[0] : 'cs';
        }
        
        // Save to session
        $this->session['language'] = $lang;
        $this->language = $lang;
        
        // Redirect based on training status
        if (!empty($this->visitor['training_completed_at'])) {
            wp_redirect($this->get_url('summary'));
        } else {
            $this->redirect_to_current_training_step();
        }
        exit;
    }
    
    /**
     * Handle training step completion
     * 
     * @param string $step_type Step type (video, map, risks, etc.)
     */
    private function handle_training_step_complete($step_type) {
        global $wpdb;
        
        $visitor_id = (int) $this->visitor['id'];
        
        // Map step type to column name
        $column_map = array(
            'video' => 'training_step_video',
            'map' => 'training_step_map',
            'risks' => 'training_step_risks',
            'department' => 'training_step_department',
            'additional' => 'training_step_additional',
            // Note: oopp doesn't have a separate column yet
        );
        
        $column = isset($column_map[$step_type]) ? $column_map[$step_type] : null;
        
        // Mark step as complete in DB
        if ($column) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                array($column => 1),
                array('id' => $visitor_id),
                array('%d'),
                array('%d')
            );
        }
        
        // Set training_started_at if this is first step
        if (empty($this->visitor['training_started_at'])) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                array(
                    'training_started_at' => current_time('mysql'),
                    'training_status' => 'in_progress'
                ),
                array('id' => $visitor_id),
                array('%s', '%s'),
                array('%d')
            );
        }
        
        // Move to next step or complete
        $this->move_to_next_training_step($step_type);
    }
    
    /**
     * Handle skip training action
     */
    private function handle_skip_training() {
        // Mark training as skipped/completed and go to summary
        $this->complete_training();
    }
    
    /**
     * Move to next training step or complete training
     * 
     * @param string $current_type Current step type
     */
    private function move_to_next_training_step($current_type) {
        $steps = $this->get_available_training_steps();
        
        // Find current step index
        $current_index = -1;
        foreach ($steps as $i => $step) {
            if ($step['type'] === $current_type) {
                $current_index = $i;
                break;
            }
        }
        
        // Check if there's a next step
        if ($current_index !== -1 && isset($steps[$current_index + 1])) {
            $next = $steps[$current_index + 1];
            wp_redirect($this->get_url($next['step']));
        } else {
            // No more steps - complete training
            $this->complete_training();
        }
        exit;
    }
    
    /**
     * Mark training as completed in database
     */
    private function complete_training() {
        global $wpdb;
        
        $visitor_id = (int) $this->visitor['id'];
        
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            array(
                'training_completed_at' => current_time('mysql'),
                'training_status' => 'completed'
            ),
            array('id' => $visitor_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Redirect to summary
        wp_redirect($this->get_url('summary'));
        exit;
    }
    
    /**
     * Redirect to current (first incomplete) training step
     */
    private function redirect_to_current_training_step() {
        $steps = $this->get_available_training_steps();
        
        // No steps available - complete immediately
        if (empty($steps)) {
            $this->complete_training();
            return;
        }
        
        // Refresh visitor data to get latest step status
        global $wpdb;
        $fresh = $wpdb->get_row($wpdb->prepare(
            "SELECT training_step_video, training_step_map, training_step_risks,
                    training_step_department, training_step_additional
             FROM {$wpdb->prefix}saw_visitors 
             WHERE id = %d",
            $this->visitor['id']
        ), ARRAY_A);
        
        if ($fresh) {
            $this->visitor = array_merge($this->visitor, $fresh);
        }
        
        // Map step types to DB columns
        $step_columns = array(
            'video' => 'training_step_video',
            'map' => 'training_step_map',
            'risks' => 'training_step_risks',
            'department' => 'training_step_department',
            'additional' => 'training_step_additional',
            'oopp' => null, // No column for OOPP - always show if available
        );
        
        // Find first incomplete step
        foreach ($steps as $step) {
            $type = $step['type'];
            $column = isset($step_columns[$type]) ? $step_columns[$type] : null;
            
            // If no column exists or column value is 0/empty, this is current step
            if (!$column || empty($this->visitor[$column])) {
                wp_redirect($this->get_url($step['step']));
                exit;
            }
        }
        
        // All steps complete - mark training as done
        $this->complete_training();
    }
    
    /**
     * Get available training steps for current language
     * 
     * Returns only steps that have content configured.
     * 
     * @return array Array of step definitions
     */
    private function get_available_training_steps() {
        global $wpdb;
        
        $steps = array();
        
        // Get language ID
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $this->language
        ));
        
        if (!$language_id) {
            return $steps;
        }
        
        // Get training content record
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
        
        $content_id = (int) $content['id'];
        
        // 1. Video step
        if (!empty($content['video_url'])) {
            $steps[] = array('type' => 'video', 'step' => 'training-video');
        }
        
        // 2. Map step
        if (!empty($content['pdf_map_path'])) {
            $steps[] = array('type' => 'map', 'step' => 'training-map');
        }
        
        // 3. Risks step
        if (!empty($content['risks_text'])) {
            $steps[] = array('type' => 'risks', 'step' => 'training-risks');
        }
        
        // 4. Department step - check if any department has content
        $dept_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_department_content 
             WHERE training_content_id = %d 
             AND text_content IS NOT NULL AND text_content != ''",
            $content_id
        ));
        if ($dept_count > 0) {
            $steps[] = array('type' => 'department', 'step' => 'training-department');
        }
        
        // 5. OOPP step - check if visitor has OOPP items
        if (!class_exists('SAW_OOPP_Public')) {
            $oopp_file = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/class-saw-oopp-public.php';
            if (file_exists($oopp_file)) {
                require_once $oopp_file;
            }
        }
        
        if (class_exists('SAW_OOPP_Public')) {
            $has_oopp = SAW_OOPP_Public::has_oopp(
                $this->customer_id,
                $this->branch_id,
                $this->visit_id
            );
            if ($has_oopp) {
                $steps[] = array('type' => 'oopp', 'step' => 'training-oopp');
            }
        }
        
        // 6. Additional step - check text or documents
        $has_additional_text = !empty($content['additional_text']);
        $additional_docs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents 
             WHERE document_type = 'additional' AND reference_id = %d",
            $content_id
        ));
        
        if ($has_additional_text || $additional_docs > 0) {
            $steps[] = array('type' => 'additional', 'step' => 'training-additional');
        }
        
        return $steps;
    }
    
    /**
     * Get training content for current language
     * 
     * @return array|null Content array or null
     */
    private function get_training_content() {
        global $wpdb;
        
        // Get language ID
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $this->language
        ));
        
        if (!$language_id) {
            return null;
        }
        
        // Get main content
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_training_content 
             WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
            $this->customer_id,
            $this->branch_id,
            $language_id
        ), ARRAY_A);
        
        if (!$content) {
            return null;
        }
        
        // Convert video URL to embed format
        if (!empty($content['video_url'])) {
            $content['video_embed_url'] = $this->convert_to_embed_url($content['video_url']);
        }
        
        // Get department content
        $content['departments'] = $this->get_department_content($content['id']);
        
        // Get OOPP items
        $content['oopp_items'] = $this->get_oopp_items();
        
        // Get additional documents
        $content['documents'] = $this->get_additional_documents($content['id']);
        
        return $content;
    }
    
    /**
     * Convert video URL to embed URL
     * 
     * @param string $url Original URL
     * @return string Embed URL
     */
    private function convert_to_embed_url($url) {
        // YouTube
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            preg_match('/(?:v=|youtu\.be\/)([^&\?]+)/', $url, $matches);
            if (!empty($matches[1])) {
                return 'https://www.youtube.com/embed/' . $matches[1];
            }
        }
        
        // Vimeo
        if (strpos($url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
            if (!empty($matches[1])) {
                return 'https://player.vimeo.com/video/' . $matches[1];
            }
        }
        
        return $url;
    }
    
    /**
     * Get department content for visitor's hosts
     * 
     * @param int $content_id Training content ID
     * @return array Department content
     */
    private function get_department_content($content_id) {
        global $wpdb;
        
        // Get host user IDs for this visit
        $host_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
            $this->visit_id
        ));
        
        if (empty($host_ids)) {
            return array();
        }
        
        // Get department IDs from hosts
        $department_ids = array();
        foreach ($host_ids as $host_id) {
            $dept_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $host_id
            ));
            
            if (empty($dept_ids)) {
                // Admin/super_manager with no specific departments - get all active
                $all_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}saw_departments 
                     WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                    $this->customer_id,
                    $this->branch_id
                ));
                $department_ids = array_merge($department_ids, $all_ids);
            } else {
                $department_ids = array_merge($department_ids, $dept_ids);
            }
        }
        
        $department_ids = array_unique(array_filter($department_ids));
        
        if (empty($department_ids)) {
            return array();
        }
        
        // Get content for each department
        $departments = array();
        foreach ($department_ids as $dept_id) {
            $dept = $wpdb->get_row($wpdb->prepare(
                "SELECT dc.text_content, d.name as department_name
                 FROM {$wpdb->prefix}saw_training_department_content dc
                 INNER JOIN {$wpdb->prefix}saw_departments d ON dc.department_id = d.id
                 WHERE dc.training_content_id = %d AND dc.department_id = %d
                 AND dc.text_content IS NOT NULL AND dc.text_content != ''",
                $content_id,
                $dept_id
            ), ARRAY_A);
            
            if ($dept) {
                $departments[] = $dept;
            }
        }
        
        return $departments;
    }
    
    /**
     * Get OOPP items for visitor
     * 
     * @return array OOPP items
     */
    private function get_oopp_items() {
        if (!class_exists('SAW_OOPP_Public')) {
            $file = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/class-saw-oopp-public.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        if (class_exists('SAW_OOPP_Public')) {
            return SAW_OOPP_Public::get_for_visitor(
                $this->customer_id,
                $this->branch_id,
                $this->visit_id
            );
        }
        
        return array();
    }
    
    /**
     * Get additional documents
     * 
     * @param int $content_id Training content ID
     * @return array Documents
     */
    private function get_additional_documents($content_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT file_name, file_path 
             FROM {$wpdb->prefix}saw_training_documents 
             WHERE document_type = 'additional' AND reference_id = %d
             ORDER BY uploaded_at ASC",
            $content_id
        ), ARRAY_A);
    }
    
    /**
     * Get available languages for customer
     * 
     * @return array Languages
     */
    private function get_available_languages() {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT language_code, language_name 
             FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d
             ORDER BY id ASC",
            $this->customer_id
        ), ARRAY_A);
    }
    
    /**
     * Get URL for a step
     * 
     * @param string $step Step name (empty for base URL)
     * @return string Full URL
     */
    public function get_url($step = '') {
        $base = home_url('/visitor-info/' . $this->token . '/');
        
        if ($step) {
            return $base . $step . '/';
        }
        
        return $base;
    }
    
    /**
     * Get translations for current language
     * 
     * @return array Translations
     */
    private function get_translations() {
        $translations = array(
            'cs' => array(
                'page_title' => 'Bezpečnostní informace',
                'subtitle' => 'Informace pro návštěvu',
                'select_language' => 'Vyberte jazyk',
                'valid_until' => 'Platné do',
                'section_video' => 'Školící video',
                'section_map' => 'Mapa areálu',
                'section_risks' => 'Bezpečnostní rizika',
                'section_departments' => 'Informace o odděleních',
                'section_oopp' => 'Ochranné pomůcky (OOPP)',
                'section_additional' => 'Další informace',
                'view_pdf' => 'Zobrazit PDF',
                'no_content' => 'Obsah není k dispozici',
                'training_complete' => 'Školení dokončeno',
                'training_pending' => 'Školení nebylo dokončeno',
                'change_language' => 'Změnit jazyk',
                'error_not_found' => 'Stránka nenalezena',
                'error_not_found_desc' => 'Odkaz je neplatný nebo byl odstraněn.',
                'error_expired' => 'Platnost odkazu vypršela',
                'error_expired_desc' => 'Tento odkaz již není platný. Kontaktujte prosím organizátora návštěvy.',
            ),
            'en' => array(
                'page_title' => 'Safety Information',
                'subtitle' => 'Visit Information',
                'select_language' => 'Select language',
                'valid_until' => 'Valid until',
                'section_video' => 'Training Video',
                'section_map' => 'Area Map',
                'section_risks' => 'Safety Risks',
                'section_departments' => 'Department Information',
                'section_oopp' => 'Protective Equipment (PPE)',
                'section_additional' => 'Additional Information',
                'view_pdf' => 'View PDF',
                'no_content' => 'Content not available',
                'training_complete' => 'Training completed',
                'training_pending' => 'Training not completed',
                'change_language' => 'Change language',
                'error_not_found' => 'Page not found',
                'error_not_found_desc' => 'The link is invalid or has been removed.',
                'error_expired' => 'Link expired',
                'error_expired_desc' => 'This link is no longer valid. Please contact the visit organizer.',
            ),
            'sk' => array(
                'page_title' => 'Bezpečnostné informácie',
                'subtitle' => 'Informácie pre návštevu',
                'select_language' => 'Vyberte jazyk',
                'valid_until' => 'Platné do',
                'section_video' => 'Školiace video',
                'section_map' => 'Mapa areálu',
                'section_risks' => 'Bezpečnostné riziká',
                'section_departments' => 'Informácie o oddeleniach',
                'section_oopp' => 'Ochranné pomôcky (OOPP)',
                'section_additional' => 'Ďalšie informácie',
                'view_pdf' => 'Zobraziť PDF',
                'no_content' => 'Obsah nie je k dispozícii',
                'training_complete' => 'Školenie dokončené',
                'training_pending' => 'Školenie nebolo dokončené',
                'change_language' => 'Zmeniť jazyk',
                'error_not_found' => 'Stránka nenájdená',
                'error_not_found_desc' => 'Odkaz je neplatný alebo bol odstránený.',
                'error_expired' => 'Platnosť odkazu vypršala',
                'error_expired_desc' => 'Tento odkaz už nie je platný. Kontaktujte prosím organizátora návštevy.',
            ),
            'uk' => array(
                'page_title' => 'Інформація про безпеку',
                'subtitle' => 'Інформація для відвідування',
                'select_language' => 'Виберіть мову',
                'valid_until' => 'Дійсний до',
                'section_video' => 'Навчальне відео',
                'section_map' => 'Карта території',
                'section_risks' => 'Ризики безпеки',
                'section_departments' => 'Інформація про відділи',
                'section_oopp' => 'Засоби захисту (ЗІЗ)',
                'section_additional' => 'Додаткова інформація',
                'view_pdf' => 'Переглянути PDF',
                'no_content' => 'Вміст недоступний',
                'training_complete' => 'Навчання завершено',
                'training_pending' => 'Навчання не завершено',
                'change_language' => 'Змінити мову',
                'error_not_found' => 'Сторінку не знайдено',
                'error_not_found_desc' => 'Посилання недійсне або було видалено.',
                'error_expired' => 'Термін дії посилання закінчився',
                'error_expired_desc' => 'Це посилання більше не дійсне. Зверніться до організатора візиту.',
            ),
        );
        
        return isset($translations[$this->language]) ? $translations[$this->language] : $translations['cs'];
    }
    
    /**
     * Calculate validity date for display
     * 
     * @return string|null Formatted date or null
     */
    private function get_validity_date() {
        // Use planned_date_to + 48h grace period
        $date = $this->visitor['planned_date_to'] ?? $this->visitor['planned_date_from'] ?? null;
        
        if ($date) {
            $timestamp = strtotime($date . ' +48 hours');
            return date_i18n('d.m.Y', $timestamp);
        }
        
        return null;
    }
    
    // ==========================================
    // RENDER METHODS
    // ==========================================
    
    /**
     * Render language selection page
     */
    private function render_language_select() {
        $languages = $this->get_available_languages();
        $t = $this->get_translations();
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/visitor-info/templates/language-select.php';
        
        if (file_exists($template)) {
            include $template;
        } else {
            $this->render_error('system_error');
        }
    }
    
    /**
     * Render training step using shared templates
     */
    private function render_training_step() {
        // Extract step type from URL step
        $step_type = str_replace('training-', '', $this->step);
        
        // Get training content
        $content = $this->get_training_content();
        
        // Map step type to shared template filename
        $template_map = array(
            'video' => 'video.php',
            'map' => 'map.php',
            'risks' => 'risks.php',
            'department' => 'department.php',
            'oopp' => 'oopp.php',
            'additional' => 'additional.php',
        );
        
        $template_file = isset($template_map[$step_type]) ? $template_map[$step_type] : null;
        
        if (!$template_file) {
            $this->redirect_to_current_training_step();
            return;
        }
        
        // Find template path
        $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/' . $template_file;
        
        if (!file_exists($template_path)) {
            $this->redirect_to_current_training_step();
            return;
        }
        
        // Render training page with shared template
        $this->render_training_page($template_path, $step_type, $content);
    }
    
    /**
     * Render training page wrapper with shared template
     * 
     * @param string $template_path Path to shared template
     * @param string $step_type Step type
     * @param array $content Training content
     */
    private function render_training_page($template_path, $step_type, $content) {
        $t = $this->get_translations();
        $steps = $this->get_available_training_steps();
        
        // Calculate step progress
        $current_index = 0;
        foreach ($steps as $i => $s) {
            if ($s['type'] === $step_type) {
                $current_index = $i;
                break;
            }
        }
        $total_steps = count($steps);
        $step_number = $current_index + 1;
        
        // ===== VARIABLES FOR SHARED TEMPLATES =====
        // These must be set before including the template
        
        $lang = $this->language;
        $is_invitation = false;
        $is_visitor_info = true;  // KEY: This activates visitor_info context
        $token = $this->token;
        
        // Flow data for shared templates
        $flow = array(
            'language' => $this->language,
            'visitor_id' => $this->visitor['id'],
        );
        
        // Step-specific data
        $video_url = isset($content['video_embed_url']) ? $content['video_embed_url'] : '';
        $has_video = !empty($video_url);
        
        $pdf_path = isset($content['pdf_map_path']) ? $content['pdf_map_path'] : '';
        $has_pdf = !empty($pdf_path);
        
        $risks_text = isset($content['risks_text']) ? $content['risks_text'] : '';
        $additional_text = isset($content['additional_text']) ? $content['additional_text'] : '';
        
        $departments = isset($content['departments']) ? $content['departments'] : array();
        $oopp_items = isset($content['oopp_items']) ? $content['oopp_items'] : array();
        $documents = isset($content['documents']) ? $content['documents'] : array();
        
        $completed = false; // Will be checked in template
        
        // ===== OUTPUT PAGE =====
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($this->language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#667eea">
    <title><?php echo esc_html($t['page_title']); ?> - <?php echo esc_html($this->visitor['customer_name']); ?></title>
    <?php wp_head(); ?>
</head>
<body class="saw-visitor-info-training">
    
    <!-- Progress Header -->
    <div class="saw-info-training-header">
        <div class="saw-info-progress">
            <span class="saw-info-step-indicator">
                <?php echo $step_number; ?> / <?php echo $total_steps; ?>
            </span>
        </div>
    </div>
    
    <!-- Training Content (shared template) -->
    <div class="saw-info-training-content">
        <?php include $template_path; ?>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
        <?php
    }
    
    /**
     * Render summary view
     */
    private function render_summary() {
        $content = $this->get_training_content();
        $available_steps = $this->get_available_training_steps();
        $languages = $this->get_available_languages();
        $t = $this->get_translations();
        $valid_until = $this->get_validity_date();
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/visitor-info/templates/summary-view.php';
        
        if (file_exists($template)) {
            include $template;
        } else {
            $this->render_error('system_error');
        }
    }
    
    /**
     * Render error page
     * 
     * @param string $type Error type (not_found, expired, system_error)
     */
    private function render_error($type) {
        $t = $this->get_translations();
        
        $errors = array(
            'not_found' => array(
                'icon' => '❌',
                'title' => $t['error_not_found'],
                'desc' => $t['error_not_found_desc'],
                'status' => 404
            ),
            'expired' => array(
                'icon' => '⏰',
                'title' => $t['error_expired'],
                'desc' => $t['error_expired_desc'],
                'status' => 410
            ),
            'system_error' => array(
                'icon' => '⚠️',
                'title' => 'Systémová chyba',
                'desc' => 'Došlo k neočekávané chybě. Zkuste to prosím znovu.',
                'status' => 500
            ),
        );
        
        $error = isset($errors[$type]) ? $errors[$type] : $errors['not_found'];
        
        status_header($error['status']);
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($this->language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#667eea">
    <title><?php echo esc_html($error['title']); ?></title>
    <?php wp_head(); ?>
</head>
<body class="saw-visitor-info-page saw-visitor-info-error">
    <div class="saw-error-container">
        <div class="saw-error-card">
            <span class="saw-error-icon"><?php echo $error['icon']; ?></span>
            <h1><?php echo esc_html($error['title']); ?></h1>
            <p><?php echo esc_html($error['desc']); ?></p>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
        <?php
    }
}