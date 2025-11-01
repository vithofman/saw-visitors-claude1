<?php
/**
 * File Upload Validator
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_File_Validator {
    
    private $config;
    
    public function __construct($config = []) {
        $default_config = require __DIR__ . '/config.php';
        $this->config = array_merge($default_config, $config);
    }
    
    public function validate($file) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', 'Neplatný soubor.');
        }
        
        $mime_check = $this->validate_mime_type($file);
        if (is_wp_error($mime_check)) {
            return $mime_check;
        }
        
        $size_check = $this->validate_file_size($file);
        if (is_wp_error($size_check)) {
            return $size_check;
        }
        
        return true;
    }
    
    private function validate_mime_type($file) {
        $allowed_mimes = $this->config['allowed_mimes'] ?? [];
        
        if (empty($allowed_mimes)) {
            return true;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_mimes)) {
            $allowed_extensions = $this->get_allowed_extensions($allowed_mimes);
            return new WP_Error('invalid_mime', 'Neplatný typ souboru. Povolené: ' . implode(', ', $allowed_extensions));
        }
        
        return true;
    }
    
    private function validate_file_size($file) {
        $max_size = $this->config['max_file_size'] ?? 0;
        
        if ($max_size > 0 && $file['size'] > $max_size) {
            $max_mb = round($max_size / 1048576, 1);
            return new WP_Error('file_too_large', 'Soubor je příliš velký (max ' . $max_mb . 'MB)');
        }
        
        return true;
    }
    
    private function get_allowed_extensions($mimes) {
        $extensions = [];
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