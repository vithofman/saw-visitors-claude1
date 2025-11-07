<?php
/**
 * File Upload Validator
 * 
 * Validates uploaded files for MIME type, file size, and upload integrity.
 * Provides comprehensive error messages for validation failures.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     1.0.0
 * @since       1.0.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW File Validator Class
 * 
 * Handles file validation including MIME type checking, file size limits,
 * and upload integrity verification.
 * 
 * @since 1.0.0
 */
class SAW_File_Validator {
    
    /**
     * Configuration array
     * 
     * @since 1.0.0
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     * 
     * Initializes the validator with configuration settings.
     * Merges provided config with default configuration.
     * 
     * @since 1.0.0
     * @param array $config Optional configuration overrides
     */
    public function __construct($config = array()) {
        $default_config = require __DIR__ . '/config.php';
        $this->config = array_merge($default_config, $config);
    }
    
    /**
     * Validate uploaded file
     * 
     * Performs comprehensive validation including upload integrity,
     * MIME type, and file size checks.
     * 
     * @since 1.0.0
     * @param array $file File array from $_FILES
     * @return bool|WP_Error True on success, WP_Error on validation failure
     */
    public function validate($file) {
        // Check if file was uploaded
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', 'Neplatný soubor.');
        }
        
        // Validate MIME type
        $mime_check = $this->validate_mime_type($file);
        if (is_wp_error($mime_check)) {
            return $mime_check;
        }
        
        // Validate file size
        $size_check = $this->validate_file_size($file);
        if (is_wp_error($size_check)) {
            return $size_check;
        }
        
        return true;
    }
    
    /**
     * Validate MIME type
     * 
     * Checks if the uploaded file's MIME type is in the allowed list.
     * Uses finfo for accurate MIME type detection.
     * 
     * @since 1.0.0
     * @param array $file File array from $_FILES
     * @return bool|WP_Error True on success, WP_Error if MIME type not allowed
     */
    private function validate_mime_type($file) {
        $allowed_mimes = $this->config['allowed_mimes'] ?? array();
        
        // No MIME restrictions
        if (empty($allowed_mimes)) {
            return true;
        }
        
        // Detect actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Check if MIME type is allowed
        if (!in_array($mime, $allowed_mimes)) {
            $allowed_extensions = $this->get_allowed_extensions($allowed_mimes);
            return new WP_Error('invalid_mime', 'Neplatný typ souboru. Povolené: ' . implode(', ', $allowed_extensions));
        }
        
        return true;
    }
    
    /**
     * Validate file size
     * 
     * Checks if the uploaded file size is within the allowed limit.
     * 
     * @since 1.0.0
     * @param array $file File array from $_FILES
     * @return bool|WP_Error True on success, WP_Error if file too large
     */
    private function validate_file_size($file) {
        $max_size = $this->config['max_file_size'] ?? 0;
        
        // No size limit
        if ($max_size <= 0) {
            return true;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $max_mb = round($max_size / 1048576, 1);
            return new WP_Error('file_too_large', 'Soubor je příliš velký (max ' . $max_mb . 'MB)');
        }
        
        return true;
    }
    
    /**
     * Get allowed file extensions
     * 
     * Converts MIME types to user-friendly file extension names.
     * 
     * @since 1.0.0
     * @param array $mimes Array of MIME types
     * @return array Array of uppercase file extensions
     */
    private function get_allowed_extensions($mimes) {
        $extensions = array();
        
        foreach ($mimes as $mime) {
            switch ($mime) {
                case 'image/jpeg':
                    $extensions[] = 'JPG';
                    break;
                case 'image/png':
                    $extensions[] = 'PNG';
                    break;
                case 'image/gif':
                    $extensions[] = 'GIF';
                    break;
            }
        }
        
        return array_unique($extensions);
    }
}