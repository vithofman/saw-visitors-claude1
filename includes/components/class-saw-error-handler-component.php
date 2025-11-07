<?php
/**
 * SAW Authentication Controller - Database-First Login
 * 
 * Handles user authentication including login, logout, password setup,
 * and password reset. Implements database-first context management where
 * user context is stored in the database and loaded via SAW_Context.
 * 
 * @package     SAW_Visitors
 * @subpackage  Controllers/Auth
 * @version     5.0.0
 * @since       4.8.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Authentication Controller Class
 * 
 * Manages authentication flows with database-first context management.
 * 
 * @since 4.8.0
 */
class SAW_Controller_Auth {

    /**
     * Password handler instance
     * 
     * @since 4.8.0
     * @var SAW_Password
     */
    private $password;

    /**
     * Constructor
     * 
     * Initializes the password handler.
     * 
     * @since 4.8.0
     */
    public function __construct() {
        $this->password = new SAW_Password();
    }

    /**
     * Login handler
     * 
     * Processes login form submission and writes context to database,
     * then initializes SAW_Context for the authenticated user.
     * 
     * CRITICAL: Context is written to DATABASE first, then SAW_Context loads from DB.
     * 
     * @since 4.8.0
     * @return void
     */
    public function login() {
        if (is_user_logged_in()) {
            $this->redirect_after_login();
            return;
        }

        $error = '';
        $success = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_login')) {
                $error = 'Bezpečnostní kontrola selhala';
            } else {
                $email = sanitize_email($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                $credentials = array(
                    'user_login' => $email,
                    'user_password' => $password,
                    'remember' => !empty($_POST['remember_me']),
                );

                $user = wp_signon($credentials, is_ssl());

                if (is_wp_error($user)) {
                    $error = 'Neplatné přihlašovací údaje';
                } else {
                    global $wpdb;
                    $saw_user = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM %i 
                         WHERE wp_user_id = %d AND is_active = 1",
                        $wpdb->prefix . 'saw_users',
                        $user->ID
                    ), ARRAY_A);

                    if (!$saw_user) {
                        wp_logout();
                        $error = 'Účet není aktivní nebo neexistuje';
                    } elseif ($saw_user['password_set_at'] === null) {
                        wp_logout();
                        $error = 'Nejprve si musíte nastavit heslo přes odkaz z emailu';
                    } else {
                        // CRITICAL: WRITE CONTEXT TO DATABASE FIRST
                        
                        // Determine default branch for context
                        $default_branch_id = $this->get_default_branch_for_login($saw_user);
                        
                        // UPDATE database with context
                        $wpdb->update(
                            $wpdb->prefix . 'saw_users',
                            array(
                                'context_customer_id' => $saw_user['customer_id'],
                                'context_branch_id' => $default_branch_id,
                                'last_login' => current_time('mysql')
                            ),
                            array('id' => $saw_user['id']),
                            array('%d', '%d', '%s'),
                            array('%d')
                        );
                        
                        // INITIALIZE SESSION (for backwards compatibility)
                        if (class_exists('SAW_Session_Manager')) {
                            $session = SAW_Session_Manager::instance();
                            $session->regenerate();
                            $session->set('saw_user_id', $saw_user['id']);
                            $session->set('saw_role', $saw_user['role']);
                        } else {
                            if (session_status() !== PHP_SESSION_NONE) {
                                session_destroy();
                            }
                            session_start();
                            session_regenerate_id(true);
                            $_SESSION['saw_user_id'] = $saw_user['id'];
                            $_SESSION['saw_role'] = $saw_user['role'];
                        }
                        
                        // INITIALIZE SAW_CONTEXT (loads from DB)
                        if (class_exists('SAW_Context')) {
                            SAW_Context::reload();
                        }

                        // Audit log
                        if (class_exists('SAW_Audit')) {
                            SAW_Audit::log(array(
                                'action' => 'user_login',
                                'user_id' => $saw_user['id'],
                                'customer_id' => $saw_user['customer_id'],
                                'details' => 'Uživatel se přihlásil',
                            ));
                        }

                        $this->redirect_after_login($saw_user['role']);
                        return;
                    }
                }
            }
        }

        $this->render_template('login', array(
            'error' => $error,
            'success' => $success,
            'email' => $email,
        ));
    }
    
    /**
     * Get default branch for login based on role
     * 
     * Determines the appropriate default branch context for a user based on their role.
     * 
     * Logic:
     * - super_admin: null (can switch)
     * - admin: null (can switch)
     * - super_manager: first assigned branch or null
     * - manager: null (no switching)
     * - terminal: null (no switching)
     * 
     * @since 4.8.0
     * @param array $saw_user SAW user data
     * @return int|null Branch ID or null
     */
    private function get_default_branch_for_login($saw_user) {
        $role = $saw_user['role'];
        
        // Super admin and admin don't have default branch
        if (in_array($role, array('super_admin', 'admin'), true)) {
            return null;
        }
        
        // Super manager: get first assigned branch
        if ($role === 'super_manager') {
            if (class_exists('SAW_User_Branches')) {
                $branches = SAW_User_Branches::get_branch_ids_for_user($saw_user['id']);
                return !empty($branches) ? $branches[0] : null;
            }
            return null;
        }
        
        // Manager and terminal: no default branch in context (they have fixed branch)
        return null;
    }

    /**
     * Forgot password handler
     * 
     * Processes forgot password form and sends reset email.
     * 
     * @since 4.8.0
     * @return void
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

        $this->render_template('forgot-password', array(
            'error' => $error,
            'success' => $success,
            'email' => $email,
        ));
    }

    /**
     * Set password handler
     * 
     * Processes initial password setup from setup token.
     * 
     * @since 4.8.0
     * @return void
     */
    public function set_password() {
        $token = $_GET['token'] ?? '';
        $error = '';
        $success = false;
        $token_invalid = false;

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

        $this->render_template('set-password', array(
            'token' => $token,
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'token_invalid' => $token_invalid,
        ));
    }

    /**
     * Reset password handler
     * 
     * Processes password reset from reset token.
     * 
     * @since 4.8.0
     * @return void
     */
    public function reset_password() {
        $token = $_GET['token'] ?? '';
        $error = '';
        $success = false;
        $token_invalid = false;

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

        $this->render_template('reset-password', array(
            'token' => $token,
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'token_invalid' => $token_invalid,
        ));
    }

    /**
     * Logout handler
     * 
     * Destroys session and logs out WordPress user.
     * Uses SAW_Session_Manager if available.
     * 
     * @since 4.8.0
     * @return void
     */
    public function logout() {
        if (class_exists('SAW_Session_Manager')) {
            SAW_Session_Manager::instance()->destroy();
        } else {
            if (session_status() !== PHP_SESSION_NONE) {
                $_SESSION = array();
                
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                
                session_destroy();
            }
        }

        wp_logout();

        wp_safe_redirect(home_url('/login/'));
        exit;
    }

    /**
     * Redirect after successful login
     * 
     * Redirects user to appropriate dashboard based on role.
     * 
     * @since 4.8.0
     * @param string|null $role User role
     * @return void
     */
    private function redirect_after_login($role = null) {
        if ($role === null) {
            if (class_exists('SAW_Context')) {
                $role = SAW_Context::get_role();
            }
        }

        $redirects = array(
            'super_admin' => '/wp-admin/',
            'admin' => '/admin/dashboard/',
            'super_manager' => '/admin/dashboard/',
            'manager' => '/manager/dashboard/',
            'terminal' => '/terminal/',
        );

        $url = $redirects[$role] ?? '/login/';
        wp_safe_redirect(home_url($url));
        exit;
    }

    /**
     * Render authentication template
     * 
     * Includes the specified template file with provided data.
     * 
     * @since 4.8.0
     * @param string $template Template name
     * @param array  $data     Template data
     * @return void
     */
    private function render_template($template, $data = array()) {
        extract($data);
        include SAW_VISITORS_PLUGIN_DIR . 'templates/auth/' . $template . '.php';
    }
}