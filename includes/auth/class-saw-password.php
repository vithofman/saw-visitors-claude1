<?php
/**
 * SAW Password Management
 *
 * Handles password setup, reset, and token generation for SAW users.
 * Manages secure token creation, email notifications, and password validation.
 *
 * @package    SAW_Visitors
 * @subpackage Auth
 * @version    5.0.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_Password Class
 *
 * Manages password lifecycle including initial setup and reset functionality.
 *
 * @since 1.0.0
 */
class SAW_Password {

    /**
     * Minimum password length
     *
     * @since 1.0.0
     * @var int
     */
    const MIN_PASSWORD_LENGTH = 8;

    /**
     * Setup token validity in days
     *
     * @since 1.0.0
     * @var int
     */
    const SETUP_TOKEN_DAYS = 7;

    /**
     * Reset token validity in hours
     *
     * @since 1.0.0
     * @var int
     */
    const RESET_TOKEN_HOURS = 1;

    /**
     * Generate secure random token
     *
     * Creates cryptographically secure 64-character hex token.
     *
     * @since 1.0.0
     * @return string 64-character hex token
     */
    private function generate_token() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create password setup token for new user
     *
     * Generates secure token for initial password setup.
     * Token expires after 7 days.
     *
     * @since 1.0.0
     * @param int $saw_user_id SAW user ID
     * @return string|false Setup token or false on failure
     */
    public function create_setup_token($saw_user_id) {
        global $wpdb;

        $token = $this->generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::SETUP_TOKEN_DAYS . ' days'));
        
        // Verify user exists
        $user_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_users',
            $saw_user_id
        ));
        
        if (!$user_exists) {
            return false;
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'saw_users',
            [
                'password_setup_token' => $token,
                'password_setup_expires' => $expires,
            ],
            ['id' => $saw_user_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }
        
        return $token;
    }

    /**
     * Send welcome email with password setup link
     *
     * Sends initial setup email to new user with time-limited token.
     *
     * @since 1.0.0
     * @param string $email User email
     * @param string $role User role
     * @param string $first_name User first name
     * @param string $token Setup token
     * @return bool Success
     */
    public function send_welcome_email($email, $role, $first_name, $token) {
        $setup_url = add_query_arg(
            ['token' => $token],
            home_url('/set-password/')
        );

        $role_names = [
            'super_admin' => __('Super Administrátora', 'saw-visitors'),
            'admin' => __('Administrátora', 'saw-visitors'),
            'super_manager' => __('Super Manažera', 'saw-visitors'),
            'manager' => __('Manažera', 'saw-visitors'),
            'terminal' => __('Terminálu', 'saw-visitors'),
        ];

        $role_label = $role_names[$role] ?? __('uživatele', 'saw-visitors');

        $subject = __('Vítejte v SAW Visitors - Nastavte si heslo', 'saw-visitors');
        
        $message = sprintf(
            __("Dobrý den %s,\n\n", 'saw-visitors'),
            $first_name
        );
        $message .= sprintf(
            __("Váš účet %s byl vytvořen v systému SAW Visitors.\n\n", 'saw-visitors'),
            $role_label
        );
        $message .= __("Pro dokončení registrace si prosím nastavte heslo:\n", 'saw-visitors');
        $message .= $setup_url . "\n\n";
        $message .= sprintf(
            __("Odkaz je platný %d dní.\n\n", 'saw-visitors'),
            self::SETUP_TOKEN_DAYS
        );
        $message .= __("Po nastavení hesla se můžete přihlásit zde:\n", 'saw-visitors');
        $message .= home_url('/login/') . "\n\n";
        $message .= __("S pozdravem,\nSAW Visitors", 'saw-visitors');

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Validate password setup token
     *
     * Checks if setup token is valid and not expired.
     * Returns user data if valid.
     *
     * @since 1.0.0
     * @param string $token Setup token
     * @return array|false User data if valid, false otherwise
     */
    public function validate_setup_token($token) {
        global $wpdb;

        if (empty($token)) {
            return false;
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT su.*, wu.user_email 
             FROM %i su
             JOIN %i wu ON su.wp_user_id = wu.ID
             WHERE su.password_setup_token = %s 
             AND su.password_setup_expires > NOW()
             AND su.password_set_at IS NULL",
            $wpdb->prefix . 'saw_users',
            $wpdb->prefix . 'users',
            $token
        ), ARRAY_A);

        return $user ?: false;
    }

    /**
     * Set password for the first time
     *
     * Validates token and sets initial password for user.
     * Clears token and marks password as set.
     *
     * @since 1.0.0
     * @param string $token Setup token
     * @param string $password New password (plain text)
     * @return true|WP_Error Success or error
     */
    public function set_password($token, $password) {
        global $wpdb;

        // Validate token
        $user = $this->validate_setup_token($token);

        if (!$user) {
            return new WP_Error(
                'invalid_token',
                __('Odkaz je neplatný nebo vypršel', 'saw-visitors')
            );
        }

        // Validate password
        $password_error = $this->validate_password_strength($password);
        if (is_wp_error($password_error)) {
            return $password_error;
        }

        // Update WordPress user password
        $updated = wp_update_user([
            'ID' => $user['wp_user_id'],
            'user_pass' => $password,
        ]);

        if (is_wp_error($updated)) {
            return $updated;
        }

        // Clear token and mark password as set
        $wpdb->update(
            $wpdb->prefix . 'saw_users',
            [
                'password_setup_token' => null,
                'password_setup_expires' => null,
                'password_set_at' => current_time('mysql'),
            ],
            ['id' => $user['id']],
            ['%s', '%s', '%s'],
            ['%d']
        );

        // Audit log
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action' => 'password_set',
                'user_id' => $user['id'],
                'customer_id' => $user['customer_id'] ?? null,
                'details' => __('Uživatel si nastavil heslo poprvé', 'saw-visitors'),
            ]);
        }

        return true;
    }

    /**
     * Create password reset token
     *
     * Generates reset token and sends email with reset link.
     * Token expires after 1 hour.
     *
     * @since 1.0.0
     * @param string $email User email
     * @return true|WP_Error Success or error
     */
    public function create_reset_token($email) {
        global $wpdb;

        if (empty($email) || !is_email($email)) {
            return new WP_Error(
                'invalid_email',
                __('Neplatná emailová adresa', 'saw-visitors')
            );
        }

        // Find user by email (join WP user)
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT su.*, wu.user_email 
             FROM %i su
             JOIN %i wu ON su.wp_user_id = wu.ID
             WHERE wu.user_email = %s AND su.is_active = 1",
            $wpdb->prefix . 'saw_users',
            $wpdb->prefix . 'users',
            $email
        ), ARRAY_A);

        if (!$user) {
            // Security: return same message whether user exists or not
            return new WP_Error(
                'email_sent',
                __('Pokud email existuje, obdržíte odkaz pro reset hesla.', 'saw-visitors')
            );
        }

        // Generate token
        $token = $this->generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::RESET_TOKEN_HOURS . ' hour'));

        // Save token
        $updated = $wpdb->update(
            $wpdb->prefix . 'saw_users',
            [
                'password_reset_token' => $token,
                'password_reset_expires' => $expires,
            ],
            ['id' => $user['id']],
            ['%s', '%s'],
            ['%d']
        );

        if (!$updated) {
            return new WP_Error(
                'database_error',
                __('Nepodařilo se vytvořit reset token', 'saw-visitors')
            );
        }

        // Send email
        $sent = $this->send_reset_email($email, $token);

        if (!$sent) {
            return new WP_Error(
                'email_failed',
                __('Nepodařilo se odeslat email', 'saw-visitors')
            );
        }

        // Audit log
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action' => 'password_reset_requested',
                'user_id' => $user['id'],
                'customer_id' => $user['customer_id'] ?? null,
                'details' => sprintf(
                    __('Reset hesla požádán pro email: %s', 'saw-visitors'),
                    $email
                ),
            ]);
        }

        return true;
    }

    /**
     * Send password reset email
     *
     * Helper method to send reset email with token link.
     *
     * @since 5.0.0
     * @param string $email User email
     * @param string $token Reset token
     * @return bool Success
     */
    private function send_reset_email($email, $token) {
        $reset_url = add_query_arg(
            ['token' => $token],
            home_url('/reset-password/')
        );

        $subject = __('Reset hesla - SAW Visitors', 'saw-visitors');
        
        $message = __("Dobrý den,\n\n", 'saw-visitors');
        $message .= __("Obdrželi jsme žádost o reset hesla pro váš účet.\n\n", 'saw-visitors');
        $message .= __("Pro nastavení nového hesla klikněte na následující odkaz:\n", 'saw-visitors');
        $message .= $reset_url . "\n\n";
        $message .= sprintf(
            __("Odkaz je platný %d hodinu.\n\n", 'saw-visitors'),
            self::RESET_TOKEN_HOURS
        );
        $message .= __("Pokud jste reset hesla nežádali, můžete tento email ignorovat.\n\n", 'saw-visitors');
        $message .= __("S pozdravem,\nSAW Visitors", 'saw-visitors');

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Validate password reset token
     *
     * Checks if reset token is valid and not expired.
     *
     * @since 1.0.0
     * @param string $token Reset token
     * @return array|false User data if valid, false otherwise
     */
    public function validate_reset_token($token) {
        global $wpdb;

        if (empty($token)) {
            return false;
        }

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT su.*, wu.user_email 
             FROM %i su
             JOIN %i wu ON su.wp_user_id = wu.ID
             WHERE su.password_reset_token = %s 
             AND su.password_reset_expires > NOW()",
            $wpdb->prefix . 'saw_users',
            $wpdb->prefix . 'users',
            $token
        ), ARRAY_A);

        return $user ?: false;
    }

    /**
     * Reset password using token
     *
     * Validates token and updates password.
     * Clears reset token after successful update.
     *
     * @since 1.0.0
     * @param string $token Reset token
     * @param string $password New password (plain text)
     * @return true|WP_Error Success or error
     */
    public function reset_password($token, $password) {
        global $wpdb;

        // Validate token
        $user = $this->validate_reset_token($token);

        if (!$user) {
            return new WP_Error(
                'invalid_token',
                __('Odkaz je neplatný nebo vypršel', 'saw-visitors')
            );
        }

        // Validate password
        $password_error = $this->validate_password_strength($password);
        if (is_wp_error($password_error)) {
            return $password_error;
        }

        // Update WordPress user password
        $updated = wp_update_user([
            'ID' => $user['wp_user_id'],
            'user_pass' => $password,
        ]);

        if (is_wp_error($updated)) {
            return $updated;
        }

        // Clear token
        $wpdb->update(
            $wpdb->prefix . 'saw_users',
            [
                'password_reset_token' => null,
                'password_reset_expires' => null,
            ],
            ['id' => $user['id']],
            ['%s', '%s'],
            ['%d']
        );

        // Audit log
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action' => 'password_reset_completed',
                'user_id' => $user['id'],
                'customer_id' => $user['customer_id'] ?? null,
                'details' => __('Heslo bylo úspěšně resetováno', 'saw-visitors'),
            ]);
        }

        return true;
    }

    /**
     * Validate password strength
     *
     * Checks if password meets minimum requirements.
     *
     * @since 5.0.0
     * @param string $password Password to validate
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    private function validate_password_strength($password) {
        if (empty($password)) {
            return new WP_Error(
                'empty_password',
                __('Heslo nemůže být prázdné', 'saw-visitors')
            );
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return new WP_Error(
                'weak_password',
                sprintf(
                    __('Heslo musí mít alespoň %d znaků', 'saw-visitors'),
                    self::MIN_PASSWORD_LENGTH
                )
            );
        }

        return true;
    }

    /**
     * Clean up expired tokens
     *
     * Removes expired setup and reset tokens from database.
     * Should be run via WordPress cron.
     *
     * @since 1.0.0
     * @return void
     */
    public static function cleanup_expired_tokens() {
        global $wpdb;

        // Clean setup tokens
        $wpdb->query($wpdb->prepare(
            "UPDATE %i 
             SET password_setup_token = NULL, password_setup_expires = NULL
             WHERE password_setup_expires < NOW()",
            $wpdb->prefix . 'saw_users'
        ));

        // Clean reset tokens
        $wpdb->query($wpdb->prepare(
            "UPDATE %i 
             SET password_reset_token = NULL, password_reset_expires = NULL
             WHERE password_reset_expires < NOW()",
            $wpdb->prefix . 'saw_users'
        ));
    }
}