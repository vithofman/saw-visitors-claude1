<?php
/**
 * File Upload Manager
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_File_Manager {
    
    private $config;
    
    public function __construct($config = []) {
        $default_config = require __DIR__ . '/config.php';
        $this->config = array_merge($default_config, $config);
    }
    
    public function delete_file($file_url) {
        if (empty($file_url)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
        
        if (file_exists($file_path)) {
            return @unlink($file_path);
        }
        
        return false;
    }
    
    public function ensure_directory_exists($dir_key) {
        $upload_dir = wp_upload_dir();
        $upload_dirs = $this->config['upload_dirs'] ?? [];
        
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
    
    public function get_directory_path($dir_key) {
        $upload_dir = wp_upload_dir();
        $upload_dirs = $this->config['upload_dirs'] ?? [];
        
        if (!isset($upload_dirs[$dir_key])) {
            return false;
        }
        
        return $upload_dir['basedir'] . $upload_dirs[$dir_key];
    }
    
    public function get_directory_url($dir_key) {
        $upload_dir = wp_upload_dir();
        $upload_dirs = $this->config['upload_dirs'] ?? [];
        
        if (!isset($upload_dirs[$dir_key])) {
            return false;
        }
        
        return $upload_dir['baseurl'] . $upload_dirs[$dir_key];
    }
}