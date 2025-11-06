<?php
/**
 * SAW Password Manager - Password Security and Reset System
 *
 * Handles bcrypt hashing, password validation, reset tokens,
 * password changes, and PIN generation for terminals.
 *
 * @package    SAW_Visitors
 * @subpackage Auth
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Password manager class
 *
 * @since 1.0.0
 */
class SAW_Password {

    /**
     * Bcrypt cost parameter
     *
     * @since 1.0.0
     * @var int
     */
    const BCRYPT_COST = 12;

    /**
     * Reset token expiry time in seconds (1 hour)
     *
     * @since 1.0.0
     * @var int
     */
    const RESET_TOKEN_EXPIRY = 3600;

    /**
     * Hash password using bcrypt
     *
     * @since 1.0.0
     * @param string $password Plaintext password
     * @return string Bcrypt hash
     */
    public function hash($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * Verify password against hash
     *
     * @since 1.0.0
     * @param string $password Plaintext password
     * @param string $hash     Bcrypt hash
     * @return bool True if password matches
     */
    public function verify($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Validate password strength
     *
     * @since 1.0.0
     * @param string $password Password to validate
     * @return bool|array True if strong, array of errors otherwise
     */
    public function validate($password) {
        $errors = [];

        if (strlen($password) < 12) {
            $errors[] = __('Password must be at least 12 characters long', 'saw-visitors');
        }

        if (strlen($password) > 128) {
            $errors[] = __('Password must not exceed 128 characters', 'saw-visitors');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = __('Password must contain at least one uppercase letter', 'saw-visitors');
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = __('Password must contain at least one lowercase letter', 'saw-visitors');
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = __('Password must contain at least one digit', 'saw-visitors');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = __('Password must contain at least one special character (!@#$%^&*)', 'saw-visitors');
        }

        $common_passwords = [
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
            'Admin123!'
        ];

        if (in_array(strtolower($password), array_map('strtolower', $common_passwords), true)) {
            $errors[] = __('This password is too common. Please choose a more complex password.', 'saw-visitors');
        }

        if (preg_match('/(.)\1{2,}/', $password)) {
            $errors[] = __('Password contains too many repeating characters', 'saw-visitors');
        }

        if (preg_match('/(abc|bcd|cde|123|234|345|456|567|678|789)/i', $password)) {
            $errors[] = __('Password contains simple character sequences', 'saw-visitors');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Generate random password
     *
     * @since 1.0.0
     * @param int $length Password length (default: 16)
     * @return string Generated password
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
     * Get random character from string
     *
     * @since 1.0.0
     * @param string $chars String of characters
     * @return string Random character
     */
    private function get_random_char($chars) {
        return $chars[random_int(0, strlen($chars) - 1)];
    }

    /**
     * Generate reset token for user
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return string|false Reset token or false on failure
     */
    public function generate_reset_token($user_id) {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_EXPIRY);

        $wpdb->delete(
            $wpdb->prefix . 'saw_password_resets',
            ['user_id' => $user_id],
            ['%d']
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_password_resets',
            [
                'user_id'    => $user_id,
                'token'      => $token_hash,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );

        if (!$result) {
            return false;
        }

        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'  => 'password_reset_requested',
                'user_id' => $user_id,
                'details' => 'Reset token generated'
            ]);
        }

        return $token;
    }

    /**
     * Validate reset token
     *
     * @since 1.0.0
     * @param string $token Reset token
     * @return int|false User ID or false if token is invalid
     */
    public function validate_reset_token($token) {
        global $wpdb;

        $token_hash = hash('sha256', $token);

        $reset = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE token = %s AND expires_at > NOW() AND used_at IS NULL",
            $wpdb->prefix . 'saw_password_resets',
            $token_hash
        ));

        if (!$reset) {
            return false;
        }

        return absint($reset->user_id);
    }

    /**
     * Reset password using token
     *
     * @since 1.0.0
     * @param string $token        Reset token
     * @param string $new_password New password
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function reset_password($token, $new_password) {
        global $wpdb;

        $user_id = $this->validate_reset_token($token);

        if (!$user_id) {
            return new WP_Error('invalid_token', __('Invalid or expired token', 'saw-visitors'));
        }

        $password_validation = $this->validate($new_password);
        if (is_array($password_validation)) {
            return new WP_Error('weak_password', implode(', ', $password_validation));
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_users',
            $user_id
        ));

        if (!$user) {
            return new WP_Error('user_not_found', __('User not found', 'saw-visitors'));
        }

        $password_hash = $this->hash($new_password);

        $wpdb->update(
            $wpdb->prefix . 'saw_users',
            ['password' => $password_hash],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        if ($user->role === 'manager' && $user->wp_user_id) {
            wp_set_password($new_password, $user->wp_user_id);
        }

        $token_hash = hash('sha256', $token);
        $wpdb->update(
            $wpdb->prefix . 'saw_password_resets',
            ['used_at' => current_time('mysql')],
            ['token' => $token_hash],
            ['%s'],
            ['%s']
        );

        if (class_exists('SAW_Session')) {
            $session = new SAW_Session();
            $session->destroy_all_user_sessions($user_id);
        }

        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'      => 'password_reset_completed',
                'user_id'     => $user_id,
                'customer_id' => $user->customer_id,
                'details'     => 'Password was reset'
            ]);
        }

        return true;
    }

    /**
     * Change password (requires old password)
     *
     * @since 1.0.0
     * @param int    $user_id      SAW user ID
     * @param string $old_password Old password
     * @param string $new_password New password
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function change_password($user_id, $old_password, $new_password) {
        global $wpdb;

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_users',
            $user_id
        ));

        if (!$user) {
            return new WP_Error('user_not_found', __('User not found', 'saw-visitors'));
        }

        if (!$this->verify($old_password, $user->password)) {
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log([
                    'action'      => 'password_change_failed',
                    'user_id'     => $user_id,
                    'customer_id' => $user->customer_id,
                    'details'     => 'Incorrect old password'
                ]);
            }

            return new WP_Error('incorrect_password', __('Incorrect old password', 'saw-visitors'));
        }

        $password_validation = $this->validate($new_password);
        if (is_array($password_validation)) {
            return new WP_Error('weak_password', implode(', ', $password_validation));
        }

        if ($old_password === $new_password) {
            return new WP_Error('same_password', __('New password must be different from the old one', 'saw-visitors'));
        }

        $password_hash = $this->hash($new_password);

        $wpdb->update(
            $wpdb->prefix . 'saw_users',
            ['password' => $password_hash],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        if ($user->role === 'manager' && $user->wp_user_id) {
            wp_set_password($new_password, $user->wp_user_id);
        }

        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'      => 'password_changed',
                'user_id'     => $user_id,
                'customer_id' => $user->customer_id,
                'details'     => 'Password was changed'
            ]);
        }

        $this->send_password_change_notification($user->email, $user->first_name);

        return true;
    }

    /**
     * Send password reset email
     *
     * @since 1.0.0
     * @param string $email User email
     * @param string $role  User role
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function send_reset_email($email, $role) {
        global $wpdb;

        $allowed_roles = ['admin', 'manager', 'terminal'];
        if (!in_array($role, $allowed_roles, true)) {
            return new WP_Error('invalid_role', __('Invalid role', 'saw-visitors'));
        }

        if ($role === 'manager') {
            $wp_user = get_user_by('email', $email);
            if (!$wp_user) {
                return new WP_Error('user_not_found', __('User with this email not found', 'saw-visitors'));
            }

            $result = retrieve_password($email);
            
            if (is_wp_error($result)) {
                return $result;
            }

            return true;
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE email = %s AND role = %s AND is_active = 1",
            $wpdb->prefix . 'saw_users',
            sanitize_email($email),
            sanitize_text_field($role)
        ));

        if (!$user) {
            return new WP_Error('user_not_found', __('User with this email not found', 'saw-visitors'));
        }

        $token = $this->generate_reset_token($user->id);

        if (!$token) {
            return new WP_Error('token_generation_failed', __('Failed to generate reset token', 'saw-visitors'));
        }

        $reset_url = add_query_arg(
            [
                'action' => 'reset_password',
                'token'  => $token,
                'role'   => $role
            ],
            home_url('/' . $role . '/login/')
        );

        $subject = __('Password Reset - SAW Visitors', 'saw-visitors');
        
        $message = sprintf(
            /* translators: 1: User first name, 2: Reset URL */
            __('Hello %1$s,

We received a request to reset your password.

To reset your password, click the following link:
%2$s

This link is valid for 1 hour.

If you did not request a password reset, please ignore this email.

Best regards,
SAW Visitors Team', 'saw-visitors'),
            $user->first_name,
            $reset_url
        );

        $sent = wp_mail($email, $subject, $message);

        if (!$sent) {
            return new WP_Error('email_failed', __('Failed to send email', 'saw-visitors'));
        }

        return true;
    }

    /**
     * Send password change notification
     *
     * @since 1.0.0
     * @param string $email      User email
     * @param string $first_name User first name
     */
    private function send_password_change_notification($email, $first_name) {
        $subject = __('Your password has been changed', 'saw-visitors');
        
        $message = sprintf(
            /* translators: %s: User first name */
            __('Hello %s,

Your password has been successfully changed.

If you did not make this change, please contact us immediately.

Best regards,
SAW Visitors Team', 'saw-visitors'),
            $first_name
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Cleanup expired reset tokens (called by cron)
     *
     * @since 1.0.0
     * @return int Number of deleted tokens
     */
    public function cleanup_expired_tokens() {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE expires_at < NOW() OR used_at IS NOT NULL",
            $wpdb->prefix . 'saw_password_resets'
        ));

        if ($deleted > 0 && class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'  => 'password_tokens_cleanup',
                'details' => sprintf(
                    /* translators: %d: number of deleted tokens */
                    __('Deleted %d expired tokens', 'saw-visitors'),
                    $deleted
                )
            ]);
        }

        return $deleted;
    }

    /**
     * Generate secure PIN for terminal (6 digits)
     *
     * @since 1.0.0
     * @return string 6-digit PIN
     */
    public function generate_pin() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hash PIN (same method as password)
     *
     * @since 1.0.0
     * @param string $pin PIN to hash
     * @return string Hashed PIN
     */
    public function hash_pin($pin) {
        return $this->hash($pin);
    }
}