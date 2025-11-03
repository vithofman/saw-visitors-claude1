<?php
/**
 * SAW Session Manager
 * 
 * Centralized session management to replace scattered session_start() calls.
 * 
 * @package SAW_Visitors
 * @since 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Session_Manager {
    
    private static $instance = null;
    private $session_started = false;
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->start();
    }
    
    /**
     * Start session if not already started
     * 
     * @return bool
     */
    public function start() {
        if ($this->session_started) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                @session_start();
                $this->session_started = true;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW_Session_Manager] Session started');
                }
                
                return true;
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW_Session_Manager] Cannot start - headers sent');
                }
                return false;
            }
        }
        
        $this->session_started = true;
        return true;
    }
    
    /**
     * Check if session is active
     * 
     * @return bool
     */
    public function is_active() {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    
    /**
     * Regenerate session ID (security)
     * 
     * @return bool
     */
    public function regenerate() {
        if (!$this->is_active()) {
            return false;
        }
        
        session_regenerate_id(true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_Session_Manager] Session ID regenerated');
        }
        
        return true;
    }
    
    /**
     * Destroy session
     * 
     * @return bool
     */
    public function destroy() {
        if (!$this->is_active()) {
            return false;
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        $this->session_started = false;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAW_Session_Manager] Session destroyed');
        }
        
        return true;
    }
    
    /**
     * Get session variable
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        if (!$this->is_active()) {
            return $default;
        }
        
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session variable
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set($key, $value) {
        if (!$this->is_active()) {
            $this->start();
        }
        
        $_SESSION[$key] = $value;
        return true;
    }
    
    /**
     * Unset session variable
     * 
     * @param string $key
     * @return bool
     */
    public function unset($key) {
        if (!$this->is_active()) {
            return false;
        }
        
        unset($_SESSION[$key]);
        return true;
    }
    
    /**
     * Check if session variable exists
     * 
     * @param string $key
     * @return bool
     */
    public function has($key) {
        if (!$this->is_active()) {
            return false;
        }
        
        return isset($_SESSION[$key]);
    }
}