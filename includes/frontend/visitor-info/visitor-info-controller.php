<?php
/**
 * Visitor Info Portal Controller
 * 
 * Main controller for the visitor info portal functionality.
 * Handles language selection, training flow, and summary view.
 * 
 * @package SAW_Visitors
 * @since 3.3.0
 * @version 3.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitor_Info_Controller {
    
    /** @var string Token from URL */
    private $token;
    
    /** @var string Current step */
    private $step;
    
    /** @var array Visitor data */
    private $visitor;
    
    /** @var int Customer ID */
    private $customer_id;
    
    /** @var int Branch ID */
    private $branch_id;
    
    /** @var int Visit ID */
    private $visit_id;
    
    /** @var string Current language */
    private $language = 'cs';
    
    /** @var array Session data reference */
    private $session;
    
    /**
     * Constructor
     */
    public function __construct($token, $step = '') {
        $this->token = $token;
        $this->step = $step;
        
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        if (!isset($_SESSION['saw_visitor_info_' . $token])) {
            $_SESSION['saw_visitor_info_' . $token] = array();
        }
        $this->session = &$_SESSION['saw_visitor_info_' . $token];
    }
    
    /**
     * Initialize controller and handle request
     */
    public function init() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/model.php';
        $config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/config.php';
        $model = new SAW_Module_Visitors_Model($config);
        
        $this->visitor = $model->get_visitor_by_info_token($this->token);
        
        if (!$this->visitor) {
            $this->render_error('not_found');
            return;
        }
        
        if (!$model->is_info_portal_token_valid($this->visitor)) {
            $this->render_error('expired');
            return;
        }
        
        $this->customer_id = (int) $this->visitor['customer_id'];
        $this->branch_id = (int) $this->visitor['branch_id'];
        $this->visit_id = (int) $this->visitor['visit_id'];
        
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['cs', 'en', 'sk', 'de', 'pl', 'uk', 'vi', 'hu', 'ro', 'ru'])) {
            $this->language = sanitize_key($_GET['lang']);
            $this->session['language'] = $this->language;
        } elseif (!empty($this->session['language'])) {
            $this->language = $this->session['language'];
        }
        
        $this->enqueue_assets();
        $this->handle_post_actions();
        $this->route();
    }
    
    private function route() {
        if (empty($this->session['language']) && $this->step !== 'language') {
            wp_redirect($this->get_url('language'));
            exit;
        }
        
        $training_completed = !empty($this->visitor['training_completed_at']);
        
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
                    wp_redirect($this->get_url('summary'));
                    exit;
                }
                $this->render_training_step();
                break;
                
            default:
                if ($training_completed) {
                    wp_redirect($this->get_url('summary'));
                } else {
                    $this->redirect_to_current_training_step();
                }
                exit;
        }
    }
    
    private function handle_post_actions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $action = isset($_POST['visitor_info_action']) ? sanitize_key($_POST['visitor_info_action']) : '';
        
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
    
    private function handle_language_selection() {
        $lang = sanitize_key($_POST['language'] ?? 'cs');
        $valid_languages = ['cs', 'en', 'sk', 'de', 'pl', 'uk', 'vi', 'hu', 'ro', 'ru'];
        if (!in_array($lang, $valid_languages)) {
            $lang = 'cs';
        }
        
        $this->session['language'] = $lang;
        $this->language = $lang;
        
        if (!empty($this->visitor['training_completed_at'])) {
            wp_redirect($this->get_url('summary'));
        } else {
            $this->redirect_to_current_training_step();
        }
        exit;
    }
    
    private function handle_training_step_complete($step_type) {
        global $wpdb;
        
        $visitor_id = (int) $this->visitor['id'];
        
        $column_map = array(
            'video' => 'training_step_video',
            'map' => 'training_step_map',
            'risks' => 'training_step_risks',
            'department' => 'training_step_department',
            'additional' => 'training_step_additional',
        );
        
        if (isset($column_map[$step_type])) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                array($column_map[$step_type] => 1),
                array('id' => $visitor_id),
                array('%d'),
                array('%d')
            );
        }
        
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
        
        $this->move_to_next_training_step($step_type);
    }
    
    private function handle_skip_training() {
        $this->complete_training();
    }
    
    private function move_to_next_training_step($current_type) {
        $steps = $this->get_available_training_steps();
        
        $current_index = -1;
        foreach ($steps as $i => $step) {
            if ($step['type'] === $current_type) {
                $current_index = $i;
                break;
            }
        }
        
        if ($current_index !== -1 && isset($steps[$current_index + 1])) {
            $next = $steps[$current_index + 1];
            wp_redirect($this->get_url($next['step']));
        } else {
            $this->complete_training();
        }
        exit;
    }
    
    private function complete_training() {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            array(
                'training_completed_at' => current_time('mysql'),
                'training_status' => 'completed'
            ),
            array('id' => (int) $this->visitor['id']),
            array('%s', '%s'),
            array('%d')
        );
        
        wp_redirect($this->get_url('summary'));
        exit;
    }
    
    private function redirect_to_current_training_step() {
        $steps = $this->get_available_training_steps();
        
        if (empty($steps)) {
            $this->complete_training();
            return;
        }
        
        $step_columns = array(
            'video' => 'training_step_video',
            'map' => 'training_step_map',
            'risks' => 'training_step_risks',
            'department' => 'training_step_department',
            'oopp' => null,
            'additional' => 'training_step_additional',
        );
        
        global $wpdb;
        $fresh_visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
            $this->visitor['id']
        ), ARRAY_A);
        
        if ($fresh_visitor) {
            $this->visitor = array_merge($this->visitor, $fresh_visitor);
        }
        
        foreach ($steps as $step) {
            $type = $step['type'];
            $column = isset($step_columns[$type]) ? $step_columns[$type] : null;
            
            if (!$column || empty($this->visitor[$column])) {
                wp_redirect($this->get_url($step['step']));
                exit;
            }
        }
        
        $this->complete_training();
    }
    
    private function get_available_training_steps() {
        global $wpdb;
        
        $steps = array();
        
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $this->language
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
        
        $content_id = (int) $content['id'];
        
        // 1. Video
        if (!empty($content['video_url'])) {
            $steps[] = array('type' => 'video', 'step' => 'training-video');
        }
        
        // 2. Map
        if (!empty($content['pdf_map_path'])) {
            $steps[] = array('type' => 'map', 'step' => 'training-map');
        }
        
        // 3. Risks
        if (!empty($content['risks_text'])) {
            $steps[] = array('type' => 'risks', 'step' => 'training-risks');
        }
        
        // 4. Department - use actual department content check
        $departments = $this->get_department_content($content_id);
        if (!empty($departments)) {
            $steps[] = array('type' => 'department', 'step' => 'training-department');
        }
        
        // 5. OOPP
        if (!class_exists('SAW_OOPP_Public')) {
            $oopp_file = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/oopp/class-saw-oopp-public.php';
            if (file_exists($oopp_file)) {
                require_once $oopp_file;
            }
        }
        
        if (class_exists('SAW_OOPP_Public')) {
            $has_oopp = SAW_OOPP_Public::has_oopp($this->customer_id, $this->branch_id, $this->visit_id);
            if ($has_oopp) {
                $steps[] = array('type' => 'oopp', 'step' => 'training-oopp');
            }
        }
        
        // 6. Additional
        $has_additional = !empty($content['additional_text']);
        $additional_docs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_training_documents 
             WHERE document_type = 'additional' AND reference_id = %d",
            $content_id
        ));
        if ($has_additional || $additional_docs > 0) {
            $steps[] = array('type' => 'additional', 'step' => 'training-additional');
        }
        
        return $steps;
    }
    
    private function get_training_content() {
        global $wpdb;
        
        $language_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_training_languages 
             WHERE customer_id = %d AND language_code = %s",
            $this->customer_id,
            $this->language
        ));
        
        if (!$language_id) {
            return null;
        }
        
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
        
        if (!empty($content['video_url'])) {
            $content['video_embed_url'] = $this->convert_to_embed_url($content['video_url']);
        }
        
        $content['departments'] = $this->get_department_content($content['id']);
        $content['oopp_items'] = $this->get_oopp_items();
        $content['documents'] = $this->get_additional_documents($content['id']);
        
        return $content;
    }
    
    private function convert_to_embed_url($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            preg_match('/(?:v=|youtu\.be\/)([^&\?]+)/', $url, $matches);
            if (!empty($matches[1])) {
                return 'https://www.youtube.com/embed/' . $matches[1];
            }
        }
        
        if (strpos($url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
            if (!empty($matches[1])) {
                return 'https://player.vimeo.com/video/' . $matches[1];
            }
        }
        
        return $url;
    }
    
    /**
     * Get department content - IMPROVED with better fallback
     */
    private function get_department_content($content_id) {
        global $wpdb;
        
        // Get host user IDs for this visit
        $host_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
            $this->visit_id
        ));
        
        $department_ids = array();
        
        if (!empty($host_ids)) {
            foreach ($host_ids as $host_id) {
                $dept_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                    $host_id
                ));
                
                if (empty($dept_ids)) {
                    // Admin/super_manager - get all active departments
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
        } else {
            // NO HOSTS - fallback to all active departments
            $department_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_departments 
                 WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                $this->customer_id,
                $this->branch_id
            ));
        }
        
        $department_ids = array_unique(array_filter($department_ids));
        
        if (empty($department_ids)) {
            return array();
        }
        
        $departments = array();
        
        foreach ($department_ids as $dept_id) {
            // Try training_department_content first
            $dept = $wpdb->get_row($wpdb->prepare(
                "SELECT dc.id, dc.text_content, dc.department_id, d.name as department_name, d.description as department_description
                 FROM {$wpdb->prefix}saw_training_department_content dc
                 INNER JOIN {$wpdb->prefix}saw_departments d ON dc.department_id = d.id
                 WHERE dc.training_content_id = %d AND dc.department_id = %d",
                $content_id,
                $dept_id
            ), ARRAY_A);
            
            if ($dept) {
                $text = !empty($dept['text_content']) ? $dept['text_content'] : $dept['department_description'];
                
                $docs = array();
                if (!empty($dept['id'])) {
                    $docs = $wpdb->get_results($wpdb->prepare(
                        "SELECT file_name, file_path FROM {$wpdb->prefix}saw_training_documents 
                         WHERE document_type = 'department' AND reference_id = %d 
                         ORDER BY uploaded_at ASC",
                        $dept['id']
                    ), ARRAY_A);
                }
                
                if (!empty($text) || !empty($docs)) {
                    $departments[] = array(
                        'department_id' => $dept['department_id'],
                        'department_name' => $dept['department_name'],
                        'text_content' => $text,
                        'documents' => $docs,
                    );
                }
            } else {
                // Fallback: department description
                $dept_info = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name, description FROM {$wpdb->prefix}saw_departments WHERE id = %d",
                    $dept_id
                ), ARRAY_A);
                
                if ($dept_info && !empty($dept_info['description'])) {
                    $departments[] = array(
                        'department_id' => $dept_info['id'],
                        'department_name' => $dept_info['name'],
                        'text_content' => $dept_info['description'],
                        'documents' => array(),
                    );
                }
            }
        }
        
        return $departments;
    }
    
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
    
    public function get_url($step = '') {
        $base = home_url('/visitor-info/' . $this->token . '/');
        return $step ? $base . $step . '/' : $base;
    }
    
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
                'error_expired_desc' => 'Tento odkaz již není platný.',
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
                'error_expired_desc' => 'This link is no longer valid.',
            ),
        );
        
        return isset($translations[$this->language]) ? $translations[$this->language] : $translations['cs'];
    }
    
    private function get_validity_date() {
        $date = isset($this->visitor['planned_date_to']) ? $this->visitor['planned_date_to'] : null;
        if (!$date) {
            $date = isset($this->visitor['planned_date_from']) ? $this->visitor['planned_date_from'] : null;
        }
        
        if ($date) {
            return date_i18n('d.m.Y', strtotime($date . ' +48 hours'));
        }
        
        return null;
    }
    
    private function enqueue_assets() {
        $css_version = SAW_VISITORS_VERSION;
        $css_base_url = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/css/terminal/';
        
        wp_enqueue_style('saw-terminal-base', $css_base_url . 'base.css', array(), $css_version);
        wp_enqueue_style('saw-terminal-layout', $css_base_url . 'layout.css', array('saw-terminal-base'), $css_version);
        wp_enqueue_style('saw-terminal-components', $css_base_url . 'components.css', array('saw-terminal-layout'), $css_version);
        wp_enqueue_style('saw-terminal-pages', $css_base_url . 'pages.css', array('saw-terminal-components'), $css_version);
        
        $js_base_url = SAW_VISITORS_PLUGIN_URL . 'includes/frontend/terminal/assets/js/terminal/';
        wp_enqueue_script('saw-touch-gestures', $js_base_url . 'touch-gestures.js', array(), $css_version, true);
        wp_enqueue_script('saw-pdf-viewer', $js_base_url . 'pdf-viewer.js', array('saw-touch-gestures'), $css_version, true);
        wp_enqueue_script('saw-video-player', $js_base_url . 'video-player.js', array(), $css_version, true);
    }
    
    private function render_language_select() {
        $languages = $this->get_available_languages();
        $t = $this->get_translations();
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/visitor-info/templates/language-select.php';
        
        if (file_exists($template)) {
            include $template;
        }
    }
    
    private function render_training_step() {
        $step_type = str_replace('training-', '', $this->step);
        $content = $this->get_training_content();
        
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
        
        $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/shared/training/' . $template_file;
        
        if (!file_exists($template_path)) {
            $this->redirect_to_current_training_step();
            return;
        }
        
        $this->render_training_page($template_path, $step_type, $content);
    }
    
    private function render_training_page($template_path, $step_type, $content) {
        $t = $this->get_translations();
        $steps = $this->get_available_training_steps();
        
        $current_index = 0;
        foreach ($steps as $i => $s) {
            if ($s['type'] === $step_type) {
                $current_index = $i;
                break;
            }
        }
        $total_steps = count($steps);
        $step_number = $current_index + 1;
        
        $lang = $this->language;
        $is_invitation = false;
        $is_visitor_info = true;
        $token = $this->token;
        
        $flow = array(
            'language' => $this->language,
            'visitor_id' => $this->visitor['id'],
        );
        
        $video_url = isset($content['video_embed_url']) ? $content['video_embed_url'] : '';
        $has_video = !empty($video_url);
        $pdf_path = isset($content['pdf_map_path']) ? $content['pdf_map_path'] : '';
        $has_pdf = !empty($pdf_path);
        $risks_text = isset($content['risks_text']) ? $content['risks_text'] : '';
        $additional_text = isset($content['additional_text']) ? $content['additional_text'] : '';
        $departments = isset($content['departments']) ? $content['departments'] : array();
        $oopp_items = isset($content['oopp_items']) ? $content['oopp_items'] : array();
        $documents = isset($content['documents']) ? $content['documents'] : array();
        $completed = false;
        
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($this->language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a202c">
    <title><?php echo esc_html($t['page_title']); ?></title>
    <?php wp_head(); ?>
</head>
<body class="saw-visitor-info-training saw-page-aurora">
    <!-- Floating step badge (transparent header) -->
    <div style="position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:1000;pointer-events:none;">
        <span style="display:inline-block;background:rgba(0,0,0,0.4);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);color:rgba(255,255,255,0.9);font-weight:600;font-size:0.875rem;padding:0.5rem 1rem;border-radius:20px;border:1px solid rgba(255,255,255,0.1);"><?php echo $step_number; ?> / <?php echo $total_steps; ?></span>
    </div>
    <div>
        <?php include $template_path; ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
        <?php
    }
    
    private function render_summary() {
        $content = $this->get_training_content();
        $available_steps = $this->get_available_training_steps();
        $languages = $this->get_available_languages();
        $t = $this->get_translations();
        $valid_until = $this->get_validity_date();
        
        $template = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/visitor-info/templates/summary-view.php';
        
        if (file_exists($template)) {
            include $template;
        }
    }
    
    private function render_error($type) {
        $t = $this->get_translations();
        
        $errors = array(
            'not_found' => array('icon' => '❌', 'title' => $t['error_not_found'], 'desc' => $t['error_not_found_desc']),
            'expired' => array('icon' => '⏰', 'title' => $t['error_expired'], 'desc' => $t['error_expired_desc']),
        );
        
        $error = isset($errors[$type]) ? $errors[$type] : $errors['not_found'];
        
        status_header($type === 'expired' ? 410 : 404);
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($this->language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($error['title']); ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a202c 0%,#2d3748 100%);padding:1rem}
        .card{background:rgba(15,23,42,0.8);backdrop-filter:blur(20px);border:1px solid rgba(148,163,184,0.15);border-radius:24px;padding:3rem 2rem;text-align:center;max-width:400px}
        .icon{font-size:4rem;margin-bottom:1.5rem}
        h1{font-size:1.5rem;color:#f9fafb;margin-bottom:0.5rem}
        p{color:#94a3b8}
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><?php echo $error['icon']; ?></div>
        <h1><?php echo esc_html($error['title']); ?></h1>
        <p><?php echo esc_html($error['desc']); ?></p>
    </div>
</body>
</html>
        <?php
    }
}