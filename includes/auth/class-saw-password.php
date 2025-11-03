<?php
/**
 * SAW Password Management - DEBUG VERSION
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Password {

    /**
     * Generate secure random token
     * 
     * @return string 64-character hex token
     */
    private function generate_token() {
        error_log('SAW_Password: generate_token() START');
        $token = bin2hex(random_bytes(32));
        error_log('SAW_Password: Token generated: ' . substr($token, 0, 10) . '...');
        return $token;
    }

    /**
     * Create password setup token for new user
     * 
     * @param int $saw_user_id SAW user ID
     * @return string|false Setup token or false on failure
     */
    public function create_setup_token($saw_user_id) {
        error_log('SAW_Password: create_setup_token() START - user_id: ' . $saw_user_id);
        
        global $wpdb;

        $token = $this->generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        error_log('SAW_Password: Token: ' . substr($token, 0, 10) . '...');
        error_log('SAW_Password: Expires: ' . $expires);
        error_log('SAW_Password: Table: ' . $wpdb->prefix . 'saw_users');
        
        // Check if user exists
        $user_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE id = %d",
            $saw_user_id
        ));
        
        error_log('SAW_Password: User exists: ' . ($user_exists ? 'YES' : 'NO'));
        
        if (!$user_exists) {
            error_log('SAW_Password: ERROR - User not found!');
            return false;
        }

        error_log('SAW_Password: Attempting UPDATE...');
        
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
            error_log('SAW_Password: UPDATE FAILED!');
            error_log('SAW_Password: Last error: ' . $wpdb->last_error);
            error_log('SAW_Password: Last query: ' . $wpdb->last_query);
            return false;
        }
        
        error_log('SAW_Password: UPDATE successful - rows affected: ' . $updated);
        error_log('SAW_Password: create_setup_token() END - returning token');
        
        return $token;
    }

    /**
     * Send welcome email with password setup link
     * 
     * @param string $email User email
     * @param string $role User role
     * @param string $first_name User first name
     * @param string $token Setup token
     * @return bool Success
     */
    public function send_welcome_email($email, $role, $first_name, $token) {
        error_log('SAW_Password: send_welcome_email() START');
        error_log('SAW_Password: Email: ' . $email);
        error_log('SAW_Password: Role: ' . $role);
        error_log('SAW_Password: First name: ' . $first_name);
        error_log('SAW_Password: Token: ' . substr($token, 0, 10) . '...');
        
        $setup_url = add_query_arg(
            ['token' => $token],
            home_url('/set-password/')
        );
        
        error_log('SAW_Password: Setup URL: ' . $setup_url);

        $role_names = [
            'super_admin' => 'Super Administrátora',
            'admin' => 'Administrátora',
            'super_manager' => 'Super Manažera',
            'manager' => 'Manažera',
            'terminal' => 'Terminálu',
        ];

        $subject = 'Vítejte v SAW Visitors - Nastavte si heslo';
        
        $message = "Dobrý den {$first_name},\n\n";
        $message .= "Váš účet {$role_names[$role]} byl vytvořen v systému SAW Visitors.\n\n";
        $message .= "Pro dokončení registrace si prosím nastavte heslo:\n";
        $message .= $setup_url . "\n\n";
        $message .= "Odkaz je platný 7 dní.\n\n";
        $message .= "Po nastavení hesla se můžete přihlásit zde:\n";
        $message .= home_url('/login/') . "\n\n";
        $message .= "S pozdravem,\n";
        $message .= "SAW Visitors";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        error_log('SAW_Password: Sending email...');
        
        $sent = wp_mail($email, $subject, $message, $headers);
        
        error_log('SAW_Password: Email sent: ' . ($sent ? 'YES' : 'NO'));
        error_log('SAW_Password: send_welcome_email() END');
        
        return $sent;
    }

    /**
     * Validate password setup token
     * 
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
             FROM {$wpdb->prefix}saw_users su
             JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
             WHERE su.password_setup_token = %s 
             AND su.password_setup_expires > NOW()
             AND su.password_set_at IS NULL",
            $token
        ), ARRAY_A);

        return $user ?: false;
    }

    /**
     * Set password for the first time
     * 
     * @param string $token Setup token
     * @param string $password New password (plain text)
     * @return true|WP_Error Success or error
     */
    public function set_password($token, $password) {
        global $wpdb;

        // Validate token
        $user = $this->validate_setup_token($token);

        if (!$user) {
            return new WP_Error('invalid_token', 'Odkaz je neplatný nebo vypršel');
        }

        // Validate password
        if (empty($password)) {
            return new WP_Error('empty_password', 'Heslo nemůže být prázdné');
        }

        if (strlen($password) < 8) {
            return new WP_Error('weak_password', 'Heslo musí mít alespoň 8 znaků');
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
                'customer_id' => $user['customer_id'] ?? null, // ✅ FIXED
                'details' => 'Uživatel si nastavil heslo poprvé',
            ]);
        }

        return true;
    }

    /**
     * Create password reset token
     * 
     * @param string $email User email
     * @return true|WP_Error Success or error
     */
    public function create_reset_token($email) {
        global $wpdb;

        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Neplatná emailová adresa');
        }

        // Find user by email (join WP user)
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT su.*, wu.user_email 
             FROM {$wpdb->prefix}saw_users su
             JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
             WHERE wu.user_email = %s AND su.is_active = 1",
            $email
        ), ARRAY_A);

        if (!$user) {
            // Security: return same message whether user exists or not
            return new WP_Error('email_sent', 'Pokud email existuje, obdržíte odkaz pro reset hesla.');
        }

        // Generate token
        $token = $this->generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

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
            return new WP_Error('database_error', 'Nepodařilo se vytvořit reset token');
        }

        // Send email
        $reset_url = add_query_arg(
            ['token' => $token],
            home_url('/reset-password/')
        );

        $subject = 'Reset hesla - SAW Visitors';
        
        $message = "Dobrý den,\n\n";
        $message .= "Obdrželi jsme žádost o reset hesla pro váš účet.\n\n";
        $message .= "Pro nastavení nového hesla klikněte na následující odkaz:\n";
        $message .= $reset_url . "\n\n";
        $message .= "Odkaz je platný 1 hodinu.\n\n";
        $message .= "Pokud jste reset hesla nežádali, můžete tento email ignorovat.\n\n";
        $message .= "S pozdravem,\n";
        $message .= "SAW Visitors";

        $sent = wp_mail($email, $subject, $message);

        if (!$sent) {
            return new WP_Error('email_failed', 'Nepodařilo se odeslat email');
        }

        // Audit log
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action' => 'password_reset_requested',
                'user_id' => $user['id'],
                'customer_id' => $user['customer_id'] ?? null, // ✅ FIXED
                'details' => "Reset hesla požádán pro email: {$email}",
            ]);
        }

        return true;
    }

    /**
     * Validate password reset token
     * 
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
             FROM {$wpdb->prefix}saw_users su
             JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
             WHERE su.password_reset_token = %s 
             AND su.password_reset_expires > NOW()",
            $token
        ), ARRAY_A);

        return $user ?: false;
    }

    /**
     * Reset password using token
     * 
     * @param string $token Reset token
     * @param string $password New password (plain text)
     * @return true|WP_Error Success or error
     */
    public function reset_password($token, $password) {
        global $wpdb;

        // Validate token
        $user = $this->validate_reset_token($token);

        if (!$user) {
            return new WP_Error('invalid_token', 'Odkaz je neplatný nebo vypršel');
        }

        // Validate password
        if (empty($password)) {
            return new WP_Error('empty_password', 'Heslo nemůže být prázdné');
        }

        if (strlen($password) < 8) {
            return new WP_Error('weak_password', 'Heslo musí mít alespoň 8 znaků');
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
                'customer_id' => $user['customer_id'] ?? null, // ✅ FIXED
                'details' => 'Heslo bylo úspěšně resetováno',
            ]);
        }

        return true;
    }

    /**
     * Clean up expired tokens (run via cron)
     */
    public static function cleanup_expired_tokens() {
        global $wpdb;

        $wpdb->query(
            "UPDATE {$wpdb->prefix}saw_users 
             SET password_setup_token = NULL, password_setup_expires = NULL
             WHERE password_setup_expires < NOW()"
        );

        $wpdb->query(
            "UPDATE {$wpdb->prefix}saw_users 
             SET password_reset_token = NULL, password_reset_expires = NULL
             WHERE password_reset_expires < NOW()"
        );
    }
}