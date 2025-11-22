<?php
/**
 * SAW Global Cache Manager
 *
 * Unified caching system with 3-layer fallback:
 * 1. Static memory cache (fastest, request-scoped)
 * 2. WordPress object cache (Redis/Memcached if available)
 * 3. Transients (database fallback)
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @version    1.0.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Cache {
    
    /**
     * Static memory cache (request-scoped)
     * 
     * @var array
     */
    private static $memory_cache = array();
    
    /**
     * Cache statistics for debugging
     * 
     * @var array
     */
    private static $stats = array(
        'memory_hits' => 0,
        'object_hits' => 0,
        'transient_hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
    );
    
    /**
     * Detected cache backend
     * 
     * @var string|null
     */
    private static $backend = null;
    
    /**
     * Get value from cache
     *
     * 3-layer lookup: memory → object cache → transient
     *
     * @param string $key   Cache key
     * @param string $group Cache group (entity name)
     * @return mixed Cached value or false if not found
     */
    public static function get($key, $group = 'saw') {
        // Layer 1: Static memory cache (fastest)
        $memory_key = self::get_memory_key($key, $group);
        
        if (isset(self::$memory_cache[$memory_key])) {
            self::$stats['memory_hits']++;
            return self::$memory_cache[$memory_key];
        }
        
        // Layer 2: Object cache (Redis/Memcached)
        $full_key = self::get_full_key($key, $group);
        $cached = wp_cache_get($full_key, $group);
        
        if ($cached !== false) {
            self::$stats['object_hits']++;
            // Store in memory for subsequent hits
            self::$memory_cache[$memory_key] = $cached;
            return $cached;
        }
        
        // Layer 3: Transient (database fallback)
        $transient_key = self::get_transient_key($key, $group);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            self::$stats['transient_hits']++;
            // Warm up object cache
            wp_cache_set($full_key, $cached, $group, 300);
            // Store in memory
            self::$memory_cache[$memory_key] = $cached;
            return $cached;
        }
        
        self::$stats['misses']++;
        return false;
    }
    
    /**
     * Set value in cache
     *
     * Writes to all 3 layers simultaneously
     *
     * @param string $key   Cache key
     * @param mixed  $value Value to cache
     * @param int    $ttl   Time to live in seconds
     * @param string $group Cache group
     * @return bool True on success
     */
    public static function set($key, $value, $ttl = 300, $group = 'saw') {
        self::$stats['sets']++;
        
        // Layer 1: Memory cache
        $memory_key = self::get_memory_key($key, $group);
        self::$memory_cache[$memory_key] = $value;
        
        // Layer 2: Object cache (shorter TTL to prevent staleness)
        $full_key = self::get_full_key($key, $group);
        wp_cache_set($full_key, $value, $group, min($ttl, 300));
        
        // Layer 3: Transient (persistent)
        $transient_key = self::get_transient_key($key, $group);
        return set_transient($transient_key, $value, $ttl);
    }
    
    /**
     * Delete specific cache key
     *
     * Removes from all 3 layers
     *
     * @param string $key   Cache key
     * @param string $group Cache group
     * @return bool True on success
     */
    public static function delete($key, $group = 'saw') {
        self::$stats['deletes']++;
        
        // Layer 1: Memory
        $memory_key = self::get_memory_key($key, $group);
        unset(self::$memory_cache[$memory_key]);
        
        // Layer 2: Object cache
        $full_key = self::get_full_key($key, $group);
        wp_cache_delete($full_key, $group);
        
        // Layer 3: Transient
        $transient_key = self::get_transient_key($key, $group);
        return delete_transient($transient_key);
    }
    
    /**
     * Flush entire cache group
     *
     * @param string $group Cache group to flush
     * @return bool True on success
     */
    public static function flush($group = 'saw') {
        global $wpdb;
        
        // Clear memory cache for this group
        foreach (self::$memory_cache as $key => $value) {
            if (strpos($key, $group . ':') === 0) {
                unset(self::$memory_cache[$key]);
            }
        }
        
        // Flush object cache group
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($group);
        }
        
        // Delete transients from database
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_saw_' . $wpdb->esc_like($group) . '_%',
            '_transient_timeout_saw_' . $wpdb->esc_like($group) . '_%'
        ));
        
        return true;
    }
    
    /**
     * Remember pattern - fetch from cache or execute callback
     *
     * Convenient helper for lazy loading with cache
     *
     * @param string   $key      Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int      $ttl      Time to live in seconds
     * @param string   $group    Cache group
     * @return mixed Cached or generated value
     */
    public static function remember($key, callable $callback, $ttl = 300, $group = 'saw') {
        $cached = self::get($key, $group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl, $group);
        
        return $value;
    }
    
    /**
     * Get cache statistics
     *
     * @return array Statistics array
     */
    public static function get_stats() {
        $total = self::$stats['memory_hits'] + self::$stats['object_hits'] + 
                 self::$stats['transient_hits'] + self::$stats['misses'];
        
        return array_merge(self::$stats, array(
            'total_requests' => $total,
            'hit_ratio' => $total > 0 ? round((array_sum(array_slice(self::$stats, 0, 3)) / $total) * 100, 2) : 0,
            'backend' => self::detect_backend(),
        ));
    }
    
    /**
     * Reset statistics (useful for testing)
     *
     * @return void
     */
    public static function reset_stats() {
        self::$stats = array_fill_keys(array_keys(self::$stats), 0);
    }
    
    /**
     * Detect cache backend
     *
     * @return string 'redis', 'memcached', 'persistent', 'transients'
     */
    private static function detect_backend() {
        if (self::$backend !== null) {
            return self::$backend;
        }
        
        if (!wp_using_ext_object_cache()) {
            self::$backend = 'transients';
            return self::$backend;
        }
        
        global $wp_object_cache;
        
        // Check for Redis
        if (class_exists('Redis') && method_exists($wp_object_cache, 'redis_instance')) {
            self::$backend = 'redis';
            return self::$backend;
        }
        
        // Check for Memcached
        if (class_exists('Memcached') && method_exists($wp_object_cache, 'getMulti')) {
            self::$backend = 'memcached';
            return self::$backend;
        }
        
        self::$backend = 'persistent';
        return self::$backend;
    }
    
    /**
     * Generate memory cache key
     *
     * @param string $key   Cache key
     * @param string $group Cache group
     * @return string Memory key
     */
    private static function get_memory_key($key, $group) {
        return $group . ':' . $key;
    }
    
    /**
     * Generate full cache key
     *
     * @param string $key   Cache key
     * @param string $group Cache group
     * @return string Full key
     */
    private static function get_full_key($key, $group) {
        return 'saw_' . $group . '_' . $key;
    }
    
    /**
     * Generate transient key
     *
     * @param string $key   Cache key
     * @param string $group Cache group
     * @return string Transient key
     */
    private static function get_transient_key($key, $group) {
        // Limit transient key length (WordPress has 172 char limit)
        $full_key = 'saw_' . $group . '_' . $key;
        if (strlen($full_key) > 172) {
            $full_key = 'saw_' . $group . '_' . md5($key);
        }
        return $full_key;
    }
}

