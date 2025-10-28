<?php
/**
 * SAW Authentication Controller
 * Handles login, logout, forgot password, and reset password for all roles
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Auth {

    /**
     * Auth handler
     */
    private $auth;

    /**
     * Password handler
     */
    private $password;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new SAW_Auth();
        $this->password = new SAW_Password();
    }

    /**
     * Admin login handler
     * 
     * URL: /admin/login/
     */
    public function admin_login() {
        if ($this->auth->check_auth() && $this->auth->is_admin()) {
            wp_safe_redirect(home_url('/admin/dashboard/'));
            exit;
        }

        $error = '';
        $success = '';
        $email = '';

        if (isset($_GET['action']) && $_GET['action'] === 'forgot-password') {
            return $this->forgot_password('admin');
        }

        if (isset($_GET['action']) && $_GET['action'] === 'reset_password' && isset($_GET['token'])) {
            return $this->reset_password('admin', $_GET['token']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_login_admin')) {
                $error = __('Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors');
            } else {
                $email = sanitize_email($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                $result = $this->auth->login($email, $password, 'admin');

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $redirect_url = $result['redirect_url'];

                    if (!empty($_GET['redirect_to'])) {
                        $redirect_url = esc_url_raw($_GET['redirect_to']);
                    }

                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }

        $this->render_login_template(array(
            'page_title'           => __('Přihlášení administrátora', 'saw-visitors'),
            'role'                 => 'admin',
            'form_action'          => home_url('/admin/login/'),
            'forgot_password_url'  => home_url('/admin/login/?action=forgot-password'),
            'redirect_to'          => $_GET['redirect_to'] ?? home_url('/admin/dashboard/'),
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
            'show_other_roles'     => true,
        ));
    }

    /**
     * Manager login handler
     * 
     * URL: /manager/login/
     */
    public function manager_login() {
        if ($this->auth->check_auth() && $this->auth->is_manager()) {
            wp_safe_redirect(home_url('/manager/dashboard/'));
            exit;
        }

        $error = '';
        $success = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_login_manager')) {
                $error = __('Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors');
            } else {
                $email = sanitize_email($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                $result = $this->auth->login($email, $password, 'manager');

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $redirect_url = $result['redirect_url'];

                    if (!empty($_GET['redirect_to'])) {
                        $redirect_url = esc_url_raw($_GET['redirect_to']);
                    }

                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }

        $this->render_login_template(array(
            'page_title'           => __('Přihlášení manažera', 'saw-visitors'),
            'role'                 => 'manager',
            'form_action'          => home_url('/manager/login/'),
            'forgot_password_url'  => wp_lostpassword_url(home_url('/manager/login/')),
            'redirect_to'          => $_GET['redirect_to'] ?? home_url('/manager/dashboard/'),
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
            'show_other_roles'     => false,
        ));
    }

    /**
     * Terminal login handler
     * 
     * URL: /terminal/login/
     */
    public function terminal_login() {
        if ($this->auth->check_auth() && $this->auth->is_terminal()) {
            wp_safe_redirect(home_url('/terminal/checkin/'));
            exit;
        }

        $error = '';
        $success = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_login_terminal')) {
                $error = __('Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors');
            } else {
                $email = sanitize_email($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                $result = $this->auth->login($email, $password, 'terminal');

                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $redirect_url = $result['redirect_url'];

                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }

        $this->render_login_template(array(
            'page_title'  => __('Přihlášení terminálu', 'saw-visitors'),
            'role'        => 'terminal',
            'form_action' => home_url('/terminal/login/'),
            'redirect_to' => home_url('/terminal/checkin/'),
            'error'       => $error,
            'success'     => $success,
            'email'       => $email,
        ));
    }

    /**
     * Forgot password handler
     * 
     * URL: /admin/login/?action=forgot-password
     * 
     * @param string $role Role
     */
    private function forgot_password($role) {
        if ($role === 'manager') {
            wp_safe_redirect(wp_lostpassword_url(home_url('/manager/login/')));
            exit;
        }

        $error = '';
        $success = false;
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_forgot_password_' . $role)) {
                $error = __('Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors');
            } else {
                $email = sanitize_email($_POST['email'] ?? '');

                if (empty($email)) {
                    $error = __('Zadejte emailovou adresu.', 'saw-visitors');
                } else {
                    $result = $this->password->send_reset_email($email, $role);

                    if (is_wp_error($result)) {
                        $error = $result->get_error_message();
                    } else {
                        $success = true;
                    }
                }
            }
        }

        $this->render_forgot_password_template(array(
            'role'        => $role,
            'form_action' => home_url('/' . $role . '/login/?action=forgot-password'),
            'back_url'    => home_url('/' . $role . '/login/'),
            'error'       => $error,
            'success'     => $success,
            'email'       => $email,
        ));
    }

    /**
     * Reset password handler
     * 
     * URL: /admin/login/?action=reset_password&token=xxx
     * 
     * @param string $role  Role
     * @param string $token Reset token
     */
    private function reset_password($role, $token) {
        $error = '';
        $success = false;
        $token_invalid = false;

        $user_id = $this->password->validate_reset_token($token);

        if (!$user_id) {
            $token_invalid = true;
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_reset_password_' . $role)) {
                    $error = __('Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors');
                } else {
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if ($new_password !== $confirm_password) {
                        $error = __('Hesla se neshodují.', 'saw-visitors');
                    } elseif (empty($new_password)) {
                        $error = __('Zadejte nové heslo.', 'saw-visitors');
                    } else {
                        $result = $this->password->reset_password($token, $new_password);

                        if (is_wp_error($result)) {
                            $error = $result->get_error_message();
                        } else {
                            $success = true;
                        }
                    }
                }
            }
        }

        $this->render_reset_password_template(array(
            'role'                => $role,
            'token'               => $token,
            'form_action'         => add_query_arg(
                array(
                    'action' => 'reset_password',
                    'token'  => $token,
                ),
                home_url('/' . $role . '/login/')
            ),
            'login_url'           => home_url('/' . $role . '/login/'),
            'forgot_password_url' => home_url('/' . $role . '/login/?action=forgot-password'),
            'error'               => $error,
            'success'             => $success,
            'token_invalid'       => $token_invalid,
        ));
    }

    /**
     * Logout handler
     * 
     * URL: /logout/ or /{role}/logout/
     */
    public function logout() {
        $this->auth->logout();

        wp_safe_redirect(home_url());
        exit;
    }

    /**
     * Render login template
     * 
     * @param array $data Template data
     */
    private function render_login_template($data) {
        extract($data);
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/auth/login.php';
    }

    /**
     * Render forgot password template
     * 
     * @param array $data Template data
     */
    private function render_forgot_password_template($data) {
        extract($data);
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/auth/forgot-password.php';
    }

    /**
     * Render reset password template
     * 
     * @param array $data Template data
     */
    private function render_reset_password_template($data) {
        extract($data);
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/auth/reset-password.php';
    }
}