<?php
/**
 * WordPress Admin Access Control
 * 
 * Blokuje přístup do WP admin pro všechny uživatele kromě Super Adminů
 * Managers (subscriber role) budou přesměrováni na svůj frontend
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Blokování WP admin přístupu pro non-super-admins
 */
function saw_block_wp_admin_access() {
    // Povolit Super Adminům
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    // Povolit AJAX requesty
    if ( wp_doing_ajax() ) {
        return;
    }

    // Povolit admin-post.php (pro form submissions)
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'admin-post.php' ) !== false ) {
        return;
    }

    // Povolit admin-ajax.php
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false ) {
        return;
    }

    // Všichni ostatní přihlášení uživatelé → redirect na frontend
    if ( is_user_logged_in() ) {
        global $wpdb;
        $current_wp_user = wp_get_current_user();

        // Načtení SAW user záznamu
        $saw_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d",
            $current_wp_user->ID
        ) );

        if ( $saw_user ) {
            // Zalogování pokusu o přístup
            SAW_Audit::log( array(
                'action'      => 'wp_admin_blocked',
                'user_id'     => $saw_user->id,
                'customer_id' => $saw_user->customer_id,
                'details'     => sprintf( 'Manager %s se pokusil přistoupit do WP admin', $saw_user->email ),
                'ip_address'  => $_SERVER['REMOTE_ADDR'],
            ) );

            // Redirect podle role
            switch ( $saw_user->role ) {
                case 'manager':
                    wp_safe_redirect( home_url( '/manager/dashboard/' ) );
                    break;
                case 'admin':
                    wp_safe_redirect( home_url( '/admin/dashboard/' ) );
                    break;
                case 'terminal':
                    wp_safe_redirect( home_url( '/terminal/checkin/' ) );
                    break;
                default:
                    wp_safe_redirect( home_url() );
            }
            exit;
        }

        // Pokud není SAW user (například starý WP user), odhlásit
        wp_logout();
        wp_safe_redirect( home_url() );
        exit;
    }

    // Nepřihlášení uživatelé → redirect na hlavní stránku
    wp_safe_redirect( home_url() );
    exit;
}
add_action( 'admin_init', 'saw_block_wp_admin_access', 1 );

/**
 * Skrytí admin baru pro non-super-admins
 */
function saw_hide_admin_bar() {
    if ( ! current_user_can( 'manage_options' ) && is_user_logged_in() ) {
        show_admin_bar( false );
    }
}
add_action( 'after_setup_theme', 'saw_hide_admin_bar' );

/**
 * Přesměrování po přihlášení
 * 
 * Managers (subscriber role) → frontend dashboard
 * Super Admins → WP admin
 */
function saw_login_redirect( $redirect_to, $request, $user ) {
    if ( ! is_wp_error( $user ) ) {
        // Super Admin → WP admin
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return admin_url();
        }

        // Manager (subscriber) → frontend dashboard
        if ( in_array( 'subscriber', (array) $user->roles, true ) ) {
            global $wpdb;

            $saw_user = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d",
                $user->ID
            ) );

            if ( $saw_user && $saw_user->role === 'manager' ) {
                return home_url( '/manager/dashboard/' );
            }
        }

        // Fallback
        return home_url();
    }

    return $redirect_to;
}
add_filter( 'login_redirect', 'saw_login_redirect', 10, 3 );

/**
 * Blokování přístupu k /wp-login.php pro SAW uživatele
 * 
 * SAW admins a terminals by neměli používat WP login - mají své vlastní
 */
function saw_block_wp_login_for_saw_users() {
    // Povolit pro logout akce
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) {
        return;
    }

    // Povolit pro lostpassword (managers)
    if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'lostpassword', 'rp', 'resetpass' ), true ) ) {
        return;
    }

    // Kontrola, zda je to POST request (login attempt)
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['log'] ) ) {
        global $wpdb;

        $email = sanitize_email( $_POST['log'] );

        // Zkontroluj, zda email patří SAW uživateli (admin/terminal)
        $saw_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users 
             WHERE email = %s AND role IN ('admin', 'terminal')",
            $email
        ) );

        if ( $saw_user ) {
            // SAW admin/terminal se pokouší přihlásit přes WP login
            SAW_Audit::log( array(
                'action'     => 'wp_login_blocked',
                'user_id'    => $saw_user->id,
                'details'    => sprintf( '%s se pokusil přihlásit přes wp-login.php', $saw_user->role ),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
            ) );

            // Redirect na příslušný login
            $redirect_url = $saw_user->role === 'admin' 
                ? home_url( '/admin/login/' )
                : home_url( '/terminal/login/' );

            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
}
add_action( 'login_init', 'saw_block_wp_login_for_saw_users' );

/**
 * Úprava WP login error zpráv
 * 
 * Nezobrazovat, zda uživatel existuje (bezpečnostní opatření)
 */
function saw_login_errors( $error ) {
    global $errors;
    
    if ( is_wp_error( $errors ) ) {
        $err_codes = $errors->get_error_codes();

        // Obecná chybová zpráva
        if ( in_array( 'invalid_username', $err_codes, true ) || in_array( 'incorrect_password', $err_codes, true ) ) {
            $error = __( 'Neplatné přihlašovací údaje.', 'saw-visitors' );
        }
    }

    return $error;
}
add_filter( 'login_errors', 'saw_login_errors' );

/**
 * Odstranění "lost your password" odkazu pro non-managers
 */
function saw_remove_lost_password_link() {
    // Povolit pouze pro GET requesty (ne pro POST - login attempts)
    if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        return;
    }

    // Pokud je zadán email v GET parametru, zkontroluj ho
    if ( isset( $_GET['email'] ) ) {
        global $wpdb;
        
        $email = sanitize_email( $_GET['email'] );

        // Zkontroluj, zda email patří SAW admin/terminal
        $saw_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users 
             WHERE email = %s AND role IN ('admin', 'terminal')",
            $email
        ) );

        if ( $saw_user ) {
            // Přesměrovat na SAW forgot password
            $redirect_url = $saw_user->role === 'admin'
                ? home_url( '/admin/login/?action=forgot-password' )
                : home_url( '/terminal/login/?action=forgot-password' );

            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
}
add_action( 'login_form_lostpassword', 'saw_remove_lost_password_link' );

/**
 * Customizace WP login loga (volitelné - můžeš nahradit vlastním logem)
 */
function saw_custom_login_logo() {
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: none;
            background-size: auto;
            width: auto;
            text-align: center;
        }
        #login h1 a::before {
            content: "SAW Visitors";
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        .login form {
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
    <?php
}
add_action( 'login_enqueue_scripts', 'saw_custom_login_logo' );

/**
 * Úprava URL loga na login stránce
 */
function saw_login_logo_url() {
    return home_url();
}
add_filter( 'login_headerurl', 'saw_login_logo_url' );

/**
 * Úprava title atributu loga
 */
function saw_login_logo_url_title() {
    return __( 'SAW Visitors - Správa návštěv', 'saw-visitors' );
}
add_filter( 'login_headertext', 'saw_login_logo_url_title' );

/**
 * Odstranění zbytečných WP menu položek pro managers
 */
function saw_remove_wp_menu_items() {
    if ( ! current_user_can( 'manage_options' ) && is_user_logged_in() ) {
        // Managers nemají přístup do WP admin, takže by tento kód neměl běžet
        // Ale pro jistotu:
        remove_menu_page( 'index.php' );                  // Dashboard
        remove_menu_page( 'edit.php' );                   // Posts
        remove_menu_page( 'upload.php' );                 // Media
        remove_menu_page( 'edit.php?post_type=page' );   // Pages
        remove_menu_page( 'edit-comments.php' );          // Comments
        remove_menu_page( 'themes.php' );                 // Appearance
        remove_menu_page( 'plugins.php' );                // Plugins
        remove_menu_page( 'users.php' );                  // Users
        remove_menu_page( 'tools.php' );                  // Tools
        remove_menu_page( 'options-general.php' );        // Settings
    }
}
add_action( 'admin_menu', 'saw_remove_wp_menu_items', 999 );

/**
 * Zakázání profile update pro managers (používají SAW systém)
 */
function saw_disable_profile_update() {
    if ( ! current_user_can( 'manage_options' ) && is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        
        if ( in_array( 'subscriber', (array) $current_user->roles, true ) ) {
            // Manager by neměl upravovat svůj WP profil
            if ( defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE ) {
                wp_die(
                    __( 'Pro úpravu profilu použijte SAW Visitors frontend.', 'saw-visitors' ),
                    __( 'Přístup zamítnut', 'saw-visitors' ),
                    array( 'response' => 403 )
                );
            }
        }
    }
}
add_action( 'admin_init', 'saw_disable_profile_update' );

/**
 * Notifikace při pokusu o přístup (email Super Adminovi)
 * 
 * Volitelné - můžeš zapnout pro extra bezpečnost
 */
function saw_notify_admin_access_attempt( $user_id, $customer_id, $email ) {
    // Získání Super Admin emailu
    $admin_email = get_option( 'admin_email' );

    $subject = __( '[SAW Visitors] Pokus o přístup do WP admin', 'saw-visitors' );
    
    $message = sprintf(
        __( 'Manager se pokusil přistoupit do WordPress admin panelu.

Email: %s
User ID: %d
Customer ID: %d
Čas: %s
IP: %s

Uživatel byl přesměrován na frontend.', 'saw-visitors' ),
        $email,
        $user_id,
        $customer_id,
        current_time( 'mysql' ),
        $_SERVER['REMOTE_ADDR']
    );

    // Odkomentuj pro aktivaci notifikací
    // wp_mail( $admin_email, $subject, $message );
}

/**
 * Cleanup WP sessions pro SAW uživatele při logout
 */
function saw_cleanup_wp_session_on_logout() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        
        // Zkontroluj, zda je to manager
        global $wpdb;
        $saw_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND role = 'manager'",
            $current_user->ID
        ) );

        if ( $saw_user ) {
            // Destroy WP sessions
            $sessions = WP_Session_Tokens::get_instance( $current_user->ID );
            $sessions->destroy_all();
        }
    }
}
add_action( 'wp_logout', 'saw_cleanup_wp_session_on_logout' );
