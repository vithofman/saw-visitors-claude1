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
        // CRITICAL: WordPress media library and editor
        wp_enqueue_media();
        wp_enqueue_editor();
        
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
        
        // CRITICAL: Force media buttons to be displayed in wp_editor
        // WordPress checks user capabilities and this filter
        add_filter('wp_editor_settings', function($settings, $editor_id) {
            // Ensure media_buttons is always true for content module editors
            if (strpos($editor_id, 'risks_text_') !== false || 
                strpos($editor_id, 'additional_text_') !== false || 
                strpos($editor_id, 'dept_text_') !== false) {
                $settings['media_buttons'] = true;
            }
            return $settings;
        }, 10, 2);
        
        // CRITICAL: Ensure media buttons HTML is output
        // This hook allows us to add custom media buttons or ensure default ones are shown
        add_action('media_buttons', function($editor_id = '') {
            // WordPress should automatically add media buttons, but we ensure they're there
            // This action is fired by wp_editor() when media_buttons is true
        }, 1);
        
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
    /**
     * Get existing files for refresh
     * 
     * Returns list of existing files for a specific context.
     * 
     * @since 2.0.0
     * @return void
     */
    public function ajax_get_existing_files() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'saw_content_action')) {
            wp_send_json_error('Bezpečnostní chyba: Neplatný nonce');
            return;
        }
        
        // Check permissions
        $role = $this->get_current_role();
        if (!in_array($role, ['admin', 'super_admin', 'manager', 'super_manager'])) {
            wp_send_json_error('Nemáte oprávnění');
            return;
        }
        
        // Get context
        global $wpdb;
        $wp_user_id = get_current_user_id();
        $saw_user = $wpdb->get_row($wpdb->prepare(
            "SELECT context_customer_id, context_branch_id FROM %i WHERE wp_user_id = %d",
            $wpdb->prefix . 'saw_users',
            $wp_user_id
        ));
        
        if (!$saw_user || !$saw_user->context_customer_id || !$saw_user->context_branch_id) {
            wp_send_json_error('Chyba kontextu');
            return;
        }
        
        $customer_id = $saw_user->context_customer_id;
        $branch_id = $saw_user->context_branch_id;
        $language_id = intval($_GET['language_id'] ?? 0);
        $context = sanitize_text_field($_GET['context'] ?? '');
        
        if (!$language_id) {
            wp_send_json_error('Chybí language_id');
            return;
        }
        
        $files = array();
        $upload_dir = wp_upload_dir();
        $document_types = $this->model->get_document_types();
        
        // Get content
        $content = $this->model->get_content($customer_id, $branch_id, $language_id);
        
        if ($content) {
            // PDF map
            if ($context === 'content_pdf_map' && !empty($content['pdf_map_path'])) {
                $files[] = array(
                    'id' => 'pdf_map_' . $language_id,
                    'url' => $upload_dir['baseurl'] . $content['pdf_map_path'],
                    'path' => $content['pdf_map_path'],
                    'name' => basename($content['pdf_map_path']),
                    'size' => file_exists($upload_dir['basedir'] . $content['pdf_map_path']) ? filesize($upload_dir['basedir'] . $content['pdf_map_path']) : 0,
                    'type' => 'application/pdf',
                    'extension' => 'pdf',
                );
            }
            
            // Documents
            if ($context === 'content_documents') {
                $doc_type = sanitize_text_field($_GET['doc_type'] ?? '');
                
                if ($doc_type === 'risks') {
                    $docs = $this->model->get_documents('risks', $content['id'], $customer_id, $branch_id);
                } elseif ($doc_type === 'additional') {
                    $docs = $this->model->get_documents('additional', $content['id'], $customer_id, $branch_id);
                } elseif ($doc_type === 'department') {
                    $dept_id = intval($_GET['dept_id'] ?? 0);
                    if ($dept_id) {
                        $dept_content_id = $this->model->get_department_content_id($content['id'], $dept_id);
                        if ($dept_content_id) {
                            $docs = $this->model->get_documents('department', $dept_content_id, $customer_id, $branch_id);
                        }
                    }
                }
                
                if (!empty($docs)) {
                    foreach ($docs as $doc) {
                        $doc_type_name = '';
                        foreach ($document_types as $dt) {
                            if ($dt['id'] == $doc['document_type_id']) {
                                $doc_type_name = $dt['name'];
                                break;
                            }
                        }
                        
                        $file_path = $doc['file_path'] ?? '';
                        $files[] = array(
                            'id' => $doc['id'],
                            'url' => !empty($file_path) ? $upload_dir['baseurl'] . $file_path : '',
                            'path' => $file_path,
                            'name' => $doc['file_name'],
                            'size' => $doc['file_size'],
                            'type' => $doc['file_type'] ?? 'application/octet-stream',
                            'extension' => strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION)),
                            'category' => $doc['document_type_id'],
                            'category_name' => $doc_type_name,
                        );
                    }
                }
            }
        }
        
        wp_send_json_success(array('files' => $files));
    }
    
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
        
        // Handle document deletions (from old delete buttons - kept for backward compatibility)
        // New delete is handled via AJAX in file upload component
        if (isset($_POST['delete_document']) && is_array($_POST['delete_document'])) {
            foreach ($_POST['delete_document'] as $doc_id) {
                $doc = $this->model->get_document_by_id(intval($doc_id));
                if ($doc) {
                    // Delete file physically
                    $upload_dir = wp_upload_dir();
                    $file_path = $upload_dir['basedir'] . $doc['file_path'];
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                    // Delete from database
                    $this->model->delete_document_by_id(intval($doc_id));
                }
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
            
            // Handle uploaded files from modern upload component
            $uploaded_files = array();
            if (!empty($_POST['uploaded_files'])) {
                $uploaded_files_json = stripslashes($_POST['uploaded_files']);
                $uploaded_files = json_decode($uploaded_files_json, true);
                
                if (!is_array($uploaded_files)) {
                    $uploaded_files = array();
                }
            }
            
            // Handle PDF map upload (from modern upload component)
            if (!empty($uploaded_files['pdf_map'])) {
                $pdf_file_data = $uploaded_files['pdf_map'];
                
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
                
                // File is already uploaded via AJAX, just save the path
                if (!empty($pdf_file_data['path'])) {
                    $this->model->save_main_content($content_id, array(
                        'pdf_map_path' => $pdf_file_data['path'],
                    ));
                }
            }
            
            // Handle risks documents (from modern upload component)
            // JavaScript sends: uploaded_files['risks_documents[]_category'] = [{file: {...}, doc_type: ...}, ...]
            $risks_keys = array('risks_documents[]_category', 'risks_doc_type[]');
            foreach ($risks_keys as $key) {
                if (!empty($uploaded_files[$key]) && is_array($uploaded_files[$key])) {
                    foreach ($uploaded_files[$key] as $file_data) {
                        if (!empty($file_data['file']) && !empty($file_data['doc_type'])) {
                            $doc_type_id = intval($file_data['doc_type']);
                            $file_meta = $file_data['file'];
                            
                            if (!empty($file_meta['path'])) {
                                $this->model->save_document(
                                    'risks',
                                    $content_id,
                                    $file_meta['path'],
                                    $file_meta['name'] ?? basename($file_meta['path']),
                                    $file_meta['size'] ?? 0,
                                    $file_meta['type'] ?? 'application/octet-stream',
                                    $doc_type_id,
                                    $customer_id,
                                    $branch_id
                                );
                            }
                        }
                    }
                    break; // Found the right key
                }
            }
            
            // Handle additional documents (from modern upload component)
            $additional_keys = array('additional_documents[]_category', 'additional_doc_type[]');
            foreach ($additional_keys as $key) {
                if (!empty($uploaded_files[$key]) && is_array($uploaded_files[$key])) {
                    foreach ($uploaded_files[$key] as $file_data) {
                        if (!empty($file_data['file']) && !empty($file_data['doc_type'])) {
                            $doc_type_id = intval($file_data['doc_type']);
                            $file_meta = $file_data['file'];
                            
                            if (!empty($file_meta['path'])) {
                                $this->model->save_document(
                                    'additional',
                                    $content_id,
                                    $file_meta['path'],
                                    $file_meta['name'] ?? basename($file_meta['path']),
                                    $file_meta['size'] ?? 0,
                                    $file_meta['type'] ?? 'application/octet-stream',
                                    $doc_type_id,
                                    $customer_id,
                                    $branch_id
                                );
                            }
                        }
                    }
                    break; // Found the right key
                }
            }
        }
        
        // Handle department content (for all roles except terminal)
        if (isset($_POST['department_text']) && is_array($_POST['department_text'])) {
            foreach ($_POST['department_text'] as $dept_id => $text) {
                $dept_id = intval($dept_id);
                $dept_content_id = $this->model->save_department_content($content_id, $dept_id, wp_kses_post($text), $customer_id, $branch_id);
                
                // Handle department documents (from modern upload component)
                // JavaScript sends: uploaded_files['department_documents[ID][]_category'] = [{file: {...}, doc_type: ...}, ...]
                $dept_doc_keys = array(
                    'department_documents[' . $dept_id . '][]_category',
                    'department_doc_type[' . $dept_id . '][]',
                );
                
                foreach ($dept_doc_keys as $dept_doc_key) {
                    if (!empty($uploaded_files[$dept_doc_key]) && is_array($uploaded_files[$dept_doc_key])) {
                        foreach ($uploaded_files[$dept_doc_key] as $file_data) {
                            if (!empty($file_data['file']) && !empty($file_data['doc_type'])) {
                                $doc_type_id = intval($file_data['doc_type']);
                                $file_meta = $file_data['file'];
                                
                                if (!empty($file_meta['path'])) {
                                    $this->model->save_document(
                                        'department',
                                        $dept_content_id,
                                        $file_meta['path'],
                                        $file_meta['name'] ?? basename($file_meta['path']),
                                        $file_meta['size'] ?? 0,
                                        $file_meta['type'] ?? 'application/octet-stream',
                                        $doc_type_id,
                                        $customer_id,
                                        $branch_id
                                    );
                                }
                            }
                        }
                        break; // Found the right key
                    }
                }
            }
        }
        
        // Return success with updated file list for refresh
        $updated_content = $this->model->get_content($customer_id, $branch_id, $language_id);
        $response_data = array(
            'message' => 'Obsah byl úspěšně uložen',
            'content_id' => $content_id,
        );
        
        // Include updated PDF map path if exists
        if (!empty($updated_content['pdf_map_path'])) {
            $upload_dir = wp_upload_dir();
            $response_data['pdf_map'] = array(
                'url' => $upload_dir['baseurl'] . $updated_content['pdf_map_path'],
                'path' => $updated_content['pdf_map_path'],
                'name' => basename($updated_content['pdf_map_path']),
            );
        }
        
        SAW_Logger::debug("Content saved successfully");
        
        wp_send_json_success($response_data);
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
