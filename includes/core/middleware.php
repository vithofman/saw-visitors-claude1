<?php
/**
 * SAW Middleware Functions
 * Route protection and permission checking
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Require authentication (any SAW role)
 * 
 * @param string $role Optional - check specific role
 * @return void
 */
function saw_require_auth($role = null) {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    if (!$saw_auth->check_auth()) {
        $redirect_url = saw_get_login_url($role);
        
        if (!empty($_SERVER['REQUEST_URI'])) {
            $return_url = esc_url_raw($_SERVER['REQUEST_URI']);
            $redirect_url = add_query_arg('redirect_to', urlencode($return_url), $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    if ($role !== null) {
        $current_user = $saw_auth->get_current_user();
        
        if ($current_user->role !== $role) {
            wp_die(
                __('Nemáte oprávnění k přístupu na tuto stránku.', 'saw-visitors'),
                __('Přístup zamítnut', 'saw-visitors'),
                array('response' => 403)
            );
        }
    }
}

/**
 * Require admin role
 * 
 * @return void
 */
function saw_require_admin() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth('admin');

    if (!$saw_auth->is_admin()) {
        wp_die(
            __('Tato stránka je dostupná pouze pro administrátory.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            array('response' => 403)
        );
    }
}

/**
 * Require manager role
 * 
 * @return void
 */
function saw_require_manager() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth('manager');

    if (!$saw_auth->is_manager()) {
        wp_die(
            __('Tato stránka je dostupná pouze pro manažery.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            array('response' => 403)
        );
    }
}

/**
 * Require terminal role
 * 
 * @return void
 */
function saw_require_terminal() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth('terminal');

    if (!$saw_auth->is_terminal()) {
        wp_die(
            __('Tato stránka je dostupná pouze pro terminály.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            array('response' => 403)
        );
    }
}

/**
 * Require Super Admin (WordPress admin)
 * 
 * @return void
 */
function saw_require_super_admin() {
    if (!current_user_can('manage_options')) {
        wp_die(
            __('Tato stránka je dostupná pouze pro Super Adminy.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            array('response' => 403)
        );
    }
}

/**
 * Check customer isolation
 * 
 * @param int $customer_id Customer ID
 * @return void
 */
function saw_require_customer($customer_id) {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth();

    if ($saw_auth->is_super_admin()) {
        return;
    }

    if (!$saw_auth->check_customer_isolation($customer_id)) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action'      => 'customer_isolation_violation',
                'user_id'     => $saw_auth->get_current_user()->id ?? null,
                'customer_id' => $saw_auth->get_current_customer_id(),
                'details'     => sprintf('Pokus o přístup k datům zákazníka %d', $customer_id),
                'ip_address'  => $_SERVER['REMOTE_ADDR'],
            ));
        }

        wp_die(
            __('Nemáte oprávnění k přístupu k těmto datům.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            array('response' => 403)
        );
    }
}

/**
 * Check department access for managers
 * 
 * @param int $department_id Department ID
 * @return void
 */
function saw_require_department_access($department_id) {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    saw_require_auth();

    $current_user = $saw_auth->get_current_user();

    if ($saw_auth->is_super_admin() || $saw_auth->is_admin()) {
        return;
    }

    if ($saw_auth->is_manager()) {
        global $wpdb;

        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_user_departments 
             WHERE user_id = %d AND department_id = %d",
            $current_user->id,
            $department_id
        ));

        if (!$has_access) {
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log(array(
                    'action'      => 'department_access_violation',
                    'user_id'     => $current_user->id,
                    'customer_id' => $current_user->customer_id,
                    'details'     => sprintf('Manager nemá přístup k oddělení %d', $department_id),
                    'ip_address'  => $_SERVER['REMOTE_ADDR'],
                ));
            }

            wp_die(
                __('Nemáte oprávnění k přístupu k tomuto oddělení.', 'saw-visitors'),
                __('Přístup zamítnut', 'saw-visitors'),
                array('response' => 403)
            );
        }

        return;
    }

    wp_die(
        __('Nemáte oprávnění k přístupu k oddělením.', 'saw-visitors'),
        __('Přístup zamítnut', 'saw-visitors'),
        array('response' => 403)
    );
}

/**
 * Get login URL for role
 * 
 * @param string|null $role Role
 * @return string
 */
function saw_get_login_url($role = null) {
    $urls = array(
        'admin'    => home_url('/admin/login/'),
        'manager'  => home_url('/manager/login/'),
        'terminal' => home_url('/terminal/login/'),
    );

    if ($role && isset($urls[$role])) {
        return $urls[$role];
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($request_uri, '/admin/') !== false) {
        return $urls['admin'];
    }
    if (strpos($request_uri, '/manager/') !== false) {
        return $urls['manager'];
    }
    if (strpos($request_uri, '/terminal/') !== false) {
        return $urls['terminal'];
    }

    return $urls['admin'];
}

/**
 * Verify AJAX nonce
 * 
 * @param string $action Action name
 * @return void
 */
function saw_verify_ajax_nonce($action) {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (!wp_verify_nonce($nonce, $action)) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action'     => 'ajax_nonce_failed',
                'details'    => sprintf('Neplatný nonce pro akci: %s', $action),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
            ));
        }

        wp_send_json_error(array(
            'message' => __('Bezpečnostní kontrola selhala. Obnovte stránku a zkuste to znovu.', 'saw-visitors'),
            'code'    => 'nonce_failed',
        ));
    }
}

/**
 * Verify POST nonce
 * 
 * @param string $action Action name
 * @param string $name   Nonce field name
 * @return void
 */
function saw_verify_post_nonce($action, $name = '_wpnonce') {
    $nonce = isset($_POST[$name]) ? sanitize_text_field($_POST[$name]) : '';

    if (!wp_verify_nonce($nonce, $action)) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action'     => 'post_nonce_failed',
                'details'    => sprintf('Neplatný nonce pro akci: %s', $action),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
            ));
        }

        wp_die(
            __('Bezpečnostní kontrola selhala.', 'saw-visitors'),
            __('Chyba', 'saw-visitors'),
            array('response' => 403)
        );
    }
}

/**
 * Get current SAW user
 * 
 * @return object|null
 */
function saw_get_current_user() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->get_current_user();
}

/**
 * Get current customer ID
 * 
 * @return int|null
 */
function saw_get_current_customer_id() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->get_current_customer_id();
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function saw_is_logged_in() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->check_auth();
}

/**
 * Check if user is admin
 * 
 * @return bool
 */
function saw_is_admin() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->is_admin();
}

/**
 * Check if user is manager
 * 
 * @return bool
 */
function saw_is_manager() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->is_manager();
}

/**
 * Check if user is terminal
 * 
 * @return bool
 */
function saw_is_terminal() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth->is_terminal();
}

/**
 * Check if user is Super Admin
 * 
 * @return bool
 */
function saw_is_super_admin() {
    return current_user_can('manage_options');
}

/**
 * Rate limit check for AJAX
 * 
 * @param string $action Action name
 * @param int    $max    Maximum requests
 * @param int    $window Time window in seconds
 * @return void
 */
function saw_ajax_rate_limit($action, $max = 10, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'saw_rate_limit_' . md5($ip . $action);
    
    $count = (int) get_transient($key);

    if ($count >= $max) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log(array(
                'action'     => 'ajax_rate_limit_exceeded',
                'details'    => sprintf('Rate limit překročen pro akci: %s', $action),
                'ip_address' => $ip,
            ));
        }

        wp_send_json_error(array(
            'message' => __('Příliš mnoho requestů. Zkuste to za chvíli.', 'saw-visitors'),
            'code'    => 'rate_limit_exceeded',
        ));
    }

    set_transient($key, $count + 1, $window);
}

/**
 * Escape helper for templates
 * 
 * @param string $text Text to escape
 * @return string
 */
function saw_esc($text) {
    return esc_html($text);
}

/**
 * URL helper for templates
 * 
 * @param string $url URL to escape
 * @return string
 */
function saw_esc_url($url) {
    return esc_url($url);
}

/**
 * Attr helper for templates
 * 
 * @param string $attr Attribute to escape
 * @return string
 */
function saw_esc_attr($attr) {
    return esc_attr($attr);
}