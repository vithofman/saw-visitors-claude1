<?php
/**
 * Middleware funkce pro SAW Visitors
 * 
 * Ochrana routes a kontrola oprávnění
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
 * Vyžaduje přihlášení (jakákoliv SAW role)
 * 
 * Pokud uživatel není přihlášen, přesměruje na příslušnou login stránku
 * 
 * @param string $role Volitelně - kontrola konkrétní role
 * @return void
 */
function saw_require_auth( $role = null ) {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    if ( ! $saw_auth->check_auth() ) {
        // Uživatel není přihlášen
        $redirect_url = saw_get_login_url( $role );
        
        // Zapamatovat původní URL pro redirect po přihlášení
        if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $return_url = esc_url_raw( $_SERVER['REQUEST_URI'] );
            $redirect_url = add_query_arg( 'redirect_to', urlencode( $return_url ), $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    // Pokud je specifikována role, zkontroluj ji
    if ( $role !== null ) {
        $current_user = $saw_auth->get_current_user();
        
        if ( $current_user->role !== $role ) {
            wp_die(
                __( 'Nemáte oprávnění k přístupu na tuto stránku.', 'saw-visitors' ),
                __( 'Přístup zamítnut', 'saw-visitors' ),
                array( 'response' => 403 )
            );
        }
    }
}

/**
 * Vyžaduje admin roli
 * 
 * @return void
 */
function saw_require_admin() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth( 'admin' );

    if ( ! $saw_auth->is_admin() ) {
        wp_die(
            __( 'Tato stránka je dostupná pouze pro administrátory.', 'saw-visitors' ),
            __( 'Přístup zamítnut', 'saw-visitors' ),
            array( 'response' => 403 )
        );
    }
}

/**
 * Vyžaduje manager roli
 * 
 * @return void
 */
function saw_require_manager() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth( 'manager' );

    if ( ! $saw_auth->is_manager() ) {
        wp_die(
            __( 'Tato stránka je dostupná pouze pro manažery.', 'saw-visitors' ),
            __( 'Přístup zamítnut', 'saw-visitors' ),
            array( 'response' => 403 )
        );
    }
}

/**
 * Vyžaduje terminal roli
 * 
 * @return void
 */
function saw_require_terminal() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth( 'terminal' );

    if ( ! $saw_auth->is_terminal() ) {
        wp_die(
            __( 'Tato stránka je dostupná pouze pro terminály.', 'saw-visitors' ),
            __( 'Přístup zamítnut', 'saw-visitors' ),
            array( 'response' => 403 )
        );
    }
}

/**
 * Vyžaduje Super Admin (WordPress admin)
 * 
 * @return void
 */
function saw_require_super_admin() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            __( 'Tato stránka je dostupná pouze pro Super Adminy.', 'saw-visitors' ),
            __( 'Přístup zamítnut', 'saw-visitors' ),
            array( 'response' => 403 )
        );
    }
}

/**
 * Kontrola customer izolace
 * 
 * Ověří, že aktuální uživatel má přístup k datům zadaného zákazníka.
 * Super Admin má přístup ke všem zákazníkům.
 * 
 * @param int $customer_id ID zákazníka
 * @return void
 */
function saw_require_customer( $customer_id ) {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    // Nejprve zkontroluj, že je uživatel přihlášen
    saw_require_auth();

    // Super Admin má přístup ke všem
    if ( $saw_auth->is_super_admin() ) {
        return;
    }

    // Kontrola customer izolace
    if ( ! $saw_auth->check_customer_isolation( $customer_id ) ) {
        // Zalogování pokusu o přístup k cizím datům
        SAW_Audit::log( array(
            'action'      => 'customer_isolation_violation',
            'user_id'     => $saw_auth->get_current_user()->id ?? null,
            'customer_id' => $saw_auth->get_current_customer_id(),
            'details'     => sprintf( 'Pokus o přístup k datům zákazníka %d', $customer_id ),
            'ip_address'  => $_SERVER['REMOTE_ADDR'],
        ) );

        wp_die(
            __( 'Nemáte oprávnění k přístupu k těmto datům.', 'saw-visitors' ),
            __( 'Přístup zamítnut', 'saw-visitors' ),
            array( 'response' => 403 )
        );
    }
}

/**
 * Kontrola, zda má manager přístup k oddělení
 * 
 * @param int $department_id ID oddělení
 * @return void
 */
function saw_require_department_access( $department_id ) {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    // Nejprve zkontroluj, že je uživatel přihlášen
    saw_require_auth();

    $current_user = $saw_auth->get_current_user();

    // Super Admin a Admin mají přístup ke všem oddělením
    if ( $saw_auth->is_super_admin() || $saw_auth->is_admin() ) {
        return;
    }

    // Manager - zkontroluj, zda má přiřazeno toto oddělení
    if ( $saw_auth->is_manager() ) {
        global $wpdb;

        $has_access = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_user_departments 
             WHERE user_id = %d AND department_id = %d",
            $current_user->id,
            $department_id
        ) );

        if ( ! $has_access ) {
            SAW_Audit::log( array(
                'action'      => 'department_access_violation',
                'user_id'     => $current_user->id,
                'customer_id' => $current_user->customer_id,
                'details'     => sprintf( 'Manager nemá přístup k oddělení %d', $department_id ),
                'ip_address'  => $_SERVER['REMOTE_ADDR'],
            ) );

            wp_die(
                __( 'Nemáte oprávnění k přístupu k tomuto oddělení.', 'saw-visitors' ),
                __( 'Přístup zamítnut', 'saw-visitors' ),
                array( 'response' => 403 )
            );
        }

        return;
    }

    // Terminal nemá přístup k oddělením vůbec
    wp_die(
        __( 'Nemáte oprávnění k přístupu k oddělením.', 'saw-visitors' ),
        __( 'Přístup zamítnut', 'saw-visitors' ),
        array( 'response' => 403 )
    );
}

/**
 * Získání login URL pro danou roli
 * 
 * @param string|null $role Role ('admin', 'manager', 'terminal')
 * @return string
 */
function saw_get_login_url( $role = null ) {
    $urls = array(
        'admin'    => home_url( '/admin/login/' ),
        'manager'  => home_url( '/manager/login/' ),
        'terminal' => home_url( '/terminal/login/' ),
    );

    if ( $role && isset( $urls[ $role ] ) ) {
        return $urls[ $role ];
    }

    // Default - pokusit se určit z URL
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if ( strpos( $request_uri, '/admin/' ) !== false ) {
        return $urls['admin'];
    }
    if ( strpos( $request_uri, '/manager/' ) !== false ) {
        return $urls['manager'];
    }
    if ( strpos( $request_uri, '/terminal/' ) !== false ) {
        return $urls['terminal'];
    }

    // Fallback na admin
    return $urls['admin'];
}

/**
 * Kontrola AJAX nonce
 * 
 * @param string $action Název akce
 * @return void
 */
function saw_verify_ajax_nonce( $action ) {
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

    if ( ! wp_verify_nonce( $nonce, $action ) ) {
        SAW_Audit::log( array(
            'action'     => 'ajax_nonce_failed',
            'details'    => sprintf( 'Neplatný nonce pro akci: %s', $action ),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ) );

        wp_send_json_error( array(
            'message' => __( 'Bezpečnostní kontrola selhala. Obnovte stránku a zkuste to znovu.', 'saw-visitors' ),
            'code'    => 'nonce_failed',
        ) );
    }
}

/**
 * Kontrola POST nonce
 * 
 * @param string $action Název akce
 * @param string $name   Název nonce fieldu (default: '_wpnonce')
 * @return void
 */
function saw_verify_post_nonce( $action, $name = '_wpnonce' ) {
    $nonce = isset( $_POST[ $name ] ) ? sanitize_text_field( $_POST[ $name ] ) : '';

    if ( ! wp_verify_nonce( $nonce, $action ) ) {
        SAW_Audit::log( array(
            'action'     => 'post_nonce_failed',
            'details'    => sprintf( 'Neplatný nonce pro akci: %s', $action ),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ) );

        wp_die(
            __( 'Bezpečnostní kontrola selhala.', 'saw-visitors' ),
            __( 'Chyba', 'saw-visitors' ),
            array( 'response' => 403 )
        );
    }
}

/**
 * Získání aktuálního SAW uživatele
 * 
 * Pomocná funkce pro šablony
 * 
 * @return object|null
 */
function saw_get_current_user() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->get_current_user();
}

/**
 * Získání aktuálního customer ID
 * 
 * @return int|null
 */
function saw_get_current_customer_id() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->get_current_customer_id();
}

/**
 * Kontrola, zda je aktuální uživatel přihlášen
 * 
 * @return bool
 */
function saw_is_logged_in() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->check_auth();
}

/**
 * Kontrola, zda je aktuální uživatel admin
 * 
 * @return bool
 */
function saw_is_admin() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->is_admin();
}

/**
 * Kontrola, zda je aktuální uživatel manager
 * 
 * @return bool
 */
function saw_is_manager() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->is_manager();
}

/**
 * Kontrola, zda je aktuální uživatel terminal
 * 
 * @return bool
 */
function saw_is_terminal() {
    global $saw_auth;

    if ( ! isset( $saw_auth ) ) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->is_terminal();
}

/**
 * Kontrola, zda je aktuální uživatel Super Admin
 * 
 * @return bool
 */
function saw_is_super_admin() {
    return current_user_can( 'manage_options' );
}

/**
 * Rate limit kontrola pro AJAX
 * 
 * @param string $action   Název akce
 * @param int    $max      Maximální počet requestů
 * @param int    $window   Časové okno v sekundách
 * @return void
 */
function saw_ajax_rate_limit( $action, $max = 10, $window = 60 ) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'saw_rate_limit_' . md5( $ip . $action );
    
    $count = (int) get_transient( $key );

    if ( $count >= $max ) {
        SAW_Audit::log( array(
            'action'     => 'ajax_rate_limit_exceeded',
            'details'    => sprintf( 'Rate limit překročen pro akci: %s', $action ),
            'ip_address' => $ip,
        ) );

        wp_send_json_error( array(
            'message' => __( 'Příliš mnoho requestů. Zkuste to za chvíli.', 'saw-visitors' ),
            'code'    => 'rate_limit_exceeded',
        ) );
    }

    set_transient( $key, $count + 1, $window );
}

/**
 * Escape helper pro šablony
 * 
 * @param string $text Text k escapování
 * @return string
 */
function saw_esc( $text ) {
    return esc_html( $text );
}

/**
 * URL helper pro šablony
 * 
 * @param string $url URL k escapování
 * @return string
 */
function saw_esc_url( $url ) {
    return esc_url( $url );
}

/**
 * Attr helper pro šablony
 * 
 * @param string $attr Atribut k escapování
 * @return string
 */
function saw_esc_attr( $attr ) {
    return esc_attr( $attr );
}
