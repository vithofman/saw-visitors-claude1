<?php
/**
 * File Upload Component
 * 
 * Main file uploader class that handles file uploads with validation,
 * directory management, and secure file operations. Coordinates between
 * validator and manager components.
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
 * SAW File Uploader Class
 * 
 * Handles complete file upload workflow including validation, directory
 * management, secure uploads, and file deletion.
 * 
 * @since 1.0.0
 */
class SAW_File_Uploader {
    
    /**
     * Configuration array
     * 
     * @since 1.0.0
     * @var array
     */
    private $config;
    
    /**
     * File validator instance
     * 
     * @since 1.0.0
     * @var SAW_File_Validator
     */
    private $validator;
    
    /**
     * File manager instance
     * 
     * @since 1.0.0
     * @var SAW_File_Manager
     */
    private $manager;
    
    /**
     * Constructor
     * 
     * Initializes the uploader with configuration and creates validator
     * and manager instances.
     * 
     * @since 1.0.0
     * @param array $config Optional configuration overrides
     */
    public function __construct($config = array()) {
        $default_config = require __DIR__ . '/config.php';
        $this->config = array_merge($default_config, $config);
        
        require_once __DIR__ . '/class-saw-file-validator.php';
        require_once __DIR__ . '/class-saw-file-manager.php';
        
        $this->validator = new SAW_File_Validator($this->config);
        $this->manager = new SAW_File_Manager($this->config);
    }
    
    /**
     * Upload file
     * 
     * Validates and uploads a file to the specified directory.
     * Returns file information on success or WP_Error on failure.
     * 
     * @since 1.0.0
     * @param array  $file    File array from $_FILES
     * @param string $dir_key Directory key from config
     * @return array|WP_Error Array with file info on success, WP_Error on failure
     */
    public function upload($file, $dir_key = 'customers') {
        // Validate file
        $validation = $this->validator->validate($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Ensure target directory exists
        $target_dir = $this->manager->ensure_directory_exists($dir_key);
        if (is_wp_error($target_dir)) {
            return $target_dir;
        }
        
        // Generate secure filename
        $filename = $this->generate_filename($file['name']);
        $filepath = $target_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', 'Nahrání souboru selhalo');
        }
        
        // Get public URL
        $file_url = $this->manager->get_directory_url($dir_key) . $filename;
        
        return array(
            'url' => $file_url,
            'path' => $filepath,
            'filename' => $filename,
        );
    }
    
    /**
     * Delete file
     * 
     * Deletes a file by its URL. Delegates to file manager.
     * 
     * @since 1.0.0
     * @param string $file_url File URL to delete
     * @return bool True on success, false on failure
     */
    public function delete($file_url) {
        return $this->manager->delete_file($file_url);
    }
    
    /**
     * Check if file should be removed
     * 
     * Checks if the remove flag is set in POST data for a specific field.
     * Sanitizes input for security.
     * 
     * @since 1.0.0
     * @param string $field_name Field name to check
     * @return bool True if file should be removed, false otherwise
     */
    public function should_remove_file($field_name) {
        $remove_field = $field_name . '_remove';
        
        if (!isset($_POST[$remove_field])) {
            return false;
        }
        
        $remove_value = sanitize_text_field($_POST[$remove_field]);
        
        return $remove_value === '1';
    }
    
    /**
     * Generate unique filename
     * 
     * Creates a timestamped filename to prevent collisions.
     * Uses WordPress sanitize_file_name for security.
     * 
     * @since 1.0.0
     * @param string $original_name Original filename
     * @return string Generated filename
     */
    private function generate_filename($original_name) {
        $sanitized = sanitize_file_name($original_name);
        return time() . '_' . $sanitized;
    }
    
    /**
     * Get configuration
     * 
     * Returns the current configuration array.
     * 
     * @since 1.0.0
     * @return array Configuration array
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Enqueue component assets
     * 
     * Loads CSS and JavaScript files for the file upload component.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
    }
}