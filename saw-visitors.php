<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO (multi-tenant).
 * Version: 4.8.2
 * Author: SAW
 * Text Domain: saw-visitors
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

define( 'SAW_DEBUG', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SAW_VISITORS_VERSION', '4.8.2' );
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAW_DB_PREFIX', 'saw_' );

// ========================================
// EARLY COMPONENT INITIALIZATION FOR AJAX
// ========================================
if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-component-manager.php')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-component-manager.php';
    SAW_Component_Manager::instance();
}

// ========================================
// 🚀 UNIVERSAL AJAX HANDLERS
// Funguje pro VŠECHNY moduly (customers, branches, departments, users, atd.)
// ========================================

/**
 * NOVÝ Univerzální AJAX handler
 * Zpracuje VŠECHNY akce ve formátu:
 * wp_ajax_saw_{metoda}_{modul}
 *
 * Příklady:
 * - saw_load_sidebar_customers -> $controller->ajax_load_sidebar()
 * - saw_delete_branches -> $controller->ajax_delete()
 * - saw_get_gps_coordinates_branches -> $controller->ajax_get_gps_coordinates()
 */
function saw_universal_ajax_handler() {
    // Bezpečné načtení a sanitace názvu akce
    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';

    // Extrahování metody a modulu
    // Vzor: saw_ (metoda) _ (modul)
    if (!preg_match('/^saw_([a-z_]+)_(.+)$/', $action, $matches)) {
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('[SAW] Invalid AJAX action format: ' . $action);
        }
        wp_send_json_error(['message' => __('Invalid action format', 'saw-visitors')], 400);
        return;
    }

    $raw_method = sanitize_key($matches[1]); // např. 'load_sidebar', 'delete', 'get_gps_coordinates'
    $module     = sanitize_key($matches[2]); // např. 'customers', 'branches'

    // Převedení na název metody v controlleru (ajax_load_sidebar, ajax_delete, atd.)
    $method_name = 'ajax_' . $raw_method;

    if (defined('SAW_DEBUG') && SAW_DEBUG) {
        error_log("[SAW] AJAX Handler: Module '{$module}', Method '{$method_name}'");
    }

    // Načtení základních tříd pro modulový systém a kontext/permissions
    if (!class_exists('SAW_Module_Loader')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-loader.php';
    }

    if (!class_exists('SAW_Context')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
    }

    if (!class_exists('SAW_Permissions')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
    }

    // ==========================
    // 🔒 NONCE VALIDACE
    // ==========================
    $nonce_value = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    /**
     * Umožňuje rozšířit seznam povolených nonce „akcí" pro univerzální AJAX.
     *
     * @param array  $nonces       Výchozí seznam nonce akcí.
     * @param string $module       Modul (např. 'branches').
     * @param string $raw_method   Syrový název metody (např. 'delete', 'load_sidebar').
     * @param string $method_name  Název metody v controlleru (např. 'ajax_delete').
     * @param string $action       Celý název AJAX akce (např. 'saw_delete_branches').
     */
    $allowed_nonces = apply_filters(
        'saw_universal_ajax_allowed_nonces',
        ['saw_ajax_nonce', 'saw_admin_table_nonce'],
        $module,
        $raw_method,
        $method_name,
        $action
    );

    $nonce_verified = false;
    if (!empty($nonce_value)) {
        foreach ((array) $allowed_nonces as $nonce_action) {
            if (empty($nonce_action)) {
                continue;
            }

            if (wp_verify_nonce($nonce_value, $nonce_action)) {
                $nonce_verified = true;
                break;
            }
        }
    }

    if (!$nonce_verified) {
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('[SAW] AJAX Handler: Nonce verification failed for action ' . $action);
        }
        wp_send_json_error(['message' => __('Neplatný bezpečnostní token', 'saw-visitors')], 403);
        return;
    }

    try {
        // --------------------------
        // ⚙️ Modulová konfigurace
        // --------------------------
        $module_config = SAW_Module_Loader::load($module);

        if (!$module_config) {
            throw new Exception('Module configuration not found for: ' . $module);
        }

        // Inicializace kontextu (current customer, branch, role, atd.)
        SAW_Context::instance();
        $role = SAW_Context::get_role();

        // --------------------------
        // 🔐 Mapa logických akcí
        // --------------------------
        $logical_action = apply_filters(
            'saw_universal_ajax_action',
            null,
            $raw_method,
            $module_config,
            $module,
            $method_name,
            $action
        );

        if (empty($logical_action)) {
            // Heuristická mapa podle názvu metody
            $keyword_map = apply_filters(
                'saw_universal_ajax_action_keywords',
                [
                    'list'   => ['list', 'load', 'fetch', 'search', 'table', 'datatable', 'index'],
                    'view'   => ['get', 'view', 'detail', 'show', 'download', 'export'],
                    'create' => ['create', 'store', 'add', 'new', 'import'],
                    'edit'   => ['edit', 'update', 'save', 'set', 'assign', 'sync'],
                    'delete' => ['delete', 'remove', 'destroy', 'detach'],
                ],
                $module_config,
                $raw_method,
                $module,
                $method_name,
                $action
            );

            foreach ($keyword_map as $mapped_action => $keywords) {
                foreach ((array) $keywords as $keyword) {
                    if ($keyword && strpos($raw_method, $keyword) !== false) {
                        $logical_action = $mapped_action;
                        break 2;
                    }
                }
            }
        }

        if (empty($logical_action)) {
            $logical_action = 'view';
        }

        // --------------------------
        // 🔐 Kontrola oprávnění
        // --------------------------
        $has_permission = current_user_can('manage_options');

        if (!$has_permission) {
            if (empty($role)) {
                $role = 'guest';
            }

            $has_permission = SAW_Permissions::check($role, $module, $logical_action);
        }

        if (!$has_permission) {
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log("[SAW] AJAX Handler: Permission denied for role '{$role}' on {$module}->{$logical_action}");
            }
            wp_send_json_error(['message' => __('Nedostatečná oprávnění', 'saw-visitors')], 403);
            return;
        }

        // --------------------------
        // 🧱 Základní třídy
        // --------------------------
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';

        // Převedení názvu modulu na název třídy (customers -> Customers)
        $class_name = 'SAW_Module_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $module))) . '_Controller';

        if (!class_exists($class_name)) {
            throw new Exception("Controller class not found: {$class_name}");
        }

        // Vytvoření instance controlleru (tím se spustí jeho __construct)
        $controller = new $class_name();

        if (!method_exists($controller, $method_name)) {
            throw new Exception("Method {$method_name} not found in {$class_name}");
        }

        /**
         * Hook před zavoláním metody controlleru
         */
        do_action(
            'saw_universal_ajax_before_method',
            $module,
            $method_name,
            $logical_action,
            $module_config,
            $controller
        );

        // Zavolání požadované metody (např. $controller->ajax_delete())
        $controller->{$method_name}();

        /**
         * Hook po zavolání metody controlleru
         */
        do_action(
            'saw_universal_ajax_after_method',
            $module,
            $method_name,
            $logical_action,
            $module_config,
            $controller
        );

    } catch (Throwable $e) {
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('[SAW] AJAX Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()], 500);
    }
}

// ========================================
// Registrace AJAX handlerů
// ========================================

// Tady registruješ konkrétní akce, např.:
// add_action('wp_ajax_saw_load_sidebar_customers', 'saw_universal_ajax_handler');
// add_action('wp_ajax_saw_delete_branches', 'saw_universal_ajax_handler');




// ========================================
// BOOTSTRAP & AKTIVACE
// ========================================

if ( ! defined( 'SAW_DEBUG' ) ) {
    define( 'SAW_DEBUG', false );
}

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-activator.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-deactivator.php';

function saw_activate_plugin_silent() {
    if ( function_exists( 'ob_start' ) ) { ob_start(); }
    SAW_Activator::activate();
    if ( function_exists( 'ob_get_level' ) && ob_get_level() ) { ob_end_clean(); }
}
register_activation_hook( __FILE__, 'saw_activate_plugin_silent' );

function saw_deactivate_plugin_silent() {
    if ( function_exists( 'ob_start' ) ) { ob_start(); }
    SAW_Deactivator::deactivate();
    if ( function_exists( 'ob_get_level' ) && ob_get_level() ) { ob_end_clean(); }
}
register_deactivation_hook( __FILE__, 'saw_deactivate_plugin_silent' );

if ( ! function_exists( 'saw_log_error' ) ) {
    function saw_log_error( $msg, $ctx = [] ) {
        if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
            $line = '[SAW Visitors] ' . $msg;
            if ( $ctx ) {
                $line .= ' | ' . wp_json_encode( $ctx, JSON_UNESCAPED_UNICODE );
            }
            error_log( $line );
        }
    }
}

add_action( 'plugins_loaded', function () {
    // ✅ DEBUG
    $log = WP_CONTENT_DIR . '/saw-bootstrap-debug.log';
    file_put_contents($log, "\n" . date('H:i:s') . " ==================\n", FILE_APPEND);
    file_put_contents($log, "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n", FILE_APPEND);
    file_put_contents($log, "Bootstrap started\n", FILE_APPEND);

    try {
        file_put_contents($log, "Loading Module Style Manager\n", FILE_APPEND);
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-style-manager.php';
        SAW_Module_Style_Manager::get_instance();

        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/class-auto-setup.php')) {
            file_put_contents($log, "Loading training languages\n", FILE_APPEND);
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/class-auto-setup.php';
        }

        $main = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-visitors.php';
        file_put_contents($log, "Checking main class: $main\n", FILE_APPEND);

        if ( ! file_exists( $main ) ) {
            file_put_contents($log, "ERROR: Main class file not found\n", FILE_APPEND);
            saw_log_error( 'Missing main class file', [ 'path' => $main ] );
            return;
        }

        file_put_contents($log, "Requiring main class\n", FILE_APPEND);
        require_once $main;

        if ( ! class_exists( 'SAW_Visitors' ) ) {
            file_put_contents($log, "ERROR: SAW_Visitors class not found\n", FILE_APPEND);
            saw_log_error( 'Class SAW_Visitors not found after require' );
            return;
        }

        file_put_contents($log, "Getting SAW_Visitors instance\n", FILE_APPEND);
        $instance = SAW_Visitors::get_instance();

        if ( method_exists( $instance, 'run' ) ) {
            file_put_contents($log, "Calling run()\n", FILE_APPEND);
            $instance->run();
            file_put_contents($log, "Bootstrap completed successfully\n", FILE_APPEND);
        } else {
            file_put_contents($log, "ERROR: run() method not found\n", FILE_APPEND);
        }
    } catch ( Throwable $t ) {
        file_put_contents($log, "EXCEPTION: " . $t->getMessage() . "\n", FILE_APPEND);
        file_put_contents($log, "File: " . $t->getFile() . "\n", FILE_APPEND);
        file_put_contents($log, "Line: " . $t->getLine() . "\n", FILE_APPEND);

        saw_log_error( 'Bootstrap exception', [
            'message' => $t->getMessage(),
            'file'    => $t->getFile(),
            'line'    => $t->getLine(),
        ] );
    }
}, 20 );