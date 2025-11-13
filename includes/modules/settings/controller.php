<?php
/**
 * Settings Module Controller
 *
 * @package SAW_Visitors
 * @subpackage Modules\Settings
 * @since 4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

class SAW_Module_Settings_Controller extends SAW_Base_Controller {
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
    }
    
    public function index() {
        echo '<!DOCTYPE html><html><head><title>Settings</title></head><body>';
        echo '<h1>✅ Settings modul funguje!</h1>';
        echo '<p>Path: ' . __DIR__ . '</p>';
        echo '<p>Entity: ' . $this->entity . '</p>';
        echo '</body></html>';
        exit;
    }
}
