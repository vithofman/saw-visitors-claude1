<?php
/**
 * File Upload Component
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_File_Uploader {
    
    private $config;
    private $validator;
    private $manager;
    
    public function __construct($config = []) {
        $default_config = require __DIR__ . '/config.php';
        $this->config = array_merge($default_config, $config);
        
        require_once __DIR__ . '/class-saw-file-validator.php';
        require_once __DIR__ . '/class-saw-file-manager.php';
        
        $this->validator = new SAW_File_Validator($this->config);
        $this->manager = new SAW_File_Manager($this->config);
    }
    
    public function upload($file, $dir_key = 'customers') {
        $validation = $this->validator->validate($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $target_dir = $this->manager->ensure_directory_exists($dir_key);
        if (is_wp_error($target_dir)) {
            return $target_dir;
        }
        
        $filename = $this->generate_filename($file['name']);
        $filepath = $target_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', 'Nahrání souboru selhalo');
        }
        
        $file_url = $this->manager->get_directory_url($dir_key) . $filename;
        
        return [
            'url' => $file_url,
            'path' => $filepath,
            'filename' => $filename,
        ];
    }
    
    public function delete($file_url) {
        return $this->manager->delete_file($file_url);
    }
    
    public function should_remove_file($field_name) {
        return isset($_POST[$field_name . '_remove']) && $_POST[$field_name . '_remove'] === '1';
    }
    
    private function generate_filename($original_name) {
        $sanitized = sanitize_file_name($original_name);
        return time() . '_' . $sanitized;
    }
    
    public function get_config() {
        return $this->config;
    }
    
    public function enqueue_assets() {
        wp_enqueue_style(
            'saw-file-upload-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/file-upload/file-upload.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-file-upload-component',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/file-upload/file-upload.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
    }
}