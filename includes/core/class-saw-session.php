<?php
/**
 * SAW Session Management - Database-Backed Sessions
 *
 * Handles session creation, validation, destruction with automatic cleanup,
 * IP tracking, and rate limiting (max 5 sessions per user).
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session management class
 *
 * @since 1.0.0
 */
class SAW_Session {

    /**
     * Default session expiry in seconds (24 hours)
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_EXPIRY = 86400;

    /**
     * Maximum sessions per user
     *
     * @since 1.0.0
     * @var int
     */
    const MAX_SESSIONS_PER_USER = 5;

    /**
     * Create new session
     *
     * @since 1.0.0
     * @param int $user_id     SAW user ID
     * @param int $customer_id Customer ID
     * @param int $expiry      Expiry time in seconds
     * @return string|false Session token or false on error
     */
    public function create_session($user_id, $customer_id, $expiry = self::DEFAULT_EXPIRY) {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        $ip_address = $this->get_client_ip();
        
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) 
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255)
            : '';

        $expires_at = date('Y-m-d H:i:s', time() + $expiry);

        $this->cleanup_old_sessions_for_user($user_id);

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_sessions',
            [
                'session_token' => $token_hash,
                'user_id'       => $user_id,
                'customer_id'   => $customer_id,
                'ip_address'    => $ip_address,
                'user_agent'    => $user_agent,
                'last_activity' => current_time('mysql'),
                'expires_at'    => $expires_at
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if (!$result) {
            return false;
        }

        $this->set_session_cookie($token, $expiry);

        return $token;
    }

    /**
     * Validate session token
     *
     * @since 1.0.0
     * @param string $token Session token
     * @return array|false Session data or false if invalid
     */
    public function validate_session($token) {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE session_token = %s AND expires_at > NOW()",
            $wpdb->prefix . 'saw_sessions',
            $token_hash
        ), ARRAY_A);

        if (!$session) {
            return false;
        }

        $current_ip = $this->get_client_ip();
        if (apply_filters('saw_session_check_ip', true) && $session['ip_address'] !== $current_ip) {
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log([
                    'action'      => 'session_ip_mismatch',
                    'user_id'     => $session['user_id'],
                    'customer_id' => $session['customer_id'],
                    'ip_address'  => $current_ip,
                    'details'     => sprintf('IP changed from %s to %s', $session['ip_address'], $current_ip)
                ]);
            }
        }

        $this->update_last_activity($token_hash);

        return $session;
    }

    /**
     * Destroy session
     *
     * @since 1.0.0
     * @param string $token Session token
     * @return bool Success status
     */
    public function destroy_session($token) {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $result = $wpdb->delete(
            $wpdb->prefix . 'saw_sessions',
            ['session_token' => $token_hash],
            ['%s']
        );

        $this->delete_session_cookie();

        return (bool) $result;
    }

    /**
     * Cleanup expired sessions
     *
     * @since 1.0.0
     * @return int Number of deleted sessions
     */
    public function cleanup_expired() {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE expires_at < NOW()",
            $wpdb->prefix . 'saw_sessions'
        ));

        if ($deleted > 0 && class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'  => 'sessions_cleanup',
                'details' => sprintf('Deleted %d expired sessions', $deleted)
            ]);
        }

        return $deleted;
    }

    /**
     * Get session data
     *
     * @since 1.0.0
     * @param string $token Session token
     * @return array|false Session data or false if invalid
     */
    public function get_session_data($token) {
        return $this->validate_session($token);
    }

    /**
     * Update last activity timestamp
     *
     * @since 1.0.0
     * @param string $token_hash Session token hash
     * @return bool Success status
     */
    public function update_last_activity($token_hash) {
        global $wpdb;

        return (bool) $wpdb->update(
            $wpdb->prefix . 'saw_sessions',
            ['last_activity' => current_time('mysql')],
            ['session_token' => $token_hash],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Destroy all user sessions
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return int Number of destroyed sessions
     */
    public function destroy_all_user_sessions($user_id) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'saw_sessions',
            ['user_id' => $user_id],
            ['%d']
        );

        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'  => 'all_sessions_destroyed',
                'user_id' => $user_id,
                'details' => sprintf('Destroyed %d sessions', $deleted)
            ]);
        }

        return $deleted;
    }

    /**
     * Get all active user sessions
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return array Active sessions
     */
    public function get_user_sessions($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE user_id = %d AND expires_at > NOW() ORDER BY last_activity DESC",
            $wpdb->prefix . 'saw_sessions',
            $user_id
        ), ARRAY_A);
    }

    /**
     * Cleanup old sessions for user (enforce max sessions limit)
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return int Number of deleted sessions
     */
    private function cleanup_old_sessions_for_user($user_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE user_id = %d AND expires_at > NOW()",
            $wpdb->prefix . 'saw_sessions',
            $user_id
        ));

        if ($count >= self::MAX_SESSIONS_PER_USER) {
            $to_delete = $count - self::MAX_SESSIONS_PER_USER + 1;

            $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE user_id = %d ORDER BY last_activity ASC LIMIT %d",
                $wpdb->prefix . 'saw_sessions',
                $user_id,
                $to_delete
            ));

            return $to_delete;
        }

        return 0;
    }

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Set session cookie
     *
     * @since 1.0.0
     * @param string $token  Session token
     * @param int    $expiry Expiry time in seconds
     */
    private function set_session_cookie($token, $expiry) {
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            setcookie('saw_session_token', $token, [
                'expires'  => time() + $expiry,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        } else {
            setcookie(
                'saw_session_token',
                $token,
                time() + $expiry,
                COOKIEPATH,
                COOKIE_DOMAIN,
                $secure,
                $httponly
            );
        }
    }

    /**
     * Delete session cookie
     *
     * @since 1.0.0
     */
    private function delete_session_cookie() {
        if (PHP_VERSION_ID >= 70300) {
            setcookie('saw_session_token', '', [
                'expires'  => time() - 3600,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie(
                'saw_session_token',
                '',
                time() - 3600,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
    }

    /**
     * Extend session expiry
     *
     * @since 1.0.0
     * @param string $token  Session token
     * @param int    $expiry New expiry time in seconds
     * @return bool Success status
     */
    public function extend_session($token, $expiry = self::DEFAULT_EXPIRY) {
        global $wpdb;

        $token_hash = hash('sha256', $token);
        $new_expires_at = date('Y-m-d H:i:s', time() + $expiry);

        $result = $wpdb->update(
            $wpdb->prefix . 'saw_sessions',
            ['expires_at' => $new_expires_at],
            ['session_token' => $token_hash],
            ['%s'],
            ['%s']
        );

        if ($result) {
            $this->set_session_cookie($token, $expiry);
        }

        return (bool) $result;
    }

    /**
     * Get session statistics
     *
     * @since 1.0.0
     * @param int|null $customer_id Optional filter by customer
     * @return array Statistics data
     */
    public function get_session_stats($customer_id = null) {
        global $wpdb;

        $where = $customer_id ? $wpdb->prepare('WHERE customer_id = %d', absint($customer_id)) : '';
        $sessions_table = $wpdb->prefix . 'saw_sessions';
        $users_table = $wpdb->prefix . 'saw_users';

        $stats = [
            'active_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i {$where} AND expires_at > NOW()",
                $sessions_table
            )),
            
            'expired_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i {$where} AND expires_at <= NOW()",
                $sessions_table
            )),
            
            'sessions_by_role' => $wpdb->get_results($wpdb->prepare(
                "SELECT u.role, COUNT(*) as count 
                 FROM %i s 
                 JOIN %i u ON s.user_id = u.id 
                 {$where} AND s.expires_at > NOW()
                 GROUP BY u.role",
                $sessions_table,
                $users_table
            ), ARRAY_A)
        ];

        return $stats;
    }
}