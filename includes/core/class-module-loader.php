<?php
/**
 * Module Loader
 * 
 * Auto-discovery modulů ze složky modules/
 * Manifest caching pro performance
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Loader 
{
    private static $modules = [];
    private static $manifest_cache_key = 'saw_module_manifest_v2';
    private static $cache_ttl = 86400;
    
    /**
     * Discover all modules
     */
    public static function discover() {
        $cached = get_transient(self::$manifest_cache_key);
        if ($cached !== false) {
            self::$modules = $cached;
            return self::$modules;
        }
        
        $modules_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/';
        
        if (!is_dir($modules_dir)) {
            return [];
        }
        
        $module_folders = array_diff(scandir($modules_dir), ['.', '..']);
        
        foreach ($module_folders as $folder) {
            $config_file = $modules_dir . $folder . '/config.php';
            
            if (file_exists($config_file)) {
                $config = require $config_file;
                $config['slug'] = $folder;
                $config['path'] = $modules_dir . $folder . '/';
                
                self::$modules[$folder] = $config;
            }
        }
        
        set_transient(self::$manifest_cache_key, self::$modules, self::$cache_ttl);
        
        return self::$modules;
    }
    
    /**
     * Load specific module
     */
    public static function load($slug) {
        if (empty(self::$modules)) {
            self::discover();
        }
        
        if (!isset(self::$modules[$slug])) {
            return false;
        }
        
        $config = self::$modules[$slug];
        $path = $config['path'];
        
        $controller_file = $path . 'controller.php';
        if (file_exists($controller_file)) {
            require_once $controller_file;
        }
        
        return $config;
    }
    
    /**
     * Get all modules
     */
    public static function get_all() {
        if (empty(self::$modules)) {
            self::discover();
        }
        return self::$modules;
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        delete_transient(self::$manifest_cache_key);
        self::$modules = [];
    }
}
