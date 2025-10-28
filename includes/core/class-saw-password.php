<?php
/**
 * SAW Password Manager
 * 
 * Bcrypt hashing, validace, reset tokeny, změna hesel
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Password {

    /**
     * Bcrypt cost parametr
     */
    const BCRYPT_COST = 12;

    /**
     * Platnost reset tokenu (1 hodina)
     */
    const RESET_TOKEN_EXPIRY = 3600;

    /**
     * Hash hesla pomocí bcrypt
     *
     * @param string $password Plaintext heslo
     * @return string Bcrypt hash
     */
    public function hash($password) {
        return password_hash($password, PASSWORD_BCRYPT, array('cost' => self::BCRYPT_COST));
    }

    /**
     * Ověření hesla proti hashu
     *
     * @param string $password Plaintext heslo
     * @param string $hash     Bcrypt hash
     * @return bool
     */
    public function verify($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Validace síly hesla
     *
     * @param string $password Heslo k validaci
     * @return bool|array True pokud je silné, jinak pole s chybami
     */
    public function validate($password) {
        $errors = array();

        if (strlen($password) < 12) {
            $errors[] = __('Heslo musí mít alespoň 12 znaků', 'saw-visitors');
        }

        if (strlen($password) > 128) {
            $errors[] = __('Heslo nesmí být delší než 128 znaků', 'saw-visitors');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = __('Heslo musí obsahovat alespoň jedno velké písmeno', 'saw-visitors');
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = __('Heslo musí obsahovat alespoň jedno malé písmeno', 'saw-visitors');
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = __('Heslo musí obsahovat alespoň jednu číslici', 'saw-visitors');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = __('Heslo musí obsahovat alespoň jeden speciální znak (!@#$%^&*)', 'saw-visitors');
        }

        $common_passwords = array(
            'password123',
            'password1234',
            'admin123',
            'admin1234',
            '12345678',
            '123456789',
            '1234567890',
            'qwerty123',
            'abc123456',
            'password!',
            'Admin123!',
        );

        if (in_array(strtolower($password), array_map('strtolower', $common_passwords), true)) {
            $errors[] = __('Toto heslo je příliš běžné. Zvolte složitější heslo.', 'saw-visitors');
        }

        if (preg_match('/(.)\1{2,}/', $password)) {
            $errors[] = __('Heslo obsahuje příliš mnoho opakujících se znaků', 'saw-visitors');
        }

        if (preg_match('/(abc|bcd|cde|123|234|345|456|567|678|789)/i', $password)) {
            $errors[] = __('Heslo obsahuje jednoduchou sekvenci znaků', 'saw-visitors');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Generování náhodného hesla
     *
     * @param int $length Délka hesla (default: 16)
     * @return string
     */
    public function generate($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        $password .= $this->get_random_char('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $password .= $this->get_random_char('abcdefghijklmnopqrstuvwxyz');
        $password .= $this->get_random_char('0123456789');
        $password .= $this->get_random_char('!@#$%^&*');

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $this->get_random_char($chars);
        }

        return str_shuffle($password);
    }

    /**
     * Získání náhodného znaku ze stringu
     *
     * @param string $chars String znaků
     * @return string
     */
    private function get_random_char($chars) {
        return $chars[random_int(0, strlen($chars) - 1)];
    }

    /**
     * Vytvoření reset tokenu pro uživatele
     *
     * @param int $user_id SAW user ID
     * @return string|false Reset token nebo false při chybě
     */
    public function generate_reset_token($user_id) {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_EXPIRY);

        $wpdb->delete(
            $wpdb->prefix . 'saw_password_resets',
            array('user_id' => $user_id),
            array('%d')
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_password_resets',
            array(
                'user_id'    => $user_id,
                'token'      => $token_hash,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );

        if (!$result) {
            return false;
        }

        SAW_Audit::log(array(
            'action'  => 'password_reset_requested',
            'user_id' => $user_id,
            'details' => 'Reset token vygenerován',
        ));

        return $token;
    }

    /**
     * Validace reset tokenu
     *
     * @param string $token Reset token
     * @return int|false User ID nebo false pokud je token neplatný
     */
    public function validate_reset_token($token) {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $reset = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_password_resets 
             WHERE token = %s AND expires_at > NOW() AND used_at IS NULL",
            $token_hash
        ));

        if (!$reset) {
            return false;
        }

        return (int) $reset->user_id;
    }

    /**
     * Reset hesla pomocí tokenu
     *
     * @param string $token        Reset token
     * @param string $new_password Nové heslo
     * @return bool|WP_Error
     */
    public function reset_password($token, $new_password) {
        global $wpdb;

        $user_id = $this->validate_reset_token($token);

        if (!$user_id) {
            return new WP_Error('invalid_token', __('Neplatný nebo expirovaný token', 'saw-visitors'));
        }

        $password_validation = $this->validate($new_password);
        if (is_array($password_validation)) {
            return new WP_Error('weak_password', implode(', ', $password_validation));
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d",
            $user_id
        ));

        if (!$user) {
            return new WP_Error('user_not_found', __('Uživatel nenalezen', 'saw-visitors'));
        }

        $password_hash = $this->hash($new_password);

        $wpdb->update(
            $wpdb->prefix . 'saw_users',
            array('password' => $password_hash),
            array('id' => $user_id),
            array('%s'),
            array('%d')
        );

        if ($user->role === 'manager' && $user->wp_user_id) {
            wp_set_password($new_password, $user->wp_user_id);
        }

        $token_hash = hash('sha256', $token);
        $wpdb->update(
            $wpdb->prefix . 'saw_password_resets',
            array('used_at' => current_time('mysql')),
            array('token' => $token_hash),
            array('%s'),
            array('%s')
        );

        $session = new SAW_Session();
        $session->destroy_all_user_sessions($user_id);

        SAW_Audit::log(array(
            'action'      => 'password_reset_completed',
            'user_id'     => $user_id,
            'customer_id' => $user->customer_id,
            'details'     => 'Heslo bylo resetováno',
        ));

        return true;
    }

    /**
     * Změna hesla (vyžaduje staré heslo)
     *
     * @param int    $user_id      SAW user ID
     * @param string $old_password Staré heslo
     * @param string $new_password Nové heslo
     * @return bool|WP_Error
     */
    public function change_password($user_id, $old_password, $new_password) {
        global $wpdb;

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d",
            $user_id
        ));

        if (!$user) {
            return new WP_Error('user_not_found', __('Uživatel nenalezen', 'saw-visitors'));
        }

        if (!$this->verify($old_password, $user->password)) {
            SAW_Audit::log(array(
                'action'      => 'password_change_failed',
                'user_id'     => $user_id,
                'customer_id' => $user->customer_id,
                'details'     => 'Nesprávné staré heslo',
            ));

            return new WP_Error('incorrect_password', __('Nesprávné staré heslo', 'saw-visitors'));
        }

        $password_validation = $this->validate($new_password);
        if (is_array($password_validation)) {
            return new WP_Error('weak_password', implode(', ', $password_validation));
        }

        if ($old_password === $new_password) {
            return new WP_Error('same_password', __('Nové heslo musí být odlišné od starého', 'saw-visitors'));
        }

        $password_hash = $this->hash($new_password);

        $wpdb->update(
            $wpdb->prefix . 'saw_users',
            array('password' => $password_hash),
            array('id' => $user_id),
            array('%s'),
            array('%d')
        );

        if ($user->role === 'manager' && $user->wp_user_id) {
            wp_set_password($new_password, $user->wp_user_id);
        }

        SAW_Audit::log(array(
            'action'      => 'password_changed',
            'user_id'     => $user_id,
            'customer_id' => $user->customer_id,
            'details'     => 'Heslo bylo změněno',
        ));

        $this->send_password_change_notification($user->email, $user->first_name);

        return true;
    }

    /**
     * Odeslání reset emailu
     *
     * @param string $email Email uživatele
     * @param string $role  Role uživatele
     * @return bool|WP_Error
     */
    public function send_reset_email($email, $role) {
        global $wpdb;

        $allowed_roles = array('admin', 'manager', 'terminal');
        if (!in_array($role, $allowed_roles, true)) {
            return new WP_Error('invalid_role', __('Neplatná role', 'saw-visitors'));
        }

        if ($role === 'manager') {
            $wp_user = get_user_by('email', $email);
            if (!$wp_user) {
                return new WP_Error('user_not_found', __('Uživatel s tímto emailem nenalezen', 'saw-visitors'));
            }

            $result = retrieve_password($email);
            
            if (is_wp_error($result)) {
                return $result;
            }

            return true;
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users 
             WHERE email = %s AND role = %s AND is_active = 1",
            $email,
            $role
        ));

        if (!$user) {
            return new WP_Error('user_not_found', __('Uživatel s tímto emailem nenalezen', 'saw-visitors'));
        }

        $token = $this->generate_reset_token($user->id);

        if (!$token) {
            return new WP_Error('token_generation_failed', __('Nepodařilo se vygenerovat reset token', 'saw-visitors'));
        }

        $reset_url = add_query_arg(
            array(
                'action' => 'reset_password',
                'token'  => $token,
                'role'   => $role,
            ),
            home_url('/' . $role . '/login/')
        );

        $subject = __('Reset hesla - SAW Visitors', 'saw-visitors');
        
        $message = sprintf(
            __('Dobrý den %s,

Obdrželi jsme požadavek na reset Vašeho hesla.

Pro reset hesla klikněte na následující odkaz:
%s

Odkaz je platný 1 hodinu.

Pokud jste o reset hesla nežádali, tento email ignorujte.

S pozdravem,
SAW Visitors tým', 'saw-visitors'),
            $user->first_name,
            $reset_url
        );

        $sent = wp_mail($email, $subject, $message);

        if (!$sent) {
            return new WP_Error('email_failed', __('Nepodařilo se odeslat email', 'saw-visitors'));
        }

        return true;
    }

    /**
     * Odeslání notifikace o změně hesla
     *
     * @param string $email      Email uživatele
     * @param string $first_name Jméno uživatele
     * @return void
     */
    private function send_password_change_notification($email, $first_name) {
        $subject = __('Vaše heslo bylo změněno', 'saw-visitors');
        
        $message = sprintf(
            __('Dobrý den %s,

Vaše heslo bylo úspěšně změněno.

Pokud jste heslo neměnili Vy, okamžitě nás kontaktujte.

S pozdravem,
SAW Visitors tým', 'saw-visitors'),
            $first_name
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Cleanup starých reset tokenů (voláno cronem)
     *
     * @return int Počet smazaných tokenů
     */
    public function cleanup_expired_tokens() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}saw_password_resets 
             WHERE expires_at < NOW() OR used_at IS NOT NULL"
        );

        if ($deleted > 0) {
            SAW_Audit::log(array(
                'action'  => 'password_tokens_cleanup',
                'details' => sprintf('Smazáno %d expirovaných tokenů', $deleted),
            ));
        }

        return $deleted;
    }

    /**
     * Generování bezpečného PINu pro terminal (6 číslic)
     *
     * @return string
     */
    public function generate_pin() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hash PINu (stejný způsob jako heslo)
     *
     * @param string $pin PIN
     * @return string
     */
    public function hash_pin($pin) {
        return $this->hash($pin);
    }
}