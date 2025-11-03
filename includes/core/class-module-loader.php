<?php
/**
 * Module Loader
 * 
 * Auto-discovery modulů ze složky modules/.
 * Načte config.php, vytvoří manifest, cachuje ho.
 * 
 * @package SAW_Visitors
 * @version 2.1.0
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
     * 
     * Skenuje složku modules/, načte config.php, vytvoří manifest.
     * Cache se na 24 hodin.
     * 
     * @return array Module manifest
     */
    public static function discover() {
        $cached = get_transient(self::$manifest_cache_key);
        if ($cached !== false && is_array($cached)) {
            self::$modules = $cached;
            return self::$modules;
        }
        
        $modules_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/';
        
        if (!is_dir($modules_dir)) {
            return [];
        }
        
        $module_folders = array_diff(scandir($modules_dir), ['.', '..']);
        
        foreach ($module_folders as $folder) {
            $folder_path = $modules_dir . $folder;
            
            if (!is_dir($folder_path)) {
                continue;
            }
            
            $config_file = $folder_path . '/config.php';
            
            if (file_exists($config_file)) {
                $config = require $config_file;
                
                $config['slug'] = $folder;
                $config['path'] = $folder_path . '/';
                
                if (!isset($config['capabilities'])) {
                    $config['capabilities'] = [
                        'list' => 'manage_options',
                        'view' => 'manage_options',
                        'create' => 'manage_options',
                        'edit' => 'manage_options',
                        'delete' => 'manage_options',
                    ];
                }
                
                if (!isset($config['cache'])) {
                    $config['cache'] = [
                        'enabled' => true,
                        'ttl' => 300,
                    ];
                }
                
                self::$modules[$folder] = $config;
            }
        }
        
        set_transient(self::$manifest_cache_key, self::$modules, self::$cache_ttl);
        
        return self::$modules;
    }
    
    /**
     * Load specific module
     * 
     * Lazy loading - načte controller a model až když je potřeba.
     * 
     * @param string $slug Module slug
     * @return array|false Module config nebo false
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
        
        $model_file = $path . 'model.php';
        if (file_exists($model_file)) {
            require_once $model_file;
        }
        
        $controller_file = $path . 'controller.php';
        if (file_exists($controller_file)) {
            require_once $controller_file;
        }
        
        return $config;
    }
    
    /**
     * Get all modules
     * 
     * @return array Module manifest
     */
    public static function get_all() {
        if (empty(self::$modules)) {
            self::discover();
        }
        return self::$modules;
    }
    
    /**
     * Clear cache
     * 
     * Volej po deploy, po přidání nového modulu, atd.
     */
    public static function clear_cache() {
        delete_transient(self::$manifest_cache_key);
        self::$modules = [];
        
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_saw_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_saw_%'"
        );
    }
    
    /**
     * Get module by route
     * 
     * Najde modul podle URL route.
     * 
     * @param string $route URL path (např. 'settings/customers')
     * @return array|null Module config nebo null
     */
    public static function get_by_route($route) {
        if (empty(self::$modules)) {
            self::discover();
        }
        
        $route = ltrim($route, '/');
        
        foreach (self::$modules as $slug => $config) {
            $module_route = ltrim($config['route'] ?? '', '/');
            
            if (strpos($route, $module_route) === 0) {
                return $config;
            }
        }
        
        return null;
    }
}