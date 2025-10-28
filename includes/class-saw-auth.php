<?php
/**
 * Autentizační systém pro SAW Visitors
 * 
 * Dual-layer přístup:
 * - Managers: WordPress users (subscriber) + saw_users záznam
 * - Admins/Terminals: pouze saw_users (bez WP účtu)
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Auth {

    /**
     * Session handler
     *
     * @var SAW_Session
     */
    private $session;

    /**
     * Password handler
     *
     * @var SAW_Password
     */
    private $password;

    /**
     * Constructor - LAZY LOADING
     */
    public function __construct() {
        // Lenivá inicializace - vytvoří se až když budou potřeba
        $this->session = null;
        $this->password = null;
    }
    
    /**
     * Získání session instance (lazy loading)
     */
    private function get_session() {
        if ($this->session === null && class_exists('SAW_Session')) {
            $this->session = new SAW_Session();
        }
        return $this->session;
    }
    
    /**
     * Získání password instance (lazy loading)
     */
    private function get_password() {
        if ($this->password === null && class_exists('SAW_Password')) {
            $this->password = new SAW_Password();
        }
        return $this->password;
    }

    /**
     * Přihlášení uživatele (admin/manager/terminal)
     *
     * @param string $email    Email uživatele
     * @param string $password Heslo
     * @param string $role     Požadovaná role ('admin', 'manager', 'terminal')
     * @return array|WP_Error  Úspěch nebo chyba
     */
    public function login( $email, $password, $role ) {
        global $wpdb;

        // Validace vstupů
        if ( empty( $email ) || empty( $password ) || empty( $role ) ) {
            return new WP_Error( 'missing_fields', __( 'Vyplňte všechna pole', 'saw-visitors' ) );
        }

        // Validace role
        $allowed_roles = array( 'admin', 'manager', 'terminal' );
        if ( ! in_array( $role, $allowed_roles, true ) ) {
            return new WP_Error( 'invalid_role', __( 'Neplatná role', 'saw-visitors' ) );
        }

        // Kontrola rate limitu PŘED pokusem o přihlášení
        if ( ! $this->check_rate_limit( $_SERVER['REMOTE_ADDR'], 'login' ) ) {
            SAW_Audit::log( array(
                'action'     => 'login_rate_limited',
                'email'      => $email,
                'role'       => $role,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'details'    => 'Překročen limit pokusů o přihlášení',
            ) );
            
            return new WP_Error( 
                'rate_limit_exceeded', 
                __( 'Příliš mnoho pokusů o přihlášení. Zkuste to za 1 hodinu.', 'saw-visitors' ) 
            );
        }

        // Přihlášení podle role
        if ( $role === 'manager' ) {
            $result = $this->login_manager( $email, $password );
        } else {
            $result = $this->login_saw_user( $email, $password, $role );
        }

        // Zalogování pokusu
        if ( is_wp_error( $result ) ) {
            // Neúspěšné přihlášení - inkrementuj rate limit
            $this->increment_rate_limit( $_SERVER['REMOTE_ADDR'], 'login' );
            
            SAW_Audit::log( array(
                'action'     => 'login_failed',
                'email'      => $email,
                'role'       => $role,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'details'    => $result->get_error_message(),
            ) );
        } else {
            // Úspěšné přihlášení - reset rate limit
            $this->reset_rate_limit( $_SERVER['REMOTE_ADDR'], 'login' );
            
            SAW_Audit::log( array(
                'action'      => 'login_success',
                'user_id'     => $result['user_id'],
                'customer_id' => $result['customer_id'],
                'role'        => $role,
                'ip_address'  => $_SERVER['REMOTE_ADDR'],
            ) );
        }

        return $result;
    }

    /**
     * Přihlášení managera (WordPress user)
     *
     * @param string $email    Email
     * @param string $password Heslo
     * @return array|WP_Error
     */
    private function login_manager( $email, $password ) {
        global $wpdb;

        // Autentizace přes WordPress
        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'invalid_credentials', __( 'Neplatné přihlašovací údaje', 'saw-visitors' ) );
        }

        // Načtení SAW user záznamu
        $saw_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users 
             WHERE wp_user_id = %d AND role = 'manager' AND is_active = 1",
            $user->ID
        ) );

        if ( ! $saw_user ) {
            return new WP_Error( 'no_saw_record', __( 'SAW záznam uživatele nenalezen', 'saw-visitors' ) );
        }

        // Přihlášení WP uživatele
        wp_set_auth_cookie( $user->ID, false, is_ssl() );
        wp_set_current_user( $user->ID );
        do_action( 'wp_login', $user->user_login, $user );

        // Vytvoření SAW session
        $session_token = $this->get_session()->create_session( $saw_user->id, $saw_user->customer_id );

        if ( ! $session_token ) {
            return new WP_Error( 'session_failed', __( 'Nepodařilo se vytvořit session', 'saw-visitors' ) );
        }

        // Načtení oddělení managera
        $departments = $wpdb->get_col( $wpdb->prepare(
            "SELECT department_id FROM {$wpdb->prefix}saw_user_departments 
             WHERE user_id = %d",
            $saw_user->id
        ) );

        return array(
            'success'        => true,
            'user_id'        => $saw_user->id,
            'customer_id'    => $saw_user->customer_id,
            'role'           => 'manager',
            'wp_user_id'     => $user->ID,
            'departments'    => $departments,
            'session_token'  => $session_token,
            'redirect_url'   => home_url( '/manager/dashboard/' ),
        );
    }

    /**
     * Přihlášení SAW uživatele (admin/terminal) - BEZ WordPress účtu
     *
     * @param string $email    Email
     * @param string $password Heslo (nebo PIN pro terminal)
     * @param string $role     Role
     * @return array|WP_Error
     */
    private function login_saw_user( $email, $password, $role ) {
        global $wpdb;

        // Načtení uživatele
        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users 
             WHERE email = %s AND role = %s AND is_active = 1",
            $email,
            $role
        ) );

        if ( ! $user ) {
            return new WP_Error( 'user_not_found', __( 'Uživatel nenalezen', 'saw-visitors' ) );
        }

        // Ověření hesla (nebo PINu pro terminal)
        $password_field = ( $role === 'terminal' && ! empty( $user->pin ) ) ? 'pin' : 'password';
        $stored_hash = $user->$password_field;

        if ( empty( $stored_hash ) ) {
            return new WP_Error( 'no_password', __( 'Uživatel nemá nastavené heslo', 'saw-visitors' ) );
        }

        if ( ! $this->get_password()->verify( $password, $stored_hash ) ) {
            return new WP_Error( 'invalid_credentials', __( 'Neplatné přihlašovací údaje', 'saw-visitors' ) );
        }

        // Vytvoření session
        $session_token = $this->get_session()->create_session( $user->id, $user->customer_id );

        if ( ! $session_token ) {
            return new WP_Error( 'session_failed', __( 'Nepodařilo se vytvořit session', 'saw-visitors' ) );
        }

        // Redirect URL podle role
        $redirect_urls = array(
            'admin'    => home_url( '/admin/dashboard/' ),
            'terminal' => home_url( '/terminal/checkin/' ),
        );

        return array(
            'success'        => true,
            'user_id'        => $user->id,
            'customer_id'    => $user->customer_id,
            'role'           => $role,
            'session_token'  => $session_token,
            'redirect_url'   => $redirect_urls[ $role ],
        );
    }

    /**
     * Odhlášení uživatele
     *
     * @return bool
     */
    public function logout() {
        $current_user = $this->get_current_user();

        if ( $current_user ) {
            // Zalogování odhlášení
            SAW_Audit::log( array(
                'action'      => 'logout',
                'user_id'     => $current_user->id,
                'customer_id' => $current_user->customer_id,
                'role'        => $current_user->role,
                'ip_address'  => $_SERVER['REMOTE_ADDR'],
            ) );

            // Zrušení SAW session
            $session_token = $this->get_session_token();
            if ( $session_token ) {
                $this->get_session()->destroy_session( $session_token );
            }

            // Odhlášení WP uživatele (pokud je manager)
            if ( $current_user->role === 'manager' && is_user_logged_in() ) {
                wp_logout();
            }

            return true;
        }

        return false;
    }

    /**
     * Kontrola, zda je uživatel přihlášen
     *
     * @return bool
     */
    public function check_auth() {
        return $this->get_current_user() !== null;
    }

    /**
     * Získání aktuálního SAW uživatele
     *
     * @return object|null
     */
    public function get_current_user() {
        $session_token = $this->get_session_token();

        if ( ! $session_token ) {
            return null;
        }

        // Validace session
        $session_data = $this->get_session()->validate_session( $session_token );

        if ( ! $session_data ) {
            return null;
        }

        global $wpdb;
        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d AND is_active = 1",
            $session_data['user_id']
        ) );

        return $user;
    }

    /**
     * Získání customer_id aktuálního uživatele
     *
     * @return int|null
     */
    public function get_current_customer_id() {
        $user = $this->get_current_user();
        return $user ? (int) $user->customer_id : null;
    }

    /**
     * Kontrola, zda je uživatel Super Admin (WordPress admin)
     *
     * @return bool
     */
    public function is_super_admin() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Kontrola, zda je uživatel Admin
     *
     * @return bool
     */
    public function is_admin() {
        $user = $this->get_current_user();
        return $user && $user->role === 'admin';
    }

    /**
     * Kontrola, zda je uživatel Manager
     *
     * @return bool
     */
    public function is_manager() {
        $user = $this->get_current_user();
        return $user && $user->role === 'manager';
    }

    /**
     * Kontrola, zda je uživatel Terminal
     *
     * @return bool
     */
    public function is_terminal() {
        $user = $this->get_current_user();
        return $user && $user->role === 'terminal';
    }

    /**
     * Kontrola customer izolace
     * 
     * Ověří, zda aktuální uživatel má přístup k datům zadaného zákazníka
     *
     * @param int $customer_id ID zákazníka
     * @return bool
     */
    public function check_customer_isolation( $customer_id ) {
        // Super Admin má přístup ke všem zákazníkům
        if ( $this->is_super_admin() ) {
            return true;
        }

        $current_customer_id = $this->get_current_customer_id();

        return $current_customer_id && (int) $customer_id === $current_customer_id;
    }

    /**
     * Vytvoření nového managera (WordPress user + SAW user)
     *
     * @param array $data {
     *     @type string $email        Email
     *     @type string $password     Heslo
     *     @type string $first_name   Jméno
     *     @type string $last_name    Příjmení
     *     @type int    $customer_id  ID zákazníka
     *     @type array  $departments  Pole ID oddělení
     * }
     * @return int|WP_Error ID vytvořeného uživatele nebo chyba
     */
    public function create_manager( $data ) {
        global $wpdb;

        // Validace
        $required = array( 'email', 'password', 'first_name', 'last_name', 'customer_id' );
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', sprintf( __( 'Pole %s je povinné', 'saw-visitors' ), $field ) );
            }
        }

        // Validace hesla
        $password_validation = $this->password->validate( $data['password'] );
        if ( is_array( $password_validation ) ) {
            return new WP_Error( 'weak_password', implode( ', ', $password_validation ) );
        }

        // Kontrola, zda email již neexistuje
        if ( email_exists( $data['email'] ) ) {
            return new WP_Error( 'email_exists', __( 'Email již existuje', 'saw-visitors' ) );
        }

        // Vytvoření WordPress uživatele
        $wp_user_id = wp_create_user( $data['email'], $data['password'], $data['email'] );

        if ( is_wp_error( $wp_user_id ) ) {
            return $wp_user_id;
        }

        // Nastavení role na subscriber (bez WP admin přístupu)
        wp_update_user( array(
            'ID'         => $wp_user_id,
            'role'       => 'subscriber',
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
        ) );

        // Vytvoření SAW user záznamu
        $insert_result = $wpdb->insert(
            $wpdb->prefix . 'saw_users',
            array(
                'wp_user_id'  => $wp_user_id,
                'customer_id' => $data['customer_id'],
                'role'        => 'manager',
                'email'       => $data['email'],
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'is_active'   => 1,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
        );

        if ( ! $insert_result ) {
            // Rollback - smazat WP uživatele
            wp_delete_user( $wp_user_id );
            return new WP_Error( 'db_error', __( 'Nepodařilo se vytvořit SAW záznam', 'saw-visitors' ) );
        }

        $saw_user_id = $wpdb->insert_id;

        // Přiřazení oddělení
        if ( ! empty( $data['departments'] ) && is_array( $data['departments'] ) ) {
            foreach ( $data['departments'] as $department_id ) {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_user_departments',
                    array(
                        'user_id'       => $saw_user_id,
                        'department_id' => $department_id,
                        'assigned_at'   => current_time( 'mysql' ),
                    ),
                    array( '%d', '%d', '%s' )
                );
            }
        }

        // Zalogování
        SAW_Audit::log( array(
            'action'      => 'manager_created',
            'user_id'     => $saw_user_id,
            'customer_id' => $data['customer_id'],
            'details'     => 'Vytvořen nový manager: ' . $data['email'],
        ) );

        // Odeslání welcome emailu
        $this->send_manager_welcome_email( $data['email'], $data['first_name'], $data['password'] );

        return $saw_user_id;
    }

    /**
     * Smazání managera (WordPress user + SAW user)
     *
     * @param int $user_id SAW user ID
     * @return bool|WP_Error
     */
    public function delete_manager( $user_id ) {
        global $wpdb;

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d AND role = 'manager'",
            $user_id
        ) );

        if ( ! $user ) {
            return new WP_Error( 'user_not_found', __( 'Manager nenalezen', 'saw-visitors' ) );
        }

        // Smazání WP uživatele
        if ( $user->wp_user_id ) {
            wp_delete_user( $user->wp_user_id );
        }

        // Smazání z user_departments
        $wpdb->delete(
            $wpdb->prefix . 'saw_user_departments',
            array( 'user_id' => $user_id ),
            array( '%d' )
        );

        // Smazání SAW user záznamu
        $wpdb->delete(
            $wpdb->prefix . 'saw_users',
            array( 'id' => $user_id ),
            array( '%d' )
        );

        SAW_Audit::log( array(
            'action'      => 'manager_deleted',
            'user_id'     => $user_id,
            'customer_id' => $user->customer_id,
            'details'     => 'Smazán manager: ' . $user->email,
        ) );

        return true;
    }

    /**
     * Aktualizace managera
     *
     * @param int   $user_id SAW user ID
     * @param array $data    Data k aktualizaci
     * @return bool|WP_Error
     */
    public function update_manager( $user_id, $data ) {
        global $wpdb;

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE id = %d AND role = 'manager'",
            $user_id
        ) );

        if ( ! $user ) {
            return new WP_Error( 'user_not_found', __( 'Manager nenalezen', 'saw-visitors' ) );
        }

        // Aktualizace WP uživatele
        if ( $user->wp_user_id ) {
            $wp_update_data = array( 'ID' => $user->wp_user_id );

            if ( ! empty( $data['first_name'] ) ) {
                $wp_update_data['first_name'] = $data['first_name'];
            }
            if ( ! empty( $data['last_name'] ) ) {
                $wp_update_data['last_name'] = $data['last_name'];
            }
            if ( ! empty( $data['email'] ) ) {
                $wp_update_data['user_email'] = $data['email'];
            }

            wp_update_user( $wp_update_data );
        }

        // Aktualizace SAW user záznamu
        $saw_update_data = array();
        $format = array();

        if ( ! empty( $data['first_name'] ) ) {
            $saw_update_data['first_name'] = $data['first_name'];
            $format[] = '%s';
        }
        if ( ! empty( $data['last_name'] ) ) {
            $saw_update_data['last_name'] = $data['last_name'];
            $format[] = '%s';
        }
        if ( ! empty( $data['email'] ) ) {
            $saw_update_data['email'] = $data['email'];
            $format[] = '%s';
        }

        if ( ! empty( $saw_update_data ) ) {
            $saw_update_data['updated_at'] = current_time( 'mysql' );
            $format[] = '%s';

            $wpdb->update(
                $wpdb->prefix . 'saw_users',
                $saw_update_data,
                array( 'id' => $user_id ),
                $format,
                array( '%d' )
            );
        }

        // Aktualizace oddělení
        if ( isset( $data['departments'] ) && is_array( $data['departments'] ) ) {
            // Smazat staré přiřazení
            $wpdb->delete(
                $wpdb->prefix . 'saw_user_departments',
                array( 'user_id' => $user_id ),
                array( '%d' )
            );

            // Přidat nové
            foreach ( $data['departments'] as $department_id ) {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_user_departments',
                    array(
                        'user_id'       => $user_id,
                        'department_id' => $department_id,
                        'assigned_at'   => current_time( 'mysql' ),
                    ),
                    array( '%d', '%d', '%s' )
                );
            }
        }

        SAW_Audit::log( array(
            'action'      => 'manager_updated',
            'user_id'     => $user_id,
            'customer_id' => $user->customer_id,
            'details'     => 'Aktualizován manager: ' . $user->email,
        ) );

        return true;
    }

    /**
     * Získání session tokenu z cookie
     *
     * @return string|null
     */
    private function get_session_token() {
        return isset( $_COOKIE['saw_session_token'] ) ? sanitize_text_field( $_COOKIE['saw_session_token'] ) : null;
    }

    /**
     * Kontrola rate limitu
     *
     * @param string $ip     IP adresa
     * @param string $action Typ akce
     * @return bool True pokud je v limitu
     */
    private function check_rate_limit( $ip, $action ) {
        global $wpdb;

        $limit = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_rate_limits 
             WHERE ip_address = %s AND action = %s AND expires_at > NOW()",
            $ip,
            $action
        ) );

        if ( ! $limit ) {
            return true;
        }

        // Pokud je blokován
        if ( $limit->is_blocked ) {
            return false;
        }

        // Kontrola počtu pokusů (5 pokusů za 15 minut)
        return $limit->attempts < 5;
    }

    /**
     * Inkrementace rate limitu
     *
     * @param string $ip     IP adresa
     * @param string $action Typ akce
     */
    private function increment_rate_limit( $ip, $action ) {
        global $wpdb;

        $limit = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_rate_limits 
             WHERE ip_address = %s AND action = %s",
            $ip,
            $action
        ) );

        if ( ! $limit ) {
            // Vytvoř nový záznam
            $wpdb->insert(
                $wpdb->prefix . 'saw_rate_limits',
                array(
                    'ip_address' => $ip,
                    'action'     => $action,
                    'attempts'   => 1,
                    'expires_at' => date( 'Y-m-d H:i:s', strtotime( '+15 minutes' ) ),
                    'is_blocked' => 0,
                ),
                array( '%s', '%s', '%d', '%s', '%d' )
            );
        } else {
            $new_attempts = $limit->attempts + 1;
            $is_blocked = $new_attempts >= 5 ? 1 : 0;
            $expires_at = $is_blocked ? date( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ) : $limit->expires_at;

            $wpdb->update(
                $wpdb->prefix . 'saw_rate_limits',
                array(
                    'attempts'   => $new_attempts,
                    'is_blocked' => $is_blocked,
                    'expires_at' => $expires_at,
                ),
                array( 'id' => $limit->id ),
                array( '%d', '%d', '%s' ),
                array( '%d' )
            );
        }
    }

    /**
     * Reset rate limitu po úspěšném přihlášení
     *
     * @param string $ip     IP adresa
     * @param string $action Typ akce
     */
    private function reset_rate_limit( $ip, $action ) {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'saw_rate_limits',
            array(
                'ip_address' => $ip,
                'action'     => $action,
            ),
            array( '%s', '%s' )
        );
    }

    /**
     * Odeslání welcome emailu novému managerovi
     *
     * @param string $email      Email
     * @param string $first_name Jméno
     * @param string $password   Heslo (v plaintextu - pouze při vytvoření)
     */
    private function send_manager_welcome_email( $email, $first_name, $password ) {
        $subject = __( 'Váš účet byl vytvořen', 'saw-visitors' );
        
        $message = sprintf(
            __( 'Dobrý den %s,

Váš účet manager byl vytvořen.

Přihlašovací údaje:
Email: %s
Heslo: %s

Přihlaste se na: %s

S pozdravem,
SAW Visitors tým', 'saw-visitors' ),
            $first_name,
            $email,
            $password,
            home_url( '/manager/login/' )
        );

        wp_mail( $email, $subject, $message );
    }
}