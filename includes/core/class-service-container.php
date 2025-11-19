<?php
/**
 * Service Container - Dependency Injection
 *
 * Provides a simple dependency injection container for managing
 * singleton and transient service instances.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Container Class
 *
 * Manages service registration and retrieval using singleton pattern
 * for registered services.
 *
 * @since 5.0.0
 */
class SAW_Service_Container {
    
    /**
     * Registered services
     *
     * @since 5.0.0
     * @var array
     */
    private static $services = [];
    
    /**
     * Service instances cache
     *
     * @since 5.0.0
     * @var array
     */
    private static $instances = [];
    
    /**
     * Register a service
     *
     * @since 5.0.0
     * @param string   $name      Service name
     * @param callable $callback  Factory function that returns service instance
     * @param bool     $singleton Whether service should be singleton (default: true)
     * @return void
     */
    public static function register($name, $callback, $singleton = true) {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException("Callback for service '{$name}' is not callable");
        }
        
        self::$services[$name] = [
            'callback'  => $callback,
            'singleton' => (bool) $singleton
        ];
    }
    
    /**
     * Get a service instance
     *
     * @since 5.0.0
     * @param string $name Service name
     * @return mixed Service instance
     * @throws Exception If service not found
     */
    public static function get($name) {
        if (!isset(self::$services[$name])) {
            throw new Exception("Service not found: {$name}");
        }
        
        $service = self::$services[$name];
        
        // Return cached instance if singleton
        if ($service['singleton'] && isset(self::$instances[$name])) {
            return self::$instances[$name];
        }
        
        // Create new instance
        $instance = call_user_func($service['callback']);
        
        // Cache if singleton
        if ($service['singleton']) {
            self::$instances[$name] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Check if service is registered
     *
     * @since 5.0.0
     * @param string $name Service name
     * @return bool True if registered, false otherwise
     */
    public static function has($name) {
        return isset(self::$services[$name]);
    }
    
    /**
     * Clear all services (for testing)
     *
     * @since 5.0.0
     * @return void
     */
    public static function reset() {
        self::$services = [];
        self::$instances = [];
    }
}

