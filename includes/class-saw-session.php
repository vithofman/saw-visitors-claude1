<?php
/**
 * Session management pro SAW Visitors
 * 
 * DB-backed sessions s automatickým cleanup
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Session {

    /**
     * Výchozí délka platnosti session (24 hodin)
     */
    const DEFAULT_EXPIRY = 86400; // 24 * 60 * 60

    /**
     * Maximální počet sessions na uživatele
     */
    const MAX_SESSIONS_PER_USER = 5;

    /**
     * Vytvoření nové session
     *
     * @param int $user_id     SAW user ID
     * @param int $customer_id Customer ID
     * @param int $expiry      Čas expirace v sekundách (default: 24h)
     * @return string|false    Session token nebo false při chybě
     */
    public function create_session( $user_id, $customer_id, $expiry = self::DEFAULT_EXPIRY ) {
        global $wpdb;

        // Generování bezpečného tokenu
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash( 'sha256', $token );

        // Získání IP adresy a user agenta
        $ip_address = $this->get_client_ip();
        $user_agent = substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 );

        // Výpočet času expirace
        $expires_at = date( 'Y-m-d H:i:s', time() + $expiry );

        // Kontrola počtu aktivních sessions uživatele
        $this->cleanup_old_sessions_for_user( $user_id );

        // Vložení do databáze
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_sessions',
            array(
                'session_token' => $token_hash,
                'user_id'       => $user_id,
                'customer_id'   => $customer_id,
                'ip_address'    => $ip_address,
                'user_agent'    => $user_agent,
                'last_activity' => current_time( 'mysql' ),
                'expires_at'    => $expires_at,
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return false;
        }

        // Nastavení cookie s tokenem (httponly, secure pokud je HTTPS)
        $this->set_session_cookie( $token, $expiry );

        return $token;
    }

    /**
     * Validace session tokenu
     *
     * @param string $token Session token
     * @return array|false  Session data nebo false pokud je neplatný
     */
    public function validate_session( $token ) {
        global $wpdb;

        $token_hash = hash( 'sha256', $token );

        // Načtení session z DB
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_sessions 
             WHERE session_token = %s AND expires_at > NOW()",
            $token_hash
        ), ARRAY_A );

        if ( ! $session ) {
            return false;
        }

        // Kontrola IP adresy (volitelně - může být vypnuto pro roaming users)
        $current_ip = $this->get_client_ip();
        if ( apply_filters( 'saw_session_check_ip', true ) && $session['ip_address'] !== $current_ip ) {
            // IP se změnila - možný session hijacking
            SAW_Audit::log( array(
                'action'      => 'session_ip_mismatch',
                'user_id'     => $session['user_id'],
                'customer_id' => $session['customer_id'],
                'ip_address'  => $current_ip,
                'details'     => sprintf( 'IP změněna z %s na %s', $session['ip_address'], $current_ip ),
            ) );

            // V production režimu by se session měla zrušit
            // return false;
        }

        // Aktualizace last_activity
        $this->update_last_activity( $token_hash );

        return $session;
    }

    /**
     * Zrušení session
     *
     * @param string $token Session token
     * @return bool
     */
    public function destroy_session( $token ) {
        global $wpdb;

        $token_hash = hash( 'sha256', $token );

        $result = $wpdb->delete(
            $wpdb->prefix . 'saw_sessions',
            array( 'session_token' => $token_hash ),
            array( '%s' )
        );

        // Smazání cookie
        $this->delete_session_cookie();

        return (bool) $result;
    }

    /**
     * Cleanup všech expirovaných sessions (voláno cronem)
     *
     * @return int Počet smazaných sessions
     */
    public function cleanup_expired() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}saw_sessions WHERE expires_at < NOW()"
        );

        if ( $deleted > 0 ) {
            SAW_Audit::log( array(
                'action'  => 'sessions_cleanup',
                'details' => sprintf( 'Smazáno %d expirovaných sessions', $deleted ),
            ) );
        }

        return $deleted;
    }

    /**
     * Získání dat session
     *
     * @param string $token Session token
     * @return array|false
     */
    public function get_session_data( $token ) {
        return $this->validate_session( $token );
    }

    /**
     * Aktualizace last_activity
     *
     * @param string $token_hash Hash session tokenu
     * @return bool
     */
    public function update_last_activity( $token_hash ) {
        global $wpdb;

        return (bool) $wpdb->update(
            $wpdb->prefix . 'saw_sessions',
            array( 'last_activity' => current_time( 'mysql' ) ),
            array( 'session_token' => $token_hash ),
            array( '%s' ),
            array( '%s' )
        );
    }

    /**
     * Zrušení všech sessions uživatele
     *
     * @param int $user_id SAW user ID
     * @return int Počet zrušených sessions
     */
    public function destroy_all_user_sessions( $user_id ) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'saw_sessions',
            array( 'user_id' => $user_id ),
            array( '%d' )
        );

        SAW_Audit::log( array(
            'action'  => 'all_sessions_destroyed',
            'user_id' => $user_id,
            'details' => sprintf( 'Zrušeno %d sessions', $deleted ),
        ) );

        return $deleted;
    }

    /**
     * Získání všech aktivních sessions uživatele
     *
     * @param int $user_id SAW user ID
     * @return array
     */
    public function get_user_sessions( $user_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_sessions 
             WHERE user_id = %d AND expires_at > NOW()
             ORDER BY last_activity DESC",
            $user_id
        ), ARRAY_A );
    }

    /**
     * Cleanup starých sessions pro uživatele (max 5 sessions)
     *
     * @param int $user_id SAW user ID
     * @return int Počet smazaných sessions
     */
    private function cleanup_old_sessions_for_user( $user_id ) {
        global $wpdb;

        // Získání počtu aktivních sessions
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_sessions 
             WHERE user_id = %d AND expires_at > NOW()",
            $user_id
        ) );

        if ( $count >= self::MAX_SESSIONS_PER_USER ) {
            // Smazat nejstarší sessions (ponechat 4, aby po vložení nové bylo 5)
            $to_delete = $count - self::MAX_SESSIONS_PER_USER + 1;

            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}saw_sessions 
                 WHERE user_id = %d 
                 ORDER BY last_activity ASC 
                 LIMIT %d",
                $user_id,
                $to_delete
            ) );

            return $to_delete;
        }

        return 0;
    }

    /**
     * Získání IP adresy klienta
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

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                    $ip = trim( $ip );

                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Nastavení session cookie
     *
     * @param string $token  Session token
     * @param int    $expiry Čas expirace v sekundách
     */
    private function set_session_cookie( $token, $expiry ) {
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Lax'; // Nebo 'Strict' pro větší bezpečnost

        // PHP 7.3+ podporuje SameSite jako array parametr
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( 'saw_session_token', $token, array(
                'expires'  => time() + $expiry,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ) );
        } else {
            // Fallback pro starší PHP
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
     * Smazání session cookie
     */
    private function delete_session_cookie() {
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( 'saw_session_token', '', array(
                'expires'  => time() - 3600,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ) );
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
     * Prodloužení session (refresh token)
     *
     * @param string $token   Session token
     * @param int    $expiry  Nový čas expirace v sekundách
     * @return bool
     */
    public function extend_session( $token, $expiry = self::DEFAULT_EXPIRY ) {
        global $wpdb;

        $token_hash = hash( 'sha256', $token );
        $new_expires_at = date( 'Y-m-d H:i:s', time() + $expiry );

        $result = $wpdb->update(
            $wpdb->prefix . 'saw_sessions',
            array( 'expires_at' => $new_expires_at ),
            array( 'session_token' => $token_hash ),
            array( '%s' ),
            array( '%s' )
        );

        if ( $result ) {
            // Aktualizovat cookie
            $this->set_session_cookie( $token, $expiry );
        }

        return (bool) $result;
    }

    /**
     * Získání statistik sessions
     *
     * @param int|null $customer_id Volitelně filtrovat podle zákazníka
     * @return array
     */
    public function get_session_stats( $customer_id = null ) {
        global $wpdb;

        $where = $customer_id ? $wpdb->prepare( 'WHERE customer_id = %d', $customer_id ) : '';

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
