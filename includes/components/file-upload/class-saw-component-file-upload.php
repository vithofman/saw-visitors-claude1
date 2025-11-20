<?php
/**
 * File Upload Component
 * 
 * Modern file upload component with AJAX upload, progress tracking,
 * drag & drop, and toast notifications.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     2.0.0
 * @since       2.0.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Component File Upload Class
 * 
 * Handles AJAX file uploads with validation, progress tracking,
 * and secure file operations.
 * 
 * @since 2.0.0
 */
class SAW_Component_File_Upload {
    
    /**
     * Register AJAX handlers
     * 
     * Registers WordPress AJAX actions for file upload functionality.
     * 
     * @since 2.0.0
     * @return void
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_saw_upload_file', [__CLASS__, 'handle_upload']);
        add_action('wp_ajax_nopriv_saw_upload_file', [__CLASS__, 'handle_upload']);
        add_action('wp_ajax_saw_delete_file', [__CLASS__, 'handle_delete']);
        add_action('wp_ajax_nopriv_saw_delete_file', [__CLASS__, 'handle_delete']);
    }
    
    /**
     * Handle file upload AJAX request
     * 
     * Processes file upload with validation, security checks,
     * and returns file metadata.
     * 
     * @since 2.0.0
     * @return void
     */
    public static function handle_upload() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_upload_file')) {
            wp_send_json_error([
                'message' => 'Bezpečnostní chyba: Neplatný nonce'
            ]);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error([
                'message' => 'Nemáte oprávnění k nahrávání souborů'
            ]);
            return;
        }
        
        // Check if file was uploaded
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Soubor nebyl nahrán';
            
            if (!empty($_FILES['file']['error'])) {
                switch ($_FILES['file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'Soubor je příliš velký';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'Soubor byl nahrán pouze částečně';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'Žádný soubor nebyl nahrán';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = 'Chybí dočasný adresář';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = 'Nepodařilo se zapsat soubor na disk';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = 'Nahrávání bylo zastaveno rozšířením';
                        break;
                }
            }
            
            wp_send_json_error([
                'message' => $error_message
            ]);
            return;
        }
        
        // Get upload context
        $context = sanitize_text_field($_POST['context'] ?? 'documents');
        
        // Get max file size from POST or use default
        $max_size = !empty($_POST['max_size']) ? intval($_POST['max_size']) : 0;
        
        // Get allowed file types from POST
        $allowed_types = !empty($_POST['accept']) ? explode(',', sanitize_text_field($_POST['accept'])) : [];
        
        // Prepare file array
        $file = $_FILES['file'];
        
        // Validate file size
        if ($max_size > 0 && $file['size'] > $max_size) {
            $max_mb = round($max_size / 1048576, 1);
            wp_send_json_error([
                'message' => "Soubor je příliš velký (max {$max_mb}MB)"
            ]);
            return;
        }
        
        // Validate file type
        if (!empty($allowed_types)) {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = array_map(function($type) {
                return trim(str_replace('.', '', strtolower($type)));
            }, $allowed_types);
            
            if (!in_array($file_extension, $allowed_extensions)) {
                wp_send_json_error([
                    'message' => 'Nepodporovaný formát souboru. Povolené: ' . implode(', ', $allowed_types)
                ]);
                return;
            }
        }
        
        // Determine upload directory based on context
        $dir_key = self::get_directory_key($context);
        
        // Load file uploader
        require_once __DIR__ . '/class-saw-file-uploader.php';
        
        $uploader = new SAW_File_Uploader([
            'max_file_size' => $max_size > 0 ? $max_size : 10485760, // Default 10MB
            'allowed_mimes' => self::get_allowed_mimes($allowed_types)
        ]);
        
        // Upload file
        $result = $uploader->upload($file, $dir_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }
        
        // Get relative path for storage
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'], '', $result['path']);
        
        // Return file metadata
        wp_send_json_success([
            'file' => [
                'id' => uniqid('file_', true), // Temporary ID for frontend
                'url' => $result['url'],
                'path' => $relative_path, // Relative path for database storage
                'full_path' => $result['path'], // Full server path
                'filename' => $result['filename'],
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
                'extension' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))
            ]
        ]);
    }
    
    /**
     * Get directory key based on context
     * 
     * Maps upload context to directory key from config.
     * 
     * @since 2.0.0
     * @param string $context Upload context
     * @return string Directory key
     */
    private static function get_directory_key($context) {
        $context_map = [
            'content_pdf_map' => 'documents',
            'content_documents' => 'documents',
            'documents' => 'documents',
            'customers' => 'customers',
            'branches' => 'branches',
            'materials' => 'materials',
            'visitor-uploads' => 'visitor-uploads',
            'risk-docs' => 'risk-docs',
        ];
        
        return $context_map[$context] ?? 'documents';
    }
    
    /**
     * Get allowed MIME types from file extensions
     * 
     * Converts file extensions to MIME types for validation.
     * 
     * @since 2.0.0
     * @param array $extensions File extensions
     * @return array MIME types
     */
    private static function get_allowed_mimes($extensions) {
        if (empty($extensions)) {
            return []; // No restrictions
        }
        
        $mime_map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
        ];
        
        $mimes = [];
        foreach ($extensions as $ext) {
            $ext = trim(str_replace('.', '', strtolower($ext)));
            if (isset($mime_map[$ext])) {
                $mimes[] = $mime_map[$ext];
            }
        }
        
        return array_unique($mimes);
    }
    
    /**
     * Handle file delete AJAX request
     * 
     * Deletes a file physically from the server and optionally from database.
     * 
     * @since 2.0.0
     * @return void
     */
    public static function handle_delete() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_upload_file')) {
            wp_send_json_error([
                'message' => 'Bezpečnostní chyba: Neplatný nonce'
            ]);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error([
                'message' => 'Nemáte oprávnění k mazání souborů'
            ]);
            return;
        }
        
        $file_url = sanitize_text_field($_POST['file_url'] ?? '');
        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        $file_id = !empty($_POST['file_id']) ? intval($_POST['file_id']) : null;
        $context = sanitize_text_field($_POST['context'] ?? 'documents');
        
        if (empty($file_url) && empty($file_path)) {
            wp_send_json_error([
                'message' => 'Chybí URL nebo cesta k souboru'
            ]);
            return;
        }
        
        // Load file manager
        require_once __DIR__ . '/class-saw-file-manager.php';
        
        $config = require __DIR__ . '/config.php';
        $manager = new SAW_File_Manager($config);
        
        // Delete file physically
        $deleted = false;
        if (!empty($file_url)) {
            $deleted = $manager->delete_file($file_url);
        } elseif (!empty($file_path)) {
            $upload_dir = wp_upload_dir();
            $full_path = $upload_dir['basedir'] . $file_path;
            if (file_exists($full_path)) {
                $deleted = @unlink($full_path);
            }
        }
        
        if (!$deleted) {
            wp_send_json_error([
                'message' => 'Nepodařilo se smazat soubor'
            ]);
            return;
        }
        
        // If file_id is provided, delete from database (for content module documents)
        if ($file_id && $context === 'content_documents') {
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'saw_training_documents',
                array('id' => $file_id),
                array('%d')
            );
        }
        
        wp_send_json_success([
            'message' => 'Soubor byl úspěšně smazán'
        ]);
    }
    
    /**
     * Enqueue component assets
     * 
     * Loads CSS and JavaScript files for the file upload component.
     * 
     * @since 2.0.0
     * @return void
     */
    public static function enqueue_assets() {
        $plugin_url = SAW_VISITORS_PLUGIN_URL;
        $version = SAW_VISITORS_VERSION;
        
        // Enqueue CSS
        wp_enqueue_style(
            'saw-file-upload-modern',
            $plugin_url . 'assets/css/components/file-upload-modern.css',
            [],
            $version
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'saw-file-upload-modern',
            $plugin_url . 'assets/js/components/file-upload-modern.js',
            ['jquery'],
            $version,
            true
        );
        
        // Localize script
        wp_localize_script('saw-file-upload-modern', 'sawFileUpload', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_upload_file'),
            'strings' => [
                'uploading' => 'Nahrávání...',
                'success' => 'Soubor byl úspěšně nahrán',
                'error' => 'Chyba při nahrávání',
                'file_too_large' => 'Soubor je příliš velký',
                'invalid_type' => 'Nepodporovaný formát souboru',
                'select_files' => 'Vybrat soubory',
                'drag_drop' => 'Přetáhněte soubory nebo',
            ]
        ]);
    }
}

