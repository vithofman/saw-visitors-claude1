<?php
/**
 * Content Module Controller
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Content_Controller 
{
    protected $config;
    protected $model;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/content/';
        
        $this->config = require $module_path . 'config.php';
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Content_Model();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets() {
        // WordPress media library
        wp_enqueue_media();
        
        SAW_Asset_Loader::enqueue_module('content');
    }
    
    public function index() {
        $role = $this->get_current_role();
        
        // Kontrola oprávnění
        // super_admin - vidí vše
        // admin - vidí všechny pobočky svého zákazníka
        // super_manager - vidí obsah pro svou pobočku (všechna oddělení)
        // manager - vidí pouze svá přiřazená oddělení
        // terminal - nemá přístup
        if (!in_array($role, array('admin', 'super_admin', 'manager', 'super_manager'))) {
            wp_die(
                'Nemáte oprávnění zobrazit tuto stránku.',
                'Přístup odepřen',
                array('response' => 403)
            );
        }
        
        // HANDLE FORM SUBMISSION FIRST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_content_nonce'])) {
            $this->handle_save();
            // handle_save() ends with exit, so code below won't execute
        }
        
        // Get customer and branch from context (same way as in handle_save)
        global $wpdb;
        $wp_user_id = get_current_user_id();
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT context_customer_id, context_branch_id FROM %i WHERE wp_user_id = %d",
            $wpdb->prefix . 'saw_users',
            $wp_user_id
        ));
        
        if (!$saw_user || !$saw_user->context_customer_id || !$saw_user->context_branch_id) {
            wp_die(
                'Chybí kontext zákazníka nebo pobočky.',
                'Chyba konfigurace',
                array('response' => 500)
            );
        }
        
        $customer_id = $saw_user->context_customer_id;
        $branch_id = $saw_user->context_branch_id;
        
        // CRITICAL: Load WordPress media templates
        add_action('admin_footer', 'wp_print_media_templates');
        add_action('wp_footer', 'wp_print_media_templates');
        
        // CRITICAL: Give admin, super_manager and manager full editor capabilities
        add_filter('user_has_cap', function($allcaps) use ($role) {
            if (in_array($role, array('admin', 'super_admin', 'manager', 'super_manager'))) {
                $allcaps['edit_posts'] = true;
                $allcaps['upload_files'] = true;
                $allcaps['edit_files'] = true;
            }
            return $allcaps;
        });
        
        $languages = $this->model->get_training_languages($customer_id);
        
        if (empty($languages)) {
            wp_die(
                'Nejdříve musíte vytvořit alespoň jeden jazyk školení.',
                'Žádné jazyky',
                array('response' => 404)
            );
        }
        
        // Load document types
        $document_types = $this->model->get_document_types();
        
        $view_data = array(
            'icon' => $this->config['icon'],
            'languages' => $languages,
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
            'document_types' => $document_types,
            'model' => $this->model,
        );
        
        $this->render_view($view_data);
    }
    
    private function render_view($view_data) {
        if (!class_exists('SAW_App_Layout')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-layout.php';
        }
        
        ob_start();
        
        extract($view_data);
        
        $template_path = $this->config['path'] . 'view.php';
        if (file_exists($template_path)) {
            require $template_path;
        }
        
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, 'Správa obsahu', 'content');
    }
    
    private function get_current_role() {
        if (class_exists('SAW_Context')) {
            return SAW_Context::get_role();
        }
        
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        return null;
    }
    
    /**
     * Handle form save
     */
    public function handle_save() {
        // Debug log
        $log = WP_CONTENT_DIR . '/saw-content-save-debug.log';
        SAW_Logger::debug("Handling content upload", [
            'post' => $_POST,
            'files' => $_FILES
        ]);

        // Verify nonce
        if (!isset($_POST['saw_content_nonce'])) {
            SAW_Logger::error("Nonce not found in POST");
            wp_send_json_error('Bezpečnostní chyba: Chybí nonce');
        }
        
        if (!wp_verify_nonce($_POST['saw_content_nonce'], 'saw_content_action')) {
            SAW_Logger::error("Nonce verification failed");
            wp_send_json_error('Bezpečnostní chyba: Neplatný nonce');
        }
        
        SAW_Logger::debug("Nonce OK");
        
        // Check permissions
        $role = $this->get_current_role(); // Changed from get_current_user_role() to get_current_role() to match existing method
        SAW_Logger::debug("Role: " . $role);
        
        if (!in_array($role, ['admin', 'super_admin', 'manager', 'super_manager'])) { // Reverted roles to match original logic
            SAW_Logger::error("Unauthorized role");
            wp_send_json_error('Nemáte oprávnění pro tuto akci');
        }
        
        // Get customer and branch from context
        global $wpdb;
        $wp_user_id = get_current_user_id();
        SAW_Logger::debug("WP User ID: " . $wp_user_id);
        
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT context_customer_id, context_branch_id FROM %i WHERE wp_user_id = %d",
            $wpdb->prefix . 'saw_users',
            $wp_user_id
        ));
        
        SAW_Logger::debug("SAW User", ['user' => $saw_user]);
        
        if (!$saw_user || !$saw_user->context_customer_id || !$saw_user->context_branch_id) {
            SAW_Logger::error("Missing context");
            wp_send_json_error('Chyba kontextu: Uživatel nenalezen');
        }
        
        $customer_id = $saw_user->context_customer_id;
        $branch_id = $saw_user->context_branch_id;
        $language_id = intval($_POST['language_id']);
        
        // Handle document deletions
        if (isset($_POST['delete_document']) && is_array($_POST['delete_document'])) {
            foreach ($_POST['delete_document'] as $doc_id) {
                $this->model->delete_document_by_id(intval($doc_id));
            }
        }
        
        SAW_Logger::debug("Customer: $customer_id, Branch: $branch_id, Language: $language_id");
        
        // Get or create content record
        $content_id = $this->model->get_or_create_content($customer_id, $branch_id, $language_id);
        SAW_Logger::debug("Content ID: $content_id");
        
        if (!$content_id) {
            SAW_Logger::error("Failed to create content record");
            wp_send_json_error('Nepodařilo se vytvořit záznam obsahu');
        }
        
        // Save main content (all roles except manager)
        if ($role !== 'manager') {
            SAW_Logger::debug("Saving main content...");
            
            $result = $this->model->save_main_content($content_id, array(
                'video_url' => sanitize_text_field($_POST['video_url'] ?? ''),
                'risks_text' => wp_kses_post($_POST['risks_text'] ?? ''),
                'additional_text' => wp_kses_post($_POST['additional_text'] ?? ''),
            ));
            
            SAW_Logger::debug("Main content save result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // Handle PDF map upload
            if (!empty($_FILES['pdf_map']['name'])) {
                // Delete old PDF map if exists
                $old_content = $this->model->get_content($customer_id, $branch_id, $language_id);
                if ($old_content && !empty($old_content['pdf_map_path'])) {
                    $upload_dir = wp_upload_dir();
                    $old_file = $upload_dir['basedir'] . $old_content['pdf_map_path'];
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                        error_log("SAW Content: Deleted old PDF map: " . $old_file);
                    }
                }
                
                $pdf_file = $this->handle_file_upload($_FILES['pdf_map'], 'pdf');
                if ($pdf_file) {
                    $this->model->save_main_content($content_id, array(
                        'pdf_map_path' => $pdf_file['path'],
                    ));
                }
            }
            
            // Handle risks documents - only add new ones, don't delete existing
            if (!empty($_FILES['risks_documents']['name'][0])) {
                foreach ($_FILES['risks_documents']['name'] as $key => $name) {
                    if (!empty($name)) {
                        $file = array(
                            'name' => $_FILES['risks_documents']['name'][$key],
                            'type' => $_FILES['risks_documents']['type'][$key],
                            'tmp_name' => $_FILES['risks_documents']['tmp_name'][$key],
                            'error' => $_FILES['risks_documents']['error'][$key],
                            'size' => $_FILES['risks_documents']['size'][$key],
                        );
                        $doc_type_id = !empty($_POST['risks_doc_type'][$key]) ? intval($_POST['risks_doc_type'][$key]) : null;
                        $uploaded = $this->handle_file_upload($file, 'document');
                        if ($uploaded) {
                            $this->model->save_document('risks', $content_id, $uploaded['path'], $uploaded['name'], $uploaded['size'], $uploaded['type'], $doc_type_id);
                        }
                    }
                }
            }
            
            // Handle additional documents - only add new ones, don't delete existing
            if (!empty($_FILES['additional_documents']['name'][0])) {
                foreach ($_FILES['additional_documents']['name'] as $key => $name) {
                    if (!empty($name)) {
                        $file = array(
                            'name' => $_FILES['additional_documents']['name'][$key],
                            'type' => $_FILES['additional_documents']['type'][$key],
                            'tmp_name' => $_FILES['additional_documents']['tmp_name'][$key],
                            'error' => $_FILES['additional_documents']['error'][$key],
                            'size' => $_FILES['additional_documents']['size'][$key],
                        );
                        $doc_type_id = !empty($_POST['additional_doc_type'][$key]) ? intval($_POST['additional_doc_type'][$key]) : null;
                        $uploaded = $this->handle_file_upload($file, 'document');
                        if ($uploaded) {
                            $this->model->save_document('additional', $content_id, $uploaded['path'], $uploaded['name'], $uploaded['size'], $uploaded['type'], $doc_type_id);
                        }
                    }
                }
            }
        }
        
        // Handle department content (for all roles except terminal)
        if (isset($_POST['department_text']) && is_array($_POST['department_text'])) {
            foreach ($_POST['department_text'] as $dept_id => $text) {
                $dept_id = intval($dept_id);
                $dept_content_id = $this->model->save_department_content($content_id, $dept_id, wp_kses_post($text));
                
                // Handle department documents - only add new ones, don't delete existing
                if (!empty($_FILES['department_documents']['name'][$dept_id][0])) {
                    foreach ($_FILES['department_documents']['name'][$dept_id] as $key => $name) {
                        if (!empty($name)) {
                            $file = array(
                                'name' => $_FILES['department_documents']['name'][$dept_id][$key],
                                'type' => $_FILES['department_documents']['type'][$dept_id][$key],
                                'tmp_name' => $_FILES['department_documents']['tmp_name'][$dept_id][$key],
                                'error' => $_FILES['department_documents']['error'][$dept_id][$key],
                                'size' => $_FILES['department_documents']['size'][$dept_id][$key],
                            );
                            $doc_type_id = !empty($_POST['department_doc_type'][$dept_id][$key]) ? intval($_POST['department_doc_type'][$dept_id][$key]) : null;
                            $uploaded = $this->handle_file_upload($file, 'document');
                            if ($uploaded) {
                                $this->model->save_document('department', $dept_content_id, $uploaded['path'], $uploaded['name'], $uploaded['size'], $uploaded['type'], $doc_type_id);
                            }
                        }
                    }
                }
            }
        }
        
        // Redirect back with success message
        $redirect_url = remove_query_arg(array('saved', 'error'), wp_get_referer());
        $final_url = add_query_arg('saved', '1', $redirect_url);
        
        SAW_Logger::debug("Redirecting to: $final_url");
        
        wp_send_json_success(['redirect' => $final_url]);
        exit;
    }
    
    /**
     * Handle file upload
     *
     * @param array $file
     * @param string $type
     * @return array|false
     */
    private function handle_file_upload($file, $type = 'document') {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/saw-training/' . $type . 's/';
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $filename = sanitize_file_name($file['name']);
        $filename = time() . '_' . $filename;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return array(
                'path' => str_replace($upload_dir['basedir'], '', $target_file),
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
            );
        }
        
        return false;
    }
}
