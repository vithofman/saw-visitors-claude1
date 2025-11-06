<?php
/**
 * Module Loader - Auto-discovery and Loading System
 *
 * Scans modules directory, loads configurations, caches manifests,
 * and provides lazy loading for module controllers and models.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module loader class for auto-discovery and lazy loading
 *
 * @since 1.0.0
 */
class SAW_Module_Loader {
    
    /**
     * @var array Loaded modules manifest
     */
    private static $modules = [];
    
    /**
     * @var string Transient cache key for module manifest
     */
    private static $manifest_cache_key = 'saw_module_manifest_v2';
    
    /**
     * @var int Cache TTL in seconds (24 hours)
     */
    private static $cache_ttl = 86400;
    
    /**
     * @var array Default module capabilities
     */
    private static $default_capabilities = [
        'list'   => 'manage_options',
        'view'   => 'manage_options',
        'create' => 'manage_options',
        'edit'   => 'manage_options',
        'delete' => 'manage_options'
    ];
    
    /**
     * @var array Default cache settings
     */
    private static $default_cache = [
        'enabled' => true,
        'ttl'     => 300
    ];
    
    /**
     * Discover all modules from modules directory
     *
     * Scans modules folder, loads config.php files, creates manifest.
     * Results are cached for 24 hours.
     *
     * @since 1.0.0
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
            
            if (!file_exists($config_file)) {
                continue;
            }
            
            $config = self::load_config_file($config_file);
            
            if (!is_array($config)) {
                continue;
            }
            
            $config['slug'] = $folder;
            $config['path'] = trailingslashit($folder_path);
            
            if (!isset($config['capabilities'])) {
                $config['capabilities'] = self::$default_capabilities;
            }
            
            if (!isset($config['cache'])) {
                $config['cache'] = self::$default_cache;
            }
            
            self::$modules[$folder] = $config;
        }
        
        set_transient(self::$manifest_cache_key, self::$modules, self::$cache_ttl);
        
        return self::$modules;
    }
    
    /**
     * Load specific module (lazy loading)
     *
     * Loads controller and model files only when needed.
     *
     * @since 1.0.0
     * @param string $slug Module slug
     * @return array|false Module config or false if not found
     */
    public static function load($slug) {
        self::ensure_discovered();
        
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
     * Get all discovered modules
     *
     * @since 1.0.0
     * @return array Module manifest
     */
    public static function get_all() {
        self::ensure_discovered();
        return self::$modules;
    }
    
    /**
     * Get module by route path
     *
     * Finds module based on URL route matching.
     *
     * @since 1.0.0
     * @param string $route URL path (e.g., 'settings/customers')
     * @return array|null Module config or null if not found
     */
    public static function get_by_route($route) {
        self::ensure_discovered();
        
        $route = ltrim($route, '/');
        
        foreach (self::$modules as $slug => $config) {
            $module_route = ltrim($config['route'] ?? '', '/');
            
            if (strpos($route, $module_route) === 0) {
                return $config;
            }
        }
        
        return null;
    }
    
    /**
     * Clear module cache
     *
     * Call after deployment, after adding new modules, or when manifest changes.
     *
     * @since 1.0.0
     */
    public static function clear_cache() {
        delete_transient(self::$manifest_cache_key);
        self::$modules = [];
        
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_saw_') . '%',
            $wpdb->esc_like('_transient_timeout_saw_') . '%'
        ));
    }
    
    /**
     * Ensure modules are discovered
     *
     * Helper to avoid duplicate discovery checks.
     *
     * @since 1.0.0
     */
    private static function ensure_discovered() {
        if (empty(self::$modules)) {
            self::discover();
        }
    }
    
    /**
     * Safely load and validate config file
     *
     * @since 1.0.0
     * @param string $config_file Path to config file
     * @return array|false Config array or false on failure
     */
    private static function load_config_file($config_file) {
        if (!file_exists($config_file) || !is_readable($config_file)) {
            return false;
        }
        
        $config = require $config_file;
        
        return is_array($config) ? $config : false;
    }
}