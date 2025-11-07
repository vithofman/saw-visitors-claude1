<?php
/**
 * File Upload Manager
 * 
 * Handles file operations including upload, deletion, and directory management
 * for the SAW Visitors plugin. Provides secure file path handling and directory
 * creation with proper validation.
 * 
 * @package     SAW_Visitors
 * @subpackage  Core/FileManager
 * @version     1.0.0
 * @since       1.0.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW File Manager Class
 * 
 * Manages file uploads, deletions, and directory operations with security
 * validations and proper error handling.
 * 
 * @since 1.0.0
 */
class SAW_File_Manager {
    
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
     * Initializes the file manager with configuration settings.
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
     * Delete file by URL
     * 
     * Converts file URL to server path and deletes the file if it exists.
     * Includes basic path validation to prevent directory traversal.
     * 
     * @since 1.0.0
     * @param string $file_url File URL to delete
     * @return bool True on success, false on failure
     */
    public function delete_file($file_url) {
        if (empty($file_url)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
        
        // Security: Ensure file is within upload directory
        $real_path = realpath($file_path);
        $real_upload_dir = realpath($upload_dir['basedir']);
        
        if ($real_path === false || strpos($real_path, $real_upload_dir) !== 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW File Manager] Security: Attempted to delete file outside upload directory: ' . $file_path);
            }
            return false;
        }
        
        if (file_exists($file_path)) {
            return @unlink($file_path);
        }
        
        return false;
    }
    
    /**
     * Ensure directory exists
     * 
     * Creates directory if it doesn't exist. Uses wp_mkdir_p for proper
     * permissions and parent directory creation.
     * 
     * @since 1.0.0
     * @param string $dir_key Directory key from config
     * @return string|WP_Error Directory path on success, WP_Error on failure
     */
    public function ensure_directory_exists($dir_key) {
        $upload_dir = wp_upload_dir();
        $upload_dirs = $this->config['upload_dirs'] ?? array();
        
        if (!isset($upload_dirs[$dir_key])) {
            return new WP_Error('invalid_dir', 'Neplatný typ adresáře.');
        }
        
        $target_dir = $upload_dir['basedir'] . $upload_dirs[$dir_key];
        
        if (!file_exists($target_dir)) {
            if (!wp_mkdir_p($target_dir)) {
                return new WP_Error('mkdir_failed', 'Nepodařilo se vytvořit adresář.');
            }
        }
        
        return $target_dir;
    }
    
    /**
     * Get directory path
     * 
     * Returns the full server path for a configured directory key.
     * 
     * @since 1.0.0
     * @param string $dir_key Directory key from config
     * @return string|false Directory path on success, false if key not found
     */
    public function get_directory_path($dir_key) {
        $upload_dir = wp_upload_dir();
        $upload_dirs = $this->config['upload_dirs'] ?? array();
        
        if (!isset($upload_dirs[$dir_key])) {
            return false;
        }
        
        return $upload_dir['basedir'] . $upload_dirs[$dir_key];
    }
    
    /**
     * Get directory URL
     * 
     * Returns the full URL for a configured directory key.
     * 
     * @since 1.0.0
     * @param string $dir_key Directory key from config
     * @return string|false Directory URL on success, false if key not found
     */
    public function get_directory_url($dir_key) {
        $upload_dir = wp_upload_dir();
        $upload_dirs = $this->config['upload_dirs'] ?? array();
        
        if (!isset($upload_dirs[$dir_key])) {
            return false;
        }
        
        return $upload_dir['baseurl'] . $upload_dirs[$dir_key];
    }
}