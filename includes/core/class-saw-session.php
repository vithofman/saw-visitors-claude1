<?php
/**
 * SAW Session Management
 * Database-backed sessions with automatic cleanup
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Session {

    /**
     * Default session expiry (24 hours)
     */
    const DEFAULT_EXPIRY = 86400;

    /**
     * Maximum sessions per user
     */
    const MAX_SESSIONS_PER_USER = 5;

    /**
     * Create new session
     *
     * @param int $user_id     SAW user ID
     * @param int $customer_id Customer ID
     * @param int $expiry      Expiry time in seconds
     * @return string|false    Session token or false on error
     */
    public function create_session($user_id, $customer_id, $expiry = self::DEFAULT_EXPIRY) {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        $ip_address = $this->get_client_ip();
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $expires_at = date('Y-m-d H:i:s', time() + $expiry);

        $this->cleanup_old_sessions_for_user($user_id);

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_sessions',
            array(
                'session_token' => $token_hash,
                'user_id'       => $user_id,
                'customer_id'   => $customer_id,
                'ip_address'    => $ip_address,
                'user_agent'    => $user_agent,
                'last_activity' => current_time('mysql'),
                'expires_at'    => $expires_at,
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%s')
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
     * @param string $token Session token
     * @return array|false  Session data or false if invalid
     */
    public function validate_session($token) {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_sessions 
             WHERE session_token = %s AND expires_at > NOW()",
            $token_hash
        ), ARRAY_A);

        if (!$session) {
            return false;
        }

        $current_ip = $this->get_client_ip();
        if (apply_filters('saw_session_check_ip', true) && $session['ip_address'] !== $current_ip) {
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log(array(
                    'action'      => 'session_ip_mismatch',
                    'user_id'     => $session['user_id'],
                    'customer_id' => $session['customer_id'],
                    'ip_address'  => $current_ip,
                    'details'     => sprintf('IP changed from %s to %s', $session['ip_address'], $current_ip),
                ));
            }
        }

        $this->update_last_activity($token_hash);

        return $session;
    }

    /**
     * Destroy session
     *
     * @param string $token Session token
     * @return bool
     */
    public function destroy_session($token) {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $result = $wpdb->delete(
            $wpdb->prefix . 'saw_sessions',
            array('session_token' => $token_hash),
            array('%s')
        );

        $this->delete_session_cookie();

        return (bool) $result;
    }

    /**
     * Cleanup expired sessions
     *
     * @return int Number of deleted sessions
     */
    public function cleanup_expired() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}saw_sessions WHERE expires_at < NOW()"
        );

        if ($deleted > 0 && class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action'  => 'sessions_cleanup',
                'details' => sprintf('Deleted %d expired sessions', $deleted),
            ));
        }

        return $deleted;
    }

    /**
     * Get session data
     *
     * @param string $token Session token
     * @return array|false
     */
    public function get_session_data($token) {
        return $this->validate_session($token);
    }

    /**
     * Update last activity
     *
     * @param string $token_hash Session token hash
     * @return bool
     */
    public function update_last_activity($token_hash) {
        global $wpdb;

        return (bool) $wpdb->update(
            $wpdb->prefix . 'saw_sessions',
            array('last_activity' => current_time('mysql')),
            array('session_token' => $token_hash),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Destroy all user sessions
     *
     * @param int $user_id SAW user ID
     * @return int Number of destroyed sessions
     */
    public function destroy_all_user_sessions($user_id) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'saw_sessions',
            array('user_id' => $user_id),
            array('%d')
        );

        if (class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action'  => 'all_sessions_destroyed',
                'user_id' => $user_id,
                'details' => sprintf('Destroyed %d sessions', $deleted),
            ));
        }

        return $deleted;
    }

    /**
     * Get all active user sessions
     *
     * @param int $user_id SAW user ID
     * @return array
     */
    public function get_user_sessions($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_sessions 
             WHERE user_id = %d AND expires_at > NOW()
             ORDER BY last_activity DESC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Cleanup old sessions for user
     *
     * @param int $user_id SAW user ID
     * @return int Number of deleted sessions
     */
    private function cleanup_old_sessions_for_user($user_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_sessions 
             WHERE user_id = %d AND expires_at > NOW()",
            $user_id
        ));

        if ($count >= self::MAX_SESSIONS_PER_USER) {
            $to_delete = $count - self::MAX_SESSIONS_PER_USER + 1;

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}saw_sessions 
                 WHERE user_id = %d 
                 ORDER BY last_activity ASC 
                 LIMIT %d",
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
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
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
     * @param string $token  Session token
     * @param int    $expiry Expiry time in seconds
     */
    private function set_session_cookie($token, $expiry) {
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            setcookie('saw_session_token', $token, array(
                'expires'  => time() + $expiry,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ));
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
     */
    private function delete_session_cookie() {
        if (PHP_VERSION_ID >= 70300) {
            setcookie('saw_session_token', '', array(
                'expires'  => time() - 3600,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ));
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
     * Extend session
     *
     * @param string $token   Session token
     * @param int    $expiry  New expiry time in seconds
     * @return bool
     */
    public function extend_session($token, $expiry = self::DEFAULT_EXPIRY) {
        global $wpdb;

        $token_hash = hash('sha256', $token);
        $new_expires_at = date('Y-m-d H:i:s', time() + $expiry);

        $result = $wpdb->update(
            $wpdb->prefix . 'saw_sessions',
            array('expires_at' => $new_expires_at),
            array('session_token' => $token_hash),
            array('%s'),
            array('%s')
        );

        if ($result) {
            $this->set_session_cookie($token, $expiry);
        }

        return (bool) $result;
    }

    /**
     * Get session statistics
     *
     * @param int|null $customer_id Optional filter by customer
     * @return array
     */
    public function get_session_stats($customer_id = null) {
        global $wpdb;

        $where = $customer_id ? $wpdb->prepare('WHERE customer_id = %d', $customer_id) : '';

        $stats = array(
            'active_sessions' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_sessions 
                 {$where} AND expires_at > NOW()"
            ),
            'expired_sessions' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_sessions 
                 {$where} AND expires_at <= NOW()"
            ),
            'sessions_by_role' => $wpdb->get_results(
                "SELECT u.role, COUNT(*) as count 
                 FROM {$wpdb->prefix}saw_sessions s 
                 JOIN {$wpdb->prefix}saw_users u ON s.user_id = u.id 
                 {$where} AND s.expires_at > NOW()
                 GROUP BY u.role",
                ARRAY_A
            ),
        );

        return $stats;
    }
}