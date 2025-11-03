<?php
/**
 * SAW Authentication Controller
 * Handles login, logout, forgot password, set password, and reset password
 * 
 * All users authenticate via WordPress (wp_signon)
 * Custom UI for all auth pages
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Auth {

    private $password;

    public function __construct() {
        $this->password = new SAW_Password();
    }

    /**
     * Main login page
     * 
     * URL: /login/
     * 
     * Uses WordPress authentication (wp_signon)
     * Works for ALL roles (super_admin, admin, manager, terminal)
     */
    public function login() {
        // Already logged in? Redirect
        if (is_user_logged_in()) {
            $this->redirect_after_login();
            return;
        }

        $error = '';
        $success = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_login')) {
                $error = 'Bezpečnostní kontrola selhala';
            } else {
                $email = sanitize_email($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                // WordPress authentication
                $credentials = [
                    'user_login' => $email,
                    'user_password' => $password,
                    'remember' => !empty($_POST['remember_me']),
                ];

                $user = wp_signon($credentials, is_ssl());

                if (is_wp_error($user)) {
                    $error = 'Neplatné přihlašovací údaje';
                } else {
                    // Find SAW user record
                    global $wpdb;
                    $saw_user = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}saw_users 
                         WHERE wp_user_id = %d AND is_active = 1",
                        $user->ID
                    ), ARRAY_A);

                    if (!$saw_user) {
                        wp_logout();
                        $error = 'Účet není aktivní nebo neexistuje';
                    } elseif ($saw_user['password_set_at'] === null) {
                        wp_logout();
                        $error = 'Nejprve si musíte nastavit heslo přes odkaz z emailu';
                    } else {
                        // ✅ OPRAVA: Správně nastav session
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        session_regenerate_id(true);
                        
                        $_SESSION['saw_user_id'] = $saw_user['id'];
                        $_SESSION['saw_role'] = $saw_user['role'];
                        
                        // ✅ OPRAVENO: saw_current_customer_id místo saw_customer_id
                        $_SESSION['saw_current_customer_id'] = $saw_user['customer_id'];
                        $_SESSION['saw_current_branch_id'] = $saw_user['branch_id'];
                        
                        // ✅ OPRAVENO: Ulož customer_id i do user meta (pro persistenci)
                        if ($saw_user['customer_id']) {
                            update_user_meta($user->ID, 'saw_current_customer_id', $saw_user['customer_id']);
                        }
                        if ($saw_user['branch_id']) {
                            update_user_meta($user->ID, 'saw_current_branch_id', $saw_user['branch_id']);
                        }

                        // Update last login
                        $wpdb->update(
                            $wpdb->prefix . 'saw_users',
                            ['last_login' => current_time('mysql')],
                            ['id' => $saw_user['id']],
                            ['%s'],
                            ['%d']
                        );

                        // Audit log
                        if (class_exists('SAW_Audit')) {
                            SAW_Audit::log([
                                'action' => 'user_login',
                                'user_id' => $saw_user['id'],
                                'customer_id' => $saw_user['customer_id'],
                                'details' => 'Uživatel se přihlásil',
                            ]);
                        }

                        // Redirect
                        $this->redirect_after_login($saw_user['role']);
                        return;
                    }
                }
            }
        }

        // Render login template
        $this->render_template('login', [
            'error' => $error,
            'success' => $success,
            'email' => $email,
        ]);
    }

    /**
     * Forgot password page
     * 
     * URL: /forgot-password/
     */
    public function forgot_password() {
        $error = '';
        $success = false;
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_forgot_password')) {
                $error = 'Bezpečnostní kontrola selhala';
            } else {
                $email = sanitize_email($_POST['email'] ?? '');

                if (empty($email)) {
                    $error = 'Zadejte emailovou adresu';
                } else {
                    $result = $this->password->create_reset_token($email);

                    if (is_wp_error($result)) {
                        // Pro security: i když user neexistuje, ukážeme success
                        if ($result->get_error_code() === 'email_sent') {
                            $success = true;
                        } else {
                            $error = $result->get_error_message();
                        }
                    } else {
                        $success = true;
                    }
                }
            }
        }

        $this->render_template('forgot-password', [
            'error' => $error,
            'success' => $success,
            'email' => $email,
        ]);
    }

    /**
     * Set password page (first-time setup)
     * 
     * URL: /set-password/?token=xxx
     */
    public function set_password() {
        $token = $_GET['token'] ?? '';
        $error = '';
        $success = false;
        $token_invalid = false;

        // Validate token
        $user = $this->password->validate_setup_token($token);

        if (!$user) {
            $token_invalid = true;
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_set_password')) {
                    $error = 'Bezpečnostní kontrola selhala';
                } else {
                    $password = $_POST['password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if ($password !== $confirm_password) {
                        $error = 'Hesla se neshodují';
                    } elseif (empty($password)) {
                        $error = 'Zadejte heslo';
                    } elseif (strlen($password) < 8) {
                        $error = 'Heslo musí mít alespoň 8 znaků';
                    } else {
                        $result = $this->password->set_password($token, $password);

                        if (is_wp_error($result)) {
                            $error = $result->get_error_message();
                        } else {
                            $success = true;
                        }
                    }
                }
            }
        }

        $this->render_template('set-password', [
            'token' => $token,
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'token_invalid' => $token_invalid,
        ]);
    }

    /**
     * Reset password page
     * 
     * URL: /reset-password/?token=xxx
     */
    public function reset_password() {
        $token = $_GET['token'] ?? '';
        $error = '';
        $success = false;
        $token_invalid = false;

        // Validate token
        $user = $this->password->validate_reset_token($token);

        if (!$user) {
            $token_invalid = true;
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_reset_password')) {
                    $error = 'Bezpečnostní kontrola selhala';
                } else {
                    $password = $_POST['password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if ($password !== $confirm_password) {
                        $error = 'Hesla se neshodují';
                    } elseif (empty($password)) {
                        $error = 'Zadejte nové heslo';
                    } elseif (strlen($password) < 8) {
                        $error = 'Heslo musí mít alespoň 8 znaků';
                    } else {
                        $result = $this->password->reset_password($token, $password);

                        if (is_wp_error($result)) {
                            $error = $result->get_error_message();
                        } else {
                            $success = true;
                        }
                    }
                }
            }
        }

        $this->render_template('reset-password', [
            'token' => $token,
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'token_invalid' => $token_invalid,
        ]);
    }

    /**
     * Logout
     * 
     * URL: /logout/
     */
    public function logout() {
        // Destroy PHP session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();

        // WordPress logout
        wp_logout();

        // Redirect
        wp_safe_redirect(home_url('/login/'));
        exit;
    }

    /**
     * Redirect after successful login
     */
    private function redirect_after_login($role = null) {
        if ($role === null && isset($_SESSION['saw_role'])) {
            $role = $_SESSION['saw_role'];
        }

        $redirects = [
            'super_admin' => '/wp-admin/',
            'admin' => '/admin/dashboard/',
            'super_manager' => '/admin/dashboard/',
            'manager' => '/manager/dashboard/',
            'terminal' => '/terminal/',
        ];

        $url = $redirects[$role] ?? '/login/';
        wp_safe_redirect(home_url($url));
        exit;
    }

    /**
     * Render template
     */
    private function render_template($template, $data = []) {
        extract($data);
        include SAW_VISITORS_PLUGIN_DIR . 'templates/auth/' . $template . '.php';
    }
}