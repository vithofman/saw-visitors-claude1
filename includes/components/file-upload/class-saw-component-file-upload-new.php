<?php
/**
 * File Upload Component - Version 3.0.1
 * 
 * FIX: Better error handling to prevent HTML output
 * FIX: Proper JSON responses in all cases
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     3.0.1
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Component File Upload Class
 * 
 * @since 3.0.1
 */
class SAW_Component_File_Upload {
    
    /**
     * Register AJAX handlers
     * 
     * @since 3.0.1
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
     * FIX: Wrapped in try-catch to prevent HTML errors
     * 
     * @since 3.0.1
     * @return void
     */
    public static function handle_upload() {
        // FIX: Catch all errors and return JSON
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_upload_file')) {
                wp_send_json_error([
                    'message' => 'Bezpečnostní chyba: Neplatný nonce'
                ]);
                return;
            }
            
            // Rate Limiting
            $user_id = get_current_user_id();
            $rate_limit_key = 'saw_upload_rate_' . $user_id;
            $upload_count = get_transient($rate_limit_key);
            
            if ($upload_count === false) {
                set_transient($rate_limit_key, 1, 60);
            } else {
                if ($upload_count >= 10) {
                    wp_send_json_error([
                        'message' => 'Příliš mnoho pokusů o nahrání. Počkejte chvíli a zkuste to znovu.'
                    ]);
                    return;
                }
                set_transient($rate_limit_key, $upload_count + 1, 60);
            }
            
            // Check user capabilities
            if (!current_user_can('upload_files')) {
                wp_send_json_error([
                    'message' => 'Nemáte oprávnění k nahrávání souborů'
                ]);
                return;
            }
            
            // Daily Upload Quota
            $quota_key = 'saw_upload_quota_' . $user_id . '_' . date('Y-m-d');
            $uploaded_today = get_transient($quota_key) ?: 0;
            $daily_limit = 104857600; // 100MB
            
            if (!empty($_FILES['file']['size'])) {
                $file_size = intval($_FILES['file']['size']);
                
                if (($uploaded_today + $file_size) > $daily_limit) {
                    $remaining = $daily_limit - $uploaded_today;
                    $remaining_mb = round($remaining / 1048576, 1);
                    
                    wp_send_json_error([
                        'message' => "Denní limit pro nahrávání byl překročen. Zbývá: {$remaining_mb}MB"
                    ]);
                    return;
                }
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
            
            // Blacklist Dangerous Extensions
            $dangerous_extensions = [
                'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht',
                'exe', 'bat', 'cmd', 'com', 'pif', 'scr',
                'js', 'vbs', 'vbe', 'ws', 'wsf', 'wsh',
                'msi', 'msp', 'jar', 'app', 'deb', 'rpm',
                'sh', 'bash', 'csh', 'ksh', 'zsh',
                'asp', 'aspx', 'cer', 'csr', 'jsp',
                'drv', 'sys', 'cab', 'cpl', 'inf', 'ins',
                'sql', 'sqlite', 'db', 'dbf'
            ];
            
            if (!empty($_FILES['file']['name'])) {
                $filename = sanitize_file_name($_FILES['file']['name']);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $dangerous_extensions)) {
                    wp_send_json_error([
                        'message' => 'Tento typ souboru není povolen z bezpečnostních důvodů.'
                    ]);
                    return;
                }
            }
            
            // Get upload context
            $context = sanitize_text_field($_POST['context'] ?? 'documents');
            
            // Get max file size
            $max_size = !empty($_POST['max_size']) ? intval($_POST['max_size']) : 0;
            
            // Get allowed file types
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
            
            // Enhanced MIME Validation
            $allowed_mimes = self::get_allowed_mimes($allowed_types);
            
            if (!empty($_FILES['file']['tmp_name']) && file_exists($_FILES['file']['tmp_name'])) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detected_mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
                finfo_close($finfo);
                
                if (!empty($allowed_mimes) && !in_array($detected_mime, $allowed_mimes)) {
                    wp_send_json_error([
                        'message' => 'Obsah souboru neodpovídá deklarovanému typu.'
                    ]);
                    return;
                }
                
                // Executable Content Detection
                $file_content = file_get_contents($_FILES['file']['tmp_name'], false, null, 0, 1024);
                if (preg_match('/<\?php|<script|eval\(|base64_decode/i', $file_content)) {
                    wp_send_json_error([
                        'message' => 'Soubor obsahuje zakázaný obsah.'
                    ]);
                    return;
                }
            }
            
            // Determine upload directory
            $dir_key = self::get_directory_key($context);
            
            // FIX: Check if required classes exist
// === TEMPORARY FIX: Disable uploader check ===
/*
            $uploader_path = __DIR__ . '/class-saw-file-uploader.php';
            if (!file_exists($uploader_path)) {
                wp_send_json_error([
                    'message' => 'Chyba konfigurace: Chybí soubor class-saw-file-uploader.php'
                ]);
                return;
            }
            
            require_once $uploader_path;
            
            if (!class_exists('SAW_File_Uploader')) {
                wp_send_json_error([
                    'message' => 'Chyba konfigurace: Třída SAW_File_Uploader neexistuje'
                ]);
                return;
            }
            
            $uploader = new SAW_File_Uploader([
                'max_file_size' => $max_size > 0 ? $max_size : 10485760,
                'allowed_mimes' => $allowed_mimes
            ]);
            
            // Upload file
            $result = $uploader->upload($file, $dir_key);
*/
            
            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message()
                ]);
                return;
            }
            
            // Get relative path
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace($upload_dir['basedir'], '', $result['path']);
            
            // Update Daily Quota
            if (!empty($file['size'])) {
                set_transient($quota_key, $uploaded_today + $file['size'], DAY_IN_SECONDS);
            }
            
            // Audit Logging
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log([
                    'action' => 'file_uploaded',
                    'details' => sprintf(
                        'Soubor "%s" (%s) nahrán do kontextu: %s',
                        $result['filename'],
                        size_format($file['size']),
                        $context
                    ),
                    'user_id' => $user_id,
                    'ip_address' => self::get_client_ip(),
                    'metadata' => [
                        'filename' => $result['filename'],
                        'size' => $file['size'],
                        'type' => $file['type'],
                        'context' => $context,
                        'mime_detected' => isset($detected_mime) ? $detected_mime : null
                    ]
                ]);
            }
            
            // Return success
            wp_send_json_success([
                'file' => [
                    'id' => uniqid('file_', true),
                    'url' => $result['url'],
                    'path' => $relative_path,
                    'full_path' => $result['path'],
                    'filename' => $result['filename'],
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'extension' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))
                ]
            ]);
            
        } catch (Exception $e) {
            // FIX: Catch all exceptions and return JSON
            wp_send_json_error([
                'message' => 'Chyba serveru: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get directory key based on context
     * 
     * @since 3.0.1
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
     * @since 3.0.1
     * @param array $extensions File extensions
     * @return array MIME types
     */
    private static function get_allowed_mimes($extensions) {
        if (empty($extensions)) {
            return [];
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
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'csv' => 'text/csv',
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
     * @since 3.0.1
     * @return void
     */
    public static function handle_delete() {
        try {
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
            
            // Check if required classes exist
            $manager_path = __DIR__ . '/class-saw-file-manager.php';
            $config_path = __DIR__ . '/config.php';
            
            if (!file_exists($manager_path) || !file_exists($config_path)) {
                wp_send_json_error([
                    'message' => 'Chyba konfigurace: Chybí požadované soubory'
                ]);
                return;
            }
            
            require_once $manager_path;
            $config = require $config_path;
            
            if (!class_exists('SAW_File_Manager')) {
                wp_send_json_error([
                    'message' => 'Chyba konfigurace: Třída SAW_File_Manager neexistuje'
                ]);
                return;
            }
            
            $manager = new SAW_File_Manager($config);
            
            // Delete file
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
            
            // Delete from database
            if ($file_id && $context === 'content_documents') {
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'saw_training_documents',
                    array('id' => $file_id),
                    array('%d')
                );
            }
            
            // Audit Logging
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log([
                    'action' => 'file_deleted',
                    'details' => sprintf(
                        'Soubor smazán z kontextu: %s (ID: %s)',
                        $context,
                        $file_id ?: 'N/A'
                    ),
                    'user_id' => get_current_user_id(),
                    'ip_address' => self::get_client_ip(),
                    'metadata' => [
                        'file_url' => $file_url,
                        'file_path' => $file_path,
                        'file_id' => $file_id,
                        'context' => $context
                    ]
                ]);
            }
            
            wp_send_json_success([
                'message' => 'Soubor byl úspěšně smazán'
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Chyba serveru: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Enqueue component assets
     * 
     * @since 3.0.1
     * @return void
     */
    public static function enqueue_assets() {
        $plugin_url = SAW_VISITORS_PLUGIN_URL;
        $version = '3.0.1'; // FIX VERSION
        
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
    
    /**
     * Get client IP address
     * 
     * @since 3.0.1
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
}