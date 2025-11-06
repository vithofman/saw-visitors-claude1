<?php
/**
 * SAW Middleware Functions
 *
 * Route protection and permission checking with AJAX support.
 * Handles authentication, authorization, and customer isolation.
 *
 * @package    SAW_Visitors
 * @subpackage Middleware
 * @version    2.1.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get or initialize SAW_Auth instance
 *
 * Helper to avoid repeated global declarations and initialization.
 *
 * @since 2.1.0
 * @return SAW_Auth
 */
function saw_get_auth_instance() {
    global $saw_auth;

    if (!isset($saw_auth)) {
        $saw_auth = new SAW_Auth();
    }

    return $saw_auth;
}

/**
 * Get sanitized IP address
 *
 * @since 2.1.0
 * @return string Sanitized IP address
 */
function saw_get_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) 
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) 
        : '';
}

/**
 * Get sanitized request URI
 *
 * @since 2.1.0
 * @return string Sanitized request URI
 */
function saw_get_request_uri() {
    return isset($_SERVER['REQUEST_URI']) 
        ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
        : '';
}

/**
 * Require authentication (any SAW role)
 *
 * Checks if user is authenticated. For AJAX requests, returns JSON error.
 * For regular requests, redirects to login page.
 *
 * @since 1.0.0
 * @param string|null $role Optional specific role to check
 * @return void
 */
function saw_require_auth($role = null) {
    $saw_auth = saw_get_auth_instance();

    if (!$saw_auth->check_auth()) {
        // Handle AJAX requests with JSON response
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Nejste přihlášen', 'saw-visitors'),
                'code' => 'not_authenticated'
            ]);
            exit;
        }
        
        // Handle regular requests with redirect
        $redirect_url = saw_get_login_url($role);
        
        $request_uri = saw_get_request_uri();
        if (!empty($request_uri)) {
            $redirect_url = add_query_arg('redirect_to', urlencode($request_uri), $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    // Check specific role if provided
    if ($role !== null) {
        $current_user = $saw_auth->get_current_user();
        
        if ($current_user->role !== $role) {
            if (wp_doing_ajax()) {
                wp_send_json_error([
                    'message' => __('Nemáte oprávnění k této akci', 'saw-visitors'),
                    'code' => 'insufficient_permissions'
                ]);
                exit;
            }
            
            wp_die(
                __('Nemáte oprávnění k přístupu na tuto stránku.', 'saw-visitors'),
                __('Přístup zamítnut', 'saw-visitors'),
                ['response' => 403]
            );
        }
    }
}

/**
 * Require admin role
 *
 * @since 1.0.0
 * @return void
 */
function saw_require_admin() {
    $saw_auth = saw_get_auth_instance();

    saw_require_auth('admin');

    if (!$saw_auth->is_admin()) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Tato akce je dostupná pouze pro administrátory', 'saw-visitors'),
                'code' => 'admin_required'
            ]);
            exit;
        }
        
        wp_die(
            __('Tato stránka je dostupná pouze pro administrátory.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            ['response' => 403]
        );
    }
}

/**
 * Require manager role
 *
 * @since 1.0.0
 * @return void
 */
function saw_require_manager() {
    $saw_auth = saw_get_auth_instance();

    saw_require_auth('manager');

    if (!$saw_auth->is_manager()) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Tato akce je dostupná pouze pro manažery', 'saw-visitors'),
                'code' => 'manager_required'
            ]);
            exit;
        }
        
        wp_die(
            __('Tato stránka je dostupná pouze pro manažery.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            ['response' => 403]
        );
    }
}

/**
 * Require terminal role
 *
 * @since 1.0.0
 * @return void
 */
function saw_require_terminal() {
    $saw_auth = saw_get_auth_instance();

    saw_require_auth('terminal');

    if (!$saw_auth->is_terminal()) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Tato akce je dostupná pouze pro terminály', 'saw-visitors'),
                'code' => 'terminal_required'
            ]);
            exit;
        }
        
        wp_die(
            __('Tato stránka je dostupná pouze pro terminály.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            ['response' => 403]
        );
    }
}

/**
 * Require Super Admin (WordPress admin)
 *
 * @since 1.0.0
 * @return void
 */
function saw_require_super_admin() {
    if (!current_user_can('manage_options')) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Tato akce je dostupná pouze pro Super Adminy', 'saw-visitors'),
                'code' => 'super_admin_required'
            ]);
            exit;
        }
        
        wp_die(
            __('Tato stránka je dostupná pouze pro Super Adminy.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            ['response' => 403]
        );
    }
}

/**
 * Check customer isolation
 *
 * Ensures user can only access data from their customer.
 * Super admins bypass this check.
 *
 * @since 1.0.0
 * @param int $customer_id Customer ID to check access for
 * @return void
 */
function saw_require_customer($customer_id) {
    $saw_auth = saw_get_auth_instance();

    saw_require_auth();

    if ($saw_auth->is_super_admin()) {
        return;
    }

    if (!$saw_auth->check_customer_isolation($customer_id)) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'      => 'customer_isolation_violation',
                'user_id'     => $saw_auth->get_current_user()->id ?? null,
                'customer_id' => $saw_auth->get_current_customer_id(),
                'details'     => sprintf('Pokus o přístup k datům zákazníka %d', $customer_id),
                'ip_address'  => saw_get_client_ip(),
            ]);
        }

        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Nemáte oprávnění k přístupu k těmto datům', 'saw-visitors'),
                'code' => 'customer_isolation_violation'
            ]);
            exit;
        }

        wp_die(
            __('Nemáte oprávnění k přístupu k těmto datům.', 'saw-visitors'),
            __('Přístup zamítnut', 'saw-visitors'),
            ['response' => 403]
        );
    }
}

/**
 * Check department access for managers
 *
 * Ensures managers can only access their assigned departments.
 * Admins and super admins bypass this check.
 *
 * @since 1.0.0
 * @param int $department_id Department ID to check access for
 * @return void
 */
function saw_require_department_access($department_id) {
    global $wpdb;
    
    $saw_auth = saw_get_auth_instance();

    saw_require_auth();

    $current_user = $saw_auth->get_current_user();

    // Admins and super admins have full access
    if ($saw_auth->is_super_admin() || $saw_auth->is_admin()) {
        return;
    }

    // Check manager's department access
    if ($saw_auth->is_manager()) {
        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE user_id = %d AND department_id = %d",
            $wpdb->prefix . 'saw_user_departments',
            $current_user->id,
            $department_id
        ));

        if (!$has_access) {
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log([
                    'action'      => 'department_access_violation',
                    'user_id'     => $current_user->id,
                    'customer_id' => $current_user->customer_id,
                    'details'     => sprintf('Manager nemá přístup k oddělení %d', $department_id),
                    'ip_address'  => saw_get_client_ip(),
                ]);
            }

            if (wp_doing_ajax()) {
                wp_send_json_error([
                    'message' => __('Nemáte oprávnění k přístupu k tomuto oddělení', 'saw-visitors'),
                    'code' => 'department_access_violation'
                ]);
                exit;
            }

            wp_die(
                __('Nemáte oprávnění k přístupu k tomuto oddělení.', 'saw-visitors'),
                __('Přístup zamítnut', 'saw-visitors'),
                ['response' => 403]
            );
        }

        return;
    }

    // Other roles don't have department access
    if (wp_doing_ajax()) {
        wp_send_json_error([
            'message' => __('Nemáte oprávnění k přístupu k oddělením', 'saw-visitors'),
            'code' => 'departments_access_denied'
        ]);
        exit;
    }

    wp_die(
        __('Nemáte oprávnění k přístupu k oddělením.', 'saw-visitors'),
        __('Přístup zamítnut', 'saw-visitors'),
        ['response' => 403]
    );
}

/**
 * Get login URL for role
 *
 * Returns appropriate login URL based on role or current request URI.
 *
 * @since 1.0.0
 * @param string|null $role Role name
 * @return string Login URL
 */
function saw_get_login_url($role = null) {
    $urls = [
        'admin'    => home_url('/admin/login/'),
        'manager'  => home_url('/manager/login/'),
        'terminal' => home_url('/terminal/login/'),
    ];

    if ($role && isset($urls[$role])) {
        return $urls[$role];
    }

    $request_uri = saw_get_request_uri();

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
 * Validates nonce for AJAX requests and logs failures.
 *
 * @since 1.0.0
 * @param string $action Action name for nonce verification
 * @return void
 */
function saw_verify_ajax_nonce($action) {
    $nonce = isset($_POST['nonce']) 
        ? sanitize_text_field(wp_unslash($_POST['nonce'])) 
        : '';

    if (!wp_verify_nonce($nonce, $action)) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'     => 'ajax_nonce_failed',
                'details'    => sprintf('Neplatný nonce pro akci: %s', $action),
                'ip_address' => saw_get_client_ip(),
            ]);
        }

        wp_send_json_error([
            'message' => __('Bezpečnostní kontrola selhala. Obnovte stránku a zkuste to znovu.', 'saw-visitors'),
            'code'    => 'nonce_failed',
        ]);
    }
}

/**
 * Verify POST nonce
 *
 * Validates nonce for POST requests and logs failures.
 *
 * @since 1.0.0
 * @param string $action Action name for nonce verification
 * @param string $name   Nonce field name (default: '_wpnonce')
 * @return void
 */
function saw_verify_post_nonce($action, $name = '_wpnonce') {
    $nonce = isset($_POST[$name]) 
        ? sanitize_text_field(wp_unslash($_POST[$name])) 
        : '';

    if (!wp_verify_nonce($nonce, $action)) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'     => 'post_nonce_failed',
                'details'    => sprintf('Neplatný nonce pro akci: %s', $action),
                'ip_address' => saw_get_client_ip(),
            ]);
        }

        wp_die(
            __('Bezpečnostní kontrola selhala.', 'saw-visitors'),
            __('Chyba', 'saw-visitors'),
            ['response' => 403]
        );
    }
}

/**
 * Get current SAW user
 *
 * @since 1.0.0
 * @return object|null User object or null
 */
function saw_get_current_user() {
    $saw_auth = saw_get_auth_instance();
    return $saw_auth->get_current_user();
}

/**
 * Get current customer ID
 *
 * @since 1.0.0
 * @return int|null Customer ID or null
 */
function saw_get_current_customer_id() {
    $saw_auth = saw_get_auth_instance();
    return $saw_auth->get_current_customer_id();
}

/**
 * Check if user is logged in
 *
 * @since 1.0.0
 * @return bool True if authenticated
 */
function saw_is_logged_in() {
    $saw_auth = saw_get_auth_instance();
    return $saw_auth->check_auth();
}

/**
 * Check if user is admin
 *
 * @since 1.0.0
 * @return bool True if admin
 */
function saw_is_admin() {
    $saw_auth = saw_get_auth_instance();
    return $saw_auth->is_admin();
}

/**
 * Check if user is manager
 *
 * @since 1.0.0
 * @return bool True if manager
 */
function saw_is_manager() {
    $saw_auth = saw_get_auth_instance();
    return $saw_auth->is_manager();
}

/**
 * Check if user is terminal
 *
 * @since 1.0.0
 * @return bool True if terminal
 */
function saw_is_terminal() {
    $saw_auth = saw_get_auth_instance();
    return $saw_auth->is_terminal();
}

/**
 * Check if user is Super Admin
 *
 * @since 1.0.0
 * @return bool True if super admin
 */
function saw_is_super_admin() {
    return current_user_can('manage_options');
}

/**
 * Rate limit check for AJAX
 *
 * Implements simple rate limiting using transients.
 * Logs rate limit violations for security monitoring.
 *
 * @since 1.0.0
 * @param string $action Action name to rate limit
 * @param int    $max    Maximum requests allowed (default: 10)
 * @param int    $window Time window in seconds (default: 60)
 * @return void
 */
function saw_ajax_rate_limit($action, $max = 10, $window = 60) {
    $ip = saw_get_client_ip();
    $key = 'saw_rate_limit_' . md5($ip . $action);
    
    $count = (int) get_transient($key);

    if ($count >= $max) {
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'     => 'ajax_rate_limit_exceeded',
                'details'    => sprintf('Rate limit překročen pro akci: %s', $action),
                'ip_address' => $ip,
            ]);
        }

        wp_send_json_error([
            'message' => __('Příliš mnoho requestů. Zkuste to za chvíli.', 'saw-visitors'),
            'code'    => 'rate_limit_exceeded',
        ]);
    }

    set_transient($key, $count + 1, $window);
}

/**
 * Escape helper for templates
 *
 * @since 1.0.0
 * @param string $text Text to escape
 * @return string Escaped text
 */
function saw_esc($text) {
    return esc_html($text);
}

/**
 * URL helper for templates
 *
 * @since 1.0.0
 * @param string $url URL to escape
 * @return string Escaped URL
 */
function saw_esc_url($url) {
    return esc_url($url);
}

/**
 * Attribute helper for templates
 *
 * @since 1.0.0
 * @param string $attr Attribute to escape
 * @return string Escaped attribute
 */
function saw_esc_attr($attr) {
    return esc_attr($attr);
}