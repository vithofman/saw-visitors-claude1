<?php
/**
 * SAW Session Manager - PHP Session Wrapper
 *
 * Centralized PHP session management to replace scattered session_start() calls.
 * Provides clean API for session operations.
 *
 * NOTE: This manages PHP $_SESSION only (for WP user_id, role).
 * For customer/branch context, use SAW_Context (database-backed).
 * For custom token-based sessions, use SAW_Session (database-backed).
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session manager class
 *
 * @since 1.0.0
 */
class SAW_Session_Manager {
    
    /**
     * Singleton instance
     *
     * @since 1.0.0
     * @var SAW_Session_Manager|null
     */
    private static $instance = null;
    
    /**
     * Session started flag
     *
     * @since 1.0.0
     * @var bool
     */
    private $session_started = false;
    
    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return SAW_Session_Manager
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->start();
    }
    
    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     *
     * @since 1.0.0
     * @throws Exception
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Start session if not already started
     *
     * @since 1.0.0
     * @return bool Success status
     */
    public function start() {
        if ($this->session_started) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                @session_start();
                $this->session_started = true;
                return true;
            } else {
                return false;
            }
        }
        
        $this->session_started = true;
        return true;
    }
    
    /**
     * Check if session is active
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_active() {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    
    /**
     * Regenerate session ID (security measure)
     *
     * @since 1.0.0
     * @return bool Success status
     */
    public function regenerate() {
        if (!$this->is_active()) {
            return false;
        }
        
        session_regenerate_id(true);
        
        return true;
    }
    
    /**
     * Destroy session
     *
     * @since 1.0.0
     * @return bool Success status
     */
    public function destroy() {
        if (!$this->is_active()) {
            return false;
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
        $this->session_started = false;
        
        return true;
    }
    
    /**
     * Get session variable
     *
     * @since 1.0.0
     * @param string $key     Session key
     * @param mixed  $default Default value if key not found
     * @return mixed Session value or default
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
     * @since 1.0.0
     * @param string $key   Session key
     * @param mixed  $value Value to store
     * @return bool Success status
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
     * @since 1.0.0
     * @param string $key Session key
     * @return bool Success status
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
     * @since 1.0.0
     * @param string $key Session key
     * @return bool
     */
    public function has($key) {
        if (!$this->is_active()) {
            return false;
        }
        
        return isset($_SESSION[$key]);
    }
    
    /**
     * Get all session data
     *
     * @since 1.0.0
     * @return array Session data
     */
    public function all() {
        if (!$this->is_active()) {
            return [];
        }
        
        return $_SESSION;
    }
    
    /**
     * Clear all session data (but keep session active)
     *
     * @since 1.0.0
     * @return bool Success status
     */
    public function clear() {
        if (!$this->is_active()) {
            return false;
        }
        
        $_SESSION = [];
        return true;
    }
}