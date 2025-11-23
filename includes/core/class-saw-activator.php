<?php
/**
 * SAW Activator - Plugin Activation Handler
 *
 * Handles plugin activation tasks including database setup,
 * role creation, default data insertion, and permissions initialization.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activator class
 *
 * @since 1.0.0
 */
class SAW_Activator {

    /**
     * Activation handler
     *
     * Runs all necessary setup tasks when plugin is activated.
     *
     * @since 1.0.0
     */
    public static function activate() {
        self::check_requirements();
        self::create_custom_roles();
        self::create_database_tables();
        self::insert_default_permissions();
        self::create_upload_directories();
        self::set_default_options();
        self::register_and_flush_rewrite_rules();
    }

    /**
     * Check system requirements
     *
     * Verifies PHP and WordPress versions meet minimum requirements.
     *
     * @since 1.0.0
     */
    private static function check_requirements() {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            wp_die(
                sprintf(
                    /* translators: %s: Current PHP version */
                    esc_html__('SAW Visitors requires PHP 8.1 or higher. Your version: %s', 'saw-visitors'),
                    esc_html(PHP_VERSION)
                ),
                esc_html__('Plugin Activation Error', 'saw-visitors'),
                ['back_link' => true]
            );
        }
        
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            wp_die(
                sprintf(
                    /* translators: %s: Current WordPress version */
                    esc_html__('SAW Visitors requires WordPress 6.0 or higher. Your version: %s', 'saw-visitors'),
                    esc_html($wp_version)
                ),
                esc_html__('Plugin Activation Error', 'saw-visitors'),
                ['back_link' => true]
            );
        }
    }

    /**
     * Create custom WordPress roles
     *
     * Creates SAW-specific roles with appropriate capabilities.
     *
     * @since 1.0.0
     */
    private static function create_custom_roles() {
        add_role('saw_admin', __('SAW Admin', 'saw-visitors'), [
            'read'              => true,
            'saw_access'        => true,
            'saw_manage_users'  => true
        ]);
        
        add_role('saw_super_manager', __('SAW Super Manager', 'saw-visitors'), [
            'read'       => true,
            'saw_access' => true
        ]);
        
        add_role('saw_manager', __('SAW Manager', 'saw-visitors'), [
            'read'       => true,
            'saw_access' => true
        ]);
        
        add_role('saw_terminal', __('SAW Terminal', 'saw-visitors'), [
            'read'       => true,
            'saw_access' => true
        ]);
        
        $role = get_role('saw_admin');
        if ($role && !$role->has_cap('saw_manage_users')) {
            $role->add_cap('saw_manage_users');
        }
    }

    /**
     * Create database tables
     *
     * Uses SAW_Installer class to create all required tables.
     *
     * @since 1.0.0
     */
    private static function create_database_tables() {
        $installer_file = SAW_VISITORS_PLUGIN_DIR . 'includes/database/class-saw-installer.php';
        
        if (!file_exists($installer_file)) {
            return;
        }
        
        require_once $installer_file;
        
        if (class_exists('SAW_Installer')) {
            SAW_Installer::install();
        }
    }

    /**
     * Insert default permissions
     *
     * Loads and inserts permissions from schema file.
     *
     * @since 1.0.0
     */
    private static function insert_default_permissions() {
        $permissions_class = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        $schema_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/permissions-schema.php';
        
        if (!file_exists($permissions_class) || !file_exists($schema_file)) {
            return;
        }
        
        require_once $permissions_class;
        
        if (!class_exists('SAW_Permissions')) {
            return;
        }
        
        $schema = require $schema_file;
        
        if (empty($schema) || !is_array($schema)) {
            return;
        }
        
        SAW_Permissions::bulk_insert_from_schema($schema);
    }

    /**
     * Create upload directories
     *
     * Creates necessary directories for file uploads.
     *
     * @since 1.0.0
     */
    private static function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/saw-visitor-docs';
        
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }
        
        $subdirs = ['materials', 'visitor-uploads', 'risk-docs'];
        
        foreach ($subdirs as $subdir) {
            $path = $base_dir . '/' . $subdir;
            
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
        
        $customers_dir = $upload_dir['basedir'] . '/saw-customers';
        if (!file_exists($customers_dir)) {
            wp_mkdir_p($customers_dir);
        }
    }

    /**
     * Set default WordPress options
     *
     * @since 1.0.0
     */
    private static function set_default_options() {
        add_option('saw_db_version', SAW_VISITORS_VERSION);
        add_option('saw_plugin_activated', current_time('mysql'));
    }

    /**
     * Register and flush rewrite rules
     *
     * Sets up custom URL routing for the plugin.
     *
     * @since 1.0.0
     */
    private static function register_and_flush_rewrite_rules() {
        $router_file = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-router.php';
        
        if (!file_exists($router_file)) {
            return;
        }
        
        require_once $router_file;
        
        if (!class_exists('SAW_Router')) {
            return;
        }
        
        $router = new SAW_Router();
        
        if (method_exists($router, 'register_routes')) {
            $router->register_routes();
        }
        
        // Register invitation router rewrite rules
        $invitation_router_file = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/invitation-router.php';
        if (file_exists($invitation_router_file)) {
            require_once $invitation_router_file;
            
            if (class_exists('SAW_Invitation_Router')) {
                $invitation_router = new SAW_Invitation_Router();
                if (method_exists($invitation_router, 'register_routes')) {
                    $invitation_router->register_routes();
                }
            }
        }
        
        flush_rewrite_rules();
    }
}
